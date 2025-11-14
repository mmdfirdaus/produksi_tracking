<?php
session_start();
// Menginclude koneksi database dan header
include_once __DIR__ . '/../../../system/database_connection.php';
include_once __DIR__ . '/../../../templates/header_admin.php';

// Pastikan pengguna sudah login dan rolenya adalah 'admin'
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../auth/login.php');
    exit;
}

$id_user = $_SESSION['user_id'];
$user = null;
$alurs = [];

// Mengambil data pengguna saat ini untuk ditampilkan di form
try {
    // 1. Ambil data user
    $stmt_user = $pdo->prepare("SELECT username, full_name, role FROM users WHERE id = ?");
    $stmt_user->execute([$id_user]);
    $user = $stmt_user->fetch(PDO::FETCH_ASSOC);

    // 2. Ambil data alur yang di-assign ke admin ini
    $stmt_alur = $pdo->prepare("
        SELECT ma.nama_alur 
        FROM master_alur ma
        JOIN admin_tahapan_access ata ON ma.id_alur = ata.id_tahapan
        WHERE ata.id_user = ?
        ORDER BY ma.urutan, ma.nama_alur
    ");
    $stmt_alur->execute([$id_user]);
    $alurs = $stmt_alur->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}

// Jika pengguna tidak ditemukan
if (!$user) {
    echo "Data pengguna tidak ditemukan.";
    include_once __DIR__ . '/../../../templates/footer.php';
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan Akun - Admin</title>
    
    <style>
        /* ========================================= */
        /* ROOT VARIABLES - Consistent with Header */
        /* ========================================= */
        :root {
            --glass-bg: rgba(255, 255, 255, 0.1);
            --glass-border: rgba(255, 255, 255, 0.18);
            --accent-color: #667eea;
            --accent-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --hover-bg: rgba(255, 255, 255, 0.15);
            --text-primary: #2c3e50;
            --text-secondary: #6c757d;
            --card-bg: rgba(255, 255, 255, 0.98);
            --input-bg: rgba(255, 255, 255, 0.95);
            --shadow-sm: 0 2px 8px rgba(31, 38, 135, 0.1);
            --shadow-md: 0 8px 32px rgba(31, 38, 135, 0.15);
            --shadow-lg: 0 12px 48px rgba(31, 38, 135, 0.2);
            --success-gradient: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            --warning-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }

        /* ========================================= */
        /* MAIN CONTAINER */
        /* ========================================= */
        .settings-container {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        /* ========================================= */
        /* PAGE HEADER */
        /* ========================================= */
        .page-header {
            text-align: center;
            margin-bottom: 2rem;
            animation: fadeInDown 0.6s ease;
        }

        .page-header h1 {
            font-size: 2rem;
            font-weight: 700;
            background: var(--accent-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
        }

        .page-header p {
            color: var(--text-secondary);
            font-size: 0.95rem;
        }

        /* ========================================= */
        /* TWO COLUMN LAYOUT - DESKTOP */
        /* ========================================= */
        .settings-grid {
            display: grid;
            grid-template-columns: 60% 40%;
            gap: 1.5rem;
            align-items: start;
        }

        /* ========================================= */
        /* GLASSMORPHISM CARD */
        /* ========================================= */
        .settings-card {
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            box-shadow: var(--shadow-lg);
            padding: 2.5rem;
            animation: fadeInUp 0.6s ease;
            transition: all 0.3s ease;
            height: fit-content;
        }

        .settings-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 16px 64px rgba(31, 38, 135, 0.25);
        }

        /* ========================================= */
        /* INFO CARD - RIGHT COLUMN */
        /* ========================================= */
        .info-card {
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            box-shadow: var(--shadow-lg);
            padding: 2rem;
            animation: fadeInUp 0.6s ease 0.2s backwards;
            transition: all 0.3s ease;
            position: sticky;
            top: 100px;
        }

        .info-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 16px 64px rgba(31, 38, 135, 0.25);
        }

        /* ========================================= */
        /* ALERT MESSAGES */
        /* ========================================= */
        .alert-modern {
            border: none;
            border-radius: 12px;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            animation: slideInRight 0.5s ease;
            box-shadow: var(--shadow-sm);
        }

        .alert-modern i {
            font-size: 1.5rem;
        }

        .alert-modern.alert-success {
            background: var(--success-gradient);
            color: white;
        }

        .alert-modern.alert-danger {
            background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%);
            color: white;
        }

        .alert-modern .btn-close {
            filter: brightness(0) invert(1);
        }

        /* ========================================= */
        /* SECTION HEADERS */
        /* ========================================= */
        .section-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid transparent;
            border-image: var(--accent-gradient) 1;
        }

        .section-header i {
            font-size: 1.5rem;
            background: var(--accent-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .section-header h5 {
            margin: 0;
            font-weight: 600;
            color: var(--text-primary);
        }

        /* ========================================= */
        /* FORM GROUPS */
        /* ========================================= */
        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }

        .form-group label {
            display: block;
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            font-size: 0.95rem;
        }

        .form-group label i {
            margin-right: 0.5rem;
            color: var(--accent-color);
        }

        /* ========================================= */
        /* INPUT FIELDS */
        /* ========================================= */
        .form-control-modern {
            width: 100%;
            padding: 0.875rem 1rem;
            font-size: 0.95rem;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            background: var(--input-bg);
            color: var(--text-primary);
            transition: all 0.3s ease;
            font-family: inherit;
        }

        .form-control-modern:focus {
            outline: none;
            border-color: var(--accent-color);
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
            background: white;
        }

        .form-control-modern:disabled {
            background: #f5f5f5;
            color: var(--text-secondary);
            cursor: not-allowed;
            border-color: #e0e0e0;
        }

        /* ========================================= */
        /* PASSWORD INPUT WITH TOGGLE */
        /* ========================================= */
        .password-wrapper {
            position: relative;
        }

        .password-wrapper .form-control-modern {
            padding-right: 3rem;
        }

        .password-toggle {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-secondary);
            cursor: pointer;
            font-size: 1.25rem;
            padding: 0.25rem;
            transition: all 0.3s ease;
            z-index: 10;
        }

        .password-toggle:hover {
            color: var(--accent-color);
            transform: translateY(-50%) scale(1.1);
        }

        /* ========================================= */
        /* INFO BOX */
        /* ========================================= */
        .info-box {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
            border-left: 4px solid var(--accent-color);
            border-radius: 12px;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: start;
            gap: 0.75rem;
        }

        .info-box i {
            font-size: 1.5rem;
            color: var(--accent-color);
            margin-top: 0.25rem;
        }

        .info-box-content h6 {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            font-size: 0.95rem;
        }

        .info-box-content p {
            margin: 0;
            color: var(--text-secondary);
            font-size: 0.875rem;
            line-height: 1.5;
        }

        /* ========================================= */
        /* ALUR MESIN LIST */
        /* ========================================= */
        .alur-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .alur-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.875rem 1rem;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);
            border-radius: 10px;
            margin-bottom: 0.5rem;
            transition: all 0.3s ease;
            border: 1px solid rgba(102, 126, 234, 0.1);
        }

        .alur-item:hover {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
            transform: translateX(5px);
            border-color: var(--accent-color);
        }

        .alur-item i {
            color: var(--accent-color);
            font-size: 1.25rem;
        }

        .alur-item span {
            color: var(--text-primary);
            font-weight: 500;
            font-size: 0.9rem;
        }

        /* ========================================= */
        /* WARNING ALERT */
        /* ========================================= */
        .warning-box {
            background: var(--warning-gradient);
            color: white;
            border-radius: 12px;
            padding: 1rem 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .warning-box i {
            font-size: 1.5rem;
        }

        /* ========================================= */
        /* HELPER TEXT */
        /* ========================================= */
        .form-text-modern {
            display: block;
            margin-top: 0.5rem;
            font-size: 0.85rem;
            color: var(--text-secondary);
        }

        .form-text-modern i {
            margin-right: 0.25rem;
        }

        /* ========================================= */
        /* VALIDATION FEEDBACK */
        /* ========================================= */
        .invalid-feedback-modern {
            display: none;
            margin-top: 0.5rem;
            font-size: 0.85rem;
            color: #dc3545;
        }

        .invalid-feedback-modern i {
            margin-right: 0.25rem;
        }

        .invalid-feedback-modern.show {
            display: block;
            animation: shake 0.5s ease;
        }

        .valid-feedback-modern {
            display: none;
            margin-top: 0.5rem;
            font-size: 0.85rem;
            color: #28a745;
        }

        .valid-feedback-modern i {
            margin-right: 0.25rem;
        }

        .valid-feedback-modern.show {
            display: block;
        }

        .form-control-modern.is-invalid {
            border-color: #dc3545;
        }

        .form-control-modern.is-valid {
            border-color: #28a745;
        }

        /* ========================================= */
        /* SUBMIT BUTTON */
        /* ========================================= */
        .btn-submit-modern {
            width: 100%;
            padding: 1rem 2rem;
            font-size: 1rem;
            font-weight: 600;
            color: white;
            background: var(--accent-gradient);
            border: none;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 2rem;
        }

        .btn-submit-modern:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        .btn-submit-modern:active {
            transform: translateY(0);
        }

        .btn-submit-modern i {
            font-size: 1.25rem;
        }

        .btn-submit-modern.loading {
            pointer-events: none;
            opacity: 0.7;
        }

        .btn-submit-modern.loading i {
            animation: spin 1s linear infinite;
        }

        /* ========================================= */
        /* BACK TO TOP BUTTON */
        /* ========================================= */
        .back-to-top {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            width: 50px;
            height: 50px;
            background: var(--accent-gradient);
            color: white;
            border: none;
            border-radius: 50%;
            cursor: pointer;
            display: none;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
            transition: all 0.3s ease;
            z-index: 1000;
        }

        .back-to-top:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
        }

        .back-to-top.show {
            display: flex;
            animation: fadeInUp 0.3s ease;
        }

        /* ========================================= */
        /* ANIMATIONS */
        /* ========================================= */
        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

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

        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        /* ========================================= */
        /* RESPONSIVE DESIGN - TABLET */
        /* ========================================= */
        @media (max-width: 1199px) {
            .settings-grid {
                grid-template-columns: 1fr;
            }

            .info-card {
                position: static;
            }
        }

        /* ========================================= */
        /* RESPONSIVE DESIGN - MOBILE */
        /* ========================================= */
        @media (max-width: 768px) {
            .settings-container {
                padding: 0 0.75rem;
                margin: 1rem auto;
            }

            .settings-card,
            .info-card {
                padding: 1.5rem;
                border-radius: 16px;
            }

            .page-header h1 {
                font-size: 1.5rem;
            }

            .page-header p {
                font-size: 0.875rem;
            }

            .section-header {
                margin-bottom: 1rem;
            }

            .section-header h5 {
                font-size: 1rem;
            }

            .form-group {
                margin-bottom: 1.25rem;
            }

            .form-control-modern {
                padding: 0.75rem;
                font-size: 16px; /* Prevent iOS zoom */
            }

            .btn-submit-modern {
                padding: 0.875rem 1.5rem;
                font-size: 0.95rem;
            }

            .info-box,
            .warning-box {
                padding: 0.875rem 1rem;
            }

            .info-box-content h6 {
                font-size: 0.875rem;
            }

            .info-box-content p {
                font-size: 0.8rem;
            }

            .alur-item {
                padding: 0.75rem;
            }

            .alur-item span {
                font-size: 0.85rem;
            }

            .back-to-top {
                bottom: 1rem;
                right: 1rem;
                width: 45px;
                height: 45px;
                font-size: 1.25rem;
            }
        }

        /* ========================================= */
        /* TOUCH DEVICE OPTIMIZATIONS */
        /* ========================================= */
        @media (hover: none) and (pointer: coarse) {
            .form-control-modern,
            .btn-submit-modern,
            .password-toggle,
            .back-to-top {
                -webkit-tap-highlight-color: transparent;
            }

            .btn-submit-modern:active {
                transform: scale(0.98);
            }

            .password-toggle:active {
                transform: translateY(-50%) scale(0.95);
            }

            .back-to-top:active {
                transform: scale(0.95);
            }
        }

        /* ========================================= */
        /* SAFE AREA INSETS FOR MODERN PHONES */
        /* ========================================= */
        @supports (padding: max(0px)) {
            .settings-container {
                padding-left: max(1rem, env(safe-area-inset-left));
                padding-right: max(1rem, env(safe-area-inset-right));
                padding-bottom: max(2rem, env(safe-area-inset-bottom));
            }

            .back-to-top {
                bottom: max(2rem, env(safe-area-inset-bottom) + 1rem);
                right: max(2rem, env(safe-area-inset-right) + 1rem);
            }
        }

        /* ========================================= */
        /* CUSTOM SCROLLBAR */
        /* ========================================= */
        ::-webkit-scrollbar {
            width: 10px;
        }

        ::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--accent-gradient);
            border-radius: 5px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
        }
    </style>
