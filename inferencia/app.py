"""Servidor de inferencia unificado (M1 + M2 + M3 + M4) — FastAPI + Uvicorn.

Mismo código para RunPod (https://<POD_ID>-8000.proxy.runpod.net) y Google Colab
(túnel Cloudflare). Laravel solo cambia la variable INFERENCE_URL.

Ejecutar:
    API_TOKEN=xxxx python app.py              # escucha en 0.0.0.0:8000
    uvicorn app:app --host 0.0.0.0 --port 8000

Endpoints:
    GET  /health          estado, GPU, modelos, versiones, latencia de calentamiento
    POST /inferir         (X-API-Token) imagen -> placa + vehiculo + ejes + pasajeros + imagen anotada
    POST /inferir-placa   (X-API-Token) imagen -> solo M1 + OCR (para el agente conversacional)
"""
from __future__ import annotations

import os
import secrets
from contextlib import asynccontextmanager

from fastapi import FastAPI, Header, HTTPException, Request
from fastapi.middleware.cors import CORSMiddleware
from starlette.concurrency import run_in_threadpool

from peaje_ia import config
from peaje_ia.pipeline import Pipeline, decodificar

pipeline = Pipeline()


@asynccontextmanager
async def ciclo_de_vida(_app: FastAPI):
    await run_in_threadpool(pipeline.cargar)
    pod = os.environ.get("RUNPOD_POD_ID")
    url = f"https://{pod}-{config.PUERTO}.proxy.runpod.net" if pod else f"http://0.0.0.0:{config.PUERTO}"
    print("=" * 70, flush=True)
    print(f" Servidor de inferencia listo  ->  INFERENCE_URL = {url}", flush=True)
    if config.API_TOKEN_GENERADO:
        print(f" API_TOKEN generado (cópielo al panel Laravel): {config.API_TOKEN}", flush=True)
    print(f" Modos: {pipeline.modos()}  calentamiento: {pipeline.calentamiento_ms} ms", flush=True)
    print("=" * 70, flush=True)
    yield


app = FastAPI(title="Peaje IA · Servidor de inferencia", version="1.0.0", lifespan=ciclo_de_vida)
app.add_middleware(CORSMiddleware, allow_origins=["*"], allow_methods=["GET", "POST"], allow_headers=["*"])


def verificar_token(token: str | None) -> None:
    if not token or not secrets.compare_digest(token, config.API_TOKEN):
        raise HTTPException(status_code=401, detail="Token inválido o ausente (header X-API-Token)")


async def leer_imagen(request: Request) -> bytes:
    """Acepta multipart/form-data (campo 'imagen', 'image' o 'file') o el JPEG/PNG crudo en el body."""
    tipo = request.headers.get("content-type", "")
    if tipo.startswith("multipart/form-data"):
        form = await request.form()
        for campo in ("imagen", "image", "file", "frame"):
            archivo = form.get(campo)
            if archivo is not None and hasattr(archivo, "read"):
                datos = await archivo.read()
                break
        else:
            raise HTTPException(status_code=422, detail="Falta el archivo 'imagen' en el formulario")
    else:
        datos = await request.body()
    if not datos:
        raise HTTPException(status_code=422, detail="Imagen vacía")
    if len(datos) > config.MAX_BYTES:
        raise HTTPException(status_code=413, detail="Imagen demasiado grande")
    return datos


@app.get("/")
def raiz():
    return {"servicio": "peaje-ia-inferencia", "docs": "/docs", "health": "/health"}


@app.get("/health")
def health():
    return pipeline.salud()


@app.post("/inferir")
async def inferir(request: Request, x_api_token: str | None = Header(default=None)):
    verificar_token(x_api_token)
    datos = await leer_imagen(request)
    try:
        img = decodificar(datos)
        return await run_in_threadpool(pipeline.inferir, img)
    except ValueError as e:
        raise HTTPException(status_code=400, detail=str(e))


@app.post("/inferir-placa")
async def inferir_placa(request: Request, x_api_token: str | None = Header(default=None)):
    verificar_token(x_api_token)
    datos = await leer_imagen(request)
    try:
        img = decodificar(datos)
        return await run_in_threadpool(pipeline.inferir_placa, img)
    except ValueError as e:
        raise HTTPException(status_code=400, detail=str(e))
    except RuntimeError as e:
        raise HTTPException(status_code=503, detail=str(e))


if __name__ == "__main__":
    import uvicorn

    # host 0.0.0.0 es obligatorio: con 127.0.0.1 el proxy de RunPod devuelve 502
    uvicorn.run(app, host="0.0.0.0", port=config.PUERTO, log_level="info")
