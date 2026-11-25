"""Descarga de modelos con verificación SHA-256 (detector y lector de placas)."""
from __future__ import annotations

import hashlib
import urllib.request
from pathlib import Path

from . import config


def sha256(ruta: Path) -> str:
    h = hashlib.sha256()
    with open(ruta, "rb") as f:
        for bloque in iter(lambda: f.read(1 << 20), b""):
            h.update(bloque)
    return h.hexdigest()


def descargar_m1(destino: Path | None = None, verbose: bool = True) -> dict[str, str]:
    """Descarga (si faltan) los 3 archivos de M1 y verifica su SHA-256.

    Lanza RuntimeError si algún hash no coincide (archivo corrupto o alterado).
    """
    destino = Path(destino or config.MODELOS_DIR)
    destino.mkdir(parents=True, exist_ok=True)
    estado: dict[str, str] = {}
    for nombre, (url, esperado) in config.M1_ARCHIVOS.items():
        ruta = destino / nombre
        if not ruta.exists() or sha256(ruta) != esperado:
            if verbose:
                print(f"[descarga] {nombre} ...", flush=True)
            tmp = ruta.with_suffix(ruta.suffix + ".part")
            urllib.request.urlretrieve(url, tmp)
            tmp.replace(ruta)
        real = sha256(ruta)
        if real != esperado:
            raise RuntimeError(f"SHA-256 inválido para {nombre}: {real} != {esperado}")
        estado[nombre] = real
        if verbose:
            print(f"[ok] {nombre} sha256={real[:16]}…", flush=True)
    return estado


if __name__ == "__main__":
    descargar_m1()
