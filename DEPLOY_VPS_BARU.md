# Runbook Migrasi DekatPOS — Toko 1 ke VPS Baru

Target: memindahkan 1 instance SalePro (`/var/www/dekatpos`) dari VPS lama ke VPS baru,
dengan struktur yang siap ditambah jadi 4 toko.

## Peta server

| | VPS LAMA | VPS BARU |
|---|---|---|
| Host | `vmi2812332` | `VM-10-158` |
| User SSH | `root` | `ubuntu` (pakai `sudo`) |
| OS | Ubuntu 24.04.3 | Ubuntu 24.04.4 |
| RAM | 11 Gi (swap penuh) | 7.5 Gi (swap 18%) |
| DB | MariaDB 10.11.13 — `database_salepro` | belum ada |
| PHP | 8.3 | belum ada |
| Web server | (bukan nginx/apache) | **Caddy** — HTTPS otomatis |
| Path app | `/var/www/dekatpos` | `/var/www/toko1` |

## Isi placeholder ini dulu

```
IP_LAMA        = ...
IP_BARU        = ...
DOMAIN_TOKO1   = toko1.contoh.id
DB_PASS_BARU   = (generate baru, jangan pakai "PasswordKuat!")
```

---

# ⚠️ BACA DULU — 3 jebakan di codebase ini

Ketiganya sudah aku verifikasi langsung di source. Kalau dilewat, migrasinya rusak.

### 1. `reset:db` menghapus SELURUH database tiap menit

`app/Console/Kernel.php` berisi:

```php
$schedule->command('reset:db')->everyMinute();
```

Dan `app/Console/Commands/ResetDB.php` melakukan `truncate()` ke **semua tabel**, lalu
mengimpor ulang `public/salepro_data.sql`. Ini sisa dari mode demo SalePro.

**Konsekuensi:** kalau kamu memasang cron scheduler Laravel standar
(`* * * * * php artisan schedule:run`), seluruh data toko terhapus dalam 60 detik.
Di VPS lama ini tidak kejadian karena scheduler-nya memang tidak pernah dipasang.

→ Penanganannya di **Langkah 11**. Jangan pasang cron sebelum sampai sana.

### 2. Ada file sensitif yang bisa diunduh publik

Di dalam `public/`:

| File | Bahaya |
|---|---|
| `salepro_backup_1689056748.sql` (340 KB) | dump database, bisa diunduh siapa saja |
| `install.zip`, `frontend.zip` | installer, bisa dipakai reinstall paksa |
| `delete.php` | script hapus |
| `read_me/` | dokumentasi vendor |

→ Dibereskan di **Langkah 12**.

### 3. Upload user ada di `public/`, bukan hanya `storage/`

`public/images` (72 MB), `public/documents` (13 MB), `public/product`, `public/logo`,
`public/sale`, `public/purchase`, `public/quotation`, `public/transfer`, `public/stock_count`.

→ Jangan pakai `rsync --exclude=public`. Folder-folder ini **wajib** ikut.

---

# Langkah 1 — Install stack di VPS BARU

```bash
sudo apt update && sudo apt upgrade -y

sudo apt install -y mariadb-server mariadb-client

sudo apt install -y php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring \
  php8.3-xml php8.3-curl php8.3-zip php8.3-gd php8.3-bcmath php8.3-intl \
  php8.3-opcache

sudo apt install -y composer rsync

# verifikasi
php -v
php -m | tr '\n' ' '
mariadb --version
composer --version
```

Pastikan muncul di daftar modul: `zip`, `gd`, `mysqli`/`pdo_mysql`, `mbstring`, `bcmath`, `intl`.
`ext-zip` itu syarat wajib di `composer.json`; `gd` dipakai intervention/image untuk barcode
dan gambar produk.

# Langkah 2 — Tuning MariaDB & PHP

Untuk RAM 7.5 Gi dengan rencana 4 toko:

```bash
sudo tee /etc/mysql/mariadb.conf.d/99-tuning.cnf >/dev/null <<'EOF'
[mysqld]
innodb_buffer_pool_size = 1536M
innodb_log_file_size    = 256M
innodb_flush_method     = O_DIRECT
max_connections         = 80
tmp_table_size          = 64M
max_heap_table_size     = 64M
slow_query_log          = 1
slow_query_log_file     = /var/log/mysql/slow.log
long_query_time         = 2
EOF

sudo systemctl restart mariadb
```

