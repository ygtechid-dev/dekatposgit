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
| `BASE` | `/var/www/salepro` | Path code SalePro di VPS |
| `CLONE_ROOT` | `/var/www` | Toko di-clone ke `/var/www/<subdomain>` |
| `DB_USER` / `DB_PASS` | `root` / password MySQL | MySQL lokal di VPS |
| `PHP_FPM` | `unix:/run/php/php8.1-fpm.sock` | Sesuaikan versi PHP di VPS (`php -v`) |
| `REF_DB` | *(kosong)* | Opsi: nama DB referensi utk clone schema+data |

### 3. Buat code SalePro di VPS (source)

```bash
# Di VPS, code SalePro harus sudah ada di /var/www/salepro
cd /var/www
git clone <repo-salepro> salepro
# atau
rsync -avh ~/App_Salepro/ root@150.109.22.82:/var/www/salepro/
```

### 4. DNS (Namecheap)

- `*.dekatpos.com`  A  `150.109.22.82`  ← wildcard, subdomain otomatis
- `cloner.dekatpos.com` A `150.109.22.82` ← optional, lebih spesifik

### 5. SSL cloner

```bash
certbot --nginx -d cloner.dekatpos.com --non-interactive --agree-tos -m admin@dekatpos.com
```

### 6. Akses panel

Buka `https://cloner.dekatpos.com` → isi form:

- **Nama Toko**: Barokah Mart
- **Subdomain**: `barokahmart`
- **Email / Password Admin**: (opsional)

Klik **Buat Toko** → otomatis:
1. Copy code dari `/var/www/salepro` ke `/var/www/barokahmart`
2. Buat DB `sp_barokahmart` + user `sp_barokahmart`
3. Jalankan `migrate` + `db:seed`
4. Tulis `.env` (APP_NAME, DB, APP_URL)
5. Buat Nginx config + reload
6. Daftarkan SSL Let's Encrypt

Hasil: `https://barokahmart.dekatpos.com` langsung bisa login.

## Struktur File

```
salepro-cloner/
├── index.php        # Backend PHP (config + aksi create/remove/list)
├── view.php         # UI (form + tabel toko)
└── setup-vps.sh     # Script sekali-jalan utk setup awal VPS
```

## Catatan Penting

- **Ref DB (`REF_DB`)**: isi dengan nama DB SalePro yang sudah berisi data base (mis. `salepro_local`). Tiap toko baru akan di-clone struktur+datanya. Kosongkan jika mau toko mulai bersih (hanya migrate+seed).
- **Storage upload**: tiap toko punya folder `public/storage` sendiri. Pastikan permissions `775`.
- **Max 10 toko**: tiap toko = 1 DB MySQL + 1 folder code. Estimasi: ~2-5 GB / toko (code+vendor), DB sesuai pemakaian.
- **Backup**: tiap toko adalah folder terpisah di `/var/www/`, gampang di-backup per toko.
