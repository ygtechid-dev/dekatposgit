<?php
// ============================================================
// SalePro Multi-Store Cloner - Backend Logic
// Deploy to VPS: /var/www/salepro-cloner/
// ============================================================

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
set_time_limit(0);

// ---------- CONFIG (edit sesuai environment VPS) ----------
// BASE = path SalePro source code di VPS (bukan cloner!)
define('BASE',        '/var/www/salepro');
define('CLONE_ROOT',  '/var/www');
define('ROOT_DOMAIN', 'dekatpos.com');
define('DB_HOST',     '127.0.0.1');
define('DB_PORT',     '3306');
define('DB_USER',     'newuser');
define('DB_PASS',     'passwordku');
// Reference DB to clone from (optional). Empty = run migrate+seed only.
define('REF_DB',      '');
define('NGINX_DIR',   '/etc/nginx/conf.d');
define('PHP_FPM',     'unix:/run/php/php8.3-fpm.sock');
// User pemilik file clone (harus sama dengan user PHP-FPM pool)
define('PHP_USER',    'www-data');
define('MAX_STORES',  7);
// ------------------------------------------------------------

$result = null;

$isAjax = (
    (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') ||
    (($_SERVER['HTTP_ACCEPT'] ?? '') === 'application/json')
);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $result = handleAction($_POST);
}

function handleAction($p)
{
    global $result;
    switch ($p['action'] ?? '') {
        case 'create': $result = createStore($p); break;
        case 'remove': $result = removeStore($p); break;
        case 'repair': $result = repairStore($p); break;
        case 'fixenv': $result = fixEnvStore($p); break;
        case 'diag':   $result = diagStore($p); break;
        case 'list':   $result = listStores(); break;
        default:       $result = ['ok' => false, 'msg' => 'unknown action'];
    }
    return $result;
}

/**
 * Regenerate & reinstall the nginx config for an existing store, then reload.
 * Use this when a store's DB + code exist but the subdomain returns 404.
 */
function repairStore($p){
    $sub = strtolower(trim($p['subdomain'] ?? ''));
    if ($sub === '' || !preg_match('/^[a-z0-9-]{2,30}$/', $sub)) {
        return ['ok' => false, 'msg' => 'Subdomain tidak valid.'];
    }
    $clonePath = CLONE_ROOT . '/' . $sub;
    if (!is_dir($clonePath)) {
        return ['ok' => false, 'msg' => "Folder toko $clonePath tidak ada. Buat ulang toko."];
    }

    // Kalau ada file inti yang hilang (mis. public/index.php), isi dari BASE tanpa menimpa .env
    $repaired = [];
    if (!is_file("$clonePath/public/index.php") && is_file(BASE . '/public/index.php')) {
        shell_exec("sudo -n chown -R " . PHP_USER . ":" . PHP_USER . " " . escapeshellarg($clonePath) . " 2>&1");
        shell_exec("sudo -n mkdir -p " . escapeshellarg($clonePath) . " 2>&1");
        $out2 = trim((string)shell_exec(
            "rsync -rlD --ignore-existing --exclude=.git --exclude=dekatposgit --exclude=salepro-cloner --exclude=.env " .
            escapeshellarg(rtrim(BASE, '/') . '/') . " " . escapeshellarg(rtrim($clonePath, '/') . '/') . " 2>&1"
        ));
        if ($out2 !== '') $repaired[] = 'rsync: ' . $out2;
        else $repaired[] = 'file hilang disalin dari BASE';
    }
    // Pastikan semua file terbaca PHP-FPM dan milik user PHP-FPM
    shell_exec("sudo -n chown -R " . PHP_USER . ":" . PHP_USER . " " . escapeshellarg($clonePath) . " 2>&1; chmod -R a+rX " . escapeshellarg($clonePath) . " 2>&1; chmod -R 775 " . escapeshellarg($clonePath . '/storage') . " " . escapeshellarg($clonePath . '/bootstrap') . " 2>&1");

    if (!is_file("$clonePath/public/index.php")) {
        return ['ok' => false, 'msg' => "public/index.php masih tidak ada di $clonePath. Cek BASE (" . BASE . "/public)."];
    }

    $confPath = NGINX_DIR . "/salepro-$sub.conf";
    $confHasSsl = is_file($confPath) && strpos((string)@file_get_contents($confPath), 'listen 443') !== false;

    if ($confHasSsl) {
        // Jangan timpa config yang sudah punya SSL (dibuat certbot). Cukup test & reload.
        $out = trim((string)shell_exec("sudo -n nginx -t 2>&1; sudo -n systemctl reload nginx 2>&1"));
        $ok  = strpos($out, 'test is successful') !== false;
        $msg = $ok ? "Nginx (dengan SSL) OK & reloaded. Buka https://$sub." . ROOT_DOMAIN : "Gagal: $out";
    } else {
        $stagingFile = sys_get_temp_dir() . "/salepro-nginx-$sub.conf";
        if (@file_put_contents($stagingFile, nginxConfig($sub, $clonePath)) === false) {
            return ['ok' => false, 'msg' => "Gagal tulis staging file $stagingFile"];
        }
        $out = trim((string)shell_exec(
            "sudo -n cp $stagingFile $confPath 2>&1; " .
            "sudo -n nginx -t 2>&1; sudo -n systemctl reload nginx 2>&1; " .
            "sudo -n rm -f $stagingFile 2>&1"
        ));
        $ok = strpos($out, 'test is successful') !== false;
        $msg = $ok
            ? "Nginx untuk $sub diperbaiki & di-reload. Buka http://$sub." . ROOT_DOMAIN
            : "Gagal: $out";
    }
    if ($repaired) $msg .= "\n(" . implode(', ', $repaired) . ")";
    return ['ok' => $ok, 'msg' => $msg];
}

