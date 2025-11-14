<?php
session_start();
header('Content-Type: application/json');
include '../../system/database_connection.php';

// 1. OTENTIKASI (Sama seperti kode lama Anda)
// Tetap menggunakan otentikasi yang lebih ketat dari kode Anda.
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'superadmin') {
    http_response_code(403);
    // Menggunakan format respons baru agar konsisten
    echo json_encode(['status' => 'error', 'message' => 'Akses ditolak']);
    exit;
}

// Inisialisasi variabel
$sql = "";
$params = [];

// 2. LOGIKA BARU UNTUK DASHBOARD (menggunakan parameter `filter`)
// Blok ini menangani permintaan dari card di dashboard baru.
if (isset($_GET['filter'])) {
    $filter = $_GET['filter'];

    // Menyiapkan query dasar yang mengambil kolom sesuai kebutuhan modal di dashboard
    // Saya menggunakan alias (AS) agar nama kolomnya cocok dengan yang diharapkan JavaScript baru
    // pt.jumlah_unit AS jumlah_target -> Mengganti nama kolom saat data dikirim
    // pt.is_priority AS prioritas -> Mengganti nama kolom saat data dikirim
    $base_sql = "SELECT 
                    pt.id_target, 
                    mb.nama_barang, 
                    pt.jumlah_unit AS jumlah_target, 
                    pt.status, 
                    pt.is_priority AS prioritas
                 FROM production_targets pt
                 JOIN master_barang mb ON pt.id_barang = mb.id_barang";

    if ($filter == 'berjalan') {
        // Logika 'berjalan' dari dashboard disesuaikan dengan skema Anda: status = 'ongoing' dan is_active = 1
        $sql = $base_sql . " WHERE pt.status = 'ongoing' AND pt.is_active = 1 ORDER BY pt.id_target DESC";
    } elseif ($filter == 'prioritas') {
        // Logika 'prioritas' dari dashboard disesuaikan dengan skema Anda: is_priority = 1, status = 'ongoing', dan is_active = 1
        $sql = $base_sql . " WHERE pt.is_priority = 1 AND pt.status = 'ongoing' AND pt.is_active = 1 ORDER BY pt.priority_deadline ASC";
    }

// 3. LOGIKA LAMA ANDA DIPERTAHANKAN (menggunakan parameter `type`)
// Jika tidak ada parameter 'filter', kode akan memeriksa parameter 'type' seperti sebelumnya.
} elseif (isset($_GET['type'])) {
    $type = $_GET['type'];

    // Semua 'case' dari kode lama Anda ada di sini, tidak diubah sama sekali.
    switch ($type) {
        case 'priority':
            $sql = "SELECT pt.id_target, pt.id_barang, pt.nama_permintaan, mb.nama_barang, pt.priority_deadline
                    FROM production_targets pt
                    JOIN master_barang mb ON pt.id_barang = mb.id_barang
                    WHERE pt.status = 'ongoing' AND pt.is_active = 1 AND pt.is_priority = 1
                    ORDER BY pt.priority_deadline ASC";
            break;

        case 'pending':
            $sql = "SELECT pt.id_target, pt.id_barang, pt.nama_permintaan, mb.nama_barang
                    FROM production_targets pt
                    JOIN master_barang mb ON pt.id_barang = mb.id_barang
                    WHERE pt.status = 'ongoing' AND pt.is_active = 1 AND (
                        SELECT COUNT(tm.id_material) FROM target_material tm JOIN alur_barang ab ON tm.id_alur = ab.id_alur AND ab.id_barang = pt.id_barang WHERE tm.id_target = pt.id_target
                    ) > 0 AND (
                        SELECT COUNT(tm.id_material)
                        FROM target_material tm
                        JOIN alur_barang ab ON tm.id_alur = ab.id_alur AND ab.id_barang = pt.id_barang
                        LEFT JOIN (
                            SELECT id_material, SUM(jumlah_selesai) as total_selesai 
                            FROM laporan_harian GROUP BY id_material
                        ) lh ON tm.id_material = lh.id_material
                        WHERE tm.id_target = pt.id_target AND (tm.jumlah_per_unit * pt.jumlah_unit) > COALESCE(lh.total_selesai, 0)
                    ) = 0";
            break;

        case 'stalled':
            $sql = "SELECT * FROM (
                        SELECT pt.id_target, pt.id_barang, pt.nama_permintaan, mb.nama_barang, MAX(lh.tanggal_laporan) AS last_report
                        FROM production_targets pt
                        JOIN master_barang mb ON pt.id_barang = mb.id_barang
                        LEFT JOIN target_material tm ON pt.id_target = tm.id_target
                        LEFT JOIN laporan_harian lh ON tm.id_material = lh.id_material
                        WHERE pt.status = 'ongoing' AND pt.is_active = 1
                        GROUP BY pt.id_target, pt.id_barang, pt.nama_permintaan, mb.nama_barang
                    ) AS sub
                    WHERE sub.last_report < CURDATE() - INTERVAL 3 DAY OR sub.last_report IS NULL
                    ORDER BY sub.last_report ASC";
            break;

        case 'archived':
            $sql = "SELECT pt.id_target, pt.id_barang, pt.nama_permintaan, mb.nama_barang, pt.alasan_nonaktif, pt.created_at
                    FROM production_targets pt
                    JOIN master_barang mb ON pt.id_barang = mb.id_barang
                    WHERE pt.is_active = 0
                    ORDER BY pt.created_at DESC";
            break;
        
        default:
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Tipe tidak valid']);
            exit;
    }
// 4. PENANGANAN JIKA TIDAK ADA PARAMETER YANG SESUAI
} else {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Parameter tidak valid. Harap sediakan "filter" atau "type".']);
    exit;
}

// 5. EKSEKUSI QUERY DAN PENGIRIMAN DATA
// Bagian ini dieksekusi untuk semua kondisi di atas (baik 'filter' maupun 'type').
try {
    if (!empty($sql)) {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Menggunakan format respons baru yang lebih deskriptif
        // Ini tidak akan merusak kode JS lama Anda selama JS tersebut mengakses `response.data`
        echo json_encode(['status' => 'success', 'data' => $results]);
    } else {
        // Ini terjadi jika ada 'filter' yang tidak cocok (misal: filter=abc)
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Filter tidak valid.']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
?>