<?php
include_once '../../../templates/header_user.php';
include_once '../../../system/database_connection.php';

// 1. Validasi ID Target dari URL
if (!isset($_GET['id_target']) || !is_numeric($_GET['id_target'])) {
    echo "<script>alert('ID Target tidak valid!'); window.location.href='../dashboard.php';</script>";
    exit;
}
$id_target = (int)$_GET['id_target'];

// Ambil parameter filter
$start_date = $_GET['start_date'] ?? null;
$end_date = $_GET['end_date'] ?? null;
$quick_filter = $_GET['quick_filter'] ?? null;

// Handle Quick Filters
if ($quick_filter && !$start_date && !$end_date) {
    $end_date = date('Y-m-d');
    switch ($quick_filter) {
        case 'today':
            $start_date = date('Y-m-d');
            break;
        case '7days':
            $start_date = date('Y-m-d', strtotime('-7 days'));
            break;
        case '30days':
            $start_date = date('Y-m-d', strtotime('-30 days'));
            break;
        case 'month':
            $start_date = date('Y-m-01');
            break;
    }
}

$filter_active = $start_date && $end_date;

// Pagination setup
$limit = 50;
$page = isset($_GET['pg']) ? max(1, (int)$_GET['pg']) : 1;
$offset = ($page - 1) * $limit;

$history_by_alur = [];
$min_date = null;
$total_items = 0;
$total_alur = 0;

try {
    // 2. Ambil informasi header dari target produksi
    $header_stmt = $pdo->prepare("
        SELECT pt.nama_permintaan, mb.nama_barang, pt.id_barang
        FROM production_targets pt
        JOIN master_barang mb ON pt.id_barang = mb.id_barang
        WHERE pt.id_target = :id_target
    ");
    $header_stmt->execute([':id_target' => $id_target]);
    $header_info = $header_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$header_info) {
        throw new Exception("Data Target Produksi tidak ditemukan.");
    }

    // 3. Ambil tanggal laporan paling awal
    $min_date_stmt = $pdo->prepare("
        SELECT MIN(lh.tanggal_laporan) as min_tanggal
        FROM laporan_harian lh
        JOIN target_material tm ON lh.id_material = tm.id_material
        WHERE tm.id_target = :id_target
    ");
    $min_date_stmt->execute([':id_target' => $id_target]);
    $min_date_result = $min_date_stmt->fetch(PDO::FETCH_ASSOC);
    $min_date = $min_date_result['min_tanggal'] ?? date('Y-m-d');

    // 4. Query dengan pagination
    if ($filter_active) {
        // Count total items
        $count_sql = "
            SELECT COUNT(lh.id_laporan) as total
            FROM laporan_harian lh
            JOIN target_material tm ON lh.id_material = tm.id_material
            WHERE tm.id_target = :id_target
              AND lh.tanggal_laporan BETWEEN :start_date AND :end_date
        ";
        $count_stmt = $pdo->prepare($count_sql);
        $count_stmt->execute([
            ':id_target' => $id_target,
            ':start_date' => $start_date,
            ':end_date' => $end_date
        ]);
        $total_items = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Main query with pagination
        $query_sql = "
            SELECT
                lh.tanggal_laporan,
                lh.jumlah_selesai,
                mk.nama_komponen,
                ma.nama_alur,
                ma.id_alur,
                ma.urutan,
                COALESCE(tas.status_pengerjaan, 'Pending') AS status_pengerjaan_alur,
                GROUP_CONCAT(DISTINCT u.full_name SEPARATOR ', ') AS penanggung_jawab
            FROM laporan_harian lh
            JOIN target_material tm ON lh.id_material = tm.id_material
            JOIN master_komponen mk ON tm.id_komponen = mk.id_komponen
            JOIN master_alur ma ON tm.id_alur = ma.id_alur
            LEFT JOIN target_alur_status tas ON tm.id_target = tas.id_target AND tm.id_alur = tas.id_alur
            LEFT JOIN admin_tahapan_access ata ON ma.id_alur = ata.id_tahapan
            LEFT JOIN users u ON ata.id_user = u.id
            WHERE tm.id_target = :id_target
              AND lh.tanggal_laporan BETWEEN :start_date AND :end_date
            GROUP BY lh.id_laporan
            ORDER BY ma.urutan ASC, lh.tanggal_laporan DESC, lh.created_at DESC
            LIMIT :limit OFFSET :offset
        ";

        $history_stmt = $pdo->prepare($query_sql);
        $history_stmt->bindValue(':id_target', $id_target, PDO::PARAM_INT);
        $history_stmt->bindValue(':start_date', $start_date, PDO::PARAM_STR);
        $history_stmt->bindValue(':end_date', $end_date, PDO::PARAM_STR);
        $history_stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $history_stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $history_stmt->execute();
        $history_logs_raw = $history_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Kelompokkan berdasarkan alur
        foreach ($history_logs_raw as $log) {
            $nama_alur = $log['nama_alur'];
            if (!isset($history_by_alur[$nama_alur])) {
                $history_by_alur[$nama_alur] = [
                    'status' => $log['status_pengerjaan_alur'],
                    'pic'    => $log['penanggung_jawab'] ?? 'Tidak ditentukan',
                    'items'  => []
                ];
                $total_alur++;
            }
            $history_by_alur[$nama_alur]['items'][] = $log;
        }
    }

    $total_pages = $total_items > 0 ? ceil($total_items / $limit) : 0;

} catch (Exception $e) {
    error_log("Database Error: " . $e->getMessage());
    die("Terjadi kesalahan saat mengambil data. Silakan coba lagi nanti.");
}
?>

<style>
/* Modern Glassmorphism Theme */
:root {
    --glass-bg: rgba(255, 255, 255, 0.95);
    --glass-border: rgba(255, 255, 255, 0.18);
    --accent-color: #667eea;
    --accent-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --success-color: #28a745;
    --warning-color: #ffc107;
    --text-dark: #2c3e50;
}

.history-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 2rem 1rem;
}