/**
 * Perbaiki .env toko: pastikan USER_VERIFIED=1 (non-demo) + PRODUCT_MODE=CLIENT,
 * lalu bersihkan cache config agar env() terbaca ulang.
 */
function fixEnvStore($p)
{
    $sub = strtolower(trim($p['subdomain'] ?? ''));
    if ($sub === '' || !preg_match('/^[a-z0-9-]{2,30}$/', $sub)) {
        return ['ok' => false, 'msg' => 'Subdomain tidak valid.'];
    }
    $clonePath = CLONE_ROOT . '/' . $sub;
    $envPath   = "$clonePath/.env";
    if (!is_file($envPath)) {
        return ['ok' => false, 'msg' => "File .env tidak ada di $clonePath"];
    }

    // pastikan bisa ditulis
    shell_exec("sudo -n chown " . PHP_USER . ":" . PHP_USER . " " . escapeshellarg($envPath) . " 2>&1");

    $content = file_get_contents($envPath);
    $content = setEnv($content, 'USER_VERIFIED', '1');
    $content = setEnv($content, 'PRODUCT_MODE', 'CLIENT');
    $content = setEnv($content, 'APP_TIMEZONE', 'Asia/Jakarta');
    if (@file_put_contents($envPath, $content) === false) {
        return ['ok' => false, 'msg' => "Gagal menulis $envPath (permission)."];
    }

    $phpBin = trim((string)shell_exec('command -v php 2>/dev/null')) ?: '/usr/bin/php';
    $out = trim((string)shell_exec("cd $clonePath && $phpBin artisan config:clear 2>&1; $phpBin artisan optimize:clear 2>&1"));
    $bad = preg_match('/error|exception|failed/i', $out);

    return [
        'ok'  => !$bad,
        'msg' => ($bad ? "Peringatan: $out\n" : '') . "Env $sub diperbarui: USER_VERIFIED=1, PRODUCT_MODE=CLIENT. Cache dibersihkan.",
    ];
}

/**
 * Diagnose a store: file existence, readability, ownership, nginx conf, php-fpm user.
 */
