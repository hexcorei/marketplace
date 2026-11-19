<?php
/** Logout hanya lewat POST + token CSRF (otomatis diverifikasi bootstrap) agar tidak bisa dipicu dari link/gambar pihak lain. */
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    redirect('index.php');
}

auth_logout();
start_secure_session();          // sesi baru (ID baru) untuk menampilkan pesan
flash('success', 'Anda telah keluar.');
redirect('login.php');