```bash
sudo tee /etc/php/8.3/fpm/conf.d/99-salepro.ini >/dev/null <<'EOF'
memory_limit = 256M
upload_max_filesize = 32M
post_max_size = 32M
max_execution_time = 120
max_input_vars = 5000
opcache.enable = 1
opcache.memory_consumption = 192
opcache.max_accelerated_files = 20000
opcache.revalidate_freq = 2
EOF
```

Swap jangan agresif:

```bash
echo 'vm.swappiness=10' | sudo tee /etc/sysctl.d/99-swap.conf
sudo sysctl --system
```

# Langkah 3 — Amankan MariaDB & buat database

```bash
sudo mariadb-secure-installation
```

Jawaban: root password → set; anonymous users → Y; remote root → Y; test db → Y; reload → Y.

Nama DB pakai pola per-toko supaya rapi waktu nambah toko 2–4:

```bash
sudo mariadb <<'SQL'
CREATE DATABASE salepro_toko1 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'salepro_toko1'@'localhost' IDENTIFIED BY 'DB_PASS_BARU';
GRANT ALL PRIVILEGES ON salepro_toko1.* TO 'salepro_toko1'@'localhost';
FLUSH PRIVILEGES;
SQL
```

Tiap toko dapat user DB sendiri — kalau satu bocor, tiga lainnya tidak ikut jebol.

# Langkah 4 — Dump database di VPS LAMA

> Jalankan saat toko tutup / tidak ada transaksi. Semua transaksi yang masuk **setelah**
> dump ini tidak ikut pindah.

```bash
# di VPS LAMA
cd /root
sudo mysqldump --single-transaction --quick --routines --triggers \
  --default-character-set=utf8mb4 \
  database_salepro | gzip -9 > salepro_$(date +%F_%H%M).sql.gz

ls -lh salepro_*.sql.gz

# catat jumlah baris per tabel untuk dicocokkan nanti
sudo mysql -N -e "SELECT table_name, table_rows FROM information_schema.tables \
  WHERE table_schema='database_salepro' ORDER BY 1;" > /root/rowcount_lama.txt
wc -l /root/rowcount_lama.txt
```

Catatan: `--single-transaction` bikin dump konsisten tanpa mengunci tabel.
Jangan tambahkan `--no-tablespaces` — itu opsi MySQL, MariaDB akan menolaknya.

# Langkah 5 — Paketkan file aplikasi di VPS LAMA

```bash
# di VPS LAMA
cd /var/www
sudo tar czf /root/dekatpos_files.tar.gz \
  --exclude='dekatpos/storage/framework/cache/*' \
  --exclude='dekatpos/storage/framework/sessions/*' \
  --exclude='dekatpos/storage/framework/views/*' \
  --exclude='dekatpos/storage/logs/*' \
  --exclude='dekatpos/bootstrap/cache/*' \
  dekatpos

ls -lh /root/dekatpos_files.tar.gz
```

Yang di-exclude itu cache hasil kompilasi yang menyimpan **path absolut server lama** —
kalau ikut terbawa, aplikasi error karena mencari `/var/www/dekatpos` di server baru.
`vendor/` sengaja **ikut** supaya versi dependency-nya identik dengan yang sekarang jalan.

# Langkah 6 — Transfer ke VPS BARU

**Opsi A — langsung VPS ke VPS** (paling cepat):

```bash
# di VPS LAMA
scp /root/dekatpos_files.tar.gz /root/salepro_*.sql.gz ubuntu@IP_BARU:/tmp/
```

**Opsi B — lewat laptop** (kalau SSH antar-VPS diblokir):

```bash
# di laptop
scp root@IP_LAMA:/root/dekatpos_files.tar.gz .
scp root@IP_LAMA:/root/salepro_*.sql.gz .
scp dekatpos_files.tar.gz salepro_*.sql.gz ubuntu@IP_BARU:/tmp/
```

Ekstrak di VPS baru:

```bash
# di VPS BARU
cd /tmp
sudo mkdir -p /var/www
sudo tar xzf dekatpos_files.tar.gz -C /var/www
sudo mv /var/www/dekatpos /var/www/toko1
ls -la /var/www/toko1 | head
```

# Langkah 7 — Import database

```bash
# di VPS BARU
zcat /tmp/salepro_*.sql.gz | sudo mariadb --default-character-set=utf8mb4 salepro_toko1

# cocokkan dengan server lama
sudo mysql -N -e "SELECT table_name, table_rows FROM information_schema.tables \
  WHERE table_schema='salepro_toko1' ORDER BY 1;" > /tmp/rowcount_baru.txt
wc -l /tmp/rowcount_baru.txt
```

Jumlah baris `rowcount_lama.txt` dan `rowcount_baru.txt` harus sama.
(Angka `table_rows` InnoDB sifatnya perkiraan, jadi bandingkan jumlah **tabel** dulu;
verifikasi angka presisi ada di Langkah 13.)

