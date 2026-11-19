<?php
/**
 * Logika transaksi pemesanan.
 *
 * place_order() berjalan dalam SATU transaksi database (ACID):
 *   BEGIN -> kunci baris produk (SELECT ... FOR UPDATE) -> validasi stok & harga
 *   dari database -> INSERT orders & order_items -> kurangi stok -> kosongkan
 *   keranjang -> COMMIT. Kegagalan di langkah mana pun -> ROLLBACK penuh.
 * Harga SELALU diambil dari tabel products, tidak pernah dari input pengguna.
 */
declare(strict_types=1);
if (!defined('APP_BOOT')) { http_response_code(403); exit; }

/** Error yang pesannya aman ditampilkan ke pengguna. */
final class OrderException extends RuntimeException
{
}

/**
 * @param array{nama_penerima:string, telepon:string, alamat:string, catatan:string} $data
 * @return int ID pesanan baru
 */
function place_order(int $buyerId, array $data): int
{
    $db = db();
    $db->begin_transaction();
    try {
        $cart = db_all('SELECT product_id, qty FROM cart_items WHERE user_id = ? ORDER BY product_id', 'i', [$buyerId]);
        if ($cart === []) {
            throw new OrderException('Keranjang Anda kosong.');
        }

        $ids = array_map(static fn(array $r): int => (int) $r['product_id'], $cart);
        $in  = implode(',', array_fill(0, count($ids), '?'));
        // Urut berdasarkan id + FOR UPDATE: mencegah overselling & deadlock antar-transaksi
        $rows = db_all(
            "SELECT id, seller_id, nama, harga, stok, is_active FROM products WHERE id IN ($in) ORDER BY id FOR UPDATE",
            str_repeat('i', count($ids)),
            $ids
        );
        $products = [];
        foreach ($rows as $r) {
            $products[(int) $r['id']] = $r;
        }

        $total = 0.0;
        $lines = [];
        foreach ($cart as $c) {
            $pid = (int) $c['product_id'];
            $qty = (int) $c['qty'];
            $p   = $products[$pid] ?? null;
            if ($p === null || (int) $p['is_active'] !== 1) {
                throw new OrderException('Salah satu produk di keranjang sudah tidak tersedia. Silakan periksa keranjang Anda.');
            }
            if ($qty < 1 || $qty > (int) $p['stok']) {
                throw new OrderException('Stok "' . $p['nama'] . '" tidak mencukupi (tersisa ' . (int) $p['stok'] . ' unit).');
            }
            $harga    = (float) $p['harga'];
            $subtotal = $harga * $qty;
            $total   += $subtotal;
            $lines[]  = [
                'product_id' => $pid,
                'seller_id'  => (int) $p['seller_id'],
                'nama'       => (string) $p['nama'],
                'harga'      => $harga,
                'qty'        => $qty,
                'subtotal'   => $subtotal,
            ];
        }

        $orderId = 0;
        for ($try = 0; $try < 5 && $orderId === 0; $try++) {
            $kode = 'INV' . date('Ymd') . strtoupper(bin2hex(random_bytes(3)));
            try {
                $orderId = db_insert(
                    'INSERT INTO orders (kode_pesanan, buyer_id, total, status, nama_penerima, telepon_penerima, alamat_pengiriman, catatan)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                    'sidsssss',
                    [$kode, $buyerId, $total, 'menunggu_pembayaran',
                     $data['nama_penerima'], $data['telepon'], $data['alamat'],
                     $data['catatan'] === '' ? null : $data['catatan']]
                );
            } catch (mysqli_sql_exception $ex) {
                if ((int) $ex->getCode() !== 1062) { // 1062 = kode pesanan kembar -> coba kode lain
                    throw $ex;
                }
            }
        }
        if ($orderId === 0) {
            throw new RuntimeException('Gagal membuat kode pesanan unik.');
        }

        foreach ($lines as $l) {
            db_insert(
                'INSERT INTO order_items (order_id, product_id, seller_id, nama_produk, harga_satuan, qty, subtotal)
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                'iiisdid',
                [$orderId, $l['product_id'], $l['seller_id'], $l['nama'], $l['harga'], $l['qty'], $l['subtotal']]
            );
            $affected = db_exec(
                'UPDATE products SET stok = stok - ? WHERE id = ? AND stok >= ?',
                'iii',
                [$l['qty'], $l['product_id'], $l['qty']]
            );
            if ($affected !== 1) {
                throw new OrderException('Stok "' . $l['nama'] . '" baru saja berubah. Silakan periksa keranjang Anda.');
            }
        }

        db_exec('DELETE FROM cart_items WHERE user_id = ?', 'i', [$buyerId]);
        $db->commit();
        return $orderId;
    } catch (Throwable $ex) {
        $db->rollback();
        throw $ex;
    }
}

/** Pembeli membatalkan pesanannya sendiri selama masih "menunggu pembayaran"; stok dikembalikan. */
function cancel_order(int $orderId, int $buyerId): void
{
    $db = db();
    $db->begin_transaction();
    try {
        $order = db_one('SELECT id, status FROM orders WHERE id = ? AND buyer_id = ? FOR UPDATE', 'ii', [$orderId, $buyerId]);
        if ($order === null) {
            throw new OrderException('Pesanan tidak ditemukan.');
        }
        if ($order['status'] !== 'menunggu_pembayaran') {
            throw new OrderException('Pesanan ini tidak dapat dibatalkan lagi.');
        }
        $items = db_all(
            'SELECT product_id, qty FROM order_items WHERE order_id = ? AND product_id IS NOT NULL ORDER BY product_id',
            'i',
            [$orderId]
        );
        foreach ($items as $it) {
            db_exec('UPDATE products SET stok = stok + ? WHERE id = ?', 'ii', [(int) $it['qty'], (int) $it['product_id']]);
        }
        db_exec("UPDATE orders SET status = 'dibatalkan' WHERE id = ?", 'i', [$orderId]);
        $db->commit();
    } catch (Throwable $ex) {
        $db->rollback();
        throw $ex;
    }
}
