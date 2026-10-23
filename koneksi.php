<?php
ob_start();

// === Deteksi HTTPS (untuk cookie Secure & HSTS) ===
$__https = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
    || (($_SERVER['SERVER_PORT'] ?? null) == 443)
    || (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');

// === Hardening session (wajib sebelum session_start) ===
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_trans_sid', '0');

    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $__https,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    } else {
        session_set_cookie_params(0, '/', '', $__https, true);
    }

    session_name('ESURAT_SESSID');
    session_start();
}

// === Batas idle 2 jam + rotasi ID session tiap 30 menit ===
if (!empty($_SESSION['login'])) {
    $__now  = time();
    $__idle = 7200;

    if (!empty($_SESSION['last_activity']) && ($__now - (int)$_SESSION['last_activity'] > $__idle)) {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $__p = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $__p['path'] ?? '/',
                $__p['domain'] ?? '',
                (bool)($__p['secure'] ?? false),
                (bool)($__p['httponly'] ?? true)
            );
        }
        session_destroy();
        session_start();
        session_regenerate_id(true);
    } else {
        $_SESSION['last_activity'] = $__now;
        if (empty($_SESSION['last_regen']) || ($__now - (int)$_SESSION['last_regen'] > 1800)) {
            session_regenerate_id(true);
            $_SESSION['last_regen'] = $__now;
        }
    }
}

// === Header keamanan global ===
// Catatan: X-Content-Type-Options, X-Frame-Options, Referrer-Policy, dan
// Permissions-Policy sudah dipasang untuk SEMUA respons lewat .htaccess.
// Di sini hanya header yang tidak ada di .htaccess (CSP & HSTS).
if (!headers_sent()) {
    header(
        "Content-Security-Policy: default-src 'self'; base-uri 'self'; object-src 'none'; "
        . "frame-ancestors 'self'; form-action 'self'; img-src 'self' data:; "
        . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com https://cdn.jsdelivr.net; "
        . "font-src 'self' data: https://fonts.gstatic.com https://cdnjs.cloudflare.com https://cdn.jsdelivr.net; "
        . "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://code.jquery.com; "
        . "connect-src 'self';"
    );
    if ($__https) {
        header('Strict-Transport-Security: max-age=31536000');
    }
}

// Sembunyikan error di server produksi; tetap tampil saat localhost untuk debugging
$__is_local = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
if (!$__is_local) {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}

$host = "localhost";
$user = "root";
$pass = "";
$db = "db_layanan_surat";

// Disable exceptions to use procedural error handling (die)
mysqli_report(MYSQLI_REPORT_OFF);

$koneksi = mysqli_connect($host, $user, $pass, $db);
if (!$koneksi) {
    die("Koneksi gagal: " . mysqli_connect_error());
}
mysqli_set_charset($koneksi, "utf8mb4");

// === Helper keamanan global (tidak mengubah tampilan) ===
if (!function_exists('csrf_token')) {
    function csrf_token() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}
if (!function_exists('csrf_verify')) {
    function csrf_verify($token) {
        return isset($_SESSION['csrf_token']) && is_string($token) && hash_equals($_SESSION['csrf_token'], $token);
    }
}
if (!function_exists('csrf_field')) {
    function csrf_field() {
        return '<input type="hidden" name="csrf" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
    }
}
if (!function_exists('esc')) {
    function esc($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}
?>
