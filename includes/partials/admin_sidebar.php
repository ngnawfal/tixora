<?php
// Shared Admin Sidebar + Mobile Drawer (konsisten di semua halaman admin)
$activeAdmin = $activeAdmin ?? '';
$adminName = $_SESSION['nama_lengkap'] ?? 'Administrator';
$adminInitial = strtoupper(mb_substr((string) $adminName, 0, 1));
?>
<a href="#main-content" class="skip-link">Lewati ke konten utama</a>
<button type="button" class="sidebar-toggle" id="sidebarToggle" aria-label="Buka menu admin" aria-expanded="false">
    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" viewBox="0 0 24 24" aria-hidden="true">
        <line x1="3" y1="6" x2="21" y2="6"/>
        <line x1="3" y1="12" x2="21" y2="12"/>
        <line x1="3" y1="18" x2="21" y2="18"/>
    </svg>
</button>
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<aside class="admin-sidebar" id="adminSidebar" aria-label="Navigasi Admin">
    <div class="sidebar-header">
        <a href="<?php echo e(BASE_URL); ?>admin/" class="sidebar-brand" aria-label="TIXORA Admin">
            <img src="<?php echo e(BASE_URL); ?>assets/icon/Logo_Light.svg" alt="TIXORA" width="160" height="40">
        </a>
        <span class="sidebar-env-badge">ADMIN CONSOLE</span>
    </div>

    <nav class="sidebar-menu">
        <div class="sidebar-title">Manajemen</div>
        <a class="sidebar-link <?php echo $activeAdmin === 'events' ? 'active' : ''; ?>" href="<?php echo e(BASE_URL); ?>admin/">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
            Data Tiket Event
        </a>
        <a class="sidebar-link <?php echo $activeAdmin === 'transactions' ? 'active' : ''; ?>" href="<?php echo e(BASE_URL); ?>admin/transaksi.php">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 14l2 2 4-4m-9 8h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            Data Transaksi
        </a>
        <a class="sidebar-link <?php echo $activeAdmin === 'reference' ? 'active' : ''; ?>" href="<?php echo e(BASE_URL); ?>admin/kelola.php">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h10"/></svg>
            Genre &amp; Venue
        </a>

        <div class="sidebar-title">Navigasi</div>
        <a class="sidebar-link" href="<?php echo e(BASE_URL); ?>index.php">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
            Lihat Katalog Publik
        </a>
        <a class="sidebar-link <?php echo $activeAdmin === 'profile' ? 'active' : ''; ?>" href="<?php echo e(BASE_URL); ?>pages/profil.php">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
            Profil Saya
        </a>
    </nav>

    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="sidebar-user-avatar"><?php echo e($adminInitial); ?></div>
            <div class="sidebar-user-meta">
                <span class="sidebar-user-name"><?php echo e($adminName); ?></span>
                <span class="sidebar-user-role">Administrator</span>
            </div>
        </div>
        <form method="POST" action="<?php echo e(BASE_URL); ?>auth/logout.php">
            <?php echo csrfField(); ?>
            <button type="submit" class="sidebar-link text-danger full-width">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a2 2 0 01-2 2H6a2 2 0 01-2-2V7a2 2 0 012-2h5a2 2 0 012 2v1"/></svg>
                Keluar
            </button>
        </form>
    </div>
</aside>

<script>
(function() {
    const toggle = document.getElementById('sidebarToggle');
    const overlay = document.getElementById('sidebarOverlay');
    const sidebar = document.getElementById('adminSidebar');
    if (!toggle || !overlay || !sidebar) return;

    function setOpen(open) {
        sidebar.classList.toggle('open', open);
        overlay.classList.toggle('active', open);
        toggle.setAttribute('aria-expanded', String(open));
    }

    toggle.addEventListener('click', () => setOpen(!sidebar.classList.contains('open')));
    overlay.addEventListener('click', () => setOpen(false));
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') setOpen(false); });
})();
</script>
