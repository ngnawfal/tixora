<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$idOrder = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$idUser  = (int) $_SESSION['user_id'];
$isPreview = isset($_GET['preview']) && isAdmin();
$order = null;

if ($isPreview) {
    $idEvent = isset($_GET['id_event']) ? (int) $_GET['id_event'] : 0;
    $ev = ambilEventById($idEvent);
    if ($ev) {
        $order = [
            'id_order'      => 0,
            'order_code'    => 'TXR-PREVIEW-MODE',
            'ticket_token'  => 'TXR-PREVIEW-' . strtoupper(substr(md5((string) $idEvent), 0, 10)),
            'buyer_name'    => $_SESSION['nama_lengkap'] . ' (Preview)',
            'quantity'      => 2,
            'event_title'   => $ev['title'],
            'event_artist'  => $ev['artist'],
            'event_date'    => $ev['event_date'],
            'venue_name'    => $ev['venue_name'],
            'venue_city'    => $ev['venue_city'],
            'category_name' => $ev['category_name'],
            'accent_color'  => $ev['accent_color'] ?: '#E04826',
            'event_poster'  => $ev['poster'],
        ];
    }
} else {
    $stmt = $koneksi->prepare(
        "SELECT * FROM v_transaction_summary
         WHERE id_order = ? AND id_user = ? AND payment_status = 'paid'
         LIMIT 1"
    );
    $stmt->bind_param('ii', $idOrder, $idUser);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if (!$order) {
    setFlash('E-Ticket tidak ditemukan atau pembayaran belum terkonfirmasi lunas.', 'warning');
    header('Location: ' . BASE_URL . ($isPreview ? 'admin/' : 'pages/pesanan_saya.php'));
    exit;
}

$ticketCode = $order['ticket_token'] ?: generateTicketToken((int) $order['id_order'], $idUser);
$qrUrl      = 'https://api.qrserver.com/v1/create-qr-code/?size=280x280&margin=8&data=' . rawurlencode($ticketCode);
$posterFile = !empty($order['event_poster']) ? basename($order['event_poster']) : '';
$posterUrl  = $posterFile !== '' ? UPLOADS_URL . rawurlencode($posterFile) : '';
$themeColor = preg_match('/^#[0-9A-Fa-f]{6}$/', (string) ($order['accent_color'] ?? ''))
    ? $order['accent_color']
    : '#E04826';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Ticket <?php echo e($order['event_title']); ?> — <?php echo e(APP_NAME); ?></title>
    <link rel="icon" href="<?php echo e(BASE_URL); ?>assets/icon/Icon_Mark.svg" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@600;700&family=Outfit:wght@600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap">
    <style>
        :root {
            --bg: #141413;
            --accent: <?php echo $themeColor; ?>;
            --ink: #141413;
            --muted: #6F6C64;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
            background: var(--bg);
            background-image: radial-gradient(640px circle at 50% 0%, rgba(224, 72, 38, 0.22), transparent 65%);
            color: #FAF9F5;
            min-height: 100dvh;
            padding: 2.5rem 1.25rem 3.5rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .pass-brand {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            margin-bottom: 1.5rem;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.78rem;
            letter-spacing: 0.14em;
            color: #C7C4BA;
            text-transform: uppercase;
        }
        .ticket-card {
            width: 100%;
            max-width: 430px;
            background: #FFFFFF;
            color: var(--ink);
            border-radius: 22px;
            overflow: hidden;
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.45);
        }
        .ticket-hero {
            position: relative;
            height: 200px;
            background: var(--accent);
            overflow: hidden;
        }
        .ticket-hero img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            opacity: 0.82;
        }
        .ticket-hero-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(to top, rgba(20, 20, 19, 0.92) 0%, rgba(20, 20, 19, 0.25) 60%, transparent 100%);
            padding: 1.35rem 1.5rem;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            color: #FAF9F5;
        }
        .ticket-badge-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: auto;
        }
        .ticket-pill {
            display: inline-block;
            background: rgba(255, 255, 255, 0.18);
            backdrop-filter: blur(8px);
            padding: 0.25rem 0.7rem;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        .ticket-title {
            font-family: 'Outfit', sans-serif;
            font-size: 1.5rem;
            font-weight: 800;
            line-height: 1.15;
            margin-bottom: 0.2rem;
        }
        .ticket-artist {
            font-size: 0.9rem;
            color: rgba(250, 249, 245, 0.82);
        }
        .ticket-body {
            padding: 1.6rem 1.5rem 1.25rem;
        }
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.15rem 1rem;
        }
        .info-label {
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--muted);
            margin-bottom: 0.2rem;
        }
        .info-value {
            font-size: 0.92rem;
            font-weight: 700;
            color: var(--ink);
            line-height: 1.3;
        }
        .ticket-perf {
            position: relative;
            height: 26px;
            background: #fff;
        }
        .ticket-perf::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 1.5rem;
            right: 1.5rem;
            border-top: 2px dashed #D8D4C9;
        }
        .cutout {
            position: absolute;
            top: 1px;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: var(--bg);
        }
        .cutout.left { left: -12px; }
        .cutout.right { right: -12px; }

        .qr-box {
            margin: 0.5rem 1.5rem 1.5rem;
            padding: 1.35rem;
            background: #F7F6F2;
            border: 1px dashed #D3CFC2;
            border-radius: 14px;
            text-align: center;
        }
        .qr-box img {
            width: 176px;
            height: 176px;
            margin: 0 auto 0.85rem;
            border-radius: 8px;
            background: #fff;
            padding: 6px;
        }
        .qr-token {
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.84rem;
            font-weight: 700;
            letter-spacing: 0.1em;
            color: var(--ink);
        }
        .qr-hint {
            margin-top: 0.35rem;
            font-size: 0.74rem;
            color: var(--muted);
        }
        .ticket-actions {
            width: 100%;
            max-width: 430px;
            margin-top: 1.25rem;
            display: grid;
            gap: 0.65rem;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            width: 100%;
            padding: 0.9rem 1.25rem;
            border-radius: 12px;
            font-family: inherit;
            font-size: 0.925rem;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            border: 1px solid transparent;
            transition: transform 0.15s ease, background-color 0.18s ease;
        }
        .btn:active { transform: scale(0.98); }
        .btn-primary { background: #E04826; color: #fff; }
        .btn-primary:hover { background: #C23819; }
        .btn-outline { background: transparent; color: #FAF9F5; border-color: rgba(255, 255, 255, 0.22); }
        .btn-outline:hover { background: rgba(255, 255, 255, 0.08); }

        @media print {
            body { background: #fff; padding: 0; color: #000; }
            .pass-brand, .ticket-actions, .cutout { display: none !important; }
            .ticket-card { box-shadow: none; border: 1px solid #D3CFC2; }
        }
    </style>
</head>
<body>
    <div class="pass-brand">
        <span>TIXORA OFFICIAL E-PASS</span>
    </div>

    <article class="ticket-card">
        <div class="ticket-hero">
            <?php if ($posterUrl !== ''): ?>
                <img src="<?php echo e($posterUrl); ?>" alt="Poster <?php echo e($order['event_title']); ?>">
            <?php endif; ?>
            <div class="ticket-hero-overlay">
                <div class="ticket-badge-row">
                    <span class="ticket-pill"><?php echo e($order['category_name']); ?></span>
                    <span class="ticket-pill">TIXORA PASS</span>
                </div>
                <h1 class="ticket-title"><?php echo e($order['event_title']); ?></h1>
                <p class="ticket-artist"><?php echo e($order['event_artist']); ?></p>
            </div>
        </div>

        <div class="ticket-body">
            <div class="info-grid">
                <div>
                    <div class="info-label">Pemegang Tiket</div>
                    <div class="info-value"><?php echo e($order['buyer_name']); ?></div>
                </div>
                <div>
                    <div class="info-label">Kode Pesanan</div>
                    <div class="info-value" style="font-family: 'JetBrains Mono', monospace; font-size: 0.82rem;"><?php echo e($order['order_code']); ?></div>
                </div>
                <div>
                    <div class="info-label">Tanggal &amp; Jam</div>
                    <div class="info-value"><?php echo e(formatTanggalIndo($order['event_date'], true)); ?></div>
                </div>
                <div>
                    <div class="info-label">Jumlah Tiket</div>
                    <div class="info-value"><?php echo (int) $order['quantity']; ?> Orang (GA)</div>
                </div>
                <div style="grid-column: 1 / -1;">
                    <div class="info-label">Venue / Lokasi</div>
                    <div class="info-value"><?php echo e($order['venue_name']); ?>, <?php echo e($order['venue_city']); ?></div>
                </div>
            </div>
        </div>

        <div class="ticket-perf" aria-hidden="true">
            <div class="cutout left"></div>
            <div class="cutout right"></div>
        </div>

        <div class="qr-box">
            <img src="<?php echo e($qrUrl); ?>" alt="QR Code Verifikasi Tiket <?php echo e($ticketCode); ?>">
            <div class="qr-token"><?php echo e($ticketCode); ?></div>
            <p class="qr-hint">Tunjukkan QR code ini kepada petugas di gerbang masuk venue.</p>
        </div>
    </article>

    <div class="ticket-actions">
        <button type="button" class="btn btn-primary" onclick="window.print()">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v8H6z"/>
            </svg>
            Cetak / Simpan PDF E-Ticket
        </button>
        <?php if ($isPreview): ?>
            <a href="<?php echo e(BASE_URL); ?>admin/" class="btn btn-outline">Kembali ke Panel Admin</a>
        <?php else: ?>
            <a href="<?php echo e(BASE_URL); ?>pages/pesanan_saya.php" class="btn btn-outline">Kembali ke Pesanan Saya</a>
        <?php endif; ?>
    </div>
</body>
</html>
