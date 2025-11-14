<?php
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'superadmin') {
    header("location: ../../auth/login.php");
    exit;
}

$page_title = 'Dashboard Superadmin';
include '../../templates/header_superadmin.php';
include '../../system/database_connection.php';

try {
    // Data Inisial (statis, tidak berubah oleh filter)
    $ongoing_targets = $pdo->query("SELECT COUNT(*) FROM production_targets WHERE status = 'ongoing'")->fetchColumn();
    $total_items = $pdo->query("SELECT COUNT(*) FROM master_barang")->fetchColumn();
    
    // Data Inisial (dinamis, akan diupdate oleh filter, default 'Semua Waktu')
    $initial_priority_targets = $pdo->query("SELECT COUNT(*) FROM production_targets WHERE status = 'ongoing' AND is_active = 1 AND is_priority = 1")->fetchColumn();
    $initial_completed_reports = $pdo->query("SELECT COUNT(*) FROM production_targets WHERE status = 'Selesai'")->fetchColumn();
    
    // 2. Daftar Target Prioritas (untuk list di bawah)
    $priority_list_stmt = $pdo->query("
    SELECT pt.id_target, pt.id_barang, pt.nama_permintaan, mb.nama_barang, pt.priority_deadline
    FROM production_targets pt
    JOIN master_barang mb ON pt.id_barang = mb.id_barang
    WHERE pt.status = 'ongoing' AND pt.is_active = 1 AND pt.is_priority = 1
    ORDER BY pt.priority_deadline ASC
    LIMIT 5
");
    $priority_list = $priority_list_stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Data untuk Grafik Status Target (default 'Semua Waktu')
    $status_distribution_stmt = $pdo->query("
        SELECT status, COUNT(*) as count 
        FROM production_targets 
        GROUP BY status
    ");
    $status_data = $status_distribution_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $chart_labels = [];
    $chart_values = [];
    foreach ($status_data as $data) {
        $chart_labels[] = ucfirst($data['status']);
        $chart_values[] = $data['count'];
    }

    // 4. Query untuk Target Menunggu Verifikasi
    $pending_verification_stmt = $pdo->query("
        SELECT pt.id_target, pt.id_barang, pt.nama_permintaan, mb.nama_barang
        FROM production_targets pt
        JOIN master_barang mb ON pt.id_barang = mb.id_barang
        WHERE pt.status = 'ongoing'
        AND (
            SELECT COUNT(tm.id_material)
            FROM target_material tm
            JOIN alur_barang ab ON tm.id_alur = ab.id_alur AND ab.id_barang = pt.id_barang
            WHERE tm.id_target = pt.id_target
        ) > 0
        AND (
            SELECT COUNT(tm.id_material)
            FROM target_material tm
            JOIN alur_barang ab ON tm.id_alur = ab.id_alur AND ab.id_barang = pt.id_barang
            LEFT JOIN (
                SELECT id_material, SUM(jumlah_selesai) as total_selesai
                FROM laporan_harian
                GROUP BY id_material
            ) lh ON tm.id_material = lh.id_material
            WHERE tm.id_target = pt.id_target
            AND (tm.jumlah_per_unit * pt.jumlah_unit) > COALESCE(lh.total_selesai, 0)
        ) = 0
        LIMIT 5
    ");
    $pending_verification_list = $pending_verification_stmt->fetchAll(PDO::FETCH_ASSOC);

    // 5. Query untuk Target Terhenti
    $stalled_targets_stmt = $pdo->query("
        SELECT * FROM (
            SELECT
                pt.id_target,
                pt.id_barang,
                pt.nama_permintaan,
                mb.nama_barang,
                MAX(lh.tanggal_laporan) AS last_report
            FROM
                production_targets pt
            JOIN master_barang mb ON pt.id_barang = mb.id_barang
            LEFT JOIN target_material tm ON pt.id_target = tm.id_target
            LEFT JOIN laporan_harian lh ON tm.id_material = lh.id_material
            WHERE
                pt.status = 'ongoing'
            GROUP BY
                pt.id_target, pt.id_barang, pt.nama_permintaan, mb.nama_barang
        ) AS sub
        WHERE sub.last_report < CURDATE() - INTERVAL 3 DAY OR sub.last_report IS NULL
        ORDER BY sub.last_report ASC
        LIMIT 5
    ");
    $stalled_targets_list = $stalled_targets_stmt->fetchAll(PDO::FETCH_ASSOC);

    // 6. Query untuk Target Nonaktif (Arsip)
    $archived_targets_stmt = $pdo->query("
        SELECT pt.id_target, pt.id_barang, pt.nama_permintaan, mb.nama_barang, pt.alasan_nonaktif, pt.created_at
        FROM production_targets pt
        JOIN master_barang mb ON pt.id_barang = mb.id_barang
        WHERE pt.is_active = 0
        ORDER BY pt.created_at DESC
        LIMIT 5
    ");
    $archived_targets_list = $archived_targets_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error saat mengambil data dashboard: " . $e->getMessage());
}
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>


<style>
/* Modern Dashboard Styles (Existing styles from your old code) */
:root {
    --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --success-gradient: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    --warning-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    --info-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    --danger-gradient: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
    --grey-gradient: linear-gradient(135deg, #868f96 0%, #596164 100%);
    
    --card-shadow: 0 10px 30px rgba(0,0,0,0.1);
    --card-shadow-hover: 0 20px 60px rgba(0,0,0,0.15);
    --border-radius: 16px;
    --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

body {
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
}

.container-fluid {
    padding: 2rem;
}

/* Modern Welcome Header */
.welcome-header {
    background: var(--primary-gradient);
    border-radius: var(--border-radius);
    padding: 2rem;
    color: white;
    margin-bottom: 2rem;
    box-shadow: var(--card-shadow);
    position: relative;
    overflow: hidden;
}

.welcome-header::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="50" cy="50" r="1" fill="rgba(255,255,255,0.1)"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
    opacity: 0.3;
}

.welcome-header h1 {
    font-size: 2.5rem;
    font-weight: 700;
    margin: 0;
    position: relative;
    z-index: 1;
}

.welcome-header p {
    font-size: 1.1rem;
    margin: 0.5rem 0 0 0;
    opacity: 0.9;
    position: relative;
    z-index: 1;
}

/* CSS for FILTERS */
.filter-container {
    background-color: #fff;
    padding: 1rem 1.5rem;
    border-radius: var(--border-radius);
    box-shadow: var(--card-shadow);
    margin-bottom: 2rem;
    display: flex;
    align-items: center;
    gap: 1.5rem;
}

.filter-label {
    font-weight: 600;
    color: #4a5568;
    margin: 0;
}

.filter-buttons .btn {
    border-radius: 20px;
    font-weight: 500;
    padding: 0.5rem 1.2rem;
    transition: all 0.2s ease-in-out;
    border: 1px solid #e2e8f0;
    background-color: #f8fafc;
    color: #475569;
}

.filter-buttons .btn.active,
.filter-buttons .btn:hover {
    background: var(--primary-gradient);
    color: white;
    border-color: transparent;
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
}

/* Modern Stats Cards */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.modern-card {
    background: white;
    border-radius: var(--border-radius);
    padding: 2rem;
    box-shadow: var(--card-shadow);
    transition: var(--transition);
    position: relative;
    overflow: hidden;
    border: none;
    cursor: pointer;
}

.modern-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--card-shadow-hover);
}

.modern-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 4px;
    background: var(--card-gradient, var(--primary-gradient));
}

.modern-card.primary::before { background: var(--primary-gradient); }
.modern-card.success::before { background: var(--success-gradient); }
.modern-card.warning::before { background: var(--warning-gradient); }
.modern-card.info::before { background: var(--info-gradient); }

.card-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.card-info h3 {
    font-size: 2.5rem;
    font-weight: 700;
    margin: 0;
    color: #2d3748;
    line-height: 1;
}

.card-info p {
    font-size: 0.9rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #718096;
    margin: 0.5rem 0 0 0;
}

.card-icon {
    width: 60px;
    height: 60px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--card-gradient, var(--primary-gradient));
    color: white;
    font-size: 1.8rem;
    box-shadow: 0 8px 20px rgba(0,0,0,0.15);
}

.card-icon.primary { background: var(--primary-gradient); }
.card-icon.success { background: var(--success-gradient); }
.card-icon.warning { background: var(--warning-gradient); }
.card-icon.info { background: var(--info-gradient); }

.content-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 2rem;
    align-items: start;
}
.content-card {
    background: white;
    border-radius: var(--border-radius);
    box-shadow: var(--card-shadow);
    transition: var(--transition);
    overflow: hidden;
    margin-bottom: 2rem;
}
.content-card:hover {
    box-shadow: var(--card-shadow-hover);
}
.card-header-modern {
    padding: 1.5rem 2rem;
    background: linear-gradient(135deg, #f8f9ff 0%, #f1f4ff 100%);
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.card-header-modern h3 {
    font-size: 1.2rem;
    font-weight: 600;
    margin: 0;
    color: #2d3748;
}
.view-all-btn {
    background: var(--primary-gradient);
    border: none;
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    font-size: 0.85rem;
    font-weight: 500;
    transition: var(--transition);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}
.view-all-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
    color: white;
}
.card-body-modern {
    padding: 0;
}
.modern-list {
    list-style: none;
    padding: 0;
    margin: 0;
}
.modern-list-item {
    padding: 1.5rem 2rem;
    border-bottom: 1px solid #f1f5f9;
    transition: var(--transition);
    text-decoration: none;
    color: inherit;
    display: block;
    position: relative;
}
.modern-list-item:hover {
    background: linear-gradient(135deg, #f8faff 0%, #f0f4ff 100%);
    color: inherit;
    text-decoration: none;
}
.modern-list-item::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 4px;
    background: var(--item-color, var(--primary-gradient));
    transform: scaleX(0);
    transition: var(--transition);
    transform-origin: left;
}
.modern-list-item:hover::before {
    transform: scaleX(1);
}
.modern-list-item.priority::before { background: var(--warning-gradient); }
.modern-list-item.success::before { background: var(--success-gradient); }
.modern-list-item.warning::before { background: var(--danger-gradient); }
.modern-list-item.archived::before { background: var(--grey-gradient); }
.list-item-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 0.5rem;
}
.list-item-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: #2d3748;
    margin: 0;
    line-height: 1.3;
}
.list-item-date {
    font-size: 0.85rem;
    color: #718096;
    font-weight: 500;
}
.list-item-description {
    font-size: 0.95rem;
    color: #4a5568;
    margin: 0;
    line-height: 1.4;
}
.list-item-description strong {
    color: #2d3748;
}
.priority-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: linear-gradient(135deg, #fef7e0 0%, #fde68a 100%);
    color: #92400e;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.empty-state {
    text-align: center;
    padding: 3rem 2rem;
    color: #718096;
}
.empty-state-icon {
    font-size: 3rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}
