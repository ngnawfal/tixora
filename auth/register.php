<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';

if (isLoggedIn()) {
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}

$pageTitle = 'Daftar Akun';
$error     = '';
$old = [
    'nama_lengkap' => '',
    'email'        => '',
    'no_hp'        => '',
    'username'     => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old['nama_lengkap'] = trim((string) ($_POST['nama_lengkap'] ?? ''));
    $old['email']        = trim((string) ($_POST['email'] ?? ''));
    $old['no_hp']        = trim((string) ($_POST['no_hp'] ?? ''));
    $old['username']     = trim((string) ($_POST['username'] ?? ''));
    $password            = (string) ($_POST['password'] ?? '');
    $confirmPassword     = (string) ($_POST['password_confirm'] ?? '');

    if (!verifyCsrf()) {
        $error = 'Sesi formulir tidak valid. Muat ulang halaman dan coba lagi.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Konfirmasi password tidak cocok.';
    } else {
        $res = register($old['username'], $password, $old['email'], $old['nama_lengkap'], $old['no_hp']);
        if ($res['ok']) {
            setFlash('Akun berhasil dibuat. Silakan masuk menggunakan kredensial Anda.', 'success');
            header('Location: ' . BASE_URL . 'auth/login.php');
            exit;
        }
        $error = $res['error'] ?? 'Registrasi gagal.';
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
            <h1>Buat akun baru</h1>
            <p>Bergabung di TIXORA untuk memesan tiket konser resmi.</p>
        </div>

        <?php if ($error !== ''): ?>
            <?php tampilkanPesan($error, 'danger'); ?>
        <?php endif; ?>

        <form method="POST" class="auth-form">
            <?php echo csrfField(); ?>

            <div class="form-group">
                <label class="form-label form-label-required" for="nama_lengkap">Nama Lengkap</label>
                <input type="text" name="nama_lengkap" id="nama_lengkap" class="form-input" value="<?php echo e($old['nama_lengkap']); ?>" required minlength="2" maxlength="100" autocomplete="name">
            </div>

            <div class="form-group">
                <label class="form-label form-label-required" for="email">Email</label>
                <input type="email" name="email" id="email" class="form-input" value="<?php echo e($old['email']); ?>" required autocomplete="email">
            </div>

            <div class="form-group">
                <label class="form-label form-label-required" for="no_hp">Nomor HP</label>
                <input type="tel" name="no_hp" id="no_hp" class="form-input" value="<?php echo e($old['no_hp']); ?>" placeholder="08xxxxxxxxxx" required autocomplete="tel">
            </div>

            <div class="form-group">
                <label class="form-label form-label-required" for="username">Username</label>
                <input type="text" name="username" id="username" class="form-input" value="<?php echo e($old['username']); ?>" pattern="[a-zA-Z0-9_]{3,30}" title="3–30 karakter huruf, angka, atau underscore" required autocomplete="username">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label form-label-required" for="password">Password</label>
                    <input type="password" name="password" id="password" class="form-input" required minlength="8" autocomplete="new-password">
                </div>
                <div class="form-group">
                    <label class="form-label form-label-required" for="password_confirm">Konfirmasi</label>
                    <input type="password" name="password_confirm" id="password_confirm" class="form-input" required minlength="8" autocomplete="new-password">
                </div>
            </div>
            <span class="form-hint" style="margin-top:-0.5rem;">Gunakan minimal 8 karakter kombinasi huruf dan angka.</span>

            <button type="submit" class="button primary button-lg full-width">Daftar Akun</button>
        </form>

        <div class="auth-footer">
            <p>Sudah punya akun? <a href="<?php echo e(BASE_URL); ?>auth/login.php">Masuk di sini</a></p>
        </div>
    </div>
</main>
</body>
</html>
