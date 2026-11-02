# SalePro Multi-Store Cloner - VPS Setup
# Jalankan: bash setup-vps.sh
# Asumsi: SSH akses root VPS 150.109.22.82, PHP 8.3, Nginx, MySQL terinstall

set -e
echo "=== SalePro Cloner VPS Setup ==="

# 1. Update system
apt-get update -qq
apt-get install -y -qq php8.3-fpm php8.3-cli php8.3-mysql php8.3-xml php8.3-curl nginx mysql-server certbot python3-certbot-nginx unzip 2>&1 | tail -5

# 2. Buat folder cloner
mkdir -p /var/www/salepro-cloner
echo "Folder /var/www/salepro-cloner dibuat"

# 3. Cek code SalePro
if [ ! -d /var/www/salepro ]; then
  echo "ERROR: /var/www/salepro tidak ada!"
  echo "Upload dulu code SalePro:"
  echo "  scp -r ~/App_Salepro root@150.109.22.82:/var/www/salepro"
  echo "Pastikan /var/www/salepro/Database.sql dan /var/www/salepro/artisan ada"
  exit 1
else
  echo "OK: /var/www/salepro ditemukan"
  ls /var/www/salepro/artisan /var/www/salepro/Database.sql 2>&1 || echo "WARNING: artisan atau Database.sql tidak ada!"
fi

# 4. Aktifkan PHP-FPM dan Nginx
systemctl enable php8.3-fpm
systemctl start php8.3-fpm
systemctl enable nginx
systemctl start nginx

# 5. DNS (perlu di Namecheap):
echo ""
echo "=== LANGKAH MANUAL (Namecheap DNS) ==="
echo "Tambahkan record berikut di Namecheap (Domain dekatpos.com -> Advanced DNS):"
echo "  1. Host: *           Type: A   Value: 150.109.22.82   (wildcard, subdomain toko)"
echo "  2. Host: cloner     Type: A   Value: 150.109.22.82   (untuk panel cloner)"
echo ""
echo "Wait 5-10 menit sampai DNS aktif, lalu test: dig cloner.dekatpos.com"

# 6. Setup Nginx untuk cloner (HTTP dulu, SSL nanti)
cat > /etc/nginx/conf.d/salepro-cloner.conf << 'NGINX_CLONER'
server {
    listen 80;
    server_name cloner.dekatpos.com www.cloner.dekatpos.com;

    root /var/www/salepro-cloner;
    index index.php;

    client_max_body_size 64M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /index.php {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        include /etc/nginx/fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param QUERY_STRING $query_string;
        fastcgi_param REQUEST_METHOD $request_method;
        fastcgi_param SCRIPT_NAME /index.php;
    }

    location ~ \.php$ { return 404; }
    location ~ /\.(?!well-known).* { deny all; }
}
NGINX_CLONER

echo "Nginx config cloner ditulis"
nginx -t && systemctl reload nginx
echo "Nginx reloaded"

# 7. Buat user MySQL untuk cloner
read -p "Password MySQL untuk user 'cloner' (enter = default 'Cloner@2024'): " DB_PASS
DB_PASS="${DB_PASS:-Cloner@2024}"
mysql -u root -e "CREATE USER IF NOT EXISTS 'cloner'@'localhost' IDENTIFIED BY '$DB_PASS'; GRANT ALL PRIVILEGES ON *.* TO 'cloner'@'localhost' WITH GRANT OPTION; FLUSH PRIVILEGES;" 2>/dev/null || \
mysql -u root --skip-grant-tables -e "CREATE USER IF NOT EXISTS 'cloner'@'localhost' IDENTIFIED BY '$DB_PASS'; GRANT ALL PRIVILEGES ON *.* TO 'cloner'@'localhost' WITH GRANT OPTION; FLUSH PRIVILEGES;" 2>/dev/null || \
echo "WARNING: Buat user 'cloner' manual di MySQL"
echo "User MySQL cloner dibuat dengan password: $DB_PASS"

echo ""
echo "=== SETUP SELESAI ==="
echo ""
echo "1. Copy file cloner ke VPS:"
echo "   scp ~/App_Salepro/salepro-cloner/* root@150.109.22.82:/var/www/salepro-cloner/"
echo ""
echo "2. Edit /var/www/salepro-cloner/index.php:"
echo "   define('DB_USER', 'cloner');"
echo "   define('DB_PASS', '$DB_PASS');"
echo ""
echo "3. Buka: http://cloner.dekatpos.com  (belum SSL dulu)"
echo "   Setelah toko pertama dibuat, SSL akan otomatis dibuat certbot"
echo ""
