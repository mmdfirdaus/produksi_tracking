<?php
session_start();
include '../../../system/database_connection.php';

// Keamanan: Pastikan hanya superadmin yang dapat mengakses file ini
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'superadmin') {
    // Redirect ke halaman login jika tidak memiliki otorisasi
    header("location: ../../auth/login.php");
    exit;
}

// 1. Logika untuk MENAMBAH alur/tahapan baru
if (isset($_POST['tambah_alur'])) {
    // Ambil data dari form dan lakukan sanitasi dasar
    $nama_alur = trim($_POST['nama_alur']);
    $id_area = filter_input(INPUT_POST, 'id_area', FILTER_VALIDATE_INT);
    $urutan = filter_input(INPUT_POST, 'urutan', FILTER_VALIDATE_INT);

    // Validasi sederhana
    if (empty($nama_alur) || $id_area === false || $urutan === false) {
        $status = 'danger';
        $message = 'Semua field wajib diisi dengan benar.';
    } else {
        try {
            // Gunakan prepared statement untuk keamanan
            $sql = "INSERT INTO master_alur (nama_alur, id_area, urutan) VALUES (:nama_alur, :id_area, :urutan)";
            $stmt = $pdo->prepare($sql);

            // Bind parameter ke statement
            $stmt->bindParam(':nama_alur', $nama_alur, PDO::PARAM_STR);
            $stmt->bindParam(':id_area', $id_area, PDO::PARAM_INT);
            $stmt->bindParam(':urutan', $urutan, PDO::PARAM_INT);

            // Eksekusi statement
            if ($stmt->execute()) {
                $status = 'success';
                $message = 'Tahapan baru berhasil ditambahkan.';
            } else {
                $status = 'danger';
                $message = 'Gagal menambahkan tahapan baru.';
            }
        } catch (PDOException $e) {
            $status = 'danger';
            // Tampilkan pesan error yang lebih spesifik jika diperlukan untuk debugging
            $message = 'Terjadi error pada database: ' . $e->getMessage();
        }
    }
    // Redirect kembali ke halaman kelola alur dengan membawa status dan pesan
    header("location: kelola_alur_mesin.php?status=" . $status . "&message=" . urlencode($message));
    exit;
}

// 2. Logika untuk MEMPERBARUI alur/tahapan
elseif (isset($_POST['update_alur'])) {
    // Ambil data dari form modal edit
    $id_alur = filter_input(INPUT_POST, 'id_alur', FILTER_VALIDATE_INT);
    $nama_alur = trim($_POST['nama_alur']);
    $id_area = filter_input(INPUT_POST, 'id_area', FILTER_VALIDATE_INT);
    $urutan = filter_input(INPUT_POST, 'urutan', FILTER_VALIDATE_INT);

    // Validasi
    if ($id_alur === false || empty($nama_alur) || $id_area === false || $urutan === false) {
        $status = 'danger';
        $message = 'Data tidak valid untuk diperbarui.';
    } else {
        try {
            $sql = "UPDATE master_alur SET nama_alur = :nama_alur, id_area = :id_area, urutan = :urutan WHERE id_alur = :id_alur";
            $stmt = $pdo->prepare($sql);

            $stmt->bindParam(':nama_alur', $nama_alur, PDO::PARAM_STR);
            $stmt->bindParam(':id_area', $id_area, PDO::PARAM_INT);
            $stmt->bindParam(':urutan', $urutan, PDO::PARAM_INT);
            $stmt->bindParam(':id_alur', $id_alur, PDO::PARAM_INT);

            if ($stmt->execute()) {
                $status = 'success';
                $message = 'Data tahapan berhasil diperbarui.';
            } else {
                $status = 'danger';
                $message = 'Gagal memperbarui data tahapan.';
            }
        } catch (PDOException $e) {
            $status = 'danger';
            $message = 'Terjadi error pada database: ' . $e->getMessage();
        }
    }
    header("location: kelola_alur_mesin.php?status=" . $status . "&message=" . urlencode($message));
    exit;
}

// 3. Logika untuk MENGHAPUS alur/tahapan
elseif (isset($_POST['hapus_alur'])) {
    $id_alur = filter_input(INPUT_POST, 'id_alur', FILTER_VALIDATE_INT);

    if ($id_alur === false) {
        $status = 'danger';
        $message = 'ID tahapan tidak valid.';
    } else {
        try {
            $sql = "DELETE FROM master_alur WHERE id_alur = :id_alur";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':id_alur', $id_alur, PDO::PARAM_INT);

            if ($stmt->execute()) {
                $status = 'success';
                $message = 'Tahapan berhasil dihapus.';
            } else {
                $status = 'danger';
                $message = 'Gagal menghapus tahapan.';
            }
        } catch (PDOException $e) {
            $status = 'danger';
            $message = 'Gagal menghapus data karena terhubung dengan data lain. Error: ' . $e->getMessage();
        }
    }
    header("location: kelola_alur_mesin.php?status=" . $status . "&message=" . urlencode($message));
    exit;
}

// Jika tidak ada aksi yang cocok, redirect ke halaman utama
else {
    header("location: kelola_alur_mesin.php");
    exit;
}
?>