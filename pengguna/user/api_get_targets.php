<?php
session_start();
include_once '../../system/database_connection.php';

// Pastikan pengguna sudah login dan memiliki role 'user'
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    http_response_code(403);
    echo '<div class="alert alert-danger">Akses ditolak. Anda harus login sebagai user.</div>';
    exit;
}

$type = $_GET['type'] ?? 'ongoing';

// Inisialisasi query dasar (GLOBAL, TANPA FILTER HAK AKSES)
$sql = "";
$params = [];

// Fungsi untuk mencetak baris tabel
function print_table_row($target, $type) {
    // Tentukan URL detail berdasarkan role (user)
    $detail_url = "";
    if ($type === 'selesai') {
        // Jika sudah 'Selesai', arahkan ke rincian_laporan.php
        $detail_url = "management_laporan/rincian_laporan.php?id_target=" . htmlspecialchars($target['id_target']);
    } else {
        // Untuk 'ongoing', 'prioritas', dll, arahkan ke material.php (untuk input/detail)
        $detail_url = "management_produksi/material.php?id_target=" . htmlspecialchars($target['id_target']);
    }

    echo "<tr>";
    echo "<td>" . htmlspecialchars($target['id_target']) . "</td>";
    // UPDATE: Cetak Kolom No SPK
    echo "<td><span class='badge bg-secondary'>" . htmlspecialchars($target['no_spk'] ?? '-') . "</span></td>";
    echo "<td><a href='{$detail_url}'>" . htmlspecialchars($target['nama_barang']) . "</a></td>";
    echo "<td>" . htmlspecialchars($target['nama_permintaan']) . "</td>";
    echo "<td>" . htmlspecialchars($target['jumlah_unit']) . "</td>";

    // Kolom dinamis berdasarkan tipe
    if ($type === 'prioritas') {
        $deadline_display = $target['priority_deadline'] ? date('d M Y', strtotime($target['priority_deadline'])) : 'N/A';
        echo "<td><span class='badge bg-danger'>" . $deadline_display . "</span></td>";
    } elseif ($type === 'terakhir_input') {
        echo "<td><span class='badge bg-info'>" . htmlspecialchars($target['nama_alur']) . "</span></td>";
        echo "<td>" . date('d M Y, H:i', strtotime($target['created_at'])) . "</td>";
    } elseif ($type === 'terhenti') {
        $last_report_display = $target['last_report_time'] ? date('d M Y', strtotime($target['last_report_time'])) : 'Belum ada';
        echo "<td><span class='badge bg-danger'>" . htmlspecialchars($target['days_stalled']) . " hari</span></td>";
        echo "<td>" . $last_report_display . "</td>";
    } else {
        // Untuk 'ongoing' dan 'selesai'
        $status_badge = $target['status'] === 'ongoing' ? 'bg-warning text-dark' : 'bg-success';
        echo "<td><span class='badge " . $status_badge . "'>" . htmlspecialchars(ucfirst($target['status'])) . "</span></td>";
    }
    
    echo "</tr>";
}

