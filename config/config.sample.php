<?php
/**
 * CONTOH KONFIGURASI
 *
 * 1. Salin file ini menjadi  config/config.php
 * 2. Isi kredensial database yang sebenarnya.
 *
 * config/config.php sudah masuk .gitignore -> JANGAN pernah di-commit ke GitHub.
 * Folder config/ juga diblokir dari akses web lewat config/.htaccess.
 */
return [
    'db' => [
        'host' => 'localhost',          // InfinityFree: mis. sql123.infinityfree.com
        'user' => 'root',               // InfinityFree: mis. if0_12345678
        'pass' => '',                   // isi dengan password database
        'name' => 'marketplace',        // InfinityFree: mis. if0_12345678_marketplace
        'port' => 3306,
        'timezone' => '+07:00',         // zona waktu sesi MySQL (WIB)
    ],

    'app' => [
        'name'        => 'TokoKita',
        'debug'       => false,         // WAJIB false saat online
        'force_https' => false,         // ubah ke true setelah SSL aktif di hosting
        'base_path'   => null,          // null = deteksi otomatis; contoh manual: '/marketplace'
        'timezone'    => 'Asia/Jakarta',
    ],

    'security' => [
        'session_idle_timeout'     => 1800,   // detik tanpa aktivitas -> sesi berakhir (30 menit)
        'session_absolute_timeout' => 28800,  // masa hidup maksimum sesi (8 jam)
        'min_password_length'      => 10,
        'max_login_attempts'       => 5,      // gagal login per email dalam jendela waktu
        'max_login_attempts_ip'    => 30,     // gagal login per IP dalam jendela waktu
        'max_register_per_ip'      => 10,     // percobaan daftar per IP dalam jendela waktu
        'lockout_seconds'          => 900,    // jendela/penguncian (15 menit)
    ],

    'upload' => [
        'max_bytes'     => 2097152,  // 2 MB
        'max_dimension' => 1200,     // sisi terpanjang gambar hasil simpan (px)
    ],
];
