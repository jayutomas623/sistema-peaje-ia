"""M4 · Conteo de pasajeros por segmentación de instancias (clase person).

RESTRICCIÓN DE PRIVACIDAD (obligatoria):
  * Solo se DETECTA y CUENTA a las personas visibles dentro del vehículo.
  * NO hay reconocimiento facial, ni identificación, ni embeddings de rostro.
  * NUNCA se guardan recortes de personas ni de rostros: las máscaras viven solo en memoria
    y en la imagen anotada se pintan OPACAS (ocultan el rostro) junto al número de pasajeros.
"""
from __future__ import annotations

import numpy as np

from . import config
from .modelos_yolo import cargar_yolo
from .utilidades import contenida, dispositivo, resultados_yolo_seg

NOMBRES_PERSONA = {"person", "persona", "pasajero", "passenger", "conductor", "driver"}


class ContadorPasajeros:
    def __init__(self):
        self.device = dispositivo(config.DEVICE)
        if config.M4_PESOS.exists():
            self.modo, self.pesos = "entrenado", str(config.M4_PESOS)
        else:
            self.modo, self.pesos = "fallback-coco", config.M4_FALLBACK
        self.modelo = cargar_yolo(self.pesos)

    @property
    def nombre(self) -> str:
        return f"{self.pesos} ({self.modo})"

    def predecir_crudo(self, img: np.ndarray) -> list[dict]:
        r = self.modelo.predict(img, conf=config.M4_UMBRAL, device=self.device, verbose=False)[0]
        return resultados_yolo_seg(r)

    def inferir(self, img: np.ndarray, vehiculo: dict | None, dets: list[dict] | None = None) -> dict:
        dets = self.predecir_crudo(img) if dets is None else dets
        personas = [d for d in dets if d["nombre"].lower() in NOMBRES_PERSONA and d["conf"] >= config.M4_UMBRAL]
        dentro: list[dict] = []
        if vehiculo is not None:
            vmask = vehiculo["mask"]
            for p in personas:
                area = max(1, int(p["mask"].sum()))
                frac_mascara = float((p["mask"] & vmask).sum()) / area
                frac_caja = contenida(p["box"], vehiculo["box"])
                # Persona dentro del vehículo: su máscara intersecta la del vehículo y su caja cae
                # mayormente dentro de la caja del vehículo (descarta peatones al costado).
                if frac_mascara >= config.M4_MIN_INTERSECCION and frac_caja >= 0.6:
                    dentro.append(p)
        conteo = len(dentro)
        prom = float(np.mean([p["conf"] for p in dentro])) if dentro else None
        return {
            "conteo": conteo,
            "confianza_promedio": None if prom is None else round(prom, 3),
            "_mascaras": [p["mask"] for p in dentro],   # solo para dibujar; jamás se serializan
        }