function diagStore($p)
{
    $sub = strtolower(trim($p['subdomain'] ?? ''));
    if ($sub === '' || !preg_match('/^[a-z0-9-]{2,30}$/', $sub)) {
        return ['ok' => false, 'msg' => 'Subdomain tidak valid.'];
    }
    $clonePath = CLONE_ROOT . '/' . $sub;
    $index     = "$clonePath/public/index.php";
    $conf      = NGINX_DIR . "/salepro-$sub.conf";

    $lines = [];
    $lines[] = "BASE       : " . BASE;
    $lines[] = "BASE artisan: " . (is_file(BASE . '/artisan') ? 'YES' : 'NO');
    $lines[] = "BASE public/index.php: " . (is_file(BASE . '/public/index.php') ? 'YES' : 'NO');
    $lines[] = "clone path : $clonePath";
    $lines[] = "dir exists : " . (is_dir($clonePath) ? 'YES' : 'NO');
    $lines[] = "public dir : " . (is_dir("$clonePath/public") ? 'YES' : 'NO');
    $lines[] = "index.php  : " . (is_file($index) ? 'YES' : 'NO');
    $lines[] = "readable   : " . (is_readable($index) ? 'YES' : 'NO');
    $lines[] = "size       : " . (@filesize($index) ?: 'n/a') . " bytes";
    $lines[] = "owner/perm : " . trim((string)@shell_exec("ls -ld " . escapeshellarg($clonePath) . " 2>&1"));
    $lines[] = "index perm : " . trim((string)@shell_exec("ls -l " . escapeshellarg($index) . " 2>&1"));
    $lines[] = "nginx conf : " . (is_file($conf) ? 'YES' : 'NO');
    $lines[] = "php-fpm u  : " . trim((string)@shell_exec("ps -o user= -C php8.3-fpm 2>/dev/null | sort -u | tr '\\n' ' '"));
    $openBase = trim((string)@shell_exec("grep -rh open_basedir /etc/php/8.3/fpm/ 2>/dev/null | head -1"));
    $lines[] = "open_basedir: " . ($openBase !== '' ? $openBase : '(tidak diset)');
    $lines[] = "nginx test : " . trim((string)@shell_exec("sudo -n nginx -t 2>&1 | tail -1"));

    return ['ok' => true, 'msg' => "Diagnosa $sub:\n" . implode("\n", $lines)];
}

function listStores()
{
    $stores = [];
    $dirs = storeDirs();
    foreach ($dirs as $dir) {
        $sub = basename($dir);
        $name = trim((string)@file_get_contents("$dir/.salepro-store"));
        $stores[] = [
            'subdomain' => $sub,
            'name'      => $name !== '' ? $name : $sub,
            'db'        => 'sp_' . $sub,
            'url'       => 'https://' . $sub . '.' . ROOT_DOMAIN,
            'path'      => $dir,
        ];
    }
    return ['ok' => true, 'stores' => $stores, 'count' => count($stores), 'max' => MAX_STORES];
}

/** Return clone dirs that belong to this cloner (identified by .salepro-store marker). */
function storeDirs()
{
    $out = [];
    foreach (glob(CLONE_ROOT . '/*/.salepro-store') ?: [] as $marker) {
        $out[] = dirname($marker);
    }
    sort($out);
    return $out;
}