.empty-state-text {
    font-size: 1.1rem;
    font-weight: 500;
    margin: 0;
}
.chart-card {
    background: white;
    border-radius: var(--border-radius);
    box-shadow: var(--card-shadow);
    transition: var(--transition);
    overflow: hidden;
    height: fit-content;
}
.chart-card:hover {
    box-shadow: var(--card-shadow-hover);
}
.chart-container {
    padding: 2rem;
    position: relative;
    height: 400px;
}
.modal-content {
    border-radius: var(--border-radius);
    border: none;
    box-shadow: 0 25px 50px rgba(0,0,0,0.15);
}
.modal-header {
    background: var(--primary-gradient);
    color: white;
    border-bottom: none;
    border-radius: var(--border-radius) var(--border-radius) 0 0;
    padding: 1.5rem 2rem;
}
.modal-title {
    font-weight: 600;
}

.modal-header .btn-close {
    filter: invert(1) grayscale(100%) brightness(200%);
}

.modal-body {
    padding: 0;
}
.modal-body-custom-padding {
    padding: 1.5rem 2rem;
}

/* === KODE UNTUK BACK TO TOP === */
.back-to-top {
    position: fixed;
    bottom: 30px;
    right: 30px;
    width: 50px;
    height: 50px;
    background: var(--primary-gradient);
    color: white;
    border: none;
    border-radius: 50%;
    display: none;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    cursor: pointer;
    box-shadow: 0 4px 20px rgba(102, 126, 234, 0.4);
    transition: all 0.3s ease;
    z-index: 1000;
}

