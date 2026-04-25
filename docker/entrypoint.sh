#!/bin/sh
set -e

echo "[entrypoint] Verificando conexión a MySQL..."
MAX=30
N=0
until php artisan migrate:status --database=landlord >/dev/null 2>&1; do
    N=$((N + 1))
    if [ "$N" -ge "$MAX" ]; then
        echo "[entrypoint] Error: MySQL no disponible después de ${MAX} intentos. Abortando."
        exit 1
    fi
    echo "[entrypoint] MySQL no listo ($N/$MAX), reintentando en 3s..."
    sleep 3
done

echo "[entrypoint] Ejecutando migraciones landlord..."
php artisan migrate --database=landlord --force

echo "[entrypoint] Optimizando..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

echo "[entrypoint] Iniciando supervisord..."
exec /usr/bin/supervisord -c /etc/supervisord.conf
