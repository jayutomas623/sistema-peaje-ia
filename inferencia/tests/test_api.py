"""Pruebas del servidor de inferencia.

    API_TOKEN=test pytest -q tests/

La primera ejecución descarga los modelos (M1 ONNX + yolo11s-seg + yoloe).
"""
from __future__ import annotations

import os
import sys
from pathlib import Path

import cv2
import numpy as np
import pytest

os.environ.setdefault("API_TOKEN", "test")
sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

from peaje_ia.normalizacion import normalizar_placa  # noqa: E402


@pytest.mark.parametrize("crudo,esperado,valido", [
    ("2481-KLM", "2481KLM", True),
    ("1701N4C", "1701NAC", True),       # 4 -> A en posición de letra
    ("I7O3XFY", "1703XFY", True),       # I -> 1, O -> 0 en posiciones de dígito
    ("BOLIVIA1534PHK", "1534PHK", True),
    ("194 UXI", "194UXI", True),
    ("", "", False),
    ("ABC", "ABC", False),
])
def test_normalizacion(crudo, esperado, valido):
    assert normalizar_placa(crudo) == (esperado, valido)


@pytest.fixture(scope="module")
def cliente():
    from fastapi.testclient import TestClient

    import app as servidor

    with TestClient(servidor.app) as c:
        yield c


def _jpeg(img: np.ndarray) -> bytes:
    return cv2.imencode(".jpg", img)[1].tobytes()


def test_health(cliente):
    r = cliente.get("/health")
    assert r.status_code == 200
    d = r.json()
    assert d["modos"]["m1"] == "onnx"
    assert "gpu" in d and "versiones" in d


def test_token_obligatorio(cliente):
    r = cliente.post("/inferir", content=_jpeg(np.zeros((64, 64, 3), np.uint8)),
                     headers={"Content-Type": "image/jpeg"})
    assert r.status_code == 401


def test_contrato_json(cliente):
    img = np.full((480, 640, 3), 120, np.uint8)
    r = cliente.post("/inferir", files={"imagen": ("f.jpg", _jpeg(img), "image/jpeg")},
                     headers={"X-API-Token": "test"})
    assert r.status_code == 200
    d = r.json()
    for clave in ("placa", "vehiculo", "ejes", "vista_vehiculo", "keypoints", "pasajeros",
                  "imagen_anotada_base64", "latencia_ms"):
        assert clave in d
    assert set(d["placa"]) >= {"texto", "texto_crudo", "formato_bolivia", "confianza", "bbox"}
    assert set(d["pasajeros"]) == {"conteo", "confianza_promedio"}
    assert d["imagen_anotada_base64"].startswith("data:image/jpeg;base64,")


def test_inferir_placa_raw(cliente):
    img = np.full((240, 320, 3), 200, np.uint8)
    r = cliente.post("/inferir-placa", content=_jpeg(img),
                     headers={"Content-Type": "image/jpeg", "X-API-Token": "test"})
    assert r.status_code == 200
    assert r.json()["legible"] is False


def test_imagen_invalida(cliente):
    r = cliente.post("/inferir", content=b"no-es-imagen",
                     headers={"Content-Type": "image/jpeg", "X-API-Token": "test"})
    assert r.status_code == 400
