<?php
session_start();
require_once '../../system/database_connection.php';

// Pastikan hanya superadmin yang bisa akses
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'superadmin' || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../../auth/login.php");
    exit();
}

try {
    $pdo->beginTransaction();

    // Loop melalui setiap admin yang dikirim dari form
    if (isset($_POST['admins'])) {
        foreach ($_POST['admins'] as $adminId) {
            // 1. Hapus semua hak akses lama untuk admin ini
            $stmtDelete = $pdo->prepare("DELETE FROM admin_tahapan_access WHERE id_user = ?");
            $stmtDelete->execute([$adminId]);

            // 2. Tambahkan hak akses baru yang dipilih (jika ada)
            if (isset($_POST['access'][$adminId])) {
                $stmtInsert = $pdo->prepare("INSERT INTO admin_tahapan_access (id_user, id_tahapan) VALUES (?, ?)");
                foreach ($_POST['access'][$adminId] as $tahapanId) {
                    $stmtInsert->execute([$adminId, $tahapanId]);
                }
            }
        }
    }

    $pdo->commit();
    $_SESSION['success_message'] = "Hak akses berhasil diperbarui!";

} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['error_message'] = "Gagal memperbarui hak akses: " . $e->getMessage();
}

header("Location: manage_user_access.php");
exit();