.back-to-top.show {
    display: flex;
}

.back-to-top:hover {
    transform: scale(1.1);
    box-shadow: 0 6px 25px rgba(102, 126, 234, 0.6);
}
/* === AKHIR KODE BACK TO TOP === */

@media (max-width: 768px) {
    .container-fluid {
        padding: 1rem;
    }
    .content-grid {
        grid-template-columns: 1fr;
    }
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 1rem;
    }
    .welcome-header {
        padding: 1.5rem;
    }
    .welcome-header h1 {
        font-size: 2rem;
    }
    .card-content {
        flex-direction: column;
        text-align: center;
        gap: 1rem;
    }
    .chart-container {
        height: 300px;
        padding: 1rem;
    }
}
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
.animate-fade-in {
    animation: fadeInUp 0.6s ease-out;
}
.loading-spinner {
    display: inline-block;
    width: 2rem;
    height: 2rem;
    border: 3px solid #f3f3f3;
    border-top: 3px solid var(--primary-gradient);
    border-radius: 50%;
    animation: spin 1s linear infinite;
}
@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
</style>

<div class="container-fluid">
    <div class="welcome-header animate-fade-in">
        <h1>Dashboard Superadmin</h1>
        <p>Selamat datang kembali! Kelola dan pantau seluruh aktivitas produksi dengan mudah.</p>
    </div>

    
    <div class="stats-grid animate-fade-in">
        <div class="modern-card primary" id="card-target-berjalan">
            <div class="card-content">
                <div class="card-info">
                    <h3 id="ongoing-targets-stat"><?php echo $ongoing_targets; ?></h3>
                    <p>Target Berjalan (Saat Ini)</p>
                </div>
                <div class="card-icon primary">
                    <i class="bi bi-play-circle-fill"></i>
                </div>
            </div>
        </div>
        
        <div class="modern-card warning" id="card-target-prioritas">
            <div class="card-content">
                <div class="card-info">
                    <h3 id="priority-targets-stat"><?php echo $initial_priority_targets; ?></h3>
                    <p>Target Prioritas</p>
                </div>
                <div class="card-icon warning">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                </div>
            </div>
        </div>
        
        <div class="modern-card success" id="card-laporan-selesai">
            <div class="card-content">
                <div class="card-info">
                     <h3 id="completed-reports-stat"><?php echo $initial_completed_reports; ?></h3>
                    <p>Laporan Selesai</p>
                </div>
                <div class="card-icon success">
                    <i class="bi bi-check-circle-fill"></i>
                </div>
            </div>
        </div>
        
        <div class="modern-card info" id="card-master-barang">
            <div class="card-content">
                <div class="card-info">
                     <h3 id="total-items-stat"><?php echo $total_items; ?></h3>
                    <p>Total Master Barang</p>
                </div>
                <div class="card-icon info">
                    <i class="bi bi-box-seam-fill"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="content-grid animate-fade-in">
        <div class="content-column">
            <div class="content-card">
                <div class="card-header-modern">
                    <h3><i class="bi bi-star-fill" style="color: #f59e0b; margin-right: 0.5rem;"></i>Target Prioritas Utama</h3>
                    <button type="button" class="view-all-btn lihat-semua-btn" 
                            data-bs-toggle="modal" data-bs-target="#lihatSemuaModal" 
                            data-type="priority" data-title="Semua Target Prioritas">
                        Lihat Semua <i class="bi bi-arrow-right"></i>
                    </button>
                </div>
                <div class="card-body-modern">
                    <?php if (empty($priority_list)): ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">🎯</div>
                            <p class="empty-state-text">Tidak ada target prioritas yang sedang berjalan</p>
                        </div>
                    <?php else: ?>
                        <div class="modern-list">
                            <?php foreach ($priority_list as $item): ?>
                                <a href="manajemen_produksi/detail_barang.php?id=<?php echo $item['id_barang']; ?>" class="modern-list-item priority">
                                    <div class="list-item-header">
                                        <h4 class="list-item-title"><?php echo htmlspecialchars($item['nama_permintaan']); ?></h4>
                                        <span class="list-item-date">Tenggat: <?php echo date('d M Y', strtotime($item['priority_deadline'])); ?></span>
                                    </div>
                                    <p class="list-item-description">Untuk barang: <strong><?php echo htmlspecialchars($item['nama_barang']); ?></strong></p>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="content-card">
                <div class="card-header-modern">
                    <h3><i class="bi bi-check2-circle" style="color: #10b981; margin-right: 0.5rem;"></i>Target Menunggu Verifikasi</h3>
                    <button type="button" class="view-all-btn lihat-semua-btn" 
                            data-bs-toggle="modal" data-bs-target="#lihatSemuaModal" 
                            data-type="pending" data-title="Semua Target Menunggu Verifikasi">
                        Lihat Semua <i class="bi bi-arrow-right"></i>
                    </button>
                </div>
                <div class="card-body-modern">
                    <?php if (empty($pending_verification_list)): ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">✅</div>
                            <p class="empty-state-text">Saat ini tidak ada target yang menunggu untuk diselesaikan</p>
                        </div>
                    <?php else: ?>
                        <div class="modern-list">
                            <?php foreach ($pending_verification_list as $item): ?>
                                <a href="manajemen_produksi/detail_barang.php?id=<?php echo $item['id_barang']; ?>" class="modern-list-item success">
                                    <div class="list-item-header">
                                        <h4 class="list-item-title"><?php echo htmlspecialchars($item['nama_permintaan']); ?></h4>
                                        <span class="priority-badge">Ready</span>
                                    </div>
                                    <p class="list-item-description">Semua komponen untuk <strong><?php echo htmlspecialchars($item['nama_barang']); ?></strong> telah terpenuhi. Klik untuk verifikasi.</p>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="content-card">
                <div class="card-header-modern">
                    <h3><i class="bi bi-clock-history" style="color: #ef4444; margin-right: 0.5rem;"></i>Target Terhenti (> 3 Hari)</h3>
                    <button type="button" class="view-all-btn lihat-semua-btn" 
                            data-bs-toggle="modal" data-bs-target="#lihatSemuaModal" 
                            data-type="stalled" data-title="Semua Target Terhenti">
                        Lihat Semua <i class="bi bi-arrow-right"></i>
                    </button>
                </div>
                <div class="card-body-modern">
                    <?php if (empty($stalled_targets_list)): ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">⚡</div>
                            <p class="empty-state-text">Semua target berjalan memiliki progress baru</p>
                        </div>
                    <?php else: ?>
                        <div class="modern-list">
                            <?php foreach ($stalled_targets_list as $item): ?>
                                <a href="manajemen_produksi/detail_barang.php?id=<?php echo $item['id_barang']; ?>" class="modern-list-item warning">
                                    <div class="list-item-header">
                                        <h4 class="list-item-title"><?php echo htmlspecialchars($item['nama_permintaan']); ?></h4>
                                        <span class="list-item-date">
                                            <?php if ($item['last_report']): ?>
                                                Laporan terakhir: <?php echo date('d M Y', strtotime($item['last_report'])); ?>
                                            <?php else: ?>
                                                Belum ada laporan
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    <p class="list-item-description">Target untuk <strong><?php echo htmlspecialchars($item['nama_barang']); ?></strong> perlu diperiksa.</p>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
             <div class="content-card">
                <div class="card-header-modern">
                    <h3><i class="bi bi-archive-fill" style="color: #6b7280; margin-right: 0.5rem;"></i>Arsip Target (Nonaktif)</h3>
                    <button type="button" class="view-all-btn lihat-semua-btn"
                            data-bs-toggle="modal" data-bs-target="#lihatSemuaModal"
                            data-type="archived" data-title="Semua Target yang Diarsipkan">
                        Lihat Semua <i class="bi bi-arrow-right"></i>
                    </button>
                </div>
                <div class="card-body-modern">
                    <?php if (empty($archived_targets_list)): ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">🗄️</div>
                            <p class="empty-state-text">Belum ada target yang diarsipkan</p>
                        </div>
                    <?php else: ?>
                        <div class="modern-list">
                            <?php foreach ($archived_targets_list as $item): ?>
                                <a href="manajemen_produksi/detail_barang.php?id=<?php echo $item['id_barang']; ?>" class="modern-list-item archived">
                                    <div class="list-item-header">
                                        <h4 class="list-item-title"><?php echo htmlspecialchars($item['nama_permintaan']); ?></h4>
                                    </div>
                                    <p class="list-item-description">
                                        Barang: <strong><?php echo htmlspecialchars($item['nama_barang']); ?></strong><br>
                                        <small>Alasan: <?php echo htmlspecialchars($item['alasan_nonaktif'] ?: 'Tidak ada alasan'); ?></small>
                                    </p>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="content-column">
            <div class="chart-card">
                <div class="card-header-modern">
                    <h3><i class="bi bi-pie-chart-fill" style="color: #6366f1; margin-right: 0.5rem;"></i>Distribusi Status Target</h3>
                </div>
                <div class="chart-container">
                    <canvas id="statusPieChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="targetListModal" tabindex="-1" aria-labelledby="targetListModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="targetListModalLabel">Daftar Target</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body modal-body-custom-padding">
        <p class="text-center" id="modal-loading">
            <i class="fas fa-spinner fa-spin"></i> Memuat data...
        </p>
        <div class="table-responsive" id="modal-table-container" style="display: none;">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>ID Target</th>
                        <th>Nama Barang</th>
                        <th>Jumlah</th>
                        <th>Status</th>
                        <th>Prioritas</th>
                    </tr>
                </thead>
                <tbody id="target-list-tbody">
                    </tbody>
            </table>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="lihatSemuaModal" tabindex="-1" aria-labelledby="lihatSemuaModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header" style="background: linear-gradient(135deg, #f8f9ff 0%, #f1f4ff 100%); color: #2d3748; border-bottom: 1px solid #e2e8f0;">
        <h5 class="modal-title" id="lihatSemuaModalLabel">Daftar Lengkap</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="lihatSemuaContent">
            <div class="text-center p-5"><div class="loading-spinner"></div></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<!-- TOMBOL BACK TO TOP -->
