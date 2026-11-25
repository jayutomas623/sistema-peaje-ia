# 🧭 Guía paso a paso post-desarrollo

Manual en **orden cronológico exacto** de todo lo que falta hacer después de programar, hasta grabar el video.
El código ya está listo y probado (Laravel 42 pruebas ✔ en SQLite y PostgreSQL, inferencia 12 pruebas ✔, Flutter `analyze` sin issues).

> **Regla de oro:** en la demo **nada** puede apuntar a `localhost`, `127.0.0.1`, IPs privadas (`192.168.*`, `10.*`)
> ni túneles hacia tu PC. Todo queda en Roboflow, Colab, RunPod, tu subdominio HTTPS, Pusher y el ESP32.

**Tiempo estimado total:** 8–14 h (la mayor parte es etiquetar en Roboflow y entrenar en Colab).

| # | Paso | Resultado que debes tener al final |
|---|---|---|
| 0 | Subir el repositorio a GitHub (público) | URL del repo |
| 1 | Roboflow | 3 datasets versionados + `ROBOFLOW_API_KEY` |
| 2 | Colab | `m2_vehiculos_seg.pt`, `m3_llantas_pose.pt`, `m4_pasajeros_seg.pt` + métricas |
| 3 | RunPod | `https://<POD_ID>-8000.proxy.runpod.net/health` respondiendo + evidencias de entornos |
| 4 | Google Cloud | Web Client ID/Secret + Android Client ID |
| 5 | Pusher + Gemini | claves de Pusher + `GEMINI_API_KEY` |
| 6 | Subdominio HTTPS | panel en `https://peaje.tu-dominio.com` con datos sembrados |
| 7 | APK | `app-release.apk` instalado en el celular |
| 8 | ESP32 | LEDs y buzzer reaccionando a los pasos |
| 9 | Pruebas + video | checklist completo + video de 8–12 min |

---

## Paso 0 · Publicar el repositorio en GitHub

El repositorio ya está inicializado en `sistema-peaje-ia/` (rama `main`) sin commits. Desde Git Bash:

```bash
cd ~/Downloads/todo/sistema-peaje-ia
git status --ignored | grep -E "\.env$|secrets\.h$|\.jks|google-services" || echo "OK: ningún secreto a la vista"
git add .
git status            # revisa que NO aparezcan .env, secrets.h, *.jks, key.properties, google-services.json
git commit -m "Sistema Inteligente de Control de Peaje: entrenamiento, inferencia, web, móvil, hardware y docs"
```

1. En GitHub → **New repository** → nombre `sistema-peaje-ia` → **Public** → sin README.
2. `git remote add origin https://github.com/<usuario>/sistema-peaje-ia.git && git push -u origin main`
3. Reemplaza `TU_USUARIO` por tu usuario en:
   `entrenamiento/01_m1_placas_bolivia_ocr.ipynb` (celda `REPO_URL`) e `inferencia/servidor_inferencia_runpod_colab.ipynb`.
   Haz commit y push de ese cambio.

> ⚠️ Si alguna vez subes un secreto por error: **rótalo** (genera uno nuevo) y elimínalo del historial. Borrar el
> archivo en un commit nuevo **no basta**.

---

## Paso 1 · Datasets en Roboflow

> **Los notebooks 02, 03 y 04 ya usan datasets públicos etiquetados de Roboflow Universe**
> (ver [`entrenamiento/DATASETS.md`](entrenamiento/DATASETS.md)): **no hace falta sacar fotos ni etiquetar**.
> De este paso solo necesitas los puntos 1 y 2 (cuenta y API key). Las secciones 1.1–1.3 quedan como
> referencia por si algún día quieres un dataset propio de La Paz/El Alto.

1. Crea una cuenta en <https://roboflow.com> (plan gratuito) y un **Workspace** (anota su *slug*, p. ej. `juan-peaje`).
2. **API key:** *Settings → API Keys → Private API Key → Copy*. **No** la pegues en código ni en el repo.
   En Colab irá en **🔑 Secretos** con el nombre `ROBOFLOW_API_KEY`.

### 1.1 M2 · Vehículos (Instance Segmentation)
* *Create New Project* → **Instance Segmentation** → nombre `vehiculos-bolivia-seg`.
* Clases **exactas** (minúsculas, sin tilde): `auto`, `minibus`, `bus`, `camion` (y `moto` si tienes ejemplos).
* Imágenes: fotos y frames de video de La Paz/El Alto (sacar frames: `ffmpeg -i video.mp4 -vf fps=1 frame_%04d.jpg`).
  Meta mínima: **150 instancias por clase** (más minibuses, que COCO no conoce). Incluye frontal, lateral y 3/4.
* Etiqueta con **Smart Polygon (SAM)**: un clic sobre el vehículo → ajustar → elegir clase.
* *Generate* → split 70/20/10 → preprocesado *Auto-Orient* + *Resize 640* → augmentations: *Flip horizontal*,
  *Brightness ±25 %*, *Blur 1px* → **Create**. Anota el número de versión.

### 1.2 M3 · Llantas (Keypoint Detection)
* Abre primero el notebook `03` en Colab y ejecuta la sección **3 (auto-etiquetado)** con un zip de ≥ 200 fotos
  **laterales** de vehículos (2, 3 y más ejes). Descarga `autoetiquetado_llantas.zip`.
