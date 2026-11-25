<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$id    = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$event = ambilEventById($id);

if (!$event) {
    setFlash('Event konser tidak ditemukan.', 'danger');
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}

$priceRaw   = (float) $event['price'];
$discount   = (int) $event['discount_percent'];
$priceFinal = (float) $event['final_price'];
$quota      = (int) $event['quota'];
$maxPerOrder = min($quota, 10);
$accent     = $event['accent_color'] ?: '#E04826';

$tanggalDisplay = formatTanggalIndo($event['event_date']);
$waktuDisplay   = date('H:i', strtotime($event['event_date'])) . ' WIB';

$pageTitle = $event['title'];
$activeNav = 'home';

require __DIR__ . '/../includes/partials/head.php';
?>
<body>
<?php require __DIR__ . '/../includes/partials/navbar.php'; ?>

<main id="main-content" class="detail-wrapper">
    <a href="<?php echo e(BASE_URL); ?>index.php" class="back-link">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
            <path d="M19 12H5M12 19l-7-7 7-7"/>
        </svg>
        Kembali ke Katalog Event
    </a>

    <!-- Banner -->
    <div class="detail-banner">
        <?php if (!empty($event['poster'])): ?>
            <img src="<?php echo e(UPLOADS_URL . basename($event['poster'])); ?>" alt="Poster <?php echo e($event['title']); ?>">
        <?php else: ?>
            <div class="detail-banner-placeholder" style="background: linear-gradient(135deg, <?php echo e($accent); ?>, #141413);">
                <?php echo e($event['category_name']); ?> FESTIVAL
            </div>
        <?php endif; ?>
        <?php if ($discount > 0): ?>
            <span class="event-discount-tag"><?php echo $discount; ?>% OFF</span>
        <?php endif; ?>
    </div>

    <div class="detail-grid">
        <!-- Main Column -->
        <article class="detail-main">
            <span class="badge" style="background: <?php echo e($accent); ?>1A; color: <?php echo e($accent); ?>;">
                <?php echo e($event['category_name']); ?>
            </span>
            <h1 class="detail-title"><?php echo e($event['title']); ?></h1>
            <p class="detail-artist">Penampilan spesial oleh <?php echo e($event['artist']); ?></p>

            <div class="detail-info-list">
                <div class="detail-info-row">
                    <div class="detail-info-icon">
                        <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>
                        </svg>
                    </div>
                    <div>
                        <span class="detail-info-label">Tanggal &amp; Waktu Pelaksanaan</span>
                        <span class="detail-info-value"><?php echo e($tanggalDisplay); ?></span>
                        <span class="detail-info-sub">Mulai pukul <?php echo e($waktuDisplay); ?></span>
                    </div>
                </div>

                <div class="detail-info-row">
                    <div class="detail-info-icon">
                        <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/>
                            <circle cx="12" cy="10" r="3"/>
                        </svg>
                    </div>
                    <div>
                        <span class="detail-info-label">Lokasi Venue</span>
                        <span class="detail-info-value"><?php echo e($event['venue_name']); ?></span>
                        <span class="detail-info-sub"><?php echo e($event['venue_city']); ?>, Indonesia</span>
                    </div>
                </div>

                <div class="detail-info-row">
                    <div class="detail-info-icon">
                        <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/>
                        </svg>
                    </div>
                    <div>
                        <span class="detail-info-label">Ketersediaan Kuota</span>
                        <span class="detail-info-value tabular">
                            <?php echo $quota > 0 ? number_format($quota) . ' Tiket Tersedia' : 'Tiket Habis (Sold Out)'; ?>
                        </span>
                    </div>
                </div>
            </div>

            <div class="detail-desc-section">
                <h3>Tentang Event</h3>
                <p>
                    <?php if (!empty($event['description'])): ?>
                        <?php echo nl2br(e($event['description'])); ?>
                    <?php else: ?>
                        Saksikan penampilan langsung dari <strong><?php echo e($event['artist']); ?></strong> dalam rangkaian <strong><?php echo e($event['title']); ?></strong> di <?php echo e($event['venue_name']); ?>. Amankan e-ticket resmi Anda melalui TIXORA sebelum kuota habis.
                    <?php endif; ?>
                </p>
            </div>
        </article>

        <!-- Purchase Sidebar (Desktop) -->
        <aside class="detail-sidebar" aria-label="Ringkasan pembelian tiket">
            <h2>Pesan Tiket</h2>
            <p class="form-hint" style="margin-top:0.25rem;"><?php echo e($tanggalDisplay); ?> &bull; <?php echo e($waktuDisplay); ?></p>

            <div class="ticket-price-box">
                <span class="event-price-label" style="color: var(--accent);">Harga Resmi (General Admission)</span>
                <?php if ($discount > 0): ?>
                    <span class="event-price-old" style="font-size: 0.9rem; margin-top: 0.25rem;"><?php echo e(formatRupiah($priceRaw)); ?></span>
                <?php endif; ?>
                <div class="price-main">
                    <?php echo $priceFinal <= 0 ? 'Gratis' : e(formatRupiah($priceFinal)); ?>
                    <span style="font-size: 0.8rem; font-weight: 500; color: var(--ink-muted);">/ tiket</span>
                </div>
            </div>

            <?php if (!isset($_SESSION['user_id'])): ?>
                <div class="alert alert-warning">
                    Silakan masuk terlebih dahulu untuk melanjutkan pemesanan tiket.
                </div>
                <a href="<?php echo e(BASE_URL); ?>auth/login.php" class="button primary button-lg button-pill full-width">
                    Masuk untuk Membeli
                </a>
            <?php elseif ($quota > 0): ?>
                <form action="<?php echo e(BASE_URL); ?>pages/beli_tiket.php" method="GET" id="formBeli">
                    <input type="hidden" name="id" value="<?php echo (int) $event['id_event']; ?>">

                    <div class="form-group">
                        <label class="form-label" for="jumlahTiket">Jumlah Tiket (maks. <?php echo $maxPerOrder; ?>)</label>
                        <input type="number" name="qty" id="jumlahTiket" class="form-input tabular" min="1" max="<?php echo $maxPerOrder; ?>" value="1" required>
                    </div>

                    <div class="ticket-total-row">
                        <span>Estimasi Total</span>
                        <span id="totalHarga" class="tabular" style="color: var(--accent); font-size: 1.125rem;">
                            <?php echo $priceFinal <= 0 ? 'Gratis' : e(formatRupiah($priceFinal)); ?>
                        </span>
                    </div>

                    <button type="submit" class="button primary button-lg button-pill full-width">
                        Lanjut Data Pembeli &rarr;
                    </button>
                </form>
            <?php else: ?>
                <button type="button" disabled class="button secondary button-lg button-pill full-width">Sold Out</button>
            <?php endif; ?>
        </aside>
    </div>
