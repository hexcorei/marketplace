<?php
/** Riwayat pesanan milik pembeli yang sedang login. */
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$user   = require_role('pembeli');
$orders = db_all(
    'SELECT id, kode_pesanan, total, status, created_at
     FROM orders WHERE buyer_id = ? ORDER BY created_at DESC, id DESC LIMIT 100',
    'i',
    [(int) $user['id']]
);

render_header('Pesanan Saya');
?>
<h1 class="h3 mb-3">Pesanan Saya</h1>
<?php if ($orders === []): ?>
    <div class="alert alert-info">Anda belum memiliki pesanan.</div>
    <a class="btn btn-primary" href="<?= e(url('index.php')) ?>">Mulai belanja</a>
<?php else: ?>
    <div class="table-responsive bg-white border rounded">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th>Kode</th><th>Tanggal</th><th class="text-end">Total</th><th>Status</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($orders as $o): ?>
                <tr>
                    <td class="fw-semibold"><?= e($o['kode_pesanan']) ?></td>
                    <td><?= e(tanggal($o['created_at'])) ?></td>
                    <td class="text-end"><?= e(rupiah($o['total'])) ?></td>
                    <td><?= status_badge($o['status']) ?></td>
                    <td class="text-end"><a class="btn btn-outline-primary btn-sm" href="<?= e(url('order_detail.php?id=' . (int) $o['id'])) ?>">Detail</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<?php render_footer(); ?>
