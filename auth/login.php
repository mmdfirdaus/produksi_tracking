<?php
session_start();

// Jika sudah login, redirect ke dashboard sesuai role
if (isset($_SESSION['user_id'])) {
    switch ($_SESSION['role']) {
        case 'superadmin':
            header("Location: ../pengguna/superadmin/dashboard.php");
            break;
        case 'admin':
            header("Location: ../pengguna/admin/dashboard.php");
            break;
        case 'user':
            header("Location: ../pengguna/user/dashboard.php");
            break;
    }
    exit;
}

$error_message = $_SESSION['error_message'] ?? null;
$error_field = $_SESSION['error_field'] ?? null; // 'username', 'password', atau 'all'
$old_username = $_SESSION['old_username'] ?? ($_COOKIE['remembered_username'] ?? '');

// Hapus session error agar tidak muncul terus saat refresh
unset($_SESSION['error_message']);
unset($_SESSION['error_field']);
unset($_SESSION['old_username']);

// Helper function untuk mengecek error field (agar kodingan HTML lebih rapi)
function isInvalid($field, $error_field) {
    return ($error_field == $field || $error_field == 'all') ? 'border-danger text-danger' : '';
}

// Menangkap pesan dari proses_registrasi.php
$register_error = $_GET['register_error'] ?? null;
$register_success = $_GET['register_success'] ?? null;

