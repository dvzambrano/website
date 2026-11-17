#!/usr/bin/env bash
# Despliegue desde esta máquina, sin el Git deploy de hPanel:
#
#   deploy/release.sh staging|production [--target hostinger|vps]
#                    [--skip-tests] [--yes] [--build-only]
#
# Destino (no aplica a --build-only): --target, o la variable DEPLOY_TARGET
# (deploy-assistant.sh la exporta), o una pregunta si hay terminal. Sin nada
# de eso falla: no hay destino por defecto.
#   hostinger  hosting compartido: ~/domains/micalme.com/public_html
#   vps        VPS (usuario deploy): /var/www/micalme.com/public_html
#
# Opciones: --skip-tests (el commit ya pasó la suite en otra corrida que
# no quedó registrada), --yes (no pide confirmación en producción),
# --build-only (verifica, corre los tests y arma el release, sin tocar el
# servidor).
#
# Pasos: 1) exige main limpio y pusheado; 2) suite completa (una vez por
# commit: queda marcado en .git/ y el deploy a producción del mismo commit
# no la repite); 3) build del release con `git archive` +
# `composer install --no-dev` + `npm ci && npm run build` (Vite compila aquí:
# el servidor no tiene npm y public/build no está en git); 4) sube el release
# a ~/tmp/ del usuario del destino; 5) allá: backup del .env y mysqldump de la
# base central, reemplazo de los directorios de código con `rsync --delete`
# (nunca .env ni storage/) y deploy/deploy.sh. Producción exige que ese mismo
# commit ya esté en staging.
#
# Las migraciones NO corren en el deploy (ver deploy/deploy.sh).
set -euo pipefail

APP_SLUG=micalme
env_name=''
skip_tests=0
assume_yes=0
build_only=0
target="${DEPLOY_TARGET:-}"

while [ $# -gt 0 ]; do
    case "$1" in
        staging | production) env_name="$1" ;;
        --skip-tests) skip_tests=1 ;;
        --yes) assume_yes=1 ;;
        --build-only) build_only=1 ;;
        --target)
            [ $# -ge 2 ] || { echo "--target requiere un valor (hostinger|vps)" >&2; exit 2; }
            target="$2"; shift ;;
        --target=*) target="${1#--target=}" ;;
        *) echo "Argumento desconocido: $1" >&2; exit 2 ;;
    esac
    shift
done
[ -n "$env_name" ] || { echo "Uso: deploy/release.sh staging|production [--target hostinger|vps] [--skip-tests] [--yes] [--build-only]" >&2; exit 2; }

# Elige el destino: --target / DEPLOY_TARGET, o pregunta si hay terminal.
pick_target() {
    local answer=""
    if [ -z "$target" ]; then
        if [ -t 0 ] && [ -t 1 ]; then
            echo "Destino del despliegue:"
            echo "  1) hostinger  (hosting compartido)"
            echo "  2) vps        (VPS, usuario deploy)"
            read -r -p "Selecciona una opción [1-2]: " answer || true
            case "$answer" in
                1) target=hostinger ;;
                2) target=vps ;;
                *) echo "Opción no válida." >&2; exit 2 ;;
            esac
        else
            echo "Falta el destino: usa --target hostinger|vps o DEPLOY_TARGET." >&2
            exit 2
        fi
    fi
    case "$target" in
        hostinger)
            SSH_HOST=hostinger
            REMOTE_ROOT_REL="domains/micalme.com/public_html"
            REMOTE_IS_HOME=1 ;;
        vps)
            SSH_HOST=vps-deploy
            REMOTE_ROOT_ABS="/var/www/micalme.com/public_html"
            REMOTE_IS_HOME=0 ;;
        *) echo "Destino desconocido: '$target' (usa hostinger o vps)." >&2; exit 2 ;;
    esac
}

cd "$(git rev-parse --show-toplevel)"
step() { printf '\n\033[1;34m==> %s\033[0m\n' "$*"; }

step "Verificando el repositorio"
[ "$(git rev-parse --abbrev-ref HEAD)" = main ] || { echo "Solo se despliega desde main." >&2; exit 1; }
[ -z "$(git status --porcelain)" ] || { echo "Hay cambios sin commitear." >&2; exit 1; }
git fetch --quiet origin main
sha=$(git rev-parse HEAD)
[ "$sha" = "$(git rev-parse origin/main)" ] || { echo "HEAD no coincide con origin/main: haz push (o pull) primero." >&2; exit 1; }
short=${sha:0:7}
echo "Commit $short → $env_name"

