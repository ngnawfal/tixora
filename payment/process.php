<?php
// ============================================================
// TIXORA — Pemrosesan Checkout & Pembayaran
// - Harga & diskon 100% dihitung di server (anti price tampering)
// - Mendukung POST order baru (dengan CSRF) & GET ?reopen=<id> aman
// - Stok promo 100% dipotong atomik menggunakan transaksi FOR UPDATE
// ============================================================
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/gateway.php';

requireLogin();

$idUser = (int) $_SESSION['user_id'];
$verifySsl = strtolower((string) env('BAYARGG_VERIFY_SSL', 'true')) !== 'false';
$bayargg = new BayarGgGateway(
    (string) env('BAYARGG_API_KEY', ''),
    (string) env('BAYARGG_WEBHOOK_SECRET', ''),
    $verifySsl
);

$orderView   = null;
$payUrl      = '';
$invoiceId   = '';
$totalBayar  = 0.0;
$kodeUnik    = 0;

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['reopen'])) {
    // ── Mode Reopen Pesanan Pending Milik User Sendiri ──
    $reopenId = (int) $_GET['reopen'];
    $stmt = $koneksi->prepare(
        "SELECT * FROM v_transaction_summary
         WHERE id_order = ? AND id_user = ? AND payment_status = 'pending'
         LIMIT 1"
    );
    $stmt->bind_param('ii', $reopenId, $idUser);
    $stmt->execute();
    $orderView = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$orderView) {
        setFlash('Pesanan pending tidak ditemukan atau sudah diproses.', 'warning');
        header('Location: ' . BASE_URL . 'pages/pesanan_saya.php');
        exit;
    }

    $idOrder    = (int) $orderView['id_order'];
    $payUrl     = (string) ($orderView['payment_url'] ?? '');
    $invoiceId  = (string) ($orderView['payment_reference'] ?? '');
    $totalBayar = (float) $orderView['total_amount'];

    if ($payUrl === '') {
        setFlash('Tautan pembayaran lama tidak tersedia. Silakan batalkan dan buat pesanan baru.', 'warning');
        header('Location: ' . BASE_URL . 'pages/pesanan_saya.php');
        exit;
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        setFlash('Sesi checkout tidak valid. Silakan ulangi dari halaman pemesanan.', 'danger');
        header('Location: ' . BASE_URL . 'index.php');
        exit;
    }

    $idEvent     = (int) ($_POST['id_event'] ?? 0);
    $jumlahTiket = (int) ($_POST['jumlah_tiket'] ?? 0);
    $buyerName   = trim((string) ($_POST['nama_pembeli'] ?? ''));
    $buyerEmail  = strtolower(trim((string) ($_POST['email_pembeli'] ?? '')));
    $buyerPhone  = trim((string) ($_POST['no_hp'] ?? ''));

    $event = ambilEventById($idEvent);
    if (!$event) {
        setFlash('Event tidak ditemukan.', 'danger');
        header('Location: ' . BASE_URL . 'index.php');
        exit;
    }

    $maxAllowed = min((int) $event['quota'], 10);
    if (
        $jumlahTiket < 1 || $jumlahTiket > $maxAllowed ||
        $buyerName === '' || !filter_var($buyerEmail, FILTER_VALIDATE_EMAIL) ||
        !isValidPhone($buyerPhone)
    ) {
        setFlash('Data pemesan atau jumlah tiket tidak valid. Periksa kembali formulir Anda.', 'danger');
        header('Location: ' . BASE_URL . 'pages/beli_tiket.php?id=' . $idEvent);
        exit;
    }

    // Hitung harga mutlak di server dari data database
    $unitPrice       = (float) $event['price'];
    $discountPercent = (int) $event['discount_percent'];
    $hargaSatuanNet  = hitungHargaAkhir($unitPrice, $discountPercent);
    $totalHarga      = round($hargaSatuanNet * $jumlahTiket, 2);
    $orderCode       = generateOrderCode();

    $stmt = $koneksi->prepare(
        "INSERT INTO orders
            (order_code, id_user, id_event, quantity, unit_price, discount_percent, total_amount,
             payment_status, buyer_name, buyer_email, buyer_phone, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, NOW())"
    );
    $stmt->bind_param(
        'siiididsss',
        $orderCode,
        $idUser,
        $idEvent,
        $jumlahTiket,
        $unitPrice,
        $discountPercent,
        $totalHarga,
        $buyerName,
        $buyerEmail,
        $buyerPhone
    );
    if (!$stmt->execute()) {
        $stmt->close();
        setFlash('Gagal membuat pesanan. Silakan coba lagi.', 'danger');
        header('Location: ' . BASE_URL . 'pages/beli_tiket.php?id=' . $idEvent);
        exit;
    }
    $idOrder = (int) $koneksi->insert_id;
    $stmt->close();

    // Promo 100% (Gratis) — hanya berlaku jika server memvalidasi harga akhir = 0
    if ($totalHarga <= 0) {
        $resPaid = selesaikanPembayaranOrder($idOrder, 'PROMO-100-' . $orderCode, 'promo');
        if ($resPaid['ok']) {
            header('Location: ' . BASE_URL . 'payment/success.php?order_id=' . $idOrder);
            exit;
        }
        // Stok habis saat race condition -> batalkan order pending
        $cancel = $koneksi->prepare("UPDATE orders SET payment_status = 'failed' WHERE id_order = ?");
        $cancel->bind_param('i', $idOrder);
        $cancel->execute();
        $cancel->close();

        setFlash('Maaf, kuota tiket baru saja habis terjual.', 'danger');
        header('Location: ' . BASE_URL . 'pages/detail.php?id=' . $idEvent);
        exit;
    }

    // Buat invoice ke Bayar.GG
    $callbackUrl = rtrim(BASE_URL, '/') . '/payment/webhook.php';
    $redirectUrl = rtrim(BASE_URL, '/') . '/payment/success.php?order_id=' . $idOrder;

    $invoice = $bayargg->createPayment(
        (int) round($totalHarga),
        'TIXORA ' . $orderCode . ' — ' . $event['title'],
        $buyerName,
        $buyerEmail,
        $buyerPhone,
        $callbackUrl,
        $redirectUrl,
        'qris'
    );

    if (!$invoice || empty($invoice['success']) || empty($invoice['data']['payment_url'])) {
        $apiErr = is_array($invoice)
            ? ($invoice['message'] ?? $invoice['error'] ?? 'Gagal membuat invoice pembayaran.')
            : 'Tidak dapat menghubungi payment gateway.';

        $fail = $koneksi->prepare("UPDATE orders SET payment_status = 'failed' WHERE id_order = ?");
        $fail->bind_param('i', $idOrder);
        $fail->execute();
        $fail->close();

        setFlash('Pembayaran gagal dimulai: ' . $apiErr, 'danger');
        header('Location: ' . BASE_URL . 'pages/beli_tiket.php?id=' . $idEvent);
        exit;
    }

    $payUrl     = (string) $invoice['data']['payment_url'];
    $invoiceId  = (string) $invoice['data']['invoice_id'];
    $totalBayar = (float) ($invoice['data']['final_amount'] ?? $totalHarga);
    $kodeUnik   = (int) ($invoice['data']['unique_code'] ?? 0);

    $upd = $koneksi->prepare(
        "UPDATE orders SET payment_reference = ?, payment_url = ?, payment_method = 'qris' WHERE id_order = ?"
    );
    $upd->bind_param('ssi', $invoiceId, $payUrl, $idOrder);
    $upd->execute();
    $upd->close();

    // Muat view lengkap pesanan untuk tampilan checkout
    $vStmt = $koneksi->prepare('SELECT * FROM v_transaction_summary WHERE id_order = ? LIMIT 1');
    $vStmt->bind_param('i', $idOrder);
    $vStmt->execute();
    $orderView = $vStmt->get_result()->fetch_assoc();
    $vStmt->close();
} else {
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}

