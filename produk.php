<?php
/** Halaman Detail Produk (publik). */
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$id = get_int('id');
if ($id === null) {
    abort(404, 'Produk tidak ditemukan.');
}
$user = current_user();

$p = db_one(
    'SELECT p.id, p.seller_id, p.category_id, p.nama, p.deskripsi, p.harga, p.stok, p.gambar, p.is_active,
            c.nama AS kategori, u.nama AS penjual
     FROM products p
     JOIN categories c ON c.id = p.category_id
     JOIN users u ON u.id = p.seller_id
     WHERE p.id = ?',
    'i',
    [$id]
);
// Produk nonaktif hanya boleh dilihat oleh penjualnya sendiri
$isOwner = $p !== null && $user !== null && (int) $user['id'] === (int) $p['seller_id'];
if ($p === null || ((int) $p['is_active'] !== 1 && !$isOwner)) {
    abort(404, 'Produk tidak ditemukan.');
}
$stok = (int) $p['stok'];

render_header($p['nama']);
?>
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= e(url('index.php')) ?>">Produk</a></li>
        <li class="breadcrumb-item"><a href="<?= e(url('index.php?kategori=' . (int) $p['category_id'])) ?>"><?= e($p['kategori']) ?></a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= e($p['nama']) ?></li>
    </ol>
</nav>

<?php if ((int) $p['is_active'] !== 1): ?>
    <div class="alert alert-warning">Produk ini berstatus nonaktif dan hanya terlihat oleh Anda sebagai penjual.</div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-md-5">
        <div class="card"><img class="product-detail-img rounded" src="<?= e(product_image_url($p['gambar'])) ?>" alt="<?= e($p['nama']) ?>"></div>
    </div>
    <div class="col-md-7">
        <span class="badge text-bg-secondary mb-2"><?= e($p['kategori']) ?></span>
        <h1 class="h3"><?= e($p['nama']) ?></h1>
        <div class="display-6 text-primary fw-bold my-3"><?= e(rupiah($p['harga'])) ?></div>
        <dl class="row mb-3">
            <dt class="col-sm-3">Penjual</dt><dd class="col-sm-9"><?= e($p['penjual']) ?></dd>
            <dt class="col-sm-3">Stok</dt>
            <dd class="col-sm-9">
                <?php if ($stok > 0): ?><?= $stok ?> unit<?php else: ?><span class="badge text-bg-danger">Habis</span><?php endif; ?>
            </dd>
        </dl>

        <?php if ($user === null): ?>
            <div class="alert alert-info">
                Silakan <a href="<?= e(url('login.php')) ?>">masuk</a> atau
                <a href="<?= e(url('register.php')) ?>">daftar</a> sebagai pembeli untuk memesan produk ini.
            </div>
        <?php elseif ($user['role'] !== 'pembeli'): ?>
            <div class="alert alert-secondary">Akun penjual tidak dapat membeli produk. Gunakan akun pembeli untuk berbelanja.</div>
        <?php elseif ($stok < 1): ?>
            <button class="btn btn-secondary btn-lg" disabled>Stok habis</button>
        <?php else: ?>
            <form method="post" action="<?= e(url('cart.php')) ?>" class="row g-2 align-items-end">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="product_id" value="<?= (int) $p['id'] ?>">
                <div class="col-auto">
                    <label for="qty" class="form-label mb-1">Jumlah</label>
                    <input type="number" class="form-control qty-input" id="qty" name="qty" value="1" min="1" max="<?= $stok ?>" required>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary btn-lg">Tambah ke Keranjang</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="card mt-4">
    <div class="card-header fw-semibold">Deskripsi Produk</div>
    <div class="card-body">
        <p class="mb-0 text-prewrap"><?= $p['deskripsi'] !== null && $p['deskripsi'] !== '' ? e($p['deskripsi']) : 'Penjual belum menambahkan deskripsi.' ?></p>
    </div>
</div>
<?php render_footer(); ?>
