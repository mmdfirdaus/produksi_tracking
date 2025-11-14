<?php
session_start();
include '../../../system/database_connection.php';

// Cek hak akses superadmin
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'superadmin') {
    header("location: ../../auth/login.php?error=access_denied");
    exit;
}

// Proses Tambah Alur
if (isset($_POST['tambah_alur'])) {
    $nama_alur = $_POST['nama_alur'];
    $urutan = $_POST['urutan'];
    $stmt = $pdo->prepare("INSERT INTO master_alur (nama_alur, urutan) VALUES (?, ?)");
    $stmt->execute([$nama_alur, $urutan]);
}

// Proses Tambah Mesin
if (isset($_POST['tambah_mesin'])) {
    $id_alur = $_POST['id_alur_for_mesin'];
    $nama_mesin = $_POST['nama_mesin'];
    $stmt = $pdo->prepare("INSERT INTO master_mesin (id_alur, nama_mesin) VALUES (?, ?)");
    $stmt->execute([$id_alur, $nama_mesin]);
}

// Redirect kembali ke halaman kelola tahapan
header("Location: kelola_tahapan.php");
exit;
?>