# Langkah 8 — Konfigurasi `.env`

```bash
# di VPS BARU
cd /var/www/toko1
sudo cp .env .env.backup-lama
sudo grep '^APP_KEY=' .env    # ← CATAT nilainya, jangan sampai berubah
```

**`APP_KEY` wajib sama persis dengan server lama.** Kalau diganti, semua data terenkripsi
dan sesi user jadi tidak terbaca.

```bash
sudo nano /var/www/toko1/.env
```

Ubah baris berikut:

```env
APP_NAME=DekatPOS Toko 1
APP_ENV=production
APP_DEBUG=false
APP_URL=https://DOMAIN_TOKO1
APP_KEY=base64:...          # ← BIARKAN sama seperti server lama

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=salepro_toko1
DB_USERNAME=salepro_toko1
DB_PASSWORD=DB_PASS_BARU
DB_PREFIX=

CACHE_DRIVER=file
SESSION_DRIVER=file
QUEUE_CONNECTION=sync
LOG_CHANNEL=daily
LOG_LEVEL=warning
```

Tiga perubahan penting dari server lama:

- `APP_ENV=local` → `production` dan `APP_DEBUG=true` → `false`. Di server lama, setiap
  error menampilkan stack trace lengkap **berikut isi `.env` termasuk password database**
  ke siapa pun yang membuka halamannya.
- `APP_URL=http://localhost` → domain asli. Ini dipakai untuk generate URL gambar
  produk, link invoice, dan reset password — dengan `localhost` semuanya rusak.
- `CACHE_DRIVER=array` → `file`. `array` artinya cache hilang tiap request, jadi query
  `product_list`/`permissions` diulang terus. Ganti ke `file` bikin jauh lebih ringan.

# Langkah 9 — Permission, symlink, dan cache

```bash
cd /var/www/toko1

# hapus cache yang menyimpan path server lama
sudo rm -f bootstrap/cache/*.php
sudo rm -rf storage/framework/views/*

# symlink storage (yang lama menunjuk ke path server lama, sudah rusak)
sudo rm -f public/storage
sudo -u www-data php artisan storage:link

# kepemilikan & mode
sudo chown -R www-data:www-data /var/www/toko1
sudo find /var/www/toko1 -type d -exec chmod 755 {} \;
sudo find /var/www/toko1 -type f -exec chmod 644 {} \;
sudo chmod -R 775 storage bootstrap/cache
sudo chmod 640 .env

# regenerate autoload + cache produksi
sudo -u www-data COMPOSER_HOME=/tmp/composer composer dump-autoload -o --no-dev
sudo -u www-data php artisan config:clear
sudo -u www-data php artisan cache:clear
sudo -u www-data php artisan view:clear
sudo -u www-data php artisan route:clear
```

Cek koneksi database sebelum lanjut:

```bash
sudo -u www-data php artisan tinker --execute="echo DB::table('users')->count();"
```

Harus keluar angka jumlah user. Kalau error, `.env` atau grant DB-nya salah.

> Jangan jalankan `php artisan migrate` dulu. Database yang diimpor sudah lengkap.
> Kalau nanti perlu, cek `php artisan migrate:status` lebih dulu.

# Langkah 10 — PHP-FPM pool khusus toko 1

Matikan pool default supaya tidak makan RAM percuma:

```bash
sudo mv /etc/php/8.3/fpm/pool.d/www.conf /etc/php/8.3/fpm/pool.d/www.conf.disabled
```

```bash
sudo tee /etc/php/8.3/fpm/pool.d/toko1.conf >/dev/null <<'EOF'
[toko1]
user = www-data
group = www-data

listen = /run/php/salepro-toko1.sock
listen.owner = caddy
listen.group = caddy
listen.mode = 0660

pm = ondemand
pm.max_children = 6
pm.process_idle_timeout = 30s
pm.max_requests = 500

php_admin_value[memory_limit] = 256M
php_admin_value[upload_max_filesize] = 32M
php_admin_value[post_max_size] = 32M
php_admin_value[error_log] = /var/log/php-fpm-toko1.log
php_admin_flag[log_errors] = on

catch_workers_output = yes
EOF

sudo systemctl restart php8.3-fpm
sudo systemctl status php8.3-fpm --no-pager | head -5
ls -la /run/php/
```

`listen.owner = caddy` itu kuncinya: socket harus bisa dibaca Caddy, sementara proses
PHP tetap jalan sebagai `www-data` (pemilik file). `pm = ondemand` bikin worker toko yang
sepi otomatis mati dan RAM-nya kembali — ini yang bikin 4 toko muat di 7.5 Gi.

