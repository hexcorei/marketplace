<?php
declare(strict_types=1);
if (!defined('APP_BOOT')) { http_response_code(403); exit; }

/** Ambil nilai konfigurasi: config('db','host') atau config('db') untuk satu bagian. */
function config(string $section, ?string $key = null, mixed $default = null): mixed
{
    $cfg = $GLOBALS['__config'][$section] ?? [];
    if ($key === null) {
        return $cfg;
    }
    return $cfg[$key] ?? $default;
}

/** Escape output HTML (pencegahan XSS). Gunakan untuk SEMUA data dinamis di template. */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ---------- URL & redirect ----------

function base_path(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    $cfg = config('app', 'base_path', null);
    if (is_string($cfg)) {
        return $base = rtrim($cfg, '/');
    }
    $base = '';
    $doc  = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
    $root = realpath(BASE_DIR);
    if ($doc !== false && $root !== false) {
        $doc  = rtrim(str_replace('\\', '/', $doc), '/');
        $root = str_replace('\\', '/', $root);
        if ($root !== $doc && str_starts_with($root, $doc . '/')) {
            $base = substr($root, strlen($doc));
        }
    }
    return $base;
}

function url(string $path = ''): string
{
    return base_path() . '/' . ltrim($path, '/');
}

/** Redirect HANYA ke path internal aplikasi (mencegah open redirect). */
function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

// ---------- Input ----------

/** Bersihkan string dari karakter kontrol & spasi tepi. Newline/tab dipertahankan. */
function clean_str(mixed $value): string
{
    if (!is_string($value)) {
        return '';
    }
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);
    return $value === null ? '' : trim($value);
}

function get_str(string $key): string  { return clean_str($_GET[$key]  ?? ''); }
function post_str(string $key): string { return clean_str($_POST[$key] ?? ''); }

/** Validasi bilangan bulat dalam rentang; null bila tidak valid. */
function int_or_null(mixed $value, int $min = 1, int $max = PHP_INT_MAX): ?int
{
    if (is_string($value)) {
        $value = trim($value);
        if (preg_match('/^-?\d{1,18}$/', $value) !== 1) {
            return null;
        }
        $value = (int) $value;
    }
    if (!is_int($value)) {
        return null;
    }
    return ($value >= $min && $value <= $max) ? $value : null;
}

function get_int(string $key, int $min = 1, int $max = PHP_INT_MAX): ?int
{
    return int_or_null($_GET[$key] ?? null, $min, $max);
}

function post_int(string $key, int $min = 1, int $max = PHP_INT_MAX): ?int
{
    return int_or_null($_POST[$key] ?? null, $min, $max);
}

// ---------- Format ----------

function rupiah(float|int|string $amount): string
{
    return 'Rp ' . number_format((float) $amount, 0, ',', '.');
}

function tanggal(string $datetime): string
{
    $ts = strtotime($datetime);
    return $ts === false ? '-' : date('d/m/Y H:i', $ts);
}

const ORDER_STATUS = [
    'menunggu_pembayaran' => ['Menunggu Pembayaran', 'warning'],
    'diproses'            => ['Diproses',            'info'],
    'dikirim'             => ['Dikirim',             'primary'],
    'selesai'             => ['Selesai',             'success'],
    'dibatalkan'          => ['Dibatalkan',          'secondary'],
];

function status_badge(string $status): string
{
    [$label, $color] = ORDER_STATUS[$status] ?? [$status, 'secondary'];
    return '<span class="badge text-bg-' . e($color) . '">' . e($label) . '</span>';
}

/** URL gambar produk; nama file divalidasi ketat, selain itu pakai placeholder. */
function product_image_url(?string $file): string
{
    if ($file !== null && preg_match('/^[a-f0-9]{32}\.jpg$/', $file) === 1) {
        return url('uploads/products/' . $file);
    }
    return url('assets/img/placeholder.svg');
}

// ---------- Flash message ----------

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $message];
}

function take_flashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return is_array($flashes) ? $flashes : [];
}

// ---------- Penanganan error global ----------

function handle_exception(Throwable $ex): void
{
    error_log(sprintf('[%s] %s in %s:%d', get_class($ex), $ex->getMessage(), $ex->getFile(), $ex->getLine()));
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    echo '<!doctype html><html lang="id"><head><meta charset="utf-8"><title>Terjadi kesalahan</title></head><body>';
    echo '<h1>Terjadi kesalahan pada server</h1><p>Silakan coba lagi beberapa saat lagi.</p>';
    if (config('app', 'debug', false)) {
        echo '<pre>' . e($ex->getMessage()) . '</pre>';
    }
    echo '</body></html>';
}
