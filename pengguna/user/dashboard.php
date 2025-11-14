<?php
// Path ke header khusus user
include_once '../../templates/header_user.php'; 
include_once '../../system/database_connection.php';

date_default_timezone_set('Asia/Jakarta');

// Inisialisasi variabel data
$data_deadline = [];
$data_terakhir_input = [];
$data_terhenti = [];
$total_ongoing = 0;
$total_selesai = 0;
$total_prioritas = 0;
$limit_list = 5; // Kita tampilkan 5 list
$deadline_terdekat_display = "Tidak ada";

try {
    // ... [SEMUA QUERY PHP DARI LANGKAH SEBELUMNYA TETAP SAMA] ...
    // =================================================================
    // || QUERY KPI GLOBAL (TANPA FILTER HAK AKSES) ||
    // =================================================================

    // 1. Total Target On Going (Global Scope)
    $sql_ongoing = "SELECT COUNT(id_target) FROM production_targets 
                    WHERE status = 'ongoing' AND is_active = 1";
    $total_ongoing = $pdo->query($sql_ongoing)->fetchColumn();

    // 2. Total Target Selesai (Global Scope)
    $sql_selesai = "SELECT COUNT(id_target) FROM production_targets 
                    WHERE status = 'Selesai'";
    $total_selesai = $pdo->query($sql_selesai)->fetchColumn();

    // 3. Total Target Prioritas (Global Scope)
    $sql_prioritas = "SELECT COUNT(id_target) FROM production_targets 
                      WHERE (prioritas = 'Prioritas' OR is_priority = 1) 
                      AND status = 'ongoing' AND is_active = 1";
    $total_prioritas = $pdo->query($sql_prioritas)->fetchColumn();
    
    // 4. Deadline Prioritas Terdekat (Global Scope)
    if ($total_prioritas > 0) {
        $stmt_deadline_card = $pdo->prepare("
            SELECT priority_deadline 
            FROM production_targets 
            WHERE (prioritas = 'Prioritas' OR is_priority = 1) 
            AND status = 'ongoing' AND is_active = 1 
            AND priority_deadline IS NOT NULL
            ORDER BY priority_deadline ASC 
            LIMIT 1
        ");
        $stmt_deadline_card->execute();
        $deadline_terdekat = $stmt_deadline_card->fetchColumn();
        
        if ($deadline_terdekat) {
            $hari_ini = new DateTime('today');
            $tanggal_deadline = new DateTime(date('Y-m-d', strtotime($deadline_terdekat)));

            if ($hari_ini > $tanggal_deadline) {
                $selisih = $hari_ini->diff($tanggal_deadline);
                $hari_terlewat = $selisih->days;
                $deadline_terdekat_display = '<span style="color: #e74c3c; font-weight: bold;">Lewat ' . $hari_terlewat . ' hari</span>';
            } else {
                $deadline_terdekat_display = date('d M Y', strtotime($deadline_terdekat));
            }
        }
    }
    
    // =================================================================
    // || QUERY LIST GLOBAL (TANPA FILTER HAK AKSES) ||
    // =================================================================

    // 5. Query untuk Pop-up Deadline (Global)
    $sql_deadline = "
        SELECT pt.id_target, mb.nama_barang, pt.nama_permintaan, pt.tanggal_selesai,
               DATEDIFF(pt.tanggal_selesai, CURDATE()) AS sisa_hari
        FROM production_targets pt
        JOIN master_barang mb ON pt.id_barang = mb.id_barang
        WHERE pt.status = 'ongoing'
          AND pt.tanggal_selesai BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 15 DAY)
        ORDER BY sisa_hari ASC
    ";
    $stmt_deadline = $pdo->prepare($sql_deadline);
    $stmt_deadline->execute();
    $data_deadline = $stmt_deadline->fetchAll(PDO::FETCH_ASSOC);

    // 6. Query untuk "Terakhir Kali Diinput" (Global) - [DIUBAH]
    // Query ini diubah untuk hanya menampilkan 1 data unik per target berdasarkan input terakhir
    $sql_terakhir_input = "
        SELECT
            pt.id_target,
            mb.nama_barang,
            pt.nama_permintaan,
            lh.created_at,
            ma.nama_alur,
            lh.jumlah_selesai
        FROM (
            -- 1. Temukan id_laporan (PK) terbaru untuk setiap id_target
            SELECT 
                tm.id_target,
                MAX(lh.id_laporan) AS max_id_laporan
            FROM laporan_harian lh
            JOIN target_material tm ON lh.id_material = tm.id_material
            GROUP BY tm.id_target
        ) AS latest_reports
        -- 2. Join kembali untuk mendapatkan detail laporan terbaru itu
        JOIN laporan_harian lh ON lh.id_laporan = latest_reports.max_id_laporan
        -- 3. Join untuk mendapatkan detail target, barang, dan alur
        JOIN target_material tm ON lh.id_material = tm.id_material
        JOIN production_targets pt ON tm.id_target = pt.id_target
        JOIN master_barang mb ON pt.id_barang = mb.id_barang
        JOIN master_alur ma ON tm.id_alur = ma.id_alur
        -- 4. Urutkan berdasarkan waktu laporan terbaru
        ORDER BY lh.created_at DESC
        LIMIT ?
    ";
    $stmt_terakhir_input = $pdo->prepare($sql_terakhir_input);
    $stmt_terakhir_input->bindValue(1, $limit_list, PDO::PARAM_INT);
    $stmt_terakhir_input->execute();
    $data_terakhir_input = $stmt_terakhir_input->fetchAll(PDO::FETCH_ASSOC);

    // 7. Query untuk "Target Terhenti" (Global)
    $sql_terhenti = "
        SELECT
            pt.id_target,
            mb.nama_barang,
            pt.nama_permintaan,
            MAX(lh.created_at) AS last_report_time,
            DATEDIFF(NOW(), COALESCE(MAX(lh.created_at), pt.created_at)) as days_stalled
        FROM production_targets pt
        JOIN master_barang mb ON pt.id_barang = mb.id_barang
        LEFT JOIN target_material tm ON pt.id_target = tm.id_target
        LEFT JOIN laporan_harian lh ON tm.id_material = lh.id_material
        WHERE pt.status = 'ongoing' AND pt.is_active = 1
        GROUP BY pt.id_target, mb.nama_barang, pt.nama_permintaan, pt.created_at
        HAVING days_stalled > 1
        ORDER BY days_stalled DESC, last_report_time ASC
        LIMIT ?
    ";
    $stmt_terhenti = $pdo->prepare($sql_terhenti);
    $stmt_terhenti->bindValue(1, $limit_list, PDO::PARAM_INT);
    $stmt_terhenti->execute();
    $data_terhenti = $stmt_terhenti->fetchAll(PDO::FETCH_ASSOC);


} catch (PDOException $e) {
    echo '<div class="alert alert-danger">Error mengambil data dashboard: ' . $e->getMessage() . '</div>';
}
?>

