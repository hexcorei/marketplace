<?php
/** Checkout: membuat & menyimpan transaksi pemesanan ke database. */
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/orders.php';

$user = require_role('pembeli');
$uid  = (int) $user['id'];

$items = db_all(
    'SELECT ci.product_id, ci.qty, p.nama, p.harga, p.stok, p.is_active
     FROM cart_items ci JOIN products p ON p.id = ci.product_id
     WHERE ci.user_id = ? ORDER BY ci.id',
    'i',
    [$uid]
);
if ($items === []) {
    flash('warning', 'Keranjang Anda kosong.');
    redirect('cart.php');
}
$total = 0.0;
foreach ($items as $it) {
    $total += (float) $it['harga'] * (int) $it['qty'];
}

$form = [
    'nama_penerima' => (string) $user['nama'],
    'telepon'       => (string) ($user['telepon'] ?? ''),
    'alamat'        => (string) ($user['alamat'] ?? ''),
    'catatan'       => '',
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = [
        'nama_penerima' => post_str('nama_penerima'),
        'telepon'       => post_str('telepon'),
        'alamat'        => post_str('alamat'),
        'catatan'       => post_str('catatan'),
    ];
    $len = static fn(string $s): int => mb_strlen($s);

    if ($len($form['nama_penerima']) < 2 || $len($form['nama_penerima']) > 100) {
        $errors[] = 'Nama penerima harus 2-100 karakter.';
    }
    if (preg_match('/^\+?[0-9][0-9\s\-]{7,18}$/', $form['telepon']) !== 1) {
        $errors[] = 'Nomor telepon tidak valid (8-20 digit, boleh diawali +).';
    }
    if ($len($form['alamat']) < 10 || $len($form['alamat']) > 500) {
        $errors[] = 'Alamat pengiriman harus 10-500 karakter.';
    }
    if ($len($form['catatan']) > 255) {
        $errors[] = 'Catatan maksimal 255 karakter.';
    }

    if ($errors === []) {
        try {
            $orderId = place_order($uid, $form);
            flash('success', 'Pesanan berhasil dibuat. Terima kasih telah berbelanja!');
            redirect('order_detail.php?id=' . $orderId);
        } catch (OrderException $ex) {
            $errors[] = $ex->getMessage();
        }
    }
}

render_header('Checkout');
?>
<h1 class="h3 mb-3">Checkout</h1>

<?php if ($errors !== []): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header fw-semibold">Data Pengiriman</div>
            <div class="card-body">
                <form method="post" action="<?= e(url('checkout.php')) ?>" novalidate>
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label for="nama_penerima" class="form-label">Nama penerima</label>
                        <input type="text" class="form-control" id="nama_penerima" name="nama_penerima" maxlength="100"
                               value="<?= e($form['nama_penerima']) ?>" required autocomplete="name">
                    </div>
                    <div class="mb-3">
                        <label for="telepon" class="form-label">Nomor telepon</label>
                        <input type="tel" class="form-control" id="telepon" name="telepon" maxlength="20"
                               value="<?= e($form['telepon']) ?>" required autocomplete="tel">
                    </div>
                    <div class="mb-3">
                        <label for="alamat" class="form-label">Alamat lengkap</label>
                        <textarea class="form-control" id="alamat" name="alamat" rows="3" maxlength="500" required><?= e($form['alamat']) ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="catatan" class="form-label">Catatan untuk penjual (opsional)</label>
                        <input type="text" class="form-control" id="catatan" name="catatan" maxlength="255" value="<?= e($form['catatan']) ?>">
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg">Buat Pesanan</button>
                    <a class="btn btn-link" href="<?= e(url('cart.php')) ?>">Kembali ke keranjang</a>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header fw-semibold">Ringkasan Pesanan</div>
            <ul class="list-group list-group-flush">
                <?php foreach ($items as $it): ?>
                    <li class="list-group-item d-flex justify-content-between">
                        <span><?= e($it['nama']) ?> <span class="text-muted">&times; <?= (int) $it['qty'] ?></span></span>
                        <span class="text-nowrap"><?= e(rupiah((float) $it['harga'] * (int) $it['qty'])) ?></span>
                    </li>
                <?php endforeach; ?>
                <li class="list-group-item d-flex justify-content-between fw-bold">
                    <span>Total</span><span class="text-primary"><?= e(rupiah($total)) ?></span>
                </li>
            </ul>
            <div class="card-footer small text-muted">Harga dan stok diverifikasi ulang oleh server saat pesanan dibuat.</div>
        </div>
    </div>
</div>
<?php render_footer(); ?>
