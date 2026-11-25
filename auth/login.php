<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';

if (isLoggedIn()) {
    header('Location: ' . BASE_URL . (isAdmin() ? 'admin/' : 'index.php'));
    exit;
}

$pageTitle = 'Masuk';
$error     = '';
$flash     = getFlash();
$oldUser   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $oldUser = trim((string) ($_POST['username'] ?? ''));

    if (!verifyCsrf()) {
        $error = 'Sesi formulir tidak valid atau kedaluwarsa. Muat ulang halaman dan coba lagi.';
    } else {
        $rate = checkLoginRateLimit();
        if (!$rate['allowed']) {
            $error = 'Terlalu banyak percobaan login gagal. Coba kembali dalam ' . (int) $rate['retry_after'] . ' detik.';
        } else {
            $password = (string) ($_POST['password'] ?? '');
            if (login($oldUser, $password)) {
                header('Location: ' . BASE_URL . (isAdmin() ? 'admin/' : 'index.php'));
                exit;
            }
            $error = 'Username/email atau password salah.';
        }
    }
}

require __DIR__ . '/../includes/partials/head.php';
?>
<body>
<main class="auth-container">
    <div class="auth-card">
        <a href="<?php echo e(BASE_URL); ?>index.php" class="auth-back" title="Kembali ke beranda" aria-label="Kembali ke beranda">
            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M19 12H5M12 19l-7-7 7-7"/>
            </svg>
        </a>

        <div class="auth-header">
            <img src="<?php echo e(BASE_URL); ?>assets/icon/Logo_Light.svg" alt="TIXORA" width="180" height="45">
            <h1>Selamat datang kembali</h1>
            <p>Masuk untuk memesan tiket dan mengelola e-ticket Anda.</p>
        </div>

        <?php if ($flash): ?>
            <?php tampilkanPesan($flash['message'], $flash['type']); ?>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <?php tampilkanPesan($error, 'danger'); ?>
        <?php endif; ?>

        <form method="POST" class="auth-form" autocomplete="on">
            <?php echo csrfField(); ?>

            <div class="form-group">
                <label class="form-label form-label-required" for="username">Username atau Email</label>
                <input type="text" name="username" id="username" class="form-input" value="<?php echo e($oldUser); ?>" placeholder="Masukkan username atau email" required autofocus autocomplete="username">
            </div>

            <div class="form-group">
                <label class="form-label form-label-required" for="password">Password</label>
                <input type="password" name="password" id="password" class="form-input" placeholder="Masukkan password Anda" required autocomplete="current-password">
            </div>

            <button type="submit" class="button primary button-lg full-width">Masuk ke TIXORA</button>
        </form>

        <div class="auth-footer">
            <p>Belum punya akun? <a href="<?php echo e(BASE_URL); ?>auth/register.php">Daftar sekarang</a></p>
        </div>
    </div>
</main>
</body>
</html>
