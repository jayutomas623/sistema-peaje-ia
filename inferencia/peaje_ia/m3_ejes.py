"""M3 · Keypoints de llantas (YOLO-pose, 5 keypoints) + conteo de ejes.

Adaptado del notebook del docente `keypoints_llantas_yolo_detectron2_v3.ipynb`:
  - limpiar_llantas  : descarta detecciones contenidas en otra (aro dentro de la llanta)
  - lado cercano     : solo se cuentan llantas con altura visible >= 65 % de la mayor
  - separación eje   : dos llantas a menos de 0.6 diámetros en x son el mismo eje (ruedas dobles)
  - mínimo 2 ejes    : no existen vehículos de 1 eje
  - vista frontal/posterior -> ejes = None (el backend usa los ejes registrados o la tarifa base)

Keypoints por llanta: 0 centro, 1 contacto, 2 superior, 3 izquierda, 4 derecha.
"""
from __future__ import annotations

import cv2
import numpy as np

from . import config
from .modelos_yolo import cargar_yolo, cargar_yoloe
from .utilidades import contenida, dispositivo, nms_simple

UMBRAL_CONTENCION = 0.7
UMBRAL_CERCANA = 0.65
SEPARACION_EJE = 0.6
PROMPTS_LLANTA = ["wheel", "tire", "car wheel"]


def altura_visible(l: dict) -> float:
    return max(1.0, float(l["kps"][1, 1] - l["kps"][2, 1]))   # contacto.y - superior.y


def limpiar_llantas(llantas: list[dict]) -> list[dict]:
    orden = sorted(llantas, key=lambda l: -(l["box"][2] - l["box"][0]) * (l["box"][3] - l["box"][1]))
    res: list[dict] = []
    for l in orden:
        if all(contenida(l["box"], r["box"]) < UMBRAL_CONTENCION for r in res):
            res.append(l)
    return res


def kps_de_mascara(mask: np.ndarray) -> np.ndarray | None:
    """5 keypoints geométricos a partir del contorno de una llanta segmentada (modo zero-shot)."""
    cnts, _ = cv2.findContours(mask.astype(np.uint8), cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_NONE)
    if not cnts:
        return None
    c = max(cnts, key=cv2.contourArea)
    if cv2.contourArea(c) < 8:
        return None
    pts = c.reshape(-1, 2).astype(float)
    if len(pts) >= 5:
        (cx, cy), _, _ = cv2.fitEllipse(c)
    else:
        cx, cy = pts.mean(0)
    k = np.array([[cx, cy], pts[pts[:, 1].argmax()], pts[pts[:, 1].argmin()],
                  pts[pts[:, 0].argmin()], pts[pts[:, 0].argmax()]])
    return np.concatenate([k, np.ones((5, 1))], axis=1)


def analizar_ejes(caja_vehiculo, llantas: list[dict]) -> tuple[int | None, str, str, list[dict]]:
    """Devuelve (ejes, vista, nota, llantas_cercanas)."""
    if not llantas:
        return None, "desconocida", "Sin llantas detectadas", []
    hmax = max(altura_visible(l) for l in llantas)
    cercanas = [l for l in llantas if altura_visible(l) >= UMBRAL_CERCANA * hmax]
    x1, y1, x2, y2 = caja_vehiculo
    proporcion = (x2 - x1) / max(1.0, y2 - y1)
    if len(cercanas) == 2 and proporcion < 1.6:
        a, b = cercanas
        angostas = all((l["kps"][4, 0] - l["kps"][3, 0]) / altura_visible(l) < 0.65 for l in cercanas)
        misma_altura = abs(a["kps"][1, 1] - b["kps"][1, 1]) < 0.15 * hmax
        if angostas and misma_altura:
            return None, "frontal/posterior", "No determinable en esta vista", cercanas
    cercanas = sorted(cercanas, key=lambda l: l["kps"][0, 0])
    xs = [float(l["kps"][0, 0]) for l in cercanas]
    diam = float(np.median([altura_visible(l) for l in cercanas]))
    ejes = 1 + sum(1 for p, q in zip(xs, xs[1:]) if q - p > SEPARACION_EJE * diam)
    redondez = float(np.median([(l["kps"][4, 0] - l["kps"][3, 0]) / altura_visible(l) for l in cercanas]))
    vista = "lateral" if redondez > 0.8 else "3/4"
    nota = ""
    if ejes < 2:
        ejes, nota = 2, "Mínimo asumido (solo se detectó 1 eje)"
    return ejes, vista, nota, cercanas