</main>

<!-- Sticky Buy Bar (Mobile Only) -->
<div class="sticky-buy-bar">
    <div class="sticky-buy-price">
        <span><?php echo e($tanggalDisplay); ?></span>
        <span><?php echo $priceFinal <= 0 ? 'Gratis' : e(formatRupiah($priceFinal)); ?></span>
    </div>
    <?php if (!isset($_SESSION['user_id'])): ?>
        <a href="<?php echo e(BASE_URL); ?>auth/login.php" class="button primary button-pill">Masuk &amp; Beli</a>
    <?php elseif ($quota > 0): ?>
        <a href="<?php echo e(BASE_URL); ?>pages/beli_tiket.php?id=<?php echo (int) $event['id_event']; ?>&qty=1" class="button primary button-pill">Beli Tiket</a>
    <?php else: ?>
        <button type="button" disabled class="button secondary button-pill">Sold Out</button>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/partials/footer.php'; ?>

<script>
(function() {
    const qtyInput = document.getElementById('jumlahTiket');
    const totalEl  = document.getElementById('totalHarga');
    const unitPrice = <?php echo json_encode((float) $priceFinal); ?>;
    if (!qtyInput || !totalEl) return;

    qtyInput.addEventListener('input', function() {
        const max = parseInt(this.max, 10) || 1;
        let qty = parseInt(this.value, 10);
        if (isNaN(qty) || qty < 1) qty = 1;
        if (qty > max) qty = max;
        const total = qty * unitPrice;
        totalEl.textContent = total <= 0 ? 'Gratis' : 'Rp ' + total.toLocaleString('id-ID');
    });
})();
</script>
</body>
</html>
