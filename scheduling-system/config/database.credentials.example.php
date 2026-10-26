<?php

/**
 * Contoh konfigurasi database.
 *
 * Salin file ini menjadi `database.credentials.php` di folder yang sama,
 * lalu isi sesuai server. File hasil salinan TIDAK di-commit ke Git.
 *
 * Alternatif tanpa file: set environment variable DB_HOST, DB_NAME,
 * DB_USER, DB_PASS.
 */

return [
    'host'     => 'localhost',
    'dbname'   => 'nama_database',
    'username' => 'user_database',
    'password' => 'password_database',
    'charset'  => 'utf8mb4',
];
