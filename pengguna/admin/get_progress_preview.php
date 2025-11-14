<?php
session_start();
header('Content-Type: application/json');

// Pastikan admin atau superadmin yang bisa akses
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !in_array($_SESSION["role"], ['admin', 'superadmin'])) {
    echo json_encode(['error' => 'Akses ditolak']);
    exit;
}

include '../../system/database_connection.php';

$id_target = isset($_GET['id_target']) ? (int)$_GET['id_target'] : 0;

if ($id_target === 0) {
    echo json_encode(['error' => 'ID Target tidak valid.']);
    exit;
}

try {
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    
    // 1. Ambil jumlah unit DAN id_barang dari target
    $stmt_target = $pdo->prepare("SELECT jumlah_unit, id_barang FROM production_targets WHERE id_target = ?");
    $stmt_target->execute([$id_target]);
    $target_info = $stmt_target->fetch(PDO::FETCH_ASSOC);

    if (!$target_info) {
        echo json_encode(['error' => 'Target tidak ditemukan.']);
        exit;
    }
    
    $jumlah_unit_target = $target_info['jumlah_unit'];
    $id_barang = $target_info['id_barang']; // Ambil id_barang

    // 2. Modifikasi Kueri Utama dengan JOIN alur_barang
    $stmt = $pdo->prepare("
        SELECT 
            ma.nama_alur,
            mk.nama_komponen,
            tm.jumlah_per_unit,
            (tm.jumlah_per_unit * :jumlah_unit) AS kebutuhan_total,
            COALESCE(SUM(lh.jumlah_selesai), 0) AS total_selesai
        FROM target_material tm
        JOIN master_komponen mk ON tm.id_komponen = mk.id_komponen
        JOIN master_alur ma ON tm.id_alur = ma.id_alur
        
        /* --- INI PERBAIKANNYA --- */
        JOIN alur_barang ab ON ab.id_barang = :id_barang AND ab.id_alur = tm.id_alur
        /* --- SELESAI PERBAIKAN --- */
        
        LEFT JOIN laporan_harian lh ON tm.id_material = lh.id_material
        WHERE tm.id_target = :id_target
        GROUP BY ma.nama_alur, ma.urutan, mk.nama_komponen, tm.jumlah_per_unit
        ORDER BY ma.urutan, mk.nama_komponen
    ");
    
    // 3. Tambahkan :id_barang ke execute
    $stmt->execute([
        ':id_target' => $id_target, 
        ':jumlah_unit' => $jumlah_unit_target,
        ':id_barang' => $id_barang 
    ]);
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $progress_data = [];
    foreach ($results as $row) {
        // Kelompokkan berdasarkan nama_alur
        $progress_data[$row['nama_alur']][] = [
            'nama_komponen' => $row['nama_komponen'],
            'kebutuhan_total' => $row['kebutuhan_total'],
            'total_selesai' => $row['total_selesai']
        ];
    }
    
    echo json_encode($progress_data);

} catch (PDOException $e) {
    // Catat error ke log
    error_log($e->getMessage());
    echo json_encode(['error' => 'Terjadi kesalahan pada database.']);
}
?>