</head>
<body>

<div class="settings-container">
    <!-- Page Header -->
    <div class="page-header">
        <h1><i class="bi bi-gear-fill"></i> Pengaturan Akun Admin</h1>
        <p>Kelola informasi profil, keamanan akun, dan akses alur mesin Anda</p>
    </div>

    <!-- Success Message -->
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert-modern alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill"></i>
            <div>
                <strong>Berhasil!</strong> <?php echo $_SESSION['success_message']; ?>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <!-- Error Message -->
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert-modern alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <div>
                <strong>Gagal!</strong> <?php echo $_SESSION['error_message']; ?>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <!-- Two Column Grid Layout -->
    <div class="settings-grid">
        
        <!-- LEFT COLUMN: Settings Card (60%) -->
        <div class="settings-card">
            <form id="settingsForm" action="proses_pengaturan.php" method="POST">
                
                <!-- Data Akun Section -->
                <div class="section-header">
                    <i class="bi bi-person-circle"></i>
                    <h5>Data Akun</h5>
                </div>

                <div class="form-group">
                    <label for="full_name">
                        <i class="bi bi-person-badge"></i> Nama Lengkap
                    </label>
                    <input 
                        type="text" 
                        class="form-control-modern" 
                        id="full_name" 
                        name="full_name" 
                        value="<?php echo htmlspecialchars($user['full_name']); ?>" 
                        required
                        autocomplete="name"
                    >
                    <div class="invalid-feedback-modern" id="fullNameError">
                        <i class="bi bi-exclamation-circle"></i> Nama lengkap tidak boleh kosong
                    </div>
                </div>

                <div class="form-group">
                    <label for="username">
                        <i class="bi bi-at"></i> Username
                    </label>
                    <input 
                        type="text" 
                        class="form-control-modern" 
                        id="username" 
                        name="username" 
                        value="<?php echo htmlspecialchars($user['username']); ?>" 
                        required
                        autocomplete="username"
                    >
                    <div class="invalid-feedback-modern" id="usernameError">
                        <i class="bi bi-exclamation-circle"></i> Username tidak boleh kosong
                    </div>
                </div>

                <div class="form-group">
                    <label for="role">
                        <i class="bi bi-shield-lock"></i> Role
                    </label>
                    <input 
                        type="text" 
                        class="form-control-modern" 
                        id="role" 
                        name="role" 
                        value="<?php echo htmlspecialchars(ucfirst($user['role'])); ?>" 
                        disabled
                    >
                    <small class="form-text-modern">
                        <i class="bi bi-info-circle"></i> Role ditetapkan oleh Superadmin dan tidak dapat diubah
                    </small>
                </div>

                <!-- Ganti Password Section -->
                <div class="section-header" style="margin-top: 2.5rem;">
                    <i class="bi bi-key-fill"></i>
                    <h5>Ganti Password</h5>
                </div>

                <div class="info-box">
                    <i class="bi bi-lightbulb-fill"></i>
                    <div class="info-box-content">
                        <h6>Informasi Penting</h6>
                        <p>Pastikan Anda mengingat <strong>Username</strong> dan <strong>Password</strong> baru Anda untuk login selanjutnya. Kosongkan bagian password jika tidak ingin menggantinya.</p>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password_lama">
                        <i class="bi bi-lock"></i> Password Lama
                    </label>
                    <div class="password-wrapper">
                        <input 
                            type="password" 
                            class="form-control-modern" 
                            id="password_lama" 
                            name="password_lama" 
                            placeholder="Masukkan password Anda saat ini"
                            autocomplete="current-password"
                        >
                        <button type="button" class="password-toggle" data-target="password_lama">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password_baru">
                        <i class="bi bi-key"></i> Password Baru
                    </label>
                    <div class="password-wrapper">
                        <input 
                            type="password" 
                            class="form-control-modern" 
                            id="password_baru" 
                            name="password_baru" 
                            placeholder="Masukkan password baru (minimal 6 karakter)"
                            autocomplete="new-password"
                        >
                        <button type="button" class="password-toggle" data-target="password_baru">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                    <div class="invalid-feedback-modern" id="passwordBaruError">
                        <i class="bi bi-exclamation-circle"></i> Password minimal 6 karakter
                    </div>
                    <div class="valid-feedback-modern" id="passwordBaruValid">
                        <i class="bi bi-check-circle"></i> Password valid
                    </div>
                </div>

                <div class="form-group">
                    <label for="konfirmasi_password">
                        <i class="bi bi-shield-check"></i> Konfirmasi Password Baru
                    </label>
                    <div class="password-wrapper">
                        <input 
                            type="password" 
                            class="form-control-modern" 
                            id="konfirmasi_password" 
                            name="konfirmasi_password" 
                            placeholder="Ketik ulang password baru Anda"
                            autocomplete="new-password"
                        >
                        <button type="button" class="password-toggle" data-target="konfirmasi_password">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                    <div class="invalid-feedback-modern" id="konfirmasiError">
                        <i class="bi bi-exclamation-circle"></i> Password tidak cocok
                    </div>
                    <div class="valid-feedback-modern" id="konfirmasiValid">
                        <i class="bi bi-check-circle"></i> Password cocok
                    </div>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="btn-submit-modern" id="submitBtn">
                    <i class="bi bi-save"></i>
                    <span>Simpan Perubahan</span>
                </button>

            </form>
        </div>

        <!-- RIGHT COLUMN: Info Card (40%) -->
        <div class="info-card">
            <div class="section-header">
                <i class="bi bi-diagram-3-fill"></i>
                <h5>Akses Alur Mesin</h5>
            </div>

            <p style="color: var(--text-secondary); font-size: 0.9rem; margin-bottom: 1rem;">
                Berikut adalah daftar alur mesin (tahapan) yang dapat Anda kelola:
            </p>

            <?php if (!empty($alurs)): ?>
                <ul class="alur-list">
                    <?php foreach ($alurs as $alur): ?>
                        <li class="alur-item">
                            <i class="bi bi-check-circle-fill"></i>
                            <span><?php echo htmlspecialchars($alur['nama_alur']); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <small class="form-text-modern" style="margin-top: 1rem; display: block;">
                    <i class="bi bi-info-circle"></i> Total <strong><?php echo count($alurs); ?> alur mesin</strong> yang dapat Anda akses
                </small>
            <?php else: ?>
                <div class="warning-box">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <div>
                        <strong>Belum Ada Akses</strong>
                        <p style="margin: 0.25rem 0 0 0; font-size: 0.85rem;">
                            Anda belum memiliki akses ke alur mesin manapun. Harap hubungi Superadmin.
                        </p>
                    </div>
                </div>
            <?php endif; ?>

            <small class="form-text-modern" style="margin-top: 1.5rem; display: block; text-align: center;">
                <i class="bi bi-shield-lock"></i> Akses ini diatur oleh Superadmin
            </small>
        </div>

    </div>
