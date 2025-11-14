<?php
// Cek apakah sesi sudah dimulai
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Pastikan path ke koneksi database sudah benar
if (file_exists(__DIR__ . '/../system/database_connection.php')) {
    include_once __DIR__ . '/../system/database_connection.php';
} elseif (file_exists(__DIR__ . '/../../system/database_connection.php')) {
    include_once __DIR__ . '/../../system/database_connection.php';
}

// Jika tidak ada sesi user atau role bukan admin, redirect ke halaman login
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: /produksi_tracking/auth/login.php');
    exit;
}

// Definisikan base URL untuk konsistensi path
$base_url = '/produksi_tracking';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) : 'Dashboard'; ?> - Admin</title>
    <link rel="icon" href="/produksi_tracking/assets/images/logo_v1.png" type="image/png">
    <link rel="apple-touch-icon" href="/produksi_tracking/assets/images/logo_v1.png">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="<?php echo $base_url; ?>/assets/css/style.css">

    <style>
        /* ========================================= */
        /* ROOT VARIABLES - Matching Superadmin */
        /* ========================================= */
        :root {
            --glass-bg: rgba(255, 255, 255, 0.1);
            --glass-border: rgba(255, 255, 255, 0.18);
            --accent-color: #667eea;
            --accent-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --hover-bg: rgba(255, 255, 255, 0.15);
            --text-primary: #ffffff;
            --text-secondary: rgba(255, 255, 255, 0.85);
        }

        /* ========================================= */
        /* BODY BACKGROUND - Matching Superadmin */
        /* ========================================= */
        body {
            padding-top: 80px;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 50%, #667eea 100%);
            background-attachment: fixed;
            min-height: 100vh;
        }

        /* ========================================= */
        /* GLASSMORPHISM NAVBAR - From Superadmin */
        /* ========================================= */
        .navbar.fixed-top {
            background: rgba(30, 60, 114, 0.75);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--glass-border);
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            padding: 0.75rem 0;
        }

        .navbar.scrolled {
            background: rgba(30, 60, 114, 0.95);
            padding: 0.5rem 0;
            box-shadow: 0 10px 40px 0 rgba(31, 38, 135, 0.5);
        }

        /* ========================================= */
        /* LOGO STYLING - From Superadmin */
        /* ========================================= */
        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.5rem 1rem;
            border-radius: 12px;
            transition: all 0.3s ease;
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
        }

        .navbar-brand:hover {
            background: var(--hover-bg);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        .navbar-brand img {
            transition: transform 0.3s ease;
        }

        .navbar-brand:hover img {
            transform: scale(1.1) rotate(5deg);
        }

        /* ========================================= */
        /* NAV LINKS - From Superadmin */
        /* ========================================= */
        .navbar-nav .nav-link {
            color: var(--text-secondary);
            font-size: 0.95rem;
            font-weight: 500;
            padding: 0.65rem 1.2rem !important;
            margin: 0 0.25rem;
            border-radius: 10px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .navbar-nav .nav-link::before {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            width: 0;
            height: 3px;
            background: var(--accent-gradient);
            transform: translateX(-50%);
            transition: width 0.3s ease;
            border-radius: 3px 3px 0 0;
        }

        .navbar-nav .nav-link:hover {
            color: var(--text-primary);
            background: var(--hover-bg);
            transform: translateY(-2px);
        }

        .navbar-nav .nav-link:hover::before {
            width: 80%;
        }

        .navbar-nav .nav-link.active {
            color: var(--text-primary);
            background: var(--accent-gradient);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }

        .navbar-nav .nav-link i {
            font-size: 1.1rem;
            margin-right: 0.5rem;
            transition: transform 0.3s ease;
        }

        .navbar-nav .nav-link:hover i {
            transform: scale(1.2);
        }

        /* ========================================= */
        /* DROPDOWN MENU - From Superadmin */
        /* ========================================= */
        .dropdown-menu {
            background: rgba(30, 60, 114, 0.95);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.5);
            padding: 0.5rem;
            margin-top: 0.5rem !important;
            animation: dropdownSlide 0.3s ease;
        }

        @keyframes dropdownSlide {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .dropdown-item {
            color: var(--text-secondary);
            padding: 0.65rem 1rem;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
        }

        .dropdown-item:hover {
            color: var(--text-primary);
            background: var(--hover-bg);
            transform: translateX(5px);
        }

        .dropdown-item i {
            margin-right: 0.75rem;
            font-size: 1rem;
            opacity: 0.8;
        }

        .dropdown-divider {
            border-color: var(--glass-border);
            margin: 0.5rem 0;
        }

        /* ========================================= */
        /* DROPDOWN TOGGLE ARROW */
        /* ========================================= */
        .dropdown-toggle::after {
            transition: transform 0.3s ease;
        }

        .dropdown-toggle[aria-expanded="true"]::after {
            transform: rotate(180deg);
        }

        /* ========================================= */
        /* USER PROFILE DROPDOWN */
        /* ========================================= */
        #userDropdown {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 25px;
            padding: 0.5rem 1.25rem !important;
        }

        #userDropdown:hover {
            background: var(--accent-gradient);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }

        /* ========================================= */
        /* NAVBAR TOGGLER */
        /* ========================================= */
        .navbar-toggler {
            border: 2px solid var(--glass-border);
            border-radius: 8px;
            padding: 0.5rem;
            transition: all 0.3s ease;
        }

        .navbar-toggler:hover {
            background: var(--hover-bg);
            border-color: var(--accent-color);
        }

        .navbar-toggler:focus {
            box-shadow: 0 0 0 0.25rem rgba(102, 126, 234, 0.25);
        }

        /* ========================================= */
        /* RESPONSIVE DROPDOWN HOVER - Desktop */
        /* ========================================= */
        @media (min-width: 992px) {
            .dropdown:hover .dropdown-menu {
                display: block;
                margin-top: 0;
            }
        }

        /* ========================================= */
        /* MOBILE RESPONSIVE */
        /* ========================================= */
        @media (max-width: 991px) {
            body {
                padding-top: 70px;
            }

            .navbar-collapse {
                background: rgba(30, 60, 114, 0.98);
                backdrop-filter: blur(20px);
                border-radius: 12px;
                margin-top: 1rem;
                padding: 1rem;
                border: 1px solid var(--glass-border);
            }

            .navbar-nav .nav-link {
                margin: 0.25rem 0;
            }

            .dropdown-menu {
                background: rgba(20, 40, 80, 0.9);
                border: 1px solid var(--glass-border);
            }
        }

        /* ========================================= */
        /* SMOOTH SCROLL BEHAVIOR */
        /* ========================================= */
        html {
            scroll-behavior: smooth;
        }

        /* ========================================= */
        /* CONTENT CARD ENHANCEMENT */
        /* ========================================= */
        .card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.18);
            border-radius: 16px;
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.15);
        }

        /* ========================================= */
        /* SAFE AREA INSETS FOR MODERN PHONES */
        /* ========================================= */
        @supports (padding: max(0px)) {
            .navbar {
                padding-left: max(1rem, env(safe-area-inset-left));
                padding-right: max(1rem, env(safe-area-inset-right));
                padding-top: env(safe-area-inset-top);
            }
        }

        /* ========================================= */
        /* iOS SPECIFIC FIXES */
        /* ========================================= */
        @supports (-webkit-touch-callout: none) {
            .navbar-toggler,
            .nav-link,
            .dropdown-item {
                -webkit-tap-highlight-color: transparent;
            }
            
            body {
                -webkit-font-smoothing: antialiased;
            }
        }

        /* ========================================= */
        /* TOUCH DEVICE OPTIMIZATIONS */
        /* ========================================= */
        @media (hover: none) and (pointer: coarse) {
            .navbar-nav .nav-link:active {
                background-color: var(--hover-bg);
                transform: scale(0.98);
            }
            
            .dropdown-item:active {
                background-color: var(--hover-bg);
            }
            
            .navbar-brand:active {
                transform: scale(0.95);
            }
            
            .navbar-toggler:active {
                background-color: var(--hover-bg);
                transform: scale(0.95);
            }
        }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?php echo $base_url; ?>/pengguna/admin/dashboard.php" aria-label="Dashboard">
                <img src="<?php echo $base_url; ?>/assets/images/logo_v1.png" alt="Logo" height="30">
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" 
                    aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $base_url; ?>/pengguna/admin/dashboard.php">
                            <i class="bi bi-grid-fill"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $base_url; ?>/pengguna/admin/master_barang.php">
                            <i class="bi bi-box-seam-fill"></i> Master Barang
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $base_url; ?>/pengguna/admin/manajemen_laporan/laporan.php">
                            <i class="bi bi-archive-fill"></i> Laporan Selesai
                        </a>
                    </li>
                </ul>

                <div class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" 
                           data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($_SESSION["full_name"]); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li>
        <a class="dropdown-item" href="<?php echo $base_url; ?>/pengguna/admin/pengaturan/pengaturan.php">
            <i class="bi bi-gear"></i> Pengaturan
        </a>
    </li>
    <li><hr class="dropdown-divider"></li> <li>
                            <li>
                                <a class="dropdown-item" href="<?php echo $base_url; ?>/auth/logout.php" id="logout-link">
                                    <i class="bi bi-box-arrow-left"></i>Logout
                                </a>
                            </li>
                        </ul>
                    </li>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <main class="p-3">

    <!-- BEST PRACTICE: Script hanya untuk navbar functionality -->
    <!-- Logout confirmation di-handle oleh footer.php -->
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const navbar = document.querySelector('.navbar.fixed-top');
        const navbarNav = document.getElementById('navbarNav');
        
        // ========================================
        // FIX: DYNAMIC PADDING ADJUSTMENT
        // ========================================
        function adjustMainContentPadding() {
            const mainContent = document.querySelector('main.p-3');
            if (navbar && mainContent) {
                const navbarHeight = navbar.offsetHeight;
                // Add 16px (1rem) as extra space
                mainContent.style.paddingTop = `${navbarHeight + 16}px`;
            }
        }
        
        // Call on load and resize
        adjustMainContentPadding();
        window.addEventListener('resize', adjustMainContentPadding);

        // ========================================
        // NAVBAR SCROLL EFFECT
        // ========================================
        window.addEventListener('scroll', function () {
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }

            // Auto-close navbar on scroll (mobile only)
            if (window.innerWidth < 992 && navbarNav.classList.contains('show')) {
                const bsCollapse = new bootstrap.Collapse(navbarNav, {
                    toggle: false
                });
                bsCollapse.hide();
            }
        });

        // ========================================
        // ACTIVE PAGE HIGHLIGHT
        // ========================================
        const currentPath = window.location.pathname;
        const navLinks = document.querySelectorAll('.navbar-nav .nav-link');
        
        navLinks.forEach(link => {
            if (link.getAttribute('href') === currentPath) {
                link.classList.add('active');
            }
        });

        // ========================================
        // AUTO-CLOSE MOBILE MENU ON NAVIGATION
        // ========================================
        if (window.innerWidth < 992) {
            navLinks.forEach(link => {
                // Skip dropdown toggles
                if (!link.classList.contains('dropdown-toggle')) {
                    link.addEventListener('click', function() {
                        if (navbarNav.classList.contains('show')) {
                            const bsCollapse = bootstrap.Collapse.getInstance(navbarNav);
                            if (bsCollapse) {
                                bsCollapse.hide();
                            }
                        }
                    });
                }
            });
        }

        // ========================================
        // SMOOTH SCROLL TO TOP ON BRAND CLICK
        // ========================================
        const navbarBrand = document.querySelector('.navbar-brand');
        navbarBrand.addEventListener('click', function(e) {
            const brandHref = this.getAttribute('href');
            if (window.location.pathname === brandHref) {
                e.preventDefault();
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        });

        // ========================================
        // SMOOTH DROPDOWN ANIMATION
        // ========================================
        const dropdowns = document.querySelectorAll('.dropdown-toggle');
        dropdowns.forEach(dropdown => {
            dropdown.addEventListener('click', function() {
                const icon = this.querySelector('.dropdown-toggle::after');
                if (icon) {
                    icon.style.transform = this.getAttribute('aria-expanded') === 'true' 
                        ? 'rotate(180deg)' 
                        : 'rotate(0deg)';
                }
            });
        });

        // ========================================
        // HANDLE WINDOW RESIZE
        // ========================================
        let resizeTimer;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function() {
                adjustMainContentPadding();
                
                // Reset mobile menu on desktop resize
                if (window.innerWidth >= 992 && navbarNav.classList.contains('show')) {
                    const bsCollapse = bootstrap.Collapse.getInstance(navbarNav);
                    if (bsCollapse) {
                        bsCollapse.hide();
                    }
                }
            }, 250);
        });
    });
    </script>

</body>
</html>