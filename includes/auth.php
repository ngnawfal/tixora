<?php
// ============================================================
// TIXORA — Modul Autentikasi & Otorisasi
// ============================================================

require_once __DIR__ . '/security.php';

function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']) && (int) $_SESSION['user_id'] > 0;
}

function isAdmin(): bool
{
    return isLoggedIn() && (($_SESSION['role'] ?? '') === 'admin');
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        setFlash('Silakan masuk terlebih dahulu untuk melanjutkan.', 'warning');
        header('Location: ' . BASE_URL . 'auth/login.php');
        exit;
    }
}

function requireAdmin(): void
{
    if (!isAdmin()) {
        setFlash('Akses ditolak. Halaman ini khusus administrator.', 'danger');
        header('Location: ' . BASE_URL . 'index.php');
        exit;
    }
}

/**
 * Login dengan prepared statement, password_verify, dan session_regenerate_id.
 */
function login(string $username, string $password): bool
{
    global $koneksi;

    $username = trim($username);
    if ($username === '' || $password === '') {
        return false;
    }

    $stmt = $koneksi->prepare(
        'SELECT id_user, username, password_hash, email, full_name, phone, role
         FROM users
         WHERE username = ? OR email = ?
         LIMIT 1'
    );
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('ss', $username, $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows === 1) {
        $user = $result->fetch_assoc();
        $stmt->close();

        if (password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            clearFailedLogins();

            $_SESSION['user_id']      = (int) $user['id_user'];
            $_SESSION['username']     = $user['username'];
            $_SESSION['email']        = $user['email'];
            $_SESSION['nama_lengkap'] = $user['full_name'];
            $_SESSION['no_hp']        = $user['phone'] ?? '';
            $_SESSION['role']         = $user['role'];

            // Rotasi token CSRF setelah eskalasi/autentikasi
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
            return true;
        }
    } else {
        $stmt->close();
    }

    recordFailedLogin();
    return false;
}

/**
 * Logout penuh: bersihkan array session, expire cookie, dan hancurkan sesi.
 */
function logout(): void
{
    initSecureSession();
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
    header('Location: ' . BASE_URL . 'auth/login.php');
    exit;
}

/**
 * Registrasi pengguna baru dengan validasi input dan hashing Argon2id / Bcrypt.
 */
function register(string $username, string $password, string $email, string $fullName, string $phone = ''): array
{
    global $koneksi;

    $username = trim($username);
    $email    = strtolower(trim($email));
    $fullName = trim($fullName);
    $phone    = trim($phone);

    if ($fullName === '' || mb_strlen($fullName) < 2 || mb_strlen($fullName) > 100) {
        return ['ok' => false, 'error' => 'Nama lengkap harus antara 2 hingga 100 karakter.'];
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Format alamat email tidak valid.'];
    }
    if ($phone !== '' && !isValidPhone($phone)) {
        return ['ok' => false, 'error' => 'Format nomor HP tidak valid (8–20 digit/karakter telepon).'];
    }
    if (!isValidUsername($username)) {
        return ['ok' => false, 'error' => 'Username hanya boleh berisi huruf, angka, dan garis bawah (3–30 karakter).'];
    }
    if (strlen($password) < 8) {
        return ['ok' => false, 'error' => 'Password minimal 8 karakter untuk keamanan akun Anda.'];
    }

    // Cek duplikasi username / email
    $check = $koneksi->prepare('SELECT id_user FROM users WHERE username = ? OR email = ? LIMIT 1');
    $check->bind_param('ss', $username, $email);
    $check->execute();
    $exists = $check->get_result()->num_rows > 0;
    $check->close();

    if ($exists) {
        return ['ok' => false, 'error' => 'Username atau email sudah terdaftar. Gunakan data lain.'];
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $koneksi->prepare(
        "INSERT INTO users (username, password_hash, email, full_name, phone, role)
         VALUES (?, ?, ?, ?, ?, 'user')"
    );
    $stmt->bind_param('sssss', $username, $passwordHash, $email, $fullName, $phone);
    $ok = $stmt->execute();
    $stmt->close();

    if (!$ok) {
        return ['ok' => false, 'error' => 'Terjadi kesalahan saat membuat akun. Silakan coba lagi.'];
    }

    return ['ok' => true, 'error' => null];
}
