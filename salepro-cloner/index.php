<?php
// ============================================================
// SalePro Multi-Store Cloner - Backend Logic
// Deploy to VPS: /var/www/salepro-cloner/
// ============================================================

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// ---------- CONFIG (edit sesuai environment VPS) ----------
define('BASE',        '/var/www/salepro');   // source SalePro code
define('CLONE_ROOT',  '/var/www');           // clones live: /var/www/<subdomain>
define('ROOT_DOMAIN', 'dekatpos.com');
define('DB_HOST',     '127.0.0.1');
define('DB_PORT',     '3306');
define('DB_USER',     'root');
define('DB_PASS',     '');
// Reference DB to clone from (optional). Empty = run migrate+seed only.
define('REF_DB',      '');
define('NGINX_DIR',   '/etc/nginx/conf.d');
// PHP-FPM sock for nginx - adjust to your VPS (php8.1-fpm, php8.0-fpm, etc)
define('PHP_FPM',     'unix:/run/php/php8.3-fpm.sock');
// ------------------------------------------------------------

$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $result = handleAction($_POST);
}

function handleAction($p)
{
    global $result;
    switch ($p['action'] ?? '') {
        case 'create': $result = createStore($p); break;
        case 'remove': $result = removeStore($p); break;
        case 'list':   $result = listStores(); break;
        default:       $result = ['ok' => false, 'msg' => 'unknown action'];
    }
    return $result;
}

