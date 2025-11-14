<?php
session_start();
header('Content-Type: application/json');

// Pastikan hanya admin yang bisa akses
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'admin') {
    echo json_encode(['error' => 'Akses ditolak']);
    exit;
}

include '../../../system/database_connection.php';

$id_target = isset($_GET['id_target']) ? (int)$_GET['id_target'] : 0;

if ($id_target === 0) {
    echo json_encode(['error' => 'ID Target tidak valid.']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT DISTINCT DATE_FORMAT(tanggal_laporan, '%Y-%m') as month_value,
                        DATE_FORMAT(tanggal_laporan, '%M %Y') as month_name
        FROM laporan_harian lh
        JOIN target_material tm ON lh.id_material = tm.id_material
        WHERE tm.id_target = :id_target
        ORDER BY month_value DESC
    ");
    $stmt->execute([':id_target' => $id_target]);
    $months = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $formatted_months = [];
    foreach ($months as $month) {
        $formatted_months[] = [
            'value' => $month['month_value'],
            'name' => $month['month_name']
        ];
    }

    echo json_encode(['months' => $formatted_months]);

} catch (PDOException $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>