function createStore($p)
{
    $name      = trim($p['store_name'] ?? '');
    $subdomain = strtolower(trim($p['subdomain'] ?? ''));
    $admin     = trim($p['admin_email'] ?? '');
    $apass     = $p['admin_pass'] ?? '';

    if ($name === '' || $subdomain === '') {
        return ['ok' => false, 'msg' => 'Nama toko dan subdomain wajib diisi.'];
    }
    if (!preg_match('/^[a-z0-9-]{2,30}$/', $subdomain)) {
        return ['ok' => false, 'msg' => 'Subdomain: huruf kecil, angka, strip (-). 2-30 karakter.'];
    }

    $fullUrl   = $subdomain . '.' . ROOT_DOMAIN;
    $db        = 'sp_' . $subdomain;
    $dbuser    = $db;
    $dbPass    = bin2hex(random_bytes(12)); // password DB selalu kuat & terpisah dari login admin
    $clonePath = CLONE_ROOT . '/' . $subdomain;
    $log = [];

    if (is_dir($clonePath)) {
        $isRealStore = is_file("$clonePath/public/index.php") && is_file("$clonePath/.salepro-store");
        if ($isRealStore) {
            return ['ok' => false, 'msg' => "Store $subdomain sudah ada. Hapus dulu via Remove."];
        }
        // Sisa folder rusak/parsial -> bersihkan dulu
        shell_exec("sudo -n rm -rf " . escapeshellarg($clonePath) . " 2>&1");
    }

    // Check max stores limit (only dirs with our marker count)
    $existing = count(storeDirs());
    if ($existing >= MAX_STORES) {
        return ['ok' => false, 'msg' => "Limit server tercapai: $existing dari " . MAX_STORES . " toko. Hapus toko lama dulu."];
    }

    try {
        // 1. Copy source code
        if (!is_dir(BASE) || !is_file(BASE . '/artisan')) {
            return ['ok' => false, 'msg' => "Source SalePro tidak valid di " . BASE . " (file artisan tidak ditemukan). BASE harus menunjuk folder SalePro yang sudah lengkap, bukan folder cloner."];
        }
        if (!is_file(BASE . '/public/index.php')) {
            return ['ok' => false, 'msg' => "File " . BASE . "/public/index.php tidak ada. Upload code SalePro lengkap (termasuk folder public/) ke BASE."];
        }

        // 1b. Verify DB connection BEFORE doing anything
        $testConn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, null, (int)DB_PORT);
        if (!$testConn) {
            return ['ok' => false, 'msg' => 'Gagal koneksi MySQL (' . DB_USER . '@' . DB_HOST . ':' . DB_PORT . '): ' . mysqli_connect_error()];
        }
        mysqli_close($testConn);
        logMsg($log, "Koneksi MySQL OK (" . DB_USER . "@" . DB_HOST . ")");

        logMsg($log, "Menyalin kode SalePro ke $clonePath ...");
        // /var/www milik root -> buat folder clone via sudo, lalu beri ke user PHP-FPM
        shell_exec("sudo -n mkdir -p " . escapeshellarg($clonePath) . " 2>&1");
        shell_exec("sudo -n chown " . PHP_USER . ":" . PHP_USER . " " . escapeshellarg($clonePath) . " 2>&1; sudo -n chmod 775 " . escapeshellarg($clonePath) . " 2>&1");
        $copyOut = trim((string)shell_exec(
            "rsync -rlD --exclude=.git --exclude=dekatposgit --exclude=salepro-cloner --exclude=.env " .
            escapeshellarg(rtrim(BASE, '/') . '/') . " " . escapeshellarg(rtrim($clonePath, '/') . '/') . " 2>&1"
        ));
        if ($copyOut !== '') logMsg($log, "rsync: " . $copyOut);
        $phpBin = trim((string)shell_exec('command -v php 2>/dev/null')) ?: '/usr/bin/php';

        // Pastikan folder writable Laravel ada
        foreach (['storage/app/public','storage/framework/cache','storage/framework/sessions','storage/framework/views','storage/logs','bootstrap/cache'] as $d) {
            @mkdir("$clonePath/$d", 0775, true);
        }

        // Verifikasi hasil copy: public/index.php harus ada
        if (!is_file("$clonePath/public/index.php")) {
            return ['ok' => false, 'msg' => "Copy gagal: $clonePath/public/index.php tidak ada. Cek isi folder BASE/public."];
        }
        logMsg($log, "Verifikasi OK: public/index.php ada");
        // Marker agar folder ini dikenali sebagai toko SalePro (dipakai list & limit)
        @file_put_contents("$clonePath/.salepro-store", $name);

        // Permission: pastikan semua file bisa dibaca PHP-FPM (a+rX), write utk storage/bootstrap
        shell_exec("chmod -R a+rX $clonePath 2>&1; chmod -R 775 $clonePath/storage $clonePath/bootstrap 2>&1");

        // 2. Create DB + user
        logMsg($log, "Membuat database $db + user $dbuser ...");
        dbExec(null, "CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        dbExec(null, "CREATE USER IF NOT EXISTS '$dbuser'@'localhost' IDENTIFIED BY '$dbPass'");
        dbExec(null, "ALTER USER '$dbuser'@'localhost' IDENTIFIED BY '$dbPass'");
        dbExec(null, "CREATE USER IF NOT EXISTS '$dbuser'@'127.0.0.1' IDENTIFIED BY '$dbPass'");
        dbExec(null, "ALTER USER '$dbuser'@'127.0.0.1' IDENTIFIED BY '$dbPass'");
        dbExec(null, "GRANT ALL PRIVILEGES ON `$db`.* TO '$dbuser'@'localhost'");
        dbExec(null, "GRANT ALL PRIVILEGES ON `$db`.* TO '$dbuser'@'127.0.0.1'");
        dbExec(null, "FLUSH PRIVILEGES");

        // 3. Seed database from reference SQL (Database.sql full dump)
        // This includes: all tables, roles, general_settings, external_services, admin user
        $refSql = BASE . '/Database.sql';
        if (is_file($refSql)) {
            logMsg($log, "Import Database.sql ke $db ...");
            $sqlData = file_get_contents($refSql);
            // Strip the outer transaction wrapper so multi_query works cleanly
            $sqlData = preg_replace('/^START TRANSACTION;|\bCOMMIT;\s*$/m', '', $sqlData);
            dbExec($db, $sqlData);
            logMsg($log, "Database.sql terimport");
        } else {
            logMsg($log, "Database.sql tidak ditemukan di $refSql, fallback ke migrate+seed ...");
            $migrateOut = shell_exec("cd $clonePath && php artisan migrate --force 2>&1");
            shell_exec("cd $clonePath && php artisan db:seed --force 2>&1");
            if (preg_match('/ERROR|Exception|FAILED/i', $migrateOut)) {
                shell_exec("sudo -n rm -rf " . escapeshellarg($clonePath) . " 2>&1");
                return ['ok' => false, 'msg' => 'Migrasi gagal. LOG: ' . trim($migrateOut), 'log' => $log];
            }
        }

        // 4. Write .env (pakai .env.example BASE sebagai template agar semua key lengkap)
        logMsg($log, "Menulis .env ...");
        $envPath = "$clonePath/.env";
        if (is_file($envPath)) {
            $envContent = file_get_contents($envPath);
        } elseif (is_file(BASE . '/.env.example')) {
            $envContent = file_get_contents(BASE . '/.env.example');
        } else {
            $envContent = '';
        }
        // Force all critical keys. DB_SOCKET must be cleared (sisa dev MAMP bikin koneksi gagal)
        $envMap = [
            'APP_NAME'       => $name,
            'APP_ENV'        => 'production',
            'APP_DEBUG'      => 'false',
            'APP_URL'        => "https://$fullUrl",
            'APP_TIMEZONE'   => 'Asia/Jakarta',
            'DB_CONNECTION'  => 'mysql',
            'DB_HOST'        => DB_HOST,
            'DB_PORT'        => DB_PORT,
            'DB_DATABASE'    => $db,
            'DB_USERNAME'    => $dbuser,
            'DB_PASSWORD'    => $dbPass,
            'DB_SOCKET'      => '',
            'CENTRAL_DOMAIN' => ROOT_DOMAIN,
            'CACHE_DRIVER'   => 'file',
            'SESSION_DRIVER' => 'file',
            'QUEUE_CONNECTION' => 'sync',
            // SalePro: 1 = install terverifikasi (tanpa pembatasan demo)
            'PRODUCT_MODE'     => 'CLIENT',
            'USER_VERIFIED'    => '1',
            'APP_TIMEZONE'     => 'Asia/Jakarta',
        ];
        foreach ($envMap as $k => $v) {
            $envContent = setEnv($envContent, $k, $v);
        }
        if (!is_file($envPath) || strpos($envContent, 'APP_KEY=base64:') === false) {
            $envContent = setEnv($envContent, 'APP_KEY', 'base64:' . base64_encode(random_bytes(32)));
        }
        file_put_contents($envPath, $envContent);
        logMsg($log, ".env ditulis (DB=$db, socket cleared)");

        // 5. Permissions + clear any cached config inherited from BASE
        shell_exec("chmod -R 775 $clonePath/storage $clonePath/bootstrap 2>&1");
        $clearOut = shell_exec("cd $clonePath && $phpBin artisan optimize:clear 2>&1");
        shell_exec("cd $clonePath && $phpBin artisan storage:link 2>&1");
        if (preg_match('/error|exception|failed/i', (string)$clearOut)) {
            logMsg($log, "Peringatan optimize:clear: " . trim((string)$clearOut));
        } else {
            logMsg($log, "Cache/config lama dibersihkan");
        }

        // 6. Create admin user (temp PHP script, pakai model + kolom yang benar)
        if ($admin !== '') {
            logMsg($log, "Membuat admin $admin ...");
            $adminEmail = filter_var($admin, FILTER_SANITIZE_EMAIL);
            $adminPass  = $apass !== '' ? $apass : 'Admin@12345';
            $script = $clonePath . '/_create_admin.php';
            $phpCode = '<?php
require __DIR__ . "/vendor/autoload.php";
$app = require_once __DIR__ . "/bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
try {
    $email = ' . var_export($adminEmail, true) . ';
    $pass  = ' . var_export($adminPass, true) . ';
    // Pakai user default dari Database.sql (id=1) agar tidak melanggar kolom NOT NULL
    $u = App\Models\User::find(1) ?: App\Models\User::orderBy("id")->first();
    if ($u) {
        $u->name     = "Admin";
        $u->email    = $email;
        $u->password = password_hash($pass, PASSWORD_BCRYPT);
        $u->is_active = 1;
        $u->role_id   = 1;
        $u->save();
        echo "updated:" . $u->id;
    } else {
        $u = App\Models\User::create([
            "name" => "Admin",
            "email" => $email,
            "password" => password_hash($pass, PASSWORD_BCRYPT),
            "phone" => "",
            "company_name" => "",
            "is_active" => 1,
            "is_deleted" => 0,
            "role_id" => 1,
        ]);
        echo "created:" . $u->id;
    }
} catch (\Throwable $e) {
    echo "ADMIN_ERROR: " . $e->getMessage();
}
';
            file_put_contents($script, $phpCode);
            $out = trim((string)shell_exec("cd $clonePath && $phpBin _create_admin.php 2>&1; rm -f _create_admin.php"));
            if (preg_match('/^(created|updated):\d+/', $out, $m)) {
                logMsg($log, "Admin OK (" . $m[0] . "), password: $adminPass");
            } else {
                logMsg($log, "Admin gagal dibuat: " . ($out === '' ? '(output kosong - cek .env/permission)' : $out));
            }
        }

        // 7. Nginx config (port 80 only - valid tanpa SSL, certbot nanti tambah 443)
        logMsg($log, 'Menulis Nginx config ...');
        $stagingFile = sys_get_temp_dir() . "/salepro-nginx-$subdomain.conf";
        $written = @file_put_contents($stagingFile, nginxConfig($subdomain, $clonePath));
        if ($written === false) {
            logMsg($log, "GAGAL tulis staging file $stagingFile (cek permission /tmp)");
        } else {
            logMsg($log, "Staging file ditulis: $stagingFile");
        }
        $sudoOut = trim((string)shell_exec(
            "sudo -n cp $stagingFile " . NGINX_DIR . "/salepro-$subdomain.conf 2>&1; " .
            "sudo -n nginx -t 2>&1; sudo -n systemctl reload nginx 2>&1; " .
            "sudo -n rm -f $stagingFile 2>&1"
        ));
        if (strpos($sudoOut, 'test is successful') !== false) {
            logMsg($log, "Nginx config aktif & reload OK");
        } else {
            logMsg($log, "PERINGATAN nginx: " . $sudoOut);
        }

        // 8. SSL - fire-and-forget background (jangan blocking PHP)
        logMsg($log, 'Mendaftarkan SSL di background ...');
        $sslLog = sys_get_temp_dir() . "/salepro-ssl-$subdomain.log";
        shell_exec("nohup sudo -n certbot --nginx -d $fullUrl -d www.$fullUrl --non-interactive --agree-tos -m admin@" . ROOT_DOMAIN . " --redirect > $sslLog 2>&1 &");

        $steps = [
            "URL      : https://$fullUrl  (http://$fullUrl juga bisa sebelum SSL ready)",
            "Admin    : " . ($admin !== '' ? "$admin / " . ($apass !== '' ? $apass : 'Admin@12345') : '(default: admin / demo@mail.com)'),
            "DB       : $db",
            "DB user  : $dbuser / $dbPass",
            "Kode     : $clonePath",
            "SSL log  : tail -f $sslLog",
        ];
        return ['ok' => true, 'msg' => "Store \"$name\" ($subdomain) berhasil dibuat!", 'steps' => $steps, 'log' => $log, 'url' => "https://$fullUrl", 'subdomain' => $subdomain];

    } catch (Exception $e) {
        logMsg($log, 'ERROR: ' . $e->getMessage());
        return ['ok' => false, 'msg' => 'Gagal: ' . $e->getMessage(), 'log' => $log];
    }
}

