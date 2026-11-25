<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$idUser = (int) $_SESSION['user_id'];
$pesan  = '';
$tipe   = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        $pesan = 'Sesi formulir tidak valid. Muat ulang halaman dan coba lagi.';
        $tipe  = 'danger';
    } elseif (isset($_POST['update_profile'])) {
        $fullName = trim((string) ($_POST['nama_lengkap'] ?? ''));
        $email    = strtolower(trim((string) ($_POST['email'] ?? '')));
        $phone    = trim((string) ($_POST['no_hp'] ?? ''));

        if ($fullName === '' || mb_strlen($fullName) < 2) {
            $pesan = 'Nama lengkap minimal 2 karakter.';
            $tipe  = 'danger';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $pesan = 'Format email tidak valid.';
            $tipe  = 'danger';
        } elseif ($phone !== '' && !isValidPhone($phone)) {
            $pesan = 'Format nomor HP tidak valid.';
            $tipe  = 'danger';
        } else {
            // Pastikan email belum dipakai akun lain
            $chk = $koneksi->prepare('SELECT id_user FROM users WHERE email = ? AND id_user <> ? LIMIT 1');
            $chk->bind_param('si', $email, $idUser);
            $chk->execute();
            $duplicate = $chk->get_result()->num_rows > 0;
            $chk->close();

            if ($duplicate) {
                $pesan = 'Alamat email tersebut sudah digunakan oleh akun lain.';
                $tipe  = 'danger';
            } else {
                $stmt = $koneksi->prepare('UPDATE users SET full_name = ?, email = ?, phone = ? WHERE id_user = ?');
                $stmt->bind_param('sssi', $fullName, $email, $phone, $idUser);
                if ($stmt->execute()) {
                    $_SESSION['nama_lengkap'] = $fullName;
                    $_SESSION['email']        = $email;
                    $_SESSION['no_hp']        = $phone;
                    $pesan = 'Profil berhasil diperbarui.';
                    $tipe  = 'success';
                } else {
                    $pesan = 'Gagal memperbarui profil.';
                    $tipe  = 'danger';
                }
                $stmt->close();
            }
        }
    } elseif (isset($_POST['change_password'])) {
        $currentPass = (string) ($_POST['current_password'] ?? '');
        $newPass     = (string) ($_POST['new_password'] ?? '');
        $confirmPass = (string) ($_POST['confirm_password'] ?? '');

        $pwStmt = $koneksi->prepare('SELECT password_hash FROM users WHERE id_user = ? LIMIT 1');
        $pwStmt->bind_param('i', $idUser);
        $pwStmt->execute();
        $pwRow = $pwStmt->get_result()->fetch_assoc();
        $pwStmt->close();

        if (!$pwRow || !password_verify($currentPass, $pwRow['password_hash'])) {
            $pesan = 'Password saat ini tidak sesuai.';
            $tipe  = 'danger';
        } elseif (strlen($newPass) < 8) {
            $pesan = 'Password baru minimal 8 karakter.';
            $tipe  = 'danger';
        } elseif ($newPass !== $confirmPass) {
            $pesan = 'Konfirmasi password baru tidak cocok.';
            $tipe  = 'danger';
        } else {
            $newHash = password_hash($newPass, PASSWORD_DEFAULT);
            $upd = $koneksi->prepare('UPDATE users SET password_hash = ? WHERE id_user = ?');
            $upd->bind_param('si', $newHash, $idUser);
            if ($upd->execute()) {
                session_regenerate_id(true);
                $pesan = 'Password berhasil diperbarui.';
                $tipe  = 'success';
            } else {
                $pesan = 'Gagal memperbarui password.';
                $tipe  = 'danger';
            }
            $upd->close();
        }
    }
}

$stmt = $koneksi->prepare('SELECT username, email, full_name, phone, role, created_at FROM users WHERE id_user = ? LIMIT 1');
$stmt->bind_param('i', $idUser);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    logout();
}

$isUserAdmin = ($user['role'] === 'admin');
$pageTitle   = 'Profil Saya';
$activeNav   = 'profile';
$activeAdmin = 'profile';

require __DIR__ . '/../includes/partials/head.php';
?>
<body>
<?php if ($isUserAdmin): ?>
<div class="admin-wrapper">
    <?php require __DIR__ . '/../includes/partials/admin_sidebar.php'; ?>
    <div id="main-content" class="admin-content">
<?php else: ?>
    <?php require __DIR__ . '/../includes/partials/navbar.php'; ?>
    <main id="main-content">
<?php endif; ?>

