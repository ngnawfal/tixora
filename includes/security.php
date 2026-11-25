<?php
// ============================================================
// TIXORA — Modul Keamanan Inti
// Hardening session, security headers, CSRF, XSS escaping,
// rate limiting login, dan token E-Ticket berbasis HMAC.
// ============================================================

function sendSecurityHeaders(): void
{
    if (headers_sent()) {
        return;
    }
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
}

function initSecureSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $isHttps = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (($_SERVER['SERVER_PORT'] ?? null) == 443)
    );

    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

/**
 * Escaping HTML aman untuk mencegah XSS.
 */
function e(?string $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Menghasilkan atau mengambil token CSRF per sesi.
 */
function csrfToken(): string
{
    initSecureSession();
    if (empty($_SESSION['_csrf_token']) || !is_string($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

/**
 * Render input hidden CSRF untuk semua form POST.
 */
function csrfField(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrfToken()) . '">';
}

/**
 * Verifikasi token CSRF dari permintaan POST.
 */
function verifyCsrf(?string $token = null): bool
{
    initSecureSession();
    $submitted = $token ?? ($_POST['_csrf'] ?? '');
    $expected  = $_SESSION['_csrf_token'] ?? '';

    if (!is_string($submitted) || !is_string($expected) || $submitted === '' || $expected === '') {
        return false;
    }

    return hash_equals($expected, $submitted);
}

/**
 * Menyimpan pesan flash di session (menghindari reflected XSS lewat URL).
 */
function setFlash(string $message, string $type = 'success'): void
{
    initSecureSession();
    $allowed = ['success', 'danger', 'warning', 'info'];
    $_SESSION['_flash'] = [
        'message' => $message,
        'type'    => in_array($type, $allowed, true) ? $type : 'info',
    ];
}

/**
 * Mengambil & menghapus pesan flash (atau membaca ?pesan secara aman).
 */
function getFlash(): ?array
{
    initSecureSession();
    if (!empty($_SESSION['_flash'])) {
        $flash = $_SESSION['_flash'];
        unset($_SESSION['_flash']);
        return $flash;
    }
    if (isset($_GET['pesan']) && trim((string) $_GET['pesan']) !== '') {
        $allowed = ['success', 'danger', 'warning', 'info'];
        $type = $_GET['tipe'] ?? 'success';
        return [
            'message' => trim((string) $_GET['pesan']),
            'type'    => in_array($type, $allowed, true) ? $type : 'success',
        ];
    }
    return null;
}

/**
 * Rate limiting sederhana berbasis sesi untuk endpoint login.
 * Maksimal 5 percobaan gagal per 5 menit.
 */
function checkLoginRateLimit(): array
{
    initSecureSession();
    $window = 300; // 5 menit
    $maxAttempts = 5;
    $now = time();

    $attempts = $_SESSION['_login_attempts'] ?? [];
    $attempts = array_values(array_filter($attempts, fn($ts) => ($now - (int) $ts) < $window));
    $_SESSION['_login_attempts'] = $attempts;

    if (count($attempts) >= $maxAttempts) {
        $retryAfter = $window - ($now - (int) $attempts[0]);
        return ['allowed' => false, 'retry_after' => max(1, $retryAfter)];
    }

    return ['allowed' => true, 'retry_after' => 0];
}

function recordFailedLogin(): void
{
    initSecureSession();
    $_SESSION['_login_attempts'][] = time();
}

function clearFailedLogins(): void
{
    initSecureSession();
    unset($_SESSION['_login_attempts']);
}

/**
 * Generate token e-ticket kriptografis berbasis HMAC-SHA256 (tidak bisa ditebak).
 */
function generateTicketToken(int $orderId, int $userId): string
{
    $secret = env('APP_KEY', 'tixora-fallback-change-me-in-env');
    $nonce  = bin2hex(random_bytes(4));
    $sig    = strtoupper(substr(hash_hmac('sha256', "{$orderId}|{$userId}|{$nonce}", $secret), 0, 12));
    return sprintf('TXR-%04d-%s-%s', $orderId, strtoupper($nonce), $sig);
}

/**
 * Generate kode pesanan unik.
 */
function generateOrderCode(): string
{
    return 'TXR-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
}

/**
 * Validasi format nomor HP Indonesia / internasional.
 */
function isValidPhone(string $phone): bool
{
    return (bool) preg_match('/^[0-9+\-\s()]{8,20}$/', $phone);
}

/**
 * Validasi format username (alfanumerik & underscore, 3-30 karakter).
 */
function isValidUsername(string $username): bool
{
    return (bool) preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username);
}
