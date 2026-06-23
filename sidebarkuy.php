<?php
/**
 * =================================================================================
 * FILE: sidebarkuy.php
 * DESKRIPSI: Komponen Sidebar Universal — di-include ke semua halaman.
 * TEMA: Navy Cream — #1e2d40 sidebar + #fdf8f0 background + #e8d5b7 aksen
 * CARA PAKAI: include 'sidebarkuy.php'; di awal <body> setiap halaman.
 * Variabel yang harus sudah ada: $_SESSION['role'], $_SESSION['username'], $halaman_aktif
 * =================================================================================
 */

// Tentukan menu berdasarkan role
$is_owner = ($_SESSION['role'] === 'owner');
$is_admin = ($_SESSION['role'] === 'kasir');

// Daftar menu Owner
$menu_owner = [
    ['href' => 'dashboard_ownerkuy.php', 'icon' => 'ti-layout-dashboard',       'label' => 'Dashboard',         'key' => 'dashboard'],
    ['href' => 'laporankuy.php',         'icon' => 'ti-chart-bar',               'label' => 'Laporan',           'key' => 'laporan'],
    ['href' => 'riwayatkuy.php',         'icon' => 'ti-clock-history',           'label' => 'Riwayat Transaksi', 'key' => 'riwayat'],
    ['href' => 'pelanggankuy.php',       'icon' => 'ti-users',                   'label' => 'Pelanggan',         'key' => 'pelanggan'],
    ['href' => 'pengeluarankuy.php',     'icon' => 'ti-wallet',                  'label' => 'Pengeluaran',       'key' => 'pengeluaran'],
    ['href' => 'layanankuy.php',         'icon' => 'ti-tag',                     'label' => 'Harga Paket',       'key' => 'layanan'],
    ['href' => 'voucherkuy.php',         'icon' => 'ti-ticket',                  'label' => 'Voucher Diskon',    'key' => 'voucher'],
    ['href' => 'userkuy.php',            'icon' => 'ti-users-group',             'label' => 'Kelola User',       'key' => 'user'],
];

// Daftar menu Admin
$menu_admin = [
    ['href' => 'dashboard_kasirkuy.php', 'icon' => 'ti-layout-dashboard',       'label' => 'Dashboard',         'key' => 'dashboard'],
    ['href' => 'transaksikuy.php',       'icon' => 'ti-shopping-cart',           'label' => 'Kasir',             'key' => 'transaksi'],
    ['href' => 'riwayatkuy.php',         'icon' => 'ti-clock-history',           'label' => 'Riwayat',           'key' => 'riwayat'],
    ['href' => 'pelanggankuy.php',       'icon' => 'ti-users',                   'label' => 'Pelanggan',         'key' => 'pelanggan'],
];

$menu_aktif = $is_owner ? $menu_owner : $menu_admin;
$halaman_aktif = isset($halaman_aktif) ? $halaman_aktif : '';
?>

<!-- =====================================================================
     CSS GLOBAL NAVY CREAM THEME + SIDEBAR LAYOUT
     Diletakkan di sini agar berlaku di semua halaman yang include file ini
     ===================================================================== -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
/* ── VARIABEL TEMA NAVY CREAM ─────────────────────────────────── */
:root {
    --navy-900:  #111d2b;
    --navy-800:  #1e2d40;
    --navy-700:  #243347;
    --navy-600:  #2e4d6e;
    --navy-500:  #3d6491;
    --cream-50:  #fdf8f0;
    --cream-100: #f5edd8;
    --cream-200: #e8d5b7;
    --cream-300: #d4b896;
    --gold:      #c9a96e;
    --gold-dark: #a8874f;
    --text-dark: #1e2d40;
    --text-mid:  #4a6178;
    --text-soft: #7a92a8;
    --sidebar-w: 240px;
    --sidebar-w-col: 70px;
    --transition: 0.22s cubic-bezier(.4,0,.2,1);
}

/* ── RESET & BASE ─────────────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; }

body {
    margin: 0;
    font-family: 'Plus Jakarta Sans', sans-serif;
    background-color: var(--cream-50);
    color: var(--text-dark);
    min-height: 100vh;
}

/* ── LAYOUT WRAPPER ───────────────────────────────────────────── */
.kuy-layout {
    display: flex;
    min-height: 100vh;
}

/* ── SIDEBAR ──────────────────────────────────────────────────── */
.kuy-sidebar {
    width: var(--sidebar-w);
    min-height: 100vh;
    background: var(--navy-800);
    display: flex;
    flex-direction: column;
    position: fixed;
    left: 0; top: 0; bottom: 0;
    z-index: 1000;
    transition: width var(--transition);
    overflow: hidden;
}

