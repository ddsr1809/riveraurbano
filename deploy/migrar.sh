#!/usr/bin/env bash
# Aplica el esquema, agrega textos nuevos y corre las migraciones pendientes.
# Es seguro correrlo en cada despliegue.
#   Producción (por defecto): usa el contenedor "db" de docker-compose.prod.yml
#   Otro servidor:  SQL_CMD="mysql -h127.0.0.1 -uroot -pXXX --default-character-set=utf8mb4" deploy/migrar.sh
set -euo pipefail
cd "$(dirname "$0")/.."

if [[ -z "${SQL_CMD:-}" ]]; then
  SQL_CMD="docker compose -f docker-compose.prod.yml exec -T db sh -c 'exec mariadb -uroot -p\"\$MARIADB_ROOT_PASSWORD\" --default-character-set=utf8mb4 \"\$@\"' sh"
fi
sql() { eval "$SQL_CMD" "$@"; }

echo "→ Esquema";                     sql < database/01_esquema.sql
echo "→ Textos y configuración nuevos"; sql < database/02_datos.sql

shopt -s nullglob
for f in database/migraciones/*.sql; do
  n="$(basename "$f")"
  [[ "$n" =~ ^[A-Za-z0-9_.-]+$ ]] || { echo "Nombre de migración no válido: $n"; exit 1; }
  ya="$(echo "SELECT COUNT(*) FROM rivera.migraciones WHERE archivo='$n';" | sql -N | tr -d '[:space:]')"
  if [[ "$ya" == "0" ]]; then
    echo "→ Migración $n"
    { echo "USE rivera;"; cat "$f"; echo; echo "INSERT INTO migraciones (archivo) VALUES ('$n');"; } | sql
  fi
done
echo "✓ Base de datos al día"
