<?php
return [
    // Fallback konfigurasi Muse dari .env. Nilai di menu Admin (tabel settings) selalu diutamakan.
    // Dibaca via config() — bukan env() langsung — agar tetap jalan setelah `php artisan config:cache`.
    'muse' => [
        'base_url' => env('MUSE_BASE_URL', ''),
        'token' => env('MUSE_API_TOKEN'),
        'webhook_secret' => env('MUSE_WEBHOOK_SECRET'),
        'timeout' => (int) env('MUSE_TIMEOUT', 15),
    ],
];