/* Page Header */
.page-header-glass {
    background: var(--glass-bg);
    backdrop-filter: blur(10px);
    border-radius: 25px;
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
    border: 1px solid var(--glass-border);
}

.page-title-main {
    font-size: 2rem;
    font-weight: 700;
    color: var(--text-dark);
    margin: 0 0 0.5rem 0;
}

.page-subtitle {
    color: #666;
    font-size: 1.1rem;
    margin: 0;
}

.btn-back-custom {
    background: var(--accent-gradient);
    color: white;
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 50px;
    font-weight: 600;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-back-custom:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
    color: white;
}

/* Stats Cards */
.stats-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: var(--glass-bg);
    backdrop-filter: blur(10px);
    border-radius: 20px;
    padding: 1.5rem;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    border: 1px solid var(--glass-border);
    text-align: center;
    transition: transform 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
}

.stat-icon {
    font-size: 2rem;
    color: var(--accent-color);
    margin-bottom: 0.5rem;
}

.stat-value {
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--text-dark);
    margin: 0;
}

.stat-label {
    color: #666;
    font-size: 0.9rem;
    margin: 0;
}

/* Quick Filters */
.quick-filters-container {
    background: var(--glass-bg);
    backdrop-filter: blur(10px);
    border-radius: 20px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
    border: 1px solid var(--glass-border);
}

.quick-filters {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
    justify-content: center;
}

