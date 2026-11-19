<?php
/** CRUD Produk - bagian Read: daftar produk milik penjual yang login. */
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';

$user = require_role('penjual');
$products = db_all(
    'SELECT p.id, p.nama, p.harga, p.stok, p.gambar, p.is_active, c.nama AS kategori
     FROM products p JOIN categories c ON c.id = p.category_id
     WHERE p.seller_id = ? ORDER BY p.created_at DESC, p.id DESC',
    'i',
    [(int) $user['id']]
);

render_header('Produk Saya');
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <h1 class="h3 mb-0">Produk Saya</h1>
    <a class="btn btn-primary" href="<?= e(url('seller/product_form.php')) ?>">+ Tambah Produk</a>
</div>

<?php if ($products === []): ?>
    <div class="alert alert-info">Anda belum memiliki produk. Klik "Tambah Produk" untuk mulai berjualan.</div>
<?php else: ?>
    <div class="table-responsive bg-white border rounded">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th>Produk</th><th>Kategori</th><th class="text-end">Harga</th><th class="text-center">Stok</th><th>Status</th><th class="text-end">Aksi</th></tr>
            </thead>
            <tbody>
            <?php foreach ($products as $p): ?>
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <img class="cart-thumb rounded" src="<?= e(product_image_url($p['gambar'])) ?>" alt="">
                            <a class="text-decoration-none" href="<?= e(url('produk.php?id=' . (int) $p['id'])) ?>"><?= e($p['nama']) ?></a>
                        </div>
                    </td>
                    <td><?= e($p['kategori']) ?></td>
                    <td class="text-end text-nowrap"><?= e(rupiah($p['harga'])) ?></td>
                    <td class="text-center"><?= (int) $p['stok'] ?></td>
                    <td>
                        <?= (int) $p['is_active'] === 1
                            ? '<span class="badge text-bg-success">Aktif</span>'
                            : '<span class="badge text-bg-secondary">Nonaktif</span>' ?>
                    </td>
                    <td class="text-end text-nowrap">
                        <a class="btn btn-outline-primary btn-sm" href="<?= e(url('seller/product_form.php?id=' . (int) $p['id'])) ?>">Edit</a>
                        <form method="post" action="<?= e(url('seller/product_delete.php')) ?>" class="d-inline"
                              data-confirm="Hapus produk &quot;<?= e($p['nama']) ?>&quot;? Tindakan ini tidak dapat dibatalkan.">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                            <button type="submit" class="btn btn-outline-danger btn-sm">Hapus</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<?php render_footer(); ?>