// Ambil username dari cookie jika ada (untuk Remember Me)
$remembered_username = $_COOKIE['remembered_username'] ?? '';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistem Tracking Produksi</title>
    <link rel="icon" href="/produksi_tracking/assets/images/logo_v1.png" type="image/png">
    <link rel="apple-touch-icon" href="/produksi_tracking/assets/images/logo_v1.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            /* Modern Color Scheme */
            --primary-color: #4F46E5;
            --primary-dark: #4338CA;
            --primary-light: #6366F1;
            --secondary-color: #7C3AED;
            --accent-color: #EC4899;
            
            /* Neutral Colors */
            --dark: #1E293B;
            --dark-light: #334155;
            --gray: #64748B;
            --gray-light: #94A3B8;
            --border-color: #E2E8F0;
            --bg-light: #F8FAFC;
            
            /* Status Colors */
            --success: #10B981;
            --error: #EF4444;
            --warning: #F59E0B;
            --info: #3B82F6;
            
            /* Shadows */
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            
            /* Transitions */
            --transition-fast: 150ms cubic-bezier(0.4, 0, 0.2, 1);
            --transition-base: 250ms cubic-bezier(0.4, 0, 0.2, 1);
            --transition-slow: 350ms cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--bg-light);
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* ========== SPLIT SCREEN LAYOUT ========== */
        .login-container {
            display: flex;
            min-height: 100vh;
        }

        /* Left Panel - Branding */
        .branding-panel {
            flex: 1;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 4rem;
            color: white;
        }

        /* Animated Background Pattern */
        .branding-panel::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: 
                repeating-linear-gradient(0deg, transparent, transparent 2px, rgba(255,255,255,0.03) 2px, rgba(255,255,255,0.03) 4px),
                repeating-linear-gradient(90deg, transparent, transparent 2px, rgba(255,255,255,0.03) 2px, rgba(255,255,255,0.03) 4px);
            animation: movePattern 20s linear infinite;
        }

        @keyframes movePattern {
            0% { transform: translate(0, 0); }
            100% { transform: translate(50px, 50px); }
        }

        /* Floating Shapes */
        .shape {
            position: absolute;
            opacity: 0.1;
            animation: float 20s ease-in-out infinite;
        }

        .shape-1 {
            width: 300px;
            height: 300px;
            background: white;
            border-radius: 50%;
            top: -100px;
            right: -100px;
            animation-delay: 0s;
        }

        .shape-2 {
            width: 200px;
            height: 200px;
            background: white;
            border-radius: 50%;
            bottom: -50px;
            left: -50px;
            animation-delay: 5s;
        }

        .shape-3 {
            width: 150px;
            height: 150px;
            background: white;
            border-radius: 30% 70% 70% 30% / 30% 30% 70% 70%;
            top: 50%;
            left: 10%;
            animation-delay: 10s;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            33% { transform: translateY(-30px) rotate(120deg); }
            66% { transform: translateY(30px) rotate(240deg); }
        }

        .branding-content {
            position: relative;
            z-index: 1;
            text-align: center;
            max-width: 500px;
        }

        .brand-logo-container {
            width: 140px;
            height: 140px;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255, 255, 255, 0.2);
            border-radius: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            transition: transform var(--transition-base);
        }

        .brand-logo-container:hover {
            transform: scale(1.05) rotate(5deg);
        }

        .brand-logo-container img {
            max-width: 100px;
            max-height: 100px;
            filter: brightness(0) invert(1);
        }

        .brand-logo-container i {
            font-size: 4rem;
            color: white;
        }

        .branding-content h1 {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 1rem;
            line-height: 1.2;
        }

        .branding-content p {
            font-size: 1.125rem;
            opacity: 0.95;
            line-height: 1.6;
            margin-bottom: 2rem;
        }

        .feature-list {
            list-style: none;
            text-align: left;
            max-width: 400px;
            margin: 0 auto;
        }

        .feature-list li {
            padding: 0.75rem 0;
            display: flex;
            align-items: center;
            font-size: 1rem;
            opacity: 0.9;
        }

        .feature-list li i {
            margin-right: 1rem;
            font-size: 1.25rem;
            color: rgba(255, 255, 255, 0.9);
        }

        /* Right Panel - Form */
        .form-panel {
            flex: 1;
            background: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 3rem;
            position: relative;
        }

        .form-container {
            width: 100%;
            max-width: 480px;
            margin: 0 auto;
        }

        .form-header {
            margin-bottom: 2.5rem;
        }

        .form-header h2 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }

        .form-header p {
            color: var(--gray);
            font-size: 1rem;
        }

        /* Form Groups */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            font-weight: 500;
            color: var(--dark);
            margin-bottom: 0.5rem;
            font-size: 0.95rem;
        }

        .input-wrapper {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-light);
            font-size: 1.125rem;
            transition: color var(--transition-fast);
            pointer-events: none;
        }

        .form-input {
            width: 100%;
            height: 3.25rem;
            padding: 0.75rem 1rem 0.75rem 3rem;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 1rem;
            color: var(--dark);
            background: white;
            transition: all var(--transition-base);
            font-family: inherit;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
        }

        .form-input:focus + .input-icon {
            color: var(--primary-color);
        }

        .form-input::placeholder {
            color: var(--gray-light);
        }

        /* Password Toggle */
        .password-toggle {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--gray-light);
            cursor: pointer;
            padding: 0.5rem;
            font-size: 1.125rem;
            transition: color var(--transition-fast);
            z-index: 2;
        }

        .password-toggle:hover {
            color: var(--primary-color);
        }

        /* Remember Me & Forgot Password */
        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .remember-me {
            display: flex;
            align-items: center;
            cursor: pointer;
            user-select: none;
        }

        .remember-me input[type="checkbox"] {
            width: 1.25rem;
            height: 1.25rem;
            margin-right: 0.5rem;
            cursor: pointer;
            accent-color: var(--primary-color);
        }

        .remember-me label {
            font-size: 0.9rem;
            color: var(--dark-light);
            cursor: pointer;
            margin: 0;
        }

        .forgot-link {
            color: var(--primary-color);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            transition: color var(--transition-fast);
        }

        .forgot-link:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }

        /* Submit Button */
        .btn-submit {
            width: 100%;
            height: 3.25rem;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1.05rem;
            font-weight: 600;
            cursor: pointer;
            transition: all var(--transition-base);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(79, 70, 229, 0.4);
        }

        .btn-submit:active {
            transform: translateY(0);
        }

        .btn-submit:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        /* Loading Spinner */
        .spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        .btn-submit.loading .spinner {
            display: inline-block;
        }

        .btn-submit.loading .btn-text {
            display: none;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Divider */
        .divider {
            display: flex;
            align-items: center;
            margin: 2rem 0;
            color: var(--gray-light);
            font-size: 0.875rem;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border-color);
        }

        .divider span {
            padding: 0 1rem;
        }

        /* Register Link */
        .register-link {
            text-align: center;
            padding: 1.5rem;
            background: var(--bg-light);
            border-radius: 10px;
            margin-top: 1.5rem;
        }

        .register-link p {
            color: var(--dark-light);
            margin: 0;
            font-size: 0.95rem;
        }

        .register-link a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
            transition: color var(--transition-fast);
        }

        .register-link a:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }

        /* ========== TOAST NOTIFICATION ========== */
        .toast-container {
            position: fixed;
            top: 2rem;
            right: 2rem;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 1rem;
            pointer-events: none;
        }

        .toast {
            min-width: 320px;
            max-width: 400px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
            padding: 1rem 1.25rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            transform: translateX(450px);
            opacity: 0;
            animation: slideIn var(--transition-slow) forwards;
            pointer-events: all;
            border-left: 4px solid;
        }

        @keyframes slideIn {
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes slideOut {
            to {
                transform: translateX(450px);
                opacity: 0;
            }
        }

        .toast.hiding {
            animation: slideOut var(--transition-slow) forwards;
        }

        .toast-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            flex-shrink: 0;
        }

        .toast.success {
            border-left-color: var(--success);
        }

        .toast.success .toast-icon {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }

        .toast.error {
            border-left-color: var(--error);
        }

        .toast.error .toast-icon {
            background: rgba(239, 68, 68, 0.1);
            color: var(--error);
        }

        .toast.warning {
            border-left-color: var(--warning);
        }

        .toast.warning .toast-icon {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
        }

        .toast.info {
            border-left-color: var(--info);
        }

        .toast.info .toast-icon {
            background: rgba(59, 130, 246, 0.1);
            color: var(--info);
        }

        .toast-content {
            flex: 1;
        }

        .toast-title {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.25rem;
            font-size: 0.95rem;
        }

        .toast-message {
            color: var(--gray);
            font-size: 0.875rem;
            line-height: 1.4;
        }

        .toast-close {
            background: none;
            border: none;
            color: var(--gray-light);
            cursor: pointer;
            padding: 0.25rem;
            font-size: 1.125rem;
            transition: color var(--transition-fast);
            flex-shrink: 0;
        }

        .toast-close:hover {
            color: var(--dark);
        }

        /* ========== MODAL STYLES ========== */
        .modal-content {
            border: none;
            border-radius: 16px;
            box-shadow: var(--shadow-xl);
        }

        .modal-header {
            border-bottom: 1px solid var(--border-color);
            padding: 1.5rem 2rem;
            background: var(--bg-light);
            border-radius: 16px 16px 0 0;
        }

        .modal-title {
            font-weight: 700;
            color: var(--dark);
            font-size: 1.25rem;
        }

        .modal-body {
            padding: 2rem;
        }

        .modal-body .form-control {
            height: 3rem;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            padding: 0.75rem 1rem;
            transition: all var(--transition-base);
        }

        .modal-body .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
        }

        .modal-body .input-group-text {
            background: white;
            border: 2px solid var(--border-color);
            border-right: none;
            color: var(--gray);
        }

        .modal-body .input-group .form-control {
            border-left: none;
        }

        .modal-body .btn-primary,
        .modal-body .btn-success {
            height: 3rem;
            font-weight: 600;
            border-radius: 8px;
            transition: all var(--transition-base);
        }

        .modal-body .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            border: none;
        }

        .modal-body .btn-success {
            background: var(--success);
            border: none;
        }

        .modal-body .btn-primary:hover,
        .modal-body .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        /* ========== RESPONSIVE DESIGN ========== */
        
        /* Tablet */
        @media (max-width: 1024px) {
            .branding-panel {
                padding: 3rem 2rem;
            }
            
            .form-panel {
                padding: 2rem;
            }
            
            .branding-content h1 {
                font-size: 2rem;
            }
        }

        /* Mobile */
        @media (max-width: 768px) {
            .login-container {
                flex-direction: column;
            }

            .branding-panel {
                min-height: 40vh;
                padding: 2rem 1.5rem;
            }

            .brand-logo-container {
                width: 100px;
                height: 100px;
                border-radius: 20px;
                margin-bottom: 1.5rem;
            }

            .brand-logo-container img,
            .brand-logo-container i {
                max-width: 70px;
                max-height: 70px;
                font-size: 3rem;
            }

            .branding-content h1 {
                font-size: 1.75rem;
            }

            .branding-content p {
                font-size: 1rem;
            }

            .feature-list {
                display: none; /* Hide on mobile to save space */
            }

            .form-panel {
                padding: 2rem 1.5rem;
                min-height: 60vh;
            }

            .form-header h2 {
                font-size: 1.5rem;
            }

            .form-header p {
                font-size: 0.9rem;
            }

            .form-input,
            .btn-submit {
                height: 3rem;
                font-size: 0.95rem;
            }

            .toast-container {
                top: 1rem;
                right: 1rem;
                left: 1rem;
            }

            .toast {
                min-width: auto;
                width: 100%;
                max-width: none;
            }

            .form-options {
                gap: 0.75rem;
            }

            .remember-me label,
            .forgot-link {
                font-size: 0.85rem;
            }
        }

        /* Small Mobile */
        @media (max-width: 480px) {
            .branding-panel {
                min-height: 30vh;
                padding: 1.5rem 1rem;
            }

            .brand-logo-container {
                width: 80px;
                height: 80px;
                margin-bottom: 1rem;
            }

            .brand-logo-container img,
            .brand-logo-container i {
                max-width: 55px;
                max-height: 55px;
                font-size: 2.5rem;
            }

            .branding-content h1 {
                font-size: 1.5rem;
            }

            .branding-content p {
                font-size: 0.9rem;
            }

            .form-panel {
                padding: 1.5rem 1rem;
            }

            .form-header {
                margin-bottom: 1.5rem;
            }

            .form-group {
                margin-bottom: 1.25rem;
            }

            .register-link {
                padding: 1rem;
            }
        }

        /* Landscape Mobile */
        @media (max-width: 768px) and (orientation: landscape) {
            .login-container {
                flex-direction: row;
            }

            .branding-panel {
                min-height: 100vh;
            }

            .feature-list {
                display: none;
            }

            .branding-content h1 {
                font-size: 1.5rem;
            }

            .branding-content p {
                font-size: 0.9rem;
            }
        }

        /* Hide scrollbar for modal in some browsers */
        .modal-body::-webkit-scrollbar {
            width: 6px;
        }

        .modal-body::-webkit-scrollbar-track {
            background: var(--bg-light);
        }

        .modal-body::-webkit-scrollbar-thumb {
            background: var(--gray-light);
            border-radius: 3px;
        }

        /* Accessibility improvements */
        .form-input:focus-visible,
        .btn-submit:focus-visible,
        .password-toggle:focus-visible {
            outline: 2px solid var(--primary-color);
            outline-offset: 2px;
        }

        /* Smooth page load animation */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .form-container {
            animation: fadeInUp 0.6s ease-out;
        }

        .branding-content {
            animation: fadeInUp 0.6s ease-out 0.2s both;
        }

        /* --- TAMBAHAN CSS UNTUK ALERT & ERROR --- */
        .custom-alert {
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            animation: shake 0.5s cubic-bezier(.36,.07,.19,.97) both;
        }
        
        .border-danger {
            border-color: #EF4444 !important;
            background-color: #FEF2F2;
        }
        
        .border-danger:focus {
            box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.1) !important;
        }
        
        /* Mengubah warna icon menjadi merah jika error */
        .input-wrapper .input-icon.text-danger {
            color: #EF4444 !important;
        }

        @keyframes shake {
            10%, 90% { transform: translate3d(-1px, 0, 0); }
            20%, 80% { transform: translate3d(2px, 0, 0); }
            30%, 50%, 70% { transform: translate3d(-4px, 0, 0); }
            40%, 60% { transform: translate3d(4px, 0, 0); }
        }

    </style>