function removeStore($p)
{
    $sub = strtolower(trim($p['subdomain'] ?? ''));
    if ($sub === '') return ['ok' => false, 'msg' => 'Subdomain kosong'];
    $clonePath = CLONE_ROOT . '/' . $sub;
    $db = 'sp_' . $sub;

    dbExec(null, "DROP DATABASE IF EXISTS `$db`");
    dbExec(null, "DROP USER IF EXISTS '$db'@'localhost'");
    dbExec(null, "DROP USER IF EXISTS '$db'@'127.0.0.1'");
    dbExec(null, "FLUSH PRIVILEGES");

    if (is_dir($clonePath)) {
        shell_exec("sudo -n rm -rf " . escapeshellarg($clonePath) . " 2>&1");
    }

    @unlink(NGINX_DIR . "/salepro-$sub.conf");
    shell_exec("sudo -n rm -f " . NGINX_DIR . "/salepro-$sub.conf 2>/dev/null; sudo -n nginx -t >/dev/null 2>&1 && sudo -n systemctl reload nginx 2>/dev/null");

    return ['ok' => true, 'msg' => "Store $sub dihapus (DB + user + kode + nginx)."];
}

// ---- helpers ----
function logMsg(&$log, $m) { $log[] = $m; }

function replaceEnv($content, $key, $val)
{
    $pattern = '/^(' . preg_quote($key, '/') . '=).*/m';
    if (preg_match($pattern, $content)) return preg_replace($pattern, "$1$val", $content);
    return $content . "\n$key=$val\n";
}

