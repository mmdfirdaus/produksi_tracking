<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lupa Kata Sandi - Sistem Tracking Produksi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            /* Modern Color Scheme - Match dengan login.php */
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
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 2rem 1rem;
            position: relative;
            overflow-x: hidden;
        }

        /* Animated Background Pattern */
        body::before {
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
            pointer-events: none;
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
            pointer-events: none;
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

        @keyframes float {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            33% { transform: translateY(-30px) rotate(120deg); }
            66% { transform: translateY(30px) rotate(240deg); }
        }

        /* Main Container */
        .forgot-password-container {
            width: 100%;
            max-width: 480px;
            position: relative;
            z-index: 1;
            animation: fadeInUp 0.6s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Card */
        .forgot-password-card {
            background: white;
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 3rem 2.5rem;
            position: relative;
            overflow: hidden;
        }

        .forgot-password-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: linear-gradient(90deg, var(--primary-color) 0%, var(--secondary-color) 100%);
        }

        /* Icon Container */
        .icon-container {
            width: 120px;
            height: 120px;
            margin: 0 auto 2rem;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 30px rgba(79, 70, 229, 0.3);
            animation: pulse 2s ease-in-out infinite;
            position: relative;
        }

        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
                box-shadow: 0 10px 30px rgba(79, 70, 229, 0.3);
            }
            50% {
                transform: scale(1.05);
                box-shadow: 0 15px 40px rgba(79, 70, 229, 0.4);
            }
        }

        .icon-container::before {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            opacity: 0.3;
            animation: ripple 2s ease-out infinite;
        }

        @keyframes ripple {
            0% {
                transform: scale(1);
                opacity: 0.3;
            }
            100% {
                transform: scale(1.5);
                opacity: 0;
            }
        }

        .icon-container i {
            font-size: 3.5rem;
            color: white;
            position: relative;
            z-index: 1;
        }

        /* Header */
        .forgot-password-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .forgot-password-header h1 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }

        .forgot-password-header p {
            color: var(--gray);
            font-size: 1rem;
            line-height: 1.6;
        }

        /* Progress Indicator */
        .progress-indicator {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
            margin-bottom: 2rem;
            padding: 1rem;
            background: var(--bg-light);
            border-radius: 12px;
        }

        .progress-step {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .step-circle {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: white;
            border: 2px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.875rem;
            color: var(--gray);
            transition: all var(--transition-base);
        }

        .step-circle.active {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            border-color: var(--primary-color);
            color: white;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
        }

        .step-circle.completed {
            background: var(--success);
            border-color: var(--success);
            color: white;
        }

        .step-label {
            font-size: 0.85rem;
            color: var(--gray);
            font-weight: 500;
        }

        .step-label.active {
            color: var(--primary-color);
            font-weight: 600;
        }

        .step-divider {
            width: 40px;
            height: 2px;
            background: var(--border-color);
        }

        /* Form Styling */
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
            border-radius: 12px;
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

        /* Submit Button */
        .btn-submit {
            width: 100%;
            height: 3.25rem;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            border: none;
            border-radius: 12px;
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

        .btn-submit:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(79, 70, 229, 0.4);
        }

        .btn-submit:active:not(:disabled) {
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

        /* Back Link */
        .back-link-container {
            text-align: center;
            margin-top: 2rem;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.95rem;
            transition: all var(--transition-fast);
            padding: 0.5rem 1rem;
            border-radius: 8px;
        }

        .back-link:hover {
            background: var(--bg-light);
            color: var(--primary-dark);
            transform: translateX(-4px);
        }

        .back-link i {
            transition: transform var(--transition-fast);
        }

        .back-link:hover i {
            transform: translateX(-4px);
        }

        /* Custom SweetAlert2 Styling */
        .swal2-popup {
            border-radius: 20px;
            padding: 2rem;
            font-family: 'Inter', sans-serif;
        }

        .swal2-title {
            color: var(--dark);
            font-weight: 700;
            font-size: 1.75rem;
        }

        .swal2-html-container {
            color: var(--gray);
            font-size: 1rem;
        }

        .swal2-icon {
            border-width: 3px;
        }

        .swal2-icon.swal2-success {
            border-color: var(--success);
        }

        .swal2-icon.swal2-success [class^='swal2-success-line'] {
            background-color: var(--success);
        }

        .swal2-icon.swal2-success .swal2-success-ring {
            border-color: rgba(16, 185, 129, 0.3);
        }

        .swal2-icon.swal2-error {
            border-color: var(--error);
        }

        .swal2-icon.swal2-error [class^='swal2-x-mark-line'] {
            background-color: var(--error);
        }

        .swal2-icon.swal2-info {
            border-color: var(--info);
            color: var(--info);
        }

        .swal2-icon.swal2-warning {
            border-color: var(--warning);
            color: var(--warning);
        }

        .swal2-confirm {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%) !important;
            border: none !important;
            border-radius: 10px !important;
            padding: 0.75rem 2rem !important;
            font-weight: 600 !important;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3) !important;
            transition: all var(--transition-base) !important;
        }

        .swal2-confirm:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 8px 20px rgba(79, 70, 229, 0.4) !important;
        }

        .swal2-cancel {
            background: var(--gray-light) !important;
            border: none !important;
            border-radius: 10px !important;
            padding: 0.75rem 2rem !important;
            font-weight: 600 !important;
            transition: all var(--transition-base) !important;
        }

        .swal2-cancel:hover {
            background: var(--gray) !important;
        }

        .swal2-input {
            border: 2px solid var(--border-color) !important;
            border-radius: 10px !important;
            padding: 0.875rem 1rem !important;
            font-size: 1rem !important;
            transition: all var(--transition-base) !important;
        }

        .swal2-input:focus {
            border-color: var(--primary-color) !important;
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1) !important;
        }

        .swal2-validation-message {
            background: rgba(239, 68, 68, 0.1) !important;
            color: var(--error) !important;
            border: none !important;
            border-radius: 8px !important;
            font-weight: 500 !important;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            body {
                padding: 1rem;
            }

            .forgot-password-card {
                padding: 2rem 1.5rem;
                border-radius: 20px;
            }

            .icon-container {
                width: 100px;
                height: 100px;
                margin-bottom: 1.5rem;
            }

            .icon-container i {
                font-size: 3rem;
            }

            .forgot-password-header h1 {
                font-size: 1.75rem;
            }

            .forgot-password-header p {
                font-size: 0.9rem;
            }

            .progress-indicator {
                padding: 0.75rem;
                gap: 0.5rem;
            }

            .step-circle {
                width: 28px;
                height: 28px;
                font-size: 0.8rem;
            }

            .step-label {
                display: none; /* Hide labels on mobile for cleaner look */
            }

            .step-divider {
                width: 30px;
            }

            .form-input,
            .btn-submit {
                height: 3rem;
                font-size: 0.95rem;
            }

            .btn-submit {
                font-size: 1rem;
            }

            /* SweetAlert2 Mobile */
            .swal2-popup {
                width: calc(100% - 2rem) !important;
                padding: 1.5rem !important;
            }

            .swal2-title {
                font-size: 1.5rem !important;
            }
        }

        @media (max-width: 480px) {
            .forgot-password-card {
                padding: 1.5rem 1.25rem;
            }

            .icon-container {
                width: 90px;
                height: 90px;
            }

            .icon-container i {
                font-size: 2.5rem;
            }

            .forgot-password-header h1 {
                font-size: 1.5rem;
            }

            .progress-indicator {
                gap: 0.25rem;
            }

            .step-divider {
                width: 20px;
            }
        }

        /* Accessibility */
        .form-input:focus-visible,
        .btn-submit:focus-visible,
        .back-link:focus-visible {
            outline: 2px solid var(--primary-color);
            outline-offset: 2px;
        }

        /* Loading State Enhancement */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .form-feedback {
            margin-top: 0.5rem;
            font-size: 0.875rem;
            animation: fadeIn 0.3s ease-out;
        }

        .form-feedback.success {
            color: var(--success);
        }

        .form-feedback.error {
            color: var(--error);
        }
    </style>