.kuy-sidebar.collapsed {
    width: var(--sidebar-w-col);
}

/* Brand area */
.kuy-brand {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 20px 16px 16px;
    border-bottom: 1px solid rgba(232,213,183,0.1);
    text-decoration: none;
    min-height: 68px;
    flex-shrink: 0;
}

.kuy-brand-logo {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background: var(--cream-200);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    overflow: hidden;
}

.kuy-brand-logo img {
    width: 24px;
    height: 24px;
    object-fit: contain;
}

.kuy-brand-text {
    overflow: hidden;
    white-space: nowrap;
    transition: opacity var(--transition), width var(--transition);
}

.kuy-brand-name {
    font-size: 15px;
    font-weight: 800;
    color: var(--cream-200);
    line-height: 1.2;
    letter-spacing: -0.3px;
}

.kuy-brand-role {
    font-size: 11px;
    color: var(--gold);
    font-weight: 600;
    letter-spacing: 0.5px;
    text-transform: uppercase;
}

.kuy-sidebar.collapsed .kuy-brand-text { opacity: 0; width: 0; }

/* Toggle button */
.kuy-toggle {
    position: absolute;
    top: 20px;
    right: -13px;
    width: 26px;
    height: 26px;
    background: var(--cream-200);
    border: none;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--navy-800);
    font-size: 13px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    transition: background var(--transition), transform var(--transition);
    z-index: 10;
}
.kuy-toggle:hover { background: var(--gold); }
.kuy-sidebar.collapsed .kuy-toggle { transform: rotate(180deg); }

/* Nav menu */
.kuy-nav {
    flex: 1;
    padding: 12px 10px;
    overflow-y: auto;
    overflow-x: hidden;
    scrollbar-width: none;
}
.kuy-nav::-webkit-scrollbar { display: none; }

.kuy-nav-label {
    font-size: 9px;
    font-weight: 700;
    letter-spacing: 1.2px;
    text-transform: uppercase;
    color: var(--text-soft);
    padding: 8px 8px 4px;
    white-space: nowrap;
    overflow: hidden;
    transition: opacity var(--transition);
}
.kuy-sidebar.collapsed .kuy-nav-label { opacity: 0; }

.kuy-nav-item {
    display: flex;
    align-items: center;
    gap: 11px;
    padding: 10px 10px;
    border-radius: 10px;
    text-decoration: none;
    color: rgba(232,213,183,0.65);
    font-size: 13.5px;
    font-weight: 500;
    margin-bottom: 2px;
    transition: all var(--transition);
    white-space: nowrap;
    position: relative;
    overflow: hidden;
}

.kuy-nav-item i {
    font-size: 18px;
    flex-shrink: 0;
    width: 22px;
    text-align: center;
    transition: color var(--transition);
}

.kuy-nav-item span {
    overflow: hidden;
    white-space: nowrap;
    transition: opacity var(--transition);
}

.kuy-sidebar.collapsed .kuy-nav-item span { opacity: 0; width: 0; }

.kuy-nav-item:hover {
    background: var(--navy-600);
    color: var(--cream-200);
}

.kuy-nav-item.active {
    background: var(--gold);
    color: var(--navy-900);
    font-weight: 700;
}

.kuy-nav-item.active i { color: var(--navy-900); }

/* Tooltip saat collapsed */
.kuy-sidebar.collapsed .kuy-nav-item::after {
    content: attr(data-label);
    position: absolute;
    left: calc(var(--sidebar-w-col) + 8px);
    background: var(--navy-700);
    color: var(--cream-200);
    font-size: 12px;
    font-weight: 600;
    padding: 5px 10px;
    border-radius: 7px;
    white-space: nowrap;
    pointer-events: none;
    opacity: 0;
    transform: translateX(-6px);
    transition: all 0.15s;
    box-shadow: 0 4px 12px rgba(0,0,0,0.25);
    z-index: 9999;
}
.kuy-sidebar.collapsed .kuy-nav-item:hover::after {
    opacity: 1;
    transform: translateX(0);
}

/* ── USER AREA (bawah sidebar) ────────────────────────────────── */
.kuy-user {
    border-top: 1px solid rgba(232,213,183,0.1);
    padding: 12px 10px;
    flex-shrink: 0;
}

.kuy-user-info {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px;
    border-radius: 10px;
    margin-bottom: 6px;
    overflow: hidden;
}

