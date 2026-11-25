# =====================================================================
#  Peaje IA · web/ (Laravel 12) — imagen para Render (plan gratuito) o cualquier host Docker
#  PHP 8.3 + Apache · pdo_pgsql, pdo_sqlite, pdo_mysql, gd, zip, intl, mbstring, bcmath, curl + Composer
#
#  Local:  docker build -t peaje-web ./web
#          docker run --rm -p 8000:8000 --env-file web/.env peaje-web
#  Render: Root Directory = web · Runtime = Docker (ver render.yaml en la raíz del repo)
# =====================================================================
FROM php:8.3-apache-bookworm

ENV DEBIAN_FRONTEND=noninteractive \
    COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_NO_INTERACTION=1 \
    APACHE_DOCUMENT_ROOT=/var/www/html/public \
    PORT=8000

# ---------- Extensiones PHP ----------
# mbstring, curl y pdo_sqlite ya vienen compiladas en la imagen oficial; se instalan las que faltan
# y luego se verifica la lista completa (el build falla si falta alguna).
RUN apt-get update \
 && apt-get install -y --no-install-recommends \
      libpq-dev libzip-dev libicu-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
      libsqlite3-dev unzip git ca-certificates \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j"$(nproc)" pdo_pgsql pdo_mysql gd zip intl bcmath opcache \
 && rm -rf /var/lib/apt/lists/* \
 && for ext in pdo_pgsql pdo_sqlite pdo_mysql gd zip intl mbstring bcmath curl openssl; do \
      php -m | grep -qix "$ext" || { echo "Falta la extensión PHP: $ext"; exit 1; }; \
    done

COPY --from=composer:2.8 /usr/bin/composer /usr/local/bin/composer

# ---------- PHP y Apache ----------
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
 && { \
      echo 'memory_limit=256M'; \
      echo 'upload_max_filesize=12M'; \
      echo 'post_max_size=14M'; \
      echo 'max_execution_time=60'; \
      echo 'expose_php=Off'; \
      echo 'opcache.enable=1'; \
      echo 'opcache.validate_timestamps=0'; \
    } > "$PHP_INI_DIR/conf.d/zz-peaje.ini" \
 && a2enmod rewrite headers \
 && sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
 && sed -ri 's!<VirtualHost \*:80>!<VirtualHost *:${PORT}>!' /etc/apache2/sites-available/000-default.conf \
 && sed -ri 's!^Listen 80$!Listen ${PORT}!' /etc/apache2/ports.conf \
 && { \
      echo 'ServerName localhost'; \
      echo 'ServerTokens Prod'; \
      echo 'ServerSignature Off'; \
      echo '<Directory ${APACHE_DOCUMENT_ROOT}>'; \
      echo '    AllowOverride All'; \
      echo '    Require all granted'; \
      echo '</Directory>'; \
    } > /etc/apache2/conf-available/peaje.conf \
 && a2enconf peaje

WORKDIR /var/www/html

# ---------- Dependencias (capa cacheable) ----------
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

# ---------- Código ----------
COPY . .
# Durante `docker build` no existen las variables de Render (PUSHER_*, APP_KEY…): Laravel arranca sin broadcaster.
# sed elimina CRLF por si el repositorio se clonó en Windows (sh no ejecuta scripts con \r).
RUN BROADCAST_CONNECTION=null composer dump-autoload --optimize --no-dev \
 && BROADCAST_CONNECTION=null php artisan package:discover --ansi \
 && mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions \
             storage/framework/views storage/logs bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache database \
 && sed -i 's/\r$//' docker-entrypoint.sh \
 && chmod +x docker-entrypoint.sh

EXPOSE 8000
HEALTHCHECK --interval=30s --timeout=5s --start-period=60s --retries=3 \
  CMD curl -fsS "http://127.0.0.1:${PORT}/up" > /dev/null || exit 1

ENTRYPOINT ["/var/www/html/docker-entrypoint.sh"]
CMD ["apache2-foreground"]
