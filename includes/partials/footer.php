<footer class="site-footer">
    <div class="footer-inner">
        <div class="footer-brand-col">
            <img src="<?php echo e(BASE_URL); ?>assets/icon/Logo_Light.svg" alt="TIXORA" width="148" height="36">
            <p class="footer-desc">
                <strong>TIXORA</strong> — Sistem Manajemen Tiket Konser berbasis PHP &amp; MySQL dengan arsitektur basis data ternormalisasi dan e-ticket terverifikasi.
            </p>
        </div>
        <div class="footer-links-col">
            <a href="<?php echo e(BASE_URL); ?>index.php">Eksplorasi Konser</a>
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="<?php echo e(BASE_URL); ?>pages/pesanan_saya.php">Pesanan Saya</a>
                <a href="<?php echo e(BASE_URL); ?>pages/profil.php">Profil Akun</a>
            <?php else: ?>
                <a href="<?php echo e(BASE_URL); ?>auth/login.php">Masuk</a>
                <a href="<?php echo e(BASE_URL); ?>auth/register.php">Daftar</a>
            <?php endif; ?>
        </div>
    </div>
    <div class="footer-bottom">
        <span>&copy; <?php echo date('Y'); ?> <?php echo e(APP_NAME); ?> — <?php echo e(APP_TAGLINE); ?></span>
        <span class="mono">Ticket Index, Order &amp; Reservation Application</span>
    </div>
</footer>
