#!/usr/bin/env bash
# Se ejecuta EN EL SERVIDOR desde el pipeline: construye, levanta y migra.
set -euo pipefail
cd "$(dirname "$0")/.."
C="docker compose -f docker-compose.prod.yml"

echo "→ Construyendo imagen web";   $C build --pull web
echo "→ Levantando servicios";      $C up -d --remove-orphans --wait --wait-timeout 180 db
bash deploy/migrar.sh
$C up -d --remove-orphans --wait --wait-timeout 180 || { $C ps; $C logs --tail=80 web; exit 1; }

echo "→ Verificando";
$C exec -T web curl -fsS http://localhost/salud.php && echo
docker image prune -f >/dev/null
echo "✓ Publicado $(git log -1 --format='%h %s' 2>/dev/null || cat REVISION 2>/dev/null || true)"
