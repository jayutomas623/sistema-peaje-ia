"""M2 · Segmentación de instancias de vehículos (YOLO11-seg).

* Modo "entrenado": pesos propios m2_vehiculos_seg.pt (Roboflow + Colab, notebook 02).
* Modo "fallback-coco": yolo11s-seg.pt preentrenado en COCO (car→auto, bus→bus,
  truck→camion, motorcycle→moto). COCO no tiene "minibus": por eso se entrena M2.
"""
from __future__ import annotations

import numpy as np

from . import config
from .modelos_yolo import cargar_yolo
from .utilidades import dispositivo, nms_simple, resultados_yolo_seg

CATEGORIAS = ("moto", "auto", "minibus", "bus", "camion")

# Nombres de clase (dataset propio o COCO) -> categoría interna
NORMALIZAR = {
    "auto": "auto", "car": "auto", "auto particular": "auto", "automovil": "auto", "vagoneta": "auto",
    "sedan": "auto", "suv": "auto", "taxi": "auto", "jeep": "auto",
    "minibus": "minibus", "minibús": "minibus", "van": "minibus", "micro": "minibus", "trufi": "minibus",
    "bus": "bus", "autobus": "bus", "omnibus": "bus", "flota": "bus",
    "camion": "camion", "camión": "camion", "truck": "camion", "volqueta": "camion", "cisterna": "camion",
    "moto": "moto", "motorcycle": "moto", "motocicleta": "moto",
}
COCO_VEHICULOS = {"car", "bus", "truck", "motorcycle"}


class SegmentadorVehiculos:
    def __init__(self):
        self.device = dispositivo(config.DEVICE)
        if config.M2_PESOS.exists():
            self.modo, self.pesos = "entrenado", str(config.M2_PESOS)
        else:
            self.modo, self.pesos = "fallback-coco", config.M2_FALLBACK
        self.modelo = cargar_yolo(self.pesos)

    @property
    def nombre(self) -> str:
        return f"{self.pesos} ({self.modo})"

    def predecir_crudo(self, img: np.ndarray, conf: float | None = None) -> list[dict]:
        r = self.modelo.predict(img, conf=conf or config.M2_UMBRAL, device=self.device,
                                retina_masks=False, verbose=False)[0]
        return resultados_yolo_seg(r)

    def filtrar(self, dets: list[dict], alto: int, ancho: int) -> list[dict]:
        vehiculos = []
        for d in dets:
            nombre = d["nombre"].lower().strip()
            if self.modo == "fallback-coco" and nombre not in COCO_VEHICULOS:
                continue
            cat = NORMALIZAR.get(nombre)
            if cat is None or d["conf"] < config.M2_UMBRAL:
                continue
            area = float(d["mask"].sum()) / float(alto * ancho)
            vehiculos.append({**d, "categoria": cat, "area_mascara": area})
        return nms_simple(vehiculos, 0.7)

    def inferir(self, img: np.ndarray, dets: list[dict] | None = None) -> tuple[dict | None, list[dict]]:
        alto, ancho = img.shape[:2]
        dets = self.predecir_crudo(img) if dets is None else dets
        vehiculos = self.filtrar(dets, alto, ancho)
        if not vehiculos:
            return None, []
        # Vehículo principal: el de mayor máscara (el más cercano a la cámara de la caseta)
        principal = max(vehiculos, key=lambda v: v["area_mascara"] * (0.5 + v["conf"]))
        return principal, vehiculos

    @staticmethod
    def a_json(v: dict | None) -> dict | None:
        if v is None:
            return None
        return {
            "categoria": v["categoria"],
            "confianza": round(v["conf"], 3),
            "area_mascara": round(v["area_mascara"], 3),
            "bbox": [int(round(x)) for x in v["box"]],
        }
