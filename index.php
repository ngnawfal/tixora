<?php
require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle         = 'Eksplorasi Konser & Event Musik';
$activeNav         = 'home';
$showCatalogSearch = true;

$flash      = getFlash();
$events     = ambilSemuaEvent(true);
$categories = ambilSemuaKategori();
$venues     = ambilSemuaVenue();

$totalEvents     = count($events);
$totalCategories = count($categories);
$totalVenues     = count($venues);

require __DIR__ . '/includes/partials/head.php';
?>
<body>
<?php require __DIR__ . '/includes/partials/navbar.php'; ?>

<main id="main-content" class="container">
    <?php if ($flash): ?>
        <?php tampilkanPesan($flash['message'], $flash['type']); ?>
    <?php endif; ?>

    <!-- Hero Banner -->
    <section class="hero-banner" aria-label="Ringkasan TIXORA">
        <div class="hero-content">
            <div class="hero-eyebrow">
                <span class="hero-eyebrow-dot" aria-hidden="true"></span>
                <span>TIXORA &bull; OFFICIAL TICKETING</span>
            </div>
            <h1 class="hero-title">Temukan konser terbaik, amankan tiketmu tanpa ribet.</h1>
            <p class="hero-subtitle">
                Pemesanan tiket konser resmi dengan kuota real-time, transparansi harga, dan e-ticket digital terverifikasi langsung dari satu platform.
            </p>
        </div>
        <div class="hero-stats" aria-label="Statistik katalog">
            <div class="hero-stat-box">
                <span class="hero-stat-num"><?php echo number_format($totalEvents); ?></span>
                <span class="hero-stat-label">Konser Aktif</span>
            </div>
            <div class="hero-stat-box">
                <span class="hero-stat-num"><?php echo number_format($totalCategories); ?></span>
                <span class="hero-stat-label">Genre Musik</span>
            </div>
            <div class="hero-stat-box">
                <span class="hero-stat-num"><?php echo number_format($totalVenues); ?></span>
                <span class="hero-stat-label">Venue Resmi</span>
            </div>
        </div>
    </section>

    <!-- Filter Bar -->
    <section class="catalog-toolbar" aria-label="Filter genre event">
        <div class="category-pills" role="tablist" aria-label="Pilih genre">
            <button type="button" class="category-pill active" data-category="" role="tab" aria-selected="true">
                Semua Genre
            </button>
            <?php foreach ($categories as $cat): ?>
                <?php if ((int) $cat['event_count'] > 0): ?>
                    <button type="button" class="category-pill" data-category="<?php echo e($cat['name']); ?>" role="tab" aria-selected="false">
                        <?php echo e($cat['name']); ?>
                    </button>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <div class="catalog-count" id="catalogCount">
            Menampilkan <?php echo number_format($totalEvents); ?> event
        </div>
    </section>

    <!-- Event Grid -->
    <section aria-label="Daftar event konser">
        <div class="card-grid" id="eventGrid">
            <?php foreach ($events as $ev):
                $discount   = (int) $ev['discount_percent'];
                $priceRaw   = (float) $ev['price'];
                $priceFinal = (float) $ev['final_price'];
                $quota      = (int) $ev['quota'];
                $accent     = $ev['accent_color'] ?: '#E04826';

                if ($quota <= 0) {
                    $stockClass = 'soldout';
                    $stockLabel = 'Sold Out';
                } elseif ($quota <= 10) {
                    $stockClass = 'low';
                    $stockLabel = 'Sisa ' . number_format($quota);
                } else {
                    $stockClass = 'available';
                    $stockLabel = 'Sisa ' . number_format($quota);
                }
            ?>
            <a href="<?php echo e(BASE_URL); ?>pages/detail.php?id=<?php echo (int) $ev['id_event']; ?>"
               class="event-card"
               data-title="<?php echo e(mb_strtolower($ev['title'])); ?>"
               data-artist="<?php echo e(mb_strtolower($ev['artist'])); ?>"
               data-venue="<?php echo e(mb_strtolower($ev['venue_name'] . ' ' . $ev['venue_city'])); ?>"
               data-category="<?php echo e($ev['category_name']); ?>">

                <div class="event-card-image">
                    <span class="event-genre-tag"><?php echo e($ev['category_name']); ?></span>
                    <?php if ($discount > 0): ?>
                        <span class="event-discount-tag"><?php echo $discount; ?>% OFF</span>
                    <?php endif; ?>

                    <?php if (!empty($ev['poster'])): ?>
                        <img src="<?php echo e(UPLOADS_URL . basename($ev['poster'])); ?>"
                             alt="Poster <?php echo e($ev['title']); ?>"
                             loading="lazy">
                    <?php else: ?>
                        <div class="event-card-placeholder" style="background: <?php echo e($accent); ?>;">
                            <span><?php echo e($ev['category_name']); ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="event-card-content">
                    <h2 class="event-card-title"><?php echo e($ev['title']); ?></h2>
                    <div class="event-card-artist"><?php echo e($ev['artist']); ?></div>

                    <div class="event-card-meta-list">
                        <div class="event-meta-line">
                            <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                <rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>
                            </svg>
                            <span><?php echo e(formatTanggalIndo($ev['event_date'])); ?></span>
                        </div>
                        <div class="event-meta-line">
                            <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.828 0l-4.243-4.243a8 8 0 1111.314 0z"/>
                                <circle cx="12" cy="11" r="3"/>
                            </svg>
                            <span><?php echo e($ev['venue_name']); ?>, <?php echo e($ev['venue_city']); ?></span>
                        </div>
                    </div>

                    <div class="event-card-footer">
                        <div>
                            <span class="event-price-label">Mulai dari</span>
                            <?php if ($discount > 0): ?>
                                <span class="event-price-old"><?php echo e(formatRupiah($priceRaw)); ?></span>
                            <?php endif; ?>
                            <div class="event-price-now <?php echo $discount > 0 ? 'promo' : ''; ?>">
                                <?php echo $priceFinal <= 0 ? 'Gratis' : e(formatRupiah($priceFinal)); ?>
                            </div>
                        </div>
                        <span class="stock-chip <?php echo $stockClass; ?>"><?php echo e($stockLabel); ?></span>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- Empty State saat filter tidak menemukan hasil -->
        <div class="empty-state" id="emptyState" style="display: <?php echo $totalEvents === 0 ? 'block' : 'none'; ?>;">
            <div class="empty-state-icon">
                <svg width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                    <circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="M20 20l-3.5-3.5"/>
                </svg>
            </div>
            <h3>Event tidak ditemukan</h3>
            <p>Coba gunakan kata kunci konser, artis, atau pilih genre musik lain untuk melihat jadwal yang tersedia.</p>
            <button type="button" class="button secondary" id="resetFiltersBtn">Tampilkan Semua Event</button>
        </div>
    </section>
