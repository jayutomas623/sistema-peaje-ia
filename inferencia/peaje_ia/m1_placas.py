"""M1 · Detección de placas (YOLOv9-t end2end en ONNX) + OCR (fast-plate-ocr CCT-XS).

Basado en python/placas/detector.py y placas_fastocr.py de la guía del docente
(placas_entornos_v2.html). Diferencias:
  * La sesión ONNX y el lector se crean UNA sola vez (no por petición).
  * Se elige la placa principal (mayor confianza x área) y se normaliza al formato boliviano.
"""
from __future__ import annotations

from dataclasses import dataclass
from pathlib import Path

import cv2
import numpy as np
import onnxruntime as ort

from . import config
from .normalizacion import normalizar_placa

LADO = 384


@dataclass
class Placa:
    texto: str
    texto_crudo: str
    formato_bolivia: bool
    confianza: float          # confianza combinada (detección x lectura)
    confianza_deteccion: float
    confianza_lectura: float | None
    bbox: list[int]

    def a_json(self) -> dict:
        return {
            "texto": self.texto or None,
            "texto_crudo": self.texto_crudo or None,
            "formato_bolivia": self.formato_bolivia,
            "confianza": round(self.confianza, 3),
            "confianza_deteccion": round(self.confianza_deteccion, 3),
            "confianza_lectura": None if self.confianza_lectura is None else round(self.confianza_lectura, 3),
            "bbox": self.bbox,
        }


class LectorPlacas:
    """Detector + lector de placas. Thread-safe si se usa bajo el Lock del servidor."""

    def __init__(self, carpeta: Path | None = None, umbral: float | None = None):
        carpeta = Path(carpeta or config.MODELOS_DIR)
        self.umbral = config.M1_UMBRAL if umbral is None else umbral
        opciones = ort.SessionOptions()
        opciones.log_severity_level = 3
        # M1 es muy liviano: se ejecuta en CPU con ONNX Runtime (evita conflicto onnxruntime-gpu/torch)
        self.sesion = ort.InferenceSession(str(carpeta / config.M1_DETECTOR), opciones,
                                           providers=["CPUExecutionProvider"])
        self.entrada = self.sesion.get_inputs()[0].name
        from fast_plate_ocr import LicensePlateRecognizer  # import diferido (pesado)

        self.ocr = LicensePlateRecognizer(
            onnx_model_path=str(carpeta / config.M1_OCR),
            plate_config_path=str(carpeta / config.M1_OCR_CONFIG),
        )
        self.nombre = "yolo-v9-t-384 (ONNX) + fast-plate-ocr cct_xs_v2_global"

    # ------------------------------------------------------------------ detección
    def detectar(self, imagen: np.ndarray) -> list[tuple[int, int, int, int, float]]:
        alto, ancho = imagen.shape[:2]
        escala = min(LADO / alto, LADO / ancho)
        nuevo_ancho, nuevo_alto = round(ancho * escala), round(alto * escala)
        margen_x, margen_y = (LADO - nuevo_ancho) / 2, (LADO - nuevo_alto) / 2
        lienzo = cv2.resize(imagen, (nuevo_ancho, nuevo_alto), interpolation=cv2.INTER_LINEAR)
        lienzo = cv2.copyMakeBorder(
            lienzo, round(margen_y - 0.1), round(margen_y + 0.1), round(margen_x - 0.1), round(margen_x + 0.1),
            cv2.BORDER_CONSTANT, value=(114, 114, 114),
        )
        entrada = (lienzo[:, :, ::-1].transpose(2, 0, 1) / 255.0).astype(np.float32)[None]
        salida = self.sesion.run(None, {self.entrada: entrada})[0]
        cajas = []
        for _, x1, y1, x2, y2, _, confianza in salida:
            if confianza < self.umbral:
                continue
            x1, x2 = [(v - margen_x) / escala for v in (x1, x2)]
            y1, y2 = [(v - margen_y) / escala for v in (y1, y2)]
            x1, y1 = int(max(0, x1)), int(max(0, y1))
            x2, y2 = int(min(ancho, x2)), int(min(alto, y2))
            if x2 - x1 > 4 and y2 - y1 > 4:
                cajas.append((x1, y1, x2, y2, float(confianza)))
        return cajas

    # ------------------------------------------------------------------ lectura
    def leer(self, recorte_bgr: np.ndarray) -> tuple[str, float | None]:
        rgb = cv2.cvtColor(recorte_bgr, cv2.COLOR_BGR2RGB)
        try:
            pred = self.ocr.run_one(rgb, return_confidence=True)
            texto = getattr(pred, "plate", pred)
            probs = getattr(pred, "char_probs", None)
        except (AttributeError, TypeError):
            # Compatibilidad con versiones antiguas de fast-plate-ocr: run() -> (textos, probs)
            res = self.ocr.run(rgb, return_confidence=True)
            texto, probs = (res[0][0], res[1][0]) if isinstance(res, tuple) else (res[0], None)
        texto = str(texto).replace("_", "")
        conf = None
        if probs is not None:
            p = np.asarray(probs, dtype=float).reshape(-1)
            p = p[: max(1, len(texto))]
            conf = float(p.mean()) if p.size else None
        return texto, conf

    def inferir(self, imagen: np.ndarray) -> tuple[Placa | None, list[Placa]]:
        """Devuelve (placa_principal, todas_las_placas)."""
        placas: list[Placa] = []
        for x1, y1, x2, y2, cdet in self.detectar(imagen):
            crudo, clec = self.leer(imagen[y1:y2, x1:x2])
            texto, valido = normalizar_placa(crudo)
            comb = cdet * (clec if clec is not None else 0.8)
            placas.append(Placa(texto, crudo.strip(), valido, comb, cdet, clec,
                                [x1, y1, x2, y2]))
        if not placas:
            return None, []

        def puntaje(p: Placa) -> float:
            area = (p.bbox[2] - p.bbox[0]) * (p.bbox[3] - p.bbox[1])
            return (1.5 if p.formato_bolivia else 1.0) * p.confianza * np.sqrt(max(area, 1))

        principal = max(placas, key=puntaje)
        return principal, placas