<div class="profile-container">
    <?php if ($pesan !== ''): ?>
        <?php tampilkanPesan($pesan, $tipe); ?>
    <?php endif; ?>

    <div class="profile-header">
        <div class="profile-avatar-large">
            <?php echo e(strtoupper(mb_substr($user['full_name'], 0, 1))); ?>
        </div>
        <div>
            <h1 style="font-size: 1.55rem; margin-bottom: 0.15rem;"><?php echo e($user['full_name']); ?></h1>
            <p class="form-hint" style="margin-bottom: 0.45rem;">@<?php echo e($user['username']); ?></p>
            <span class="badge <?php echo $isUserAdmin ? 'badge-danger' : 'badge-success'; ?>">
                <?php echo $isUserAdmin ? 'Administrator' : 'Member TIXORA'; ?>
            </span>
        </div>
    </div>

    <!-- Mode Tampilan -->
    <section class="panel-card" id="viewMode">
        <div class="panel-header">
            <span>Informasi Akun</span>
            <button type="button" class="button secondary button-sm" onclick="toggleEdit()">Edit Profil</button>
        </div>
        <div class="panel-body">
            <div class="profile-row">
                <div class="profile-label">Nama Lengkap</div>
                <div class="profile-value"><?php echo e($user['full_name']); ?></div>
            </div>
            <div class="profile-row">
                <div class="profile-label">Alamat Email</div>
                <div class="profile-value"><?php echo e($user['email']); ?></div>
            </div>
            <div class="profile-row">
                <div class="profile-label">Nomor HP</div>
                <div class="profile-value"><?php echo e($user['phone'] ?: '-'); ?></div>
            </div>
            <div class="profile-row">
                <div class="profile-label">Username</div>
                <div class="profile-value mono"><?php echo e($user['username']); ?></div>
            </div>
            <div class="profile-row">
                <div class="profile-label">Bergabung Sejak</div>
                <div class="profile-value"><?php echo e(formatTanggalIndo($user['created_at'])); ?></div>
            </div>
        </div>
    </section>

    <!-- Mode Edit -->
    <form class="panel-card" id="editForm" method="POST" style="display: none;">
        <?php echo csrfField(); ?>
        <input type="hidden" name="update_profile" value="1">
        <div class="panel-header">
            <span>Edit Informasi Akun</span>
        </div>
        <div class="panel-body form">
            <div class="form-group">
                <label class="form-label form-label-required" for="p_nama">Nama Lengkap</label>
                <input type="text" id="p_nama" name="nama_lengkap" class="form-input" value="<?php echo e($user['full_name']); ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label form-label-required" for="p_email">Email</label>
                <input type="email" id="p_email" name="email" class="form-input" value="<?php echo e($user['email']); ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label" for="p_hp">Nomor HP</label>
                <input type="tel" id="p_hp" name="no_hp" class="form-input" value="<?php echo e($user['phone'] ?? ''); ?>" placeholder="08xxxxxxxxxx">
            </div>
            <div class="form-actions">
                <button type="button" class="button secondary" onclick="toggleEdit()">Batal</button>
                <button type="submit" class="button primary">Simpan Perubahan</button>
            </div>
        </div>
    </form>

    <!-- Keamanan Password -->
    <form class="panel-card" method="POST">
        <?php echo csrfField(); ?>
        <input type="hidden" name="change_password" value="1">
        <div class="panel-header">
            <span>Keamanan &amp; Ubah Password</span>
        </div>
        <div class="panel-body form">
            <div class="form-group">
                <label class="form-label form-label-required" for="cur_pw">Password Saat Ini</label>
                <input type="password" id="cur_pw" name="current_password" class="form-input" required autocomplete="current-password">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label form-label-required" for="new_pw">Password Baru</label>
                    <input type="password" id="new_pw" name="new_password" class="form-input" required minlength="8" autocomplete="new-password">
                </div>
                <div class="form-group">
                    <label class="form-label form-label-required" for="conf_pw">Konfirmasi Password Baru</label>
                    <input type="password" id="conf_pw" name="confirm_password" class="form-input" required minlength="8" autocomplete="new-password">
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="button dark">Perbarui Password</button>
            </div>
        </div>
    </form>
</div>

<?php if ($isUserAdmin): ?>
    </div>
</div>
<?php else: ?>
    </main>
    <?php require __DIR__ . '/../includes/partials/footer.php'; ?>
<?php endif; ?>

<script>
function toggleEdit() {
    const view = document.getElementById('viewMode');
    const edit = document.getElementById('editForm');
    const isEditing = edit.style.display === 'block';
    view.style.display = isEditing ? 'block' : 'none';
    edit.style.display = isEditing ? 'none' : 'block';
}
</script>
</body>
</html>
