#!/bin/sh
# Descarga las bases gratuitas DB-IP Lite (ciudad y proveedor/ASN). Sin cuenta ni pago.
# Licencia CC BY 4.0: el panel muestra el crédito "IP Geolocation by DB-IP".
# DB-IP publica una versión nueva cada mes; se revisa una vez al día y solo descarga si cambió el mes.
#   sh geoip-dbip.sh            → descarga una vez
#   sh geoip-dbip.sh --siempre  → se queda corriendo (lo usa el contenedor "geoip")
set -u
DIR="${GEOIP_DIR:-/usr/share/GeoIP}"
URL="${DBIP_URL:-https://download.db-ip.com/free}"
mkdir -p "$DIR"

mes_anterior() {
  a=$(date -u +%Y); m=$(date -u +%m); m=${m#0}
  if [ "$m" -eq 1 ]; then a=$((a - 1)); m=12; else m=$((m - 1)); fi
  printf '%04d-%02d' "$a" "$m"
}

bajar() {   # $1 = city-lite | asn-lite
  destino="$DIR/dbip-$1.mmdb"
  actual=$(date -u +%Y-%m)
  [ -f "$destino" ] && [ "$(cat "$destino.version" 2>/dev/null)" = "$actual" ] && return 0
  for mes in "$actual" "$(mes_anterior)"; do
    [ -f "$destino" ] && [ "$(cat "$destino.version" 2>/dev/null)" = "$mes" ] && return 0   # ya tenemos la más reciente publicada
    rm -f "$destino.gz.tmp" "$destino.tmp"
    if wget -q -T 120 -O "$destino.gz.tmp" "$URL/dbip-$1-$mes.mmdb.gz" \
       && gunzip -c "$destino.gz.tmp" > "$destino.tmp" \
       && [ "$(wc -c < "$destino.tmp")" -gt "${MIN_BYTES:-1000000}" ]; then
      mv -f "$destino.tmp" "$destino"          # reemplazo atómico: el sitio nunca lee un archivo a medias
      echo "$mes" > "$destino.version"
      rm -f "$destino.gz.tmp"
      echo "$(date -u '+%F %T') DB-IP $1 $mes actualizada"
      return 0
    fi
  done
  rm -f "$destino.gz.tmp" "$destino.tmp"
  echo "$(date -u '+%F %T') No se pudo descargar DB-IP $1 (se reintenta más tarde)" >&2
  return 1
}

actualizar() { bajar city-lite; bajar asn-lite; }

if [ "${1:-}" = "--siempre" ]; then
  while true; do actualizar; sleep 86400; done
else
  actualizar
fi