if [ "$build_only" -eq 0 ]; then
    pick_target
    echo "Destino: $target (alias SSH: $SSH_HOST)"
    # Rutas absolutas en el servidor: el hosting usa su home; el VPS, /var/www.
    REMOTE_HOME=$(ssh "$SSH_HOST" 'echo $HOME')
    if [ "$REMOTE_IS_HOME" -eq 1 ]; then
        REMOTE_ROOT_ABS="$REMOTE_HOME/$REMOTE_ROOT_REL"
    fi
    case "$env_name" in
        staging) remote_dir="$REMOTE_ROOT_ABS/subdomain_devtest" ;;
        production) remote_dir="$REMOTE_ROOT_ABS/domain" ;;
    esac
fi

if [ "$env_name" = production ] && [ "$build_only" -eq 0 ]; then
    staging_rev=$(ssh "$SSH_HOST" "cat '$REMOTE_ROOT_ABS/subdomain_devtest/REVISION' 2>/dev/null || true")
    [ "$staging_rev" = "$sha" ] || { echo "Staging corre '${staging_rev:0:7}', no $short: despliega primero a staging." >&2; exit 1; }
    if [ "$assume_yes" -eq 0 ]; then
        read -r -p "Desplegar $short a PRODUCCIÓN en $target (bots con dinero real). Escribe 'production' para confirmar: " answer
        [ "$answer" = production ] || { echo "Cancelado."; exit 1; }
    fi
fi

tested_marker="$(git rev-parse --git-dir)/$APP_SLUG-tested-$sha"
if [ "$skip_tests" -eq 1 ]; then
    step "Tests omitidos (--skip-tests)"
elif [ -f "$tested_marker" ]; then
    step "Tests: $short ya pasó la suite completa ($(cat "$tested_marker"))"
else
    step "Suite de tests"
    php artisan config:clear --quiet
    php artisan test
    date -u +'%Y-%m-%d %H:%M UTC' > "$tested_marker"
fi

build_dir="${TMPDIR:-/tmp}/$APP_SLUG-release-$sha"
if [ -f "$build_dir/REVISION" ]; then
    step "Build: reutilizando $build_dir"
else
    step "Build del release en $build_dir"
    rm -rf "$build_dir"
    mkdir -p "$build_dir"
    git archive HEAD | tar -x -C "$build_dir"
    composer install --working-dir="$build_dir" --no-dev --optimize-autoloader --no-interaction --prefer-dist --no-progress
    # dvzambrano/* se instalan por git clone: su .git/ no sirve en runtime y
    # sus pack files 0444 no se pueden pisar en el deploy siguiente.
    find "$build_dir/vendor" -type d -name .git -prune -exec rm -rf {} +
    # public/build no está en git y el servidor no tiene npm: Vite compila aquí,
    # desde el mismo commit, y los assets viajan con el release.
    (cd "$build_dir" && npm ci --no-audit --no-fund && npm run build)
    rm -rf "$build_dir/node_modules"
    echo "$sha" > "$build_dir/REVISION"
fi

if [ "$build_only" -eq 1 ]; then
    step "Release listo en $build_dir (--build-only: el servidor no se tocó)"
    exit 0
fi

remote_release="$REMOTE_HOME/tmp/$APP_SLUG-release-$short"
step "Subiendo el release a $remote_release ($target)"
ssh "$SSH_HOST" "mkdir -p '$remote_release'"
rsync -az --delete "$build_dir/" "$SSH_HOST:$remote_release/"

step "Backup, reemplazo y deploy/deploy.sh en $env_name ($target)"
stamp=$(date -u +%Y%m%d-%H%M%S)
ssh "$SSH_HOST" bash -s -- "$remote_dir" "$env_name" "$stamp" "$remote_release" "$REMOTE_HOME" "$REMOTE_IS_HOME" "$APP_SLUG" <<'REMOTE'
set -euo pipefail
app_dir=$1; env_name=$2; stamp=$3; release=$4; home=$5; is_home=$6; slug=$7
# Ni .env ni storage/.
code_dirs='app bootstrap config database deploy public resources routes vendor'
code_files='.htaccess artisan composer.json composer.lock REVISION'
cd "$app_dir"
# Un .env hecho a mano marca un destino real de deploy: sin él, no se toca nada.
test -f .env || { echo "No hay .env en $app_dir: no es un destino de deploy" >&2; exit 1; }

