<?php
// ============================================================
// TIXORA — Konfigurasi Koneksi Database (MySQLi)
// Kredensial dibaca dari file .env agar tidak hardcoded di kode.
// ============================================================

require_once __DIR__ . '/env.php';

$dbHost = env('DB_HOST', '127.0.0.1');
$dbPort = (int) env('DB_PORT', '3306');
$dbUser = env('DB_USER', 'root');
$dbPass = env('DB_PASS', '') ?? '';
$dbName = env('DB_NAME', 'db_tixora');

mysqli_report(MYSQLI_REPORT_OFF);

$koneksi = @mysqli_connect($dbHost, $dbUser, $dbPass, $dbName, $dbPort);

if (!$koneksi) {
    error_log('[TIXORA DB ERROR] ' . mysqli_connect_error());
    http_response_code(503);
    exit(
        '<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><title>Layanan Database Tidak Tersedia — TIXORA</title>'
        . '<style>body{font-family:system-ui,sans-serif;background:#F7F6F2;color:#141413;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:1.5rem}'
        . '.box{max-width:460px;background:#fff;border:1px solid #E5E2D9;border-radius:16px;padding:2rem;text-align:center}'
        . 'h1{margin:0 0 .5rem;font-size:1.25rem}p{margin:0;color:#57554F;font-size:.9375rem;line-height:1.6}code{background:#F1EFE8;padding:.15rem .4rem;border-radius:4px}</style></head>'
        . '<body><div class="box"><h1>Koneksi Database Gagal</h1>'
        . '<p>Aplikasi TIXORA tidak dapat terhubung ke server MySQL/MariaDB. Pastikan database <code>db_tixora</code> sudah di-import dari <code>database/tixora.sql</code> dan konfigurasi <code>.env</code> sudah sesuai.</p>'
        . '</div></body></html>'
    );
}

mysqli_set_charset($koneksi, 'utf8mb4');
