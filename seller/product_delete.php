<?php
/** CRUD Produk - Delete. Hanya POST (+CSRF otomatis) dan hanya untuk produk milik penjual sendiri. */
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';

$user = require_role('penjual');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    redirect('seller/products.php');
}

$id = post_int('id');
if ($id !== null) {
    $p = db_one('SELECT id, gambar FROM products WHERE id = ? AND seller_id = ?', 'ii', [$id, (int) $user['id']]);
    if ($p !== null) {
        // order_items.product_id otomatis SET NULL; riwayat pesanan tetap utuh lewat kolom snapshot
        db_exec('DELETE FROM products WHERE id = ? AND seller_id = ?', 'ii', [$id, (int) $user['id']]);
        delete_product_image($p['gambar']);
        flash('success', 'Produk berhasil dihapus.');
        redirect('seller/products.php');
    }
}
flash('danger', 'Produk tidak ditemukan.');
redirect('seller/products.php');
