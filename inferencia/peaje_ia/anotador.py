"""Imagen anotada de evidencia (estilo de las imágenes referenciales M1, M2 y M3).

Privacidad M4: las personas se cubren con una máscara OPACA y solo se muestra el conteo.
"""
from __future__ import annotations

import base64

import cv2
import numpy as np

from . import config

# Colores BGR por categoría (coherentes con el panel web)
COLORES = {
    "auto": (219, 152, 52), "minibus": (113, 204, 46), "bus": (60, 76, 231),
    "camion": (247, 85, 168), "moto": (11, 158, 245),
}
VERDE, ROJO_KP, AMARILLO, GRIS = (94, 197, 34), (94, 63, 244), (0, 215, 255), (60, 60, 60)
COLOR_PASAJERO = (90, 60, 30)


def _grosor(img) -> int:
    return max(2, int(round(max(img.shape[:2]) / 450)))


def _etiqueta(img, texto, x, y, color, escala=None):
    g = _grosor(img)
    esc = escala or g * 0.33
    gt = max(1, g // 2)
    (tw, th), _ = cv2.getTextSize(texto, cv2.FONT_HERSHEY_SIMPLEX, esc, gt)
    y = max(th + 8, y)
    cv2.rectangle(img, (x, y - th - 8), (x + tw + 8, y), color, -1)
    cv2.putText(img, texto, (x + 4, y - 5), cv2.FONT_HERSHEY_SIMPLEX, esc, (255, 255, 255), gt, cv2.LINE_AA)


def _linea_discontinua(img, p, q, color, g, paso=14):
    p, q = np.array(p, float), np.array(q, float)
    largo = np.linalg.norm(q - p)
    if largo < 1:
        return
    n = int(largo // paso)
    for i in range(0, n, 2):
        a = p + (q - p) * (i / n)
        b = p + (q - p) * (min(i + 1, n) / n)
        cv2.line(img, tuple(map(int, a)), tuple(map(int, b)), color, g, cv2.LINE_AA)


def anotar(img_bgr: np.ndarray, placa, vehiculo: dict | None, ejes: dict | None, pasajeros: dict | None) -> str:
    out = img_bgr.copy()
    g = _grosor(out)

    # --- M2: máscara del vehículo -------------------------------------------------
    if vehiculo is not None:
        col = COLORES.get(vehiculo["categoria"], (200, 200, 200))
        capa = out.copy()
        capa[vehiculo["mask"]] = col
        out = cv2.addWeighted(capa, 0.35, out, 0.65, 0)
        x1, y1, x2, y2 = map(int, vehiculo["box"])
        cv2.rectangle(out, (x1, y1), (x2, y2), col, g)
        _etiqueta(out, f"{vehiculo['categoria']} {vehiculo['conf']:.2f}", x1, y1, col)

    # --- M4: pasajeros (máscara OPACA, sin rostros) -------------------------------
    if pasajeros and pasajeros.get("_mascaras"):
        for m in pasajeros["_mascaras"]:
            out[m] = (0.15 * out[m] + 0.85 * np.array(COLOR_PASAJERO)).astype(np.uint8)
        union = np.any(np.stack(pasajeros["_mascaras"]), axis=0)
        ys, xs = np.where(union)
        _etiqueta(out, f"Pasajeros: {pasajeros['conteo']}", int(xs.min()), int(ys.min()), COLOR_PASAJERO)

    # --- M3: keypoints de llantas y ejes -------------------------------------------
    if ejes and ejes.get("keypoints"):
        kps = ejes["keypoints"]
        for a, b in zip(kps, kps[1:]):
            _linea_discontinua(out, a[:2], b[:2], ROJO_KP, max(1, g // 2 + 1))
        for i, (x, y, _) in enumerate(kps, 1):
            cv2.circle(out, (x, y), g * 4, (255, 255, 255), -1, cv2.LINE_AA)
            cv2.circle(out, (x, y), g * 3, ROJO_KP, -1, cv2.LINE_AA)
            _etiqueta(out, f"K{i}", x - g * 6, y - g * 6, ROJO_KP)
    for l in (ejes or {}).get("llantas", []):
        x1, y1, x2, y2 = map(int, l["box"])
        cv2.rectangle(out, (x1, y1), (x2, y2), AMARILLO, max(1, g // 2))

    # --- M1: placa -----------------------------------------------------------------
    if placa is not None:
        x1, y1, x2, y2 = placa.bbox
        cv2.rectangle(out, (x1, y1), (x2, y2), VERDE, g)
        _etiqueta(out, f"{placa.texto or '?'} {placa.confianza:.2f}", x1, y1, VERDE)

    # --- Banda resumen -------------------------------------------------------------
    partes = [f"Placa: {placa.texto if placa and placa.texto else 'ilegible'}"]
    if vehiculo is not None:
        partes.append(vehiculo["categoria"])
    if ejes is not None:
        partes.append(f"ejes: {ejes['ejes'] if ejes['ejes'] is not None else 'N/D'} ({ejes['vista']})")
    if pasajeros is not None:
        partes.append(f"pasajeros: {pasajeros['conteo']}")
    # Se reparte en líneas que quepan en el ancho (fotos verticales de celular)
    esc, gt = g * 0.38, max(1, g // 2)
    lineas, actual = [], ""
    for p in partes:
        cand = f"{actual} | {p}" if actual else p
        if cv2.getTextSize(cand, cv2.FONT_HERSHEY_SIMPLEX, esc, gt)[0][0] > out.shape[1] - g * 8 and actual:
            lineas.append(actual)
            actual = p
        else:
            actual = cand
    lineas.append(actual)
    alto_linea = g * 12
    banda = np.full((alto_linea * len(lineas) + g * 2, out.shape[1], 3), (39, 23, 15), np.uint8)
    for i, texto in enumerate(lineas):
        cv2.putText(banda, texto, (g * 4, alto_linea * (i + 1) - g * 2), cv2.FONT_HERSHEY_SIMPLEX,
                    esc, (255, 255, 255), gt, cv2.LINE_AA)
    out = np.vstack([banda, out])

    if out.shape[1] > config.ANCHO_MAX_ANOTADA:
        esc = config.ANCHO_MAX_ANOTADA / out.shape[1]
        out = cv2.resize(out, (config.ANCHO_MAX_ANOTADA, int(out.shape[0] * esc)), interpolation=cv2.INTER_AREA)
    ok, buf = cv2.imencode(".jpg", out, [cv2.IMWRITE_JPEG_QUALITY, config.JPEG_CALIDAD])
    return "data:image/jpeg;base64," + base64.b64encode(buf).decode() if ok else ""


def anotar_solo_placa(img_bgr: np.ndarray, placas: list) -> str:
    out = img_bgr.copy()
    for p in placas:
        x1, y1, x2, y2 = p.bbox
        cv2.rectangle(out, (x1, y1), (x2, y2), VERDE, _grosor(out))
        _etiqueta(out, f"{p.texto or '?'} {p.confianza:.2f}", x1, y1, VERDE)
    if out.shape[1] > config.ANCHO_MAX_ANOTADA:
        esc = config.ANCHO_MAX_ANOTADA / out.shape[1]
        out = cv2.resize(out, (config.ANCHO_MAX_ANOTADA, int(out.shape[0] * esc)), interpolation=cv2.INTER_AREA)
    ok, buf = cv2.imencode(".jpg", out, [cv2.IMWRITE_JPEG_QUALITY, config.JPEG_CALIDAD])
    return "data:image/jpeg;base64," + base64.b64encode(buf).decode() if ok else ""
