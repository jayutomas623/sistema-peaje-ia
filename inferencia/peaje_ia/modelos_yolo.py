"""Caché de modelos Ultralytics: un mismo archivo de pesos se carga una sola vez en GPU."""
from __future__ import annotations

from pathlib import Path

_CACHE: dict[str, object] = {}


def cargar_yolo(pesos: str | Path):
    from ultralytics import YOLO

    clave = str(pesos)
    if clave not in _CACHE:
        _CACHE[clave] = YOLO(clave)
    return _CACHE[clave]


def cargar_yoloe(pesos: str, prompts: list[str]):
    """YOLOE de vocabulario abierto con prompts de texto (requiere el paquete CLIP de Ultralytics)."""
    from ultralytics import YOLOE

    clave = f"{pesos}|{'|'.join(prompts)}"
    if clave not in _CACHE:
        m = YOLOE(pesos)
        try:
            m.set_classes(prompts, m.get_text_pe(prompts))
        except TypeError:  # versiones nuevas: set_classes(nombres)
            m.set_classes(prompts)
        _CACHE[clave] = m
    return _CACHE[clave]
