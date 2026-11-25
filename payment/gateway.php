<?php
// ============================================================
// TIXORA — Bayar.GG Payment Gateway Client
// TLS verification aktif secara default, cURL untuk semua request,
// dan verifikasi webhook HMAC-SHA256 + proteksi replay timestamp.
// ============================================================

class BayarGgGateway
{
    private const BASE_URL = 'https://www.bayar.gg/api';

    private string $apiKey;
    private string $webhookSecret;
    private bool $verifySsl;

    public function __construct(string $apiKey, string $webhookSecret = '', bool $verifySsl = true)
    {
        $this->apiKey        = $apiKey;
        $this->webhookSecret = $webhookSecret;
        $this->verifySsl     = $verifySsl;
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    public function createPayment(
        int $amount,
        string $description,
        string $customerName  = '',
        string $customerEmail = '',
        string $customerPhone = '',
        string $callbackUrl   = '',
        string $redirectUrl   = '',
        string $paymentMethod = 'qris'
    ): array|false {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'BAYARGG_API_KEY belum dikonfigurasi di file .env'];
        }

        $body = [
            'amount'         => $amount,
            'description'    => $description,
            'payment_method' => $paymentMethod,
        ];
        if ($customerName !== '')  $body['customer_name']  = $customerName;
        if ($customerEmail !== '') $body['customer_email'] = $customerEmail;
        if ($customerPhone !== '') $body['customer_phone'] = $customerPhone;
        if ($callbackUrl !== '')   $body['callback_url']   = $callbackUrl;
        if ($redirectUrl !== '')   $body['redirect_url']   = $redirectUrl;

        return $this->request('POST', self::BASE_URL . '/create-payment.php', $body);
    }

    public function checkPayment(string $invoiceId): array|false
    {
        if (!$this->isConfigured() || $invoiceId === '') {
            return false;
        }
        $url  = self::BASE_URL . '/check-payment.php?invoice=' . rawurlencode($invoiceId);
        $data = $this->request('GET', $url);
        return (is_array($data) && !empty($data['success'])) ? $data : false;
    }

    /**
     * Verifikasi signature webhook HMAC-SHA256 + batas waktu replay (300 detik).
     */
    public function verifyWebhook(array $payload, string $signature, string $timestamp, int $maxAgeSeconds = 300): bool
    {
        if ($this->webhookSecret === '' || $signature === '' || $timestamp === '') {
            return false;
        }

        if (ctype_digit($timestamp) && abs(time() - (int) $timestamp) > $maxAgeSeconds) {
            return false;
        }

        $data = ($payload['invoice_id'] ?? '') . '|'
              . ($payload['status'] ?? '') . '|'
              . ($payload['final_amount'] ?? '') . '|'
              . $timestamp;

        $expected = hash_hmac('sha256', $data, $this->webhookSecret);
        return hash_equals($expected, $signature);
    }

    private function request(string $method, string $url, ?array $jsonBody = null): array|false
    {
        $ch = curl_init($url);
        $headers = ['X-API-Key: ' . $this->apiKey];

        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => $this->verifySsl,
            CURLOPT_SSL_VERIFYHOST => $this->verifySsl ? 2 : 0,
        ];

        if ($method === 'POST') {
            $headers[] = 'Content-Type: application/json';
            $opts[CURLOPT_POST]       = true;
            $opts[CURLOPT_POSTFIELDS] = json_encode($jsonBody ?? []);
        }

        $opts[CURLOPT_HTTPHEADER] = $headers;
        curl_setopt_array($ch, $opts);

        $raw = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($raw === false || $err !== '') {
            error_log('[TIXORA GATEWAY CURL ERROR] ' . $err);
            return false;
        }

        $data = json_decode($raw, true);
        return is_array($data) ? $data : false;
    }
}
