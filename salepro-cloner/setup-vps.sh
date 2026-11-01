#!/usr/bin/env bash
# ============================================================
# SalePro Multi-Store Cloner - VPS Setup
# Target: Ubuntu + Nginx + PHP-FPM + MySQL
# VPS: 150.109.22.82  user: ninja-76%-river (root password)
# ============================================================
set -e

VPS_IP="150.109.22.82"
DOMAIN="dekatpos.com"
CLONER_SUB="cloner"
SSH="ssh root@${VPS_IP}"

echo "=========================================================="
echo "SalePro Multi-Store Cloner - VPS Setup"
echo "VPS: ${VPS_IP}  Domain: ${DOMAIN}"
echo "=========================================================="

# Run everything locally on the VPS (assumes you've SSH'd in)
# If running from your laptop: replace commands with ${SSH} 'cmd'

echo ""
echo "[1/6] Sistem update + PHP extensions"
apt-get update -qq
apt-get install -y -qq php-fpm php-cli php-mysql php-curl php-gd php-xml php-zip php-mbstring php-bcmath mysql-server certbot nginx unzip

echo ""
echo "[2/6] Aktifkan PHP-FPM & MySQL"
systemctl enable php8.1-fpm php8.1-cli
systemctl enable mysql nginx
systemctl start php8.1-fpm php8.1-cli
systemctl start mysql nginx

echo ""
echo "[3/6] Source SalePro code - taruh di /var/www/salepro"
if [ ! -d /var/www/salepro ]; then
    echo "  /var/www/salepro tidak ada. Clone dari git:"
    echo "    cd /var/www && git clone <repo-salepro> salepro"
    echo "  atau salin via: rsync -avh --delete <local>/App_Salepro/ root@${VPS_IP}:/var/www/salepro/"
fi

echo ""
echo "[4/6] Install SalePro cloner ke /var/www/salepro-cloner"
rsync -avh /Users/a/Sites/localhost/App_Salepro/salepro-cloner/ root@${VPS_IP}:/var/www/salepro-cloner/
echo "  atau: cp -r ~/App_Salepro/salepro-cloner /var/www/salepro-cloner"

echo ""
echo "[5/6] Nginx config untuk cloner"
cat > /etc/nginx/conf.d/salepro-cloner.conf <<NGINX
server {
    listen 80;
    server_name ${CLONER_SUB}.${DOMAIN};
    return 301 https://\$host\$request_uri;
}
server {
    listen 443 ssl http2;
    server_name ${CLONER_SUB}.${DOMAIN};

    ssl_certificate     /etc/letsencrypt/live/${CLONER_SUB}.${DOMAIN}/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/${CLONER_SUB}.${DOMAIN}/privkey.pem;

    root /var/www/salepro-cloner;
    index index.php;

    location / { try_files \$uri \$uri/ /index.php?\$query_string; }

    location = /index.php {
        fastcgi_pass unix:/run/php/php8.1-fpm.sock;
        include /etc/nginx/fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        fastcgi_param QUERY_STRING \$query_string;
        fastcgi_param REQUEST_METHOD \$request_method;
        fastcgi_param SCRIPT_NAME /index.php;
    }
    location ~ \.php$ { return 404; }
    location ~ /\.(?!well-known).* { deny all; }
}
NGINX
echo "  /etc/nginx/conf.d/salepro-cloner.conf dibuat"

echo ""
echo "[6/6] DNS + SSL"
echo "  Pastikan Namecheap DNS wildcard:  *.${DOMAIN}  A  ${VPS_IP}"
echo "  dan:  ${CLONER_SUB}.${DOMAIN}  A  ${VPS_IP}"
echo "  Setelah DNS aktif, register SSL:"
echo "    certbot --nginx -d ${CLONER_SUB}.${DOMAIN} --non-interactive --agree-tos -m admin@${DOMAIN}"

echo ""
echo "=========================================================="
echo "DONE. Buka: https://${CLONER_SUB}.${DOMAIN}"
echo "=========================================================="
echo ""
echo "PENTING - sebelum akses, edit /var/www/salepro-cloner/index.php bagian CONFIG:"
echo "  - PHP_FPM  (sesuai versi PHP di VPS, cek: php -v)"
echo "  - DB_PASS  (password root MySQL VPS)"
echo "  - BASE     (path code SalePro)"
echo "  - REF_DB   (opsional: DB referensi utk clone schema)"
echo ""
echo "Workflow:"
echo "  1. Buka https://${CLONER_SUB}.${DOMAIN}"
echo "  2. Isi form: nama toko + subdomain + admin"
echo "  3. Klik Buat Toko -> auto: DB, kode, .env, nginx, SSL"
echo "  4. Akses: https://subdomain.${DOMAIN}"
echo ""