<style>
:root {
    --primary-color: #2c3e50;
    --primary-light: #34495e;
    --accent-color: #3498db;
    --accent-light: #5dade2;
    --success-color: #27ae60;
    --success-light: #2ecc71;
    --warning-color: #f39c12;
    --warning-light: #f1c40f;
    --danger-color: #e74c3c;
    --danger-light: #ec7063;
    --bg-main: #f8f9fa;
    --bg-card: #ffffff;
    --text-primary: #2c3e50;
    --text-secondary: #7f8c8d;
    --text-muted: #95a5a6;
    --border-color: #ecf0f1;
    --shadow-sm: 0 2px 8px rgba(44, 62, 80, 0.08);
    --shadow-md: 0 4px 16px rgba(44, 62, 80, 0.12);
    --shadow-lg: 0 8px 24px rgba(44, 62, 80, 0.15);
    --border-radius: 12px;
    --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
body {
    background: var(--bg-main);
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    color: var(--text-primary);
}
.dashboard-header {
    background: var(--bg-card);
    padding: 2rem 2.5rem;
    margin-bottom: 2rem;
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-sm);
    position: relative;
    overflow: hidden;
}
.dashboard-header::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--primary-color), var(--accent-color));
}
.dashboard-title {
    font-size: 2rem;
    font-weight: 700;
    color: var(--primary-color);
    margin-bottom: 0.5rem;
}
.breadcrumb {
    background: none;
    padding: 0;
    margin: 0;
}
.breadcrumb-item.active {
    font-size: 1rem;
    color: var(--text-secondary);
    font-weight: 500;
}
.stats-wrapper {
    margin-bottom: 2rem;
}
.stats-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.5rem;
}
.stats-card {
    background: var(--bg-card);
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-sm);
    border: none;
    transition: var(--transition);
    position: relative;
    overflow: hidden;
    cursor: pointer;
    min-height: 180px;
}
.stats-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    transition: height 0.3s ease;
}
.stats-card.warning::before { background: var(--warning-color); }
.stats-card.success::before { background: var(--success-color); }
.stats-card.danger::before { background: var(--danger-color); }
.stats-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-lg);
}
.stats-card:hover::before { height: 6px; }
.stats-card .card-body {
    padding: 1.75rem;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    height: 100%;
}
.stats-icon-container {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    margin-bottom: 1rem;
}
.stats-icon {
    width: 56px;
    height: 56px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
    transition: var(--transition);
}
.stats-card.warning .stats-icon { background: var(--warning-color); }
.stats-card.success .stats-icon { background: var(--success-color); }
.stats-card.danger .stats-icon { background: var(--danger-color); }
.stats-card:hover .stats-icon { transform: scale(1.05); }
.stats-content {
    text-align: right;
    flex-grow: 1;
}
.stats-number {
    font-size: 2.5rem;
    font-weight: 700;
    line-height: 1;
    margin-bottom: 0.5rem;
    color: var(--text-primary);
}
.stats-label {
    font-size: 0.95rem;
    font-weight: 600;
    color: var(--text-secondary);
    margin-bottom: 0;
}
.stats-footer {
    background: rgba(248, 249, 250, 0.5);
    margin: 1rem -1.75rem -1.75rem -1.75rem;
    padding: 1rem 1.75rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 0.875rem;
    color: var(--text-secondary);
    font-weight: 600;
    border-top: 1px solid var(--border-color);
}
.stats-footer i {
    transition: transform 0.3s ease;
    color: var(--text-muted);
}
.stats-card:hover .stats-footer i {
    transform: translateX(4px);
    color: var(--accent-color);
}
.list-card {
    background: var(--bg-card);
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-sm);
    border: none;
    transition: var(--transition);
    margin-bottom: 2rem;
}
.list-card:hover { box-shadow: var(--shadow-md); }
.list-card .card-header {
    background: var(--bg-card);
    border-bottom: 2px solid var(--border-color);
    padding: 1.5rem 1.75rem;
    font-weight: 700;
    font-size: 1.1rem;
    color: var(--text-primary);
}
.list-card .card-header i {
    margin-right: 0.5rem;
    color: var(--accent-color);
}
.list-card .card-body { padding: 0; }
.card-body-placeholder {
    padding: 3rem 2rem;
    text-align: center;
    color: var(--text-muted);
    font-style: italic;
}
.list-group-flush .list-group-item {
    padding: 1.25rem 1.75rem;
    border-color: var(--border-color);
    background: transparent;
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: var(--transition);
}
.list-group-flush .list-group-item:hover { background: rgba(52, 152, 219, 0.03); }
.list-group-flush .list-group-item a {
    color: var(--primary-color);
    text-decoration: none;
    font-weight: 600;
    transition: var(--transition);
}
.list-group-flush .list-group-item a:hover { color: var(--accent-color); }
.list-group-flush .list-group-item .text-muted {
    font-size: 0.875rem;
    color: var(--text-muted);
    margin-top: 0.25rem;
}
.list-card .card-footer {
    background: rgba(248, 249, 250, 0.5);
    border-top: 1px solid var(--border-color);
    padding: 1rem 1.75rem;
}
.list-card .card-footer a {
    color: var(--accent-color);
    text-decoration: none;
    font-weight: 600;
    font-size: 0.9rem;
    transition: var(--transition);
}
.list-card .card-footer a:hover { color: var(--accent-light); }
.badge {
    padding: 0.45rem 0.85rem;
    border-radius: 6px;
    font-weight: 600;
    font-size: 0.8rem;
}
.badge.bg-light {
    background: rgba(52, 152, 219, 0.1) !important;
    color: var(--accent-color) !important;
}
.badge.bg-danger { background: var(--danger-color) !important; }
.modal-content {
    border: none;
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-lg);
}
.modal-header {
    background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
    color: white;
    border-bottom: none;
    padding: 1.5rem 2rem;
    border-radius: var(--border-radius) var(--border-radius) 0 0;
}
.modal-header.modal-header-warning { background: linear-gradient(135deg, var(--warning-color), var(--warning-light)); }
.modal-header.modal-header-danger { background: linear-gradient(135deg, var(--danger-color), var(--danger-light)); }
.modal-title {
    font-weight: 600;
    font-size: 1.25rem;
}
.btn-close {
    filter: brightness(0) invert(1);
    opacity: 0.8;
}
.btn-close:hover { opacity: 1; }
.modal-body {
    padding: 0;
    background: #fafbfc;
}
.modal-body .list-group-item {
    padding: 1rem 2rem;
    border-color: var(--border-color);
}
.modal-body .list-group-item:hover { background-color: rgba(52, 152, 219, 0.03); }
.modal-footer {
    background: white;
    border-top: 1px solid var(--border-color);
    padding: 1.5rem 2rem;
    border-radius: 0 0 var(--border-radius) var(--border-radius);
}
.back-to-top {
    position: fixed;
    bottom: 30px;
    right: 30px;
    width: 50px;
    height: 50px;
    background: linear-gradient(135deg, var(--accent-color), var(--accent-light));
    color: white;
    border: none;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    cursor: pointer;
    opacity: 0;
    visibility: hidden;
    transform: translateY(20px);
    transition: var(--transition);
    z-index: 1000;
    box-shadow: var(--shadow-md);
}
.back-to-top.show {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}
.back-to-top:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-lg);
}
.spinner-border {
    width: 3rem;
    height: 3rem;
    color: var(--accent-color);
}
@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(30px); }
    to { opacity: 1; transform: translateY(0); }
}
.animate-fade-in {
    animation: fadeInUp 0.6s ease forwards;
}
.stats-card {
    animation: fadeInUp 0.6s ease forwards;
    opacity: 0;
}
.stats-card:nth-child(1) { animation-delay: 0.1s; }
.stats-card:nth-child(2) { animation-delay: 0.2s; }
.stats-card:nth-child(3) { animation-delay: 0.3s; }
.list-card, .monitor-card {
    animation: fadeInUp 0.6s ease forwards;
    opacity: 0;
    animation-delay: 0.4s;
}
@media (max-width: 768px) {
    :root { --border-radius: 10px; }
    .container-fluid { padding-left: 0.75rem; padding-right: 0.75rem; }
    .dashboard-header { padding: 1.25rem; margin-bottom: 1.5rem; }
    .dashboard-title { font-size: 1.5rem; }
    .stats-wrapper { margin: 0 -0.75rem 1.5rem -0.75rem; padding: 0 0.75rem; }
    .stats-container { display: flex; overflow-x: auto; scroll-snap-type: x mandatory; gap: 1rem; padding-bottom: 0.5rem; }
    .stats-container::-webkit-scrollbar { height: 4px; }
    .stats-container::-webkit-scrollbar-thumb { background: var(--accent-color); }
    .stats-card { min-width: 280px; flex-shrink: 0; scroll-snap-align: start; min-height: 160px; }
    .stats-card .card-body { padding: 1.25rem; }
    .stats-icon { width: 48px; height: 48px; font-size: 1.3rem; }
    .stats-number { font-size: 2rem; }
    .stats-footer { padding: 0.875rem 1.25rem; margin: 0.75rem -1.25rem -1.25rem -1.25rem; }
    .list-card .card-header { padding: 1.25rem; font-size: 1rem; }
    .list-group-flush .list-group-item { padding: 1rem 1.25rem; flex-direction: column; align-items: flex-start; gap: 0.75rem; }
    .list-group-flush .list-group-item > div:last-child { text-align: left !important; }
    .list-card .card-footer { padding: 1rem 1.25rem; }
    .modal-dialog { margin: 0.5rem; max-width: calc(100% - 1rem); }
    .modal-header { padding: 1.25rem 1.5rem; }
    .modal-body .list-group-item { padding: 1rem 1.5rem; }
    .modal-footer { padding: 1rem 1.5rem; }
    .back-to-top { bottom: 20px; right: 20px; width: 45px; height: 45px; }
}
.ripple {
    position: absolute;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.4);
    transform: scale(0);
    animation: ripple-animation 0.6s ease-out;
    pointer-events: none;
}
@keyframes ripple-animation {
    to { transform: scale(4); opacity: 0; }
}
</style>

