<?php
declare(strict_types=1);
if (!defined('APP_BOOT')) { http_response_code(403); exit; }

/** Hash bcrypt (cost 12) palsu: dipakai agar waktu respons login sama baik email ada maupun tidak. */
const DUMMY_PASSWORD_HASH = '$2y$12$dSCnFR.FBTOfP.8Vy1ZhMuqtXRgjmG2W2.UwoAhj5jR1AxgcPBAHm';

/** Pengguna yang sedang login (selalu dibaca ulang dari DB agar role/akun terbaru yang berlaku). */
function current_user(): ?array
{
    static $user = false;
    if ($user !== false) {
        return $user;
    }
    $user = null;
    $uid  = $_SESSION['uid'] ?? null;
    if (is_int($uid) && $uid > 0) {
        $user = db_one('SELECT id, nama, email, role, telepon, alamat FROM users WHERE id = ?', 'i', [$uid]);
        if ($user === null) {
            unset($_SESSION['uid']);
        }
    }
    return $user;
}

/** Tandai sesi sebagai login. Ganti session ID (anti session fixation) dan token CSRF. */
function auth_login(int $userId): void
{
    session_regenerate_id(true);
    $_SESSION['uid']           = $userId;
    $_SESSION['created']       = time();
    $_SESSION['last_activity'] = time();
    $_SESSION['csrf']          = bin2hex(random_bytes(32));
}

function auth_logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $p['path'],
            'domain'   => $p['domain'],
            'secure'   => $p['secure'],
            'httponly' => $p['httponly'],
            'samesite' => $p['samesite'] ?? 'Lax',
        ]);
    }
    session_destroy();
}

function require_login(): array
{
    $user = current_user();
    if ($user === null) {
        flash('warning', 'Silakan masuk terlebih dahulu.');
        redirect('login.php');
    }
    return $user;
}

/** RBAC: hanya role tertentu yang boleh mengakses halaman. */
function require_role(string $role): array
{
    $user = require_login();
    if ($user['role'] !== $role) {
        abort(403, 'Anda tidak memiliki akses ke halaman ini.');
    }
    return $user;
}

function home_for(array $user): string
{
    return $user['role'] === 'penjual' ? 'seller/products.php' : 'index.php';
}
