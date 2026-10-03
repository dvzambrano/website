#!/usr/bin/env bash
# Despliegue desde esta máquina, sin el Git deploy de hPanel:
#
#   deploy/release.sh staging
#   deploy/release.sh production
#
# Opciones: --skip-tests (el commit ya pasó la suite en otra corrida que
# no quedó registrada), --yes (no pide confirmación en producción),
# --build-only (verifica, corre los tests y arma el release, sin tocar el
# servidor).
#
# Pasos: 1) exige main limpio y pusheado; 2) suite completa (una vez por
# commit: queda marcado en .git/ y el deploy a producción del mismo commit
# no la repite); 3) build del release con `git archive` +
# `composer install --no-dev` + `npm ci && npm run build` (el hosting no
# tiene npm); 4) sube el release a ~/tmp/ del servidor; 5) allá: backup del
# .env y mysqldump de la base central, reemplazo de los directorios de
# código con `rsync --delete` (nunca .env ni storage/) y deploy/deploy.sh.
# Producción exige que ese mismo commit ya esté en staging.
#
# Las migraciones NO corren en el deploy (ver deploy/deploy.sh).
set -euo pipefail

SSH_HOST=hostinger
# Relativo al home del usuario del hosting.
REMOTE_ROOT='domains/micalme.com/public_html'

env_name=''
skip_tests=0
assume_yes=0
build_only=0
for arg in "$@"; do
    case "$arg" in
        staging | production) env_name="$arg" ;;
        --skip-tests) skip_tests=1 ;;
        --yes) assume_yes=1 ;;
        --build-only) build_only=1 ;;
        *) echo "Argumento desconocido: $arg" >&2; exit 2 ;;
    esac
done
[ -n "$env_name" ] || { echo "Uso: deploy/release.sh staging|production [--skip-tests] [--yes] [--build-only]" >&2; exit 2; }

case "$env_name" in
    staging) remote_dir="$REMOTE_ROOT/subdomain_devtest" ;;
    production) remote_dir="$REMOTE_ROOT/domain" ;;
esac

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

if [ "$env_name" = production ] && [ "$build_only" -eq 0 ]; then
    staging_rev=$(ssh "$SSH_HOST" "cat ~/$REMOTE_ROOT/subdomain_devtest/REVISION 2>/dev/null || true")
    [ "$staging_rev" = "$sha" ] || { echo "Staging corre '${staging_rev:0:7}', no $short: despliega primero a staging." >&2; exit 1; }
    if [ "$assume_yes" -eq 0 ]; then
        read -r -p "Desplegar $short a PRODUCCIÓN (bots con dinero real). Escribe 'production' para confirmar: " answer
        [ "$answer" = production ] || { echo "Cancelado."; exit 1; }
    fi
fi

tested_marker="$(git rev-parse --git-dir)/micalme-tested-$sha"
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

build_dir="${TMPDIR:-/tmp}/micalme-release-$sha"
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
    # public/build no está en git y el hosting no tiene npm: Vite compila aquí.
    # node_modules no viaja al servidor.
    (cd "$build_dir" && npm ci --no-audit --no-fund && npm run build)
    rm -rf "$build_dir/node_modules"
    echo "$sha" > "$build_dir/REVISION"
fi

if [ "$build_only" -eq 1 ]; then
    step "Release listo en $build_dir (--build-only: el servidor no se tocó)"
    exit 0
fi

remote_release="tmp/micalme-release-$short"
step "Subiendo el release a ~/$remote_release"
ssh "$SSH_HOST" "mkdir -p ~/$remote_release"
rsync -az --delete "$build_dir/" "$SSH_HOST:$remote_release/"

step "Backup, reemplazo y deploy/deploy.sh en $env_name"
stamp=$(date -u +%Y%m%d-%H%M%S)
ssh "$SSH_HOST" bash -s -- "$remote_dir" "$env_name" "$stamp" "$remote_release" <<'REMOTE'
set -euo pipefail
app_dir=~/$1; env_name=$2; stamp=$3; release=~/$4
# Ni .env ni storage/.
code_dirs='app bootstrap config database deploy public resources routes vendor'
code_files='.htaccess artisan composer.json composer.lock REVISION'
cd "$app_dir"
# Un .env hecho a mano marca un destino real de deploy: sin él, no se toca nada.
test -f .env || { echo "No hay .env en $app_dir: no es un destino de deploy" >&2; exit 1; }

backups=~/backups/micalme-deploy
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
    # public/autodestroy y public/import.xls los escriben los bots en
    # runtime (no están en git): el exclude los protege del --delete.
    rsync -a --delete --exclude=/autodestroy/ --exclude=/import.xls "$release/$dir/" "$app_dir/$dir/"
done
for file in $code_files; do
    cp "$release/$file" "$app_dir/$file"
done
# storage/ solo recibe la estructura de carpetas que falte: logs, sesiones y
# archivos privados quedan como están.
rsync -a --ignore-existing "$release/storage/" "$app_dir/storage/"

bash deploy/deploy.sh
echo "Ahora: $(cat REVISION)"

# Se conservan los 3 releases más recientes en ~/tmp.
ls -dt ~/tmp/micalme-release-* 2>/dev/null | tail -n +4 | xargs -r rm -rf
REMOTE

step "Listo: $short en $env_name"
