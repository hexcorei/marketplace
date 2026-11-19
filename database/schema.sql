-- =====================================================================
--  Skema Database Web Marketplace (TokoKita)
--  Engine  : InnoDB (mendukung transaksi & foreign key)
--  Charset : utf8mb4
--
--  CARA IMPOR
--  - XAMPP        : buat database "marketplace" di phpMyAdmin, pilih database
--                   tersebut, lalu tab Import -> pilih file ini.
--  - InfinityFree : buat database lewat panel (MySQL Databases), buka
--                   phpMyAdmin, pilih database tersebut, lalu Import file ini.
--  Skrip ini aman dijalankan ulang (CREATE TABLE IF NOT EXISTS) dan tidak
--  menghapus data yang sudah ada. Tidak ada akun bawaan: buat akun lewat
--  halaman Daftar agar tidak ada kredensial default yang bisa ditebak.
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- 1. users : pengguna (pembeli & penjual)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nama          VARCHAR(100) NOT NULL,
  email         VARCHAR(150) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,              -- hasil password_hash(), bukan password asli
  role          ENUM('pembeli','penjual') NOT NULL DEFAULT 'pembeli',
  telepon       VARCHAR(20)  NULL,
  alamat        TEXT         NULL,
  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2. categories : kategori produk
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS categories (
  id    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nama  VARCHAR(60)  NOT NULL,
  slug  VARCHAR(80)  NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_categories_nama (nama),
  UNIQUE KEY uq_categories_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 3. products : produk yang dijual penjual
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS products (
  id          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  seller_id   INT UNSIGNED  NOT NULL,
  category_id INT UNSIGNED  NOT NULL,
  nama        VARCHAR(150)  NOT NULL,
  deskripsi   TEXT          NULL,
  harga       DECIMAL(12,2) NOT NULL,
  stok        INT UNSIGNED  NOT NULL DEFAULT 0,
  gambar      VARCHAR(64)   NULL,                    -- nama file acak hasil unggah
  is_active   TINYINT(1)    NOT NULL DEFAULT 1,
  created_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_products_seller   (seller_id),
  KEY idx_products_category (category_id),
  KEY idx_products_active_created (is_active, created_at),
  CONSTRAINT chk_products_harga CHECK (harga >= 0),
  CONSTRAINT fk_products_seller
    FOREIGN KEY (seller_id)   REFERENCES users(id)      ON DELETE CASCADE  ON UPDATE CASCADE,
  CONSTRAINT fk_products_category
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 4. cart_items : isi keranjang belanja per pembeli
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS cart_items (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id    INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  qty        INT UNSIGNED NOT NULL DEFAULT 1,
  created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cart_user_product (user_id, product_id),  -- satu baris per produk per pembeli
  KEY idx_cart_product (product_id),
  CONSTRAINT chk_cart_qty CHECK (qty >= 1),
  CONSTRAINT fk_cart_user
    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_cart_product
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 5. orders : header transaksi pemesanan
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS orders (
  id                INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  kode_pesanan      VARCHAR(25)   NOT NULL,
  buyer_id          INT UNSIGNED  NOT NULL,
  total             DECIMAL(14,2) NOT NULL,
  status            ENUM('menunggu_pembayaran','diproses','dikirim','selesai','dibatalkan')
                    NOT NULL DEFAULT 'menunggu_pembayaran',
  nama_penerima     VARCHAR(100)  NOT NULL,
  telepon_penerima  VARCHAR(20)   NOT NULL,
  alamat_pengiriman TEXT          NOT NULL,
  catatan           VARCHAR(255)  NULL,
  created_at        TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_orders_kode (kode_pesanan),
  KEY idx_orders_buyer (buyer_id, created_at),
  CONSTRAINT chk_orders_total CHECK (total >= 0),
  CONSTRAINT fk_orders_buyer
    FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 6. order_items : rincian barang pada pesanan
--    nama_produk & harga_satuan disalin (snapshot) agar riwayat pesanan
--    tetap benar walau produk kemudian diubah atau dihapus penjual.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS order_items (
  id           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  order_id     INT UNSIGNED  NOT NULL,
  product_id   INT UNSIGNED  NULL,
  seller_id    INT UNSIGNED  NOT NULL,
  nama_produk  VARCHAR(150)  NOT NULL,
  harga_satuan DECIMAL(12,2) NOT NULL,
  qty          INT UNSIGNED  NOT NULL,
  subtotal     DECIMAL(14,2) NOT NULL,
  PRIMARY KEY (id),
  KEY idx_oi_order   (order_id),
  KEY idx_oi_product (product_id),
  KEY idx_oi_seller  (seller_id),
  CONSTRAINT chk_oi_qty CHECK (qty >= 1),
  CONSTRAINT fk_oi_order
    FOREIGN KEY (order_id)   REFERENCES orders(id)   ON DELETE CASCADE  ON UPDATE CASCADE,
  CONSTRAINT fk_oi_product
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_oi_seller
    FOREIGN KEY (seller_id)  REFERENCES users(id)    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 7. login_attempts : pencatat percobaan masuk/daftar (anti brute-force)
--    Tabel keamanan, sengaja tanpa foreign key.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS login_attempts (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  aksi         VARCHAR(10)  NOT NULL DEFAULT 'login',
  email        VARCHAR(150) NOT NULL,
  ip_address   VARCHAR(45)  NOT NULL,
  attempted_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_la_email (aksi, email, attempted_at),
  KEY idx_la_ip    (aksi, ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Data awal kategori
-- ---------------------------------------------------------------------
INSERT IGNORE INTO categories (nama, slug) VALUES
  ('Elektronik',            'elektronik'),
  ('Fashion Pria',          'fashion-pria'),
  ('Fashion Wanita',        'fashion-wanita'),
  ('Makanan & Minuman',     'makanan-minuman'),
  ('Rumah Tangga',          'rumah-tangga'),
  ('Kesehatan & Kecantikan','kesehatan-kecantikan'),
  ('Hobi & Olahraga',       'hobi-olahraga'),
  ('Buku & Alat Tulis',     'buku-alat-tulis'),
  ('Lainnya',               'lainnya');
