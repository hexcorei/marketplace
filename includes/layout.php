<?php
declare(strict_types=1);
if (!defined('APP_BOOT')) { http_response_code(403); exit; }

function render_header(string $title): void
{
    $user    = current_user();
    $appName = (string) config('app', 'name', 'TokoKita');
    $cartCount = 0;
    if ($user !== null && $user['role'] === 'pembeli') {
        $row = db_one('SELECT COALESCE(SUM(qty), 0) AS c FROM cart_items WHERE user_id = ?', 'i', [(int) $user['id']]);
        $cartCount = (int) ($row['c'] ?? 0);
    }
    header('Content-Type: text/html; charset=utf-8');
    ?><!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> | <?= e($appName) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="<?= e(url('assets/css/app.css')) ?>">
</head>
<body class="bg-light d-flex flex-column min-vh-100">
<nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top">
    <div class="container">
        <a class="navbar-brand fw-bold" href="<?= e(url('index.php')) ?>"><?= e($appName) ?></a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"
                aria-controls="mainNav" aria-expanded="false" aria-label="Buka menu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link" href="<?= e(url('index.php')) ?>">Produk</a></li>
                <?php if ($user !== null && $user['role'] === 'penjual'): ?>
                    <li class="nav-item"><a class="nav-link" href="<?= e(url('seller/products.php')) ?>">Produk Saya</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= e(url('seller/orders.php')) ?>">Pesanan Masuk</a></li>
                <?php elseif ($user !== null): ?>
                    <li class="nav-item"><a class="nav-link" href="<?= e(url('orders.php')) ?>">Pesanan Saya</a></li>
                <?php endif; ?>
            </ul>
            <div class="d-flex align-items-center gap-2">
                <?php if ($user !== null && $user['role'] === 'pembeli'): ?>
                    <a class="btn btn-light btn-sm position-relative" href="<?= e(url('cart.php')) ?>">
                        Keranjang
                        <?php if ($cartCount > 0): ?>
                            <span class="badge text-bg-danger ms-1"><?= (int) $cartCount ?></span>
                        <?php endif; ?>
                    </a>
                <?php endif; ?>
                <?php if ($user !== null): ?>
                    <span class="navbar-text text-white small d-none d-lg-inline">
                        <?= e($user['nama']) ?> (<?= e($user['role']) ?>)
                    </span>
                    <form method="post" action="<?= e(url('logout.php')) ?>" class="m-0">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-outline-light btn-sm">Keluar</button>
                    </form>
                <?php else: ?>
                    <a class="btn btn-outline-light btn-sm" href="<?= e(url('login.php')) ?>">Masuk</a>
                    <a class="btn btn-light btn-sm" href="<?= e(url('register.php')) ?>">Daftar</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>
<main class="container py-4 flex-grow-1">
<?php
    $allowed = ['success', 'danger', 'warning', 'info'];
    foreach (take_flashes() as $f) {
        $type = in_array($f['type'] ?? '', $allowed, true) ? $f['type'] : 'info';
        echo '<div class="alert alert-' . e($type) . ' alert-dismissible fade show" role="alert">'
           . e($f['msg'] ?? '')
           . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button></div>';
    }
}

function render_footer(): void
{
    $appName = (string) config('app', 'name', 'TokoKita');
    ?>
</main>
<footer class="bg-dark text-white-50 py-3 mt-auto">
    <div class="container small text-center">&copy; <?= date('Y') ?> <?= e($appName) ?> &middot; Marketplace sederhana (PHP Native, MySQLi, Bootstrap)</div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script src="<?= e(url('assets/js/app.js')) ?>"></script>
</body>
</html>
<?php
}

/** Hentikan request dengan halaman error yang rapi. */
function abort(int $code, string $message): never
{
    http_response_code($code);
    render_header('Kesalahan ' . $code);
    echo '<div class="row justify-content-center"><div class="col-md-7"><div class="alert alert-danger">';
    echo '<h4 class="alert-heading">Kesalahan ' . (int) $code . '</h4><p class="mb-0">' . e($message) . '</p></div>';
    echo '<a class="btn btn-primary" href="' . e(url('index.php')) . '">Kembali ke beranda</a></div></div>';
    render_footer();
    exit;
}
