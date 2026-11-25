"""Diagnóstico del entorno de Python (evidencia para el bono de Gestión de Entornos +5).

Imprime y guarda en evidencia_entorno_<nombre>.txt y .json:
SO, Python, ejecutable, gestor de entorno (venv/conda/mamba), PyTorch, CUDA, GPU,
Ultralytics, ONNX Runtime, OpenCV, fast-plate-ocr, EasyOCR, Tesseract y FastAPI.

Uso:
    python diagnostico_entorno.py                 # nombre automático
    python diagnostico_entorno.py --nombre runpod-venv --salida evidencias/
"""
from __future__ import annotations

import argparse
import datetime as dt
import importlib
import json
import os
import platform
import shutil
import subprocess
import sys
from pathlib import Path


def version(modulo: str) -> str | None:
    try:
        m = importlib.import_module(modulo)
    except Exception:
        return None
    for attr in ("__version__", "VERSION", "version"):
        v = getattr(m, attr, None)
        if isinstance(v, str):
            return v
    try:
        from importlib.metadata import version as meta

        return meta(modulo.replace("_", "-"))
    except Exception:
        return "instalado"


def gestor_entorno() -> str:
    if os.environ.get("CONDA_DEFAULT_ENV") or (Path(sys.prefix) / "conda-meta").exists():
        exe = os.environ.get("CONDA_EXE", "")
        nombre = os.environ.get("CONDA_DEFAULT_ENV") or Path(sys.prefix).name
        if "mamba" in exe.lower() or os.environ.get("MAMBA_EXE") or os.environ.get("MAMBA_ROOT_PREFIX"):
            return f"mamba ({nombre})"
        return f"conda ({nombre})"
    if sys.prefix != getattr(sys, "base_prefix", sys.prefix):
        return f"venv ({Path(sys.prefix).name})"
    return "sistema (sin entorno aislado)"


def info_torch() -> dict:
    d = {"torch": version("torch"), "cuda_disponible": False, "cuda_version": None,
         "cudnn": None, "gpu": None, "gpu_memoria_gb": None}
    try:
        import torch

        d["cuda_disponible"] = torch.cuda.is_available()
        d["cuda_version"] = torch.version.cuda
        d["cudnn"] = torch.backends.cudnn.version() if torch.backends.cudnn.is_available() else None
        if torch.cuda.is_available():
            p = torch.cuda.get_device_properties(0)
            d["gpu"] = p.name
            d["gpu_memoria_gb"] = round(p.total_memory / 1024 ** 3, 1)
    except Exception as e:
        d["error"] = str(e)
    return d


def info_onnx() -> dict:
    try:
        import onnxruntime as ort

        return {"onnxruntime": ort.__version__, "providers": ort.get_available_providers()}
    except Exception:
        return {"onnxruntime": None, "providers": []}


def info_tesseract() -> str | None:
    exe = shutil.which("tesseract")
    if not exe:
        cand = Path(sys.prefix) / ("Library/bin/tesseract.exe" if os.name == "nt" else "bin/tesseract")
        exe = str(cand) if cand.exists() else None
    if not exe:
        return None
    try:
        return subprocess.run([exe, "--version"], capture_output=True, text=True, timeout=10).stdout.split("\n")[0]
    except Exception:
        return "instalado"


def nvidia_smi() -> str | None:
    if not shutil.which("nvidia-smi"):
        return None
    try:
        return subprocess.run(["nvidia-smi", "--query-gpu=name,driver_version,memory.total",
                               "--format=csv,noheader"], capture_output=True, text=True, timeout=10).stdout.strip()
    except Exception:
        return None


def diagnosticar() -> dict:
    return {
        "fecha": dt.datetime.now().isoformat(timespec="seconds"),
        "host": platform.node(),
        "runpod_pod_id": os.environ.get("RUNPOD_POD_ID"),
        "colab": "COLAB_RELEASE_TAG" in os.environ or "google.colab" in sys.modules,
        "so": f"{platform.system()} {platform.release()} ({platform.machine()})",
        "python": platform.python_version(),
        "ejecutable": sys.executable,
        "prefijo_entorno": sys.prefix,
        "gestor_entorno": gestor_entorno(),
        **info_torch(),
        "nvidia_smi": nvidia_smi(),
        "ultralytics": version("ultralytics"),
        **info_onnx(),
        "opencv": version("cv2"),
        "numpy": version("numpy"),
        "fast_plate_ocr": version("fast_plate_ocr"),
        "easyocr": version("easyocr"),
        "pytesseract": version("pytesseract"),
        "tesseract_binario": info_tesseract(),
        "fastapi": version("fastapi"),
        "uvicorn": version("uvicorn"),
    }


def main() -> None:
    ap = argparse.ArgumentParser(description=__doc__)
    ap.add_argument("--nombre", default=None, help="sufijo del archivo de evidencia")
    ap.add_argument("--salida", default=".", help="carpeta de salida")
    a = ap.parse_args()
    d = diagnosticar()
    nombre = a.nombre or d["gestor_entorno"].split(" ")[0]
    salida = Path(a.salida)
    salida.mkdir(parents=True, exist_ok=True)
    ancho = max(len(k) for k in d)
    lineas = ["=" * 72, " DIAGNÓSTICO DE ENTORNO · Sistema Inteligente de Control de Peaje", "=" * 72]
    lineas += [f" {k.ljust(ancho)} : {v}" for k, v in d.items()]
    lineas.append("=" * 72)
    texto = "\n".join(lineas)
    print(texto)
    (salida / f"evidencia_entorno_{nombre}.txt").write_text(texto + "\n", encoding="utf-8")
    (salida / f"evidencia_entorno_{nombre}.json").write_text(json.dumps(d, indent=2, ensure_ascii=False),
                                                               encoding="utf-8")
    print(f"\nGuardado: {salida / f'evidencia_entorno_{nombre}.txt'} y .json")


if __name__ == "__main__":
    main()
