# TokoKita — Web Marketplace Sederhana

Aplikasi marketplace sederhana: **penjual** menawarkan produk, **pembeli** memilih produk, memasukkannya ke keranjang, lalu membuat pesanan yang tersimpan di database.

Dibangun dengan **PHP Native 8.x**, **MySQLi** (prepared statement), dan **Bootstrap 5**.

## Fitur

- Daftar produk (pencarian, filter kategori, pagination) dan detail produk
- Registrasi & login dengan dua peran: pembeli dan penjual
- Penjual: CRUD produk (dengan unggah gambar) dan melihat pesanan masuk
- Pembeli: keranjang belanja, checkout, riwayat & pembatalan pesanan
- Transaksi pemesanan memakai transaksi database (ACID) dengan penguncian stok

## Keamanan

CSRF token global + cek same-origin, prepared statement (anti SQL injection), escape output + CSP (anti XSS), bcrypt cost 12, throttle brute-force, sesi aman (HttpOnly, SameSite, regenerasi ID, timeout), RBAC + cek kepemilikan (anti IDOR), unggahan gambar di-re-encode, header keamanan lengkap, SRI untuk CDN, folder privat diblokir `.htaccess`, dan tidak ada kredensial di repository. Rincian ada di [`docs/PERANCANGAN.md`](docs/PERANCANGAN.md).

## Menjalankan secara lokal (XAMPP)

1. Salin folder ini ke `htdocs/marketplace`, jalankan **Apache** dan **MySQL**.
2. Di phpMyAdmin buat database `marketplace`, lalu **Import** `database/schema.sql`.
3. Salin `config/config.sample.php` menjadi `config/config.php` dan isi kredensial database.
4. Buka `http://localhost/marketplace/`. Daftar satu akun **penjual** (tambah beberapa produk) dan satu akun **pembeli** (belanja).

Tidak ada akun bawaan, sehingga tidak ada password default yang bisa ditebak.

## Deployment ke InfinityFree

1. Buat akun hosting dan database MySQL di panel InfinityFree.
2. Unggah **isi** folder proyek (bukan folder pembungkusnya) ke `htdocs/` lewat File Manager atau FTP. Jangan unggah folder `.git`.
3. Impor `database/schema.sql` lewat phpMyAdmin pada database tersebut.
4. Buat `config/config.php` **langsung di server** dari `config.sample.php`: isi host (mis. `sqlXXX.infinityfree.com`), user, password, dan nama database dari panel. Pastikan `debug` = `false`.
5. Setelah SSL aktif, ubah `force_https` menjadi `true`.
6. Pastikan folder `uploads/products/` dan `storage/logs/` dapat ditulis.

## Upload ke GitHub

```bash
git init
git add .
git status                      # PASTIKAN config/config.php TIDAK ada di daftar
git commit -m "Web Marketplace TokoKita"
git branch -M main
git remote add origin https://github.com/<username>/<repo>.git
git push -u origin main
```

Periksa dengan `git ls-files | grep -i config`: hanya `config/config.sample.php` dan `config/.htaccess` yang boleh muncul. Jika password pernah ter-commit, **ganti password database** (menghapus commit saja tidak cukup).

## Pemecahan masalah

- **Tampilan tanpa gaya Bootstrap:** buka konsol browser. Jika ada galat *integrity*, perbarui atribut `integrity` di `includes/layout.php` sesuai hash resmi Bootstrap 5.3.3 (getbootstrap.com), atau hapus atribut tersebut. Jika hosting menyisipkan skrip tambahan, ubah daftar sumber CSP di `includes/security.php`.
- **Gambar produk tidak tampil / error 500 di folder uploads:** hapus baris `RemoveHandler`, `RemoveType`, dan `Options` pada `uploads/.htaccess` bila hosting tidak mengizinkannya (keamanan tetap terjaga karena hanya file `.jpg` acak yang dilayani dan semua unggahan di-re-encode).
- **Halaman 500:** lihat `storage/logs/php-error.log`; atau ubah `debug` ke `true` sementara di lingkungan lokal saja.

## Status pengujian

Sudah diuji otomatis: lint seluruh file PHP, header keamanan, penolakan POST tanpa CSRF (419) dan beda origin (403), penanganan galat tanpa kebocoran detail, serta unggahan file berbahaya (skrip menyamar jadi gambar ditolak, payload pada JPEG hilang setelah re-encode). Alur yang memakai database penuh (checkout, CRUD, dsb.) perlu diuji di XAMPP/hosting; skenario ujinya ada di `docs/PERANCANGAN.md` (T01–T13).

## Struktur

Lihat bagian *Teknologi dan Struktur Proyek* di [`docs/PERANCANGAN.md`](docs/PERANCANGAN.md).
