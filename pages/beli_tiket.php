<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$id    = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$event = ambilEventById($id);

if (!$event) {
    setFlash('Event konser tidak ditemukan.', 'danger');
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}

$quota = (int) $event['quota'];
if ($quota <= 0) {
    setFlash('Maaf, kuota tiket untuk event ini sudah habis.', 'warning');
    header('Location: ' . BASE_URL . 'pages/detail.php?id=' . $id);
    exit;
}

$maxBeli    = min($quota, 10);
$initialQty = isset($_GET['qty']) ? max(1, min($maxBeli, (int) $_GET['qty'])) : 1;
$priceRaw   = (float) $event['price'];
$discount   = (int) $event['discount_percent'];
$priceFinal = (float) $event['final_price'];
$accent     = $event['accent_color'] ?: '#E04826';
$flash      = getFlash();

$pageTitle = 'Checkout — ' . $event['title'];
$activeNav = 'home';

require __DIR__ . '/../includes/partials/head.php';
?>
<body>
<?php require __DIR__ . '/../includes/partials/navbar.php'; ?>

<main id="main-content" class="container narrow">
    <a href="<?php echo e(BASE_URL); ?>pages/detail.php?id=<?php echo (int) $event['id_event']; ?>" class="back-link">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
            <path d="M19 12H5M12 19l-7-7 7-7"/>
        </svg>
        Kembali ke Detail Event
    </a>

    <?php if ($flash): ?>
        <?php tampilkanPesan($flash['message'], $flash['type']); ?>
    <?php endif; ?>

    <div class="purchase-container">
        <!-- Info Event -->
        <section class="event-info-card">
            <div class="event-info-image">
                <?php if (!empty($event['poster'])): ?>
                    <img src="<?php echo e(UPLOADS_URL . basename($event['poster'])); ?>" alt="Poster <?php echo e($event['title']); ?>">
                <?php else: ?>
                    <div class="event-card-placeholder" style="background: <?php echo e($accent); ?>;">
                        <span><?php echo e($event['category_name']); ?></span>
                    </div>
                <?php endif; ?>
            </div>
            <div class="event-info-details">
                <h2><?php echo e($event['title']); ?></h2>
                <p class="event-artist"><?php echo e($event['artist']); ?></p>
                <div class="event-meta-grid">
                    <div class="event-meta-item">
                        <span class="label">Jadwal</span>
                        <span class="value"><?php echo e(formatTanggalIndo($event['event_date'], true)); ?></span>
                    </div>
                    <div class="event-meta-item">
                        <span class="label">Venue</span>
                        <span class="value"><?php echo e($event['venue_name']); ?></span>
                    </div>
                    <div class="event-meta-item">
                        <span class="label">Genre</span>
                        <span class="value"><?php echo e($event['category_name']); ?></span>
                    </div>
                    <div class="event-meta-item">
                        <span class="label">Kuota Tersisa</span>
                        <span class="value tabular"><?php echo number_format($quota); ?> tiket</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- Form Data Pembeli -->
        <section class="form-card">
            <h2 style="margin-bottom: 0.3rem;">Data Pemesan &amp; Jumlah Tiket</h2>
            <p class="form-hint" style="margin-bottom: 1.35rem;">E-ticket resmi TIXORA akan diterbitkan atas nama pemesan di bawah ini. Total harga dihitung otomatis secara aman oleh server.</p>

            <form method="POST" action="<?php echo e(BASE_URL); ?>payment/process.php" id="purchaseForm" class="form">
                <?php echo csrfField(); ?>
                <input type="hidden" name="id_event" value="<?php echo (int) $event['id_event']; ?>">

                <div class="form-group">
                    <label class="form-label form-label-required" for="nama_pembeli">Nama Lengkap Pembeli</label>
                    <input class="form-input" type="text" name="nama_pembeli" id="nama_pembeli" required maxlength="100"
                           value="<?php echo e($_SESSION['nama_lengkap'] ?? ''); ?>">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label form-label-required" for="email_pembeli">Alamat Email</label>
                        <input class="form-input" type="email" name="email_pembeli" id="email_pembeli" required maxlength="120"
                               value="<?php echo e($_SESSION['email'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label form-label-required" for="no_hp">Nomor HP / WhatsApp</label>
                        <input class="form-input" type="tel" name="no_hp" id="no_hp" required placeholder="08xxxxxxxxxx"
                               value="<?php echo e($_SESSION['no_hp'] ?? ''); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label form-label-required" for="jumlahTiket">Jumlah Tiket</label>
                    <input class="form-input tabular" type="number" name="jumlah_tiket" id="jumlahTiket"
                           min="1" max="<?php echo $maxBeli; ?>" value="<?php echo $initialQty; ?>" required>
                    <span class="form-hint">Maksimal <?php echo number_format($maxBeli); ?> tiket per transaksi.</span>
                </div>

                <div class="price-summary">
                    <div class="price-row">
                        <span>Harga Satuan</span>
                        <span>
                            <?php if ($discount > 0): ?>
                                <del style="color: var(--ink-muted); margin-right: 0.4rem;"><?php echo e(formatRupiah($priceRaw)); ?></del>
                            <?php endif; ?>
                            <?php echo $priceFinal <= 0 ? 'Gratis' : e(formatRupiah($priceFinal)); ?>
                        </span>
                    </div>
                    <?php if ($discount > 0): ?>
                    <div class="price-row">
                        <span>Diskon Promo</span>
                        <span style="color: var(--success); font-weight: 700;">-<?php echo $discount; ?>%</span>
                    </div>
                    <?php endif; ?>
                    <div class="price-row">
                        <span>Jumlah Tiket</span>
                        <span id="displayJumlah"><?php echo $initialQty; ?> tiket</span>
                    </div>
                    <div class="price-row total">
                        <span>Total Pembayaran</span>
                        <span id="totalHarga"><?php echo ($priceFinal * $initialQty) <= 0 ? 'Gratis' : e(formatRupiah($priceFinal * $initialQty)); ?></span>
                    </div>
                </div>

                <div class="form-actions">
                    <a href="<?php echo e(BASE_URL); ?>pages/detail.php?id=<?php echo (int) $event['id_event']; ?>" class="button secondary">Batal</a>
                    <button type="submit" class="button primary button-lg">
                        <?php echo $priceFinal <= 0 ? 'Klaim Tiket Gratis Sekarang' : 'Lanjut ke Pembayaran'; ?>
                    </button>
                </div>
            </form>
        </section>
    </div>
</main>

<?php require __DIR__ . '/../includes/partials/footer.php'; ?>

<script>
(function() {
    const jumlahInput   = document.getElementById('jumlahTiket');
    const displayJumlah = document.getElementById('displayJumlah');
    const totalHarga    = document.getElementById('totalHarga');
    const hargaSatuan   = <?php echo json_encode((float) $priceFinal); ?>;

    if (!jumlahInput) return;

    jumlahInput.addEventListener('input', function() {
        const max = parseInt(this.max, 10) || 1;
        let qty = parseInt(this.value, 10);
        if (isNaN(qty) || qty < 1) qty = 1;
        if (qty > max) qty = max;
        const total = qty * hargaSatuan;
        displayJumlah.textContent = qty + ' tiket';
        totalHarga.textContent = total <= 0 ? 'Gratis' : 'Rp ' + total.toLocaleString('id-ID');
    });
})();
</script>
</body>
</html>