</head>
<body>
    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>

    <!-- Main Login Container -->
    <div class="login-container">
        
        <!-- Left Panel - Branding -->
        <div class="branding-panel">
            <!-- Floating Shapes -->
            <div class="shape shape-1"></div>
            <div class="shape shape-2"></div>
            <div class="shape shape-3"></div>

            <div class="branding-content">
                <div class="brand-logo-container">
                    <!-- Ganti dengan logo Anda atau gunakan icon -->
                     <img src="../assets/images/logo_v1.png" alt="Logo Perusahaan"> 
                     <!-- <i class="fas fa-industry"></i> -->
                </div>
                
                <h1>Sistem Tracking Produksi</h1>
                <p>Platform terintegrasi untuk monitoring dan manajemen produksi secara real-time</p>
                
                <ul class="feature-list">
                    <li>
                        <i class="fas fa-check-circle"></i>
                        <span>Monitoring Real-time</span>
                    </li>
                    <li>
                        <i class="fas fa-check-circle"></i>
                        <span>Laporan Komprehensif</span>
                    </li>
                    <li>
                        <i class="fas fa-check-circle"></i>
                        <span>Multi-level Access Control</span>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Right Panel - Login Form -->
        <div class="form-panel">
            <div class="form-container">
                <div class="form-header">
                    <h2>Selamat Datang! 👋</h2>
                    <p>Silakan login untuk mengakses sistem</p>
                </div>

                <!-- Login Form -->
                <form id="loginForm" action="proses_login.php" method="POST">
                    <?php if ($error_message): ?>
                <div class="alert alert-danger custom-alert" role="alert">
                    <i class="fas fa-exclamation-circle fa-lg"></i>
                    <div>
                        <strong>Login Gagal!</strong><br>
                        <small><?php echo htmlspecialchars($error_message); ?></small>
                    </div>
                </div>
                <?php endif; ?>
                    
                    <!-- Username Field -->
                    <div class="form-group">
                        <label for="username" class="form-label">Username</label>
                        <div class="input-wrapper">
                            <input 
                                type="text" 
                                class="form-input <?php echo isInvalid('username', $error_field); ?>" 
                                id="username" 
                                name="username" 
                                placeholder="Masukkan username Anda"
                                value="<?php echo htmlspecialchars($old_username); ?>"
                                required
                            >
                            <i class="fas fa-user input-icon <?php echo isInvalid('username', $error_field); ?>"></i>
                        </div>
                    </div>

                    <!-- Password Field -->
                    <div class="input-wrapper">
                            <input 
                                type="password" 
                                class="form-input <?php echo isInvalid('password', $error_field); ?>" 
                                id="password" 
                                name="password" 
                                placeholder="Masukkan password Anda"
                                required
                            >
                            <i class="fas fa-lock input-icon <?php echo isInvalid('password', $error_field); ?>"></i>
                            <button type="button" class="password-toggle" id="togglePassword">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>

                    <!-- Remember Me & Forgot Password -->
                    <div class="form-options">
                        <div class="remember-me">
                            <input 
                                type="checkbox" 
                                id="rememberMe" 
                                name="remember_me"
                                <?php echo !empty($remembered_username) ? 'checked' : ''; ?>
                            >
                            <label for="rememberMe">Ingat saya</label>
                        </div>
                        <a href="lupa_password.php" class="forgot-link">Lupa Password?</a>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" class="btn-submit" id="submitBtn">
                        <span class="spinner"></span>
                        <span class="btn-text">
                            <i class="fas fa-sign-in-alt"></i>
                            Login
                        </span>
                    </button>
                </form>

                <!-- Divider -->
                <div class="divider">
                    <span>Atau</span>
                </div>

                <!-- Register Link -->
                <div class="register-link">
                    <p>
                        Belum punya akun? 
                        <a href="#" data-bs-toggle="modal" data-bs-target="#registerModal">
                            <i class="fas fa-user-plus"></i> Daftar Sekarang
                        </a>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Registration Modal -->
    <div class="modal fade" id="registerModal" tabindex="-1" aria-labelledby="registerModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="registerModalLabel">
                        <i class="fas fa-user-plus me-2"></i>
                        Buat Akun Superadmin Baru
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    
                    <div id="modal-alert-placeholder"></div>

                    <form id="registerForm" action="proses_registrasi.php" method="POST" onsubmit="return validateRegistration();">

                        <!-- Step 1: Special Key Verification -->
                        <div id="step-1-key">
                            <p class="text-muted mb-3">
                                <i class="fas fa-info-circle me-1"></i>
                                Untuk mendaftar sebagai Superadmin, silakan masukkan Kunci Spesial Anda.
                            </p>
                            <div class="mb-3">
                                <label for="special_key" class="form-label">Kunci Spesial</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-key"></i></span>
                                    <input type="password" class="form-control" id="special_key_input" placeholder="Masukkan kunci spesial">
                                </div>
                            </div>
                            <button type="button" class="btn btn-primary w-100" id="verifyKeyButton">
                                <i class="fas fa-shield-alt me-2"></i>
                                Verifikasi Kunci
                            </button>
                        </div>

                        <!-- Step 2: Registration Form -->
                        <div id="step-2-register" style="display: none;">
                            <input type="hidden" id="special_key_verified" name="special_key_verified" value="">

                            <div class="mb-3">
                                <label for="reg_full_name" class="form-label">Nama Lengkap</label>
                                <input type="text" class="form-control" id="reg_full_name" name="full_name" placeholder="Masukkan nama lengkap" required disabled>
                            </div>
                            <div class="mb-3">
                                <label for="reg_username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="reg_username" name="username" placeholder="Pilih username" required disabled>
                            </div>
                            <div class="mb-3">
                                <label for="reg_password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="reg_password" name="password" placeholder="Buat password" required disabled>
                            </div>
                            <div class="mb-3">
                                <label for="reg_confirm_password" class="form-label">Konfirmasi Password</label>
                                <input type="password" class="form-control" id="reg_confirm_password" name="confirm_password" placeholder="Konfirmasi password" required disabled>
                            </div>
                            <button type="submit" class="btn btn-success w-100">
                                <i class="fas fa-check-circle me-2"></i>
                                Buat Akun
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // ========== TOAST NOTIFICATION SYSTEM ==========
        class ToastManager {
            constructor() {
                this.container = document.getElementById('toastContainer');
            }

            show(message, type = 'info', title = '', duration = 5000) {
                const toast = document.createElement('div');
                toast.className = `toast ${type}`;
                
                const icons = {
                    success: 'fa-check-circle',
                    error: 'fa-exclamation-circle',
                    warning: 'fa-exclamation-triangle',
                    info: 'fa-info-circle'
                };

                const titles = {
                    success: title || 'Berhasil',
                    error: title || 'Error',
                    warning: title || 'Peringatan',
                    info: title || 'Informasi'
                };

                toast.innerHTML = `
                    <div class="toast-icon">
                        <i class="fas ${icons[type]}"></i>
                    </div>
                    <div class="toast-content">
                        <div class="toast-title">${titles[type]}</div>
                        <div class="toast-message">${message}</div>
                    </div>
                    <button class="toast-close" onclick="this.closest('.toast').remove()">
                        <i class="fas fa-times"></i>
                    </button>
                `;

                this.container.appendChild(toast);

                // Auto remove after duration
                if (duration > 0) {
                    setTimeout(() => {
                        toast.classList.add('hiding');
                        setTimeout(() => toast.remove(), 350);
                    }, duration);
                }

                return toast;
            }

            success(message, title = '') {
                return this.show(message, 'success', title);
            }

            error(message, title = '') {
                return this.show(message, 'error', title);
            }

            warning(message, title = '') {
                return this.show(message, 'warning', title);
            }

            info(message, title = '') {
                return this.show(message, 'info', title);
            }
        }

        const toast = new ToastManager();

        // ========== SHOW PHP MESSAGES AS TOASTS ==========
        

        <?php if ($register_error): ?>
            toast.error('<?php echo addslashes($register_error); ?>', 'Registrasi Gagal');
        <?php endif; ?>

        <?php if ($register_success): ?>
            toast.success('<?php echo addslashes($register_success); ?>', 'Registrasi Berhasil');
        <?php endif; ?>

        // ========== PASSWORD TOGGLE ==========
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');

        togglePassword.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            const icon = this.querySelector('i');
            icon.classList.toggle('fa-eye');
            icon.classList.toggle('fa-eye-slash');
        });

        // ========== FORM SUBMIT WITH LOADING STATE ==========
        const loginForm = document.getElementById('loginForm');
        const submitBtn = document.getElementById('submitBtn');

        loginForm.addEventListener('submit', function(e) {
            // Add loading state
            submitBtn.classList.add('loading');
            submitBtn.disabled = true;

            // If validation fails, remove loading state
            if (!loginForm.checkValidity()) {
                e.preventDefault();
                submitBtn.classList.remove('loading');
                submitBtn.disabled = false;
                toast.error('Mohon lengkapi semua field yang diperlukan', 'Form Tidak Lengkap');
            }
        });

        // ========== REMEMBER ME FUNCTIONALITY ==========
        const rememberMeCheckbox = document.getElementById('rememberMe');
        const usernameInput = document.getElementById('username');

        // Handle form submission for remember me
        loginForm.addEventListener('submit', function(e) {
            if (rememberMeCheckbox.checked && usernameInput.value) {
                // Set cookie untuk 30 hari
                const expires = new Date();
                expires.setTime(expires.getTime() + (30 * 24 * 60 * 60 * 1000));
                document.cookie = `remembered_username=${encodeURIComponent(usernameInput.value)}; expires=${expires.toUTCString()}; path=/; SameSite=Strict`;
            } else {
                // Hapus cookie jika tidak dicentang
                document.cookie = 'remembered_username=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/; SameSite=Strict';
            }
        });

        // ========== REGISTRATION MODAL ==========
        const correctSpecialKey = "AGS_Buat_Account_SuperAdmin";
        const registerModal = document.getElementById('registerModal');
        const step1 = document.getElementById('step-1-key');
        const step2 = document.getElementById('step-2-register');
        const verifyButton = document.getElementById('verifyKeyButton');
        const keyInput = document.getElementById('special_key_input');
        const modalAlert = document.getElementById('modal-alert-placeholder');
        const hiddenKeyInput = document.getElementById('special_key_verified');
        
        const formInputs = [
            document.getElementById('reg_full_name'),
            document.getElementById('reg_username'),
            document.getElementById('reg_password'),
            document.getElementById('reg_confirm_password')
        ];

        // Modal alert function
        function showModalAlert(message, type = 'danger') {
            modalAlert.innerHTML = `
                <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'} me-2"></i>
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            `;
        }

        // Reset modal on show
        registerModal.addEventListener('show.bs.modal', function () {
            step1.style.display = 'block';
            step2.style.display = 'none';
            
            keyInput.value = '';
            hiddenKeyInput.value = '';
            formInputs.forEach(input => {
                input.value = '';
                input.disabled = true;
            });
            
            modalAlert.innerHTML = '';
        });

        // Verify special key
        verifyButton.addEventListener('click', function() {
            const enteredKey = keyInput.value;
            
            if (enteredKey === correctSpecialKey) {
                step1.style.display = 'none';
                step2.style.display = 'block';
                
                hiddenKeyInput.value = enteredKey;
                
                formInputs.forEach(input => {
                    input.disabled = false;
                });
                
                formInputs[0].focus();
                showModalAlert('Kunci terverifikasi! Silakan lengkapi data diri Anda.', 'success');
            } else {
                showModalAlert('Kunci Spesial yang Anda masukkan salah. Silakan coba lagi.', 'danger');
                keyInput.value = '';
                keyInput.focus();
            }
        });

        // Allow Enter key to verify
        keyInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                verifyButton.click();
            }
        });

        // Validate registration
        function validateRegistration() {
            const password = document.getElementById('reg_password').value;
            const confirmPassword = document.getElementById('reg_confirm_password').value;

            if (password !== confirmPassword) {
                showModalAlert('Password dan Konfirmasi Password tidak cocok. Silakan periksa kembali.', 'danger');
                return false;
            }
            
            if (password.length < 6) {
                showModalAlert('Password minimal 6 karakter.', 'danger');
                return false;
            }
            
            return true;
        }

        // ========== FORM INPUT ENHANCEMENTS ==========
        // Auto-focus on first empty input
        document.addEventListener('DOMContentLoaded', function() {
            const firstInput = document.querySelector('.form-input:not([value])');
            if (firstInput && !firstInput.value) {
                firstInput.focus();
            }
        });

        // Add visual feedback for form validation
        const formInputs_login = document.querySelectorAll('.form-input');
        formInputs_login.forEach(input => {
            input.addEventListener('blur', function() {
                if (this.value && !this.checkValidity()) {
                    this.style.borderColor = 'var(--error)';
                } else if (this.value) {
                    this.style.borderColor = 'var(--success)';
                } else {
                    this.style.borderColor = 'var(--border-color)';
                }
            });

            input.addEventListener('input', function() {
                if (this.style.borderColor !== 'var(--primary-color)') {
                    this.style.borderColor = 'var(--border-color)';
                }
            });
        });

        // ========== ACCESSIBILITY ==========
        // Keyboard navigation for password toggle
        togglePassword.addEventListener('keypress', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                this.click();
            }
        });
    </script>
</body>
</html>