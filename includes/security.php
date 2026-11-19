<?php
/**
 * Lapisan keamanan: header HTTP, sesi, CSRF, same-origin, throttle brute-force,
 * dan penanganan unggahan gambar yang aman.
 */
declare(strict_types=1);
if (!defined('APP_BOOT')) { http_response_code(403); exit; }

function is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    return strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

/** Hanya REMOTE_ADDR yang dipercaya (X-Forwarded-For mudah dipalsukan). */
function client_ip(): string
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    return filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : '0.0.0.0';
}

// ---------------------------------------------------------------------
// Header keamanan HTTP
// ---------------------------------------------------------------------
function send_security_headers(): void
{
    if (headers_sent()) {
        return;
    }
    header_remove('X-Powered-By');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()');
    header('Cross-Origin-Opener-Policy: same-origin');
    header('Cross-Origin-Resource-Policy: same-origin');
    // Halaman dinamis & berisi data akun tidak boleh disimpan di cache
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');

    $csp = [
        "default-src 'self'",
        "script-src 'self' https://cdn.jsdelivr.net",
        "style-src 'self' https://cdn.jsdelivr.net",
        "img-src 'self' data:",
        "font-src 'self' https://cdn.jsdelivr.net",
        "connect-src 'self'",
        "object-src 'none'",
        "base-uri 'self'",
        "form-action 'self'",
        "frame-ancestors 'none'",
    ];
    if (is_https()) {
        $csp[] = 'upgrade-insecure-requests';
        header('Strict-Transport-Security: max-age=31536000');
    }
    header('Content-Security-Policy: ' . implode('; ', $csp));
}

// ---------------------------------------------------------------------
// Sesi aman
// ---------------------------------------------------------------------
function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    ini_set('session.use_strict_mode', '1');   // tolak session ID buatan penyerang
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    session_name('MPSESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => base_path() === '' ? '/' : base_path(),
        'secure'   => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    $now      = time();
    $idleMax  = (int) config('security', 'session_idle_timeout', 1800);
    $absMax   = (int) config('security', 'session_absolute_timeout', 28800);
    $uaHash   = hash('sha256', (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));

    if (isset($_SESSION['created'])) {
        $expired = ($now - (int) ($_SESSION['last_activity'] ?? 0) > $idleMax)
                || ($now - (int) $_SESSION['created'] > $absMax)
                || !hash_equals((string) ($_SESSION['ua'] ?? ''), $uaHash);
        if ($expired) {
            $wasLoggedIn = !empty($_SESSION['uid']);
            session_unset();
            session_destroy();
            session_start();
            session_regenerate_id(true);
            if ($wasLoggedIn) {
                flash('warning', 'Sesi Anda telah berakhir. Silakan masuk kembali.');
            }
        }
    }
    if (!isset($_SESSION['created'])) {
        $_SESSION['created'] = $now;
        $_SESSION['ua']      = $uaHash;
    }
    $_SESSION['last_activity'] = $now;
}

// ---------------------------------------------------------------------
// CSRF + same-origin (berlaku otomatis untuk semua POST, lihat bootstrap.php)
// ---------------------------------------------------------------------
function csrf_token(): string
{
    if (empty($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function verify_same_origin(): void
{
    $source = $_SERVER['HTTP_ORIGIN'] ?? ($_SERVER['HTTP_REFERER'] ?? '');
    if ($source === '') {
        return; // sebagian browser tidak mengirim; token CSRF tetap diwajibkan
    }
    $srcHost = parse_url((string) $source, PHP_URL_HOST);
    $myHost  = preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? ''));
    if (!is_string($srcHost) || $myHost === null || strcasecmp($srcHost, $myHost) !== 0) {
        security_log('origin_mismatch');
        abort(403, 'Permintaan ditolak karena berasal dari sumber yang tidak dikenali.');
    }
}

function enforce_post_protections(): void
{
    verify_same_origin();
    $token = $_POST['_csrf'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        security_log('csrf_invalid');
        abort(419, 'Sesi formulir tidak valid atau sudah kedaluwarsa. Muat ulang halaman lalu coba lagi.');
    }
}

// ---------------------------------------------------------------------
// Pencatatan kejadian keamanan (tanpa data pribadi: email di-hash)
// ---------------------------------------------------------------------
function security_log(string $event, string $subject = ''): void
{
    error_log(sprintf(
        '[security] %s ip=%s subject=%s uri=%s',
        $event,
        client_ip(),
        $subject === '' ? '-' : substr(hash('sha256', $subject), 0, 12),
        substr((string) ($_SERVER['REQUEST_URI'] ?? ''), 0, 120)
    ));
}

// ---------------------------------------------------------------------
// Throttle brute-force (login & registrasi)
// ---------------------------------------------------------------------
function throttle_blocked(string $aksi, string $email, string $ip, int $maxEmail, int $maxIp): bool
{
    $window = (int) config('security', 'lockout_seconds', 900);
    if ($maxEmail > 0 && $email !== '') {
        $row = db_one(
            'SELECT COUNT(*) AS c FROM login_attempts
             WHERE aksi = ? AND email = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL ? SECOND)',
            'ssi',
            [$aksi, $email, $window]
        );
        if ((int) ($row['c'] ?? 0) >= $maxEmail) {
            return true;
        }
    }
    $row = db_one(
        'SELECT COUNT(*) AS c FROM login_attempts
         WHERE aksi = ? AND ip_address = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL ? SECOND)',
        'ssi',
        [$aksi, $ip, $window]
    );
    return (int) ($row['c'] ?? 0) >= $maxIp;
}