<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">

<div class="container-fluid px-4">
    <div class="dashboard-header animate-fade-in">
        <h1 class="dashboard-title">Dashboard User</h1>
        <ol class="breadcrumb">
            <li class="breadcrumb-item active">
                <i class="fas fa-hand-wave me-2"></i>
                <span>Selamat datang, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</span>
            </li>
        </ol>
    </div>

    <div class="stats-wrapper">
        <div class="stats-container">
            <div class="stats-card warning card-clickable" data-type="ongoing" data-bs-toggle="modal" data-bs-target="#targetsModal">
                <div class="card-body">
                    <div class="stats-icon-container">
                        <div class="stats-icon">
                            <i class="fas fa-tasks"></i>
                        </div>
                        <div class="stats-content">
                            <div class="stats-number"><?php echo $total_ongoing; ?></div>
                            <div class="stats-label">Target On Going</div>
                        </div>
                    </div>
                </div>
                <div class="stats-footer">
                    <span>Lihat Detail</span> <i class="fas fa-arrow-right"></i>
                </div>
            </div>

            <div class="stats-card success card-clickable" data-type="selesai" data-bs-toggle="modal" data-bs-target="#targetsModal">
                <div class="card-body">
                    <div class="stats-icon-container">
                        <div class="stats-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stats-content">
                            <div class="stats-number"><?php echo $total_selesai; ?></div>
                            <div class="stats-label">Total Target Selesai</div>
                        </div>
                    </div>
                </div>
                <div class="stats-footer">
                    <span>Lihat Detail</span> <i class="fas fa-arrow-right"></i>
                </div>
            </div>

            <div class="stats-card danger card-clickable" data-type="prioritas" data-bs-toggle="modal" data-bs-target="#targetsModal">
                <div class="card-body">
                    <div class="stats-icon-container">
                        <div class="stats-icon">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div class="stats-content">
                            <div class="stats-number"><?php echo $total_prioritas; ?></div>
                            <div class="stats-label">Total Target Prioritas</div>
                        </div>
                    </div>
                </div>
                <div class="stats-footer">
                    <span>Batas Waktu: <?php echo $deadline_terdekat_display; ?></span>
                    <i class="fas fa-arrow-right"></i>
                </div>
                </div>
        </div>
    </div>

    <div class="row g-4 mt-2">
        <div class="col-lg-6">
            <div class="card list-card">
                <div class="card-header">
                    <i class="fas fa-history"></i>
                    Terakhir Kali Diinput (Global)
                </div>
                <div class="card-body p-0">
                    <?php if (empty($data_terakhir_input)): ?>
                        <div class="card-body-placeholder">
                            <i class="fas fa-inbox fa-2x mb-3" style="color: var(--text-muted);"></i>
                            <p>Belum ada data input terbaru.</p>
                        </div>
                    <?php else: ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($data_terakhir_input as $item): ?>
                                <li class="list-group-item">
                                    <div>
                                        <a href="management_produksi/material.php?id_target=<?php echo $item['id_target']; ?>">
                                            <?php echo htmlspecialchars($item['nama_barang']); ?>
                                        </a>
                                        <div class="text-muted">
                                            <?php echo htmlspecialchars($item['nama_permintaan']); ?>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-light shadow-sm">
                                            <?php echo htmlspecialchars($item['nama_alur']); ?>
                                        </span>
                                        <div class="text-muted mt-1">
                                            <?php echo date('d M, H:i', strtotime($item['created_at'])); ?>
                                        </div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
                <div class="card-footer">
                    <a href="#" class="card-clickable" data-type="terakhir_input" data-bs-toggle="modal" data-bs-target="#targetsModal">
                        Lihat Semua Input
                        <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card list-card">
                <div class="card-header">
                    <i class="fas fa-pause-circle text-danger"></i>
                    Target Terhenti (> 1 Hari)
                </div>
                <div class="card-body p-0">
                    <?php if (empty($data_terhenti)): ?>
                        <div class="card-body-placeholder">
                            <i class="fas fa-check-circle fa-2x mb-3" style="color: var(--success-color);"></i>
                            <p>Tidak ada target yang terhenti.</p>
                        </div>
                    <?php else: ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($data_terhenti as $item): ?>
                                <li class="list-group-item">
                                    <div>
                                        <a href="management_produksi/material.php?id_target=<?php echo $item['id_target']; ?>">
                                            <?php echo htmlspecialchars($item['nama_barang']); ?>
                                        </a>
                                        <div class="text-muted">
                                            <?php echo htmlspecialchars($item['nama_permintaan']); ?>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-danger shadow-sm">
                                            <?php echo $item['days_stalled']; ?> Hari Terhenti
                                        </span>
                                        <div class="text-muted mt-1">
                                            Input terakhir: <?php echo $item['last_report_time'] ? date('d M Y', strtotime($item['last_report_time'])) : 'Belum ada Progress'; ?>
                                        </div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
                <div class="card-footer">
                    <a href="#" class="card-clickable" data-type="terhenti" data-bs-toggle="modal" data-bs-target="#targetsModal">
                        Lihat Semua Target Terhenti
                        <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="targetsModal" tabindex="-1" aria-labelledby="targetsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="targetsModalLabel">Daftar Target Produksi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="text-center p-5">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-3 text-muted">Memuat data...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Tutup
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="deadlineModal" tabindex="-1" aria-labelledby="deadlineModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header modal-header-danger">
                <h5 class="modal-title" id="deadlineModalLabel">
                    <i class="fas fa-bell me-2"></i>Peringatan: Target Mendekati Deadline!
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="deadlineModalBody">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Tutup
                </button>
            </div>
        </div>
    </div>
