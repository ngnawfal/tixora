<?php
// ============================================================
// TIXORA — Hapus Event (POST-only + CSRF Protection)
// ============================================================
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrf()) {
    setFlash('Permintaan hapus tidak valid (wajib POST dengan token keamanan).', 'danger');
    header('Location: ' . BASE_URL . 'admin/');
    exit;
}

$id = (int) ($_POST['id_event'] ?? 0);
$res = hapusEvent($id);

if ($res['ok']) {
    setFlash('Data event berhasil dihapus.', 'success');
} else {
    setFlash($res['error'] ?? 'Gagal menghapus data event.', 'danger');
}

header('Location: ' . BASE_URL . 'admin/');
exit;
