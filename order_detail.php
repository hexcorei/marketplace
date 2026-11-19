<?php
/** Detail satu pesanan. Hanya pemilik pesanan yang boleh melihat (cegah IDOR). */
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/orders.php';

$user = require_role('pembeli');
$uid  = (int) $user['id'];
$id   = get_int('id');
if ($id === null) {
    abort(404, 'Pesanan tidak ditemukan.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'cancel') {
    try {
        cancel_order($id, $uid);
        flash('success', 'Pesanan dibatalkan dan stok dikembalikan.');
    } catch (OrderException $ex) {
        flash('danger', $ex->getMessage());
    }
    redirect('order_detail.php?id=' . $id);
}

// Filter buyer_id = pengguna login: pesanan milik orang lain tampil sebagai "tidak ditemukan"
$order = db_one(
    'SELECT id, kode_pesanan, total, status, nama_penerima, telepon_penerima, alamat_pengiriman, catatan, created_at
     FROM orders WHERE id = ? AND buyer_id = ?',
    'ii',
    [$id, $uid]
);
if ($order === null) {
    abort(404, 'Pesanan tidak ditemukan.');
}
$items = db_all(
    'SELECT nama_produk, harga_satuan, qty, subtotal FROM order_items WHERE order_id = ? ORDER BY id',
    'i',
    [$id]
);

render_header('Pesanan ' . $order['kode_pesanan']);
?>
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= e(url('orders.php')) ?>">Pesanan Saya</a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= e($order['kode_pesanan']) ?></li>
    </ol>
</nav>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <h1 class="h3 mb-0">Pesanan <?= e($order['kode_pesanan']) ?></h1>
    <?= status_badge($order['status']) ?>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="table-responsive bg-white border rounded">
            <table class="table mb-0">
                <thead class="table-light">
                    <tr><th>Produk</th><th class="text-end">Harga</th><th class="text-center">Jumlah</th><th class="text-end">Subtotal</th></tr>
                </thead>
                <tbody>
                <?php foreach ($items as $it): ?>
                    <tr>
                        <td><?= e($it['nama_produk']) ?></td>
                        <td class="text-end text-nowrap"><?= e(rupiah($it['harga_satuan'])) ?></td>
                        <td class="text-center"><?= (int) $it['qty'] ?></td>
                        <td class="text-end text-nowrap"><?= e(rupiah($it['subtotal'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="fw-bold"><td colspan="3" class="text-end">Total</td><td class="text-end text-primary"><?= e(rupiah($order['total'])) ?></td></tr>
                </tfoot>
            </table>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header fw-semibold">Pengiriman</div>
            <div class="card-body small">
                <div class="fw-semibold"><?= e($order['nama_penerima']) ?></div>
                <div><?= e($order['telepon_penerima']) ?></div>
                <div class="text-prewrap mt-2"><?= e($order['alamat_pengiriman']) ?></div>
                <?php if ($order['catatan'] !== null && $order['catatan'] !== ''): ?>
                    <div class="mt-2 text-muted">Catatan: <?= e($order['catatan']) ?></div>
                <?php endif; ?>
                <hr>
                <div class="text-muted">Dibuat: <?= e(tanggal($order['created_at'])) ?></div>
            </div>
            <?php if ($order['status'] === 'menunggu_pembayaran'): ?>
                <div class="card-footer">
                    <form method="post" action="<?= e(url('order_detail.php?id=' . $id)) ?>" data-confirm="Batalkan pesanan ini? Stok produk akan dikembalikan.">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="cancel">
                        <button type="submit" class="btn btn-outline-danger btn-sm w-100">Batalkan Pesanan</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php render_footer(); ?>