.filter-pill {
    padding: 0.65rem 1.5rem;
    border-radius: 50px;
    border: 2px solid var(--accent-color);
    background: white;
    color: var(--accent-color);
    font-weight: 600;
    text-decoration: none;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.filter-pill:hover, .filter-pill.active {
    background: var(--accent-gradient);
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
}

/* Custom Date Filter */
.custom-filter-container {
    background: var(--glass-bg);
    backdrop-filter: blur(10px);
    border-radius: 20px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
    border: 1px solid var(--glass-border);
}

.filter-form {
    display: grid;
    grid-template-columns: 1fr 1fr auto;
    gap: 1rem;
    align-items: end;
}

.form-group-custom {
    margin: 0;
}

.form-label-custom {
    font-weight: 600;
    color: var(--text-dark);
    margin-bottom: 0.5rem;
    display: block;
}

.form-control-custom {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 2px solid rgba(102, 126, 234, 0.3);
    border-radius: 12px;
    font-size: 1rem;
    transition: all 0.3s ease;
}

.form-control-custom:focus {
    outline: none;
    border-color: var(--accent-color);
    box-shadow: 0 0 15px rgba(102, 126, 234, 0.2);
}

.btn-filter {
    background: var(--accent-gradient);
    color: white;
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 12px;
    font-weight: 600;
    transition: all 0.3s ease;
    white-space: nowrap;
}

.btn-filter:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
}

.btn-reset {
    background: #6c757d;
    color: white;
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 12px;
    font-weight: 600;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

/* Accordion Alur Sections */
.alur-accordion {
    margin-bottom: 1.5rem;
}

.alur-card {
    background: var(--glass-bg);
    backdrop-filter: blur(10px);
    border-radius: 20px;
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
    border: 1px solid var(--glass-border);
    overflow: hidden;
    transition: all 0.3s ease;
}

.alur-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem;
    cursor: pointer;
    transition: background 0.3s ease;
}

.alur-header:hover {
    background: rgba(102, 126, 234, 0.05);
}

.alur-title {
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--text-dark);
    margin: 0;
}

.alur-badges {
    display: flex;
    gap: 0.75rem;
    align-items: center;
}

.status-badge {
    padding: 0.5rem 1rem;
    border-radius: 50px;
    font-weight: 600;
    font-size: 0.85rem;
}

.status-success {
    background: var(--success-color);
    color: white;
}

.status-warning {
    background: var(--warning-color);
    color: #333;
}

.pic-badge {
    background: #343a40;
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 50px;
    font-size: 0.85rem;
}

.toggle-icon {
    font-size: 1.5rem;
    color: var(--accent-color);
    transition: transform 0.3s ease;
}

.alur-header.collapsed .toggle-icon {
    transform: rotate(-90deg);
}

.alur-body {
    padding: 0 1.5rem 1.5rem;
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.3s ease;
}

.alur-body.show {
    max-height: 5000px;
}

/* Table Styling */
.table-custom {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    border-radius: 12px;
    overflow: hidden;
}

.table-custom thead {
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
}

.table-custom th {
    padding: 1rem;
    font-weight: 600;
    color: var(--text-dark);
    text-align: center;
}

.table-custom td {
    padding: 1rem;
    border-top: 1px solid rgba(0, 0, 0, 0.05);
}

.table-custom tbody tr:hover {
    background: rgba(102, 126, 234, 0.05);
}

/* Pagination */
.pagination-container {
    display: flex;
    justify-content: center;
    margin-top: 2rem;
}

.pagination-custom {
    display: flex;
    gap: 0.5rem;
    list-style: none;
    padding: 0;
    margin: 0;
}

.page-item-custom {
    margin: 0;
}

.page-link-custom {
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 45px;
    height: 45px;
    padding: 0 1rem;
    border-radius: 12px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
    background: var(--glass-bg);
    color: var(--text-dark);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
}

.page-link-custom:hover {
    background: var(--accent-gradient);
    color: white;
    transform: translateY(-2px);
}

.page-item-custom.active .page-link-custom {
    background: var(--accent-gradient);
    color: white;
}

.page-item-custom.disabled .page-link-custom {
    opacity: 0.5;
    cursor: not-allowed;
    pointer-events: none;
}

/* Back to Top Button */
.back-to-top {
    position: fixed;
    bottom: 30px;
    right: 30px;
    width: 50px;
    height: 50px;
    background: var(--accent-gradient);
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
    animation: fadeInUp 0.3s ease;
}

.back-to-top:hover {
    transform: scale(1.1);
    box-shadow: 0 6px 25px rgba(102, 126, 234, 0.6);
}

