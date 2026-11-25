<?php
// Shared <head> untuk seluruh halaman TIXORA
$pageTitle = isset($pageTitle) && $pageTitle !== ''
    ? $pageTitle . ' — ' . APP_NAME
    : APP_NAME . ' — ' . APP_TAGLINE;
$pageDesc = $pageDesc ?? 'TIXORA — Sistem Manajemen Tiket Konser modern untuk pemesanan tiket event musik, promo, dan e-ticket digital secara cepat dan aman.';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo e($pageDesc); ?>">
    <title><?php echo e($pageTitle); ?></title>
    <link rel="icon" href="<?php echo e(BASE_URL); ?>assets/icon/Icon_Mark.svg" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@500;700&family=Outfit:wght@600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="<?php echo e(BASE_URL); ?>assets/css/style.css">
</head>
