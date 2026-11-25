# =====================================================================
#  Imagen Docker del servidor de inferencia (RunPod "Custom Template" o cualquier GPU cloud)
#
#  docker build -t <usuario>/peaje-ia-inferencia:1.0 .
#  docker run --gpus all -p 8000:8000 -e API_TOKEN=xxxx <usuario>/peaje-ia-inferencia:1.0
#
#  En RunPod: Templates > New Template > Container Image = <usuario>/peaje-ia-inferencia:1.0,
#  Expose HTTP Ports = 8000, Environment Variables: API_TOKEN.
# =====================================================================
FROM python:3.11-slim

ENV PYTHONDONTWRITEBYTECODE=1 \
    PYTHONUNBUFFERED=1 \
    PIP_NO_CACHE_DIR=1 \
    PORT=8000 \
    YOLO_CONFIG_DIR=/tmp/Ultralytics

RUN apt-get update && apt-get install -y --no-install-recommends \
        libgl1 libglib2.0-0 git curl ca-certificates \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app
COPY requirements-venv.txt .
RUN pip install --upgrade pip && pip install -r requirements-venv.txt

COPY peaje_ia ./peaje_ia
COPY app.py diagnostico_entorno.py probar_cliente.py ./
COPY pesos ./pesos

# Descarga y verifica (SHA-256) los modelos ONNX de M1 y precarga los fallbacks de Ultralytics
RUN python -m peaje_ia.descargas \
    && python -c "from ultralytics import YOLO; YOLO('yolo11s-seg.pt')" \
    && (python -c "from ultralytics import YOLOE; YOLOE('yoloe-11s-seg.pt')" || true)

EXPOSE 8000
HEALTHCHECK --interval=30s --timeout=10s --start-period=120s \
    CMD curl -fs http://127.0.0.1:8000/health || exit 1

CMD ["uvicorn", "app:app", "--host", "0.0.0.0", "--port", "8000"]
