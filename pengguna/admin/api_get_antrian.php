<?php
session_start();
// Path ini mungkin perlu disesuaikan jika struktur folder Anda berbeda
include_once '../../system/database_connection.php';
date_default_timezone_set('Asia/Jakarta');

// Validasi sesi
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo '<div class="alert alert-danger m-3">Akses ditolak. Silakan login kembali.</div>';
    exit;
}

// Validasi input
if (!isset($_GET['id_alur']) || !is_numeric($_GET['id_alur'])) {
    http_response_code(400);
    echo '<div class="alert alert-danger m-3">ID Alur tidak valid.</div>';
    exit;
}

$id_user = $_SESSION['user_id'];
$id_alur_current = (int)$_GET['id_alur'];

try {
    // 1. Periksa apakah admin ini benar-benar punya akses ke alur ini
    $stmt_check_access = $pdo->prepare("
        SELECT 1 FROM admin_tahapan_access 
        WHERE id_user = ? AND id_tahapan = ?
    ");
    $stmt_check_access->execute([$id_user, $id_alur_current]);
    if ($stmt_check_access->fetchColumn() === false) {
        http_response_code(403);
        echo '<div class="alert alert-danger m-3">Anda tidak memiliki akses ke alur ini.</div>';
        exit;
    }

    // 2. Dapatkan urutan alur saat ini
    $stmt_urutan = $pdo->prepare("SELECT urutan FROM master_alur WHERE id_alur = ?");
    $stmt_urutan->execute([$id_alur_current]);
    $urutan_current = $stmt_urutan->fetchColumn();

    if ($urutan_current === false) {
        echo '<div class="alert alert-danger m-3">Data alur tidak ditemukan.</div>';
        exit;
    }

    // 3. Dapatkan id_alur sebelumnya berdasarkan urutan
    $id_alur_previous = null;
    $urutan_previous = $urutan_current - 1;
    
    if ($urutan_previous > 0) {
        $stmt_prev_alur = $pdo->prepare("SELECT id_alur FROM master_alur WHERE urutan = ?");
        $stmt_prev_alur->execute([$urutan_previous]);
        $id_alur_previous = $stmt_prev_alur->fetchColumn();
    }

    // 4. Siapkan query utama untuk MENDAPATKAN DAFTAR ANTRIAN
    // Ini adalah adaptasi dari query COUNT di dashboard.php, diubah untuk mengambil data
    
    // Jika tidak ada alur sebelumnya (ini alur pertama)
    if ($id_alur_previous === false || $id_alur_previous === null) {
        $sql = "
            SELECT 
                pt.id_target, 
                mb.nama_barang, 
                pt.nama_permintaan,
                pt.tanggal_selesai,
                (CASE WHEN pt.prioritas = 'Prioritas' OR pt.is_priority = 1 THEN 1 ELSE 0 END) AS is_prioritas
            FROM target_alur_status tas_curr
            JOIN production_targets pt ON tas_curr.id_target = pt.id_target
            JOIN master_barang mb ON pt.id_barang = mb.id_barang
            WHERE tas_curr.id_alur = ? 
              AND tas_curr.status_pengerjaan = 'Pending'
              AND pt.status = 'ongoing' AND pt.is_active = 1
            ORDER BY is_prioritas DESC, pt.tanggal_selesai ASC 
        ";
        $params = [$id_alur_current];
    } else {
        // Jika ada alur sebelumnya
        $sql = "
            SELECT 
                pt.id_target, 
                mb.nama_barang, 
                pt.nama_permintaan,
                pt.tanggal_selesai,
                (CASE WHEN pt.prioritas = 'Prioritas' OR pt.is_priority = 1 THEN 1 ELSE 0 END) AS is_prioritas
            FROM target_alur_status tas_curr
            JOIN production_targets pt ON tas_curr.id_target = pt.id_target
            JOIN master_barang mb ON pt.id_barang = mb.id_barang
            -- Cek status alur sebelumnya
            LEFT JOIN target_alur_status tas_prev ON tas_curr.id_target = tas_prev.id_target AND tas_prev.id_alur = ? -- ID Alur Sebelumnya
            WHERE tas_curr.id_alur = ? -- ID Alur Saat Ini
              AND tas_curr.status_pengerjaan = 'Pending' 
              AND (tas_prev.id_alur IS NULL OR tas_prev.status_pengerjaan = 'Sedang Dikerjakan')
              AND pt.status = 'ongoing' AND pt.is_active = 1
            GROUP BY pt.id_target, mb.nama_barang, pt.nama_permintaan, pt.tanggal_selesai, is_prioritas
            ORDER BY is_prioritas DESC, pt.tanggal_selesai ASC
        ";
        $params = [$id_alur_previous, $id_alur_current];
    }

    $stmt_antrian_list = $pdo->prepare($sql);
    $stmt_antrian_list->execute($params);
    $antrian_list = $stmt_antrian_list->fetchAll(PDO::FETCH_ASSOC);

    // 5. Render HTML untuk modal
    if (empty($antrian_list)) {
        echo '<div class="alert alert-info m-3">Tidak ada item dalam antrian untuk lini produksi ini.</div>';
    } else {
        // Gunakan style CSS yang mirip dengan 'api_get_targets.php' untuk konsistensi
?>
<style>
    .table-container {
        padding: 0.5rem; /* Padding lebih kecil untuk modal ini */
        background: white;
    }
    .modal-table {
        border-collapse: separate;
        border-spacing: 0;
        width: 100%;
        margin: 0;
        background: white;
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(44, 62, 80, 0.08);
    }
    .modal-table thead {
        background: linear-gradient(135deg, #2c3e50, #34495e);
    }
    .modal-table thead th {
        color: black !important;
        font-weight: 700;
        font-size: 0.9rem;
        padding: 0.85rem 1rem;
        border: none;
        white-space: nowrap;
        text-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
    }
    .modal-table tbody tr {
        transition: all 0.2s ease;
        border-bottom: 1px solid #ecf0f1;
    }
    .modal-table tbody tr:last-child { border-bottom: none; }
    .modal-table tbody tr:hover {
        background: rgba(52, 152, 219, 0.04);
    }
    .modal-table tbody td {
        padding: 0.85rem 1rem;
        color: #2c3e50;
        font-size: 0.875rem;
        vertical-align: middle;
    }
    .badge-modern {
        padding: 0.45rem 0.85rem; border-radius: 6px; font-weight: 600;
        font-size: 0.8rem; display: inline-flex; align-items: center;
        gap: 0.35rem; white-space: nowrap;
    }
    .badge-danger-modern {
        background: linear-gradient(135deg, #e74c3c, #ec7063);
        color: white; box-shadow: 0 2px 8px rgba(231, 76, 60, 0.3);
    }
    .badge-secondary-modern {
        background: linear-gradient(135deg, #95a5a6, #bdc3c7);
        color: white; box-shadow: 0 2px 8px rgba(149, 165, 166, 0.3);
    }
    .btn-detail {
        padding: 0.5rem 1rem; border-radius: 6px; font-weight: 600;
        font-size: 0.85rem; transition: all 0.3s ease; display: inline-flex;
        align-items: center; gap: 0.4rem; text-decoration: none;
        border: none; white-space: nowrap;
    }
    .btn-detail-primary {
        background: linear-gradient(135deg, #3498db, #5dade2);
        color: white; box-shadow: 0 2px 8px rgba(52, 152, 219, 0.25);
    }
    .btn-detail-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(52, 152, 219, 0.35); color: white;
    }
</style>

<div class="table-container">
    <div class="table-responsive">
        <table id="antrianTable" class="modal-table table table-hover" style="width:100%">
            <thead>
                <tr>
                    <th>Prioritas</th>
                    <th>ID Target</th>
                    <th>Nama Barang</th>
                    <th>Permintaan</th>
                    <th>Deadline</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($antrian_list as $item): ?>
                    <tr>
                        <td>
                            <?php if ($item['is_prioritas']): ?>
                                <span class="badge badge-modern badge-danger-modern"><i class="fas fa-exclamation-triangle"></i> Prioritas</span>
                            <?php else: ?>
                                <span class="badge badge-modern badge-secondary-modern"><i class="fas fa-minus"></i> Normal</span>
                            <?php endif; ?>
                        </td>
                        <td><strong><?php echo htmlspecialchars($item['id_target']); ?></strong></td>
                        <td><?php echo htmlspecialchars($item['nama_barang']); ?></td>
                        <td><?php echo htmlspecialchars($item['nama_permintaan']); ?></td>
                        <td><?php echo !empty($item['tanggal_selesai']) ? date('d M Y', strtotime($item['tanggal_selesai'])) : '-'; ?></td>
                        <td>
                            <a href="alur_produksi.php?id_target=<?php echo $item['id_target']; ?>" class="btn btn-detail btn-detail-primary">
                                <i class="fas fa-eye"></i> Detail
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php
    }

} catch (PDOException $e) {
    http_response_code(500);
    // Gunakan style yang sama untuk pesan error
    echo '<div class="alert alert-danger m-3"><strong>Error API:</strong> ' . $e->getMessage() . '</div>';
}
?>