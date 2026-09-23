#!/usr/bin/env bash
# Se ejecuta EN EL SERVIDOR desde el pipeline: construye, levanta y migra.
set -euo pipefail
cd "$(dirname "$0")/.."
C="docker compose -f docker-compose.prod.yml"

echo "→ Construyendo imagen web";   $C build --pull web
echo "→ Levantando servicios";      $C up -d --remove-orphans --wait --wait-timeout 180 db
bash deploy/migrar.sh

echo "→ Permisos de la carpeta de videos (el panel sube videos ahí)"
MEDIA="${MEDIA_DIR:-../media}/video"; mkdir -p "$MEDIA"
$C run --rm --no-deps --user root --entrypoint sh -v "$(cd "$MEDIA" && pwd):/v" web \
   -c "chown -R 33:$(id -g) /v && chmod -R u+rwX,g+rwX /v && chmod g+s /v"
$C up -d --remove-orphans --wait --wait-timeout 180 || { $C ps; $C logs --tail=80 web; exit 1; }

echo "→ Verificando";
$C exec -T web curl -fsS http://localhost/salud.php && echo
docker image prune -f >/dev/null
echo "✓ Publicado $(git log -1 --format='%h %s' 2>/dev/null || cat REVISION 2>/dev/null || true)"
