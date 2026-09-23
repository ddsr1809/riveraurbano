#!/usr/bin/env bash
# =====================================================================
# Preparación ÚNICA del servidor Vultr (Ubuntu 24.04). Ejecutar como root:
#   curl -fsSL https://raw.githubusercontent.com/ddsr1809/riveraurbano/main/deploy/servidor-inicial.sh | bash
# o copiarlo al servidor y correr:  bash servidor-inicial.sh
# =====================================================================
set -euo pipefail
USUARIO=deploy
BASE=/opt/riveraurbano

echo "→ Actualizando sistema"
export DEBIAN_FRONTEND=noninteractive
apt-get update -q && apt-get upgrade -yq
apt-get install -yq ca-certificates curl rsync git ufw fail2ban unattended-upgrades

echo "→ Docker"
command -v docker >/dev/null || curl -fsSL https://get.docker.com | sh
systemctl enable --now docker

echo "→ Usuario $USUARIO (lo usa GitHub Actions para desplegar)"
id "$USUARIO" >/dev/null 2>&1 || adduser --disabled-password --gecos "" "$USUARIO"
usermod -aG docker "$USUARIO"
install -d -m 700 -o "$USUARIO" -g "$USUARIO" /home/$USUARIO/.ssh
touch /home/$USUARIO/.ssh/authorized_keys
chown "$USUARIO:$USUARIO" /home/$USUARIO/.ssh/authorized_keys && chmod 600 /home/$USUARIO/.ssh/authorized_keys

echo "→ Carpetas"
install -d -o "$USUARIO" -g "$USUARIO" "$BASE" "$BASE/app" "$BASE/media" "$BASE/media/video" "$BASE/respaldos"

echo "→ Memoria swap (2 GB) si no existe"
if ! swapon --show | grep -q .; then
  fallocate -l 2G /swapfile && chmod 600 /swapfile && mkswap /swapfile && swapon /swapfile
  echo '/swapfile none swap sw 0 0' >> /etc/fstab
fi

echo "→ Firewall: solo SSH, HTTP y HTTPS"
ufw allow OpenSSH
ufw allow 80/tcp
ufw allow 443/tcp
ufw allow 443/udp
ufw --force enable

echo "→ SSH: desactivar acceso con contraseña (solo si root ya tiene llave)"
if [[ -s /root/.ssh/authorized_keys ]]; then
  sed -ri 's/^#?PasswordAuthentication .*/PasswordAuthentication no/' /etc/ssh/sshd_config
  systemctl reload ssh || systemctl reload sshd
else
  echo "  (root no tiene llave SSH; se deja el acceso con contraseña)"
fi

echo "→ Respaldo diario de la base de datos a las 3:30 a.m."
echo "30 3 * * * $USUARIO bash $BASE/app/deploy/respaldo.sh >> $BASE/respaldos/respaldo.log 2>&1" > /etc/cron.d/riveraurbano-respaldo

echo
echo "✓ Servidor listo."
echo "  Siguiente paso: pega la llave PÚBLICA del pipeline en /home/$USUARIO/.ssh/authorized_keys"