<button class="back-to-top" id="backToTop" onclick="scrollToTop()">
    <i class="bi bi-arrow-up"></i>
</button>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
// Back to Top Button
window.addEventListener('scroll', function() {
    const backToTop = document.getElementById('backToTop');
    if (window.scrollY > 300) {
        backToTop.classList.add('show');
    } else {
        backToTop.classList.remove('show');
    }
});

function scrollToTop() {
    window.scrollTo({
        top: 0,
        behavior: 'smooth'
    });
}

$(document).ready(function() {
    
    // Fungsi untuk mengambil data target dan menampilkannya di modal
    function showTargetModal(title, filter) {
        $('#targetListModalLabel').text(title);
        $('#modal-loading').show();
        $('#modal-table-container').hide();
        $('#target-list-tbody').html('');
        
        var targetModal = new bootstrap.Modal(document.getElementById('targetListModal'));
        targetModal.show();

        $.ajax({
            url: 'api_get_all_targets.php',
            method: 'GET',
            data: { filter: filter },
            dataType: 'json',
            success: function(response) {
                let html = '';
                if(response.status === 'success' && response.data.length > 0) {
                    response.data.forEach(function(target) {
                        let priorityBadge = target.prioritas == 1 ? '<span class="badge bg-warning text-dark">Prioritas</span>' : '<span class="badge bg-secondary">Normal</span>';
                        let statusText = target.status.charAt(0).toUpperCase() + target.status.slice(1);
                        let statusBadge = `<span class="badge bg-info">${statusText}</span>`;
                        
                        html += `
                            <tr>
                                <td>${target.id_target}</td>
                                <td>${target.nama_barang}</td>
                                <td>${target.jumlah_target}</td>
                                <td>${statusBadge}</td>
                                <td>${priorityBadge}</td>
                            </tr>
                        `;
                    });
                } else {
                    html = '<tr><td colspan="5" class="text-center">Tidak ada data ditemukan.</td></tr>';
                }
                $('#target-list-tbody').html(html);
            },
            error: function() {
                 $('#target-list-tbody').html('<tr><td colspan="5" class="text-center text-danger">Gagal memuat data.</td></tr>');
            },
            complete: function() {
                $('#modal-loading').hide();
                $('#modal-table-container').show();
            }
        });
    }

    // --- Event Listener untuk Setiap Card ---

    // 1. Card Target Berjalan
    $('#card-target-berjalan').on('click', function() {
        showTargetModal('Daftar Target Berjalan', 'berjalan'); 
    });

    // 2. Card Target Prioritas
    $('#card-target-prioritas').on('click', function() {
        showTargetModal('Daftar Target Prioritas', 'prioritas');
    });

    // 3. Card Laporan Selesai
    $('#card-laporan-selesai').on('click', function() {
        Swal.fire({
            title: 'Buka Laporan?',
            text: "Anda akan diarahkan ke halaman manajemen laporan.",
            icon: 'info',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Ya, lanjutkan!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'manajemen_laporan/laporan.php'; 
            }
        })
    });

    // 4. Card Master Barang
    $('#card-master-barang').on('click', function() {
        Swal.fire({
            title: 'Buka Master Barang?',
            text: "Anda akan diarahkan ke halaman kelola master barang.",
            icon: 'info',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Ya, lanjutkan!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'master_data/kelola_master_barang.php';
            }
        })
    });

});
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const ctx = document.getElementById('statusPieChart').getContext('2d');
    
    let statusPieChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode($chart_labels); ?>,
            datasets: [{
                label: 'Jumlah Target',
                data: <?php echo json_encode($chart_values); ?>,
                backgroundColor: [
                    'rgba(245, 158, 11, 0.8)',
                    'rgba(16, 185, 129, 0.8)',
                    'rgba(239, 68, 68, 0.8)',
                    'rgba(99, 102, 241, 0.8)'
                ],
                borderColor: [
                    'rgba(245, 158, 11, 1)',
                    'rgba(16, 185, 129, 1)',
                    'rgba(239, 68, 68, 1)',
                    'rgba(99, 102, 241, 1)'
                ],
                borderWidth: 3,
                hoverBorderWidth: 4,
                cutout: '60%'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 20,
                        usePointStyle: true,
                        font: { size: 14, weight: '500' }
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    titleColor: 'white',
                    bodyColor: 'white',
                    borderColor: 'rgba(255, 255, 255, 0.1)',
                    borderWidth: 1,
                    cornerRadius: 8,
                    displayColors: false,
                    callbacks: {
                        label: function(context) {
                            return `${context.label}: ${context.parsed} target`;
                        }
                    }
                }
            }
        }
    });

    // JAVASCRIPT LOGIC FOR FILTERS
    document.querySelectorAll('.filter-buttons .btn').forEach(button => {
        button.addEventListener('click', function() {
            document.querySelector('.filter-buttons .btn.active').classList.remove('active');
            this.classList.add('active');
            const period = this.getAttribute('data-period');
            updateDashboardData(period);
        });
    });

    function updateDashboardData(period) {
        fetch(`api_get_filtered_dashboard_data.php?period=${period}`)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    console.error('API Error:', data.error);
                    return;
                }
                
                document.getElementById('priority-targets-stat').textContent = data.stats.priority_targets;
                document.getElementById('completed-reports-stat').textContent = data.stats.completed_reports;

                statusPieChart.data.labels = data.chart.labels;
                statusPieChart.data.datasets[0].data = data.chart.values;
                statusPieChart.update();
            })
            .catch(error => console.error('Error fetching dashboard data:', error));
    }
    
    // Modal Logic
    const lihatSemuaModal = document.getElementById('lihatSemuaModal');
    if (lihatSemuaModal) {
        const modalTitle = lihatSemuaModal.querySelector('.modal-title');
        const modalContent = lihatSemuaModal.querySelector('#lihatSemuaContent');

        lihatSemuaModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const targetType = button.getAttribute('data-type'); 
            const title = button.getAttribute('data-title');

            modalTitle.textContent = title;
            modalContent.innerHTML = '<div class="text-center p-5"><div class="loading-spinner"></div></div>';

            $.ajax({
                url: 'api_get_all_targets.php',
                type: 'GET',
                data: { type: targetType },
                dataType: 'json',
                success: function(response) {
                    if (response.error) {
                        modalContent.innerHTML = `<div class="alert alert-danger">${response.error}</div>`;
                        return;
                    }

                    const dataItems = response.data;

                    if (dataItems.length === 0) {
                        modalContent.innerHTML = `<div class="empty-state"><div class="empty-state-icon">📋</div><p class="empty-state-text">Tidak ada data untuk ditampilkan.</p></div>`;
                        return;
                    }

                    let html = '<div class="modern-list">';
                    dataItems.forEach(item => {
                        let detailText = `Untuk barang: <strong>${item.nama_barang}</strong>`;
                        let dateText = '';
                        let itemClass = '';

                        if (targetType === 'priority' && item.priority_deadline) {
                            dateText = `<span class="list-item-date">Tenggat: ${new Date(item.priority_deadline).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })}</span>`;
                            itemClass = 'priority';
                        } else if (targetType === 'stalled') {
                            itemClass = 'warning';
                            if (item.last_report) {
                                dateText = `<span class="list-item-date">Laporan terakhir: ${new Date(item.last_report).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })}</span>`;
                            } else {
                                dateText = '<span class="list-item-date">Belum ada laporan</span>';
                            }
                        } else if (targetType === 'pending') {
                            detailText = `Semua komponen untuk <strong>${item.nama_barang}</strong> sudah terpenuhi.`;
                            dateText = '<span class="priority-badge">Ready</span>';
                            itemClass = 'success';
                        } else if (targetType === 'archived') {
                            itemClass = 'archived';
                            detailText = `Barang: <strong>${item.nama_barang}</strong><br><small>Alasan: ${item.alasan_nonaktif ? item.alasan_nonaktif : 'Tidak ada alasan'}</small>`;
                            dateText = `<span class="list-item-date">Diarsipkan pada: ${new Date(item.created_at).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })}</span>`;
                        }

                        html += `<a href="manajemen_produksi/detail_barang.php?id=${item.id_barang}" class="modern-list-item ${itemClass}">
                                     <div class="list-item-header">
                                         <h4 class="list-item-title">${item.nama_permintaan}</h4>
                                         ${dateText}
                                     </div>
                                     <p class="list-item-description">${detailText}</p>
                                 </a>`;
                    });
                    html += '</div>';
                    modalContent.innerHTML = html;
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    modalContent.innerHTML = `<div class="alert alert-danger">Gagal memuat data. Silakan coba lagi.</div>`;
                    console.error("AJAX Error: ", textStatus, errorThrown);
                }
            });
        });
    }

    // Animation script
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, observerOptions);

    document.querySelectorAll('.modern-card, .content-card, .chart-card').forEach(card => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(30px)';
        card.style.transition = 'opacity 0.6s ease-out, transform 0.6s ease-out';
        observer.observe(card);
    });
});
</script>

<?php include '../../templates/footer.php'; ?>