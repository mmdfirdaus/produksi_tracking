<?php
// api_get_months.php

// Header untuk menandakan output adalah JSON
header('Content-Type: application/json');

// Mulai sesi untuk keamanan (opsional, tapi disarankan)
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    echo json_encode(['error' => 'Akses ditolak.']);
    exit;
}

// Sertakan koneksi database
include '../../../system/database_connection.php';

// Validasi input
$id_target = isset($_GET['id_target']) ? (int)$_GET['id_target'] : 0;
if ($id_target === 0) {
    echo json_encode(['error' => 'ID Target tidak valid.']);
    exit;
}

$response = [
    'months' => []
];

try {
    // Query untuk mengambil bulan yang unik dari laporan harian
    // Query ini juga memformat nama bulan agar lebih mudah dibaca di frontend
    $stmt = $pdo->prepare("
        SELECT DISTINCT DATE_FORMAT(tanggal_laporan, '%Y-%m') AS production_month_value,
                        DATE_FORMAT(tanggal_laporan, '%M %Y') AS production_month_name
        FROM laporan_harian lh
        JOIN target_material tm ON lh.id_material = tm.id_material
        WHERE tm.id_target = ?
        ORDER BY production_month_value ASC
    ");
    $stmt->execute([$id_target]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($results as $row) {
        $response['months'][] = [
            'value' => $row['production_month_value'],
            'name'  => $row['production_month_name']
        ];
    }

} catch (PDOException $e) {
    // Jika terjadi error, kirim pesan error
    $response['error'] = 'Gagal mengambil data bulan dari database.';
    // Untuk debugging, Anda bisa menambahkan: $response['db_error'] = $e->getMessage();
}

// Cetak hasil dalam format JSON
echo json_encode($response);