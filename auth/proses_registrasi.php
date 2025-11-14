<?php
// Selalu panggil session_start di awal
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 1. Panggil Koneksi Database
// Sesuaikan path ini jika 'system' tidak berada satu level di atas 'auth'
require_once '../system/database_connection.php';

// 2. Definisikan Kunci Spesial (Server)
$correct_special_key = "AGS_Buat_Account_SuperAdmin";

// 3. Validasi Metode Request dan Kunci Spesial
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Jika bukan POST, tendang ke login
    header("Location: login.php");
    exit;
}

// Ambil data dari form
$submitted_key = $_POST['special_key_verified'] ?? '';
$full_name = trim($_POST['full_name'] ?? '');
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

// 4. Verifikasi Kunci Spesial dari Server
if ($submitted_key !== $correct_special_key) {
    // Jika kunci tidak cocok (misalnya, diedit di HTML), kirim error
    header("Location: login.php?register_error=" . urlencode("Kunci spesial tidak valid. Registrasi gagal."));
    exit;
}

// 5. Validasi Input
$errors = [];
if (empty($full_name)) {
    $errors[] = "Nama Lengkap wajib diisi.";
}
if (empty($username)) {
    $errors[] = "Username wajib diisi.";
}
if (empty($password)) {
    $errors[] = "Password wajib diisi.";
}
if (strlen($password) < 6) {
    $errors[] = "Password minimal harus 8 karakter.";
}
if ($password !== $confirm_password) {
    $errors[] = "Password dan Konfirmasi Password tidak cocok.";
}

// Jika ada error validasi, kirim kembali
if (!empty($errors)) {
    header("Location: login.php?register_error=" . urlencode(implode(' ', $errors)));
    exit;
}

// 6. Cek Username Duplikat
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetchColumn() > 0) {
        // Jika username sudah ada
        header("Location: login.php?register_error=" . urlencode("Username '$username' sudah terdaftar. Silakan gunakan username lain."));
        exit;
    }

    // 7. Hash Password
    $hashed_password = password_hash($password, PASSWORD_BCRYPT);

    // 8. Masukkan ke Database (dengan role 'superadmin' sesuai permintaan)
    $sql = "INSERT INTO users (username, full_name, password, role) VALUES (?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$username, $full_name, $hashed_password, 'superadmin']);

    // 9. Registrasi Berhasil
    header("Location: login.php?register_success=" . urlencode("Akun Superadmin '$username' berhasil dibuat. Silakan login."));
    exit;

} catch (PDOException $e) {
    // Tangani error database
    header("Location: login.php?register_error=" . urlencode("Error database: " . $e->getMessage()));
    exit;
}
?>