# Passwordless sudo untuk user PHP-FPM (www-data / ubuntu)
# Jalankan: sudo bash sudoers-setup.sh [web-user]
#   contoh: sudo bash sudoers-setup.sh www-data
# Default web-user: www-data

set -e
WEB_USER="${1:-www-data}"

id "$WEB_USER" >/dev/null 2>&1 || { echo "User $WEB_USER tidak ada. Coba: ubuntu"; exit 1; }

NGINX_BIN="$(command -v nginx || echo /usr/sbin/nginx)"
SYSTEMCTL_BIN="$(command -v systemctl || echo /usr/bin/systemctl)"
CP_BIN="$(command -v cp || echo /usr/bin/cp)"
RM_BIN="$(command -v rm || echo /usr/bin/rm)"
MKDIR_BIN="$(command -v mkdir || echo /usr/bin/mkdir)"
CHOWN_BIN="$(command -v chown || echo /usr/bin/chown)"
CHMOD_BIN="$(command -v chmod || echo /usr/bin/chmod)"
CERTBOT_BIN="$(readlink -f "$(command -v certbot 2>/dev/null)" 2>/dev/null || echo /usr/bin/certbot)"

echo "Binary yang diizinkan:"
echo "  nginx     : $NGINX_BIN"
echo "  systemctl : $SYSTEMCTL_BIN"
echo "  cp        : $CP_BIN"
echo "  rm        : $RM_BIN"
echo "  mkdir     : $MKDIR_BIN"
echo "  chown     : $CHOWN_BIN"
echo "  chmod     : $CHMOD_BIN"
echo "  certbot   : $CERTBOT_BIN"

cat > /etc/sudoers.d/salepro-cloner << SUDOERS
# SalePro Cloner - izin terbatas untuk user web
$WEB_USER ALL=(root) NOPASSWD: $CP_BIN, $NGINX_BIN, $SYSTEMCTL_BIN, $RM_BIN, $MKDIR_BIN, $CHOWN_BIN, $CHMOD_BIN, $CERTBOT_BIN
SUDOERS
chmod 0440 /etc/sudoers.d/salepro-cloner
visudo -cf /etc/sudoers.d/salepro-cloner

echo ""
echo "OK: passwordless sudo untuk $WEB_USER sudah aktif."
echo "Test:  sudo -u $WEB_USER sudo -n nginx -t"