backups="$home/backups/$slug-deploy"
mkdir -p "$backups"
chmod 700 "$backups"
cp .env "$backups/$env_name.env.bak-$stamp"
env_value() { grep -E "^$1=" .env | tail -n1 | cut -d= -f2- | sed -e 's/^"\(.*\)"$/\1/' -e "s/^'\(.*\)'$/\1/"; }
# Solo la base central (usuarios, bots, cache, jobs): las bases de los
# tenants tienen sus propias credenciales y el deploy no las toca.
db=$(env_value DB_DATABASE)
MYSQL_PWD=$(env_value DB_PASSWORD) mysqldump --single-transaction --no-tablespaces \
    -h "$(env_value DB_HOST)" -u "$(env_value DB_USERNAME)" "$db" \
    | gzip > "$backups/$env_name-$db-before-deploy-$stamp.sql.gz"
chmod 600 "$backups/$env_name".env.bak-"$stamp" "$backups/$env_name-$db-before-deploy-$stamp.sql.gz"
echo "Backups: $backups/*$stamp*"

echo "Antes: $(cat REVISION 2>/dev/null || echo '(sin REVISION)')"
for dir in $code_dirs; do
    mkdir -p "$app_dir/$dir"
    # public/autodestroy y public/import.xls los escriben los bots en
    # runtime (no están en git): el exclude los protege del --delete.
    rsync -a --no-group --no-times --delete --exclude=/autodestroy/ --exclude=/import.xls "$release/$dir/" "$app_dir/$dir/"
done
for file in $code_files; do
    cp "$release/$file" "$app_dir/$file"
done
# storage/ solo recibe la estructura de carpetas que falte: logs, sesiones y
# archivos privados quedan como están.
rsync -a --ignore-existing "$release/storage/" "$app_dir/storage/"

# ===== FIX PERMISOS AUTOMÁTICO EN CADA DEPLOY =====
# Asegura que administrador (usuario que ejecuta artisan) tenga write en:
# - .env (lectura de env() en runtime)
# - vendor/ (composer dump-autoload)
# - storage/ y bootstrap/cache/ (logs, cache, sesiones)
# Esto cubre tanto el VPS (deploy user) como Hostinger (usuario home).
fix_deploy_perms() {
    local user_runtime="administrador"
    local group_web="www-data"
    local app_dir="$1"

    # .env - asegura lectura por usuario runtime
    if [ -f "$app_dir/.env" ]; then
        chown "$user_runtime:$group_web" "$app_dir/.env" 2>/dev/null || true
        chmod 664 "$app_dir/.env" 2>/dev/null || true
    fi

    # vendor/ - composer necesita escribir autoload_psr4.php
    if [ -d "$app_dir/vendor" ]; then
        chown -R "$user_runtime:$group_web" "$app_dir/vendor" 2>/dev/null || true
        chmod -R 775 "$app_dir/vendor" 2>/dev/null || true
    fi

    # storage/ y bootstrap/cache/ - Laravel write
    for dir in storage bootstrap/cache; do
        if [ -d "$app_dir/$dir" ]; then
            chown -R "$user_runtime:$group_web" "$app_dir/$dir" 2>/dev/null || true
            chmod -R 775 "$app_dir/$dir" 2>/dev/null || true
            # Setgid en directorios para herencia de grupo
            find "$app_dir/$dir" -type d -exec chmod g+s {} + 2>/dev/null || true
        fi
    done

    # public/autodestroy - bot writes
    if [ -d "$app_dir/public/autodestroy" ]; then
        chown -R "$user_runtime:$group_web" "$app_dir/public/autodestroy" 2>/dev/null || true
        chmod -R 775 "$app_dir/public/autodestroy" 2>/dev/null || true
    fi
}

fix_deploy_perms "$app_dir"

if [ "$is_home" -eq 0 ]; then
    # VPS: complementario - ACLs para www-data en dirs que Apache escribe
    mkdir -p bootstrap/cache public/autodestroy
    for rw in storage bootstrap/cache public/autodestroy; do
        find "$rw" -user deploy -exec chgrp www-data {} + 2>/dev/null || true
        find "$rw" -user deploy -type d -exec chmod 2775 {} + 2>/dev/null || true
        find "$rw" -user deploy -type f -exec chmod 664 {} + 2>/dev/null || true
        find "$rw" -user deploy -exec setfacl -m g::rwx {} + 2>/dev/null || true
        find "$rw" -user deploy -type d -exec setfacl -d -m g::rwx {} + 2>/dev/null || true
    done
    chgrp www-data public/autodestroy 2>/dev/null || true
fi

bash deploy/deploy.sh
echo "Ahora: $(cat REVISION)"

# Se conservan los 3 releases más recientes en tmp.
ls -dt "$home"/tmp/$slug-release-* 2>/dev/null | tail -n +4 | xargs -r rm -rf
REMOTE

step "Listo: $short en $env_name ($target)"
