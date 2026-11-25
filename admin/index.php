<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$pageTitle   = 'Data Tiket Event — Admin';
$activeAdmin = 'events';

$flash      = getFlash();
$events     = ambilSemuaEvent(false);
$categories = ambilSemuaKategori();
$stats      = ambilStatistikAdmin();

require __DIR__ . '/../includes/partials/head.php';
?>
<body>
<div class="admin-wrapper">
    <?php require __DIR__ . '/../includes/partials/admin_sidebar.php'; ?>

    <main id="main-content" class="admin-content">
        <div class="admin-header">
            <div>
                <h1>Data Tiket Event</h1>
                <p>Kelola jadwal konser, kuota tiket, harga, diskon promo, dan poster resmi TIXORA.</p>
            </div>
            <a class="button primary" href="<?php echo e(BASE_URL); ?>admin/tambah.php">+ Tambah Event Baru</a>
        </div>

        <?php if ($flash): ?>
            <?php tampilkanPesan($flash['message'], $flash['type']); ?>
        <?php endif; ?>

        <!-- KPI Ringkasan -->
        <section class="kpi-grid" aria-label="Ringkasan statistik">
            <div class="kpi-card">
                <div class="kpi-label">Total Event</div>
                <div class="kpi-value"><?php echo number_format($stats['total_events']); ?></div>
                <div class="kpi-sub">Terdaftar di katalog</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Kuota Tersisa</div>
                <div class="kpi-value"><?php echo number_format($stats['total_quota']); ?></div>
                <div class="kpi-sub">Tiket siap dipesan</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Tiket Terjual</div>
                <div class="kpi-value"><?php echo number_format($stats['tickets_sold']); ?></div>
                <div class="kpi-sub"><?php echo number_format($stats['paid_orders']); ?> transaksi lunas</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Total Pendapatan</div>
                <div class="kpi-value" style="color: var(--accent);"><?php echo e(formatRupiah($stats['total_revenue'])); ?></div>
                <div class="kpi-sub"><?php echo number_format($stats['pending_orders']); ?> menunggu bayar</div>
            </div>
        </section>

        <!-- Toolbar Filter -->
        <div class="admin-toolbar">
            <select id="filterKategori" class="form-select" style="width: 200px;" aria-label="Filter genre">
                <option value="">Semua Genre</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo e($cat['name']); ?>"><?php echo e($cat['name']); ?></option>
                <?php endforeach; ?>
            </select>
            <input type="search" id="searchInput" class="form-input" style="max-width: 300px;" placeholder="Cari nama event, artis, venue..." aria-label="Cari event">
        </div>

        <!-- Tabel Event -->
        <div class="admin-table-container">
            <table class="admin-table" id="eventTable">
                <thead>
                    <tr>
                        <th>Event &amp; Penampil</th>
                        <th>Genre</th>
                        <th>Jadwal</th>
                        <th>Venue</th>
                        <th>Harga</th>
                        <th>Stok</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($events) === 0): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 2.5rem; color: var(--ink-muted);">
                                Belum ada event konser yang terdaftar.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($events as $ev):
                            $accent     = $ev['accent_color'] ?: '#E04826';
                            $discount   = (int) $ev['discount_percent'];
                            $priceRaw   = (float) $ev['price'];
                            $priceFinal = (float) $ev['final_price'];
                            $quota      = (int) $ev['quota'];
                        ?>
                        <tr class="event-row"
                            data-title="<?php echo e(mb_strtolower($ev['title'])); ?>"
                            data-artist="<?php echo e(mb_strtolower($ev['artist'])); ?>"
                            data-venue="<?php echo e(mb_strtolower($ev['venue_name'])); ?>"
                            data-kategori="<?php echo e($ev['category_name']); ?>">
                            <td>
                                <div class="event-info-cell">
                                    <?php if (!empty($ev['poster'])): ?>
                                        <img class="event-thumb" src="<?php echo e(UPLOADS_URL . basename($ev['poster'])); ?>" alt="Poster <?php echo e($ev['title']); ?>">
                                    <?php else: ?>
                                        <div class="event-thumb-placeholder" style="background: <?php echo e($accent); ?>;">IMG</div>
                                    <?php endif; ?>
                                    <div>
                                        <div class="event-title"><?php echo e($ev['title']); ?></div>
                                        <div class="event-artist-sm"><?php echo e($ev['artist']); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge" style="background: <?php echo e($accent); ?>1A; color: <?php echo e($accent); ?>;">
                                    <?php echo e($ev['category_name']); ?>
                                </span>
                            </td>
                            <td class="tabular"><?php echo e(formatTanggalIndo($ev['event_date'])); ?></td>
                            <td>
                                <div><?php echo e($ev['venue_name']); ?></div>
                                <div class="event-artist-sm"><?php echo e($ev['venue_city']); ?></div>
                            </td>
                            <td class="tabular">
                                <strong><?php echo $priceFinal <= 0 ? 'Gratis' : e(formatRupiah($priceFinal)); ?></strong>
                                <?php if ($discount > 0): ?>
                                    <div class="event-artist-sm">
                                        <del><?php echo e(formatRupiah($priceRaw)); ?></del> (-<?php echo $discount; ?>%)
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="tabular">
                                <?php if ($quota <= 0): ?>
                                    <span class="badge badge-danger">Sold Out</span>
                                <?php elseif ($quota <= 10): ?>
                                    <span class="badge badge-pending"><?php echo number_format($quota); ?> (Sedikit)</span>
                                <?php else: ?>
                                    <span class="badge badge-success"><?php echo number_format($quota); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <a href="<?php echo e(BASE_URL); ?>pages/detail.php?id=<?php echo (int) $ev['id_event']; ?>" target="_blank" rel="noopener" class="button secondary button-sm">Lihat</a>
                                    <a href="<?php echo e(BASE_URL); ?>admin/edit.php?id=<?php echo (int) $ev['id_event']; ?>" class="button secondary button-sm">Edit</a>
                                    <a href="<?php echo e(BASE_URL); ?>pages/e_ticket.php?preview=1&id_event=<?php echo (int) $ev['id_event']; ?>" target="_blank" rel="noopener" class="button secondary button-sm" title="Preview E-Ticket">Pass</a>
                                    <button type="button" class="button danger-outline button-sm"
                                            onclick="openDeleteModal(<?php echo (int) $ev['id_event']; ?>, '<?php echo e(addslashes($ev['title'])); ?>')">
                                        Hapus
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<!-- Modal Hapus Event (POST + CSRF) -->
<div id="deleteModal" class="modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="deleteModalTitle">
    <div class="modal-box">
        <div class="modal-icon">
            <svg width="26" height="26" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
            </svg>
        </div>
        <h3 id="deleteModalTitle">Hapus Event Konser?</h3>
        <p id="deleteModalDesc">Event yang dihapus tidak dapat dipulihkan. Event yang sudah memiliki riwayat transaksi otomatis dilindungi oleh Foreign Key.</p>
        <form method="POST" action="<?php echo e(BASE_URL); ?>admin/hapus.php" id="deleteEventForm">
            <?php echo csrfField(); ?>
            <input type="hidden" name="id_event" id="deleteEventId" value="">
            <div class="modal-actions">
                <button type="button" class="button secondary" onclick="closeDeleteModal()">Batal</button>
                <button type="submit" class="button danger">Ya, Hapus</button>
            </div>
        </form>
    </div>
