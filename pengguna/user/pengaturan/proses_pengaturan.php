<?php
session_start();
include_once __DIR__ . '/../../../system/database_connection.php';

// ========================================
// SECURITY CHECK - Authentication
// ========================================
if (!isset($_SESSION['user_id']) || $_SERVER["REQUEST_METHOD"] !== "POST") {
    header('Location: ../../auth/login.php');
    exit;
}

// ========================================
// GET DATA FROM SESSION AND POST
// ========================================
$id_user = $_SESSION['user_id'];
$full_name = trim($_POST['full_name']);
$username = trim($_POST['username']);
$password_lama = $_POST['password_lama'] ?? '';
$password_baru = $_POST['password_baru'] ?? '';
$konfirmasi_password = $_POST['konfirmasi_password'] ?? '';

// ========================================
// VALIDATION 1: Basic Input Validation
// ========================================
if (empty($full_name)) {
    $_SESSION['error_message'] = "Nama lengkap tidak boleh kosong.";
    header('Location: pengaturan.php');
    exit;
}

if (empty($username)) {
    $_SESSION['error_message'] = "Username tidak boleh kosong.";
    header('Location: pengaturan.php');
    exit;
}

// Validate username format (alphanumeric, underscore, dash only)
if (!preg_match('/^[a-zA-Z0-9_-]+$/', $username)) {
    $_SESSION['error_message'] = "Username hanya boleh mengandung huruf, angka, underscore (_), dan dash (-).";
    header('Location: pengaturan.php');
    exit;
}

// Validate username length (min 3, max 50)
if (strlen($username) < 3 || strlen($username) > 50) {
    $_SESSION['error_message'] = "Username harus antara 3-50 karakter.";
    header('Location: pengaturan.php');
    exit;
}

// Validate full name length (min 3, max 100)
if (strlen($full_name) < 3 || strlen($full_name) > 100) {
    $_SESSION['error_message'] = "Nama lengkap harus antara 3-100 karakter.";
    header('Location: pengaturan.php');
    exit;
}

try {
    // ========================================
    // VALIDATION 2: Check Username Uniqueness
    // ========================================
    $stmt_check_user = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
    $stmt_check_user->execute([$username, $id_user]);
    
    if ($stmt_check_user->fetch()) {
        $_SESSION['error_message'] = "Username '<strong>$username</strong>' sudah digunakan oleh pengguna lain. Silakan pilih username yang berbeda.";
        header('Location: pengaturan.php');
        exit;
    }

    // ========================================
    // PREPARE SQL QUERY - Basic Update
    // ========================================
    $sql = "UPDATE users SET full_name = ?, username = ?";
    $params = [$full_name, $username];
    
    $password_changed = false;

    // ========================================
    // PASSWORD CHANGE LOGIC
    // ========================================
    // Check if user intends to change password
    if (!empty($password_baru) || !empty($konfirmasi_password) || !empty($password_lama)) {
        
        // VALIDATION 3a: All password fields must be filled if changing password
        if (empty($password_lama)) {
            $_SESSION['error_message'] = "Untuk mengganti password, Anda harus memasukkan <strong>Password Lama</strong>.";
            header('Location: pengaturan.php');
            exit;
        }

        if (empty($password_baru)) {
            $_SESSION['error_message'] = "Untuk mengganti password, Anda harus memasukkan <strong>Password Baru</strong>.";
            header('Location: pengaturan.php');
            exit;
        }

        if (empty($konfirmasi_password)) {
            $_SESSION['error_message'] = "Untuk mengganti password, Anda harus memasukkan <strong>Konfirmasi Password Baru</strong>.";
            header('Location: pengaturan.php');
            exit;
        }

        // VALIDATION 3b: New password minimum length (6 characters)
        if (strlen($password_baru) < 6) {
            $_SESSION['error_message'] = "Password baru minimal harus <strong>6 karakter</strong>. Anda memasukkan " . strlen($password_baru) . " karakter.";
            header('Location: pengaturan.php');
            exit;
        }

        // VALIDATION 3c: New password maximum length (255 characters for security)
        if (strlen($password_baru) > 255) {
            $_SESSION['error_message'] = "Password baru maksimal <strong>255 karakter</strong>.";
            header('Location: pengaturan.php');
            exit;
        }

        // VALIDATION 3d: Confirm password must match new password
        if ($password_baru !== $konfirmasi_password) {
            $_SESSION['error_message'] = "Konfirmasi password tidak cocok dengan password baru. Pastikan kedua password sama persis.";
            header('Location: pengaturan.php');
            exit;
        }

        // VALIDATION 3e: New password should not be the same as old password (optional but good practice)
        if ($password_lama === $password_baru) {
            $_SESSION['error_message'] = "Password baru tidak boleh sama dengan password lama. Silakan gunakan password yang berbeda.";
            header('Location: pengaturan.php');
            exit;
        }

        // VALIDATION 3f: Verify old password from database
        $stmt_check_pass = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt_check_pass->execute([$id_user]);
        $user = $stmt_check_pass->fetch();

        if ($user && password_verify($password_lama, $user['password'])) {
            // Old password is correct, hash new password
            $hashed_password_baru = password_hash($password_baru, PASSWORD_DEFAULT);
            
            // Add to SQL query
            $sql .= ", password = ?";
            $params[] = $hashed_password_baru;
            $password_changed = true;
        } else {
            // Old password is incorrect
            $_SESSION['error_message'] = "Password lama yang Anda masukkan <strong>salah</strong>. Silakan coba lagi.";
            header('Location: pengaturan.php');
            exit;
        }
    }

    // ========================================
    // EXECUTE UPDATE QUERY
    // ========================================
    $sql .= " WHERE id = ?";
    $params[] = $id_user;

    $stmt_update = $pdo->prepare($sql);
    $stmt_update->execute($params);

    // ========================================
    // UPDATE SESSION VARIABLES
    // ========================================
    // Update username in session if changed
    $_SESSION['username'] = $username;
    
    // Update full_name in session if changed
    $_SESSION['full_name'] = $full_name;

    // ========================================
    // SET SUCCESS MESSAGE
    // ========================================
    if ($password_changed) {
        $_SESSION['success_message'] = "Pengaturan akun berhasil diperbarui! Username, nama lengkap, dan password Anda telah diubah.";
    } else {
        $_SESSION['success_message'] = "Pengaturan akun berhasil diperbarui! Username dan nama lengkap Anda telah diubah.";
    }

} catch (PDOException $e) {
    // ========================================
    // DATABASE ERROR HANDLING
    // ========================================
    // Log error for debugging (in production, use proper logging)
    error_log("Database Error in proses_pengaturan.php: " . $e->getMessage());
    
    // User-friendly error message
    $_SESSION['error_message'] = "Terjadi kesalahan sistem. Silakan coba lagi atau hubungi administrator jika masalah berlanjut.";
    
    // For development only - show actual error (remove in production)
    // $_SESSION['error_message'] = "Error Database: " . $e->getMessage();
}

// ========================================
// REDIRECT BACK TO SETTINGS PAGE
// ========================================
header('Location: pengaturan.php');
exit;
?>