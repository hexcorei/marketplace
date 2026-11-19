<?php
/** Registrasi akun pembeli / penjual dengan validasi server-side & password hashing. */
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$existing = current_user();
if ($existing !== null) {
    redirect(home_for($existing));
}

$errors = [];
$form   = ['nama' => '', 'email' => '', 'role' => 'pembeli'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form['nama']  = post_str('nama');
    $form['email'] = mb_strtolower(post_str('email'));
    $form['role']  = post_str('role');
    $password      = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $confirm       = is_string($_POST['password_confirm'] ?? null) ? $_POST['password_confirm'] : '';
    $ip            = client_ip();

    if (throttle_blocked('register', '', $ip, 0, (int) config('security', 'max_register_per_ip', 10))) {
        security_log('register_locked');
        $errors[] = 'Terlalu banyak percobaan pendaftaran. Silakan coba lagi dalam beberapa menit.';
    } else {
        throttle_hit('register', '', $ip);

        if (preg_match("/^[\p{L}\p{M}\s.'\-]{2,100}$/u", $form['nama']) !== 1) {
            $errors[] = 'Nama harus 2-100 karakter (huruf, spasi, titik, apostrof, atau tanda hubung).';
        }
        if (mb_strlen($form['email']) > 150 || filter_var($form['email'], FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = 'Format email tidak valid.';
        }
        if (!in_array($form['role'], ['pembeli', 'penjual'], true)) {
            $errors[] = 'Pilih jenis akun: pembeli atau penjual.';
        }
        $minLen = (int) config('security', 'min_password_length', 10);
        if (strlen($password) < $minLen || strlen($password) > 128
            || preg_match('/[A-Za-z]/', $password) !== 1 || preg_match('/\d/', $password) !== 1) {
            $errors[] = "Kata sandi minimal $minLen karakter dan harus mengandung huruf serta angka.";
        } elseif (!hash_equals($password, $confirm)) {
            $errors[] = 'Konfirmasi kata sandi tidak sama.';
        } elseif (strtolower($password) === $form['email']) {
            $errors[] = 'Kata sandi tidak boleh sama dengan email.';
        }

        if ($errors === []) {
            try {
                db_insert(
                    'INSERT INTO users (nama, email, password_hash, role) VALUES (?, ?, ?, ?)',
                    'ssss',
                    [$form['nama'], $form['email'], password_hash($password, PASSWORD_DEFAULT, ['cost' => 12]), $form['role']]
                );
                flash('success', 'Pendaftaran berhasil. Silakan masuk.');
                redirect('login.php');
            } catch (mysqli_sql_exception $ex) {
                if ((int) $ex->getCode() === 1062) { // email sudah dipakai (UNIQUE)
                    $errors[] = 'Email sudah terdaftar. Silakan masuk atau gunakan email lain.';
                } else {
                    throw $ex;
                }
            }
        }
    }
}

render_header('Daftar');
$minLen = (int) config('security', 'min_password_length', 10);
?>
<div class="row justify-content-center">
    <div class="col-md-7 col-lg-6">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h1 class="h4 mb-3">Buat Akun Baru</h1>
                <?php if ($errors !== []): ?>
                    <div class="alert alert-danger py-2">
                        <ul class="mb-0 ps-3">
                            <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                <form method="post" action="<?= e(url('register.php')) ?>" novalidate>
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label for="nama" class="form-label">Nama lengkap</label>
                        <input type="text" class="form-control" id="nama" name="nama" maxlength="100"
                               value="<?= e($form['nama']) ?>" required autocomplete="name">
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" maxlength="150"
                               value="<?= e($form['email']) ?>" required autocomplete="email">
                    </div>
                    <div class="mb-3">
                        <label class="form-label d-block">Daftar sebagai</label>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="role" id="role_pembeli" value="pembeli" <?= $form['role'] !== 'penjual' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="role_pembeli">Pembeli</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="role" id="role_penjual" value="penjual" <?= $form['role'] === 'penjual' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="role_penjual">Penjual</label>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Kata sandi</label>
                        <input type="password" class="form-control" id="password" name="password" maxlength="128"
                               required autocomplete="new-password" aria-describedby="pwHelp">
                        <div id="pwHelp" class="form-text">Minimal <?= $minLen ?> karakter, mengandung huruf dan angka.</div>
                    </div>
                    <div class="mb-3">
                        <label for="password_confirm" class="form-label">Ulangi kata sandi</label>
                        <input type="password" class="form-control" id="password_confirm" name="password_confirm" maxlength="128"
                               required autocomplete="new-password">
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Daftar</button>
                </form>
                <p class="small text-muted mt-3 mb-0">Sudah punya akun? <a href="<?= e(url('login.php')) ?>">Masuk</a>.</p>
            </div>
        </div>
    </div>
</div>
<?php render_footer(); ?>
