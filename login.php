<?php
/** Login: password_verify + throttle brute-force + pesan error generik + regenerasi sesi. */
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$existing = current_user();
if ($existing !== null) {
    redirect(home_for($existing));
}

$errors = [];
$email  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = mb_strtolower(post_str('email'));
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $ip       = client_ip();

    $maxEmail = (int) config('security', 'max_login_attempts', 5);
    $maxIp    = (int) config('security', 'max_login_attempts_ip', 30);

    if (throttle_blocked('login', $email, $ip, $maxEmail, $maxIp)) {
        security_log('login_locked', $email);
        $errors[] = 'Terlalu banyak percobaan masuk. Silakan coba lagi dalam beberapa menit.';
    } else {
        $row = null;
        if ($email !== '' && mb_strlen($email) <= 150) {
            $row = db_one('SELECT id, password_hash FROM users WHERE email = ?', 's', [$email]);
        }
        // Selalu menjalankan password_verify (hash palsu bila email tidak ada) -> waktu respons seragam
        $hash = $row['password_hash'] ?? DUMMY_PASSWORD_HASH;
        $ok   = strlen($password) <= 128 && password_verify($password, $hash) && $row !== null;

        if ($ok) {
            if (password_needs_rehash($hash, PASSWORD_DEFAULT, ['cost' => 12])) {
                db_exec('UPDATE users SET password_hash = ? WHERE id = ?', 'si', [password_hash($password, PASSWORD_DEFAULT, ['cost' => 12]), (int) $row['id']]);
            }
            throttle_clear('login', $email);
            auth_login((int) $row['id']);
            $u = db_one('SELECT role FROM users WHERE id = ?', 'i', [(int) $row['id']]);
            flash('success', 'Berhasil masuk. Selamat datang!');
            redirect(home_for(['role' => (string) ($u['role'] ?? 'pembeli')]));
        }

        throttle_hit('login', $email, $ip);
        security_log('login_failed', $email);
        // Pesan sama untuk "email tidak ada" dan "password salah" (cegah user enumeration)
        $errors[] = 'Email atau kata sandi salah.';
    }
}

render_header('Masuk');
?>
<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h1 class="h4 mb-3">Masuk</h1>
                <?php foreach ($errors as $err): ?>
                    <div class="alert alert-danger py-2"><?= e($err) ?></div>
                <?php endforeach; ?>
                <form method="post" action="<?= e(url('login.php')) ?>" novalidate>
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" maxlength="150"
                               value="<?= e($email) ?>" required autocomplete="email" autofocus>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Kata sandi</label>
                        <input type="password" class="form-control" id="password" name="password" maxlength="128"
                               required autocomplete="current-password">
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Masuk</button>
                </form>
                <p class="small text-muted mt-3 mb-0">Belum punya akun? <a href="<?= e(url('register.php')) ?>">Daftar di sini</a>.</p>
            </div>
        </div>
    </div>
</div>
<?php render_footer(); ?>