function listStores()
{
    $stores = [];
    foreach (glob(CLONE_ROOT . '/*/public/index.php', GLOB_BRACE) ?: [] as $f) {
        $sub = basename(dirname(dirname($f)));
        if (in_array($sub, ['salepro', 'salepro-cloner'])) continue;
        $stores[] = [
            'subdomain' => $sub,
            'db'        => 'sp_' . $sub,
            'url'       => 'https://' . $sub . '.' . ROOT_DOMAIN,
            'path'      => dirname(dirname($f)),
        ];
    }
    return ['ok' => true, 'stores' => $stores];
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
    $clonePath = CLONE_ROOT . '/' . $subdomain;
    $log = [];

    if (is_dir("$clonePath/public")) {
        return ['ok' => false, 'msg' => "Store $subdomain sudah ada. Hapus dulu via Remove."];
    }

    try {
        // 1. Copy source code
        if (!is_dir(BASE)) {
            return ['ok' => false, 'msg' => "Source SalePro tidak ada di " . BASE . '. Edit config di atas.'];
        }
        logMsg($log, "Menyalin kode SalePro ke $clonePath ...");
        copyDir(BASE, $clonePath);

        // 2. Create DB + user
        logMsg($log, "Membuat database $db + user $dbuser ...");
        dbExec(null, "CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        dbExec(null, "CREATE USER IF NOT EXISTS '$dbuser'@'localhost' IDENTIFIED BY '$apass'");
        dbExec(null, "CREATE USER IF NOT EXISTS '$dbuser'@'127.0.0.1' IDENTIFIED BY '$apass'");
        dbExec(null, "GRANT ALL PRIVILEGES ON `$db`.* TO '$dbuser'@'localhost'");
        dbExec(null, "GRANT ALL PRIVILEGES ON `$db`.* TO '$dbuser'@'127.0.0.1'");
        dbExec(null, "FLUSH PRIVILEGES");

        // 3. Clone schema or migrate+seed
        if (REF_DB !== '') {
            logMsg($log, "Menyalin struktur & data dari $REF_DB ...");
            $dump = shell_exec('mysqldump --single-transaction --no-data --skip-dump-date ' . REF_DB . ' 2>/dev/null');
            dbExec($db, $dump);
            shell_exec("cd $clonePath && php artisan migrate --force 2>&1");
        } else {
            logMsg($log, 'Menjalankan migrate + seed ...');
            shell_exec("cd $clonePath && php artisan migrate --force 2>&1");
            shell_exec("cd $clonePath && php artisan db:seed --force 2>&1");
        }

        // 4. Write .env
        logMsg($log, "Menulis .env ...");
        $envPath = "$clonePath/.env";
        if (is_file($envPath)) {
            $content = replaceEnv(file_get_contents($envPath), 'APP_NAME', $name);
            $content = replaceEnv($content, 'APP_URL', "https://$fullUrl");
            $content = replaceEnv($content, 'APP_ENV', 'production');
            $content = replaceEnv($content, 'APP_DEBUG', 'false');
            $content = replaceEnv($content, 'DB_DATABASE', $db);
            $content = replaceEnv($content, 'DB_USERNAME', $dbuser);
            $content = replaceEnv($content, 'DB_PASSWORD', $apass);
            $content = replaceEnv($content, 'DB_HOST', DB_HOST);
            $content = replaceEnv($content, 'DB_PORT', DB_PORT);
            $content = replaceEnv($content, 'CENTRAL_DOMAIN', ROOT_DOMAIN);
            file_put_contents($envPath, $content);
        } else {
            $key = shell_exec('openssl rand -base64 32');
            file_put_contents($envPath, "APP_NAME=$name\nAPP_ENV=production\nAPP_KEY=base64:$key\nAPP_DEBUG=false\nAPP_URL=https://$fullUrl\nDB_CONNECTION=mysql\nDB_HOST=" . DB_HOST . "\nDB_PORT=" . DB_PORT . "\nDB_DATABASE=$db\nDB_USERNAME=$dbuser\nDB_PASSWORD=$apass\nCENTRAL_DOMAIN=" . ROOT_DOMAIN . "\nCACHE_DRIVER=file\nSESSION_DRIVER=file\nQUEUE_CONNECTION=sync\n");
        }

        // 5. Generate key, permissions
        shell_exec("cd $clonePath && php artisan key:generate --force 2>/dev/null");
        shell_exec("chmod -R 775 $clonePath/storage $clonePath/bootstrap 2>/dev/null");

        // 6. Create admin user
        if ($admin !== '') {
            logMsg($log, "Membuat admin $admin ...");
            $esc = str_replace("'", '', $admin);
            $esc = str_replace("'", '', $apass);
            shell_exec("cd $clonePath && php artisan tinker --execute=\"echo \\\\App\\\\User::create(['name'=>'Admin','email'=>'$esc','password'=>bcrypt('$esc'),'active'=>1])->id;\" 2>/dev/null");
        }

        // 7. Nginx config
        logMsg($log, 'Menulis Nginx config ...');
        file_put_contents(NGINX_DIR . "/salepro-$subdomain.conf", nginxConfig($subdomain, $clonePath));
        shell_exec('nginx -t >/dev/null 2>&1 && systemctl reload nginx 2>/dev/null');

        // 8. SSL (Let's Encrypt) - non-blocking
        logMsg($log, 'Mendaftarkan SSL ...');
        shell_exec("certbot --nginx -d $fullUrl -d www.$fullUrl --non-interactive --agree-tos -m admin@" . ROOT_DOMAIN . ' --redirect >/dev/null 2>&1 &');

        $steps = [
            "URL    : https://$fullUrl",
            "Admin  : $admin / $apass",
            "DB     : $db (user $dbuser)",
            "Kode   : $clonePath",
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
        $rii = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($clonePath, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($rii as $file) { $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname()); }
        @rmdir($clonePath);
    }

    @unlink(NGINX_DIR . "/salepro-$sub.conf");
    shell_exec('systemctl reload nginx 2>/dev/null');

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

function dbExec($db, $sql)
{
    $c = mysqli_connect(DB_HOST, DB_USER, DB_PASS, $db);
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

function nginxConfig($sub, $path)
{
    return "# SalePro store: $sub
server {
    listen 80;
    server_name $sub." . ROOT_DOMAIN . " www.$sub." . ROOT_DOMAIN . ";
    return 301 https://\$host\$request_uri;
}
server {
    listen 443 ssl http2;
    server_name $sub." . ROOT_DOMAIN . " www.$sub." . ROOT_DOMAIN . ";

    ssl_certificate     /etc/letsencrypt/live/$sub." . ROOT_DOMAIN . "/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/$sub." . ROOT_DOMAIN . "/privkey.pem;

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

include __DIR__ . '/view.php';
