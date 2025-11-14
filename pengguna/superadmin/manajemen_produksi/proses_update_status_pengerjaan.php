<?php
session_start();
header('Content-Type: application/json');

// --- PERBAIKAN UTAMA ADA DI SINI ---
// Path yang benar dari /pengguna/superadmin/manajemen_produksi/ adalah naik 3 tingkat
include('../../../system/database_connection.php');

try {
    // Pastikan variabel $pdo ada setelah include
    if (!isset($pdo)) {
        throw new Exception("Koneksi database gagal dimuat. Periksa kembali path include file.");
    }

    // Cek hak akses
    if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['superadmin', 'admin'])) {
        throw new Exception('Akses ditolak. Sesi tidak valid atau Anda tidak memiliki izin.');
    }

    // Ambil data dari POST
    $id_target = isset($_POST['id_target']) ? (int)$_POST['id_target'] : 0;
    $id_alur = isset($_POST['id_alur']) ? (int)$_POST['id_alur'] : 0;
    $status_baru = isset($_POST['status']) ? $_POST['status'] : '';

    // Validasi
    if ($id_target <= 0 || $id_alur <= 0 || !in_array($status_baru, ['Pending', 'Sedang Dikerjakan'])) {
        throw new Exception('Data input tidak valid (ID Target, ID Alur, atau Status).');
    }

    // Logika INSERT atau UPDATE status untuk kombinasi target dan alur
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

} catch (PDOException $e) {
    // Menangkap error spesifik database
    echo json_encode(['success' => false, 'message' => 'Error Database: ' . $e->getMessage()]);
} catch (Exception $e) {
    // Menangkap semua error lainnya
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>