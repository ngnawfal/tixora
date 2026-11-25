<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';

// Lindungi logout dari CSRF (hanya terima POST dengan token valid)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    logout();
}

// Jika diakses lewat GET, arahkan kembali dengan aman
header('Location: ' . BASE_URL . 'index.php');
exit;
