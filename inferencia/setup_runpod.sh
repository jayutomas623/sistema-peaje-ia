#!/usr/bin/env bash
# =====================================================================
#  setup_runpod.sh — Prepara y levanta el servidor de inferencia en un Pod de RunPod
#
#  Uso (terminal web del Pod, dentro de /workspace):
#     git clone https://github.com/<usuario>/sistema-peaje-ia.git
#     cd sistema-peaje-ia/inferencia
#     export API_TOKEN="<token-largo>"          # el mismo que se guarda en Laravel
#     bash setup_runpod.sh venv                 # o: conda | mamba | todos
#
#  El servidor queda en: https://${RUNPOD_POD_ID}-8000.proxy.runpod.net
#  (expone el puerto HTTP 8000 en la configuración del Pod)
# =====================================================================
set -euo pipefail

MODO="${1:-venv}"
DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
WS="${WORKSPACE:-/workspace}"
EVID="${DIR}/evidencias"
mkdir -p "${EVID}" "${DIR}/pesos" "${DIR}/modelos"

echo "==> Paquetes del sistema (OpenCV headless, git, tesseract opcional)"
if command -v apt-get >/dev/null 2>&1; then
  apt-get update -qq && apt-get install -y -qq libgl1 libglib2.0-0 git wget curl >/dev/null || true
fi

instalar_conda() {
  if ! command -v conda >/dev/null 2>&1; then
    echo "==> Instalando Miniforge (conda + mamba) en ${WS}/miniforge3"
    wget -q -O /tmp/miniforge.sh \
      "https://github.com/conda-forge/miniforge/releases/latest/download/Miniforge3-Linux-x86_64.sh"
    bash /tmp/miniforge.sh -b -p "${WS}/miniforge3"
  fi
  # shellcheck disable=SC1091
  source "${WS}/miniforge3/etc/profile.d/conda.sh" 2>/dev/null || eval "$(conda shell.bash hook)"
}

crear_venv() {
  echo "==> [venv] ${WS}/venvs/peaje-venv"
  python3.11 -m venv "${WS}/venvs/peaje-venv" 2>/dev/null || python3 -m venv "${WS}/venvs/peaje-venv"
  # shellcheck disable=SC1091
  source "${WS}/venvs/peaje-venv/bin/activate"
  pip install -q --upgrade pip
  pip install -q -r "${DIR}/requirements-venv.txt"
  python "${DIR}/diagnostico_entorno.py" --nombre runpod-venv --salida "${EVID}"
  deactivate
}

crear_conda() {
  instalar_conda
  echo "==> [conda] peaje-conda"
  conda env create -y -f "${DIR}/environment-conda.yml" || conda env update -f "${DIR}/environment-conda.yml"
  conda run -n peaje-conda python "${DIR}/diagnostico_entorno.py" --nombre runpod-conda --salida "${EVID}"
}

crear_mamba() {
  instalar_conda
  echo "==> [mamba] peaje-mamba"
  mamba env create -y -f "${DIR}/environment-mamba.yml" || mamba env update -f "${DIR}/environment-mamba.yml"
  mamba run -n peaje-mamba python "${DIR}/diagnostico_entorno.py" --nombre runpod-mamba --salida "${EVID}"
}

case "${MODO}" in
  venv)  crear_venv ;;
  conda) crear_conda ;;
  mamba) crear_mamba ;;
  todos) crear_venv; crear_conda; crear_mamba ;;
  *) echo "Modo desconocido: ${MODO} (venv|conda|mamba|todos)"; exit 1 ;;
esac

echo "==> Descargando modelos M1 (verificación SHA-256)"
PY="${WS}/venvs/peaje-venv/bin/python"
[ -x "${PY}" ] || PY="$(command -v python3)"
(cd "${DIR}" && "${PY}" -m peaje_ia.descargas)

if [ -z "${API_TOKEN:-}" ]; then
  API_TOKEN="$(python3 -c 'import secrets; print(secrets.token_urlsafe(32))')"
  export API_TOKEN
  echo "!! API_TOKEN no estaba definido. Se generó: ${API_TOKEN}"
  echo "!! Cópielo en Laravel > Estado del sistema > INFERENCE_TOKEN"
fi

echo "==> Evidencias de entorno en ${EVID}:"
ls -1 "${EVID}" || true

URL="https://${RUNPOD_POD_ID:-POD_ID}-8000.proxy.runpod.net"
echo "==> Iniciando servidor en 0.0.0.0:8000  ->  ${URL}"
cd "${DIR}"
case "${MODO}" in
  conda) exec conda run --no-capture-output -n peaje-conda uvicorn app:app --host 0.0.0.0 --port 8000 ;;
  mamba) exec mamba run -n peaje-mamba uvicorn app:app --host 0.0.0.0 --port 8000 ;;
  *)     exec "${WS}/venvs/peaje-venv/bin/uvicorn" app:app --host 0.0.0.0 --port 8000 ;;
esac
