<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$categories = ambilSemuaKategori();
$venues     = ambilSemuaVenue();
$error      = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan'])) {
    if (!verifyCsrf()) {
        $error = 'Token keamanan formulir tidak valid. Muat ulang halaman dan coba lagi.';
    } else {
        $coverFile = $_FILES['poster'] ?? null;
        $res = tambahEvent($_POST, $coverFile);
        if ($res['ok']) {
            setFlash('Event konser baru berhasil ditambahkan.', 'success');
            header('Location: ' . BASE_URL . 'admin/');
            exit;
        }
        $error = $res['error'] ?? 'Gagal menambahkan event konser.';
    }
}

$pageTitle   = 'Tambah Event Konser — Admin';
$activeAdmin = 'events';

require __DIR__ . '/../includes/partials/head.php';
?>
<body>
<div class="admin-wrapper">
    <?php require __DIR__ . '/../includes/partials/admin_sidebar.php'; ?>

    <main id="main-content" class="admin-content">
        <a href="<?php echo e(BASE_URL); ?>admin/" class="back-link">&larr; Kembali ke Data Event</a>

        <div class="admin-header">
            <div>
                <h1>Tambah Event Konser</h1>
                <p>Masukkan informasi konser, jadwal pelaksanaan, harga tiket, dan kuota resmi.</p>
            </div>
        </div>

        <?php if ($error !== ''): ?>
            <?php tampilkanPesan($error, 'danger'); ?>
        <?php endif; ?>

        <div class="form-card" style="max-width: 820px;">
            <form method="POST" enctype="multipart/form-data" class="form">
                <?php echo csrfField(); ?>

                <div class="form-group full-width">
                    <label class="form-label form-label-required" for="title">Nama Event Konser</label>
                    <input type="text" id="title" name="title" class="form-input" value="<?php echo e($_POST['title'] ?? ''); ?>" placeholder="Contoh: Java Jazz Festival 2026" required maxlength="150">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label form-label-required" for="artist">Artis / Line-up Penampil</label>
                        <input type="text" id="artist" name="artist" class="form-input" value="<?php echo e($_POST['artist'] ?? ''); ?>" placeholder="Contoh: Dewa 19, Sheila on 7" required maxlength="150">
                    </div>

                    <div class="form-group">
                        <label class="form-label form-label-required" for="id_category">Genre / Jenis Event</label>
                        <select id="id_category" name="id_category" class="form-select" required>
                            <option value="">-- Pilih Genre --</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo (int) $cat['id_category']; ?>" <?php echo ((int) ($_POST['id_category'] ?? 0) === (int) $cat['id_category']) ? 'selected' : ''; ?>>
                                    <?php echo e($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label form-label-required" for="event_date">Tanggal &amp; Waktu Pelaksanaan</label>
                        <input type="datetime-local" id="event_date" name="event_date" class="form-input" value="<?php echo e($_POST['event_date'] ?? date('Y-m-d\T19:00', strtotime('+30 days'))); ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label form-label-required" for="id_venue">Venue / Lokasi</label>
                        <select id="id_venue" name="id_venue" class="form-select" required>
                            <option value="">-- Pilih Venue --</option>
                            <?php foreach ($venues as $ven): ?>
                                <option value="<?php echo (int) $ven['id_venue']; ?>" <?php echo ((int) ($_POST['id_venue'] ?? 0) === (int) $ven['id_venue']) ? 'selected' : ''; ?>>
                                    <?php echo e($ven['name'] . ' (' . $ven['city'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row-3">
                    <div class="form-group">
                        <label class="form-label form-label-required" for="price">Harga Tiket (Rp)</label>
                        <input type="number" id="price" name="price" class="form-input tabular" min="0" step="1000" value="<?php echo e($_POST['price'] ?? '150000'); ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="discount_percent">Diskon Promo (%)</label>
                        <input type="number" id="discount_percent" name="discount_percent" class="form-input tabular" min="0" max="100" value="<?php echo e($_POST['discount_percent'] ?? '0'); ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label form-label-required" for="quota">Kuota Tiket</label>
                        <input type="number" id="quota" name="quota" class="form-input tabular" min="0" value="<?php echo e($_POST['quota'] ?? '1000'); ?>" required>
                    </div>
                </div>

                <div class="form-group full-width">
                    <label class="form-label" for="description">Deskripsi Event</label>
                    <textarea id="description" name="description" class="form-input" rows="4" placeholder="Jelaskan konsep konser, jadwal open gate, atau informasi penting lainnya..."><?php echo e($_POST['description'] ?? ''); ?></textarea>
                </div>

                <div class="form-group full-width">
                    <label class="form-label" for="poster">Poster Event (opsional)</label>
                    <input type="file" id="poster" name="poster" class="form-input" accept="image/jpeg,image/png,image/gif,image/webp">
                    <span class="form-hint">Format yang didukung: JPG, PNG, GIF, WebP. Maksimal 2MB (diverifikasi konten MIME asli).</span>
                </div>

                <div class="form-actions">
                    <a href="<?php echo e(BASE_URL); ?>admin/" class="button secondary">Batal</a>
                    <button type="submit" name="simpan" value="1" class="button primary">Simpan Event</button>
                </div>
            </form>
        </div>
    </main>
</div>
</body>
</html>
