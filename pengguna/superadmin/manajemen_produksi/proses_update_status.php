<?php
session_start();
include '../../../system/database_connection.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'superadmin') {
    header("location: ../../auth/login.php");
    exit;
}

$id_target = isset($_GET['id_target']) ? (int)$_GET['id_target'] : 0;
$id_barang = isset($_GET['id_barang']) ? (int)$_GET['id_barang'] : 0;

if ($id_target === 0 || $id_barang === 0) {
    header("Location: ../master_data/kelola_master_barang.php?status=error&message=" . urlencode("ID tidak valid."));
    exit;
}

try {
    // ## PERBAIKAN: Gunakan 'Selesai' (huruf kapital) dan tambahkan tanggal selesai ##
    $stmt = $pdo->prepare("
        UPDATE production_targets 
        SET status = 'Selesai', tanggal_selesai = NOW() 
        WHERE id_target = :id_target
    ");
    $stmt->bindParam(':id_target', $id_target, PDO::PARAM_INT);
    
    if ($stmt->execute()) {
        header("Location: detail_barang.php?id=" . $id_barang . "&status=success&message=" . urlencode("Target telah ditandai selesai dan diarsipkan ke laporan."));
    } else {
        header("Location: detail_barang.php?id=" . $id_barang . "&status=error&message=" . urlencode("Gagal memperbarui status target."));
    }
} catch (PDOException $e) {
    header("Location: detail_barang.php?id=" . $id_barang . "&status=error&message=" . urlencode("Database error: " . $e->getMessage()));
}
exit;
?>