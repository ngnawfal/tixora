<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$id    = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$event = ambilEventById($id);

if (!$event) {
    setFlash('Data event tidak ditemukan.', 'danger');
    header('Location: ' . BASE_URL . 'admin/');
    exit;
}

$categories = ambilSemuaKategori();
$venues     = ambilSemuaVenue();
$error      = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ubah'])) {
    if (!verifyCsrf()) {
        $error = 'Token keamanan formulir tidak valid. Muat ulang halaman dan coba lagi.';
    } else {
        $coverFile = $_FILES['poster'] ?? null;
        $res = ubahEvent($id, $_POST, $coverFile);
        if ($res['ok']) {
            setFlash('Perubahan data event berhasil disimpan.', 'success');
            header('Location: ' . BASE_URL . 'admin/');
            exit;
        }
        $error = $res['error'] ?? 'Gagal memperbarui data event.';
    }
}

$eventDateVal = date('Y-m-d\TH:i', strtotime($event['event_date']));
$pageTitle    = 'Edit Event — ' . $event['title'];
$activeAdmin  = 'events';

require __DIR__ . '/../includes/partials/head.php';
?>
<body>
<div class="admin-wrapper">
    <?php require __DIR__ . '/../includes/partials/admin_sidebar.php'; ?>

    <main id="main-content" class="admin-content">
        <a href="<?php echo e(BASE_URL); ?>admin/" class="back-link">&larr; Kembali ke Data Event</a>

        <div class="admin-header">
            <div>
                <h1>Edit Event Konser</h1>
                <p>Perbarui detail untuk <strong><?php echo e($event['title']); ?></strong>.</p>
            </div>
        </div>

        <?php if ($error !== ''): ?>
            <?php tampilkanPesan($error, 'danger'); ?>
        <?php endif; ?>

        <div class="form-card" style="max-width: 820px;">
            <?php if (!empty($event['poster'])): ?>
                <div class="current-cover">
                    <img src="<?php echo e(UPLOADS_URL . basename($event['poster'])); ?>" alt="Poster <?php echo e($event['title']); ?>">
                    <div>
                        <div class="current-cover-label">Poster Saat Ini</div>
                        <div class="form-hint"><?php echo e(basename($event['poster'])); ?></div>
                    </div>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="form">
                <?php echo csrfField(); ?>

                <div class="form-group full-width">
                    <label class="form-label form-label-required" for="title">Nama Event Konser</label>
                    <input type="text" id="title" name="title" class="form-input" value="<?php echo e($event['title']); ?>" required maxlength="150">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label form-label-required" for="artist">Artis / Line-up Penampil</label>
                        <input type="text" id="artist" name="artist" class="form-input" value="<?php echo e($event['artist']); ?>" required maxlength="150">
                    </div>

                    <div class="form-group">
                        <label class="form-label form-label-required" for="id_category">Genre / Jenis Event</label>
                        <select id="id_category" name="id_category" class="form-select" required>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo (int) $cat['id_category']; ?>" <?php echo ((int) $event['id_category'] === (int) $cat['id_category']) ? 'selected' : ''; ?>>
                                    <?php echo e($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label form-label-required" for="event_date">Tanggal &amp; Waktu Pelaksanaan</label>
                        <input type="datetime-local" id="event_date" name="event_date" class="form-input" value="<?php echo e($eventDateVal); ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label form-label-required" for="id_venue">Venue / Lokasi</label>
                        <select id="id_venue" name="id_venue" class="form-select" required>
                            <?php foreach ($venues as $ven): ?>
                                <option value="<?php echo (int) $ven['id_venue']; ?>" <?php echo ((int) $event['id_venue'] === (int) $ven['id_venue']) ? 'selected' : ''; ?>>
                                    <?php echo e($ven['name'] . ' (' . $ven['city'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row-3">
                    <div class="form-group">
                        <label class="form-label form-label-required" for="price">Harga Tiket (Rp)</label>
                        <input type="number" id="price" name="price" class="form-input tabular" min="0" value="<?php echo (int) round((float) $event['price']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="discount_percent">Diskon Promo (%)</label>
                        <input type="number" id="discount_percent" name="discount_percent" class="form-input tabular" min="0" max="100" value="<?php echo (int) $event['discount_percent']; ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label form-label-required" for="quota">Kuota Tiket</label>
                        <input type="number" id="quota" name="quota" class="form-input tabular" min="0" value="<?php echo (int) $event['quota']; ?>" required>
                    </div>
                </div>

                <div class="form-group full-width">
                    <label class="form-label" for="description">Deskripsi Event</label>
                    <textarea id="description" name="description" class="form-input" rows="4"><?php echo e($event['description'] ?? ''); ?></textarea>
                </div>

                <div class="form-group full-width">
                    <label class="form-label" for="poster">Ganti Poster (opsional)</label>
                    <input type="file" id="poster" name="poster" class="form-input" accept="image/jpeg,image/png,image/gif,image/webp">
                    <span class="form-hint">Biarkan kosong jika tidak ingin mengganti poster saat ini.</span>
                </div>

                <div class="form-actions">
                    <a href="<?php echo e(BASE_URL); ?>admin/" class="button secondary">Batal</a>
                    <button type="submit" name="ubah" value="1" class="button primary">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </main>
</div>
</body>
</html>
