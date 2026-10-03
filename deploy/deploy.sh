#!/usr/bin/env bash
# Corre en el servidor, dentro de la carpeta de la app, invocado por
# deploy/release.sh DESPUÉS de que la máquina local armó el release
# (composer install --no-dev) y lo sincronizó con rsync: en el
# hosting no corren git, composer ni npm. Aquí solo va lo que depende del
# .env real del servidor.
set -euo pipefail

# Sin `migrate`: `php artisan migrate` ve también las migraciones de los
# paquetes de bots (tablas de tenant) y las crearía en la base central, y
# `modules:migrate-seed` hace `migrate:fresh` (borra datos). Las
# migraciones se siguen corriendo a mano por SSH, apuntando a la base y al
# --path que correspondan.

# Sin `config:cache`: app/ y varios paquetes dvzambrano/* leen env() en
# runtime, que con la config cacheada devuelve null. Se limpia por si quedó
# una de una corrida manual.
php artisan config:clear
php artisan route:cache
php artisan view:cache
php artisan event:cache
