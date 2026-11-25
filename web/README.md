# web/ — Laravel 12 (panel + API REST + agente + BD)

```bash
composer install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite          # o configure MySQL en .env
php artisan migrate --seed && php artisan storage:link
php artisan test                        # 42 pruebas (SQLite; con DB_CONNECTION=pgsql también pasan en PostgreSQL)
php artisan peaje:exportar-bd           # SQL + JSON para Drive
```

| Carpeta | Contenido |
|---|---|
| `app/Services/PasoService.php` | flujo del frame: ESP32 → inferencia → tarifa → saldo → evidencia → alerta → Pusher |
| `app/Services/Agente/` | `AgentePeajeService`, herramientas sobre la BD, proveedores Gemini / OpenAI-compatibles |
| `app/Services/PlacaService.php` | normalización boliviana, distancia OCR, sugerencias |
| `app/Http/Controllers/Api/` | API REST v1 |
| `database/seeders/evidencias/` | 22 fotos reales del curso + `manifest.csv` |
| `public/js/peaje.js`, `public/js/chat.js` | tiempo real (Echo + respaldo por sondeo) y chat/burbuja |

Docker / Render: `Dockerfile` (PHP 8.3 + Apache, pdo_pgsql/pdo_sqlite/pdo_mysql, gd, zip, intl, mbstring, bcmath,
curl, Composer) y `docker-entrypoint.sh` (storage:link → migrate --force → `peaje:sembrar-si-vacio` → optimize →
Apache en `$PORT`, por defecto 8000). Blueprint en `../render.yaml`.

```bash
docker build -t peaje-web . && docker run --rm -p 8000:8000 --env-file .env peaje-web
```

Despliegue en subdominio HTTPS (Render, VPS o cPanel): ver `../GUIA_PASO_A_PASO_POST_DESARROLLO.md` (Paso 6).