* *Create New Project* → **Keypoint Detection** → nombre `llantas-keypoints` → clase `llanta`.
* Define el esqueleto con **5 puntos en este orden**: `centro`, `contacto`, `superior`, `izquierda`, `derecha`
  (conexiones centro→contacto, centro→superior, centro→izquierda, centro→derecha).
* *Upload* → arrastra la carpeta descomprimida `autoetiquetado` (formato YOLOv8 pose) → **corrige cada llanta**.
  Etiqueta todas las llantas visibles (también las del lado lejano: el código las filtra).
* *Generate* con *Flip horizontal* (Roboflow intercambia izquierda/derecha) → versión.

### 1.3 M4 · Pasajeros (Instance Segmentation) — bono
* Proyecto **Instance Segmentation** `pasajeros-seg`, clases `pasajero` y `vehiculo`.
* Fotos **frontales** de minibuses/autos/buses donde se vean ocupantes por el parabrisas (100–200 imágenes).
* Etiqueta **solo el polígono del cuerpo** visible (sin atributos de identidad, sin recortar rostros).
* Genera la versión.

> Anota en `docs/plantilla_informe_tecnico.md` (sección 1) el número de imágenes de cada split.

> **Enlaces para el README:** en el plan gratuito los proyectos de Roboflow son públicos en Roboflow Universe;
> abre cada proyecto en Universe y copia las 3 URLs (`https://universe.roboflow.com/<workspace>/<proyecto>`) en la fila **Datasets de Roboflow** de
> la tabla *Enlaces de la entrega* del `README.md`. Si prefieres no publicarlos, comparte la URL de cada versión
> con el docente y súbelos exportados a Drive.

---

## Paso 2 · Entrenamiento en Google Colab (GPU T4)

Para cada notebook de `entrenamiento/`:

1. <https://colab.research.google.com> → *Archivo → Abrir cuaderno → GitHub* → pega la URL de tu repo → abre el notebook.
2. *Entorno de ejecución → Cambiar tipo de entorno → **T4 GPU*** → Guardar.
3. Icono **🔑 (Secretos)** de la barra izquierda → *Agregar secreto* `ROBOFLOW_API_KEY` = tu clave → activa
   *Acceso del notebook*.
4. Los notebooks 02–04 ya traen el dataset público configurado (`RF_WORKSPACE`, `RF_PROYECTO`, `RF_VERSION`)
   y el `REPO_URL`. No hay que editar nada. Si no existe el secreto, la celda pide la key con `getpass`.
5. *Entorno de ejecución → Ejecutar todas*. Acepta montar Google Drive cuando lo pida (al final de cada notebook).

| Notebook | Duración aprox. (T4) | Descarga | Copia en Drive |
|---|---|---|---|
| `01_m1_placas_bolivia_ocr` | 5–10 min | `m1_metricas_ocr.csv`, `m1_detalle_ocr.csv` | — |
| `02_m2_segmentacion_vehiculos_yoloseg` | ≤ 0.9 h de entrenamiento (40 épocas, tope por callback) | `m2_vehiculos_seg.pt`, `m2_metricas.json`, `m2_eval_bolivia.csv`, matriz de confusión | `pesos/` y `metricas/m2/` |
| `03_m3_keypoints_llantas_yolopose` | ≤ 0.5 h de entrenamiento (50 épocas, tope por callback) | `m3_llantas_pose.pt`, `m3_metricas.json`, `m3_eval_ejes.csv` | `pesos/` y `metricas/m3/` |
| `04_m4_segmentacion_pasajeros` | 5–15 min (solo evaluación, sin entrenar) | `m4_pasajeros_seg.pt` (COCO), `m4_metricas.json`, `m4_eval_conteo.csv` | `pesos/` y `metricas/m4/` |

6. Captura pantalla de: tabla de métricas OCR (01); mAP, matriz de confusión y exactitud en fotos bolivianas
   (02); mAP50 pose y **MAE de ejes** (03); MAE y % exacto del conteo de personas (04). Van a Drive
   `/recursos/metricas`.
7. Si Colab se desconecta: los pesos parciales quedan en `/content/runs/...`; vuelve a ejecutar desde la celda de
   entrenamiento con `resume=True` o copia `runs` a Drive antes.

> Los archivos `.pt` **no** se suben a GitHub (están en `.gitignore`): van a Drive y al Pod de RunPod.

---

## Paso 3 · Servidor de inferencia en RunPod (bono +5) + entornos (bono +5)

### 3.1 Crear el Pod
1. Cuenta en <https://runpod.io> → *Billing* → carga crédito (USD 10 alcanza para la demo; un Pod RTX A4000/3090
   cuesta ≈ USD 0.2–0.45/h).
2. *Pods → **Deploy*** → **Community Cloud** o **Secure Cloud** → GPU **RTX A4000 / RTX A5000 / RTX 3090**
   (16–24 GB). En *Filter* elige **CUDA 12.8 o superior** (la rueda de torch 2.15 de PyPI necesita driver reciente).
