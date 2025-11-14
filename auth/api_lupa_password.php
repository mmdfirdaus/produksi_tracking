<?php
session_start();
header('Content-Type: application/json');
include '../system/database_connection.php';

// Definisikan Special Key di sini. Ganti dengan kode yang lebih aman.
define('SUPERADMIN_SPECIAL_KEY', 'KunciRahasiaSuperAdmin');

$action = $_POST['action'] ?? '';
$response = ['status' => 'error', 'message' => 'Aksi tidak valid.'];

try {
    switch ($action) {
        // --- TAHAP 1: Cek apakah username adalah superadmin ---
        case 'check_username':
            $username = $_POST['username'] ?? '';
            if (empty($username)) {
                throw new Exception("Username tidak boleh kosong.");
            }

            $stmt = $pdo->prepare("SELECT role FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if (!$user) {
                throw new Exception("Username tidak ditemukan.");
            }

            if ($user['role'] !== 'superadmin') {
                throw new Exception("Fitur ini hanya tersedia untuk Superadmin.");
            }

            $response = ['status' => 'success', 'message' => 'Username terverifikasi sebagai Superadmin.'];
            break;

        // --- TAHAP 2: Verifikasi Special Key ---
        case 'verify_key':
            $username = $_POST['username'] ?? '';
            $special_key = $_POST['special_key'] ?? '';

            if (empty($username) || empty($special_key)) {
                throw new Exception("Data tidak lengkap.");
            }

            // Validasi ulang apakah user adalah superadmin
            $stmt = $pdo->prepare("SELECT role FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()['role'] !== 'superadmin') {
                 throw new Exception("Verifikasi gagal.");
            }

            if ($special_key !== SUPERADMIN_SPECIAL_KEY) {
                throw new Exception("Special Key salah.");
            }
            
            // Buat token sementara yang berlaku sebentar untuk keamanan
            $token = bin2hex(random_bytes(16));
            $_SESSION['reset_token'] = $token;
            $_SESSION['reset_token_expiry'] = time() + 300; // Token berlaku 5 menit
            $_SESSION['reset_username'] = $username;

            $response = ['status' => 'success', 'message' => 'Special Key benar.', 'token' => $token];
            break;

        // --- TAHAP 3: Atur Ulang Password ---
        case 'reset_password':
            $username = $_POST['username'] ?? '';
            $token = $_POST['token'] ?? '';
            $new_password = $_POST['new_password'] ?? '';

            // Validasi token dan waktu kedaluwarsa
            if (
                empty($token) || 
                !isset($_SESSION['reset_token']) || 
                !hash_equals($_SESSION['reset_token'], $token) ||
                time() > $_SESSION['reset_token_expiry'] ||
                $_SESSION['reset_username'] !== $username
            ) {
                // Hapus session jika tidak valid
                unset($_SESSION['reset_token'], $_SESSION['reset_token_expiry'], $_SESSION['reset_username']);
                throw new Exception("Sesi reset password tidak valid atau telah kedaluwarsa.");
            }

            if (empty($new_password) || strlen($new_password) < 6) {
                 throw new Exception("Password baru minimal harus 6 karakter.");
            }

            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE username = ? AND role = 'superadmin'");
            $stmt->execute([$hashed_password, $username]);
            
            if ($stmt->rowCount() > 0) {
                 $response = ['status' => 'success', 'message' => 'Password berhasil diubah.'];
            } else {
                 throw new Exception("Gagal memperbarui password di database.");
            }

            // Hapus session setelah berhasil
            unset($_SESSION['reset_token'], $_SESSION['reset_token_expiry'], $_SESSION['reset_username']);
            break;
    }
} catch (Exception $e) {
    $response = ['status' => 'error', 'message' => $e->getMessage()];
}

echo json_encode($response);
?>