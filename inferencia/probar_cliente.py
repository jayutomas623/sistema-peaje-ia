"""Cliente de prueba: envía una imagen al servidor (RunPod o Colab) y guarda la imagen anotada.

Uso:
    python probar_cliente.py --url https://<POD_ID>-8000.proxy.runpod.net --token XXXX foto.jpg
    python probar_cliente.py --url ... --token ... --solo-placa foto.jpg
"""
from __future__ import annotations

import argparse
import base64
import json
import time
import urllib.request
from pathlib import Path


def main() -> None:
    ap = argparse.ArgumentParser()
    ap.add_argument("imagen")
    ap.add_argument("--url", required=True)
    ap.add_argument("--token", required=True)
    ap.add_argument("--solo-placa", action="store_true")
    a = ap.parse_args()

    url = a.url.rstrip("/")
    print("health:", json.dumps(json.loads(urllib.request.urlopen(url + "/health", timeout=60).read()), indent=1)[:900])
    datos = Path(a.imagen).read_bytes()
    ruta = "/inferir-placa" if a.solo_placa else "/inferir"
    req = urllib.request.Request(url + ruta, data=datos, method="POST",
                                 headers={"Content-Type": "image/jpeg", "X-API-Token": a.token})
    t0 = time.perf_counter()
    res = json.loads(urllib.request.urlopen(req, timeout=120).read())
    ida_vuelta = (time.perf_counter() - t0) * 1000
    anotada = res.pop("imagen_anotada_base64", None)
    print(json.dumps(res, indent=2, ensure_ascii=False))
    print(f"ida y vuelta: {ida_vuelta:.0f} ms (servidor: {res.get('latencia_ms')} ms)")
    if anotada:
        salida = Path(a.imagen).with_name(Path(a.imagen).stem + "_anotada.jpg")
        salida.write_bytes(base64.b64decode(anotada.split(",", 1)[1]))
        print("imagen anotada:", salida)


if __name__ == "__main__":
    main()
