<?php

/**
 * Template konfigurasi SMTP Email — salin menjadi `mail.credentials.local.php`
 * dan isi dengan kredensial SMTP hosting Anda.
 * File `*.local.php` otomatis di-ignore oleh .gitignore.
 */
return [
    'host'       => 'iferror.web.id',
    'port'       => 465,
    'encryption' => 'ssl', // 'ssl' (port 465) atau 'tls' (port 587)
    'username'   => 'noreply@iferror.web.id',
    'password'   => 'GANTI_PASSWORD_EMAIL',
    'from_email' => 'noreply@iferror.web.id',
    'from_name'  => 'Ingetttt Coyyyy!',
];
