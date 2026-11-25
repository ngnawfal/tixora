<?php
// ============================================================
// TIXORA — Halaman Konfirmasi Pembayaran
// PERBAIKAN KEAMANAN KRITIS:
// - Wajib login + verifikasi kepemilikan pesanan (anti IDOR)
// - TIDAK PERNAH mempercayai $_GET['status'] untuk mengubah DB
// - Status hanya berubah melalui verifikasi server-side / webhook
// ============================================================
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/gateway.php';

requireLogin();

$idOrder = isset($_GET['order_id']) ? (int) $_GET['order_id'] : 0;
$idUser  = (int) $_SESSION['user_id'];

if ($idOrder <= 0) {
    header('Location: ' . BASE_URL . 'pages/pesanan_saya.php');
    exit;
}

// Cek kepemilikan pesanan (atau admin)
if (isAdmin()) {
    $stmt = $koneksi->prepare('SELECT * FROM v_transaction_summary WHERE id_order = ? LIMIT 1');
    $stmt->bind_param('i', $idOrder);
} else {
    $stmt = $koneksi->prepare('SELECT * FROM v_transaction_summary WHERE id_order = ? AND id_user = ? LIMIT 1');
    $stmt->bind_param('ii', $idOrder, $idUser);
}
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    setFlash('Pesanan tidak ditemukan atau Anda tidak memiliki akses ke pesanan ini.', 'danger');
    header('Location: ' . BASE_URL . 'pages/pesanan_saya.php');
    exit;
}

// Jika masih pending, lakukan verifikasi server-side ke payment gateway (bukan dari URL!)
if ($order['payment_status'] === 'pending' && !empty($order['payment_reference'])) {
    $apiKey = (string) env('BAYARGG_API_KEY', '');
    if ($apiKey !== '') {
        $verifySsl = strtolower((string) env('BAYARGG_VERIFY_SSL', 'true')) !== 'false';
        $gw = new BayarGgGateway($apiKey, '', $verifySsl);
        $check = $gw->checkPayment($order['payment_reference']);
        $gwStatus = $check['data']['status'] ?? $check['status'] ?? '';
        if ($gwStatus === 'paid') {
            $res = selesaikanPembayaranOrder($idOrder, $order['payment_reference'], 'qris');
            if ($res['ok']) {
                // Muat ulang data terbaru
                $reload = $koneksi->prepare('SELECT * FROM v_transaction_summary WHERE id_order = ? LIMIT 1');
                $reload->bind_param('i', $idOrder);
                $reload->execute();
                $order = $reload->get_result()->fetch_assoc();
                $reload->close();
            }
        }
    }
}

$status    = $order['payment_status'];
$isPaid    = ($status === 'paid');
$isPending = ($status === 'pending');

$pageTitle = $isPaid ? 'Pembayaran Berhasil' : ($isPending ? 'Menunggu Konfirmasi' : 'Status Pesanan');
require __DIR__ . '/../includes/partials/head.php';
?>
<body>
<header class="checkout-topbar">
    <div class="checkout-topbar-inner">
        <a href="<?php echo e(BASE_URL); ?>index.php" class="navbar-brand">
            <img src="<?php echo e(BASE_URL); ?>assets/icon/Logo_Light.svg" alt="TIXORA" width="150" height="38">
        </a>
        <span class="mono" style="font-size: 0.82rem; color: var(--ink-muted); font-weight: 600;">
            <?php echo e($order['order_code']); ?>
        </span>
    </div>
</header>

<main id="main-content" class="result-wrap">
    <div class="panel-card">
        <?php if ($isPending): ?>
            <div class="refresh-bar"><div class="refresh-bar-fill"></div></div>
        <?php endif; ?>

        <div class="result-banner">
            <?php if ($isPaid): ?>
                <div class="result-icon success">&#10003;</div>
                <h2>Pembayaran Terkonfirmasi</h2>
                <p class="form-hint" style="margin-top: 0.35rem; font-size: 0.9rem;">
                    E-Ticket resmi TIXORA Anda telah aktif dan siap digunakan di pintu masuk venue.
                </p>
            <?php elseif ($isPending): ?>
                <div class="result-icon pending">&#9201;</div>
                <h2>Menunggu Konfirmasi Pembayaran</h2>
                <p class="form-hint" style="margin-top: 0.35rem; font-size: 0.9rem;">
                    Status diperbarui otomatis setelah pembayaran diverifikasi oleh sistem gateway.
                </p>
            <?php else: ?>
                <div class="result-icon failed">&#10007;</div>
                <h2>Pesanan Tidak Aktif</h2>
                <p class="form-hint" style="margin-top: 0.35rem; font-size: 0.9rem;">
                    Pesanan ini berstatus <?php echo e(statusOrderMeta($status)['label']); ?>. Silakan buat pesanan baru jika diperlukan.
                </p>
            <?php endif; ?>
        </div>

        <div class="panel-body">
            <div class="result-row">
                <span>Kode Pesanan</span>
                <strong class="mono"><?php echo e($order['order_code']); ?></strong>
            </div>
            <div class="result-row">
                <span>Event Konser</span>
                <strong><?php echo e($order['event_title']); ?></strong>
            </div>
            <div class="result-row">
                <span>Penampil</span>
                <strong><?php echo e($order['event_artist']); ?></strong>
            </div>
            <div class="result-row">
                <span>Jadwal</span>
                <strong><?php echo e(formatTanggalIndo($order['event_date'], true)); ?></strong>
            </div>
            <div class="result-row">
                <span>Venue</span>
                <strong><?php echo e($order['venue_name']); ?></strong>
            </div>
            <div class="result-row">
                <span>Jumlah Tiket</span>
                <strong class="tabular"><?php echo (int) $order['quantity']; ?> tiket</strong>
            </div>
            <div class="result-row">
                <span>Pembeli</span>
                <strong><?php echo e($order['buyer_name']); ?></strong>
            </div>
            <div class="result-row total">
                <span>Total Pembayaran</span>
                <strong><?php echo (float) $order['total_amount'] <= 0 ? 'Gratis' : e(formatRupiah($order['total_amount'])); ?></strong>
            </div>
        </div>

        <div class="panel-body" style="border-top: 1px solid var(--border); display: flex; gap: 0.75rem; flex-wrap: wrap;">
            <?php if ($isPaid): ?>
                <a href="<?php echo e(BASE_URL); ?>pages/pesanan_saya.php" class="button secondary" style="flex:1;">Pesanan Saya</a>
                <a href="<?php echo e(BASE_URL); ?>pages/e_ticket.php?id=<?php echo (int) $order['id_order']; ?>" target="_blank" rel="noopener" class="button dark" style="flex:1;">
                    Buka E-Ticket
                </a>
            <?php elseif ($isPending): ?>
                <a href="<?php echo e(BASE_URL); ?>index.php" class="button secondary" style="flex:1;">Beranda</a>
                <a href="<?php echo e(BASE_URL); ?>pages/pesanan_saya.php" class="button primary" style="flex:1;">Ke Pesanan Saya</a>
            <?php else: ?>
                <a href="<?php echo e(BASE_URL); ?>index.php" class="button secondary" style="flex:1;">Beranda</a>
                <a href="<?php echo e(BASE_URL); ?>pages/detail.php?id=<?php echo (int) $order['id_event']; ?>" class="button primary" style="flex:1;">Pesan Ulang</a>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php if ($isPending): ?>
<script>
setTimeout(() => window.location.reload(), 10000);
</script>
<?php endif; ?>
</body>
</html>
