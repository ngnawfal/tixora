<?php
// ============================================================
// TIXORA — Bootstrap Aplikasi
// Memuat env, security hardening, koneksi DB, dan fungsi inti.
// ============================================================

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/security.php';

sendSecurityHeaders();
initSecureSession();

define('APP_NAME', env('APP_NAME', 'TIXORA'));
define('APP_TAGLINE', 'Sistem Manajemen Tiket Konser');
define('ROOT_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);

// Tentukan BASE_URL secara aman (tahan Host Header Injection)
$configuredUrl = env('APP_URL');
if ($configuredUrl) {
    define('BASE_URL', rtrim($configuredUrl, '/') . '/');
} else {
    $protocol = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (($_SERVER['SERVER_PORT'] ?? null) == 443)
    ) ? 'https' : 'http';

    $rawHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $safeHost = preg_match('/^[a-zA-Z0-9.-]+(:\d{1,5})?$/', $rawHost) ? $rawHost : 'localhost';

    $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']) ?: '') : '';
    $appRoot = str_replace('\\', '/', realpath(dirname(__DIR__)) ?: dirname(__DIR__));

    if ($docRoot !== '' && str_starts_with(strtolower($appRoot), strtolower($docRoot))) {
        $rel = trim(substr($appRoot, strlen($docRoot)), '/');
        $basePath = $rel === '' ? '/' : '/' . $rel . '/';
    } else {
        $basePath = '/';
    }

    define('BASE_URL', $protocol . '://' . $safeHost . $basePath);
}

define('UPLOADS_PATH', ROOT_PATH . 'assets' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR);
define('UPLOADS_URL', BASE_URL . 'assets/uploads/');

// Muat koneksi DB dan fungsi inti
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// Auto-cancel pesanan pending > 24 jam, di-throttle maksimal 1x per 5 menit per sesi
// agar tidak menjalankan write query pada setiap HTTP GET request.
$lastSweep = $_SESSION['_last_order_sweep'] ?? 0;
if (isset($koneksi) && (time() - (int) $lastSweep) >= 300) {
    $_SESSION['_last_order_sweep'] = time();
    @$koneksi->query(
        "UPDATE orders
         SET payment_status = 'expired', updated_at = NOW()
         WHERE payment_status = 'pending'
           AND created_at < DATE_SUB(NOW(), INTERVAL 1 DAY)"
    );
}
