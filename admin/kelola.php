<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

// Seluruh operasi mutasi (tambah & hapus) menggunakan POST + CSRF
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        setFlash('Token keamanan tidak valid. Muat ulang halaman dan coba lagi.', 'danger');
    } elseif (isset($_POST['tambah_kategori'])) {
        $res = tambahKategori((string) ($_POST['nama_kategori'] ?? ''), (string) ($_POST['accent_color'] ?? '#E04826'));
        setFlash($res['ok'] ? 'Genre musik berhasil ditambahkan.' : ($res['error'] ?? 'Gagal menambah genre.'), $res['ok'] ? 'success' : 'danger');
    } elseif (isset($_POST['hapus_kategori'])) {
        $res = hapusKategori((int) ($_POST['id_category'] ?? 0));
        setFlash($res['ok'] ? 'Genre musik berhasil dihapus.' : ($res['error'] ?? 'Gagal menghapus genre.'), $res['ok'] ? 'success' : 'danger');
    } elseif (isset($_POST['tambah_venue'])) {
        $res = tambahVenue((string) ($_POST['nama_venue'] ?? ''), (string) ($_POST['kota_venue'] ?? 'Indonesia'));
        setFlash($res['ok'] ? 'Venue baru berhasil ditambahkan.' : ($res['error'] ?? 'Gagal menambah venue.'), $res['ok'] ? 'success' : 'danger');
    } elseif (isset($_POST['hapus_venue'])) {
        $res = hapusVenue((int) ($_POST['id_venue'] ?? 0));
        setFlash($res['ok'] ? 'Venue berhasil dihapus.' : ($res['error'] ?? 'Gagal menghapus venue.'), $res['ok'] ? 'success' : 'danger');
    }

    header('Location: ' . BASE_URL . 'admin/kelola.php');
    exit;
}

$flash      = getFlash();
$categories = ambilSemuaKategori();
$venues     = ambilSemuaVenue();

$pageTitle   = 'Kelola Genre & Venue — Admin';
$activeAdmin = 'reference';

require __DIR__ . '/../includes/partials/head.php';
?>
<body>
<div class="admin-wrapper">
    <?php require __DIR__ . '/../includes/partials/admin_sidebar.php'; ?>

    <main id="main-content" class="admin-content">
        <div class="admin-header">
            <div>
                <h1>Data Referensi (Genre &amp; Venue)</h1>
                <p>Tabel master ternormalisasi yang direlasikan via Foreign Key ke katalog event konser.</p>
            </div>
        </div>

        <?php if ($flash): ?>
            <?php tampilkanPesan($flash['message'], $flash['type']); ?>
        <?php endif; ?>

        <div class="management-grid">
            <!-- Kelola Genre / Categories -->
            <section class="management-card">
                <h2>Genre Musik (<?php echo count($categories); ?>)</h2>
                <p class="card-sub">Genre yang masih dipakai oleh event aktif dilindungi dari penghapusan.</p>

                <form method="POST" class="management-form">
                    <?php echo csrfField(); ?>
                    <input type="text" name="nama_kategori" class="form-input" placeholder="Nama genre baru (mis. Folk)" required maxlength="50">
                    <input type="color" name="accent_color" value="#E04826" title="Warna aksen genre" style="width: 46px; height: 44px; padding: 3px; border: 1px solid var(--border-strong); border-radius: var(--radius-sm); background: #fff; cursor: pointer;">
                    <button type="submit" name="tambah_kategori" value="1" class="button primary">Tambah</button>
                </form>

                <div class="management-list">
                    <?php foreach ($categories as $cat): ?>
                        <div class="management-item">
                            <div class="management-item-info">
                                <span class="color-dot" style="background: <?php echo e($cat['accent_color']); ?>;"></span>
                                <div>
                                    <div class="management-item-name"><?php echo e($cat['name']); ?></div>
                                    <div class="management-item-sub"><?php echo (int) $cat['event_count']; ?> event terhubung</div>
                                </div>
                            </div>
                            <form method="POST" style="margin: 0;">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="id_category" value="<?php echo (int) $cat['id_category']; ?>">
                                <button type="submit" name="hapus_kategori" value="1"
                                        class="button danger-outline button-sm"
                                        <?php echo (int) $cat['event_count'] > 0 ? 'disabled title="Sedang digunakan oleh event"' : ''; ?>>
                                    Hapus
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <!-- Kelola Venues -->
            <section class="management-card">
                <h2>Venue / Lokasi (<?php echo count($venues); ?>)</h2>
                <p class="card-sub">Daftar gedung, stadion, dan arena resmi pelaksanaan konser.</p>

                <form method="POST" class="management-form">
                    <?php echo csrfField(); ?>
                    <input type="text" name="nama_venue" class="form-input" placeholder="Nama venue baru" required maxlength="100">
                    <input type="text" name="kota_venue" class="form-input" placeholder="Kota (mis. Bandung)" style="max-width: 150px;" required maxlength="60">
                    <button type="submit" name="tambah_venue" value="1" class="button primary">Tambah</button>
                </form>

                <div class="management-list">
                    <?php foreach ($venues as $ven): ?>
                        <div class="management-item">
                            <div class="management-item-info">
                                <div>
                                    <div class="management-item-name"><?php echo e($ven['name']); ?></div>
                                    <div class="management-item-sub"><?php echo e($ven['city']); ?> &bull; <?php echo (int) $ven['event_count']; ?> event</div>
                                </div>
                            </div>
                            <form method="POST" style="margin: 0;">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="id_venue" value="<?php echo (int) $ven['id_venue']; ?>">
                                <button type="submit" name="hapus_venue" value="1"
                                        class="button danger-outline button-sm"
                                        <?php echo (int) $ven['event_count'] > 0 ? 'disabled title="Sedang digunakan oleh event"' : ''; ?>>
                                    Hapus
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>
    </main>
</div>
</body>
</html>
