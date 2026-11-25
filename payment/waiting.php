<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$idOrder = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$idUser  = (int) $_SESSION['user_id'];

$stmt = $koneksi->prepare('SELECT * FROM v_transaction_summary WHERE id_order = ? AND id_user = ? LIMIT 1');
$stmt->bind_param('ii', $idOrder, $idUser);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    setFlash('Data pesanan tidak ditemukan.', 'danger');
    header('Location: ' . BASE_URL . 'pages/pesanan_saya.php');
    exit;
}

if ($order['payment_status'] === 'paid') {
    header('Location: ' . BASE_URL . 'payment/success.php?order_id=' . $idOrder);
    exit;
}

$pageTitle = 'Menunggu Pembayaran ' . $order['order_code'];
require __DIR__ . '/../includes/partials/head.php';
?>
<body>
<main id="main-content" class="auth-container">
    <div class="auth-card" style="max-width: 470px; text-align: center;">
        <div class="spinner" aria-hidden="true"></div>
        <h1 style="font-size: 1.5rem; margin-bottom: 0.5rem;">Menunggu Pembayaran</h1>
        <p class="form-hint" style="font-size: 0.9rem; line-height: 1.65; margin-bottom: 1.5rem;">
            Selesaikan pembayaran Anda di tab <strong>Bayar.GG</strong>. Halaman ini memeriksa status pembayaran otomatis setiap 3 detik.
        </p>

        <div class="price-summary" style="margin-bottom: 1.5rem; text-align: left;">
            <div class="price-row">
                <span>Kode Pesanan</span>
                <strong class="mono"><?php echo e($order['order_code']); ?></strong>
            </div>
            <div class="price-row">
                <span>Event</span>
                <strong><?php echo e($order['event_title']); ?></strong>
            </div>
            <div class="price-row total">
                <span>Total Tagihan</span>
                <span><?php echo e(formatRupiah($order['total_amount'])); ?></span>
            </div>
        </div>

        <div style="display: grid; gap: 0.75rem;">
            <?php if (!empty($order['payment_url'])): ?>
                <a href="<?php echo e($order['payment_url']); ?>" class="button primary button-lg full-width" target="_blank" rel="noopener">
                    Buka Ulang Halaman Pembayaran
                </a>
            <?php else: ?>
                <a href="<?php echo e(BASE_URL); ?>payment/process.php?reopen=<?php echo $idOrder; ?>" class="button primary button-lg full-width">
                    Lihat Instruksi Pembayaran
                </a>
            <?php endif; ?>
            <a href="<?php echo e(BASE_URL); ?>pages/pesanan_saya.php" class="button secondary full-width">
                Kembali ke Pesanan Saya
            </a>
        </div>
    </div>
</main>

<script>
const statusUrl  = <?php echo json_encode(BASE_URL . 'payment/check_status.php?order_id=' . $idOrder); ?>;
const successUrl = <?php echo json_encode(BASE_URL . 'payment/success.php?order_id=' . $idOrder); ?>;
const ordersUrl  = <?php echo json_encode(BASE_URL . 'pages/pesanan_saya.php'); ?>;

setInterval(() => {
    fetch(statusUrl, { credentials: 'same-origin' })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'paid') {
                window.location.href = successUrl;
            } else if (data.status === 'canceled' || data.status === 'expired' || data.status === 'failed') {
                window.location.href = ordersUrl;
            }
        })
        .catch(() => {});
}, 3000);
</script>
</body>
</html>