$methods = [
    ['id' => 'qris',       'label' => 'QRIS Nasional', 'desc' => 'Scan dari semua e-wallet & mobile banking'],
    ['id' => 'gopay_qris', 'label' => 'GoPay QRIS',    'desc' => 'Pembayaran instan via GoPay'],
    ['id' => 'qris_user',  'label' => 'BRI QRIS',      'desc' => 'Pembayaran via QRIS BRI Merchant'],
    ['id' => 'ovo',        'label' => 'OVO',           'desc' => 'Bayar langsung dari saldo OVO'],
];

$pageTitle = 'Pembayaran ' . $orderView['order_code'];
require __DIR__ . '/../includes/partials/head.php';
?>
<body>
<header class="checkout-topbar">
    <div class="checkout-topbar-inner">
        <a href="<?php echo e(BASE_URL); ?>index.php" class="navbar-brand">
            <img src="<?php echo e(BASE_URL); ?>assets/icon/Logo_Light.svg" alt="TIXORA" width="150" height="38">
        </a>
        <span class="mono" style="font-size: 0.82rem; color: var(--ink-muted); font-weight: 600;">
            <?php echo e($orderView['order_code']); ?>
        </span>
    </div>
</header>

<main id="main-content" class="checkout-wrap">
    <div class="steps" aria-label="Tahap pemesanan">
        <div class="step done">
            <div class="step-num">&#10003;</div>
            <span>Detail &amp; Pemesan</span>
        </div>
        <div class="step-divider"></div>
        <div class="step active">
            <div class="step-num">2</div>
            <span>Pembayaran</span>
        </div>
        <div class="step-divider"></div>
        <div class="step">
            <div class="step-num">3</div>
            <span>E-Ticket Terbit</span>
        </div>
    </div>

    <div class="checkout-grid">
        <div>
            <!-- Ringkasan Pesanan -->
            <section class="panel-card">
                <div class="panel-header"><span>Ringkasan Pesanan</span></div>
                <div class="panel-body">
                    <div class="summary-event">
                        <div class="summary-event-img">
                            <?php if (!empty($orderView['event_poster'])): ?>
                                <img src="<?php echo e(UPLOADS_URL . basename($orderView['event_poster'])); ?>" alt="Poster <?php echo e($orderView['event_title']); ?>">
                            <?php endif; ?>
                        </div>
                        <div>
                            <div style="font-weight: 700; font-size: 0.975rem;"><?php echo e($orderView['event_title']); ?></div>
                            <div class="form-hint"><?php echo e($orderView['event_artist']); ?> &bull; <?php echo e($orderView['venue_name']); ?></div>
                        </div>
                    </div>

                    <div class="result-row">
                        <span>Harga per tiket</span>
                        <strong class="tabular"><?php echo e(formatRupiah(hitungHargaAkhir((float) $orderView['unit_price'], (int) $orderView['discount_percent']))); ?></strong>
                    </div>
                    <div class="result-row">
                        <span>Jumlah tiket</span>
                        <strong class="tabular">&times; <?php echo (int) $orderView['quantity']; ?></strong>
                    </div>
                    <?php if ($kodeUnik > 0): ?>
                    <div class="result-row">
                        <span>Kode unik gateway</span>
                        <strong class="tabular">+Rp <?php echo number_format($kodeUnik, 0, ',', '.'); ?></strong>
                    </div>
                    <?php endif; ?>
                    <div class="result-row total">
                        <span>Total Pembayaran</span>
                        <strong><?php echo e(formatRupiah($totalBayar)); ?></strong>
                    </div>
                </div>
            </section>

            <!-- Data Pembeli -->
            <section class="panel-card">
                <div class="panel-header"><span>Informasi Pembeli</span></div>
                <div class="panel-body buyer-grid">
                    <div>
                        <div class="buyer-field-label">Nama Lengkap</div>
                        <div class="buyer-field-value"><?php echo e($orderView['buyer_name']); ?></div>
                    </div>
                    <div>
                        <div class="buyer-field-label">Email</div>
                        <div class="buyer-field-value"><?php echo e($orderView['buyer_email']); ?></div>
                    </div>
                    <div>
                        <div class="buyer-field-label">Nomor HP</div>
                        <div class="buyer-field-value"><?php echo e($orderView['buyer_phone']); ?></div>
                    </div>
                    <div>
                        <div class="buyer-field-label">Invoice Gateway</div>
                        <div class="buyer-field-value mono"><?php echo e($invoiceId ?: '-'); ?></div>
                    </div>
                </div>
            </section>
        </div>

        <div>
            <section class="panel-card">
                <div class="panel-header"><span>Metode Pembayaran</span></div>
                <div class="method-list">
                    <?php foreach ($methods as $idx => $m): ?>
                        <label class="method-option <?php echo $idx === 0 ? 'selected' : ''; ?>">
                            <input type="radio" name="payment_method" value="<?php echo e($m['id']); ?>" <?php echo $idx === 0 ? 'checked' : ''; ?>>
                            <span class="method-radio"></span>
                            <span class="method-icon"><?php echo e(strtoupper(substr($m['id'], 0, 4))); ?></span>
                            <span>
                                <span class="method-name" style="display:block;"><?php echo e($m['label']); ?></span>
                                <span class="method-desc" style="display:block;"><?php echo e($m['desc']); ?></span>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <div class="panel-body" style="border-top: 1px solid var(--border);">
                    <div style="display:flex; justify-content:space-between; align-items:baseline; margin-bottom:1rem;">
                        <span class="form-hint">Total Tagihan</span>
                        <strong class="tabular" style="font-family:'Outfit',sans-serif; font-size:1.35rem; color:var(--accent);">
                            <?php echo e(formatRupiah($totalBayar)); ?>
                        </strong>
                    </div>
                    <a href="<?php echo e($payUrl); ?>" class="button primary button-lg full-width" id="btnPay" target="_blank" rel="noopener">
                        Bayar Sekarang via Bayar.GG
                    </a>
                    <p class="form-hint" style="text-align:center; margin-top:0.75rem;">
                        Selesaikan pembayaran dalam 24 jam sebelum pesanan otomatis kedaluwarsa.
                    </p>
                </div>
            </section>

            <div style="text-align:center;">
                <a href="<?php echo e(BASE_URL); ?>pages/pesanan_saya.php" class="form-hint" style="font-weight:600;">
                    &larr; Kembali ke Pesanan Saya
                </a>
            </div>
        </div>
    </div>
</main>

<script>
document.querySelectorAll('.method-option').forEach(opt => {
    opt.addEventListener('click', function() {
        document.querySelectorAll('.method-option').forEach(o => o.classList.remove('selected'));
        this.classList.add('selected');
    });
});
const btnPay = document.getElementById('btnPay');
if (btnPay) {
    btnPay.addEventListener('click', () => {
        setTimeout(() => {
            window.location.href = <?php echo json_encode(BASE_URL . 'payment/waiting.php?id=' . (int) $orderView['id_order']); ?>;
        }, 500);
    });
}
</script>
</body>
</html>
