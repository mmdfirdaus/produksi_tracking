<?php
// proses_kategori.php

session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/produksi_tracking/system/database_connection.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'superadmin') {
    header("Location: ../../auth/login.php");
    exit;
}

// Proses Tambah Kategori
if (isset($_POST['tambah_kategori'])) {
    $nama_kategori = mysqli_real_escape_string($conn, $_POST['nama_kategori']);

    $query = "INSERT INTO master_kategori (nama_kategori) VALUES ('$nama_kategori')";
    if (mysqli_query($conn, $query)) {
        $_SESSION['success_message'] = "Kategori berhasil ditambahkan.";
    } else {
        $_SESSION['error_message'] = "Gagal menambahkan kategori: " . mysqli_error($conn);
    }
    header("Location: kelola_kategori.php");
    exit();
}

// Proses Ubah Kategori
if (isset($_POST['ubah_kategori'])) {
    $id_kategori = (int)$_POST['id_kategori'];
    $nama_kategori = mysqli_real_escape_string($conn, $_POST['nama_kategori']);

    $query = "UPDATE master_kategori SET nama_kategori = '$nama_kategori' WHERE id_kategori = $id_kategori";
    if (mysqli_query($conn, $query)) {
        $_SESSION['success_message'] = "Kategori berhasil diupdate.";
    } else {
        $_SESSION['error_message'] = "Gagal mengupdate kategori: " . mysqli_error($conn);
    }
    header("Location: kelola_kategori.php");
    exit();
}

// Proses Hapus Kategori
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id_kategori = (int)$_GET['id'];
    
    $query = "DELETE FROM master_kategori WHERE id_kategori = $id_kategori";
    if (mysqli_query($conn, $query)) {
        $_SESSION['success_message'] = "Kategori berhasil dihapus.";
    } else {
        $_SESSION['error_message'] = "Gagal menghapus kategori: " . mysqli_error($conn);
    }
    header("Location: kelola_kategori.php");
    exit();
}

// Jika tidak ada aksi yang cocok, kembalikan ke halaman kelola
header("Location: kelola_kategori.php");
exit();
?>