</div>

<script>
const deleteModal = document.getElementById('deleteModal');
const deleteIdEl  = document.getElementById('deleteEventId');
const deleteDesc  = document.getElementById('deleteModalDesc');

function openDeleteModal(id, title) {
    deleteIdEl.value = id;
    deleteDesc.textContent = 'Yakin ingin menghapus event "' + title + '"? Jika sudah ada transaksi terkait, penghapusan akan diblokir demi integritas data.';
    deleteModal.classList.add('open');
}
function closeDeleteModal() {
    deleteModal.classList.remove('open');
}
deleteModal.addEventListener('click', (e) => { if (e.target === deleteModal) closeDeleteModal(); });
document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeDeleteModal(); });

const searchInput = document.getElementById('searchInput');
const filterKat   = document.getElementById('filterKategori');
const rows        = document.querySelectorAll('.event-row');

function filterRows() {
    const q = searchInput.value.trim().toLowerCase();
    const k = filterKat.value;
    rows.forEach(r => {
        const okText = !q || r.dataset.title.includes(q) || r.dataset.artist.includes(q) || r.dataset.venue.includes(q);
        const okKat  = !k || r.dataset.kategori === k;
        r.style.display = (okText && okKat) ? '' : 'none';
    });
}
searchInput.addEventListener('input', filterRows);
filterKat.addEventListener('change', filterRows);
</script>
</body>
</html>