# Langkah 11 — Caddyfile & DNS

**Arahkan A record `DOMAIN_TOKO1` ke `IP_BARU` dulu** dan tunggu propagasi.
Caddy tidak bisa menerbitkan sertifikat sebelum domainnya resolve ke server ini.

```bash
dig +short DOMAIN_TOKO1     # harus keluar IP_BARU
```

Backup dulu, karena Caddy di VPS ini sudah melayani situs lain:

```bash
sudo cp /etc/caddy/Caddyfile /etc/caddy/Caddyfile.backup-$(date +%F)
sudo cat /etc/caddy/Caddyfile     # lihat isinya dulu
```

Tambahkan blok berikut **di bawah** konfigurasi yang sudah ada (jangan menimpa):

```caddyfile
DOMAIN_TOKO1 {
	root * /var/www/toko1/public
	encode zstd gzip

	request_body {
		max_size 32MB
	}

	route {
		# blokir file sensitif bawaan SalePro
		@blocked path /install.zip /frontend.zip /delete.php /*.sql /read_me/* /web.config
		error @blocked 404

		php_fastcgi unix//run/php/salepro-toko1.sock
		file_server
	}

	log {
		output file /var/log/caddy/toko1.log
		roll_size 20mb
		roll_keep 5
	}
}
```

```bash
sudo caddy validate --config /etc/caddy/Caddyfile
sudo systemctl reload caddy
sudo journalctl -u caddy -n 30 --no-pager
```

Tiga hal yang gampang salah di sini:

- `unix//run/php/...` — dua garis miring itu memang sintaks Caddy, bukan salah ketik.
- Blok `route { }` itu wajib. Caddy mengeksekusi direktif berdasarkan urutan bakunya,
  bukan urutan penulisan, dan `error`/`respond` bakunya dijalankan **setelah**
  `file_server`. Tanpa `route`, file seperti `install.zip` sudah terkirim duluan sebelum
  aturan 404-nya sempat jalan. Di dalam `route`, urutannya persis seperti yang ditulis.
- `php_fastcgi` sudah otomatis menangani pola `try_files` yang dibutuhkan Laravel —
  tidak perlu ditulis manual.

Kalau ada firewall:

```bash
sudo ufw status
sudo ufw allow 80,443/tcp   # hanya kalau ufw aktif
```

# Langkah 12 — Bersihkan file sensitif

```bash
cd /var/www/toko1/public
sudo rm -f salepro_backup_*.sql install.zip frontend.zip delete.php web.config
sudo rm -rf read_me saas-install-assets
sudo rm -f error_log
cd /var/www/toko1
sudo rm -f error_log .phpunit.result.cache
```

Verifikasi dari luar (harus 404):

```bash
curl -sI https://DOMAIN_TOKO1/install.zip | head -1
curl -sI https://DOMAIN_TOKO1/salepro_backup_1689056748.sql | head -1
curl -sI https://DOMAIN_TOKO1/.env | head -1
```

# Langkah 13 — Cron: HATI-HATI di sini

**Jangan pasang** `* * * * * php artisan schedule:run`. Lihat peringatan #1 di atas —
`reset:db` akan men-truncate seluruh tabel setiap menit.

Cara paling aman: panggil command yang memang dibutuhkan secara langsung, lewati scheduler.

```bash
sudo crontab -u www-data -e
```

```cron
*/5 * * * * cd /var/www/toko1 && /usr/bin/php artisan purchase:auto >> /var/log/salepro-toko1-cron.log 2>&1
0 0   * * * cd /var/www/toko1 && /usr/bin/php artisan dsoalert:find >> /var/log/salepro-toko1-cron.log 2>&1
```

Sebagai lapis pengaman kedua, netralkan juga `reset:db` di kode:

```bash
sudo nano /var/www/toko1/app/Console/Kernel.php
```

```php
protected function schedule(Schedule $schedule)
{
    $schedule->command('purchase:auto')->everyFiveMinutes();
    $schedule->command('dsoalert:find')->dailyAt('00:00');
    // $schedule->command('reset:db')->everyMinute();   // DEMO ONLY — JANGAN AKTIFKAN
    // $schedule->command('quote:daily')->everyMinute();
}
```

(`quote:daily` juga dimatikan karena command-nya tidak terdaftar — kalau jalan, hanya
menghasilkan error di log tiap menit.)

Backup harian, sekalian:

