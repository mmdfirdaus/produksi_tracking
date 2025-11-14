<?php
session_start();
// Sertakan file koneksi database
// Pastikan path ini benar dan file mendefinisikan variabel $pdo dan konstanta BASE_URL
include '../../system/database_connection.php';

// Cek apakah pengguna sudah login dan rolenya superadmin
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'superadmin') {
    // Pastikan BASE_URL sudah didefinisikan di file koneksi
    header("location: " . (defined('BASE_URL') ? BASE_URL : '../..') . "/auth/login.php?error=access_denied");
    exit;
}

// Logika untuk TAMBAH BARANG
if (isset($_POST['tambah_barang'])) {
    // Gunakan null coalescing operator (??) untuk menghindari "Undefined Index"
    $nama_barang = trim($_POST['nama_barang'] ?? '');
    $kategori = trim($_POST['kategori'] ?? '');
    $mesin = trim($_POST['mesin'] ?? ''); // Memberikan nilai default string kosong jika tidak ada
    $gambar = $_FILES['gambar'] ?? null;

    // Validasi dasar yang lebih lengkap
    // Anggap semua field wajib diisi
    if (empty($nama_barang) || empty($kategori) || empty($mesin) || !$gambar || $gambar['error'] == UPLOAD_ERR_NO_FILE) {
        header("location: master_barang.php?status=error&message=Semua field wajib diisi, termasuk gambar.");
        exit;
    }

    // --- LOGIKA UPLOAD GAMBAR ---
    $upload_dir = '../../uploads/';
    // Pastikan direktori uploads ada, jika tidak, buatkan
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $gambar_name = $gambar['name'];
    $gambar_tmp_name = $gambar['tmp_name'];
    $gambar_size = $gambar['size'];
    $gambar_error = $gambar['error'];

    // Cek apakah ada error saat upload
    if ($gambar_error !== UPLOAD_ERR_OK) {
        header("location: master_barang.php?status=error&message=Terjadi kesalahan saat mengupload gambar.");
        exit;
    }

    // Buat nama file yang unik
    $file_extension = strtolower(pathinfo($gambar_name, PATHINFO_EXTENSION));
    $allowed_extensions = ['jpg', 'jpeg', 'png'];
    
    if (!in_array($file_extension, $allowed_extensions)) {
        header("location: master_barang.php?status=error&message=Format file gambar tidak valid. Hanya JPG, JPEG, PNG yang diizinkan.");
        exit;
    }

    $unique_file_name = uniqid('barang_', true) . '.' . $file_extension;
    $destination = $upload_dir . $unique_file_name;

    // Pindahkan file ke direktori tujuan
    if (move_uploaded_file($gambar_tmp_name, $destination)) {
        // --- SIMPAN DATA KE DATABASE ---
        try {
            // Pastikan variabel $pdo sudah ada dari file koneksi
            if (!isset($pdo)) {
                 throw new PDOException("Koneksi database tidak ditemukan.");
            }

            $sql = "INSERT INTO master_barang (nama_barang, kategori, mesin, gambar) VALUES (:nama_barang, :kategori, :mesin, :gambar)";
            $stmt = $pdo->prepare($sql);
            
            $stmt->bindParam(':nama_barang', $nama_barang);
            $stmt->bindParam(':kategori', $kategori);
            $stmt->bindParam(':mesin', $mesin);
            $stmt->bindParam(':gambar', $unique_file_name);

            if ($stmt->execute()) {
                header("location: master_barang.php?status=success&message=Barang baru berhasil ditambahkan.");
            } else {
                // Hapus gambar jika query gagal
                unlink($destination);
                header("location: master_barang.php?status=error&message=Gagal menyimpan data ke database.");
            }
        } catch (PDOException $e) {
            // Hapus gambar yang sudah diupload jika query gagal
            if (file_exists($destination)) {
                unlink($destination);
            }
            // Jangan tampilkan pesan error database mentah ke pengguna di production
            // error_log("Database error: " . $e->getMessage()); // Catat error di server log
            header("location: master_barang.php?status=error&message=Terjadi kesalahan pada database.");
        }
    } else {
        header("location: master_barang.php?status=error&message=Gagal memindahkan file gambar.");
    }
    exit;
}

// Redirect jika halaman diakses langsung
header("location: master_barang.php");
exit;
?>