/**
 * Set or add an env key. Values with spaces/special chars are quoted so dotenv parses correctly.
 */
function setEnv($content, $key, $val)
{
    $pattern = '/^\s*' . preg_quote($key, '/') . '\s*=.*$/m';
    $content = preg_replace($pattern, '', $content);
    $content = preg_replace("/\n{3,}/", "\n\n", $content);
    $content = rtrim($content) . "\n$key=" . envValue($val) . "\n";
    return $content;
}

/** Format an env value: quote if it has spaces or shell/dotenv-sensitive chars. */
function envValue($v)
{
    $v = (string)$v;
    if ($v === '') return '';
    if (preg_match('/^[A-Za-z0-9_.:\/@+\-]*$/', $v)) return $v;
    return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $v) . '"';
}

function dbExec($db, $sql)
{
    // Connect with explicit port; $db may be null for server-level statements
    $c = mysqli_connect(DB_HOST, DB_USER, DB_PASS, $db ?: null, (int)DB_PORT, null);
    if (!$c) throw new Exception('MySQL connect: ' . mysqli_connect_error());
    if (!mysqli_multi_query($c, $sql)) throw new Exception('SQL: ' . mysqli_error($c));
    while (mysqli_next_result($c)) {}
    mysqli_close($c);
}

function copyDir($src, $dest)
{
    if (!is_dir($dest)) mkdir($dest, 0755, true);
    foreach (scandir($src) as $item) {
        if ($item === '.' || $item === '..' || $item === '.git') continue;
        $s = "$src/$item"; $d = "$dest/$item";
        if (is_dir($s)) copyDir($s, $d); else copy($s, $d);
    }
}

