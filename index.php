<?php
/** Halaman Daftar Produk (publik): pencarian, filter kategori, pagination. */
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$q       = mb_substr(get_str('q'), 0, 100);
$catId   = get_int('kategori');
$page    = get_int('hal', 1, 100000) ?? 1;
$perPage = 12;

$where  = ['p.is_active = 1'];
$types  = '';
$params = [];
if ($q !== '') {
    $like    = '%' . addcslashes($q, '%_\\') . '%'; // escape wildcard LIKE dari input pengguna
    $where[] = '(p.nama LIKE ? OR p.deskripsi LIKE ?)';
    $types  .= 'ss';
    array_push($params, $like, $like);
}
if ($catId !== null) {
    $where[] = 'p.category_id = ?';
    $types  .= 'i';
    $params[] = $catId;
}
$whereSql = implode(' AND ', $where);

$total  = (int) (db_one("SELECT COUNT(*) AS c FROM products p WHERE $whereSql", $types, $params)['c'] ?? 0);
$pages  = max(1, (int) ceil($total / $perPage));
$page   = min($page, $pages);
$offset = ($page - 1) * $perPage;

$products = db_all(
    "SELECT p.id, p.nama, p.harga, p.stok, p.gambar, c.nama AS kategori, u.nama AS penjual
     FROM products p
     JOIN categories c ON c.id = p.category_id
     JOIN users u ON u.id = p.seller_id
     WHERE $whereSql
     ORDER BY p.created_at DESC, p.id DESC
     LIMIT ? OFFSET ?",
    $types . 'ii',
    array_merge($params, [$perPage, $offset])
);
$categories = db_all('SELECT id, nama FROM categories ORDER BY nama');

function page_link(int $pg, string $q, ?int $catId): string
{
    $query = [];
    if ($q !== '')        { $query['q'] = $q; }
    if ($catId !== null)  { $query['kategori'] = $catId; }
    if ($pg > 1)          { $query['hal'] = $pg; }
    return url('index.php') . ($query ? '?' . http_build_query($query) : '');
}

render_header('Daftar Produk');
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <h1 class="h3 mb-0">Daftar Produk</h1>
    <span class="text-muted small"><?= (int) $total ?> produk ditemukan</span>
</div>

<form method="get" action="<?= e(url('index.php')) ?>" class="row g-2 mb-4">
    <div class="col-md-6">
        <input type="search" class="form-control" name="q" value="<?= e($q) ?>" maxlength="100"
               placeholder="Cari nama atau deskripsi produk..." aria-label="Cari produk">
    </div>
    <div class="col-md-4">
        <select name="kategori" class="form-select" aria-label="Kategori">
            <option value="">Semua kategori</option>
            <?php foreach ($categories as $c): ?>
                <option value="<?= (int) $c['id'] ?>" <?= $catId === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['nama']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2 d-grid">
        <button type="submit" class="btn btn-primary">Cari</button>
    </div>
</form>

<?php if ($products === []): ?>
    <div class="alert alert-info">Belum ada produk yang sesuai. Coba kata kunci atau kategori lain.</div>
<?php else: ?>
    <div class="row row-cols-2 row-cols-md-3 row-cols-lg-4 g-3">
        <?php foreach ($products as $p): ?>
            <div class="col">
                <div class="card h-100 product-card">
                    <a href="<?= e(url('produk.php?id=' . (int) $p['id'])) ?>">
                        <img class="card-img-top product-thumb" src="<?= e(product_image_url($p['gambar'])) ?>"
                             alt="<?= e($p['nama']) ?>" loading="lazy">
                    </a>
                    <div class="card-body d-flex flex-column">
                        <span class="badge text-bg-secondary align-self-start mb-2"><?= e($p['kategori']) ?></span>
                        <h2 class="h6 card-title"><?= e($p['nama']) ?></h2>
                        <div class="fw-bold text-primary"><?= e(rupiah($p['harga'])) ?></div>
                        <div class="small text-muted mb-2">Penjual: <?= e($p['penjual']) ?></div>
                        <div class="mt-auto">
                            <?php if ((int) $p['stok'] < 1): ?>
                                <span class="badge text-bg-danger mb-2">Stok habis</span>
                            <?php endif; ?>
                            <a class="btn btn-outline-primary btn-sm w-100"
                               href="<?= e(url('produk.php?id=' . (int) $p['id'])) ?>">Lihat Detail</a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($pages > 1): ?>
        <nav class="mt-4" aria-label="Navigasi halaman">
            <ul class="pagination justify-content-center flex-wrap">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= e(page_link(max(1, $page - 1), $q, $catId)) ?>">&laquo;</a>
                </li>
                <?php for ($i = 1; $i <= $pages; $i++): ?>
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                        <a class="page-link" href="<?= e(page_link($i, $q, $catId)) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= e(page_link(min($pages, $page + 1), $q, $catId)) ?>">&raquo;</a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
<?php endif; ?>
<?php render_footer(); ?>