.kuy-avatar {
    width: 34px;
    height: 34px;
    border-radius: 9px;
    background: var(--gold);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    font-weight: 800;
    color: var(--navy-900);
    flex-shrink: 0;
}

.kuy-user-detail {
    overflow: hidden;
    white-space: nowrap;
    transition: opacity var(--transition);
}

.kuy-user-name {
    font-size: 13px;
    font-weight: 700;
    color: var(--cream-200);
    line-height: 1.2;
}

.kuy-user-role {
    font-size: 10px;
    color: var(--text-soft);
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.kuy-sidebar.collapsed .kuy-user-detail { opacity: 0; width: 0; }

/* Tombol aksi user */
.kuy-user-actions {
    display: flex;
    gap: 6px;
    overflow: hidden;
    transition: opacity var(--transition);
}

.kuy-sidebar.collapsed .kuy-user-actions { opacity: 0; pointer-events: none; }

.kuy-btn-user {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    padding: 7px 8px;
    border-radius: 8px;
    font-size: 11.5px;
    font-weight: 600;
    cursor: pointer;
    border: none;
    text-decoration: none;
    transition: all var(--transition);
    white-space: nowrap;
}

.kuy-btn-pw {
    background: rgba(232,213,183,0.12);
    color: var(--cream-200);
}
.kuy-btn-pw:hover { background: rgba(232,213,183,0.22); color: var(--cream-100); }

.kuy-btn-logout {
    background: rgba(220,53,69,0.15);
    color: #ff8a8a;
}
.kuy-btn-logout:hover { background: rgba(220,53,69,0.28); color: #ffb3b3; }

/* ── KONTEN UTAMA ─────────────────────────────────────────────── */
.kuy-main {
    margin-left: var(--sidebar-w);
    flex: 1;
    min-height: 100vh;
    transition: margin-left var(--transition);
    background: var(--cream-50);
}

.kuy-main.collapsed { margin-left: var(--sidebar-w-col); }

/* Topbar tipis di atas konten */
.kuy-topbar {
    background: #ffffff;
    border-bottom: 1px solid var(--cream-100);
    padding: 14px 28px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: sticky;
    top: 0;
    z-index: 100;
    box-shadow: 0 1px 0 var(--cream-100);
}

.kuy-topbar-title {
    font-size: 17px;
    font-weight: 700;
    color: var(--text-dark);
}

.kuy-topbar-right {
    display: flex;
    align-items: center;
    gap: 12px;
}

.kuy-topbar-user {
    font-size: 13px;
    color: var(--text-mid);
    font-weight: 500;
}

.kuy-content {
    padding: 28px;
}

/* ── CARD STYLE GLOBAL ────────────────────────────────────────── */
.kuy-card {
    background: #ffffff;
    border-radius: 14px;
    border: 1px solid var(--cream-100);
    box-shadow: 0 1px 4px rgba(30,45,64,0.06);
    transition: box-shadow var(--transition);
}

.kuy-card:hover {
    box-shadow: 0 4px 16px rgba(30,45,64,0.10);
}

/* ── STAT CARD ────────────────────────────────────────────────── */
.kuy-stat {
    background: #ffffff;
    border-radius: 14px;
    border: 1px solid var(--cream-100);
    padding: 20px 22px;
    transition: all var(--transition);
}

.kuy-stat:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 24px rgba(30,45,64,0.10);
}

.kuy-stat-label {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    color: var(--text-soft);
    margin-bottom: 8px;
}

.kuy-stat-value {
    font-size: 1.75rem;
    font-weight: 800;
    color: var(--text-dark);
    line-height: 1;
}

.kuy-stat-sub {
    font-size: 12px;
    color: var(--text-soft);
    margin-top: 4px;
}

/* ── TOMBOL UTAMA ─────────────────────────────────────────────── */
.kuy-btn-primary {
    background: var(--navy-800);
    color: var(--cream-200);
    border: none;
    padding: 9px 20px;
    border-radius: 9px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all var(--transition);
    font-family: 'Plus Jakarta Sans', sans-serif;
}
.kuy-btn-primary:hover {
    background: var(--navy-600);
    color: var(--cream-100);
    transform: translateY(-1px);
}

.kuy-btn-gold {
    background: var(--gold);
    color: var(--navy-900);
    border: none;
    padding: 9px 20px;
    border-radius: 9px;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all var(--transition);
    font-family: 'Plus Jakarta Sans', sans-serif;
}
.kuy-btn-gold:hover {
    background: var(--gold-dark);
    color: var(--navy-900);
    transform: translateY(-1px);
}

/* ── BADGE STATUS ─────────────────────────────────────────────── */
.kuy-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 11.5px;
    font-weight: 700;
}
.kuy-badge-success { background: #d1fae5; color: #065f46; }
.kuy-badge-danger  { background: #fee2e2; color: #991b1b; }
.kuy-badge-warning { background: #fef9c3; color: #854d0e; }
.kuy-badge-info    { background: #dbeafe; color: #1e40af; }
.kuy-badge-gray    { background: var(--cream-100); color: var(--text-mid); }

/* ── TABEL ────────────────────────────────────────────────────── */
.kuy-table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
.kuy-table thead th {
    background: var(--navy-800);
    color: var(--cream-200);
    font-weight: 600;
    padding: 12px 14px;
    text-align: left;
    font-size: 12px;
    letter-spacing: 0.3px;
}
.kuy-table thead th:first-child { border-radius: 10px 0 0 0; }
.kuy-table thead th:last-child  { border-radius: 0 10px 0 0; }
.kuy-table tbody td {
    padding: 11px 14px;
    border-bottom: 1px solid var(--cream-100);
    color: var(--text-dark);
    vertical-align: middle;
}
.kuy-table tbody tr:last-child td { border-bottom: none; }
.kuy-table tbody tr:hover td { background: var(--cream-50); }
.kuy-table tfoot td {
    padding: 11px 14px;
    background: var(--cream-100);
    font-weight: 700;
    color: var(--text-dark);
}

/* ── FORM ─────────────────────────────────────────────────────── */
.kuy-form-label {
    font-size: 12.5px;
    font-weight: 700;
    color: var(--text-dark);
    margin-bottom: 6px;
    display: block;
}

.kuy-input {
    width: 100%;
    padding: 9px 13px;
    border: 1.5px solid var(--cream-200);
    border-radius: 9px;
    font-size: 13.5px;
    font-family: 'Plus Jakarta Sans', sans-serif;
    color: var(--text-dark);
    background: #ffffff;
    transition: border-color var(--transition), box-shadow var(--transition);
    outline: none;
}
.kuy-input:focus {
    border-color: var(--navy-600);
    box-shadow: 0 0 0 3px rgba(46,77,110,0.12);
}

/* ── MODAL ────────────────────────────────────────────────────── */
.kuy-modal-header {
    background: var(--navy-800);
    color: var(--cream-200);
    border-radius: 12px 12px 0 0;
    padding: 18px 22px;
}

/* ── ALERT ────────────────────────────────────────────────────── */
.kuy-alert-info {
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: 10px;
    padding: 12px 16px;
    font-size: 13px;
    color: #1e40af;
}

/* ── MOBILE OVERLAY ───────────────────────────────────────────── */
.kuy-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(17,29,43,0.5);
    z-index: 999;
    backdrop-filter: blur(2px);
}

/* ── MOBILE TOGGLE BTN ────────────────────────────────────────── */
.kuy-mobile-toggle {
    display: none;
    position: fixed;
    top: 14px;
    left: 14px;
    z-index: 1001;
    background: var(--navy-800);
    color: var(--cream-200);
    border: none;
    width: 38px;
    height: 38px;
    border-radius: 10px;
    font-size: 18px;
    cursor: pointer;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 10px rgba(0,0,0,0.2);
}

/* ── RESPONSIF ────────────────────────────────────────────────── */
@media (max-width: 768px) {
    .kuy-sidebar {
        transform: translateX(-100%);
        width: var(--sidebar-w) !important;
        transition: transform var(--transition);
    }
    .kuy-sidebar.mobile-open {
        transform: translateX(0);
    }
    .kuy-sidebar .kuy-toggle { display: none; }
    .kuy-main { margin-left: 0 !important; }
    .kuy-overlay.show { display: block; }
    .kuy-mobile-toggle { display: flex; }
    .kuy-content { padding: 16px; }
    .kuy-topbar { padding: 12px 16px 12px 60px; }
}

/* ── OVERRIDE BOOTSTRAP utk konsistensi ──────────────────────── */
.modal-header.kuy-modal-header .btn-close { filter: invert(1) brightness(2); }
</style>

<!-- =====================================================================
     OVERLAY MOBILE
     ===================================================================== -->
<div class="kuy-overlay" id="kuyOverlay" onclick="kuyCloseMobile()"></div>

<!-- =====================================================================
     TOMBOL TOGGLE MOBILE
     ===================================================================== -->
<button class="kuy-mobile-toggle" onclick="kuyOpenMobile()" aria-label="Buka Menu">
    <i class="bi bi-list"></i>
</button>

<!-- =====================================================================
     SIDEBAR
     ===================================================================== -->
<aside class="kuy-sidebar" id="kuySidebar">

    <!-- Tombol collapse -->
    <button class="kuy-toggle" id="kuyToggle" onclick="kuyToggleSidebar()" title="Perkecil/Perbesar Sidebar">
        <i class="bi bi-chevron-left"></i>
    </button>

    <!-- Brand / Logo -->
    <a href="<?php echo $is_owner ? 'dashboard_ownerkuy.php' : 'dashboard_kasirkuy.php'; ?>" class="kuy-brand">
        <div class="kuy-brand-logo">
            <img src="assets/iconlog.png" alt="Logo LaundryKuy">
        </div>
        <div class="kuy-brand-text">
            <div class="kuy-brand-name">LaundryKuy</div>
            <div class="kuy-brand-role"><?php echo $is_owner ? 'Owner Panel' : 'Kasir Panel'; ?></div>
        </div>
    </a>

    <!-- Menu Navigasi -->
    <nav class="kuy-nav">
        <div class="kuy-nav-label">Menu Utama</div>
        <?php foreach ($menu_aktif as $menu):
            $icon_map = [
                'ti-layout-dashboard' => 'bi bi-speedometer2',
                'ti-chart-bar'        => 'bi bi-bar-chart-line-fill',
                'ti-clock-history'    => 'bi bi-clock-history',
                'ti-users'            => 'bi bi-people-fill',
                'ti-wallet'           => 'bi bi-wallet2',
                'ti-tag'              => 'bi bi-tags-fill',
                'ti-ticket'           => 'bi bi-ticket-perforated-fill',
                'ti-users-group'      => 'bi bi-person-gear',
                'ti-shopping-cart'    => 'bi bi-cart-plus-fill',
            ];
            $icon_class = $icon_map[$menu['icon']] ?? 'bi bi-circle';
        ?>
        <a href="<?php echo $menu['href']; ?>"
           class="kuy-nav-item <?php echo ($halaman_aktif === $menu['key']) ? 'active' : ''; ?>"
           data-label="<?php echo $menu['label']; ?>">
            <i class="<?php echo $icon_class; ?>"></i>
            <span><?php echo $menu['label']; ?></span>
        </a>
        <?php endforeach; ?>
    </nav>

    <!-- Area User -->
    <div class="kuy-user">
        <div class="kuy-user-info">
            <div class="kuy-avatar">
                <?php echo htmlspecialchars(strtoupper(substr($_SESSION['username'], 0, 1))); // [FIX XSS] ?>
            </div>
            <div class="kuy-user-detail">
                <div class="kuy-user-name"><?php echo htmlspecialchars(ucwords($_SESSION['username'])); // [FIX XSS] ?></div>
                <div class="kuy-user-role"><?php echo htmlspecialchars(ucfirst($_SESSION['role'])); // [FIX XSS] ?></div>
            </div>
        </div>
        <div class="kuy-user-actions">
            <?php if ($is_owner): ?>
            <button class="kuy-btn-user kuy-btn-pw"
                data-bs-toggle="modal" data-bs-target="#modalGantiPwOwner"
                title="Ganti Password">
                <i class="bi bi-key-fill"></i>
                <span>Password</span>
            </button>
            <?php endif; ?>
            <a href="logoutkuy.php" class="kuy-btn-user kuy-btn-logout" title="Keluar">
                <i class="bi bi-box-arrow-right"></i>
                <span>Keluar</span>
            </a>
        </div>
    </div>

</aside>

<!-- =====================================================================
     JAVASCRIPT SIDEBAR
     ===================================================================== -->
<script>
(function() {
    const sidebar  = document.getElementById('kuySidebar');
    const main     = document.querySelector('.kuy-main');
    const overlay  = document.getElementById('kuyOverlay');
    const SK       = 'kuySidebarCollapsed';

    // Restore state dari localStorage
    if (localStorage.getItem(SK) === '1') {
        sidebar.classList.add('collapsed');
        if (main) main.classList.add('collapsed');
    }

    window.kuyToggleSidebar = function() {
        const isCol = sidebar.classList.toggle('collapsed');
        if (main) main.classList.toggle('collapsed', isCol);
        localStorage.setItem(SK, isCol ? '1' : '0');
    };

    window.kuyOpenMobile = function() {
        sidebar.classList.add('mobile-open');
        overlay.classList.add('show');
    };

    window.kuyCloseMobile = function() {
        sidebar.classList.remove('mobile-open');
        overlay.classList.remove('show');
    };
})();
</script>