/* Empty State */
.empty-state {
    background: var(--glass-bg);
    backdrop-filter: blur(10px);
    border-radius: 25px;
    padding: 4rem 2rem;
    text-align: center;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
    border: 1px solid var(--glass-border);
}

.empty-icon {
    font-size: 5rem;
    color: var(--accent-color);
    margin-bottom: 1.5rem;
}

/* Animations */
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

/* Mobile Responsive */
@media (max-width: 768px) {
    .history-container {
        padding: 1rem 0.5rem;
    }

    .page-header-glass {
        padding: 1.5rem;
        border-radius: 20px;
    }

    .page-title-main {
        font-size: 1.5rem;
    }

    .stats-container {
        grid-template-columns: 1fr 1fr;
        gap: 0.75rem;
    }
    .stats-container .stat-card:nth-child(3) {
        grid-column: 1 / -1; /* Merentang dari kolom 1 sampai kolom terakhir */
    }

    .stat-card {
        padding: 1rem;
    }

    .quick-filters {
        gap: 0.5rem;
    }

    .filter-pill {
        font-size: 0.85rem;
        padding: 0.5rem 1rem;
    }

    .filter-form {
        grid-template-columns: 1fr;
    }

    .alur-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }

    .alur-badges {
        flex-wrap: wrap;
    }

    /* Mobile Card Layout for Table */
    .table-custom {
        display: none;
    }

    .mobile-cards {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .mobile-card {
        background: white;
        border-radius: 12px;
        padding: 1rem;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
    }

    .mobile-card-row {
        display: flex;
        justify-content: space-between;
        padding: 0.75rem 0;
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    }

    .mobile-card-row:last-child {
        border-bottom: none;
    }

    .mobile-label {
        font-weight: 600;
        color: #666;
        font-size: 0.85rem;
    }

    .mobile-value {
        font-weight: 600;
        color: var(--text-dark);
        text-align: right;
    }

    .pagination-custom {
        gap: 0.3rem;
    }

    .page-link-custom {
        min-width: 40px;
        height: 40px;
        font-size: 0.85rem;
    }

    .back-to-top {
        bottom: 20px;
        right: 20px;
        width: 45px;
        height: 45px;
    }
}

/* Desktop: Hide mobile cards */
@media (min-width: 769px) {
    .mobile-cards {
        display: none;
    }
}
</style>

