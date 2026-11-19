<?php
/** Pesanan masuk: hanya baris pesanan untuk produk milik penjual yang login (filter oleh seller_id). */
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';

$user  = require_role('penjual');
$lines = db_all(
    'SELECT o.id AS order_id, o.kode_pesanan, o.status, o.created_at,
            o.nama_penerima, o.telepon_penerima, o.alamat_pengiriman,
            oi.nama_produk, oi.harga_satuan, oi.qty, oi.subtotal
     FROM order_items oi
     JOIN orders o ON o.id = oi.order_id
     WHERE oi.seller_id = ?
     ORDER BY o.created_at DESC, o.id DESC, oi.id
     LIMIT 200',
    'i',
    [(int) $user['id']]
);

render_header('Pesanan Masuk');
?>
<h1 class="h3 mb-3">Pesanan Masuk</h1>
<?php if ($lines === []): ?>
    <div class="alert alert-info">Belum ada pesanan untuk produk Anda.</div>
<?php else: ?>
    <div class="table-responsive bg-white border rounded">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th>Kode</th><th>Tanggal</th><th>Produk</th><th class="text-center">Jml</th><th class="text-end">Subtotal</th><th>Penerima</th><th>Status</th></tr>
            </thead>
            <tbody>
            <?php foreach ($lines as $l): ?>
                <tr>
                    <td class="fw-semibold text-nowrap"><?= e($l['kode_pesanan']) ?></td>
                    <td class="text-nowrap"><?= e(tanggal($l['created_at'])) ?></td>
                    <td><?= e($l['nama_produk']) ?></td>
                    <td class="text-center"><?= (int) $l['qty'] ?></td>
                    <td class="text-end text-nowrap"><?= e(rupiah($l['subtotal'])) ?></td>
                    <td class="small">
                        <div class="fw-semibold"><?= e($l['nama_penerima']) ?> &middot; <?= e($l['telepon_penerima']) ?></div>
                        <div class="text-muted text-prewrap"><?= e($l['alamat_pengiriman']) ?></div>
                    </td>
                    <td><?= status_badge($l['status']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<?php render_footer(); ?>