3. *Template:* **RunPod PyTorch** (la más reciente, `runpod/pytorch:…-py3.11-cuda12.x-devel-ubuntu22.04`).
4. *Edit Template:*
   * **Container Disk:** 30 GB · **Volume Disk:** 30 GB montado en `/workspace` (persiste al detener el Pod).
   * **Expose HTTP Ports:** `8888,8000` (8888 = Jupyter, **8000 = FastAPI**).
   * **Environment Variables:** `API_TOKEN` = un token largo. Genéralo en tu PC con
     `python -c "import secrets; print(secrets.token_urlsafe(32))"` y guárdalo (irá también a Laravel).
5. **Deploy On-Demand**. Cuando esté *Running*, anota el **POD_ID** (p. ej. `abc123xyz`).
   Tu URL pública será **`https://abc123xyz-8000.proxy.runpod.net`**.

### 3.2 Código, pesos y entornos
*Connect → Start Web Terminal* (o *Jupyter Lab → Terminal*):

```bash
cd /workspace
git clone https://github.com/<usuario>/sistema-peaje-ia.git
cd sistema-peaje-ia/inferencia
mkdir -p pesos
```

Sube los 3 `.pt` a `/workspace/sistema-peaje-ia/inferencia/pesos/` con el botón **Upload** de Jupyter Lab
(o `runpodctl send m2_vehiculos_seg.pt` desde tu PC y `runpodctl receive <código>` en el Pod).

**Evidencia de los 3 entornos (bono de entornos):**

```bash
apt-get update && apt-get install -y tmux
tmux new -s entornos
bash setup_runpod.sh todos      # crea peaje-venv (pip), peaje-conda (Tesseract) y peaje-mamba (EasyOCR)
# ...tarda 15-25 min. Al final queda corriendo el servidor del último entorno: Ctrl+C para detenerlo.
ls evidencias/                  # evidencia_entorno_runpod-venv.txt / -conda.txt / -mamba.txt (+ .json)
```

Revisa que cada `.txt` diga `cuda_disponible : True` y el nombre de la GPU. Descárgalos (clic derecho → *Download*
en Jupyter) → Drive `/recursos/entornos/`. Toma captura de la terminal mostrando los 3 diagnósticos.

> Si `cuda_disponible` sale `False`: el driver del host es más antiguo que la CUDA de la rueda. Reinstala torch
> con el comando que indica <https://pytorch.org/get-started/locally/> para la versión CUDA de `nvidia-smi`,
> o despliega otro Pod con filtro de CUDA más alto.

### 3.3 Levantar el servidor de producción (venv)
```bash
tmux new -s peaje            # sesión que sigue viva al cerrar el navegador
cd /workspace/sistema-peaje-ia/inferencia
export API_TOKEN="<el-mismo-token-del-paso-3.1>"
/workspace/venvs/peaje-venv/bin/uvicorn app:app --host 0.0.0.0 --port 8000
# Debe imprimir:  Servidor de inferencia listo -> INFERENCE_URL = https://<POD_ID>-8000.proxy.runpod.net
# Ctrl+B y luego D para salir dejando el servidor corriendo. Volver: tmux attach -t peaje
```

Verifica desde tu navegador/celular: `https://<POD_ID>-8000.proxy.runpod.net/health` → `"estado": "ok"`,
`"cuda_disponible": true`, `modos.m2 = "entrenado"`. Si un modo dice `fallback-…`, falta ese `.pt` en `pesos/`.

Prueba de inferencia desde tu PC:
```bash
cd sistema-peaje-ia/inferencia
python probar_cliente.py --url https://<POD_ID>-8000.proxy.runpod.net --token <API_TOKEN> \
  ../web/database/seeders/evidencias/01_1151DKL.jpg
```

> **Costos:** detén el Pod (*Stop*) cuando no lo uses; el volumen `/workspace` y el **POD_ID se conservan**, así que
> la URL no cambia. Antes de la defensa, inícialo con 15 min de anticipación y repite 3.3.

### 3.4 Alternativa Colab (respaldo intercambiable)
Abre `inferencia/servidor_inferencia_runpod_colab.ipynb` en Colab (T4) → *Ejecutar todas* → copia
`INFERENCE_URL` (`https://…trycloudflare.com`) e `INFERENCE_TOKEN` que imprime. En Laravel solo cambias esos dos
valores en *Estado del sistema*. (La URL de Cloudflare cambia en cada sesión.)

---

## Paso 4 · Google Cloud Console (OAuth 2.0)

1. <https://console.cloud.google.com> → crea el proyecto **Peaje IA**.
2. *APIs y servicios → **Pantalla de consentimiento de OAuth** (Google Auth Platform)*:
   * Tipo **Externo** · nombre *Peaje IA* · correo de asistencia · dominio autorizado `tu-dominio.com`.
   * Permisos: `openid`, `email`, `profile` (no sensibles).
   * **Público → Publicar app (En producción)**. Si la dejas *En prueba*, agrega como **usuarios de prueba** a
     `doc.alvaro.pinto.ma@unifranz.edu.bo`, a tu correo y al correo "intruso" con el que demostrarás *Acceso denegado*.
3. *Credenciales → Crear credenciales → ID de cliente de OAuth → **Aplicación web***:
   * Orígenes JavaScript: `https://peaje.tu-dominio.com`
   * URI de redireccionamiento: `https://peaje.tu-dominio.com/auth/google/callback`
   * Copia **Client ID** → `GOOGLE_CLIENT_ID` y **Client Secret** → `GOOGLE_CLIENT_SECRET` (solo en el `.env` del servidor).
