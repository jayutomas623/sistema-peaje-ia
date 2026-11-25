"""Orquestador M1 → M2 → M3 → M4 que produce el JSON exacto del contrato /inferir."""
from __future__ import annotations

import platform
import threading
import time

import cv2
import numpy as np

from . import __version__, config
from .anotador import anotar, anotar_solo_placa
from .descargas import descargar_m1
from .m1_placas import LectorPlacas
from .m2_vehiculos import SegmentadorVehiculos
from .m3_ejes import DetectorEjes
from .m4_pasajeros import ContadorPasajeros


def decodificar(datos: bytes) -> np.ndarray:
    img = cv2.imdecode(np.frombuffer(datos, np.uint8), cv2.IMREAD_COLOR)
    if img is None:
        raise ValueError("No se pudo decodificar la imagen (se espera JPEG o PNG)")
    return img


class Pipeline:
    def __init__(self):
        self.lock = threading.Lock()   # una inferencia a la vez sobre la GPU
        self.errores: dict[str, str] = {}
        self.calentamiento_ms: float | None = None
        self.inicio = time.time()
        self.inferencias = 0
        self.m1 = self.m2 = self.m3 = self.m4 = None

    # ------------------------------------------------------------------ carga
    def cargar(self) -> None:
        try:
            descargar_m1(verbose=True)
            self.m1 = LectorPlacas()
        except Exception as e:
            self.errores["m1"] = str(e)
        for clave, fabrica in (("m2", SegmentadorVehiculos), ("m3", DetectorEjes), ("m4", ContadorPasajeros)):
            try:
                setattr(self, clave, fabrica())
            except Exception as e:
                self.errores[clave] = str(e)
        if self.m3 is not None and self.m3.error:
            self.errores["m3"] = self.m3.error
        self.calentar()

    def calentar(self) -> None:
        """La primera inferencia reserva memoria y compila kernels CUDA: se mide aparte."""
        img = np.full((480, 640, 3), 127, np.uint8)
        try:
            self.inferir(img, anotar_imagen=False)
            t0 = time.perf_counter()
            self.inferir(img, anotar_imagen=False)
            self.calentamiento_ms = round((time.perf_counter() - t0) * 1000, 1)
        except Exception as e:  # pragma: no cover
            self.errores["calentamiento"] = str(e)

    # ------------------------------------------------------------------ inferencia
    def inferir(self, img: np.ndarray, anotar_imagen: bool = True) -> dict:
        t0 = time.perf_counter()
        with self.lock:
            placa, _ = self.m1.inferir(img) if self.m1 else (None, [])
            # M2 y M4 comparten pesos en modo fallback: una sola pasada por la red
            dets_compartidas = None
            if self.m2 and self.m4 and self.m2.modelo is self.m4.modelo:
                dets_compartidas = self.m2.predecir_crudo(img, conf=min(config.M2_UMBRAL, config.M4_UMBRAL))
            vehiculo, _ = self.m2.inferir(img, dets_compartidas) if self.m2 else (None, [])
            ejes = self.m3.inferir(img, vehiculo) if self.m3 else None
            pasajeros = self.m4.inferir(img, vehiculo, dets_compartidas) if self.m4 else None
            anotada = anotar(img, placa, vehiculo, ejes, pasajeros) if anotar_imagen else None
        self.inferencias += 1
        return {
            "placa": placa.a_json() if placa else {
                "texto": None, "texto_crudo": None, "formato_bolivia": False, "confianza": 0.0, "bbox": None},
            "vehiculo": SegmentadorVehiculos.a_json(vehiculo),
            "ejes": ejes["ejes"] if ejes else None,
            "vista_vehiculo": ejes["vista"] if ejes else "desconocida",
            "keypoints": ejes["keypoints"] if ejes else [],
            "pasajeros": {
                "conteo": pasajeros["conteo"] if pasajeros else 0,
                "confianza_promedio": pasajeros["confianza_promedio"] if pasajeros else None,
            },
            "imagen_anotada_base64": anotada,
            "latencia_ms": round((time.perf_counter() - t0) * 1000, 1),
            "nota_ejes": ejes["nota"] if ejes else "",
            "modelos": self.modos(),
        }

    def inferir_placa(self, img: np.ndarray) -> dict:
        t0 = time.perf_counter()
        if self.m1 is None:
            raise RuntimeError("M1 no está cargado: " + self.errores.get("m1", ""))
        with self.lock:
            principal, todas = self.m1.inferir(img)
            anotada = anotar_solo_placa(img, todas)
        return {
            "placa": principal.a_json() if principal else None,
            "placas": [p.a_json() for p in todas],
            "legible": bool(principal and principal.texto and principal.formato_bolivia),
            "imagen_anotada_base64": anotada,
            "latencia_ms": round((time.perf_counter() - t0) * 1000, 1),
        }

    # ------------------------------------------------------------------ estado
    def modos(self) -> dict:
        return {
            "m1": "onnx" if self.m1 else "no-cargado",
            "m2": self.m2.modo if self.m2 else "no-cargado",
            "m3": self.m3.modo if self.m3 else "no-cargado",
            "m4": self.m4.modo if self.m4 else "no-cargado",
        }

    def salud(self) -> dict:
        gpu, cuda, torch_v = None, False, None
        try:
            import torch

            torch_v = torch.__version__
            cuda = torch.cuda.is_available()
            gpu = torch.cuda.get_device_name(0) if cuda else None
        except Exception:
            pass
        versiones = {"python": platform.python_version(), "torch": torch_v}
        for mod in ("ultralytics", "onnxruntime", "cv2", "fastapi", "fast_plate_ocr"):
            try:
                versiones[mod] = getattr(__import__(mod), "__version__", "instalado")
            except Exception:
                versiones[mod] = None
        fallback = [k for k, v in self.modos().items() if v.startswith("fallback") or v == "no-disponible"]
        return {
            "estado": "ok" if self.m1 and self.m2 else "degradado",
            "servicio": "peaje-ia-inferencia",
            "version": __version__,
            "gpu": {"cuda_disponible": cuda, "nombre": gpu},
            "modelos_cargados": {
                "m1_placas": self.m1.nombre if self.m1 else None,
                "m2_vehiculos": self.m2.nombre if self.m2 else None,
                "m3_ejes": self.m3.nombre if self.m3 else None,
                "m4_pasajeros": self.m4.nombre if self.m4 else None,
            },
            "modos": self.modos(),
            "modo_fallback": bool(fallback),
            "advertencia": (
                "Usando modelos preentrenados de respaldo para: " + ", ".join(fallback)
                + ". Copie los pesos entrenados en Colab a inferencia/pesos/." if fallback else None),
            "errores": self.errores or None,
            "latencia_calentamiento_ms": self.calentamiento_ms,
            "inferencias_atendidas": self.inferencias,
            "uptime_s": round(time.time() - self.inicio, 1),
            "versiones": versiones,
            "sistema": f"{platform.system()} {platform.release()}",
        }
