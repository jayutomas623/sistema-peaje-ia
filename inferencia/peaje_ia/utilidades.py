"""Utilidades geométricas compartidas por M2, M3 y M4."""
from __future__ import annotations

import cv2
import numpy as np


def dispositivo(preferido: str = "auto") -> str:
    if preferido != "auto":
        return preferido
    try:
        import torch

        return "cuda:0" if torch.cuda.is_available() else "cpu"
    except Exception:  # pragma: no cover - torch siempre está en el servidor
        return "cpu"


def iou(a, b) -> float:
    x1, y1 = max(a[0], b[0]), max(a[1], b[1])
    x2, y2 = min(a[2], b[2]), min(a[3], b[3])
    inter = max(0, x2 - x1) * max(0, y2 - y1)
    ua = (a[2] - a[0]) * (a[3] - a[1]) + (b[2] - b[0]) * (b[3] - b[1]) - inter
    return inter / ua if ua > 0 else 0.0


def contenida(a, b) -> float:
    """Fracción de la caja a que cae dentro de la caja b."""
    x1, y1 = max(a[0], b[0]), max(a[1], b[1])
    x2, y2 = min(a[2], b[2]), min(a[3], b[3])
    inter = max(0, x2 - x1) * max(0, y2 - y1)
    area = max(1e-6, (a[2] - a[0]) * (a[3] - a[1]))
    return inter / area


def nms_simple(items: list[dict], umbral: float = 0.6) -> list[dict]:
    items = sorted(items, key=lambda d: -d["conf"])
    res: list[dict] = []
    for d in items:
        if all(iou(d["box"], r["box"]) < umbral for r in res):
            res.append(d)
    return res


def mascara_de_poligono(poly: np.ndarray, alto: int, ancho: int) -> np.ndarray:
    m = np.zeros((alto, ancho), np.uint8)
    if poly is not None and len(poly) >= 3:
        cv2.fillPoly(m, [poly.astype(np.int32)], 1)
    return m.astype(bool)


def resultados_yolo_seg(r) -> list[dict]:
    """Convierte un resultado de Ultralytics (seg) a [{nombre, conf, box, mask}]."""
    dets: list[dict] = []
    if r.boxes is None or len(r.boxes) == 0:
        return dets
    alto, ancho = r.orig_shape
    clases = r.boxes.cls.cpu().numpy().astype(int)
    confs = r.boxes.conf.cpu().numpy()
    cajas = r.boxes.xyxy.cpu().numpy()
    polis = r.masks.xy if r.masks is not None else [None] * len(clases)
    for c, s, b, poly in zip(clases, confs, cajas, polis):
        mask = mascara_de_poligono(poly, alto, ancho) if poly is not None else None
        if mask is None or not mask.any():
            mask = np.zeros((alto, ancho), bool)
            x1, y1, x2, y2 = map(int, b)
            mask[y1:y2, x1:x2] = True
        dets.append({"nombre": r.names[int(c)], "conf": float(s), "box": [float(v) for v in b], "mask": mask})
    return dets
