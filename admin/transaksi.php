<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$stats = ambilStatistikAdmin();
$res   = $koneksi->query('SELECT * FROM v_transaction_summary ORDER BY created_at DESC');
$rows  = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

$pageTitle   = 'Data Transaksi — Admin';
$activeAdmin = 'transactions';

require __DIR__ . '/../includes/partials/head.php';
?>
<body>
<div class="admin-wrapper">
    <?php require __DIR__ . '/../includes/partials/admin_sidebar.php'; ?>

    <main id="main-content" class="admin-content">
        <div class="admin-header">
            <div>
                <h1>Data Seluruh Transaksi</h1>
                <p>Monitoring real-time pesanan tiket, status pembayaran, dan referensi gateway.</p>
            </div>
        </div>

        <section class="kpi-grid" aria-label="Ringkasan transaksi">
            <div class="kpi-card">
                <div class="kpi-label">Total Transaksi</div>
                <div class="kpi-value"><?php echo number_format($stats['total_orders']); ?></div>
                <div class="kpi-sub">Semua status</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Transaksi Lunas</div>
                <div class="kpi-value" style="color: var(--success);"><?php echo number_format($stats['paid_orders']); ?></div>
                <div class="kpi-sub"><?php echo number_format($stats['tickets_sold']); ?> tiket diterbitkan</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Menunggu Bayar</div>
                <div class="kpi-value" style="color: var(--warning);"><?php echo number_format($stats['pending_orders']); ?></div>
                <div class="kpi-sub">Batas waktu 24 jam</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Total Pendapatan</div>
                <div class="kpi-value" style="color: var(--accent);"><?php echo e(formatRupiah($stats['total_revenue'])); ?></div>
                <div class="kpi-sub">Dari transaksi lunas</div>
            </div>
        </section>

        <div class="admin-toolbar">
            <select id="filterStatus" class="form-select" style="width: 190px;" aria-label="Filter status pembayaran">
                <option value="">Semua Status</option>
                <option value="paid">Lunas</option>
                <option value="pending">Menunggu Pembayaran</option>
                <option value="expired">Kedaluwarsa</option>
                <option value="canceled">Dibatalkan</option>
                <option value="failed">Gagal</option>
            </select>
            <input type="search" id="searchTx" class="form-input" style="max-width: 300px;" placeholder="Cari kode pesanan, pembeli, event..." aria-label="Cari transaksi">
        </div>

        <div class="admin-table-container">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Kode &amp; Referensi</th>
                        <th>Pembeli</th>
                        <th>Event</th>
                        <th>Qty</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Waktu Pesan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($rows) > 0): ?>
                        <?php foreach ($rows as $row):
                            $badge = statusOrderMeta($row['payment_status']);
                        ?>
                        <tr class="tx-row"
                            data-status="<?php echo e($row['payment_status']); ?>"
                            data-search="<?php echo e(mb_strtolower($row['order_code'] . ' ' . $row['buyer_name'] . ' ' . $row['buyer_email'] . ' ' . $row['event_title'] . ' ' . ($row['payment_reference'] ?? ''))); ?>">
                            <td>
                                <div class="mono" style="font-weight: 700; font-size: 0.82rem;"><?php echo e($row['order_code']); ?></div>
                                <div class="event-artist-sm mono"><?php echo e($row['payment_reference'] ?: '-'); ?></div>
                            </td>
                            <td>
                                <div class="event-title"><?php echo e($row['buyer_name']); ?></div>
                                <div class="event-artist-sm"><?php echo e($row['buyer_email']); ?></div>
                            </td>
                            <td>
                                <div style="font-weight: 600;"><?php echo e($row['event_title']); ?></div>
                                <div class="event-artist-sm"><?php echo e($row['venue_name']); ?></div>
                            </td>
                            <td class="tabular" style="font-weight: 600;"><?php echo (int) $row['quantity']; ?></td>
                            <td class="tabular" style="font-weight: 700;">
                                <?php echo (float) $row['total_amount'] <= 0 ? 'Gratis' : e(formatRupiah($row['total_amount'])); ?>
                            </td>
                            <td>
                                <span class="badge <?php echo e($badge['class']); ?>"><?php echo e($badge['label']); ?></span>
                            </td>
                            <td class="tabular"><?php echo e(formatTanggalIndo($row['created_at'], true)); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 3rem; color: var(--ink-muted);">
                                Belum ada data transaksi pembelian tiket.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<script>
const statusEl = document.getElementById('filterStatus');
const searchEl = document.getElementById('searchTx');
const txRows   = document.querySelectorAll('.tx-row');

function filterTx() {
    const s = statusEl.value;
    const q = searchEl.value.trim().toLowerCase();
    txRows.forEach(r => {
        const okS = !s || r.dataset.status === s;
        const okQ = !q || r.dataset.search.includes(q);
        r.style.display = (okS && okQ) ? '' : 'none';
    });
}
statusEl.addEventListener('change', filterTx);
searchEl.addEventListener('input', filterTx);
</script>
</body>
</html>
