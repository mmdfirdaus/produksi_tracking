<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Cek apakah pengguna sudah login dan memiliki peran superadmin
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'superadmin') {
    // Jika tidak, arahkan ke halaman login
    header("location: /produksi_tracking/auth/login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title : 'Dashboard'; ?> - Produksi Tracking</title>
    <link rel="icon" href="/produksi_tracking/assets/images/logo_v1.png" type="image/png">
    <link rel="apple-touch-icon" href="/produksi_tracking/assets/images/logo_v1.png">
    
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="/produksi_tracking/assets/css/style.css">

    <style>
        :root {
            --glass-bg: rgba(255, 255, 255, 0.1);
            --glass-border: rgba(255, 255, 255, 0.18);
            --accent-color: #667eea;
            --accent-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --hover-bg: rgba(255, 255, 255, 0.15);
            --text-primary: #ffffff;
            --text-secondary: rgba(255, 255, 255, 0.85);
        }

        body {
            padding-top: 80px; /* FIX: Tambahkan default padding untuk navbar fixed */
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 50%, #667eea 100%);
            background-attachment: fixed;
            min-height: 100vh;
        }

        /* Glassmorphism Navbar */
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

        /* Logo Styling */
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

        /* Nav Links */
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

        /* Dropdown Menu */
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

        /* Dropdown Toggle Arrow */
        .dropdown-toggle::after {
            transition: transform 0.3s ease;
        }

        .dropdown-toggle[aria-expanded="true"]::after {
            transform: rotate(180deg);
        }

        /* User Profile Dropdown */
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

        /* Navbar Toggler */
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

        /* Responsive Dropdown Hover */
        @media (min-width: 992px) {
            .dropdown:hover .dropdown-menu {
                display: block;
                margin-top: 0;
            }
        }

        /* Mobile Responsive */
        @media (max-width: 991px) {
            body {
                padding-top: 70px; /* FIX: Adjust untuk mobile */
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

        /* Smooth Scroll Behavior */
        html {
            scroll-behavior: smooth;
        }

        /* Content Card Enhancement */
        .card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.18);
            border-radius: 16px;
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.15);
        }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="/produksi_tracking/pengguna/superadmin/dashboard.php">
                <img src="/produksi_tracking/assets/images/logo_v1.png" alt="Yaden Logo" height="30">
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link" href="/produksi_tracking/pengguna/superadmin/dashboard.php">
                            <i class="bi bi-grid"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/produksi_tracking/pengguna/superadmin/master_barang.php">
                            <i class="bi bi-folder2-open"></i> Produksi
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="masterDataDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-stack"></i> Master Data
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="masterDataDropdown">
                            <li><a class="dropdown-item" href="/produksi_tracking/pengguna/superadmin/master_data/kelola_master_barang.php">
                                <i class="bi bi-box-seam"></i> Kelola Barang
                            </a></li>
                            <li><a class="dropdown-item" href="/produksi_tracking/pengguna/superadmin/master_data/kelola_kategori.php">
                                <i class="bi bi-tags"></i> Kelola Kategori
                            </a></li>
                            <li><a class="dropdown-item" href="/produksi_tracking/pengguna/superadmin/master_material/kelola_material.php">
                                <i class="bi bi-bricks"></i> Master Material
                            </a></li>
                            <li><a class="dropdown-item" href="/produksi_tracking/pengguna/superadmin/master_alur_mesin/kelola_alur_mesin.php">
                                <i class="bi bi-diagram-3"></i> Master Alur & Mesin
                            </a></li>
                        </ul>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/produksi_tracking/pengguna/superadmin/manajemen_laporan/laporan.php">
                            <i class="bi bi-archive-fill"></i> Laporan
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="pengaturanDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-gear"></i> Pengaturan
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="pengaturanDropdown">
                            <li><a class="dropdown-item" href="/produksi_tracking/pengguna/superadmin/manage_users.php">
                                <i class="bi bi-people-fill"></i> Kelola Pengguna
                            </a></li>
                            <li><a class="dropdown-item" href="/produksi_tracking/pengguna/superadmin/manage_user_access.php">
                                <i class="bi bi-key-fill"></i> Kelola Akses Pengguna
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="/produksi_tracking/pengguna/superadmin/manajemen_laporan/pengaturan_impor.php">
                                <i class="bi bi-upload"></i> Pengaturan Impor
                            </a></li>
                        </ul>
                    </li>
                </ul>

                <div class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($_SESSION["username"]); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li>
                                <a class="dropdown-item" href="#" id="logout-link">
                                    <i class="bi bi-box-arrow-left"></i> Logout
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
            </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const navbar = document.querySelector('.navbar.fixed-top');
        const navbarNav = document.getElementById('navbarNav');
        
        // === FIX: KODE UNTUK MENGATUR PADDING DINAMIS ===
        function adjustMainContentPadding() {
            const mainContent = document.querySelector('main.p-3');
            if (navbar && mainContent) {
                const navbarHeight = navbar.offsetHeight;
                // Menambahkan 16px (1rem) sebagai jarak ekstra agar konten tidak terlalu menempel
                mainContent.style.paddingTop = `${navbarHeight + 16}px`;
            }
        }
        
        // Panggil fungsi saat halaman dimuat
        adjustMainContentPadding();
        // Panggil lagi saat ukuran window berubah untuk responsivitas
        window.addEventListener('resize', adjustMainContentPadding);
        // ==============================================================

        // Navbar scroll effect
        window.addEventListener('scroll', function () {
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }

            // Auto-close navbar on scroll (mobile)
            if (navbarNav.classList.contains('show')) {
                const bsCollapse = new bootstrap.Collapse(navbarNav, {
                    toggle: false
                });
                bsCollapse.hide();
            }
        });

        // Active page highlight
        const currentPath = window.location.pathname;
        const navLinks = document.querySelectorAll('.navbar-nav .nav-link');
        
        navLinks.forEach(link => {
            if (link.getAttribute('href') === currentPath) {
                link.classList.add('active');
            }
        });

        // Logout confirmation
        const logoutButton = document.getElementById('logout-link');
        
        if (logoutButton) {
            logoutButton.addEventListener('click', function (e) {
                e.preventDefault();
                
                Swal.fire({
                    title: 'Anda Yakin?',
                    text: "Anda akan keluar dari sesi ini.",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#667eea',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Ya, Logout!',
                    cancelButtonText: 'Batal',
                    background: 'rgba(30, 60, 114, 0.95)',
                    color: '#fff',
                    backdrop: `
                        rgba(0,0,0,0.4)
                        left top
                        no-repeat
                    `
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = '/produksi_tracking/auth/logout.php';
                    }
                });
            });
        }

        // Smooth dropdown animation
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
    });
    </script>
</body>
</html>