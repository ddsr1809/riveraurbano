#!/usr/bin/env bash
# Verifica que el sitio responde en los dos idiomas y que la BD está bien.
#   deploy/prueba-humo.sh https://riveraurbano.com
set -euo pipefail
URL="${1:-http://127.0.0.1:8080}"
revisar() {
  local ruta="$1" esperado="$2" cuerpo
  cuerpo="$(curl -fsSL --max-time 20 "$URL$ruta")" || { echo "✗ $ruta no responde"; exit 1; }
  grep -qF -- "$esperado" <<<"$cuerpo" || { echo "✗ $ruta no contiene: $esperado"; exit 1; }
  echo "✓ $ruta"
}
revisar /salud.php '"ok":true'
revisar /es/ 'lang="es-MX"'
revisar /en/ 'lang="en"'
