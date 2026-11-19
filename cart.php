<?php
/** Keranjang belanja (khusus pembeli). Disimpan di database per pengguna. */
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$user = require_role('pembeli');
$uid  = (int) $user['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['remove']) ? 'remove' : post_str('action');

    switch ($action) {
        case 'add':
            $pid = post_int('product_id');
            $qty = post_int('qty', 1, 1000);
            if ($pid === null || $qty === null) {
                flash('danger', 'Data produk atau jumlah tidak valid.');
                redirect('index.php');
            }
            $p = db_one('SELECT id, nama, stok FROM products WHERE id = ? AND is_active = 1', 'i', [$pid]);
            if ($p === null) {
                flash('danger', 'Produk tidak ditemukan atau sudah tidak dijual.');
                redirect('index.php');
            }
            $existing = db_one('SELECT qty FROM cart_items WHERE user_id = ? AND product_id = ?', 'ii', [$uid, $pid]);
            $newQty   = ($existing !== null ? (int) $existing['qty'] : 0) + $qty;
            if ($newQty > (int) $p['stok']) {
                flash('warning', 'Stok "' . $p['nama'] . '" hanya tersedia ' . (int) $p['stok'] . ' unit (di keranjang: ' . ($newQty - $qty) . ').');
                redirect('produk.php?id=' . $pid);
            }
            if ($existing !== null) {
                db_exec('UPDATE cart_items SET qty = ? WHERE user_id = ? AND product_id = ?', 'iii', [$newQty, $uid, $pid]);
            } else {
                db_exec('INSERT INTO cart_items (user_id, product_id, qty) VALUES (?, ?, ?)', 'iii', [$uid, $pid, $newQty]);
            }
            flash('success', 'Produk ditambahkan ke keranjang.');
            redirect('cart.php');

        case 'update':
            $qtys = $_POST['qty'] ?? [];
            if (is_array($qtys)) {
                $rows = db_all(
                    'SELECT ci.product_id, p.stok FROM cart_items ci JOIN products p ON p.id = ci.product_id WHERE ci.user_id = ?',
                    'i',
                    [$uid]
                );
                foreach ($rows as $r) {
                    $pid = (int) $r['product_id'];
                    $q   = int_or_null($qtys[$pid] ?? null, 0, 1000);
                    if ($q === null) {
                        continue;
                    }
                    $q = min($q, (int) $r['stok']);
                    if ($q < 1) {
                        db_exec('DELETE FROM cart_items WHERE user_id = ? AND product_id = ?', 'ii', [$uid, $pid]);
                    } else {
                        db_exec('UPDATE cart_items SET qty = ? WHERE user_id = ? AND product_id = ?', 'iii', [$q, $uid, $pid]);
                    }
                }
            }
            flash('success', 'Keranjang diperbarui.');
            redirect('cart.php');

        case 'remove':
            $pid = int_or_null($_POST['remove'] ?? null);
            if ($pid !== null) {
                db_exec('DELETE FROM cart_items WHERE user_id = ? AND product_id = ?', 'ii', [$uid, $pid]);
                flash('info', 'Produk dihapus dari keranjang.');
            }
            redirect('cart.php');

        default:
            flash('danger', 'Aksi tidak dikenali.');
            redirect('cart.php');
    }
}

$items = db_all(
    'SELECT ci.product_id, ci.qty, p.nama, p.harga, p.stok, p.gambar, p.is_active
     FROM cart_items ci JOIN products p ON p.id = ci.product_id
     WHERE ci.user_id = ? ORDER BY ci.id',
    'i',
    [$uid]
);
$total   = 0.0;
$problem = false;
foreach ($items as $it) {
    $total += (float) $it['harga'] * (int) $it['qty'];
    if ((int) $it['is_active'] !== 1 || (int) $it['qty'] > (int) $it['stok']) {
        $problem = true;
    }
}

render_header('Keranjang Belanja');
?>
<h1 class="h3 mb-3">Keranjang Belanja</h1>

<?php if ($items === []): ?>
    <div class="alert alert-info">Keranjang Anda masih kosong.</div>
    <a class="btn btn-primary" href="<?= e(url('index.php')) ?>">Mulai belanja</a>
<?php else: ?>
    <?php if ($problem): ?>
        <div class="alert alert-warning">Beberapa produk tidak tersedia atau stoknya kurang dari jumlah di keranjang. Perbarui jumlah sebelum checkout.</div>
    <?php endif; ?>
    <div class="row g-4">
        <div class="col-lg-8">
            <form method="post" action="<?= e(url('cart.php')) ?>">
                <?= csrf_field() ?>
                <div class="table-responsive bg-white border rounded">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr><th>Produk</th><th class="text-end">Harga</th><th>Jumlah</th><th class="text-end">Subtotal</th><th></th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($items as $it): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <img class="cart-thumb rounded" src="<?= e(product_image_url($it['gambar'])) ?>" alt="">
                                        <div>
                                            <a class="text-decoration-none" href="<?= e(url('produk.php?id=' . (int) $it['product_id'])) ?>"><?= e($it['nama']) ?></a>
                                            <?php if ((int) $it['is_active'] !== 1): ?>
                                                <div class="small text-danger">Tidak lagi dijual</div>
                                            <?php elseif ((int) $it['qty'] > (int) $it['stok']): ?>
                                                <div class="small text-danger">Stok tersisa <?= (int) $it['stok'] ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-end text-nowrap"><?= e(rupiah($it['harga'])) ?></td>
                                <td>
                                    <input type="number" class="form-control form-control-sm qty-input"
                                           name="qty[<?= (int) $it['product_id'] ?>]" value="<?= (int) $it['qty'] ?>"
                                           min="0" max="<?= max(0, (int) $it['stok']) ?>" aria-label="Jumlah <?= e($it['nama']) ?>">
                                </td>
                                <td class="text-end text-nowrap fw-semibold"><?= e(rupiah((float) $it['harga'] * (int) $it['qty'])) ?></td>
                                <td class="text-end">
                                    <button type="submit" name="remove" value="<?= (int) $it['product_id'] ?>"
                                            class="btn btn-outline-danger btn-sm">Hapus</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="d-flex gap-2 mt-3">
                    <button type="submit" name="action" value="update" class="btn btn-outline-primary">Perbarui Keranjang</button>
                    <a class="btn btn-link" href="<?= e(url('index.php')) ?>">Lanjut belanja</a>
                </div>
            </form>
        </div>
        <div class="col-lg-4">
            <div class="card">
                <div class="card-body">
                    <h2 class="h5">Ringkasan</h2>
                    <div class="d-flex justify-content-between my-3">
                        <span>Total</span><span class="fw-bold fs-5 text-primary"><?= e(rupiah($total)) ?></span>
                    </div>
                    <?php if ($problem): ?>
                        <button class="btn btn-secondary w-100" disabled>Checkout</button>
                    <?php else: ?>
                        <a class="btn btn-primary w-100" href="<?= e(url('checkout.php')) ?>">Checkout</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
<?php render_footer(); ?>