class DetectorEjes:
    def __init__(self):
        self.device = dispositivo(config.DEVICE)
        self.error = None
        if config.M3_PESOS.exists():
            self.modo, self.pesos = "entrenado", str(config.M3_PESOS)
            self.modelo = cargar_yolo(self.pesos)
        elif config.M3_USAR_YOLOE:
            self.modo, self.pesos = "fallback-yoloe-zero-shot", config.M3_FALLBACK_YOLOE
            try:
                self.modelo = cargar_yoloe(self.pesos, PROMPTS_LLANTA)
            except Exception as e:  # CLIP no instalado, sin internet, etc.
                self.modo, self.modelo, self.error = "no-disponible", None, f"YOLOE: {e}"
        else:
            self.modo, self.pesos, self.modelo = "no-disponible", None, None

    @property
    def nombre(self) -> str:
        return f"{self.pesos} ({self.modo})" if self.pesos else self.modo

    def _llantas(self, img: np.ndarray) -> list[dict]:
        if self.modelo is None:
            return []
        if self.modo == "entrenado":
            r = self.modelo.predict(img, conf=config.M3_UMBRAL, device=self.device, verbose=False)[0]
            llantas = []
            if r.keypoints is not None and len(r.boxes):
                xy = r.keypoints.xy.cpu().numpy()
                kc = r.keypoints.conf.cpu().numpy() if r.keypoints.conf is not None else np.ones(xy.shape[:2])
                for b, s, p, c in zip(r.boxes.xyxy.cpu().numpy(), r.boxes.conf.cpu().numpy(), xy, kc):
                    llantas.append({"conf": float(s), "box": b.tolist(),
                                    "kps": np.concatenate([p, c[:, None]], 1)})
            return llantas
        # Zero-shot: YOLOE segmenta llantas y se derivan keypoints geométricos
        r = self.modelo.predict(img, conf=config.M3_UMBRAL_LLANTA_YOLOE, imgsz=1024,
                                device=self.device, verbose=False)[0]
        llantas = []
        if r.boxes is None or len(r.boxes) == 0 or r.masks is None:
            return llantas
        alto, ancho = r.orig_shape
        for b, s, poly in zip(r.boxes.xyxy.cpu().numpy(), r.boxes.conf.cpu().numpy(), r.masks.xy):
            if len(poly) < 3:
                continue
            m = np.zeros((alto, ancho), np.uint8)
            cv2.fillPoly(m, [poly.astype(np.int32)], 1)
            k = kps_de_mascara(m)
            if k is not None:
                k[:, 2] = float(s)
                llantas.append({"conf": float(s), "box": b.tolist(), "kps": k})
        return nms_simple(llantas, 0.5)

    def inferir(self, img: np.ndarray, vehiculo: dict | None) -> dict:
        alto, ancho = img.shape[:2]
        llantas = limpiar_llantas(self._llantas(img))
        caja = vehiculo["box"] if vehiculo else [0, 0, ancho, alto]
        x1, y1, x2, y2 = caja
        w, h = x2 - x1, y2 - y1
        # Asociación llanta -> vehículo principal: centro dentro y en la parte baja de la caja
        if vehiculo:
            llantas = [l for l in llantas
                       if x1 - 0.05 * w <= l["kps"][0, 0] <= x2 + 0.05 * w
                       and y1 + 0.35 * h <= l["kps"][0, 1] <= y2 + 0.10 * h]
        if self.modelo is None:
            return {"ejes": None, "vista": "desconocida", "nota": "M3 no disponible", "llantas": [], "keypoints": []}
        ejes, vista, nota, cercanas = analizar_ejes(caja, llantas)
        keypoints = [[int(round(l["kps"][0, 0])), int(round(l["kps"][0, 1])), round(float(l["conf"]), 3)]
                     for l in sorted(cercanas, key=lambda l: l["kps"][0, 0])]
        return {"ejes": ejes, "vista": vista, "nota": nota, "llantas": llantas, "keypoints": keypoints}