</main>

<?php require __DIR__ . '/includes/partials/footer.php'; ?>

<script>
(function() {
    const searchDesktop = document.getElementById('searchInput');
    const searchMobile  = document.getElementById('searchInputMobile');
    const pills         = document.querySelectorAll('.category-pill');
    const cards         = document.querySelectorAll('.event-card');
    const countEl       = document.getElementById('catalogCount');
    const emptyEl       = document.getElementById('emptyState');
    const resetBtn      = document.getElementById('resetFiltersBtn');

    let selectedCategory = '';

    function applyFilters() {
        const term = ((searchDesktop && searchDesktop.value) || (searchMobile && searchMobile.value) || '').trim().toLowerCase();
        let visible = 0;

        cards.forEach(card => {
            const matchText = !term
                || card.dataset.title.includes(term)
                || card.dataset.artist.includes(term)
                || card.dataset.venue.includes(term);
            const matchCat = !selectedCategory || card.dataset.category === selectedCategory;

            const show = matchText && matchCat;
            card.style.display = show ? '' : 'none';
            if (show) visible++;
        });

        if (countEl) {
            countEl.textContent = 'Menampilkan ' + visible + ' event';
        }
        if (emptyEl) {
            emptyEl.style.display = visible === 0 ? 'block' : 'none';
        }
    }

    pills.forEach(pill => {
        pill.addEventListener('click', function() {
            selectedCategory = this.dataset.category || '';
            pills.forEach(p => {
                p.classList.remove('active');
                p.setAttribute('aria-selected', 'false');
            });
            this.classList.add('active');
            this.setAttribute('aria-selected', 'true');
            applyFilters();
        });
    });

    if (searchDesktop) {
        searchDesktop.addEventListener('input', () => {
            if (searchMobile) searchMobile.value = searchDesktop.value;
            applyFilters();
        });
    }
    if (searchMobile) {
        searchMobile.addEventListener('input', () => {
            if (searchDesktop) searchDesktop.value = searchMobile.value;
            applyFilters();
        });
    }
    if (resetBtn) {
        resetBtn.addEventListener('click', () => {
            if (searchDesktop) searchDesktop.value = '';
            if (searchMobile) searchMobile.value = '';
            selectedCategory = '';
            pills.forEach((p, idx) => {
                p.classList.toggle('active', idx === 0);
                p.setAttribute('aria-selected', idx === 0 ? 'true' : 'false');
            });
            applyFilters();
        });
    }
})();
</script>
</body>
</html>