</div>

<!-- Back to Top Button -->
<button class="back-to-top" id="backToTop" aria-label="Kembali ke atas">
    <i class="bi bi-arrow-up"></i>
</button>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('settingsForm');
    const submitBtn = document.getElementById('submitBtn');
    const submitIcon = submitBtn.querySelector('i');
    const submitText = submitBtn.querySelector('span');
    const backToTopBtn = document.getElementById('backToTop');
    
    // Form inputs
    const fullName = document.getElementById('full_name');
    const username = document.getElementById('username');
    const passwordLama = document.getElementById('password_lama');
    const passwordBaru = document.getElementById('password_baru');
    const konfirmasiPassword = document.getElementById('konfirmasi_password');

    // ========================================
    // PASSWORD TOGGLE VISIBILITY
    // ========================================
    const passwordToggles = document.querySelectorAll('.password-toggle');
    passwordToggles.forEach(toggle => {
        toggle.addEventListener('click', function() {
            const targetId = this.getAttribute('data-target');
            const input = document.getElementById(targetId);
            const icon = this.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        });
    });

    // ========================================
    // REAL-TIME VALIDATION - PASSWORD BARU
    // ========================================
    passwordBaru.addEventListener('input', function() {
        const value = this.value;
        const errorDiv = document.getElementById('passwordBaruError');
        const validDiv = document.getElementById('passwordBaruValid');
        
        if (value.length > 0 && value.length < 6) {
            this.classList.add('is-invalid');
            this.classList.remove('is-valid');
            errorDiv.classList.add('show');
            validDiv.classList.remove('show');
        } else if (value.length >= 6) {
            this.classList.remove('is-invalid');
            this.classList.add('is-valid');
            errorDiv.classList.remove('show');
            validDiv.classList.add('show');
            
            if (konfirmasiPassword.value) {
                validateKonfirmasi();
            }
        } else {
            this.classList.remove('is-invalid', 'is-valid');
            errorDiv.classList.remove('show');
            validDiv.classList.remove('show');
        }
    });

    // ========================================
    // REAL-TIME VALIDATION - KONFIRMASI PASSWORD
    // ========================================
    konfirmasiPassword.addEventListener('input', validateKonfirmasi);

    function validateKonfirmasi() {
        const value = konfirmasiPassword.value;
        const passwordBaruValue = passwordBaru.value;
        const errorDiv = document.getElementById('konfirmasiError');
        const validDiv = document.getElementById('konfirmasiValid');
        
        if (value.length > 0) {
            if (value !== passwordBaruValue) {
                konfirmasiPassword.classList.add('is-invalid');
                konfirmasiPassword.classList.remove('is-valid');
                errorDiv.classList.add('show');
                validDiv.classList.remove('show');
            } else {
                konfirmasiPassword.classList.remove('is-invalid');
                konfirmasiPassword.classList.add('is-valid');
                errorDiv.classList.remove('show');
                validDiv.classList.add('show');
            }
        } else {
            konfirmasiPassword.classList.remove('is-invalid', 'is-valid');
            errorDiv.classList.remove('show');
            validDiv.classList.remove('show');
        }
    }

    // ========================================
    // FORM SUBMISSION WITH SWEETALERT2 CONFIRMATION
    // ========================================
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        let isValid = true;
        
        // Validate Full Name
        if (!fullName.value.trim()) {
            fullName.classList.add('is-invalid');
            document.getElementById('fullNameError').classList.add('show');
            isValid = false;
        } else {
            fullName.classList.remove('is-invalid');
            document.getElementById('fullNameError').classList.remove('show');
        }
        
        // Validate Username
        if (!username.value.trim()) {
            username.classList.add('is-invalid');
            document.getElementById('usernameError').classList.add('show');
            isValid = false;
        } else {
            username.classList.remove('is-invalid');
            document.getElementById('usernameError').classList.remove('show');
        }
        
        // Validate password if attempting change
        if (passwordBaru.value || konfirmasiPassword.value || passwordLama.value) {
            if (passwordBaru.value && passwordBaru.value.length < 6) {
                isValid = false;
                Swal.fire({
                    title: 'Password Terlalu Pendek',
                    text: 'Password baru minimal harus 6 karakter.',
                    icon: 'warning',
                    confirmButtonColor: '#667eea',
                    background: 'rgba(30, 60, 114, 0.95)',
                    color: '#fff',
                    backdrop: 'rgba(0,0,0,0.4)'
                });
                return;
            }
            
            if (passwordBaru.value !== konfirmasiPassword.value) {
                isValid = false;
                Swal.fire({
                    title: 'Password Tidak Cocok',
                    text: 'Konfirmasi password tidak sesuai dengan password baru.',
                    icon: 'error',
                    confirmButtonColor: '#667eea',
                    background: 'rgba(30, 60, 114, 0.95)',
                    color: '#fff',
                    backdrop: 'rgba(0,0,0,0.4)'
                });
                return;
            }
        }
        
        if (!isValid) {
            return;
        }
        
        const isMobile = window.innerWidth <= 768;
        
        // Show confirmation dialog
        Swal.fire({
            title: 'Konfirmasi Perubahan',
            html: '<p style="margin-bottom: 0;">Apakah Anda yakin ingin menyimpan perubahan data akun Anda?</p>',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#667eea',
            cancelButtonColor: '#d33',
            confirmButtonText: '<i class="bi bi-check-circle"></i> Ya, Simpan!',
            cancelButtonText: '<i class="bi bi-x-circle"></i> Batal',
            background: 'rgba(30, 60, 114, 0.95)',
            color: '#fff',
            width: isMobile ? '90%' : '500px',
            backdrop: 'rgba(0,0,0,0.4)',
            customClass: {
                popup: 'swal-modern-popup',
                confirmButton: 'swal-confirm-btn-modern',
                cancelButton: 'swal-cancel-btn-modern'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                submitBtn.classList.add('loading');
                submitIcon.classList.remove('bi-save');
                submitIcon.classList.add('bi-arrow-repeat');
                submitText.textContent = 'Menyimpan...';
                
                Swal.fire({
                    title: 'Memproses...',
                    html: '<div style="margin-top: 20px;"><i class="bi bi-arrow-clockwise" style="font-size: 3rem; animation: spin 1s linear infinite;"></i></div>',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showConfirmButton: false,
                    background: 'rgba(30, 60, 114, 0.95)',
                    color: '#fff',
                    width: isMobile ? '90%' : '400px',
                    backdrop: 'rgba(0,0,0,0.6)'
                });
                
                setTimeout(() => {
                    form.submit();
                }, 800);
            }
        });
    });

    // ========================================
    // BACK TO TOP BUTTON
    // ========================================
    window.addEventListener('scroll', function() {
        if (window.scrollY > 300) {
            backToTopBtn.classList.add('show');
        } else {
            backToTopBtn.classList.remove('show');
        }
    });

    backToTopBtn.addEventListener('click', function() {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    });

    // ========================================
    // AUTO-DISMISS ALERTS AFTER 5 SECONDS
    // ========================================
    const alerts = document.querySelectorAll('.alert-modern');
    alerts.forEach(alert => {
        setTimeout(() => {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 5000);
    });

    // ========================================
    // SMOOTH SCROLL TO TOP ON PAGE LOAD
    // ========================================
    window.scrollTo({ top: 0, behavior: 'smooth' });
});
</script>

<!-- Additional Custom Styles for SweetAlert -->
<style>
    .swal-modern-popup {
        border: 1px solid rgba(255, 255, 255, 0.18) !important;
        border-radius: 20px !important;
        box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.5) !important;
    }
    
    .swal-confirm-btn-modern {
        border-radius: 10px !important;
        padding: 12px 28px !important;
        font-weight: 600 !important;
        transition: all 0.3s ease !important;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
        border: none !important;
    }
    
    .swal-confirm-btn-modern:hover {
        transform: translateY(-2px) !important;
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4) !important;
    }
    
    .swal-cancel-btn-modern {
        border-radius: 10px !important;
        padding: 12px 28px !important;
        font-weight: 600 !important;
        transition: all 0.3s ease !important;
    }
    
    .swal-cancel-btn-modern:hover {
        transform: translateY(-2px) !important;
    }
</style>

<?php
include_once __DIR__ . '/../../../templates/footer.php';
?>