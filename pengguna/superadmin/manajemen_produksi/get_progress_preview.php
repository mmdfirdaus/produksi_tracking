<?php
session_start();
header('Content-Type: application/json');
include '../../../system/database_connection.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'superadmin') {
    http_response_code(403);
    echo json_encode(['error' => 'Akses ditolak']);
    exit;
}

$id_target = isset($_GET['id_target']) ? (int)$_GET['id_target'] : 0;
if ($id_target === 0) {
    http_response_code(400);
    echo json_encode(['error' => 'ID Target tidak valid']);
    exit;
}

try {
    // Ambil semua material yang relevan, hitung progres, dan kelompokkan per alur
    $stmt = $pdo->prepare("
        SELECT 
            ma.nama_alur,
            mc.nama_komponen,
            (tm.jumlah_per_unit * pt.jumlah_unit) AS kebutuhan_total,
            COALESCE(lh.total_selesai, 0) AS total_selesai
        FROM target_material tm
        JOIN production_targets pt ON tm.id_target = pt.id_target
        JOIN master_komponen mc ON tm.id_komponen = mc.id_komponen
        JOIN master_alur ma ON tm.id_alur = ma.id_alur
        JOIN alur_barang ab ON ma.id_alur = ab.id_alur AND pt.id_barang = ab.id_barang
        LEFT JOIN (
            SELECT id_material, SUM(jumlah_selesai) as total_selesai 
            FROM laporan_harian 
            GROUP BY id_material
        ) lh ON tm.id_material = lh.id_material
        WHERE tm.id_target = :id_target
        ORDER BY ma.urutan ASC, mc.nama_komponen ASC
    ");
    $stmt->execute([':id_target' => $id_target]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Olah data untuk dikirim sebagai JSON
    $progress_data = [];
    foreach ($results as $row) {
        $progress_data[$row['nama_alur']][] = $row;
    }

    echo json_encode($progress_data);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
exit;
?>