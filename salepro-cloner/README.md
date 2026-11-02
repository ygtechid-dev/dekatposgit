# SalePro Multi-Store Cloner

Web panel terpisah untuk membuat toko baru (subdomain + DB terpisah) dari codebase SalePro.

## Cara Pakai

### 1. Deploy ke VPS (150.109.22.82)

```bash
# Upload folder ini ke VPS
rsync -avh ~/App_Salepro/salepro-cloner/ root@150.109.22.82:/var/www/salepro-cloner/
```

### 2. Edit config di `/var/www/salepro-cloner/index.php`

Bagian paling atas (block `CONFIG`):

| Konstanta | Isi | Keterangan |
|---|---|---|
| `BASE` | `/var/www/salepro` | Path code SalePro di VPS (harus ada `artisan`, `vendor/`, `public/index.php`, **dan `Database.sql`**) |
| `CLONE_ROOT` | `/var/www` | Toko di-clone ke `/var/www/<subdomain>` |
| `DB_USER` / `DB_PASS` | user MySQL | MySQL lokal di VPS |
| `PHP_FPM` | `unix:/run/php/php8.3-fpm.sock` | Sesuaikan versi PHP di VPS (`php -v`) |
| `MAX_STORES` | `7` | Batas maksimal toko di VPS ini |

### 3. Buat code SalePro di VPS (source) + dependency

```bash
# Di VPS, code SalePro harus ada di /var/www/salepro
# PENTING: Database.sql, .env.example, dan folder public/ harus ikut
rsync -avh ~/App_Salepro/ root@150.109.22.82:/var/www/salepro/
# cek:
ls /var/www/salepro/Database.sql /var/www/salepro/.env.example /var/www/salepro/public/index.php
```

**Wajib `composer install` di BASE** (clone menyalin `vendor/` dari BASE):

```bash
# PHP extension minimal (Ubuntu 24.04)
sudo apt-get install -y php8.3-curl php8.3-xml php8.3-gd php8.3-mbstring php8.3-zip php8.3-bcmath php8.3-mysql

# Composer minimal 2.2 (Laravel 10 butuh composer-runtime-api ^2.2)
sudo composer self-update --2

cd /var/www/salepro
sudo cp .env.example .env
# Kalau lock dibuat di PHP 8.4 (symfony/css-selector v8 butuh PHP>=8.4), turunkan:
sudo composer update symfony/css-selector tijsverkoyen/css-to-inline-styles --with-dependencies --no-dev
sudo composer install --optimize-autoloader
sudo php artisan key:generate
```

> Catatan: Debugbar ada di `require-dev` + alias di `config/app.php`, jadi `composer install` harus **dengan** dev deps (jangan `--no-dev`), atau hapus alias Debugbar.

### 4. DNS (Namecheap)

- `*.dekatpos.com`  A  `150.109.22.82`  ← wildcard, subdomain otomatis
- `cloner.dekatpos.com` A `150.109.22.82` ← optional, lebih spesifik

### 5. SSL cloner + sudoers

```bash
# SSL untuk panel cloner
sudo certbot --nginx -d cloner.dekatpos.com --non-interactive --agree-tos -m admin@dekatpos.com

# Bolehkan PHP-FPM (www-data) menjalankan perintah privileged tanpa password
# (untuk write nginx conf, reload, dan certbot saat buat toko baru)
bash sudoers-setup.sh www-data
# kalau user web kamu "ubuntu", pakai: bash sudoers-setup.sh ubuntu
```

### 6. Akses panel

Buka `http://cloner.dekatpos.com` → isi form:

- **Nama Toko**: Barokah Mart
- **Subdomain**: `barokahmart`
- **Email / Password Admin**: (opsional)

Klik **Buat Toko** → otomatis:
1. Copy code dari `/var/www/salepro` ke `/var/www/barokahmart`
2. Buat DB `sp_barokahmart` + user `sp_barokahmart` (password DB di-generate acak & kuat)
3. Import `Database.sql` (sudah berisi tabel, roles, general_settings, admin default)
4. Tulis `.env` (DB_SOCKET dikosongkan, APP_KEY dibuat, cache lama dibersihkan)
5. Buat user admin (role Admin)
6. Buat Nginx config port-80 (langsung bisa diakses via HTTP) + reload
7. SSL Let's Encrypt dibuat di background

Hasil: `http://barokahmart.dekatpos.com` langsung bisa login (HTTPS siap ±30-60 detik).
- Login default bila form admin dikosongkan: `admin` / `demo@mail.com` (dari Database.sql)
- Login custom: email + password yang diinput di form cloner
- Kredensial DB ditampilkan di panel setelah toko dibuat

> **Catatan 404**: Kalau subdomain masih 404:
> - **Cara cepat**: di daftar toko klik **Perbaiki Nginx** (regenerate config + reload tanpa bikin ulang DB)
> - Cek DNS wildcard `*.dekatpos.com` → `150.109.22.82` aktif di Namecheap (`dig teslagi.dekatpos.com`)
> - `sudo nginx -t` harus `test successful`
> - Pastikan `sudoers-setup.sh` sudah dijalankan untuk user PHP-FPM
> - Log SSL: `cat /tmp/salepro-ssl-<subdomain>.log`
>
> **Catatan "Admin gagal dibuat"**: disebapkan `.env` hasil copy masih punya `DB_SOCKET` dev (MAMP). Versi terbaru sudah otomatis mengosongkan `DB_SOCKET`. Kalau toko lama bermasalah, hapus & buat ulang setelah update file.

## Struktur File

```
salepro-cloner/
├── index.php             # Backend PHP (config + aksi create/remove/list)
├── view.php              # UI (form + tabel toko)
├── salepro-cloner.conf   # Nginx config utk cloner (cloner.dekatpos.com)
├── setup-vps.sh          # Script setup awal VPS
└── sudoers-setup.sh      # Setup passwordless sudo utk PHP-FPM
```

## Catatan Penting

- **`Database.sql` wajib ada** di path `BASE`. Ini full dump yang sudah berisi semua tabel + data base (roles, general_settings, external_services, admin default). Tanpa ini, toko baru tidak bisa login.
- **Storage upload**: tiap toko punya folder `public/storage` sendiri. Permissions `775` otomatis di-set.
- **Max toko**: `MAX_STORES` (default 7). Bisa diubah di config `index.php`.
- **Backup**: tiap toko adalah folder terpisah di `/var/www/`, gampang di-backup per toko.
- **Hapus toko**: gunakan form "Hapus Toko" — DB, user, kode, dan nginx conf dihapus semua.
