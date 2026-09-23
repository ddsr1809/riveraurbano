#!/usr/bin/env bash
# Prueba el panel de principio a fin: crea un usuario, entra, guarda un texto y lo ve en el sitio.
#   deploy/prueba-panel.sh http://127.0.0.1:8080
set -euo pipefail
URL="${1:-http://127.0.0.1:8080}"
TMP="$(mktemp -d)"; trap 'rm -rf "$TMP"' EXIT
J="$TMP/cookies"

clave="$(php tools/crear-admin.php prueba-ci "Prueba CI" | awk '/Contraseña:/ {print $2}')"
[[ -n "$clave" ]] || { echo "✗ no se pudo crear el usuario"; exit 1; }

csrf() { grep -o 'name="csrf" value="[a-f0-9]*"' | head -1 | sed 's/.*value="//; s/"//'; }

t="$(curl -fsS -c "$J" -b "$J" "$URL/admin/" | csrf)"
malo="$(curl -fsS -c "$J" -b "$J" --data-urlencode "csrf=$t" --data-urlencode "usuario=prueba-ci" --data-urlencode "clave=mala" "$URL/admin/?p=inicio")"
grep -qF "Usuario o contraseña incorrectos" <<<"$malo" || { echo "✗ aceptó una contraseña incorrecta"; exit 1; }
echo "✓ rechaza contraseña incorrecta"
t="$(curl -fsS -c "$J" -b "$J" "$URL/admin/" | csrf)"
curl -fsS -c "$J" -b "$J" -o /dev/null -L --data-urlencode "csrf=$t" --data-urlencode "usuario=prueba-ci" --data-urlencode "clave=$clave" "$URL/admin/?p=inicio"
pagina="$(curl -fsS -c "$J" -b "$J" "$URL/admin/?p=textos&s=portada")"
grep -q "Textos del sitio" <<<"$pagina" || { echo "✗ no se pudo entrar al panel"; exit 1; }
echo "✓ acceso al panel"

t="$(csrf <<<"$pagina")"
curl -fsS -c "$J" -b "$J" -o /dev/null -L --data-urlencode "csrf=$t" --data-urlencode "s=portada" \
     --data-urlencode "t[hero.dato3][en]=Mexicali, Baja California (CI)" "$URL/admin/?p=textos"
sitio="$(curl -fsS "$URL/en/")"
grep -qF "Mexicali, Baja California (CI)" <<<"$sitio" || { echo "✗ el cambio no aparece en el sitio"; exit 1; }
echo "✓ guardar texto desde el panel"

# Sin CSRF no debe guardar
curl -fsS -c "$J" -b "$J" -o /dev/null -L --data-urlencode "t[hero.dato3][en]=HACK" "$URL/admin/?p=textos"
sitio="$(curl -fsS "$URL/en/")"
if grep -qF "HACK" <<<"$sitio"; then echo "✗ se aceptó un cambio sin token CSRF"; exit 1; fi
echo "✓ protección CSRF"
