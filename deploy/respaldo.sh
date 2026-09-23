#!/usr/bin/env bash
# Respaldo diario de la base de datos (lo programa servidor-inicial.sh en cron).
set -euo pipefail
cd /opt/riveraurbano/app
DEST=/opt/riveraurbano/respaldos
docker compose -f docker-compose.prod.yml exec -T db sh -c \
  'exec mariadb-dump -uroot -p"$MARIADB_ROOT_PASSWORD" --single-transaction --routines --databases rivera' \
  | gzip > "$DEST/rivera-$(date +%F).sql.gz"
find "$DEST" -name 'rivera-*.sql.gz' -mtime +14 -delete
echo "$(date '+%F %T') respaldo OK"
