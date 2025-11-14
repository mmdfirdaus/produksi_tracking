<?php
session_start();
header('Content-Type: application/json');

include('../../../system/database_connection.php');

try {
    if (!isset($pdo)) {
        throw new Exception("Koneksi database gagal dimuat.");
    }

    $id_user_login = $_SESSION['user_id'] ?? 0;
    $role = $_SESSION['role'] ?? '';

    if ($id_user_login === 0 || !in_array($role, ['superadmin', 'admin'])) {
        throw new Exception('Akses ditolak. Sesi tidak valid.');
    }

    $id_target = isset($_POST['id_target']) ? (int)$_POST['id_target'] : 0;
    $id_alur = isset($_POST['id_alur']) ? (int)$_POST['id_alur'] : 0;
    $status_baru = isset($_POST['status']) ? $_POST['status'] : '';

    if ($id_target <= 0 || $id_alur <= 0 || !in_array($status_baru, ['Pending', 'Sedang Dikerjakan'])) {
        throw new Exception('Data input tidak valid.');
    }

    $can_update = false;
    if ($role === 'superadmin') {
        $can_update = true;
    } else { // Jika role adalah 'admin', cek hak aksesnya
        $stmt_check_access = $pdo->prepare(
            "SELECT 1 FROM admin_tahapan_access WHERE id_user = :id_user AND id_tahapan = :id_alur LIMIT 1"
        );
        $stmt_check_access->execute([':id_user' => $id_user_login, ':id_alur' => $id_alur]);
        if ($stmt_check_access->fetchColumn()) {
            $can_update = true;
        }
    }

    if ($can_update) {
        $stmt = $pdo->prepare("
            INSERT INTO target_alur_status (id_target, id_alur, status_pengerjaan)
            VALUES (:id_target, :id_alur, :status)
            ON DUPLICATE KEY UPDATE status_pengerjaan = :status
        ");
        $params = [
            ':id_target' => $id_target,
            ':id_alur' => $id_alur,
            ':status' => $status_baru
        ];
        if ($stmt->execute($params)) {
            echo json_encode(['success' => true, 'message' => 'Status untuk tahapan ini berhasil diperbarui.']);
        } else {
            throw new Exception('Gagal memperbarui status di database.');
        }
    } else {
        throw new Exception('Anda tidak memiliki izin untuk mengubah status tahapan ini.');
    }

} catch (Exception $e) {
    http_response_code(403); // Forbidden
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>