<div class="history-container">
    <!-- Page Header -->
    <div class="page-header-glass">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
            <div>
                <h1 class="page-title-main">📊 History Input Harian</h1>
                <p class="page-subtitle">
                    <?php echo htmlspecialchars($header_info['nama_barang']); ?> - 
                    <?php echo htmlspecialchars($header_info['nama_permintaan']); ?>
                </p>
            </div>
            <a href="../management_produksi/detail_barang.php?id=<?php echo $header_info['id_barang']; ?>" 
               class="btn-back-custom">
                <i class="bi bi-arrow-left"></i> Kembali
            </a>
        </div>
    </div>

    <?php if ($filter_active): ?>
    <!-- Stats Cards -->
    <div class="stats-container">
        <div class="stat-card">
            <div class="stat-icon"><i class="bi bi-diagram-3"></i></div>
            <p class="stat-value"><?php echo $total_alur; ?></p>
            <p class="stat-label">Total Alur</p>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="bi bi-file-earmark-text"></i></div>
            <p class="stat-value"><?php echo number_format($total_items); ?></p>
            <p class="stat-label">Total Input</p>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="bi bi-calendar-range"></i></div>
            <p class="stat-value"><?php echo date('d M', strtotime($start_date)); ?> - <?php echo date('d M', strtotime($end_date)); ?></p>
            <p class="stat-label">Periode</p>
        </div>
    </div>
    <?php endif; ?>

    <!-- Quick Filters -->
    <div class="quick-filters-container">
        <div class="quick-filters">
            <a href="?id_target=<?php echo $id_target; ?>&quick_filter=today" 
               class="filter-pill <?php echo ($quick_filter == 'today') ? 'active' : ''; ?>">
                <i class="bi bi-calendar-day"></i> Hari Ini
            </a>
            <a href="?id_target=<?php echo $id_target; ?>&quick_filter=7days" 
               class="filter-pill <?php echo ($quick_filter == '7days') ? 'active' : ''; ?>">
                <i class="bi bi-calendar-week"></i> 7 Hari Terakhir
            </a>
            <a href="?id_target=<?php echo $id_target; ?>&quick_filter=30days" 
               class="filter-pill <?php echo ($quick_filter == '30days') ? 'active' : ''; ?>">
                <i class="bi bi-calendar-month"></i> 30 Hari Terakhir
            </a>
            <a href="?id_target=<?php echo $id_target; ?>&quick_filter=month" 
               class="filter-pill <?php echo ($quick_filter == 'month') ? 'active' : ''; ?>">
                <i class="bi bi-calendar3"></i> Bulan Ini
            </a>
        </div>
    </div>

    <!-- Custom Date Filter -->
    <div class="custom-filter-container">
        <form method="get" class="filter-form">
            <input type="hidden" name="id_target" value="<?php echo $id_target; ?>">
            <div class="form-group-custom">
                <label class="form-label-custom">Tanggal Mulai</label>
                <input type="date" name="start_date" class="form-control-custom"
                       value="<?php echo htmlspecialchars($start_date ?? ''); ?>"
                       min="<?php echo htmlspecialchars($min_date); ?>"
                       max="<?php echo date('Y-m-d'); ?>" required>
            </div>
            <div class="form-group-custom">
                <label class="form-label-custom">Tanggal Akhir</label>
                <input type="date" name="end_date" class="form-control-custom"
                       value="<?php echo htmlspecialchars($end_date ?? ''); ?>"
                       min="<?php echo htmlspecialchars($min_date); ?>"
                       max="<?php echo date('Y-m-d'); ?>" required>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn-filter">
                    <i class="bi bi-filter"></i> Tampilkan
                </button>
                <?php if ($filter_active): ?>
                <a href="history_laporan.php?id_target=<?php echo $id_target; ?>" class="btn-reset">
                    <i class="bi bi-x-circle"></i> Reset
                </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <?php if (!$filter_active): ?>
        <div class="empty-state">
            <div class="empty-icon"><i class="bi bi-calendar-check"></i></div>
            <h3>Pilih Periode untuk Melihat History</h3>
            <p>Gunakan quick filter di atas atau pilih rentang tanggal kustom untuk menampilkan riwayat input harian.</p>
        </div>
    <?php elseif (empty($history_by_alur)): ?>
        <div class="empty-state">
            <div class="empty-icon"><i class="bi bi-inbox"></i></div>
            <h3>Tidak Ada Data</h3>
            <p>Tidak ada riwayat input harian yang ditemukan untuk periode yang dipilih.</p>
        </div>
    <?php else: ?>
        <!-- Alur Accordion -->
        <?php foreach ($history_by_alur as $nama_alur => $data_alur): ?>
        <div class="alur-accordion">
            <div class="alur-card">
                <div class="alur-header" onclick="toggleAlur(this)">
                    <h5 class="alur-title"><?php echo htmlspecialchars($nama_alur); ?></h5>
                    <div class="alur-badges">
                        <span class="status-badge <?php echo ($data_alur['status'] == 'Sedang Dikerjakan') ? 'status-success' : 'status-warning'; ?>">
                            <?php echo htmlspecialchars($data_alur['status']); ?>
                        </span>
                        <span class="pic-badge">
                            <i class="bi bi-person"></i> <?php echo htmlspecialchars($data_alur['pic']); ?>
                        </span>
                        <i class="bi bi-chevron-down toggle-icon"></i>
                    </div>
                </div>
                <div class="alur-body">
                    <!-- Desktop Table -->
                    <table class="table-custom">
                        <thead>
                            <tr>
                                <th style="width: 20%;">Tanggal Laporan</th>
                                <th>Nama Komponen</th>
                                <th style="width: 20%;">Jumlah Diinput</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($data_alur['items'] as $log): ?>
                            <tr>
                                <td class="text-center"><?php echo date('d M Y', strtotime($log['tanggal_laporan'])); ?></td>
                                <td><?php echo htmlspecialchars($log['nama_komponen']); ?></td>
                                <td class="text-center fw-bold"><?php echo number_format($log['jumlah_selesai']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <!-- Mobile Cards -->
                    <div class="mobile-cards">
                        <?php foreach ($data_alur['items'] as $log): ?>
                        <div class="mobile-card">
                            <div class="mobile-card-row">
                                <span class="mobile-label">Tanggal</span>
                                <span class="mobile-value"><?php echo date('d M Y', strtotime($log['tanggal_laporan'])); ?></span>
                            </div>
                            <div class="mobile-card-row">
                                <span class="mobile-label">Komponen</span>
                                <span class="mobile-value"><?php echo htmlspecialchars($log['nama_komponen']); ?></span>
                            </div>
                            <div class="mobile-card-row">
                                <span class="mobile-label">Jumlah</span>
                                <span class="mobile-value" style="color: var(--accent-color);"><?php echo number_format($log['jumlah_selesai']); ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination-container">
            <ul class="pagination-custom">
                <?php
                $url_params = [
                    'id_target' => $id_target,
                    'start_date' => $start_date,
                    'end_date' => $end_date
                ];
                if ($quick_filter) $url_params['quick_filter'] = $quick_filter;
                ?>

                <li class="page-item-custom <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                    <a class="page-link-custom" href="?<?php echo http_build_query(array_merge($url_params, ['pg' => $page - 1])); ?>">
                        <i class="bi bi-chevron-left"></i>
                    </a>
                </li>

                <?php
                $range = 2;
                $start = max(1, $page - $range);
                $end = min($total_pages, $page + $range);

                if ($start > 1) {
                    echo '<li class="page-item-custom"><a class="page-link-custom" href="?' . http_build_query(array_merge($url_params, ['pg' => 1])) . '">1</a></li>';
                    if ($start > 2) echo '<li class="page-item-custom disabled"><span class="page-link-custom">...</span></li>';
                }

                for ($i = $start; $i <= $end; $i++):
                ?>
                <li class="page-item-custom <?php echo ($i == $page) ? 'active' : ''; ?>">
                    <a class="page-link-custom" href="?<?php echo http_build_query(array_merge($url_params, ['pg' => $i])); ?>">
                        <?php echo $i; ?>
                    </a>
                </li>
                <?php
                endfor;

                if ($end < $total_pages) {
                    if ($end < $total_pages - 1) echo '<li class="page-item-custom disabled"><span class="page-link-custom">...</span></li>';
                    echo '<li class="page-item-custom"><a class="page-link-custom" href="?' . http_build_query(array_merge($url_params, ['pg' => $total_pages])) . '">' . $total_pages . '</a></li>';
                }
                ?>

                <li class="page-item-custom <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                    <a class="page-link-custom" href="?<?php echo http_build_query(array_merge($url_params, ['pg' => $page + 1])); ?>">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                </li>
            </ul>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Back to Top Button -->
<button class="back-to-top" id="backToTop" onclick="scrollToTop()">
    <i class="bi bi-arrow-up"></i>
</button>

<script>
// Accordion Toggle
function toggleAlur(header) {
    const body = header.nextElementSibling;
    const isCollapsed = header.classList.contains('collapsed');
    
    if (isCollapsed) {
        header.classList.remove('collapsed');
        body.classList.add('show');
    } else {
        header.classList.add('collapsed');
        body.classList.remove('show');
    }
}

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

// Auto-expand first alur on page load
document.addEventListener('DOMContentLoaded', function() {
    const firstAlur = document.querySelector('.alur-header');
    if (firstAlur && !firstAlur.classList.contains('collapsed')) {
        const body = firstAlur.nextElementSibling;
        body.classList.add('show');
    }
});
</script>

<?php include_once '../../../templates/footer.php'; ?>