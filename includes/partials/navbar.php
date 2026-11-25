<?php
// Shared Public Navbar — konsisten di desktop & mobile untuk seluruh halaman publik
$activeNav = $activeNav ?? '';
$showCatalogSearch = $showCatalogSearch ?? false;
$navCategories = $navCategories ?? [];
$isLogged = isset($_SESSION['user_id']) && (int) $_SESSION['user_id'] > 0;
$isUserAdmin = $isLogged && (($_SESSION['role'] ?? '') === 'admin');
$avatarInitial = $isLogged ? strtoupper(mb_substr((string) ($_SESSION['nama_lengkap'] ?? 'U'), 0, 1)) : '';
?>
<a href="#main-content" class="skip-link">Lewati ke konten utama</a>
<header class="navbar">
    <div class="navbar-inner">
        <a class="navbar-brand" href="<?php echo e(BASE_URL); ?>index.php" aria-label="TIXORA Beranda">
            <img src="<?php echo e(BASE_URL); ?>assets/icon/Logo_Light.svg" alt="TIXORA — Concert Ticketing" width="168" height="42">
        </a>

        <?php if ($showCatalogSearch): ?>
        <div class="navbar-search-wrap">
            <div class="search-field">
                <svg class="search-icon" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="M20 20l-3.5-3.5"/>
                </svg>
                <input type="search" id="searchInput" class="search-input" placeholder="Cari konser, artis, atau venue..." aria-label="Cari konser atau artis">
            </div>
        </div>
        <?php endif; ?>

        <nav class="navbar-menu" aria-label="Navigasi utama">
            <a class="nav-link <?php echo $activeNav === 'home' ? 'active' : ''; ?>" href="<?php echo e(BASE_URL); ?>index.php">Eksplorasi</a>
            <?php if ($isLogged): ?>
                <?php if ($isUserAdmin): ?>
                    <a class="nav-link <?php echo $activeNav === 'admin' ? 'active' : ''; ?>" href="<?php echo e(BASE_URL); ?>admin/">Panel Admin</a>
                <?php else: ?>
                    <a class="nav-link <?php echo $activeNav === 'orders' ? 'active' : ''; ?>" href="<?php echo e(BASE_URL); ?>pages/pesanan_saya.php">Pesanan Saya</a>
                <?php endif; ?>
                <form method="POST" action="<?php echo e(BASE_URL); ?>auth/logout.php" class="nav-logout-form">
                    <?php echo csrfField(); ?>
                    <button type="submit" class="nav-link nav-link-danger">Keluar</button>
                </form>
                <a href="<?php echo e(BASE_URL); ?>pages/profil.php" class="nav-avatar <?php echo $activeNav === 'profile' ? 'active' : ''; ?>" title="Profil Saya (<?php echo e($_SESSION['nama_lengkap'] ?? ''); ?>)" aria-label="Profil Saya">
                    <?php echo e($avatarInitial); ?>
                </a>
            <?php else: ?>
                <a class="nav-link" href="<?php echo e(BASE_URL); ?>auth/login.php">Masuk</a>
                <a class="button primary button-pill" href="<?php echo e(BASE_URL); ?>auth/register.php">Daftar</a>
            <?php endif; ?>
        </nav>

        <button type="button" class="nav-hamburger" id="navHamburger" aria-label="Buka menu navigasi" aria-expanded="false" aria-controls="navMobileMenu">
            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" viewBox="0 0 24 24" aria-hidden="true">
                <line x1="3" y1="6" x2="21" y2="6"/>
                <line x1="3" y1="12" x2="21" y2="12"/>
                <line x1="3" y1="18" x2="21" y2="18"/>
            </svg>
        </button>
    </div>

    <!-- Mobile Drawer Menu -->
    <div class="nav-mobile-menu" id="navMobileMenu">
        <?php if ($showCatalogSearch): ?>
        <div class="search-field">
            <svg class="search-icon" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="M20 20l-3.5-3.5"/>
            </svg>
            <input type="search" id="searchInputMobile" class="search-input" placeholder="Cari konser, artis, atau venue..." aria-label="Cari konser atau artis">
        </div>
        <?php endif; ?>
        <div class="nav-mobile-links">
            <a class="<?php echo $activeNav === 'home' ? 'active' : ''; ?>" href="<?php echo e(BASE_URL); ?>index.php">Eksplorasi Event</a>
            <?php if ($isLogged): ?>
                <?php if ($isUserAdmin): ?>
                    <a href="<?php echo e(BASE_URL); ?>admin/">Panel Admin</a>
                <?php else: ?>
                    <a class="<?php echo $activeNav === 'orders' ? 'active' : ''; ?>" href="<?php echo e(BASE_URL); ?>pages/pesanan_saya.php">Pesanan Saya</a>
                <?php endif; ?>
                <a class="<?php echo $activeNav === 'profile' ? 'active' : ''; ?>" href="<?php echo e(BASE_URL); ?>pages/profil.php">Profil Saya</a>
                <form method="POST" action="<?php echo e(BASE_URL); ?>auth/logout.php">
                    <?php echo csrfField(); ?>
                    <button type="submit" class="mobile-logout-btn">Keluar</button>
                </form>
            <?php else: ?>
                <a href="<?php echo e(BASE_URL); ?>auth/login.php">Masuk</a>
                <a href="<?php echo e(BASE_URL); ?>auth/register.php" class="button primary full-width">Daftar Akun</a>
            <?php endif; ?>
        </div>
    </div>
</header>
<script>
(function() {
    const btn = document.getElementById('navHamburger');
    const menu = document.getElementById('navMobileMenu');
    if (!btn || !menu) return;
    btn.addEventListener('click', function(e) {
        e.stopPropagation();
        const isOpen = menu.classList.toggle('open');
        btn.classList.toggle('active', isOpen);
        btn.setAttribute('aria-expanded', String(isOpen));
    });
    document.addEventListener('click', function(e) {
        if (!menu.contains(e.target) && !btn.contains(e.target)) {
            menu.classList.remove('open');
            btn.classList.remove('active');
            btn.setAttribute('aria-expanded', 'false');
        }
    });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            menu.classList.remove('open');
            btn.classList.remove('active');
            btn.setAttribute('aria-expanded', 'false');
        }
    });
})();
</script>
