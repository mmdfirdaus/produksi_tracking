<?php
session_start();
require_once '../system/database_connection.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // 1. Simpan username inputan user sementara (PENTING: agar tidak hilang saat redirect)
    $_SESSION['old_username'] = $username;

    // 2. Validasi Input Kosong
    if (empty($username) || empty($password)) {
        $_SESSION['error_message'] = 'Username dan Password wajib diisi!';
        $_SESSION['error_field'] = 'all'; // Tandai semua field merah
        header("location: login.php");
        exit();
    }

    try {
        $sql = "SELECT id, username, password, role, full_name FROM users WHERE username = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$username]);

        if ($stmt->rowCount() == 1) {
            $user = $stmt->fetch();
            
            // 3. Verifikasi Password
            if (password_verify($password, $user['password'])) {
                
                // ==========================================
                // LOGIN BERHASIL
                // ==========================================
                session_regenerate_id();
                $_SESSION["loggedin"] = true;
                $_SESSION["user_id"] = $user['id'];
                $_SESSION["username"] = $user['username'];
                $_SESSION["role"] = $user['role'];
                $_SESSION["full_name"] = $user['full_name'];
                
                // Bersihkan session error & old input karena sudah berhasil
                unset($_SESSION['error_message']);
                unset($_SESSION['error_field']);
                unset($_SESSION['old_username']);
                
                // Handle Remember Me
                if (isset($_POST['remember_me']) && $_POST['remember_me'] === 'on') {
                    $cookie_time = time() + (30 * 24 * 60 * 60); 
                    $cookie_options = [
                        'expires' => $cookie_time,
                        'path' => '/',
                        'domain' => '', 
                        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
                        'httponly' => true,
                        'samesite' => 'Strict'
                    ];
                    setcookie('remembered_username', $username, $cookie_options);
                } else {
                    if (isset($_COOKIE['remembered_username'])) {
                        setcookie('remembered_username', '', time() - 3600, '/');
                        unset($_COOKIE['remembered_username']);
                    }
                }
                
                // Redirect Dashboard sesuai Role
                switch ($user['role']) {
                    case 'superadmin': 
                        header('Location: ../pengguna/superadmin/dashboard.php'); 
                        break;
                    case 'admin': 
                        header('Location: ../pengguna/admin/dashboard.php'); 
                        break;
                    case 'user': 
                        header('Location: ../pengguna/user/dashboard.php'); 
                        break;
                    default:
                        $_SESSION['error_message'] = 'Role user tidak valid.';
                        header('Location: login.php');
                }
                exit();

            } else {
                // ==========================================
                // KASUS: PASSWORD SALAH
                // ==========================================
                $_SESSION['error_message'] = 'Password yang Anda masukkan tidak sesuai.';
                $_SESSION['error_field'] = 'password'; // Tandai field password merah
                header("location: login.php");
                exit();
            }
        } else {
            // ==========================================
            // KASUS: USERNAME TIDAK DITEMUKAN
            // ==========================================
            $_SESSION['error_message'] = 'Username tersebut tidak terdaftar dalam sistem.';
            $_SESSION['error_field'] = 'username'; // Tandai field username merah
            header("location: login.php");
            exit();
        }
    } catch (PDOException $e) {
        // Error Database
        $_SESSION['error_message'] = 'Terjadi kesalahan sistem. Silakan coba lagi nanti.';
        error_log("Login Error: " . $e->getMessage());
        header("location: login.php");
        exit();
    }
} else {
    // Akses langsung ke file
    header("location: login.php");
    exit();
}
?>