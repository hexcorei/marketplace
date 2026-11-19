<?php
/**
 * CRUD Produk - Create & Update (satu formulir).
 * Kepemilikan diperiksa di sisi server: penjual hanya bisa mengubah produknya sendiri (cegah IDOR).
 */
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';

$user = require_role('penjual');
$sid  = (int) $user['id'];
$id   = get_int('id');

$product = null;
if ($id !== null) {
    $product = db_one('SELECT * FROM products WHERE id = ? AND seller_id = ?', 'ii', [$id, $sid]);
    if ($product === null) {
        abort(404, 'Produk tidak ditemukan.');
    }
}
$categories = db_all('SELECT id, nama FROM categories ORDER BY nama');
$catIds     = array_map(static fn(array $c): int => (int) $c['id'], $categories);

$form = [
    'nama'        => (string) ($product['nama'] ?? ''),
    'category_id' => (string) ($product['category_id'] ?? ''),
    'deskripsi'   => (string) ($product['deskripsi'] ?? ''),
    'harga'       => $product !== null ? (string) (int) $product['harga'] : '',
    'stok'        => (string) ($product['stok'] ?? '0'),
    'is_active'   => $product === null ? 1 : (int) $product['is_active'],
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form['nama']        = post_str('nama');
    $form['category_id'] = post_str('category_id');
    $form['deskripsi']   = post_str('deskripsi');
    $form['harga']       = post_str('harga');
    $form['stok']        = post_str('stok');
    $form['is_active']   = isset($_POST['is_active']) ? 1 : 0;

    $nameLen = mb_strlen($form['nama']);
    if ($nameLen < 3 || $nameLen > 150) {
        $errors[] = 'Nama produk harus 3-150 karakter.';
    }
    $catId = int_or_null($form['category_id']);
    if ($catId === null || !in_array($catId, $catIds, true)) {
        $errors[] = 'Pilih kategori yang valid.';
    }
    if (mb_strlen($form['deskripsi']) > 2000) {
        $errors[] = 'Deskripsi maksimal 2000 karakter.';
    }
    $harga = int_or_null(preg_replace('/[.\s,]/', '', $form['harga']) ?? '', 100, 1000000000);
    if ($harga === null) {
        $errors[] = 'Harga harus berupa angka antara Rp 100 dan Rp 1.000.000.000.';
    }
    $stok = int_or_null($form['stok'], 0, 100000);
    if ($stok === null) {
        $errors[] = 'Stok harus berupa angka antara 0 dan 100.000.';
    }

    $newImage = null;
    if ($errors === []) { // proses gambar hanya jika data lain sudah valid (hindari file yatim)
        [$newImage, $imgErr] = save_product_image($_FILES['gambar'] ?? []);
        if ($imgErr !== null) {
            $errors[] = $imgErr;
        }
    }

    if ($errors === []) {
        if ($product === null) {
            db_insert(
                'INSERT INTO products (seller_id, category_id, nama, deskripsi, harga, stok, gambar, is_active)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                'iissdisi',
                [$sid, $catId, $form['nama'], $form['deskripsi'] === '' ? null : $form['deskripsi'], $harga, $stok, $newImage, $form['is_active']]
            );
            flash('success', 'Produk berhasil ditambahkan.');
        } else {
            $gambar = $newImage ?? $product['gambar'];
            db_exec(
                'UPDATE products SET category_id = ?, nama = ?, deskripsi = ?, harga = ?, stok = ?, gambar = ?, is_active = ?
                 WHERE id = ? AND seller_id = ?',
                'issdisiii',
                [$catId, $form['nama'], $form['deskripsi'] === '' ? null : $form['deskripsi'], $harga, $stok, $gambar, $form['is_active'], $id, $sid]
            );
            if ($newImage !== null) {
                delete_product_image($product['gambar']); // hapus gambar lama setelah update sukses
            }
            flash('success', 'Produk berhasil diperbarui.');
        }
        redirect('seller/products.php');
    }
}

$isEdit = $product !== null;
render_header($isEdit ? 'Edit Produk' : 'Tambah Produk');
?>
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= e(url('seller/products.php')) ?>">Produk Saya</a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= $isEdit ? 'Edit' : 'Tambah' ?></li>
    </ol>
</nav>
<h1 class="h3 mb-3"><?= $isEdit ? 'Edit Produk' : 'Tambah Produk' ?></h1>

<?php if ($errors !== []): ?>
    <div class="alert alert-danger">
        <ul class="mb-0 ps-3"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <form method="post" enctype="multipart/form-data" novalidate
              action="<?= e(url('seller/product_form.php' . ($isEdit ? '?id=' . (int) $id : ''))) ?>">
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-md-8">
                    <label for="nama" class="form-label">Nama produk</label>
                    <input type="text" class="form-control" id="nama" name="nama" maxlength="150" value="<?= e($form['nama']) ?>" required>
                </div>
                <div class="col-md-4">
                    <label for="category_id" class="form-label">Kategori</label>
                    <select class="form-select" id="category_id" name="category_id" required>
                        <option value="">-- Pilih --</option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= (int) $c['id'] ?>" <?= $form['category_id'] === (string) $c['id'] ? 'selected' : '' ?>><?= e($c['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label for="deskripsi" class="form-label">Deskripsi</label>
                    <textarea class="form-control" id="deskripsi" name="deskripsi" rows="5" maxlength="2000"><?= e($form['deskripsi']) ?></textarea>
                </div>
                <div class="col-md-4">
                    <label for="harga" class="form-label">Harga (Rp)</label>
                    <input type="text" inputmode="numeric" class="form-control" id="harga" name="harga" maxlength="14" value="<?= e($form['harga']) ?>" required>
                </div>
                <div class="col-md-4">
                    <label for="stok" class="form-label">Stok</label>
                    <input type="number" class="form-control" id="stok" name="stok" min="0" max="100000" value="<?= e($form['stok']) ?>" required>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" <?= (int) $form['is_active'] === 1 ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_active">Tampilkan di etalase (aktif)</label>
                    </div>
                </div>
                <div class="col-12">
                    <label for="gambar" class="form-label">Gambar produk <?= $isEdit ? '(kosongkan jika tidak diganti)' : '(opsional)' ?></label>
                    <input type="file" class="form-control" id="gambar" name="gambar" accept="image/jpeg,image/png,image/webp">
                    <div class="form-text">JPG/PNG/WebP, maksimal 2 MB. Gambar diproses ulang oleh server demi keamanan.</div>
                    <?php if ($isEdit && !empty($product['gambar'])): ?>
                        <img class="cart-thumb rounded mt-2" src="<?= e(product_image_url($product['gambar'])) ?>" alt="Gambar saat ini">
                    <?php endif; ?>
                </div>
            </div>
            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Simpan Perubahan' : 'Simpan Produk' ?></button>
                <a class="btn btn-outline-secondary" href="<?= e(url('seller/products.php')) ?>">Batal</a>
            </div>
        </form>
    </div>
</div>
<?php render_footer(); ?>