// Tentukan query berdasarkan tipe
switch ($type) {
    case 'ongoing':
        // UPDATE: Tambah pt.no_spk
        $sql = "SELECT pt.id_target, pt.no_spk, mb.nama_barang, pt.nama_permintaan, pt.jumlah_unit, pt.status
                FROM production_targets pt
                JOIN master_barang mb ON pt.id_barang = mb.id_barang
                WHERE pt.status = 'ongoing' AND pt.is_active = 1
                ORDER BY pt.created_at DESC";
        // UPDATE: Tambah Header No SPK
        $columns = ["ID Target", "No SPK", "Nama Barang", "Nama Permintaan", "Jumlah", "Status"];
        break;

    case 'selesai':
        // UPDATE: Tambah pt.no_spk
        $sql = "SELECT pt.id_target, pt.no_spk, mb.nama_barang, pt.nama_permintaan, pt.jumlah_unit, pt.status, pt.tanggal_selesai
                FROM production_targets pt
                JOIN master_barang mb ON pt.id_barang = mb.id_barang
                WHERE pt.status = 'Selesai'
                ORDER BY pt.tanggal_selesai DESC";
        // UPDATE: Tambah Header No SPK
        $columns = ["ID Target", "No SPK", "Nama Barang", "Nama Permintaan", "Jumlah", "Status"];
        break;

    case 'prioritas':
        // UPDATE: Tambah pt.no_spk
        $sql = "SELECT pt.id_target, pt.no_spk, mb.nama_barang, pt.nama_permintaan, pt.jumlah_unit, pt.status, pt.priority_deadline
                FROM production_targets pt
                JOIN master_barang mb ON pt.id_barang = mb.id_barang
                WHERE (pt.prioritas = 'Prioritas' OR pt.is_priority = 1)
                AND pt.status = 'ongoing' AND pt.is_active = 1
                ORDER BY pt.priority_deadline ASC";
        // UPDATE: Tambah Header No SPK
        $columns = ["ID Target", "No SPK", "Nama Barang", "Nama Permintaan", "Jumlah", "Deadline"];
        break;

    case 'terakhir_input':
    // UPDATE: Tambah pt.no_spk
    $sql = "SELECT
                pt.id_target,
                pt.no_spk,
                mb.nama_barang,
                pt.nama_permintaan,
                pt.jumlah_unit,
                lh.created_at,
                ma.nama_alur
            FROM (
                SELECT 
                    tm.id_target,
                    MAX(lh.id_laporan) AS max_id_laporan
                FROM laporan_harian lh
                JOIN target_material tm ON lh.id_material = tm.id_material
                JOIN production_targets pt_inner ON tm.id_target = pt_inner.id_target
                WHERE pt_inner.status = 'ongoing' 
                GROUP BY tm.id_target
            ) AS latest_reports
            JOIN laporan_harian lh ON lh.id_laporan = latest_reports.max_id_laporan
            JOIN target_material tm ON lh.id_material = tm.id_material
            JOIN production_targets pt ON tm.id_target = pt.id_target
            JOIN master_barang mb ON pt.id_barang = mb.id_barang
            JOIN master_alur ma ON tm.id_alur = ma.id_alur
            ORDER BY lh.created_at DESC";
    
    // UPDATE: Tambah Header No SPK
    $columns = ["ID Target", "No SPK", "Nama Barang", "Nama Permintaan", "Jumlah Unit", "Alur (Terbaru)", "Waktu Input (Terbaru)"];
    break;

    case 'terhenti':
        // UPDATE: Tambah pt.no_spk
        $sql = "SELECT pt.id_target, pt.no_spk, mb.nama_barang, pt.nama_permintaan, pt.jumlah_unit,
                       COALESCE(MAX(lh.created_at), pt.created_at) AS last_report_time,
                       DATEDIFF(NOW(), COALESCE(MAX(lh.created_at), pt.created_at)) as days_stalled
                FROM production_targets pt
                JOIN master_barang mb ON pt.id_barang = mb.id_barang
                LEFT JOIN target_material tm ON pt.id_target = tm.id_target
                LEFT JOIN laporan_harian lh ON tm.id_material = lh.id_material
                WHERE pt.status = 'ongoing' AND pt.is_active = 1
                GROUP BY pt.id_target, pt.no_spk, mb.nama_barang, pt.nama_permintaan, pt.jumlah_unit, pt.created_at
                HAVING days_stalled > 1
                ORDER BY days_stalled DESC, last_report_time ASC";
        // UPDATE: Tambah Header No SPK
        $columns = ["ID Target", "No SPK", "Nama Barang", "Nama Permintaan", "Jumlah", "Terhenti (Hari)", "Input Terakhir"];
        break;

    default:
        echo '<div class="alert alert-danger">Tipe data tidak valid.</div>';
        exit;
}

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $targets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($targets)) {
        echo '<div class="alert alert-info m-3">Tidak ada data untuk ditampilkan.</div>';
        exit;
    }
    
    // Mulai output tabel
    echo '<div class="table-responsive px-3 py-3">';
    echo '<table id="targetsTable" class="table table-striped table-bordered table-hover" style="width:100%">';
    echo '<thead class="table-dark">';
    echo '<tr>';
    foreach ($columns as $col) {
        echo '<th>' . htmlspecialchars($col) . '</th>';
    }
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    
    foreach ($targets as $target) {
        print_table_row($target, $type);
    }
    
    echo '</tbody>';
    echo '</table>';
    echo '</div>';

} catch (PDOException $e) {
    echo '<div class="alert alert-danger m-3">Error: ' . $e->getMessage() . '</div>';
}
?>