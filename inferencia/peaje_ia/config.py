"""Configuración centralizada leída de variables de entorno.

Ningún secreto se escribe en el código: API_TOKEN se define en el Pod de RunPod
(o en la celda de Colab) y se copia al panel de Laravel (Estado del sistema).
"""
from __future__ import annotations

import os
import secrets
from pathlib import Path

BASE_DIR = Path(__file__).resolve().parent.parent
MODELOS_DIR = Path(os.environ.get("MODELOS_DIR", BASE_DIR / "modelos"))
PESOS_DIR = Path(os.environ.get("PESOS_DIR", BASE_DIR / "pesos"))

PUERTO = int(os.environ.get("PORT", os.environ.get("PUERTO", "8000")))

# Token que Laravel envía en el header X-API-Token. Si no se define, se genera
# uno aleatorio en cada arranque (se imprime en consola para copiarlo al panel).
API_TOKEN = os.environ.get("API_TOKEN") or secrets.token_urlsafe(24)
API_TOKEN_GENERADO = "API_TOKEN" not in os.environ

# Dispositivo: "auto" usa CUDA si está disponible.
DEVICE = os.environ.get("DEVICE", "auto")

# --- M1: placas -------------------------------------------------------------
M1_DETECTOR = "yolo-v9-t-384-license-plates-end2end.onnx"
M1_OCR = "cct_xs_v2_global.onnx"
M1_OCR_CONFIG = "cct_xs_v2_global_plate_config.yaml"
M1_UMBRAL = float(os.environ.get("M1_UMBRAL", "0.40"))

# URLs y SHA-256 oficiales (guía placas_entornos_v2.html del docente)
M1_ARCHIVOS = {
    M1_DETECTOR: (
        "https://github.com/ankandrew/open-image-models/releases/download/assets/"
        "yolo-v9-t-384-license-plates-end2end.onnx",
        "888397b96d761c89db40bc9c305838e8652660f5e282c2cadebbe8d2951a77a8",
    ),
    M1_OCR: (
        "https://github.com/ankandrew/cnn-ocr-lp/releases/download/arg-plates/cct_xs_v2_global.onnx",
        "8031afb5fdc6b4d80462c9d542f1284ebd2cfddf5dbacd62609848d7e2855f44",
    ),
    M1_OCR_CONFIG: (
        "https://github.com/ankandrew/cnn-ocr-lp/releases/download/arg-plates/"
        "cct_xs_v2_global_plate_config.yaml",
        "0335c74a305173bb6f393efed0fde03cadeaa0b649ed8e19f431016d8232d0a6",
    ),
}

# --- M2: vehículos ------------------------------------------------------------
M2_PESOS = Path(os.environ.get("M2_PESOS", PESOS_DIR / "m2_vehiculos_seg.pt"))
M2_FALLBACK = os.environ.get("M2_FALLBACK", "yolo11s-seg.pt")
M2_UMBRAL = float(os.environ.get("M2_UMBRAL", "0.35"))

# --- M3: llantas / ejes -------------------------------------------------------
M3_PESOS = Path(os.environ.get("M3_PESOS", PESOS_DIR / "m3_llantas_pose.pt"))
# Fallback zero-shot con YOLOE (vocabulario abierto: "wheel", "tire").
# Requiere: pip install git+https://github.com/ultralytics/CLIP.git
M3_FALLBACK_YOLOE = os.environ.get("M3_FALLBACK_YOLOE", "yoloe-11s-seg.pt")
M3_USAR_YOLOE = os.environ.get("M3_USAR_YOLOE", "1") == "1"
M3_UMBRAL = float(os.environ.get("M3_UMBRAL", "0.25"))
M3_UMBRAL_LLANTA_YOLOE = float(os.environ.get("M3_UMBRAL_LLANTA_YOLOE", "0.15"))

# --- M4: pasajeros ------------------------------------------------------------
M4_PESOS = Path(os.environ.get("M4_PESOS", PESOS_DIR / "m4_pasajeros_seg.pt"))
M4_FALLBACK = os.environ.get("M4_FALLBACK", "yolo11s-seg.pt")
M4_UMBRAL = float(os.environ.get("M4_UMBRAL", "0.35"))
# Fracción mínima de la máscara de la persona que debe caer dentro del vehículo
M4_MIN_INTERSECCION = float(os.environ.get("M4_MIN_INTERSECCION", "0.30"))

# Calidad JPEG de la imagen anotada y ancho máximo (reduce el tamaño del JSON)
JPEG_CALIDAD = int(os.environ.get("JPEG_CALIDAD", "80"))
ANCHO_MAX_ANOTADA = int(os.environ.get("ANCHO_MAX_ANOTADA", "960"))
# Tamaño máximo de imagen aceptada (bytes)
MAX_BYTES = int(os.environ.get("MAX_BYTES", str(8 * 1024 * 1024)))
