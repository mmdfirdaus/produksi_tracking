<?php
session_start();
header('Content-Type: application/json');
include '../../system/database_connection.php';

// 1. OTENTIKASI
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'superadmin') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Akses ditolak']);
    exit;
}

// Inisialisasi variabel
$sql = "";
$params = [];

// 2. LOGIKA BARU UNTUK DASHBOARD (menggunakan parameter `filter`)
if (isset($_GET['filter'])) {
    $filter = $_GET['filter'];

    // UPDATE BARU: Menambahkan pt.no_spk dan memastikan pt.id_barang terpanggil
    $base_sql = "SELECT 
                    pt.id_target, 
                    pt.id_barang,
                    pt.no_spk, 
                    mb.nama_barang, 
                    pt.jumlah_unit AS jumlah_target, 
                    pt.status, 
                    pt.is_priority AS prioritas
                 FROM production_targets pt
                 JOIN master_barang mb ON pt.id_barang = mb.id_barang";

    if ($filter == 'berjalan') {
        $sql = $base_sql . " WHERE pt.status = 'ongoing' AND pt.is_active = 1 ORDER BY pt.id_target DESC";
    } elseif ($filter == 'prioritas') {
        $sql = $base_sql . " WHERE pt.is_priority = 1 AND pt.status = 'ongoing' AND pt.is_active = 1 ORDER BY pt.priority_deadline ASC";
    }

// 3. LOGIKA LAMA (menggunakan parameter `type`)
} elseif (isset($_GET['type'])) {
    $type = $_GET['type'];

    switch ($type) {
        case 'priority':
            // UPDATE BARU: Menambahkan pt.no_spk
            $sql = "SELECT pt.id_target, pt.id_barang, pt.no_spk, pt.nama_permintaan, mb.nama_barang, pt.priority_deadline
                    FROM production_targets pt
                    JOIN master_barang mb ON pt.id_barang = mb.id_barang
                    WHERE pt.status = 'ongoing' AND pt.is_active = 1 AND pt.is_priority = 1
                    ORDER BY pt.priority_deadline ASC";
            break;

        case 'pending':
            // UPDATE BARU: Menambahkan pt.no_spk
            $sql = "SELECT pt.id_target, pt.id_barang, pt.no_spk, pt.nama_permintaan, mb.nama_barang
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
            // UPDATE BARU: Menambahkan pt.no_spk di SELECT dan GROUP BY
            // Penting: GROUP BY harus menyertakan no_spk agar query valid
            $sql = "SELECT * FROM (
                        SELECT pt.id_target, pt.id_barang, pt.no_spk, pt.nama_permintaan, mb.nama_barang, MAX(lh.tanggal_laporan) AS last_report
                        FROM production_targets pt
                        JOIN master_barang mb ON pt.id_barang = mb.id_barang
                        LEFT JOIN target_material tm ON pt.id_target = tm.id_target
                        LEFT JOIN laporan_harian lh ON tm.id_material = lh.id_material
                        WHERE pt.status = 'ongoing' AND pt.is_active = 1
                        GROUP BY pt.id_target, pt.id_barang, pt.nama_permintaan, mb.nama_barang, pt.no_spk
                    ) AS sub
                    WHERE sub.last_report < CURDATE() - INTERVAL 3 DAY OR sub.last_report IS NULL
                    ORDER BY sub.last_report ASC";
            break;

        case 'archived':
            // UPDATE BARU: Menambahkan pt.no_spk
            $sql = "SELECT pt.id_target, pt.id_barang, pt.no_spk, pt.nama_permintaan, mb.nama_barang, pt.alasan_nonaktif, pt.created_at
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
} else {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Parameter tidak valid. Harap sediakan "filter" atau "type".']);
    exit;
}

// 4. EKSEKUSI
try {
    if (!empty($sql)) {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['status' => 'success', 'data' => $results]);
    } else {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Filter tidak valid.']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
?>