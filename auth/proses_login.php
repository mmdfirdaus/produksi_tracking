<?php
session_start();
require_once '../system/database_connection.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // Validasi input tidak kosong
    if (empty($username) || empty($password)) {
        $_SESSION['error_message'] = 'Username dan Password harus diisi';
        header("location: login.php");
        exit();
    }

    try {
        $sql = "SELECT id, username, password, role, full_name FROM users WHERE username = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$username]);

        if ($stmt->rowCount() == 1) {
            $user = $stmt->fetch();
            
            // Verifikasi password
            if (password_verify($password, $user['password'])) {
                
                // ==========================================
                // LOGIN BERHASIL - SET SESSION
                // ==========================================
                session_regenerate_id();
                $_SESSION["loggedin"] = true;
                $_SESSION["user_id"] = $user['id'];
                $_SESSION["username"] = $user['username'];
                $_SESSION["role"] = $user['role'];
                $_SESSION["full_name"] = $user['full_name'];
                
                // ==========================================
                // HANDLE REMEMBER ME COOKIE
                // ==========================================
                if (isset($_POST['remember_me']) && $_POST['remember_me'] === 'on') {
                    // Set cookie untuk 30 hari
                    $cookie_time = time() + (30 * 24 * 60 * 60); // 30 hari dalam detik
                    
                    // Opsi cookie yang secure
                    $cookie_options = [
                        'expires' => $cookie_time,
                        'path' => '/',
                        'domain' => '', // Kosongkan atau isi dengan domain Anda
                        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on', // true jika HTTPS
                        'httponly' => true, // Tidak bisa diakses via JavaScript (security)
                        'samesite' => 'Strict' // CSRF protection
                    ];
                    
                    // Set cookie dengan username
                    setcookie('remembered_username', $username, $cookie_options);
                    
                } else {
                    // Hapus cookie jika checkbox tidak dicentang
                    if (isset($_COOKIE['remembered_username'])) {
                        setcookie('remembered_username', '', time() - 3600, '/');
                        unset($_COOKIE['remembered_username']);
                    }
                }
                
                // ==========================================
                // REDIRECT KE DASHBOARD SESUAI ROLE
                // ==========================================
                if ($user['role'] == 'superadmin') {
                    header('Location: ../pengguna/superadmin/dashboard.php');
                } elseif ($user['role'] == 'admin') {
                    header('Location: ../pengguna/admin/dashboard.php');
                } elseif ($user['role'] == 'user') {
                    header('Location: ../pengguna/user/dashboard.php');
                } else {
                    // Jika ada peran lain yang tidak terduga
                    $_SESSION['error_message'] = 'Role user tidak valid';
                    header('Location: login.php');
                }
                exit();

            } else {
                // ==========================================
                // PASSWORD SALAH
                // ==========================================
                $_SESSION['error_message'] = 'Password yang Anda masukkan salah';
                header("location: login.php");
                exit();
            }
        } else {
            // ==========================================
            // USERNAME TIDAK DITEMUKAN
            // ==========================================
            $_SESSION['error_message'] = 'Username tidak ditemukan dalam sistem';
            header("location: login.php");
            exit();
        }
    } catch (PDOException $e) {
        // ==========================================
        // DATABASE ERROR
        // ==========================================
        $_SESSION['error_message'] = 'Terjadi kesalahan sistem. Silakan coba lagi.';
        error_log("Login Error: " . $e->getMessage()); // Log error ke file
        header("location: login.php");
        exit();
    }
} else {
    // ==========================================
    // AKSES LANGSUNG KE FILE (BUKAN VIA FORM)
    // ==========================================
    header("location: login.php");
    exit();
}
?>