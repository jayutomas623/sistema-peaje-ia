#!/bin/sh
# =====================================================================
#  Arranque del contenedor web (Render / Docker)
#   1. valida APP_KEY      2. storage:link      3. migrate --force (con reintentos)
#   4. seeders SOLO si la tabla usuarios está vacía (+ repone fotos semilla)
#   5. cachea config/rutas/vistas      6. Apache en el puerto $PORT (por defecto 8000)
# =====================================================================
set -eu
cd /var/www/html

export PORT="${PORT:-8000}"

if [ -z "${APP_KEY:-}" ]; then
  echo "ERROR: falta APP_KEY. Genérela con 'php artisan key:generate --show' y péguela (base64:...) en las variables de entorno." >&2
  exit 1
fi

# SQLite (solo pruebas: el disco de Render gratuito es efímero) -> crea el archivo si no existe
if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ]; then
  DB_FILE="${DB_DATABASE:-/var/www/html/database/database.sqlite}"
  mkdir -p "$(dirname "$DB_FILE")"
  [ -f "$DB_FILE" ] || touch "$DB_FILE"
  chown www-data:www-data "$DB_FILE" "$(dirname "$DB_FILE")"
fi

php artisan config:clear > /dev/null
php artisan storage:link > /dev/null 2>&1 || true   # idempotente: ya existe tras el primer arranque

# La BD gestionada (PostgreSQL de Render) puede tardar unos segundos en aceptar conexiones
intento=1
until php artisan migrate --force; do
  if [ "$intento" -ge 10 ]; then
    echo "ERROR: no se pudo migrar la base de datos tras $intento intentos." >&2
    exit 1
  fi
  echo "BD no disponible todavía (intento $intento/10); reintento en 5 s…"
  intento=$((intento + 1))
  sleep 5
done

# Seeders solo en el primer despliegue (usuarios vacía); nunca duplica ni borra datos existentes
php artisan peaje:sembrar-si-vacio

php artisan optimize

# Los comandos anteriores se ejecutaron como root: devuelve la propiedad a Apache (www-data)
chown -R www-data:www-data storage bootstrap/cache

echo "Peaje IA escuchando en el puerto ${PORT} (APP_URL=${APP_URL:-sin definir})"
exec "$@"
