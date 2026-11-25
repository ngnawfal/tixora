<?php
// ============================================================
// TIXORA — Endpoint Polling Status Pembayaran (Owner-Scoped)
// ============================================================
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/gateway.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$orderId = isset($_GET['order_id']) ? (int) $_GET['order_id'] : 0;
$userId  = (int) $_SESSION['user_id'];

if ($orderId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid order_id']);
    exit;
}

$stmt = $koneksi->prepare(
    'SELECT id_order, payment_status, payment_reference, total_amount
     FROM orders
     WHERE id_order = ? AND id_user = ?
     LIMIT 1'
);
$stmt->bind_param('ii', $orderId, $userId);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    http_response_code(404);
    echo json_encode(['error' => 'Order not found']);
    exit;
}

$status = $order['payment_status'];

// Opsional: sinkronisasi server-side dengan Bayar.GG jika masih pending dan API key aktif
if ($status === 'pending' && !empty($order['payment_reference'])) {
    $apiKey = (string) env('BAYARGG_API_KEY', '');
    if ($apiKey !== '') {
        $verifySsl = strtolower((string) env('BAYARGG_VERIFY_SSL', 'true')) !== 'false';
        $gw = new BayarGgGateway($apiKey, '', $verifySsl);
        $check = $gw->checkPayment($order['payment_reference']);
        $gwStatus = $check['data']['status'] ?? $check['status'] ?? '';
        if ($gwStatus === 'paid') {
            $res = selesaikanPembayaranOrder($orderId, $order['payment_reference'], 'qris');
            if ($res['ok']) {
                $status = 'paid';
            }
        }
    }
}

echo json_encode(['status' => $status]);