```bash
sudo mkdir -p /var/backups/salepro
sudo tee /usr/local/bin/backup-toko1.sh >/dev/null <<'EOF'
#!/bin/bash
D=$(date +%F)
mysqldump --single-transaction --quick --routines --triggers \
  salepro_toko1 | gzip -9 > /var/backups/salepro/toko1_$D.sql.gz
find /var/backups/salepro -name 'toko1_*.sql.gz' -mtime +14 -delete
EOF
sudo chmod +x /usr/local/bin/backup-toko1.sh

sudo crontab -e
# 30 2 * * * /usr/local/bin/backup-toko1.sh
```

# Langkah 14 — Verifikasi

**Teknis:**

```bash
curl -sI https://DOMAIN_TOKO1 | head -3          # HTTP/2 200
sudo tail -30 /var/log/caddy/toko1.log
sudo tail -30 /var/www/toko1/storage/logs/laravel-*.log
free -h                                          # available masih sehat?
```

Cocokkan jumlah baris presisi pada tabel-tabel utama — jalankan di **kedua** server,
hasilnya harus identik:

```bash
sudo mysql -e "SELECT
 (SELECT COUNT(*) FROM sales)     AS sales,
 (SELECT COUNT(*) FROM purchases) AS purchases,
 (SELECT COUNT(*) FROM products)  AS products,
 (SELECT COUNT(*) FROM users)     AS users,
 (SELECT COUNT(*) FROM product_warehouse) AS stok;" NAMA_DATABASE
```

**Fungsional — buka di browser dan cek satu per satu:**

- [ ] Login admin berhasil
- [ ] Gambar produk & logo tampil (ini yang paling sering rusak — penanda `APP_URL` salah)
- [ ] Daftar produk & stok per gudang benar
- [ ] Buat 1 transaksi POS uji → cek jurnalnya terbentuk
- [ ] Cetak/preview invoice
- [ ] Laporan Stok Rak terbuka
- [ ] Upload gambar produk baru berhasil (uji permission `public/product`)
- [ ] Export Excel berhasil (uji `ext-zip`)
- [ ] Hapus transaksi uji tadi

**Kalau gambar tidak muncul:** hampir selalu `APP_URL` masih salah atau
`public/storage` belum ter-symlink. Ulangi Langkah 8 & 9.

**Kalau 502 Bad Gateway:** socket PHP-FPM tidak terbaca Caddy.
Cek `ls -la /run/php/salepro-toko1.sock` — owner harus `caddy`.

# Langkah 15 — Cutover

1. Umumkan jendela maintenance ke kasir.
2. Hentikan transaksi di sistem lama.
3. **Ulangi Langkah 4 dan 7** (dump + import ulang) supaya transaksi terakhir ikut pindah.
4. Arahkan domain produksi ke `IP_BARU`.
5. Pantau `laravel.log` dan `free -h` selama 24 jam pertama.
6. **Jangan hapus VPS lama minimal 2 minggu.** Itu jalur rollback-mu.

Rollback = arahkan DNS kembali ke server lama. Karena server lama tidak disentuh
sama sekali selama proses ini, rollback-nya instan.

---

# Menambah toko 2, 3, 4

Setelah toko 1 stabil, tiap toko berikutnya mengulang pola yang sama dengan nama berbeda:

| Item | Toko 1 | Toko 2 | Toko 3 | Toko 4 |
|---|---|---|---|---|
| Path | `/var/www/toko1` | `/var/www/toko2` | `/var/www/toko3` | `/var/www/toko4` |
| Database | `salepro_toko1` | `salepro_toko2` | `salepro_toko3` | `salepro_toko4` |
| User DB | `salepro_toko1` | `salepro_toko2` | `salepro_toko3` | `salepro_toko4` |
| Socket | `salepro-toko1.sock` | `salepro-toko2.sock` | `salepro-toko3.sock` | `salepro-toko4.sock` |
| Pool | `toko1.conf` | `toko2.conf` | `toko3.conf` | `toko4.conf` |

Yang perlu diperhatikan waktu jadi 4:

- Turunkan `pm.max_children` jadi **4** per pool (4 toko × 4 = 16 worker, ~1.8 Gi puncak).
- `APP_KEY` tiap toko **harus digenerate baru** (`php artisan key:generate`) kalau
  databasenya fresh — beda dengan toko 1 yang memigrasi data lama.
- Pantau `free -h` dan `vmstat 1 5` (kolom `si`/`so`) tiap kali menambah toko.
  Kalau swap mulai aktif, berhenti dan upgrade RAM.
- Bottleneck sebenarnya **2 vCPU**, bukan RAM. Kalau laporan mulai lambat saat
  beberapa toko ramai bersamaan, tambah vCPU sebelum tambah RAM.
