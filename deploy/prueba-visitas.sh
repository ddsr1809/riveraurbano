#!/usr/bin/env bash
# Prueba el registro de visitas: persona, bot, impostor, vista previa y señales del navegador.
#   MYSQL_CMD="mysql -h127.0.0.1 -uroot -pXXX rivera" GEOIP_DIR=tools/geoip-prueba deploy/prueba-visitas.sh http://127.0.0.1:8080
set -euo pipefail
URL="${1:-http://127.0.0.1:8080}"
sql() { $MYSQL_CMD -N -e "$1"; }
CH='Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'
GB='Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'
visita() { curl -fsS -H "X-Forwarded-For: $1" -A "$2" -H "Accept-Language: es-MX,es;q=0.9" -e "${3:-}" "$URL/es/"; }

pagina="$(visita 81.2.69.142 "$CH" "https://www.google.com/")"
token="$(grep -o '"visita":"[a-f0-9]\{32\}"' <<<"$pagina" | grep -o '[a-f0-9]\{32\}')"
[[ -n "$token" ]] || { echo "✗ la página no trae token de visita"; exit 1; }
visita 1.0.0.5  "$GB" >/dev/null     # Googlebot real (red de Google en la base de prueba)
visita 31.64.0.5 "$GB" >/dev/null    # Googlebot falso
visita 31.64.0.9 "WhatsApp/2.23.20.0 A" >/dev/null
curl -fsS -o /dev/null "$URL/favicon.ico" 2>/dev/null && { echo "✗ /favicon.ico no debería existir"; exit 1; } || true

# Señales del navegador: ejecutó JS, interactuó y dio clic a WhatsApp
for e in js interaccion whatsapp; do curl -fsS -o /dev/null -F "t=$token" -F "e=$e" -F "d=1920x1080" "$URL/v.php"; done

revisar() { local r; r="$(sql "$1")"; [[ "$r" == "$2" ]] || { echo "✗ $3 (esperaba '$2', obtuve '$r')"; exit 1; }; echo "✓ $3"; }
revisar "SELECT CONCAT(tipo,'|',fuente,'|',ciudad) FROM visitas WHERE token='$token'" "humano|Google|Londres" "persona desde Google, confirmada al interactuar, con ciudad"
revisar "SELECT tipo FROM visitas WHERE ip='1.0.0.5' ORDER BY id DESC LIMIT 1" "bot" "Googlebot real"
revisar "SELECT tipo FROM visitas WHERE ip='31.64.0.5' ORDER BY id DESC LIMIT 1" "sospechoso" "Googlebot falso detectado"
revisar "SELECT tipo FROM visitas WHERE ip='31.64.0.9' ORDER BY id DESC LIMIT 1" "vista_previa" "vista previa de WhatsApp"
revisar "SELECT COUNT(*) FROM visitas_eventos e JOIN visitas v ON v.id=e.visita_id WHERE v.token='$token' AND e.evento='whatsapp'" "1" "clic a WhatsApp registrado"
