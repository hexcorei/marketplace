<?php
/**
 * Bootstrap aplikasi: dimuat oleh setiap halaman PHP.
 * Urutan: konfigurasi -> error handling -> HTTPS -> header keamanan -> sesi
 *         -> perlindungan POST (same-origin + CSRF).
 */
declare(strict_types=1);

// Berkas ini hanya boleh di-include, tidak boleh diminta langsung lewat URL
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath((string) $_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    http_response_code(403);
    exit;
}

define('APP_BOOT', true);
define('BASE_DIR', dirname(__DIR__));

// ---------- Konfigurasi ----------
$configFile = BASE_DIR . '/config/config.php';
if (!is_file($configFile)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Konfigurasi belum dibuat. Salin config/config.sample.php menjadi config/config.php lalu isi kredensial database.');
}
$GLOBALS['__config'] = require $configFile;

require_once BASE_DIR . '/includes/helpers.php';

// ---------- Error handling: tidak membocorkan detail ke pengunjung ----------
error_reporting(E_ALL);
ini_set('display_errors', config('app', 'debug', false) ? '1' : '0');
ini_set('log_errors', '1');
$logDir = BASE_DIR . '/storage/logs';
if (is_dir($logDir) && is_writable($logDir)) {
    ini_set('error_log', $logDir . '/php-error.log');
}
set_exception_handler('handle_exception');

date_default_timezone_set((string) config('app', 'timezone', 'Asia/Jakarta'));
mb_internal_encoding('UTF-8');

require_once BASE_DIR . '/includes/database.php';
require_once BASE_DIR . '/includes/security.php';
require_once BASE_DIR . '/includes/auth.php';
require_once BASE_DIR . '/includes/layout.php';

// ---------- Paksa HTTPS (aktifkan lewat config setelah SSL siap) ----------
if (config('app', 'force_https', false) && !is_https()) {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if (preg_match('/^[A-Za-z0-9.\-]+(:\d{1,5})?$/', $host) === 1) {
        header('Location: https://' . $host . ($_SERVER['REQUEST_URI'] ?? '/'), true, 301);
        exit;
    }
    http_response_code(400);
    exit('Permintaan tidak valid.');
}

send_security_headers();
start_secure_session();

// ---------- Semua request POST wajib lolos same-origin + token CSRF ----------
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    enforce_post_protections();
}
