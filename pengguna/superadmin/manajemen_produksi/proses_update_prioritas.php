<?php
session_start();
include '../../../system/database_connection.php';

// Validasi sesi superadmin
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'superadmin') {
    header("location: ../../auth/login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';
    $id_target = isset($_POST['id_target']) ? (int)$_POST['id_target'] : 0;
    $id_barang = isset($_POST['id_barang']) ? (int)$_POST['id_barang'] : 0;
    $deadline = $_POST['priority_deadline'] ?? null;

    if ($id_target > 0 && $id_barang > 0) {
        try {
            if ($action === 'set_priority') {
                if (empty($deadline)) {
                    throw new Exception("Tanggal tenggat wajib diisi.");
                }

                $stmt = $pdo->prepare(
                    "UPDATE production_targets SET is_priority = 1, priority_deadline = :deadline WHERE id_target = :id_target"
                );
                $stmt->execute([':deadline' => $deadline, ':id_target' => $id_target]);
                $_SESSION['flash_message'] = ['status' => 'success', 'message' => 'Target berhasil dijadikan prioritas.'];

            } elseif ($action === 'unset_priority') {

                $stmt = $pdo->prepare(
                    "UPDATE production_targets SET is_priority = 0, priority_deadline = NULL WHERE id_target = :id_target"
                );
                $stmt->execute([':id_target' => $id_target]);
                $_SESSION['flash_message'] = ['status' => 'success', 'message' => 'Status prioritas telah dibatalkan.'];

            } else {
                throw new Exception("Aksi tidak valid.");
            }
        } catch (Exception $e) {
            $_SESSION['flash_message'] = ['status' => 'danger', 'message' => 'Terjadi kesalahan: ' . $e->getMessage()];
        }
    } else {
        $_SESSION['flash_message'] = ['status' => 'danger', 'message' => 'Data tidak lengkap.'];
    }

    // Redirect kembali ke halaman detail barang
    header("Location: detail_barang.php?id=" . $id_barang);
    exit;
} else {
    // Jika bukan metode POST, alihkan
    header("Location: ../master_data/kelola_master_barang.php");
    exit;
}
?>