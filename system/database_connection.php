<?php
// Pengaturan Database
$host = 'localhost';
$dbname = 'db_produksi_tracking';
$user = 'root';
$pass = ''; // Password default XAMPP adalah kosong

// =================================================================
// KONEKSI BARU (MySQLi) untuk kode baru/prosedural
// =================================================================
$conn = mysqli_connect($host, $user, $pass, $dbname);

// Cek koneksi MySQLi
if (!$conn) {
    die("Koneksi MySQLi gagal: " . mysqli_connect_error());
}
mysqli_set_charset($conn, "utf8mb4");


// =================================================================
// KONEKSI LAMA (PDO) untuk menjaga kompatibilitas kode yang sudah ada
// =================================================================
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    
    // Set mode error PDO ke exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Set mode fetch default ke associative array
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Tampilkan pesan error jika koneksi PDO gagal
    die("Koneksi PDO ke database gagal: " . $e->getMessage());
}
?>