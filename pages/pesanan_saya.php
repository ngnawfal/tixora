<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$idUser = (int) $_SESSION['user_id'];

// Handle pembatalan pesanan dengan POST + CSRF
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order'])) {
    if (!verifyCsrf()) {
        setFlash('Permintaan tidak valid. Silakan coba kembali.', 'danger');
    } else {
        $idCancel = (int) ($_POST['id_order'] ?? 0);
        $stmt = $koneksi->prepare(
            "UPDATE orders
             SET payment_status = 'canceled', updated_at = NOW()
             WHERE id_order = ? AND id_user = ? AND payment_status = 'pending'"
        );
        $stmt->bind_param('ii', $idCancel, $idUser);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        if ($affected > 0) {
            setFlash('Pesanan berhasil dibatalkan.', 'info');
        } else {
            setFlash('Pesanan tidak dapat dibatalkan.', 'warning');
        }
    }
    header('Location: ' . BASE_URL . 'pages/pesanan_saya.php');
    exit;
}

$stmt = $koneksi->prepare(
    'SELECT * FROM v_transaction_summary WHERE id_user = ? ORDER BY created_at DESC'
);
$stmt->bind_param('i', $idUser);
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$flash     = getFlash();
$pageTitle = 'Pesanan Saya';
$activeNav = 'orders';

require __DIR__ . '/../includes/partials/head.php';
?>
<body>
<?php require __DIR__ . '/../includes/partials/navbar.php'; ?>

<main id="main-content" class="container">
    <div class="page-bar">
        <div>
            <h1>Pesanan Saya</h1>
            <p class="form-hint" style="margin-top: 0.25rem;">Pantau status pembayaran dan akses e-ticket resmi konser Anda.</p>
        </div>
        <a class="button primary button-pill" href="<?php echo e(BASE_URL); ?>index.php">Cari Event Lain</a>
    </div>

    <?php if ($flash): ?>
        <?php tampilkanPesan($flash['message'], $flash['type']); ?>
    <?php endif; ?>

    <?php if (count($orders) > 0): ?>
        <div style="display: grid; gap: 1rem;">
            <?php foreach ($orders as $o):
                $badge = statusOrderMeta($o['payment_status']);
            ?>
            <article class="order-card">
                <div class="order-card-header">
                    <div>
                        <div class="order-card-title"><?php echo e($o['event_title']); ?></div>
                        <div class="order-card-sub">
                            <?php echo e($o['event_artist']); ?> &bull; <?php echo e($o['venue_name']); ?>
                        </div>
                    </div>
                    <span class="badge <?php echo e($badge['class']); ?>"><?php echo e($badge['label']); ?></span>
                </div>

                <div class="order-meta">
                    <div>
                        <div class="order-meta-label">Kode Pesanan</div>
                        <div class="order-meta-value mono"><?php echo e($o['order_code']); ?></div>
                    </div>
                    <div>
                        <div class="order-meta-label">Jumlah Tiket</div>
                        <div class="order-meta-value"><?php echo (int) $o['quantity']; ?> tiket</div>
                    </div>
                    <div>
                        <div class="order-meta-label">Total Bayar</div>
                        <div class="order-meta-value"><?php echo (float) $o['total_amount'] <= 0 ? 'Gratis' : e(formatRupiah($o['total_amount'])); ?></div>
                    </div>
                    <div>
                        <div class="order-meta-label">Tanggal Pesan</div>
                        <div class="order-meta-value"><?php echo e(formatTanggalIndo($o['created_at'])); ?></div>
                    </div>
                </div>

                <div class="order-actions">
                    <?php if ($o['payment_status'] === 'pending'): ?>
                        <a href="<?php echo e(BASE_URL); ?>payment/process.php?reopen=<?php echo (int) $o['id_order']; ?>" class="button primary">
                            Lanjutkan Pembayaran
                        </a>
                        <form method="POST" action="" id="formCancel-<?php echo (int) $o['id_order']; ?>" style="margin: 0;">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="id_order" value="<?php echo (int) $o['id_order']; ?>">
                            <input type="hidden" name="cancel_order" value="1">
                            <button type="button" class="button danger-outline" onclick="openCancelModal(<?php echo (int) $o['id_order']; ?>, '<?php echo e($o['order_code']); ?>')">
                                Batalkan Pesanan
                            </button>
                        </form>
                    <?php elseif ($o['payment_status'] === 'paid'): ?>
                        <a href="<?php echo e(BASE_URL); ?>pages/e_ticket.php?id=<?php echo (int) $o['id_order']; ?>" target="_blank" rel="noopener" class="button dark">
                            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/>
                            </svg>
                            Buka E-Ticket
                        </a>
                    <?php else: ?>
                        <a href="<?php echo e(BASE_URL); ?>pages/detail.php?id=<?php echo (int) $o['id_event']; ?>" class="button secondary button-sm">
                            Lihat Detail Event
                        </a>
                    <?php endif; ?>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <div class="empty-state-icon">
                <svg width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/>
                </svg>
            </div>
            <h3>Belum ada pesanan tiket</h3>
            <p>Mulai jelajahi konser dan festival musik favorit Anda sekarang di TIXORA.</p>
            <a href="<?php echo e(BASE_URL); ?>index.php" class="button primary button-pill">Jelajahi Event</a>
        </div>
    <?php endif; ?>
</main>

<!-- Modal Konfirmasi Pembatalan -->
<div id="cancelModal" class="modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="cancelModalTitle">
    <div class="modal-box">
        <div class="modal-icon">
            <svg width="26" height="26" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
            </svg>
        </div>
        <h3 id="cancelModalTitle">Batalkan Pesanan?</h3>
        <p id="cancelModalText">Apakah Anda yakin ingin membatalkan pesanan ini? Tindakan ini tidak dapat dikembalikan.</p>
        <div class="modal-actions">
            <button type="button" class="button secondary" onclick="closeCancelModal()">Kembali</button>
            <button type="button" class="button danger" onclick="confirmCancel()">Ya, Batalkan</button>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/partials/footer.php'; ?>

<script>
let activeCancelId = null;
const cancelModal = document.getElementById('cancelModal');
const cancelText  = document.getElementById('cancelModalText');

function openCancelModal(orderId, code) {
    activeCancelId = orderId;
    cancelText.textContent = 'Pesanan ' + code + ' akan dibatalkan secara permanen. Anda tetap bisa membuat pesanan baru selama kuota tersedia.';
    cancelModal.classList.add('open');
}
function closeCancelModal() {
    activeCancelId = null;
    cancelModal.classList.remove('open');
}
function confirmCancel() {
    if (activeCancelId) {
        const form = document.getElementById('formCancel-' + activeCancelId);
        if (form) form.submit();
    }
}
cancelModal.addEventListener('click', (e) => { if (e.target === cancelModal) closeCancelModal(); });
document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeCancelModal(); });
</script>
</body>
</html>