4. *Crear credenciales → ID de cliente de OAuth → **Android***:
   * Nombre del paquete: **`bo.edu.unifranz.peajeia.peaje_ia_movil`**
   * **Huella SHA-1** del certificado con el que firmarás el APK:

   **Opción A — clave propia (recomendada para entregar el APK):**
   ```bash
   cd sistema-peaje-ia/movil/android/app
   keytool -genkey -v -keystore upload-keystore.jks -keyalg RSA -keysize 2048 -validity 10000 -alias upload
   keytool -list -v -keystore upload-keystore.jks -alias upload        # copia la línea "SHA1:"
   ```
   Crea `movil/android/key.properties` (**no se versiona**):
   ```properties
   storePassword=<la-contraseña-del-keystore>
   keyPassword=<la-contraseña-de-la-clave>
   keyAlias=upload
   storeFile=upload-keystore.jks
   ```
   **Opción B — clave debug** (si no creas `key.properties`, el APK release se firma con ella):
   ```powershell
   keytool -list -v -keystore "$env:USERPROFILE\.android\debug.keystore" -alias androiddebugkey -storepass android -keypass android
   ```
   También puedes ver todas las huellas con `cd movil/android && ./gradlew signingReport`.
   * Guarda el keystore `.jks` y sus contraseñas fuera del repo (p. ej. en tu gestor de contraseñas).
5. La app Android **no** usa `google-services.json`: usa el **Web Client ID** como `serverClientId`
   (`--dart-define=GOOGLE_SERVER_CLIENT_ID=…`). El cliente Android solo registra paquete + SHA-1.

---

## Paso 5 · Pusher (tiempo real) y Gemini (agente)

**Pusher Channels** (gratis: 200 k mensajes/día, 100 conexiones):
1. <https://dashboard.pusher.com> → *Channels → Create app* → nombre `peaje-ia` → cluster **`sa1`** (São Paulo,
   el más cercano a Bolivia) o `us2` → frontend *Vanilla JS*, backend *Laravel*.
2. *App Keys* → copia `app_id`, `key`, `secret`, `cluster` a `PUSHER_APP_ID/KEY/SECRET/CLUSTER`.
3. No hace falta configurar nada más: el panel usa el canal **privado** `private-peaje` y Laravel lo autoriza en
   `/broadcasting/auth` solo para usuarios de la lista blanca con sesión iniciada.

**Gemini API** (gratis con límites):
1. <https://aistudio.google.com/apikey> → *Create API key* → `GEMINI_API_KEY`.
2. `AGENTE_PROVEEDOR=gemini`, `GEMINI_MODEL=gemini-2.5-flash` (si Google retira ese modelo, cambia al flash vigente).
3. Si no hay clave o falla, el agente responde igual en **modo determinista** (campo `fuente: determinista`).