function throttle_hit(string $aksi, string $email, string $ip): void
{
    db_exec(
        'INSERT INTO login_attempts (aksi, email, ip_address) VALUES (?, ?, ?)',
        'sss',
        [$aksi, mb_substr($email, 0, 150), $ip]
    );
    if (random_int(1, 20) === 1) { // bersihkan data lama sesekali
        db_exec('DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 1 DAY)');
    }
}

function throttle_clear(string $aksi, string $email): void
{
    db_exec('DELETE FROM login_attempts WHERE aksi = ? AND email = ?', 'ss', [$aksi, mb_substr($email, 0, 150)]);
}

// ---------------------------------------------------------------------
// Unggahan gambar produk yang aman
//   - cek kode error, ukuran, is_uploaded_file
//   - cek MIME SEBENARNYA lewat finfo (bukan ekstensi / Content-Type klien)
//   - cek dimensi lalu RE-ENCODE dengan GD -> metadata (EXIF) & payload
//     tersembunyi dibuang; hasil selalu JPEG
//   - nama file acak 32 hex, folder uploads/ tidak mengeksekusi skrip
// ---------------------------------------------------------------------
/** @return array{0: ?string, 1: ?string} [nama_file, pesan_error] */
function save_product_image(array $file): array
{
    if (!isset($file['error']) || is_array($file['error'])) {
        return [null, null]; // tidak ada unggahan
    }
    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        return [null, null];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return [null, 'Gagal mengunggah gambar. Pastikan ukuran file tidak lebih dari 2 MB.'];
    }
    $max = (int) config('upload', 'max_bytes', 2097152);
    if (!isset($file['size']) || $file['size'] <= 0 || $file['size'] > $max) {
        return [null, 'Ukuran gambar maksimal ' . round($max / 1048576) . ' MB.'];
    }
    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return [null, 'Unggahan tidak valid.'];
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        return [null, 'Format gambar harus JPG, PNG, atau WebP.'];
    }
    $info = @getimagesize($tmp);
    if ($info === false) {
        return [null, 'File bukan gambar yang valid.'];
    }
    [$w, $h] = $info;
    if ($w < 50 || $h < 50 || $w > 3000 || $h > 3000) {
        return [null, 'Dimensi gambar harus antara 50x50 dan 3000x3000 piksel.'];
    }

    $src = match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg($tmp),
        'image/png'  => @imagecreatefrompng($tmp),
        'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($tmp) : false,
        default      => false,
    };
    if ($src === false) {
        return [null, 'Gambar tidak dapat diproses. Coba file lain.'];
    }

    $maxDim = (int) config('upload', 'max_dimension', 1200);
    $scale  = min(1.0, $maxDim / max($w, $h));
    $nw     = max(1, (int) round($w * $scale));
    $nh     = max(1, (int) round($h * $scale));
    $dst    = imagecreatetruecolor($nw, $nh);
    imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255)); // latar putih untuk PNG transparan
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

    $dir = BASE_DIR . '/uploads/products';
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
        return [null, 'Folder unggahan tidak dapat dibuat.'];
    }
    $name = bin2hex(random_bytes(16)) . '.jpg';
    if (!imagejpeg($dst, $dir . '/' . $name, 85)) {
        return [null, 'Gagal menyimpan gambar.'];
    }
    @chmod($dir . '/' . $name, 0644);
    return [$name, null];
}

/** Hapus file gambar; nama divalidasi ketat sehingga tidak bisa keluar folder (path traversal). */
function delete_product_image(?string $name): void
{
    if ($name !== null && preg_match('/^[a-f0-9]{32}\.jpg$/', $name) === 1) {
        @unlink(BASE_DIR . '/uploads/products/' . $name);
    }
}