</head>
<body>
    <!-- Floating Shapes -->
    <div class="shape shape-1"></div>
    <div class="shape shape-2"></div>

    <!-- Main Container -->
    <div class="forgot-password-container">
        <div class="forgot-password-card">
            
            <!-- Icon -->
            <div class="icon-container">
                <i class="fas fa-shield-alt"></i>
            </div>

            <!-- Header -->
            <div class="forgot-password-header">
                <h1>Lupa Kata Sandi?</h1>
                <p>Jangan khawatir! Masukkan username Superadmin Anda dan kami akan membantu Anda mengatur ulang kata sandi.</p>
            </div>

            <!-- Progress Indicator -->
            <div class="progress-indicator">
                <div class="progress-step">
                    <div class="step-circle active">1</div>
                    <span class="step-label active">Username</span>
                </div>
                <div class="step-divider"></div>
                <div class="progress-step">
                    <div class="step-circle">2</div>
                    <span class="step-label">Verifikasi</span>
                </div>
                <div class="step-divider"></div>
                <div class="progress-step">
                    <div class="step-circle">3</div>
                    <span class="step-label">Password Baru</span>
                </div>
            </div>

            <!-- Form -->
            <form id="check-username-form">
                <div class="form-group">
                    <label for="username" class="form-label">Username Superadmin</label>
                    <div class="input-wrapper">
                        <input 
                            type="text" 
                            class="form-input" 
                            id="username" 
                            name="username" 
                            placeholder="Masukkan username superadmin Anda"
                            required 
                            autocomplete="username"
                        >
                        <i class="fas fa-user input-icon"></i>
                    </div>
                </div>

                <button type="submit" class="btn-submit" id="submitBtn">
                    <span class="spinner"></span>
                    <span class="btn-text">
                        <i class="fas fa-arrow-right"></i>
                        Lanjutkan
                    </span>
                </button>
            </form>

            <!-- Back Link -->
            <div class="back-link-container">
                <a href="login.php" class="back-link">
                    <i class="fas fa-arrow-left"></i>
                    Kembali ke Login
                </a>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        $(document).ready(function() {
            // Progress indicator helper
            function updateProgress(step) {
                $('.step-circle').removeClass('active completed');
                $('.step-label').removeClass('active');
                
                for (let i = 1; i <= 3; i++) {
                    if (i < step) {
                        $(`.progress-step:nth-child(${i * 2 - 1}) .step-circle`).addClass('completed');
                    } else if (i === step) {
                        $(`.progress-step:nth-child(${i * 2 - 1}) .step-circle`).addClass('active');
                        $(`.progress-step:nth-child(${i * 2 - 1}) .step-label`).addClass('active');
                    }
                }
            }

            // Form submission
            $('#check-username-form').on('submit', function(e) {
                e.preventDefault();
                
                const username = $('#username').val().trim();
                const submitBtn = $('#submitBtn');
                
                if (!username) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Oops...',
                        text: 'Mohon masukkan username Anda',
                        confirmButtonText: 'OK'
                    });
                    return;
                }

                // Add loading state
                submitBtn.addClass('loading').prop('disabled', true);

                $.ajax({
                    url: 'api_lupa_password.php',
                    method: 'POST',
                    data: {
                        action: 'check_username',
                        username: username
                    },
                    dataType: 'json',
                    success: function(response) {
                        submitBtn.removeClass('loading').prop('disabled', false);
                        
                        if (response.status === 'success') {
                            updateProgress(2);
                            askForSpecialKey(username);
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Gagal',
                                text: response.message,
                                confirmButtonText: 'Coba Lagi'
                            });
                        }
                    },
                    error: function(xhr, status, error) {
                        submitBtn.removeClass('loading').prop('disabled', false);
                        
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Terjadi kesalahan saat menghubungi server. Silakan coba lagi.',
                            confirmButtonText: 'OK'
                        });
                        console.error('AJAX Error:', error);
                    }
                });
            });

            // Step 2: Ask for Special Key
            function askForSpecialKey(username) {
                Swal.fire({
                    title: 'Verifikasi Superadmin',
                    text: 'Masukkan Special Key Anda untuk melanjutkan proses reset password.',
                    input: 'password',
                    inputPlaceholder: 'Masukkan Special Key',
                    inputAttributes: {
                        autocomplete: 'off',
                        autocapitalize: 'off'
                    },
                    icon: 'info',
                    showCancelButton: true,
                    confirmButtonText: '<i class="fas fa-check me-2"></i>Verifikasi',
                    cancelButtonText: '<i class="fas fa-times me-2"></i>Batal',
                    showLoaderOnConfirm: true,
                    allowOutsideClick: false,
                    backdrop: true,
                    preConfirm: (specialKey) => {
                        if (!specialKey) {
                            Swal.showValidationMessage('Special Key tidak boleh kosong');
                            return false;
                        }

                        return $.ajax({
                            url: 'api_lupa_password.php',
                            method: 'POST',
                            data: {
                                action: 'verify_key',
                                username: username,
                                special_key: specialKey
                            },
                            dataType: 'json'
                        }).fail(function(xhr, status, error) {
                            Swal.showValidationMessage('Request gagal: ' + error);
                        });
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        if (result.value && result.value.status === 'success') {
                            updateProgress(3);
                            askForNewPassword(username, result.value.token);
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Verifikasi Gagal',
                                text: result.value ? result.value.message : 'Special Key tidak valid',
                                confirmButtonText: 'Coba Lagi'
                            }).then(() => {
                                askForSpecialKey(username);
                            });
                        }
                    } else {
                        // User cancelled, reset progress
                        updateProgress(1);
                    }
                });
            }

            // Step 3: Ask for New Password
            function askForNewPassword(username, token) {
                Swal.fire({
                    title: 'Atur Ulang Kata Sandi',
                    html: `
                        <div style="text-align: left; margin-bottom: 1rem;">
                            <label style="display: block; font-weight: 500; color: #334155; margin-bottom: 0.5rem; font-size: 0.9rem;">
                                <i class="fas fa-lock me-2"></i>Kata Sandi Baru
                            </label>
                            <input type="password" id="new_password" class="swal2-input" placeholder="Minimal 6 karakter" style="margin: 0; width: 100%;">
                        </div>
                        <div style="text-align: left;">
                            <label style="display: block; font-weight: 500; color: #334155; margin-bottom: 0.5rem; font-size: 0.9rem;">
                                <i class="fas fa-lock me-2"></i>Konfirmasi Kata Sandi
                            </label>
                            <input type="password" id="confirm_password" class="swal2-input" placeholder="Masukkan kembali kata sandi" style="margin: 0; width: 100%;">
                        </div>
                    `,
                    icon: 'success',
                    showCancelButton: true,
                    confirmButtonText: '<i class="fas fa-check me-2"></i>Simpan Kata Sandi',
                    cancelButtonText: '<i class="fas fa-times me-2"></i>Batal',
                    showLoaderOnConfirm: true,
                    allowOutsideClick: false,
                    backdrop: true,
                    didOpen: () => {
                        // Focus on first input
                        $('#new_password').focus();
                    },
                    preConfirm: () => {
                        const newPassword = $('#new_password').val();
                        const confirmPassword = $('#confirm_password').val();

                        // Validation
                        if (!newPassword || !confirmPassword) {
                            Swal.showValidationMessage('Semua field harus diisi');
                            return false;
                        }

                        if (newPassword.length < 6) {
                            Swal.showValidationMessage('Kata sandi minimal 6 karakter');
                            return false;
                        }

                        if (newPassword !== confirmPassword) {
                            Swal.showValidationMessage('Konfirmasi kata sandi tidak cocok');
                            return false;
                        }

                        return $.ajax({
                            url: 'api_lupa_password.php',
                            method: 'POST',
                            data: {
                                action: 'reset_password',
                                username: username,
                                token: token,
                                new_password: newPassword
                            },
                            dataType: 'json'
                        }).fail(function(xhr, status, error) {
                            Swal.showValidationMessage('Request gagal: ' + error);
                        });
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        if (result.value && result.value.status === 'success') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Berhasil!',
                                html: `
                                    <p style="color: #64748B; margin-bottom: 1rem;">Kata sandi Anda telah berhasil diubah.</p>
                                    <p style="color: #64748B;">Anda akan diarahkan ke halaman login...</p>
                                `,
                                confirmButtonText: 'Login Sekarang',
                                allowOutsideClick: false,
                                timer: 3000,
                                timerProgressBar: true
                            }).then(() => {
                                window.location.href = 'login.php';
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Gagal',
                                text: result.value ? result.value.message : 'Terjadi kesalahan saat mengubah kata sandi',
                                confirmButtonText: 'Coba Lagi'
                            }).then(() => {
                                askForNewPassword(username, token);
                            });
                        }
                    } else {
                        // User cancelled, go back to special key
                        updateProgress(2);
                        askForSpecialKey(username);
                    }
                });
            }

            // Input validation feedback
            $('#username').on('blur', function() {
                const value = $(this).val().trim();
                if (value && value.length >= 3) {
                    $(this).css('border-color', 'var(--success)');
                } else if (value) {
                    $(this).css('border-color', 'var(--error)');
                } else {
                    $(this).css('border-color', 'var(--border-color)');
                }
            });

            $('#username').on('input', function() {
                $(this).css('border-color', 'var(--border-color)');
            });

            // Auto-focus on username input
            $('#username').focus();

            // Prevent form resubmission on page reload
            if (window.history.replaceState) {
                window.history.replaceState(null, null, window.location.href);
            }
        });
    </script>
</body>
</html>