/** Copy only files missing at destination (never overwrites existing, keeps .env). */
function copyMissing($src, $dest)
{
    if (!is_dir($dest)) mkdir($dest, 0755, true);
    foreach (scandir($src) as $item) {
        if ($item === '.' || $item === '..' || $item === '.git') continue;
        $s = "$src/$item"; $d = "$dest/$item";
        if (is_dir($s)) copyMissing($s, $d);
        elseif (!file_exists($d)) copy($s, $d);
    }
}

function nginxConfig($sub, $path)
{
    return "# SalePro store: $sub
# Port 80 - selalu valid (tanpa ref SSL), certbot nambah 443 otomatis
server {
    listen 80;
    server_name $sub." . ROOT_DOMAIN . " www.$sub." . ROOT_DOMAIN . ";

    root $path/public;
    index index.php;
    client_max_body_size 64M;

    location / { try_files \$uri \$uri/ /index.php?\$query_string; }

    location = /index.php {
        fastcgi_pass " . PHP_FPM . ";
        include /etc/nginx/fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        fastcgi_param QUERY_STRING \$query_string;
        fastcgi_param REQUEST_METHOD \$request_method;
        fastcgi_param SCRIPT_NAME /index.php;
    }

    location ~ \.php$ { return 404; }
    location ~ /\.(?!well-known).* { deny all; }
}
";
}

if ($isAjax && $result !== null) {
    header('Content-Type: application/json');
    echo json_encode($result);
    exit;
}

// Selalu sertakan daftar toko agar tabel ter-render saat halaman dibuka (GET)
// maupun setelah aksi non-AJAX.
$storeList = listStores();
if ($result === null) {
    $result = $storeList;
} else {
    $result['stores'] = $storeList['stores'];
    $result['count']  = $storeList['count'];
    $result['max']    = $storeList['max'];
}

include __DIR__ . '/view.php';
