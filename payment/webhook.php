<?php
// ============================================================
// TIXORA — Webhook Callback Pembayaran (Bayar.GG)
// - Wajib HMAC-SHA256 valid + replay timestamp window
// - Validasi nominal pembayaran >= total tagihan pesanan
// - Update status & pengurangan kuota atomik (FOR UPDATE)
// ============================================================
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/gateway.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['error' => 'Method not allowed']));
}

$webhookSecret = (string) env('BAYARGG_WEBHOOK_SECRET', '');
$apiKey        = (string) env('BAYARGG_API_KEY', '');

if ($webhookSecret === '') {
    http_response_code(503);
    exit(json_encode(['error' => 'Webhook secret not configured']));
}

$rawBody = file_get_contents('php://input');
$payload = json_decode((string) $rawBody, true);

if (!is_array($payload)) {
    http_response_code(400);
    exit(json_encode(['error' => 'Invalid JSON payload']));
}

$signature = (string) ($_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? '');
$timestamp = (string) ($_SERVER['HTTP_X_WEBHOOK_TIMESTAMP'] ?? '');
$verifySsl = strtolower((string) env('BAYARGG_VERIFY_SSL', 'true')) !== 'false';

$gateway = new BayarGgGateway($apiKey, $webhookSecret, $verifySsl);
if (!$gateway->verifyWebhook($payload, $signature, $timestamp)) {
    http_response_code(401);
    exit(json_encode(['error' => 'Invalid webhook signature or expired timestamp']));
}

$event  = (string) ($payload['event'] ?? '');
$status = (string) ($payload['status'] ?? '');

if ($event !== 'payment.paid' || $status !== 'paid') {
    http_response_code(200);
    exit(json_encode(['success' => true, 'note' => 'Event ignored']));
}

$invoiceId   = (string) ($payload['invoice_id'] ?? '');
$finalAmount = isset($payload['final_amount']) ? (float) $payload['final_amount'] : -1;

if ($invoiceId === '') {
    http_response_code(400);
    exit(json_encode(['error' => 'Missing invoice_id']));
}

$stmt = $koneksi->prepare(
    "SELECT id_order, total_amount, payment_status
     FROM orders
     WHERE payment_reference = ?
     LIMIT 1"
);
$stmt->bind_param('s', $invoiceId);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    http_response_code(200);
    exit(json_encode(['success' => true, 'note' => 'Order not found']));
}

if ($order['payment_status'] === 'paid') {
    http_response_code(200);
    exit(json_encode(['success' => true, 'note' => 'Already processed']));
}

if ($finalAmount >= 0 && $finalAmount < (float) $order['total_amount']) {
    http_response_code(400);
    exit(json_encode(['error' => 'Paid amount is less than order total']));
}

$paidRef = 'BAYARGG-' . ($payload['paid_reff_num'] ?? $invoiceId);
$method  = isset($payload['payment_method']) ? (string) $payload['payment_method'] : 'qris';

$res = selesaikanPembayaranOrder((int) $order['id_order'], $paidRef, $method);

if (!$res['ok']) {
    http_response_code(409);
    exit(json_encode(['error' => 'Failed to settle order', 'reason' => $res['reason']]));
}

http_response_code(200);
echo json_encode(['success' => true]);