</div>

<button class="back-to-top" id="backToTop" aria-label="Kembali ke atas">
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="1.4rem" height="1.4rem" fill="currentColor">
        <path d="M0 0h24v24H0V0z" fill="none"/>
        <path d="M4 12l1.41 1.41L11 7.83V20h2V7.83l5.59 5.58L20 12l-8-8-8 8z"/>
    </svg>
</button>

<?php
// Path ke footer
include_once '../../templates/footer.php'; 
?>

<script>
    const deadlineTargetsData = <?php echo json_encode($data_deadline); ?>;
</script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // ... [Handler Card Click Tetap Sama] ...
    const cards = document.querySelectorAll('.card-clickable');
    const modalBody = document.querySelector('#targetsModal .modal-body');
    const modalTitle = document.querySelector('#targetsModalLabel');
    const modalHeader = document.querySelector('#targetsModal .modal-header');

    cards.forEach(card => {
        card.addEventListener('click', function() {
            const type = this.getAttribute('data-type');
            
            modalHeader.classList.remove('modal-header-danger', 'modal-header-warning');

            let titleHtml = '';
            if(type === 'ongoing') {
                titleHtml = '<i class="fas fa-tasks me-2"></i>Daftar Target Produksi Berjalan (Global)';
                modalHeader.classList.add('modal-header-warning');
            } else if(type === 'selesai') {
                titleHtml = '<i class="fas fa-check-circle me-2"></i>Daftar Target Produksi Selesai (Global)';
            } else if(type === 'prioritas') {
                titleHtml = '<i class="fas fa-exclamation-triangle me-2"></i>Daftar Target Produksi Prioritas (Global)';
                modalHeader.classList.add('modal-header-danger');
            } else if(type === 'terakhir_input') {
                titleHtml = '<i class="fas fa-history me-2"></i>Semua Riwayat Input Terakhir';
            } else if(type === 'terhenti') {
                titleHtml = '<i class="fas fa-pause-circle me-2"></i>Semua Target Terhenti';
                modalHeader.classList.add('modal-header-danger');
            }
            modalTitle.innerHTML = titleHtml;

            modalBody.innerHTML = `
                <div class="text-center p-5">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-3 text-muted">Memuat data...</p>
                </div>
            `;

            // Fetch table data dari API baru
            fetch(`api_get_targets.php?type=${type}`) // API ini akan kita buat di Langkah 2
                .then(response => response.text())
                .then(data => {
                    modalBody.innerHTML = data;
                    if (window.jQuery && window.jQuery.fn.DataTable) {
                        $('#targetsTable').DataTable({
                            responsive: true,
                            order: [[0, 'desc']],
                            "language": {
                                "url": "https://cdn.datatables.net/plug-ins/1.10.25/i18n/Indonesian.json"
                            }
                        });
                    }
                })
                .catch(error => {
                    modalBody.innerHTML = `
                        <div class="alert alert-danger d-flex align-items-center m-3">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <div>Gagal memuat data. Pastikan file 'api_get_targets.php' ada di folder 'user'.</div>
                        </div>
                    `;
                    console.error('Error:', error);
                });
        });
    });

    // Logika Pop-up Deadline
    function checkDeadlineTargets() {
        if (!deadlineTargetsData || deadlineTargetsData.length === 0) {
            return;
        }

        const deadlineModal = new bootstrap.Modal(document.getElementById('deadlineModal'));
        const deadlineModalBody = document.getElementById('deadlineModalBody');
        
        let listHtml = '<ul class="list-group list-group-flush">';
        deadlineTargetsData.forEach(target => {
            let badgeClass = 'bg-danger';
            let sisaHariText = `${target.sisa_hari} hari lagi`;
            if (target.sisa_hari == 0) {
                sisaHariText = 'Hari Ini!';
            } else if (target.sisa_hari > 3) {
                badgeClass = 'bg-warning text-dark';
            }
            
            listHtml += `
                <li class="list-group-item d-flex justify-content-between align-items-center position-relative">
                    <div>
                        <strong>${target.nama_barang}</strong> (${target.nama_permintaan})
                        <br>
                        <small class="text-muted">
                            ID: ${target.id_target} | 
                            Deadline: ${new Date(target.tanggal_selesai).toLocaleDateString('id-ID', {
                                day: '2-digit', month: 'long', year: 'numeric'
                            })}
                        </small>
                    </div>
                    <div class="text-end">
                        <span class="badge ${badgeClass} fs-6 shadow-sm">${sisaHariText}</span>
                        <a href="management_produksi/material.php?id_target=${target.id_target}" class="btn btn-sm btn-outline-primary ms-2">
                            <i class="fas fa-eye me-1"></i> Lihat
                        </a>
                    </div>
                </li>
            `;
        });
        listHtml += '</ul>';
        deadlineModalBody.innerHTML = listHtml;
        deadlineModal.show();
    }
    
    // Panggil fungsi deadline
    checkDeadlineTargets();

    // ... [Efek Ripple dan Back-to-Top Tetap Sama] ...
    // Efek Ripple pada Card
    const statsCards = document.querySelectorAll('.stats-card');
    statsCards.forEach(card => {
        card.addEventListener('click', function(e) {
            const ripple = document.createElement('span');
            ripple.classList.add('ripple');
            const rect = this.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            const x = e.clientX - rect.left - size / 2;
            const y = e.clientY - rect.top - size / 2;
            ripple.style.width = ripple.style.height = size + 'px';
            ripple.style.left = x + 'px';
            ripple.style.top = y + 'px';
            this.appendChild(ripple);
            setTimeout(() => ripple.remove(), 600);
        });
    });

    // Tombol Back to Top
    const backToTopBtn = document.getElementById('backToTop');
    window.addEventListener('scroll', function() {
        if (window.pageYOffset > 300) {
            backToTopBtn.classList.add('show');
        } else {
            backToTopBtn.classList.remove('show');
        }
    });
    backToTopBtn.addEventListener('click', function() {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });

})();
</script>