Alternativas: `AGENTE_PROVEEDOR=groq` + `GROQ_API_KEY` (<https://console.groq.com>), u **Ollama en RunPod**
(expón el puerto 11434 y usa `OLLAMA_BASE_URL=https://<POD_ID>-11434.proxy.runpod.net/v1`).

---

## Paso 6 · Desplegar `web/` en un subdominio HTTPS

Tres opciones equivalentes: **A** VPS con Nginx + Certbot, **B** hosting con cPanel o **C** Render gratuito
(Docker + PostgreSQL, sin administrar servidor). Para la entrega, la opción **C** es la más rápida.

Genera antes estos valores (guárdalos en tu gestor de contraseñas):
```bash
php -r "echo bin2hex(random_bytes(24)), PHP_EOL;"     # HARDWARE_TOKEN (también va en secrets.h del ESP32)
```

### Opción A · VPS Ubuntu 24.04 + Nginx + Certbot
1. **DNS:** en tu proveedor de dominio crea un registro **A** `peaje` → IP pública del VPS. Espera la propagación
   (`nslookup peaje.tu-dominio.com`).
2. Paquetes:
   ```bash
   sudo apt update && sudo apt install -y nginx git unzip sqlite3 \
     php8.3-fpm php8.3-cli php8.3-sqlite3 php8.3-mysql php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip \
     php8.3-gd php8.3-intl php8.3-bcmath
   curl -sS https://getcomposer.org/installer | php && sudo mv composer.phar /usr/local/bin/composer
   ```
3. Código:
   ```bash
   sudo mkdir -p /var/www && cd /var/www
   sudo git clone https://github.com/<usuario>/sistema-peaje-ia.git peaje
   sudo chown -R $USER:www-data peaje && cd peaje/web
   composer install --no-dev --optimize-autoloader
   cp .env.example .env && php artisan key:generate
   nano .env
   ```
   Valores mínimos del `.env`:
   ```dotenv
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://peaje.tu-dominio.com
   DB_CONNECTION=sqlite                      # o mysql con DB_HOST/DB_DATABASE/DB_USERNAME/DB_PASSWORD
   ADMIN_EMAIL=tu-correo@gmail.com
   GOOGLE_CLIENT_ID=...apps.googleusercontent.com
   GOOGLE_CLIENT_SECRET=...
   INFERENCE_URL=https://<POD_ID>-8000.proxy.runpod.net
   INFERENCE_TOKEN=<API_TOKEN del Pod>
   HARDWARE_TOKEN=<token generado>
   BROADCAST_CONNECTION=pusher
   PUSHER_APP_ID=... / PUSHER_APP_KEY=... / PUSHER_APP_SECRET=... / PUSHER_APP_CLUSTER=sa1
   AGENTE_PROVEEDOR=gemini
   GEMINI_API_KEY=...
   ```
   ```bash
   touch database/database.sqlite
   php artisan migrate --seed --force
   php artisan storage:link
   php artisan config:cache && php artisan route:cache && php artisan view:cache
   sudo chown -R www-data:www-data storage bootstrap/cache database
   sudo chmod -R 775 storage bootstrap/cache database
   ```
4. Nginx (`sudo nano /etc/nginx/sites-available/peaje`):
   ```nginx
   server {
       listen 80;
       server_name peaje.tu-dominio.com;
       root /var/www/peaje/web/public;
       index index.php;
       client_max_body_size 12M;
       location / { try_files $uri $uri/ /index.php?$query_string; }
       location ~ \.php$ {
           include snippets/fastcgi-php.conf;
           fastcgi_pass unix:/run/php/php8.3-fpm.sock;
           fastcgi_read_timeout 60;
       }
       location ~ /\.(?!well-known) { deny all; }
   }
   ```
   ```bash
   sudo ln -s /etc/nginx/sites-available/peaje /etc/nginx/sites-enabled/ && sudo nginx -t && sudo systemctl reload nginx
   sudo sed -i 's/^upload_max_filesize.*/upload_max_filesize = 10M/; s/^post_max_size.*/post_max_size = 12M/' /etc/php/8.3/fpm/php.ini
   sudo systemctl restart php8.3-fpm
   sudo apt install -y certbot python3-certbot-nginx && sudo certbot --nginx -d peaje.tu-dominio.com
   ```
5. Actualizaciones futuras: `cd /var/www/peaje && git pull && cd web && composer install --no-dev -o && php artisan migrate --force && php artisan optimize`.

### Opción B · Hosting compartido con cPanel
1. *Dominios → Crear dominio/subdominio* `peaje.tu-dominio.com` con **raíz del documento** `peaje/web/public`.
2. *Seleccionar versión de PHP* → **8.3** y activa: `pdo_sqlite`, `pdo_mysql`, `mbstring`, `curl`, `fileinfo`,
   `intl`, `gd`, `zip`, `bcmath`. En *Opciones*: `upload_max_filesize=10M`, `post_max_size=12M`.
3. Código: *Git™ Version Control → Create → Clone URL* tu repo en `/home/<usuario>/peaje`.
   Si no hay Composer en el hosting: en tu PC ejecuta `cd web && composer install --no-dev -o`, comprime `vendor/`
   y súbelo con el *Administrador de archivos* a `peaje/web/` → *Extract*. (Es solo empaquetado: nada queda
   ejecutándose en tu PC.)
4. **Base de datos:** SQLite (crea el archivo vacío `peaje/web/database/database.sqlite`) o *Asistente de bases de
   datos MySQL* (crea BD + usuario con todos los privilegios) y usa `DB_CONNECTION=mysql`.
5. `.env`: copia `.env.example` a `.env` con el *Administrador de archivos* y completa los valores de la opción A.
6. *Terminal* de cPanel (si existe):
   ```bash
   cd ~/peaje/web && php artisan key:generate && php artisan migrate --seed --force
   php artisan storage:link && php artisan optimize
   ```
   Sin terminal: crea un *Cron Job* temporal de una sola ejecución con
   `cd ~/peaje/web && php artisan migrate --seed --force && php artisan storage:link && php artisan optimize`
   y bórralo luego. Si `storage:link` falla por symlinks deshabilitados, pide al soporte habilitarlos.
7. *SSL/TLS Status → Run AutoSSL* para obtener HTTPS.

### Opción C · Render (gratuito: Docker + PostgreSQL) — sin servidor propio
El repositorio ya trae `web/Dockerfile` (PHP 8.3 + Apache con `pdo_pgsql`, `pdo_sqlite`, `pdo_mysql`, `gd`, `zip`,
`intl`, `mbstring`, `bcmath`, `curl` y Composer), `web/docker-entrypoint.sh` y el Blueprint `render.yaml` en la raíz.
En cada arranque el contenedor ejecuta `storage:link` → `migrate --force` (reintenta si la BD aún no responde) →
`peaje:sembrar-si-vacio` (seeders **solo** si la tabla `usuarios` está vacía) → `optimize` → Apache en `$PORT`.

1. Genera la clave de la app en tu PC (solo genera un texto; nada queda corriendo en tu PC):
   ```bash
   cd web && php artisan key:generate --show      # copia el valor completo base64:...
   ```
2. <https://dashboard.render.com> → **New → Blueprint** → conecta tu repositorio de GitHub → Render lee
   `render.yaml` y propone dos recursos: la BD **peaje-ia-db** (PostgreSQL, plan Free) y el servicio web
   **peaje-ia** (Docker, plan Free, raíz `web/`).
3. Completa las variables marcadas como *sync: false* (el resto ya viene en el Blueprint):

   | Variable | Valor |
   |---|---|
   | `APP_KEY` | el `base64:...` del punto 1 |
   | `APP_URL` | `https://peaje-ia.onrender.com` (o tu subdominio propio, ver punto 6) |
   | `ADMIN_EMAIL` / `OPERADOR_EMAIL` | tu correo de Google / un operador (opcional) |
   | `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET` | Paso 4 (el callback se arma solo: `<APP_URL>/auth/google/callback`) |
   | `GOOGLE_CLIENT_IDS_EXTRA` | vacío salvo que uses otro Client ID |
   | `INFERENCE_URL` / `INFERENCE_TOKEN` | `https://<POD_ID>-8000.proxy.runpod.net` / `API_TOKEN` del Pod |
   | `HARDWARE_TOKEN` | el token generado arriba (el mismo de `secrets.h`) |
   | `PUSHER_APP_ID` / `PUSHER_APP_KEY` / `PUSHER_APP_SECRET` | Paso 5 (si tu app de Pusher no es `us2`, cambia también `PUSHER_APP_CLUSTER`) |
   | `GEMINI_API_KEY` | Paso 5 |

   `DB_URL` se enlaza sola a la *Internal Database URL* de la BD. **Apply** → el primer build tarda unos 5–8 min.
4. En **Logs** debe aparecer `Tabla usuarios vacía: ejecutando seeders…` y luego
   `Peaje IA escuchando en el puerto …`. En los redeploys siguientes verás `se omiten los seeders` (no se duplica
   ni borra nada).
5. **Google Cloud → Credenciales → cliente Web:** agrega `https://peaje-ia.onrender.com` en *Orígenes de JavaScript*
   y `https://peaje-ia.onrender.com/auth/google/callback` en *URI de redireccionamiento*.
6. *(Opcional)* Subdominio propio: Render → servicio → **Settings → Custom Domains** → `peaje.tu-dominio.com` → crea
   en tu DNS el **CNAME** `peaje` → `peaje-ia.onrender.com`. Render emite el certificado HTTPS automáticamente.
   Luego actualiza `APP_URL`, las URIs de Google y `API_HOST` del ESP32 / `API_BASE_URL` del APK.
7. Exportar la BD de Render (la consola *Shell* no está en el plan gratuito): en tu PC, con la
   *External Database URL* (Render → peaje-ia-db → **Connect → External**):
   ```bash
   cd web && DB_CONNECTION=pgsql DB_URL="postgresql://...external..." php artisan peaje:exportar-bd
   ```
   El `.sql` usa TRUE/FALSE y comillas estándar: se importa igual en PostgreSQL, MySQL o SQLite.

> **Límites del plan gratuito (revísalos en la documentación de Render, cambian con el tiempo):**
> * El servicio **se duerme tras ~15 min sin tráfico** y el primer request tarda ~1 min en despertar. Ábrelo un par
>   de minutos antes de grabar el video; mientras el ESP32 está encendido (consulta cada 1 s) no se duerme.
> * El **disco del contenedor es efímero:** las evidencias subidas durante la demo se pierden al redeploy o al
>   reiniciarse el servicio (los registros en PostgreSQL sí persisten). Las 22 fotos semilla se reponen solas en cada
>   arranque. **Exporta la BD y descarga las evidencias justo después de grabar el video.**
> * La **PostgreSQL gratuita caduca** pasado un tiempo desde su creación (30 días a la fecha de esta guía): créala
>   cerca de la fecha de entrega o pasa a un plan de pago para la defensa.
> * Si PostgreSQL no está disponible puedes poner `DB_CONNECTION=sqlite` y quitar `DB_URL`: funciona, pero la BD
>   vive en el disco efímero (solo para pruebas).

Prueba local de la imagen (opcional, requiere Docker):
```bash
docker build -t peaje-web ./web
docker run --rm -p 8000:8000 --env-file web/.env peaje-web      # http://localhost:8000/up
```

### 6.1 Verificaciones obligatorias
```bash
php artisan tinker --execute="dump(App\Models\Usuario::where('email','doc.alvaro.pinto.ma@unifranz.edu.bo')->first(['email','rol','activo'])->toArray());"
# → rol: administrador, activo: true
```
1. Abre `https://peaje.tu-dominio.com` → *Iniciar sesión con Google* con **tu** cuenta (`ADMIN_EMAIL`).
2. *Estado del sistema*: pega `INFERENCE_URL` y `INFERENCE_TOKEN` (si no los pusiste en `.env`) → **Guardar** →
   el badge debe decir **en línea**, GPU ✅ y los 4 modelos.
3. *Panel* → *Registrar paso con una foto* → sube `01_1151DKL.jpg` → aparece en la lista en vivo **sin recargar**
   (indicador "en vivo (pusher)" en la esquina).
4. Prueba con una cuenta de Google **no** autorizada → pantalla **Acceso denegado**.

### 6.2 Exportar la base de datos para Drive
```bash
php artisan peaje:exportar-bd
# storage/exports/peaje_bd_AAAAmmdd_HHMMSS.sql y .json  -> descarga y sube a Drive /base_de_datos
```
Repite la exportación **después** de grabar el video para que incluya los pasos de la demo.

---

## Paso 7 · Compilar e instalar el APK

```powershell
cd sistema-peaje-ia\movil
flutter clean; flutter pub get
flutter analyze
flutter build apk --release `
  --dart-define=API_BASE_URL=https://peaje.tu-dominio.com `
  --dart-define=GOOGLE_SERVER_CLIENT_ID=<WEB_CLIENT_ID>.apps.googleusercontent.com
```
* Salida: `movil\build\app\outputs\flutter-apk\app-release.apk` → súbelo a Drive `/apk` como `peaje-ia.apk`.
* Instalación: con el celular en *Depuración USB* → `adb install -r build\app\outputs\flutter-apk\app-release.apk`,
  o copia el APK al teléfono y ábrelo (permite *Instalar apps desconocidas*).
* Primera ejecución: acepta el permiso de **cámara**. En *Ajustes* de la app verifica la URL con
  **Guardar y verificar** (debe decir ✅).
* Si el login falla con `DEVELOPER_ERROR` / código 10: la SHA-1 registrada no coincide con la del APK, o el
  `GOOGLE_SERVER_CLIENT_ID` no es el **Web** Client ID del mismo proyecto.

---

## Paso 8 · Circuito y firmware del ESP32

1. Arma el circuito según [`hardware/esp32_alerta_peaje/DIAGRAMA_CONEXION.md`](hardware/esp32_alerta_peaje/DIAGRAMA_CONEXION.md):
   LED azul → GPIO 25, verde → GPIO 26, rojo → GPIO 27 (cada uno con 220 Ω a GND) y buzzer → GPIO 14.
2. Arduino IDE 2.x → placa **esp32 by Espressif** → *ESP32 Dev Module* → librería **ArduinoJson 7.x**.
3. Copia `secrets.h.example` → **`secrets.h`** (ignorado por git) y completa:
   `WIFI_SSID`, `WIFI_PASSWORD` (red 2.4 GHz o hotspot del celular), `API_HOST "peaje.tu-dominio.com"`,
   `HARDWARE_TOKEN` (el mismo del `.env`) y `CASETA_ID` (ID de la caseta en *Panel → Casetas*).
   Recomendado: pega la CA raíz en `ROOT_CA` (`openssl s_client -showcerts -connect peaje.tu-dominio.com:443`).
4. **Subir** → Monitor serie 115200: `[wifi] OK` → `[sync] último evento id=…`.
5. Prueba: registra un paso desde el panel o la app → azul (inferencia) → **verde + 1 beep** si fue autorizado
   o **rojo + 3 beeps** si es alerta. En *Estado del sistema* el ESP32 aparece **conectado** con su RSSI.

---

## Paso 9 · Pruebas end-to-end y video

### 9.1 Checklist "cero localhost"
```bash
# En el servidor (web/.env) y en lo que compilaste:
grep -nE "localhost|127\.0\.0\.1|192\.168\.|10\.[0-9]+\." web/.env && echo "❌ CORREGIR" || echo "✅ .env sin direcciones locales"
```
- [ ] `APP_URL`, `INFERENCE_URL`, `GOOGLE_REDIRECT_URI` son **https://** públicos.
- [ ] `https://<POD_ID>-8000.proxy.runpod.net/health` responde desde los **datos móviles** del celular (sin Wi-Fi de casa).
- [ ] El APK se compiló con `API_BASE_URL=https://…` (en la app: *Ajustes* muestra el subdominio).
- [ ] `secrets.h` del ESP32 usa `API_HOST` del subdominio y el ESP32 funciona con el **hotspot del celular**.
- [ ] Tu PC puede estar **apagada** durante la demo: todo sigue funcionando.
- [ ] El docente puede iniciar sesión (administrador) y una cuenta ajena ve **Acceso denegado** (web y app).
- [ ] Los 4 estados del agente responden: `1151DKL` (1), `5592UTF` (2), `1917YBP` (3), `1153DKL` (4 + ¿Quisiste decir?).
- [ ] La BD exportada (`.sql` + `.json`) está en Drive.
- [ ] `git status` limpio y el repo público no contiene `.env`, `secrets.h`, `*.jks`, `key.properties`.

### 9.2 Carpeta de Google Drive sugerida
```text
Laboratorio_Final_Peaje_<Apellido>/
├── video/demo_peaje_ia.mp4
├── apk/peaje-ia.apk
├── base_de_datos/peaje_bd_*.sql, peaje_bd_*.json
├── recursos/metricas/ (capturas y CSV/JSON de los notebooks 01-04)
├── recursos/entornos/ (evidencia_entorno_runpod-venv|conda|mamba.txt + captura de terminal)
├── recursos/datasets/ (enlaces/exports de Roboflow)
├── recursos/hardware/ (foto del circuito)
├── capturas/ (panel, app, agente, estado del sistema, RunPod)
└── enlaces.txt (repo GitHub, subdominio, URL RunPod)
```

### 9.3 Guion minuto a minuto del video (8–12 min)

Prepara antes: Pod de RunPod encendido con modelos entrenados, panel abierto en el navegador (sesión del
administrador), app instalada, ESP32 conectado al hotspot y visible en cámara, 3–4 fotos impresas o en otra
pantalla (placas del seeder) y un video/escena real de vehículos.

| Tiempo | Qué mostrar | Qué decir / evidencia |
|---|---|---|
| **0:00–0:40** | Portada: nombre, materia, modalidad individual + 4 bonos. Diagrama de `docs/arquitectura.md` | "Todo corre en la nube: Roboflow, Colab, RunPod, subdominio HTTPS, Pusher y ESP32" |
| **0:40–1:40** | **(1) Autenticación Google**: login web con tu cuenta → panel. Luego cuenta no autorizada → **Acceso denegado**. *Usuarios*: `doc.alvaro.pinto.ma@unifranz.edu.bo` = administrador activo | Lista blanca + roles, sin contraseñas propias |
| **1:40–2:30** | **(2) Infraestructura en la nube**: URL del subdominio con candado HTTPS · RunPod → Pod *Running* → `…-8000.proxy.runpod.net/health` (GPU, 4 modelos, latencia) · *Estado del sistema* en línea | **Bono RunPod**: cambiar `INFERENCE_URL` en vivo (mostrar el formulario) |
| **2:30–3:20** | **(3) Entrenamiento**: Roboflow (3 proyectos), Colab con métricas: OCR (01), mAP M2 + matriz (02), mAP pose + **MAE ejes** (03) | Métricas reales del informe |
| **3:20–5:00** | **(4) App móvil – Modo Caseta**: login Google en la app, elegir caseta, **Iniciar captura 2–3 fps** apuntando a una placa del seeder → tarjeta con placa, categoría, ejes, pasajeros, Bs, *Autorizado* + sonido. Repetir con placa **no registrada** → rojo + alerta | **(5) M1 placas** en video real, **(6) M2 categoría**, **(7) M3 ejes** (escena lateral de camión/bus) |
| **5:00–5:50** | **Panel en tiempo real** (pantalla dividida con la app): el paso aparece sin recargar, contadores suben, alerta con sonido, *Marcar como atendida*, modal de evidencia anotada (máscara, placa, keypoints K1…Kn) | **(8) Registro en tiempo real + BD** |
| **5:50–6:30** | **Bono ESP32**: cámara al circuito: azul (inferencia) → verde + beep (autorizado) → rojo + 3 beeps (evasión). Monitor serie con la URL HTTPS | Sin PC: ESP32 en hotspot del celular |
| **6:30–7:10** | **Bono pasajeros**: foto/escena frontal de minibús con ocupantes → `pasajeros: N`, máscara **opaca**, columna *Pasajeros* en panel y KPI | Privacidad: sin reconocimiento facial ni recortes |
| **7:10–8:40** | **Agente**: burbuja flotante → `¿pasó la 1151-DKL?` (estado 1) · `5592UTF` (2) · `1917YBP` (3, evasión) · `1153DKL` (4 + **¿Quisiste decir 1151DKL?**) · `1935CXX` (3↔8) · foto de placa desde la app (Modo Consulta) · foto borrosa → "envía otra foto" | Datos solo de la BD; `fuente: llm:gemini` |
| **8:40–9:30** | **CRUD**: crear vehículo (placa `3456-bcd` → se normaliza), editar saldo, tarifas por ejes (matriz Bs), casetas; operador sin permisos de admin | Roles |
| **9:30–10:30** | **Bono entornos**: terminal de RunPod con `evidencia_entorno_runpod-venv/conda/mamba.txt`, archivos `requirements-venv.txt`, `environment-*.yml`, sección del README | Versiones fijadas por servicio |
| **10:30–11:30** | Repositorio GitHub público (estructura, `.gitignore`, `.env.example`, sin secretos) · `php artisan peaje:exportar-bd` y carpeta de Drive | Entregables |
| **11:30–12:00** | Cierre: resumen de los 3 modelos + 4 bonos, limitaciones y mejoras | — |

> Graba con OBS (pantalla + cámara del celular con *scrcpy* o una segunda cámara). Haz un ensayo completo antes.

---

## Solución de problemas

| Síntoma | Causa probable | Solución |
|---|---|---|
| Panel dice "actualización cada 5 s" en vez de "en vivo (pusher)" | Claves de Pusher vacías/erróneas o cluster distinto | Revisa `PUSHER_*`, `php artisan config:clear` |
| `/health` 502 en RunPod | Servidor no iniciado o escuchando en 127.0.0.1 | `tmux attach -t peaje`; usar `--host 0.0.0.0` |
| Estado del sistema: "rechazó el token" | `INFERENCE_TOKEN` ≠ `API_TOKEN` del Pod | Copia el mismo valor en ambos |
| Login web: `redirect_uri_mismatch` | URI no registrada en Google Cloud | Agrega `https://peaje.tu-dominio.com/auth/google/callback` |
| App: Acceso denegado con tu correo | Correo no está en *Usuarios* o inactivo | Agrégalo en el panel (administrador) |
| App: `DEVELOPER_ERROR` | SHA-1 o Web Client ID incorrectos | Paso 4.4 y 4.5 |
| Imágenes de evidencia rotas | Falta `storage:link` o `APP_URL` incorrecto | `php artisan storage:link`; `APP_URL` con https |
| ESP32 `HTTP -1` | Wi-Fi 5 GHz, hora/certificado o host mal escrito | Red 2.4 GHz, `API_HOST` sin `https://`, probar `ROOT_CA=""` |
| Placas mal leídas | Foto lejana/borrosa | Acercar la cámara; el agente sugiere la placa correcta; reentrenar detector (celda opcional del 01) |
