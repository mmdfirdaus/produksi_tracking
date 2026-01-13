<?php
$page_title = 'Kebutuhan Material';
include '../../../templates/header_superadmin.php';
include '../../../system/database_connection.php';

// Ambil ID & parameter lain dari URL
$id_target = isset($_GET['id_target']) ? (int)$_GET['id_target'] : 0;
$id_alur = isset($_GET['id_alur']) ? (int)$_GET['id_alur'] : 0;
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status_filter']) ? $_GET['status_filter'] : 'semua';

if ($id_target === 0 || $id_alur === 0) {
    header("Location: ../master_barang.php");
    exit;
}

// Logika Pagination (dari kode lama)
$limit = 15;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

try {
    // Ambil informasi detail untuk header, tambahkan 'status_pengerjaan'
    $header_stmt = $pdo->prepare("
        SELECT 
            pt.nama_permintaan, 
            pt.jumlah_unit, 
            pt.no_spk,
            mb.nama_barang,
            mb.kode_barang, 
            ma.nama_alur, 
            pt.id_barang,
            -- Ambil status dari tabel baru, jika tidak ada, default-nya 'Pending'
            COALESCE(tas.status_pengerjaan, 'Pending') AS status_pengerjaan
        FROM production_targets pt
        JOIN master_barang mb ON pt.id_barang = mb.id_barang
        JOIN master_alur ma ON ma.id_alur = :id_alur
        -- JOIN ke tabel status yang baru
        LEFT JOIN target_alur_status tas ON pt.id_target = tas.id_target AND ma.id_alur = tas.id_alur
        WHERE pt.id_target = :id_target
    ");
    $header_stmt->execute([':id_target' => $id_target, ':id_alur' => $id_alur]);
    $header_info = $header_stmt->fetch();

    // Buat query dasar dan parameter
    $base_sql = "
        FROM target_material tm
        JOIN master_komponen mk ON tm.id_komponen = mk.id_komponen
        JOIN production_targets pt ON tm.id_target = pt.id_target
        LEFT JOIN (
            SELECT id_material, SUM(jumlah_selesai) as total_selesai
            FROM laporan_harian
            GROUP BY id_material
        ) lh ON tm.id_material = lh.id_material
        WHERE tm.id_target = :id_target AND tm.id_alur = :id_alur
    ";
    $params = [':id_target' => $id_target, ':id_alur' => $id_alur];

    if (!empty($search_query)) {
        $base_sql .= " AND mk.nama_komponen LIKE :search";
        $params[':search'] = "%" . $search_query . "%";
    }

    if ($status_filter == 'terpenuhi') {
        $base_sql .= " AND (tm.jumlah_per_unit * pt.jumlah_unit) <= COALESCE(lh.total_selesai, 0)";
    } elseif ($status_filter == 'belum_terpenuhi') {
        $base_sql .= " AND (tm.jumlah_per_unit * pt.jumlah_unit) > COALESCE(lh.total_selesai, 0)";
    }

    $total_stmt = $pdo->prepare("SELECT COUNT(tm.id_material) " . $base_sql);
    $total_stmt->execute($params);
    $total_results = $total_stmt->fetchColumn();
    $total_pages = ceil($total_results / $limit);
    
    // Hitung statistik
    $stats_stmt = $pdo->prepare("
        SELECT 
            COUNT(tm.id_material) as total_material,
            SUM(CASE WHEN (tm.jumlah_per_unit * pt.jumlah_unit) <= COALESCE(lh.total_selesai, 0) THEN 1 ELSE 0 END) as terpenuhi,
            SUM(CASE WHEN (tm.jumlah_per_unit * pt.jumlah_unit) > COALESCE(lh.total_selesai, 0) THEN 1 ELSE 0 END) as belum_terpenuhi
        " . $base_sql
    );
    $stats_stmt->execute($params);
    $stats = $stats_stmt->fetch();
    
    $select_query = "
        SELECT 
            tm.*, 
            mk.nama_komponen, 
            (tm.jumlah_per_unit * pt.jumlah_unit) AS kebutuhan_total,
            COALESCE(lh.total_selesai, 0) AS total_selesai
        " . $base_sql . " 
        ORDER BY
            CASE
                WHEN ((tm.jumlah_per_unit * pt.jumlah_unit) - COALESCE(lh.total_selesai, 0)) <= 0 THEN 1
                ELSE 0
            END ASC,
            mk.nama_komponen ASC 
        LIMIT :limit OFFSET :offset
    ";
    
    $material_stmt = $pdo->prepare($select_query);
    
    foreach ($params as $key => &$val) {
        $material_stmt->bindParam($key, $val);
    }
    $material_stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $material_stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    
    $material_stmt->execute();
    $materials = $material_stmt->fetchAll();

    $all_komponen_stmt = $pdo->query("SELECT id_komponen, nama_komponen FROM master_komponen WHERE is_active = 1 ORDER BY nama_komponen ASC");
    $all_komponen = $all_komponen_stmt->fetchAll();

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />

<style>
    /* ... [ SEMUA KODE CSS LAMA ANDA TETAP DI SINI ] ... */
    :root {
        --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        --success-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        --warning-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        --dark-gradient: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
        --card-shadow: 0 10px 30px rgba(0,0,0,0.1);
        --card-hover-shadow: 0 20px 40px rgba(0,0,0,0.15);
    }

    body {
        background: linear-gradient(120deg, #a8edea 0%, #fed6e3 100%);
        min-height: 100vh;
        font-family: 'Inter', system-ui, -apple-system, sans-serif;
    }

    .main-container {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        border-radius: 20px;
        margin: 20px;
        box-shadow: var(--card-shadow);
        overflow: hidden;
    }

    .header-section {
        background: var(--primary-gradient);
        color: white;
        padding: 2rem;
        padding-bottom: 5rem;
        position: relative;
        overflow: hidden;
    }

    .header-section::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -50%;
        width: 200%;
        height: 200%;
        background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
        animation: float 6s ease-in-out infinite;
    }

    @keyframes float {
        0%, 100% { transform: translateY(0px) rotate(0deg); }
        50% { transform: translateY(-20px) rotate(180deg); }
    }

    .back-btn {
    background: rgba(255,255,255,0.2);
    border: 1px solid rgba(255,255,255,0.3);
    color: white;
    border-radius: 50px;
    padding: 0.5rem 1.5rem;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.3s ease;

    /* Tambahkan 2 baris ini */
    position: relative;
    z-index: 2;
}

    .back-btn:hover {
        background: rgba(255,255,255,0.3);
        color: white;
        transform: translateX(-5px);
    }

    .stats-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin: -50px 2rem 2rem;
        position: relative;
        z-index: 10;
    }

    .stat-card {
        background: white;
        border-radius: 15px;
        padding: 1.5rem;
        box-shadow: var(--card-shadow);
        transition: all 0.3s ease;
        border: none;
    }

    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: var(--card-hover-shadow);
    }

    .stat-icon {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        margin-bottom: 1rem;
    }

    .icon-primary { background: var(--primary-gradient); color: white; }
    .icon-success { background: var(--success-gradient); color: white; }
    .icon-warning { background: var(--warning-gradient); color: white; }

    .action-section {
        background: white;
        margin: 2rem;
        border-radius: 20px;
        box-shadow: var(--card-shadow);
        overflow: hidden;
    }

    .action-header {
        background: var(--dark-gradient);
        color: white;
        padding: 1.5rem;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .action-header:hover {
        transform: scale(1.01);
    }

    .action-content {
        padding: 2rem;
    }

    .import-section, .manual-section {
        background: linear-gradient(145deg, #f8f9fa 0%, #e9ecef 100%);
        border-radius: 15px;
        padding: 2rem;
        height: 100%;
    }

    .form-control, .form-select {
        border-radius: 10px;
        border: 2px solid #e9ecef;
        padding: 0.75rem 1rem;
        transition: all 0.3s ease;
    }

    .form-control:focus, .form-select:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
    }

    .btn-modern {
        border-radius: 50px;
        padding: 0.75rem 2rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        transition: all 0.3s ease;
        border: none;
        position: relative;
        overflow: hidden;
    }

    .btn-modern::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
        transition: left 0.5s;
    }

    .btn-modern:hover::before {
        left: 100%;
    }

    .btn-primary-modern {
        background: var(--primary-gradient);
        color: white;
    }

    .btn-success-modern {
        background: var(--success-gradient);
        color: white;
    }

    .btn-info-modern {
        background: linear-gradient(135deg, #17a2b8 0%, #20c997 100%);
        color: white;
    }

    .table-modern {
        background: white;
        border-radius: 20px;
        overflow: hidden;
        box-shadow: var(--card-shadow);
    }

    .table-modern thead {
        background: var(--dark-gradient);
        color: white;
    }

    .table-modern th {
        border: none;
        padding: 1.5rem 1rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-size: 0.85rem;
    }

    .table-modern td {
        padding: 1.25rem 1rem;
        border-bottom: 1px solid #f1f3f4;
        vertical-align: middle;
    }

    .table-modern tbody tr {
        transition: all 0.3s ease;
    }

    .table-modern tbody tr:hover {
        background: linear-gradient(90deg, #f8f9fa 0%, #e9ecef 100%);
        transform: scale(1.01);
    }

    .status-badge {
        padding: 0.5rem 1rem;
        border-radius: 50px;
        font-weight: 600;
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .status-success {
        background: var(--success-gradient);
        color: white;
    }

    .status-warning {
        background: var(--warning-gradient);
        color: white;
    }

    .filter-section {
        background: white;
        border-radius: 20px;
        padding: 1.5rem;
        margin-bottom: 2rem;
        box-shadow: var(--card-shadow);
    }

    .date-picker-section {
        background: linear-gradient(145deg, #667eea, #764ba2);
        color: white;
        border-radius: 20px;
        padding: 2rem;
        margin-bottom: 2rem;
        box-shadow: var(--card-shadow);
    }

    .pagination-modern .page-link {
        border: none;
        border-radius: 50px;
        margin: 0 0.25rem;
        padding: 0.75rem 1rem;
        transition: all 0.3s ease;
    }

    .pagination-modern .page-item.active .page-link {
        background: var(--primary-gradient);
        border: none;
    }

    .pagination-modern .page-link:hover {
        background: var(--primary-gradient);
        color: white;
        transform: translateY(-2px);
    }

    .input-modern {
        position: relative;
    }

    .input-modern input {
        background: rgba(255,255,255,0.9);
        border: 2px solid rgba(255,255,255,0.3);
        border-radius: 15px;
        padding: 1rem;
        color: #333;
        width: 100%;
    }

    .input-modern input:focus {
        border-color: rgba(255,255,255,0.8);
        background: white;
    }

    .quick-action-btn {
        background: rgba(255,255,255,0.2);
        border: 1px solid rgba(255,255,255,0.3);
        color: white;
        border-radius: 15px;
        padding: 0.75rem 1.5rem;
        margin: 0 0.5rem;
        transition: all 0.3s ease;
    }

    .quick-action-btn:hover {
        background: rgba(255,255,255,0.3);
        color: white;
        transform: translateY(-2px);
    }

    .alert-modern {
        border: none;
        border-radius: 15px;
        padding: 1.5rem;
        box-shadow: var(--card-shadow);
    }

    .loading-spinner {
        display: inline-block;
        width: 20px;
        height: 20px;
        border: 3px solid rgba(255,255,255,.3);
        border-radius: 50%;
        border-top-color: #fff;
        animation: spin 1s ease-in-out infinite;
    }

    @keyframes spin {
        to { transform: rotate(360deg); }
    }

    @media (max-width: 768px) {
        .main-container {
            margin: 10px;
            border-radius: 15px;
        }
        
        .stats-cards {
            grid-template-columns: 1fr;
            margin: -30px 1rem 1rem;
        }
        
        .action-content {
            padding: 1rem;
        }
        
        .import-section, .manual-section {
            margin-bottom: 1rem;
        }
    }
    .info-card {
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(10px);
    border-radius: 12px;
    padding: 1rem 1.25rem;
    border: 1px solid rgba(255, 255, 255, 0.2);
    transition: all 0.3s ease;
}

.info-card:hover {
    background: rgba(255, 255, 255, 0.25);
    transform: translateY(-2px);
}

.info-label {
    color: rgba(255, 255, 255, 0.8);
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 0.5rem;
}

.info-value {
    color: white;
    font-weight: 700;
    margin: 0;
    font-size: 1rem;
}

/* Status Badge Large */
.status-badge-large {
    display: inline-flex;
    align-items: center;
    padding: 0.6rem 1.25rem;
    border-radius: 50px;
    font-weight: 700;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
    transition: all 0.3s ease;
}

.status-badge-large:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
}

.status-progress {
    background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    color: white;
    animation: pulse-success 2s ease-in-out infinite;
}

.status-pending {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    color: white;
    animation: pulse-warning 2s ease-in-out infinite;
}

@keyframes pulse-success {
    0%, 100% {
        box-shadow: 0 4px 15px rgba(17, 153, 142, 0.4);
    }
    50% {
        box-shadow: 0 4px 25px rgba(17, 153, 142, 0.6);
    }
}

@keyframes pulse-warning {
    0%, 100% {
        box-shadow: 0 4px 15px rgba(245, 87, 108, 0.4);
    }
    50% {
        box-shadow: 0 4px 25px rgba(245, 87, 108, 0.6);
    }
}

.header-icon-wrapper {
    animation: float-gentle 3s ease-in-out infinite;
}

@keyframes float-gentle {
    0%, 100% {
        transform: translateY(0px);
    }
    50% {
        transform: translateY(-10px);
    }
}

/* Modal Status Styling */
.modal-status-option {
    background: linear-gradient(145deg, #f8f9fa 0%, #e9ecef 100%);
    border: 2px solid #dee2e6;
    border-radius: 15px;
    padding: 1.5rem;
    cursor: pointer;
    transition: all 0.3s ease;
    margin-bottom: 1rem;
}

.modal-status-option:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
}

.modal-status-option.status-option-active {
    border: 2px solid #667eea;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
}

.modal-status-option.status-option-pending {
    border: 2px solid #f5576c;
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    color: white;
    box-shadow: 0 10px 30px rgba(245, 87, 108, 0.3);
}

.status-icon-large {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    margin-bottom: 1rem;
    background: rgba(255, 255, 255, 0.2);
}

.status-description {
    font-size: 0.9rem;
    opacity: 0.9;
    margin-top: 0.5rem;
}

/* ============================================
   RESPONSIVE DESIGN UNTUK MOBILE
   Tambahkan di bawah semua CSS yang sudah ada
   ============================================ */

@media (max-width: 768px) {
    /* Container utama */
    .main-container {
        margin: 10px;
        border-radius: 15px;
    }
    
    /* Header Section */
    .header-section {
        padding: 1.5rem 1rem;
        padding-bottom: 4rem;
    }
    
    .header-section h2 {
        font-size: 1.25rem;
        margin-bottom: 1rem !important;
    }
    
    .back-btn {
        font-size: 0.85rem;
        padding: 0.4rem 1rem;
    }
    
    /* Info Cards di Header */
    .info-card {
        padding: 0.75rem 1rem;
        margin-bottom: 0.75rem;
    }
    
    .info-label {
        font-size: 0.7rem;
    }
    
    .info-value {
        font-size: 0.9rem;
    }
    
    .status-badge-large {
        font-size: 0.75rem;
        padding: 0.5rem 1rem;
    }
    
    .status-badge-large i {
        font-size: 0.9rem;
    }
    
    /* Stats Cards */
    .stats-cards {
        display: grid; /* Tetap menggunakan grid */
        grid-template-columns: 1fr 1fr; /* Buat 2 kolom yang sama besar */
        margin: -40px 1rem 1.5rem;
        gap: 0.75rem;
    }
    
    /* Target kartu pertama (Total Material) agar lebarnya penuh */
    .stats-cards .stat-card:first-child {
        grid-column: 1 / span 2; /* Buat kartu pertama membentang selebar 2 kolom */
    }
    .stat-card {
        padding: 1rem;
    }
    
    .stat-card h6 {
        font-size: 0.8rem;
    }
    
    .stat-card h3 {
        font-size: 1.25rem;
    }
    
    .stat-icon {
        width: 40px;
        height: 40px;
        font-size: 1.2rem;
    }
    
    /* Alert Messages */
    .alert-modern {
        margin: 0 1rem 1rem;
        padding: 1rem;
        font-size: 0.85rem;
    }
    
    /* Action Section */
    .action-section {
        margin: 1.5rem 1rem;
    }
    
    .action-header {
        padding: 1rem;
    }
    
    .action-header h5 {
        font-size: 1rem;
    }
    
    .action-header small {
        font-size: 0.75rem;
    }
    
    .action-content {
        padding: 1rem;
    }
    
    .import-section, 
    .manual-section {
        padding: 1.25rem;
        margin-bottom: 1rem;
    }
    
    .import-section h6,
    .manual-section h6 {
        font-size: 0.9rem;
    }
    
    /* Form Elements */
    .form-control, 
    .form-select {
        font-size: 0.9rem;
        padding: 0.6rem 0.75rem;
    }
    
    .form-label {
        font-size: 0.85rem;
    }
    
    .form-text {
        font-size: 0.75rem;
    }
    
    /* Buttons */
    .btn-modern {
        padding: 0.6rem 1.5rem;
        font-size: 0.85rem;
    }
    
    .btn-sm {
        padding: 0.4rem 0.75rem;
        font-size: 0.75rem;
    }
    
    /* Date Picker Section */
    .date-picker-section {
        padding: 1.25rem;
        margin: 0 1rem 1.5rem;
    }
    
    .date-picker-section h5 {
        font-size: 1rem;
    }
    
    .input-modern input {
        padding: 0.75rem;
        font-size: 0.9rem;
    }
    
    .quick-action-btn {
        padding: 0.6rem 1rem;
        font-size: 0.8rem;
        margin: 0.25rem;
        display: inline-block;
        width: auto;
    }
    
    /* Filter Section */
    .filter-section {
        padding: 1rem;
        margin: 0 1rem 1.5rem;
    }
    
    .filter-section h6 {
        font-size: 0.9rem;
        margin-bottom: 0.75rem;
    }
    
    .filter-section .row {
        gap: 0.75rem;
    }
    
    .filter-section .form-select {
        margin-bottom: 0.5rem;
    }
    
    /* Table Container - Horizontal Scroll */
    .table-modern {
        margin: 0 1rem 1.5rem;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    
    .table-modern table {
        min-width: 900px; /* Pastikan tabel tidak terlalu sempit */
    }
    
    .table-modern thead th {
        font-size: 0.7rem;
        padding: 1rem 0.5rem;
        white-space: nowrap;
    }
    
    .table-modern tbody td {
        font-size: 0.8rem;
        padding: 0.75rem 0.5rem;
    }
    
    .table-modern tbody td:nth-child(2) {
        min-width: 150px; /* Nama komponen */
    }
    
    .table-modern input[type="number"] {
        font-size: 0.85rem;
        padding: 0.5rem;
        width: 80px;
    }
    
    /* Status Badge dalam Table */
    .status-badge {
        font-size: 0.7rem;
        padding: 0.4rem 0.75rem;
        white-space: nowrap;
    }
    
    /* Empty State */
    .table-modern .p-5 {
        padding: 2rem !important;
    }
    
    .table-modern .p-5 h5 {
        font-size: 0.95rem;
    }
    
    /* Pagination */
    .pagination-modern {
        flex-wrap: wrap;
        gap: 0.25rem;
    }
    
    .pagination-modern .page-link {
        padding: 0.5rem 0.75rem;
        font-size: 0.85rem;
        margin: 0.1rem;
    }
    
    /* Modals */
    .modal-dialog {
        margin: 0.5rem;
    }
    
    .modal-content {
        border-radius: 15px !important;
    }
    
    .modal-header {
        padding: 1rem;
    }
    
    .modal-header h5 {
        font-size: 1rem;
    }
    
    .modal-body {
        padding: 1rem;
    }
    
    .modal-body h5 {
        font-size: 0.95rem;
    }
    
    .modal-body p {
        font-size: 0.85rem;
    }
    
    .modal-footer {
        padding: 1rem;
        flex-wrap: wrap;
        gap: 0.5rem;
    }
    
    .modal-footer .btn {
        flex: 1 1 100%;
        margin: 0.25rem 0 !important;
    }
    
    /* Modal Status Options */
    .modal-status-option {
        padding: 1rem;
        margin-bottom: 0.75rem;
    }
    
    .modal-status-option h6 {
        font-size: 0.9rem;
    }
    
    .status-description {
        font-size: 0.8rem;
    }
    
    .status-icon-large {
        width: 50px;
        height: 50px;
        font-size: 1.5rem;
    }
    
    /* Alert dalam Modal */
    .modal-body .alert {
        padding: 0.75rem;
        font-size: 0.85rem;
    }
    
    /* Select2 Dropdown */
    .select2-container {
        font-size: 0.9rem !important;
    }
    
    .select2-container .select2-selection--single {
        height: 40px !important;
    }
    
    .select2-container .select2-selection__rendered {
        line-height: 38px !important;
    }
    
    /* Hide beberapa kolom di mobile jika terlalu sempit */
    @media (max-width: 576px) {
        .table-modern table {
            font-size: 0.75rem;
        }
        
        /* Stack buttons vertically */
        .date-picker-section .quick-action-btn {
            display: block;
            width: 100%;
            margin: 0.25rem 0;
        }
        
        .filter-section .btn {
            width: 100%;
            margin-top: 0.5rem;
        }
        
        /* Make status badge smaller */
        .status-badge-large {
            font-size: 0.7rem;
            padding: 0.4rem 0.8rem;
        }
        
        .status-badge-large .btn {
            font-size: 0.7rem;
            padding: 0.3rem 0.6rem;
        }
    }
    
    /* Landscape orientation pada mobile */
    @media (max-width: 768px) and (orientation: landscape) {
        .header-section {
            padding: 1rem;
            padding-bottom: 3rem;
        }
        
        .stats-cards {
            grid-template-columns: repeat(3, 1fr);
            margin: -30px 1rem 1rem;
        }
    }
}

/* Tablet View (768px - 992px) */
@media (min-width: 769px) and (max-width: 992px) {
    .stats-cards {
        grid-template-columns: repeat(3, 1fr);
    }
    
    .table-modern input[type="number"] {
        width: 90px;
    }
}

/* Extra Small Devices */
@media (max-width: 375px) {
    .header-section h2 {
        font-size: 1.1rem;
    }
    
    .stat-card h3 {
        font-size: 1.1rem;
    }
    
    .btn-modern {
        padding: 0.5rem 1rem;
        font-size: 0.8rem;
    }
    
    .table-modern table {
        font-size: 0.7rem;
    }
}
</style>

<div class="main-container">
    <div class="header-section">
            <a href="alur_produksi.php?id_target=<?php echo $id_target; ?>&id_barang=<?php echo $header_info['id_barang']; ?>" class="back-btn mb-3">
        <i class="bi bi-arrow-left"></i>
        <span>Kembali ke Alur Produksi</span>
    </a>
    
    <div class="row align-items-center">
        <div class="col-md-9">
            <h2 class="fw-bold mb-3">
                <i class="bi bi-gear-wide-connected me-2"></i>
                Tracking Material: <?php echo htmlspecialchars($header_info['nama_alur']); ?>
            </h2>
            
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="info-card">
                        <h6 class="info-label">Nama Barang</h6>
                        <h5 class="info-value"><?php echo htmlspecialchars($header_info['nama_barang']); ?></h5>
                        <small class="text-white-50" style="font-size: 0.8rem;">
        <i class="bi bi-upc-scan me-1"></i><?php echo htmlspecialchars($header_info['kode_barang']); ?>
    </small>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="info-card">
                        <h6 class="info-label">PO / Nama Permintaan</h6>
                        <h5 class="info-value"><?php echo htmlspecialchars($header_info['nama_permintaan']); ?></h5>
                        <small class="text-white-50" style="font-size: 0.8rem;">
        <i class="bi bi-hash me-1"></i>SPK: <?php echo htmlspecialchars($header_info['no_spk']); ?>
    </small>
                    </div>
                </div>
            </div>
            
            <div class="row g-3 mt-2">
                <div class="col-md-6">
                    <div class="info-card">
                        <h6 class="info-label">Target Produksi</h6>
                        <h5 class="info-value">
                            <i class="bi bi-box-seam me-2"></i>
                            <?php echo htmlspecialchars($header_info['jumlah_unit']); ?> Unit
                        </h5>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="info-card">
                        <h6 class="info-label">Status Pengerjaan</h6>
                        <div class="d-flex align-items-center gap-2">
                            <?php
                                $status_pengerjaan_badge = 'status-pending';
                                $status_icon = 'bi-pause-circle';
                                if ($header_info['status_pengerjaan'] == 'Sedang Dikerjakan') {
                                    $status_pengerjaan_badge = 'status-progress';
                                    $status_icon = 'bi-play-circle';
                                }
                            ?>
                            <span class="status-badge-large <?php echo $status_pengerjaan_badge; ?>">
                                <i class="bi <?php echo $status_icon; ?> me-2"></i>
                                <?php echo htmlspecialchars($header_info['status_pengerjaan']); ?>
                            </span>
                            <button type="button" 
                                    class="btn btn-sm btn-outline-light rounded-pill px-3" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#ubahStatusModal"
                                    title="Ubah Status">
                                <i class="bi bi-pencil-square me-1"></i>
                                Ubah
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 text-end">
            <div class="header-icon-wrapper">
                <div class="stat-icon icon-primary d-inline-flex">
                    <i class="bi bi-box-seam"></i>
                </div>
            </div>
        </div>
    </div>
</div>



    <div class="stats-cards">
        <div class="stat-card">
            <div class="stat-icon icon-primary">
                <i class="bi bi-list-task"></i>
            </div>
            <h6 class="text-muted mb-1">Total Material</h6>
            <h3 class="fw-bold mb-0"><?php echo $stats['total_material'] ?: 0; ?> Items</h3>
        </div>
        <div class="stat-card">
            <div class="stat-icon icon-success">
                <i class="bi bi-check-circle"></i>
            </div>
            <h6 class="text-muted mb-1">Terpenuhi</h6>
            <h3 class="fw-bold mb-0 text-success"><?php echo $stats['terpenuhi'] ?: 0; ?> Items</h3>
        </div>
        <div class="stat-card">
            <div class="stat-icon icon-warning">
                <i class="bi bi-exclamation-triangle"></i>
            </div>
            <h6 class="text-muted mb-1">Belum Terpenuhi</h6>
            <h3 class="fw-bold mb-0 text-warning"><?php echo $stats['belum_terpenuhi'] ?: 0; ?> Items</h3>
        </div>
    </div>

    <?php if (isset($_GET['status'])): ?>
    <div style="padding: 0 2rem;">
        <div class="alert alert-<?php echo $_GET['status'] == 'success' ? 'success' : 'danger'; ?> alert-modern alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($_GET['message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    </div>
    <?php endif; ?>

    <div class="action-section">
<button type="button" id="tombol-accordion-material" class="action-header text-start w-100 border-0" data-bs-target="#actionContent" aria-expanded="false" aria-controls="actionContent">
    <div class="d-flex align-items-center">
        <i class="bi bi-plus-circle-dotted me-3 fs-4"></i>
        <div>
            <h5 class="mb-0">Opsi Tambah / Perbarui Material</h5>
            <small class="opacity-75">Klik untuk membuka opsi import dan input manual</small>
        </div>
        <i class="bi bi-chevron-down ms-auto fs-5"></i>
    </div>
</button>
        
        <div id="actionContent" class="collapse">
            <div class="action-content">
                <div class="row g-4">
                    <div class="col-lg-6">
                        <div class="import-section">
                            <div class="d-flex align-items-center mb-3">
                                <div class="stat-icon icon-success me-3" style="width: 40px; height: 40px;">
                                    <i class="bi bi-file-earmark-arrow-up"></i>
                                </div>
                                <h6 class="mb-0 fw-bold">Import dari Excel</h6>
                            </div>
                            
                            <div class="mb-4">
                                <a href="download_template.php?id_target=<?php echo $id_target; ?>" class="btn btn-success-modern btn-modern">
                                    <i class="bi bi-file-earmark-excel me-2"></i>
                                    Download Template
                                </a>
                            </div>
                            
                            <form action="proses_impor_material.php" method="post" enctype="multipart/form-data">
                                <input type="hidden" name="id_target" value="<?php echo $id_target; ?>">
                                <input type="hidden" name="id_alur" value="<?php echo $id_alur; ?>">
                                <div class="mb-3">
                                    <label for="excel_file" class="form-label fw-semibold">Pilih File Excel</label>
                                    <input class="form-control" type="file" name="excel_file" id="excel_file" required accept=".xlsx, .xls">
                                    <div class="form-text">Format yang didukung: .xlsx, .xls</div>
                                </div>
                                <div class="mb-4">
                                    <label for="nama_sheet" class="form-label fw-semibold">Nama Sheet</label>
                                    <input type="text" class="form-control" name="nama_sheet" id="nama_sheet" placeholder="Contoh: Table 1 (2)" required>
                                </div>
                                <button type="submit" class="btn btn-primary-modern btn-modern w-100" name="impor_material">
                                    <i class="bi bi-upload me-2"></i>
                                    Mulai Import
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <div class="col-lg-6">
                        <div class="manual-section">
                            <div class="d-flex align-items-center mb-3">
                                <div class="stat-icon icon-primary me-3" style="width: 40px; height: 40px;">
                                    <i class="bi bi-keyboard"></i>
                                </div>
                                <h6 class="mb-0 fw-bold">Input Manual</h6>
                            </div>
                            
                            <form action="proses_manual_material.php" method="post">
                                <input type="hidden" name="id_target" value="<?php echo $id_target; ?>">
                                <input type="hidden" name="id_alur" value="<?php echo $id_alur; ?>">
                                <div class="mb-3">
                                    <label for="id_komponen_manual" class="form-label fw-semibold">Pilih Komponen</label>
                                    <select class="form-select" id="id_komponen_manual" name="id_komponen" required>
                                        <option></option> 
                                        <?php foreach($all_komponen as $komp): ?>
                                            <option value="<?php echo $komp['id_komponen']; ?>"><?php echo htmlspecialchars($komp['nama_komponen']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-4">
                                    <label for="jumlah_per_unit_manual" class="form-label fw-semibold">Jumlah per Unit</label>
                                    <input type="number" class="form-control" name="jumlah_per_unit" id="jumlah_per_unit_manual" min="1" required placeholder="Masukkan jumlah">
                                </div>
                                <button type="submit" class="btn btn-info-modern btn-modern w-100" name="tambah_manual">
                                    <i class="bi bi-plus-lg me-2"></i>
                                    Tambahkan Komponen
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($materials)): ?>
    <div style="padding: 0 2rem;">
        <div class="date-picker-section">
            <h5 class="mb-3">
                <i class="bi bi-calendar-check me-2"></i>
                Pilih Tanggal Laporan
            </h5>
            <div class="row align-items-end g-3">
                <div class="col-md-4">
                    <div class="input-modern">
                        <input type="date" id="tanggal_laporan" name="tanggal_laporan" required>
                    </div>
                </div>
                <div class="col-md-8">
                    <button type="button" class="quick-action-btn" id="btnHariIni">
                        <i class="bi bi-calendar-day me-1"></i>
                        Hari Ini
                    </button>
                    <button type="button" class="quick-action-btn" id="btnKemarin">
                        <i class="bi bi-calendar-minus me-1"></i>
                        Kemarin
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div style="padding: 0 2rem;">
        <div class="filter-section">
            <form action="" method="GET">
                <input type="hidden" name="id_target" value="<?php echo $id_target; ?>">
                <input type="hidden" name="id_alur" value="<?php echo $id_alur; ?>">
                
                <div class="row align-items-center g-3">
                    <div class="col-md-3">
                        <h6 class="mb-2 fw-semibold">
                            <i class="bi bi-funnel me-2"></i>
                            Filter & Pencarian
                        </h6>
                    </div>
                    <div class="col-md-3">
                        <select name="status_filter" class="form-select" onchange="this.form.submit()">
                            <option value="semua" <?php if ($status_filter == 'semua') echo 'selected'; ?>>Semua Status</option>
                            <option value="belum_terpenuhi" <?php if ($status_filter == 'belum_terpenuhi') echo 'selected'; ?>>Belum Terpenuhi</option>
                            <option value="terpenuhi" <?php if ($status_filter == 'terpenuhi') echo 'selected'; ?>>Terpenuhi</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <div class="input-group">
                            <input type="text" name="search" class="form-control" placeholder="Cari nama komponen..." value="<?php echo htmlspecialchars($search_query); ?>">
                            <button class="btn btn-primary-modern" type="submit">
                                <i class="bi bi-search"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div style="padding: 0 2rem 2rem;">
        <?php if (empty($materials)): ?>
            <div class="table-modern">
                <div class="p-5 text-center">
                    <div class="stat-icon icon-primary mx-auto mb-3">
                        <i class="bi bi-inbox"></i>
                    </div>
                    <h5 class="text-muted">
                        <?php if (!empty($search_query) || $status_filter != 'semua'): ?>
                            Tidak ada material yang cocok dengan kriteria filter Anda.
                        <?php else: ?>
                            Belum ada data material. Silakan impor atau tambahkan manual.
                        <?php endif; ?>
                    </h5>
                </div>
            </div>
        <?php else: ?>
            
            <?php 
            $is_form_disabled = ($header_info['status_pengerjaan'] === 'Pending');

            if ($is_form_disabled) {
                // Tampilkan pesan peringatan yang jelas jika form dinonaktifkan
                echo '<div class="alert alert-warning d-flex align-items-center" role="alert">
                          <i class="bi bi-exclamation-triangle-fill me-2 fs-4"></i>
                          <div>
                              <strong>Input Laporan Diblokir.</strong> Status pengerjaan untuk target ini masih "Pending". 
                              Ubah status menjadi "Sedang Dikerjakan" untuk dapat menginput progres harian.
                          </div>
                      </div>';
            }
            ?>
            <form action="proses_laporan_harian.php" method="POST" id="laporanHarianForm">
                <input type="hidden" name="tanggal_laporan" id="hidden_tanggal_laporan">
                <input type="hidden" name="id_target" value="<?php echo $id_target; ?>">
                <input type="hidden" name="id_alur" value="<?php echo $id_alur; ?>">
                
                <div class="table-modern">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Nama Komponen</th>
                                <th>Jumlah/Unit</th>
                                <th>Total Kebutuhan</th>
                                <th>Total Selesai</th>
                                <th>Sisa</th>
                                <th>Status</th>
                                <th>Input Laporan</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = $offset + 1; foreach ($materials as $mat): 
                                $total_kebutuhan = $mat['kebutuhan_total'];
                                $total_selesai = (int) $mat['total_selesai'];
                                $sisa = $total_kebutuhan - $total_selesai;
                                $status = ($sisa <= 0) ? 'Terpenuhi' : 'Belum Terpenuhi';
                                $status_class = ($status == 'Terpenuhi') ? 'status-success' : 'status-warning';
                            ?>
                            <tr>
                                <td class="text-center fw-bold"><?php echo $no++; ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="stat-icon icon-primary me-2" style="width: 30px; height: 30px; font-size: 0.8rem;">
                                            <i class="bi bi-box"></i>
                                        </div>
                                        <strong><?php echo htmlspecialchars($mat['nama_komponen']); ?></strong>
                                    </div>
                                </td>
                                <td class="text-center"><?php echo htmlspecialchars($mat['jumlah_per_unit']); ?></td>
                                <td class="text-center"><strong><?php echo $total_kebutuhan; ?></strong></td>
                                <td class="text-center"><?php echo $total_selesai; ?></td>
                                <td class="text-center">
                                    <span class="fw-bold <?php echo ($sisa > 0) ? 'text-danger' : 'text-success'; ?>">
                                        <?php echo $sisa; ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="status-badge <?php echo $status_class; ?>"><?php echo $status; ?></span>
                                </td>
                                <td>
                                    <input 
                                        type="number" 
                                        class="form-control" 
                                        name="laporan[<?php echo $mat['id_material']; ?>]" 
                                        min="0" 
                                        max="<?php echo $sisa > 0 ? $sisa : 0; ?>" 
                                        placeholder="<?php echo ($status == 'Terpenuhi') ? 'OK' : 'Maks: ' . $sisa; ?>" 
                                        <?php echo ($status == 'Terpenuhi' || $is_form_disabled) ? 'disabled' : ''; ?>>
                                    </td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-danger btn-sm" 
                                            style="border-radius: 50px;" 
                                            title="Hapus Komponen"
                                            data-bs-toggle="modal" 
                                            data-bs-target="#hapusKomponenModal"
                                            data-nama-komponen="<?php echo htmlspecialchars($mat['nama_komponen']); ?>"
                                            data-url-hapus="proses_manual_material.php?hapus_material=<?php echo $mat['id_material']; ?>&id_target=<?php echo $id_target; ?>&id_alur=<?php echo $id_alur; ?>">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <div class="p-3 bg-light">
                        <div class="text-end">
                            <button type="submit" class="btn btn-success-modern btn-modern" <?php echo $is_form_disabled ? 'disabled' : ''; ?>>
                                <i class="bi bi-save me-2"></i>
                                Simpan Laporan Harian
                            </button>
                            </div>
                    </div>
                </div>
            </form>

            <?php if ($total_pages > 1): ?>
            <nav aria-label="Page navigation" class="mt-4">
                <ul class="pagination pagination-modern justify-content-center">
                    <?php
                        $url_params = ['id_target' => $id_target, 'id_alur' => $id_alur];
                        if (!empty($search_query)) {
                            $url_params['search'] = $search_query;
                        }
                        if ($status_filter != 'semua') {
                            $url_params['status_filter'] = $status_filter;
                        }
                    ?>
                    <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($url_params, ['page' => $page - 1])); ?>">Previous</a>
                    </li>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($url_params, ['page' => $i])); ?>"><?php echo $i; ?></a>
                    </li>
                    <?php endfor; ?>

                    <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($url_params, ['page' => $page + 1])); ?>">Next</a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>

        <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="hapusKomponenModal" tabindex="-1" aria-labelledby="hapusKomponenModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 20px;">
            <div class="modal-header text-white" style="background: var(--warning-gradient); border-top-left-radius: 20px; border-top-right-radius: 20px;">
                <h5 class="modal-title" id="hapusKomponenModalLabel">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> Konfirmasi Hapus Komponen
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="text-center py-3">
                    <i class="bi bi-trash3-fill text-danger" style="font-size: 4rem;"></i>
                    <h5 class="mt-3 mb-3">Apakah Anda Yakin Ingin Menghapus?</h5>
                    <p class="text-muted mb-2">
                        Anda akan menghapus komponen berikut dari target produksi:
                    </p>
                    <div class="alert alert-danger">
                        <strong id="hapus_nama_komponen"></strong>
                    </div>
                    <p class="text-danger fw-bold">
                        <i class="bi bi-info-circle me-1"></i>
                        Tindakan ini juga akan menghapus semua riwayat laporan harian yang terkait dan tidak dapat dibatalkan!
                    </p>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0 justify-content-center">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius: 50px; padding: 0.75rem 1.5rem;">
                    <i class="bi bi-x-lg me-1"></i> Batal
                </button>
                <a id="linkHapusKomponen" href="#" class="btn btn-danger" style="border-radius: 50px; padding: 0.75rem 1.5rem;">
                    <i class="bi bi-trash me-1"></i> Ya, Hapus
                </a>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="simpanLaporanModal" tabindex="-1" aria-labelledby="simpanLaporanModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 20px;">
            <div class="modal-header text-white" style="background: var(--primary-gradient); border-top-left-radius: 20px; border-top-right-radius: 20px;">
                <h5 class="modal-title" id="simpanLaporanModalLabel">
                    <i class="bi bi-check-circle-fill me-2"></i> Konfirmasi Laporan Harian
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="p-3">
                    <h5 class="text-center mb-3">Apakah data yang Anda masukkan sudah benar?</h5>
                    <div class="alert alert-light border">
                        <p class="mb-1"><strong><i class="bi bi-calendar-check me-2"></i>Tanggal Laporan:</strong></p>
                        <h6 id="modalTanggalLaporan" class="fw-bold"></h6>
                        <hr>
                        <p class="mb-1"><strong><i class="bi bi-box-seam me-2"></i>Detail Produksi:</strong></p>
                        <pre id="modalRingkasanProduksi" class="mb-0" style="white-space: pre-wrap; font-family: inherit; font-size: 0.95rem;"></pre>
                    </div>
                    <p class="text-muted text-center mt-3">
                        <i class="bi bi-info-circle me-1"></i>
                        Pastikan kembali data yang Anda kirim sudah sesuai.
                    </p>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0 justify-content-center">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius: 50px; padding: 0.75rem 1.5rem;">
                    <i class="bi bi-x-lg me-1"></i> Periksa Kembali
                </button>
                <button type="button" id="tombolKonfirmasiSimpan" class="btn btn-success" style="border-radius: 50px; padding: 0.75rem 1.5rem;">
                    <i class="bi bi-send-check me-1"></i> Ya, Simpan Laporan
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="validasiModal" tabindex="-1" aria-labelledby="validasiModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 20px;">

            <div class="modal-header text-white" style="background: linear-gradient(135deg, #f5a623 0%, #f76b1c 100%); border-top-left-radius: 20px; border-top-right-radius: 20px;">
                <h5 class="modal-title" id="validasiModalLabel">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> Perhatian
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <div class="text-center py-3">
                    <i class="bi bi-exclamation-circle text-warning" style="font-size: 4rem;"></i>
                    <h5 id="modalValidasiPesan" class="mt-3 mb-2"></h5>
                </div>
            </div>

            <div class="modal-footer border-0 pt-0 justify-content-center">
                <button type="button" class="btn btn-warning text-white" data-bs-dismiss="modal" style="border-radius: 50px; padding: 0.75rem 2rem;">
                    <i class="bi bi-check-lg me-1"></i> Mengerti
                </button>
            </div>

        </div>
    </div>
</div>

<div class="modal fade" id="ubahStatusModal" tabindex="-1" aria-labelledby="ubahStatusModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 20px; overflow: hidden;">
            <div class="modal-header text-white" style="background: var(--primary-gradient);">
                <h5 class="modal-title" id="ubahStatusModalLabel">
                    <i class="bi bi-arrows-move me-2"></i> Ubah Status Pengerjaan
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <p class="text-muted mb-4">
                    <i class="bi bi-info-circle me-2"></i>
                    Pilih status pengerjaan yang sesuai dengan kondisi produksi saat ini:
                </p>
                
                <div class="status-options">
                    <div class="modal-status-option status-option-active" 
                         data-status="Sedang Dikerjakan"
                         onclick="selectStatus(this)">
                        <div class="d-flex align-items-center">
                            <div class="status-icon-large text-success me-3">
                                <i class="bi bi-play-circle-fill"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="mb-1 fw-bold">
                                    <i class="bi bi-check-circle me-1"></i>
                                    Sedang Dikerjakan
                                </h6>
                                <p class="status-description mb-0">
                                    Produksi aktif berjalan, dapat input laporan harian
                                </p>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="statusRadio" value="Sedang Dikerjakan">
                            </div>
                        </div>
                    </div>
                    
                    <div class="modal-status-option status-option-pending" 
                         data-status="Pending"
                         onclick="selectStatus(this)">
                        <div class="d-flex align-items-center">
                            <div class="status-icon-large text-warning me-3">
                                <i class="bi bi-pause-circle-fill"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="mb-1 fw-bold">
                                    <i class="bi bi-exclamation-circle me-1"></i>
                                    Pending
                                </h6>
                                <p class="status-description mb-0">
                                    Produksi ditunda, input laporan dinonaktifkan
                                </p>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="statusRadio" value="Pending">
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="alert alert-info mt-4 d-flex align-items-start" style="border-radius: 12px;">
                    <i class="bi bi-lightbulb-fill me-2 fs-5"></i>
                    <small>
                        <strong>Catatan:</strong> Perubahan status akan langsung mempengaruhi kemampuan input laporan harian.
                    </small>
                </div>
            </div>
            <div class="modal-footer border-0 justify-content-center pb-4">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius: 50px; padding: 0.75rem 2rem;">
                    <i class="bi bi-x-lg me-1"></i> Batal
                </button>
                <button type="button" id="btnKonfirmasiStatus" class="btn btn-primary" style="border-radius: 50px; padding: 0.75rem 2rem; background: var(--primary-gradient); border: none;">
                    <i class="bi bi-check-lg me-1"></i> Ubah Status
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    
    // --- PERBAIKAN FITUR: Deteksi Perubahan Data Input (Dirty Form) yang Lebih Cerdas ---
    let formIsDirty = false;

    // Fungsi untuk memeriksa ulang seluruh input
    // Jika semua input kosong atau bernilai 0, maka form dianggap "bersih" (tidak dirty)
    function updateDirtyState() {
        let hasData = false;
        const inputFields = document.querySelectorAll('input[type="number"][name^="laporan"]');
        
        for (let input of inputFields) {
            // Cek jika value ada dan lebih dari 0
            if (input.value !== '' && parseFloat(input.value) > 0) {
                hasData = true;
                break; // Cukup satu input terisi untuk dianggap dirty
            }
        }
        formIsDirty = hasData;
    }

    // Terapkan event listener ke setiap input number di tabel
    const inputFields = document.querySelectorAll('input[type="number"][name^="laporan"]');
    inputFields.forEach(input => {
        // Gunakan 'input' event agar real-time saat mengetik/menghapus
        input.addEventListener('input', updateDirtyState);
        // 'change' event untuk cover kasus seperti paste lewat mouse atau autofill
        input.addEventListener('change', updateDirtyState);
    });

    // Cegah navigasi jika form kotor (belum disimpan)
    document.querySelectorAll('a').forEach(link => {
        link.addEventListener('click', function(e) {
            // Kecualikan link yang memang untuk aksi (seperti hapus, download, atau modal trigger)
            if (formIsDirty && 
                !this.classList.contains('btn-danger') && 
                !this.hasAttribute('data-bs-toggle') && 
                this.getAttribute('href') !== '#') {
                
                e.preventDefault(); // Batalkan navigasi
                const targetUrl = this.getAttribute('href');

                Swal.fire({
                    title: 'Data Belum Disimpan!',
                    text: "Anda memiliki input laporan harian yang belum disimpan. Jika Anda meninggalkan halaman ini, data tersebut akan hilang. Yakin ingin keluar?",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#f5576c',
                    cancelButtonColor: '#667eea',
                    confirmButtonText: 'Ya, Keluar',
                    cancelButtonText: 'Batal, Saya Simpan Dulu'
                }).then((result) => {
                    if (result.isConfirmed) {
                        formIsDirty = false; // Reset flag agar bisa navigasi
                        window.location.href = targetUrl;
                    }
                });
            }
        });
    });
    // --- AKHIR FITUR ---

    // --- FITUR: Prompt Ubah Status Setelah Sukses ---
    <?php if (isset($_GET['status']) && $_GET['status'] == 'success' && $header_info['status_pengerjaan'] == 'Sedang Dikerjakan'): ?>
        setTimeout(() => {
            Swal.fire({
                title: 'Laporan Tersimpan!',
                text: "Anda telah berhasil menyimpan laporan harian. Apakah Anda ingin mengubah status pengerjaan (misalnya menjadi Pending) sekarang?",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#667eea',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Ya, Ubah Status',
                cancelButtonText: 'Tidak, Tetap Lanjut'
            }).then((result) => {
                if (result.isConfirmed) {
                    var myModal = new bootstrap.Modal(document.getElementById('ubahStatusModal'));
                    myModal.show();
                }
            });
        }, 1000); // Muncul sedikit setelah alert sukses
    <?php endif; ?>
    // --- AKHIR FITUR ---

    const accordionElement = document.getElementById('actionContent');
    let isSelect2Initialized = false;

    // Initialize Select2 when accordion is opened
    accordionElement.addEventListener('shown.bs.collapse', function () {
        if (!isSelect2Initialized) {
            $('#id_komponen_manual').select2({
                placeholder: "Ketik untuk mencari komponen...",
                theme: "bootstrap-5",
                        dropdownParent: $('body') // <--- UBAH MENJADI INI

            });
            isSelect2Initialized = true;
        }
    });
    $('#tombol-accordion-material').on('click', function (e) {
        // Baris ini sangat penting: Hentikan skrip lain (dari template) agar tidak ikut berjalan setelah tombol ini diklik.
        e.stopPropagation();

        // Panggil fungsi 'collapse' dari Bootstrap secara manual dengan perintah 'toggle'.
        var targetCollapse = $(this).attr('data-bs-target');
        $(targetCollapse).collapse('toggle');
    });


    // Date picker functionality
    const tanggalInput = document.getElementById('tanggal_laporan');
    const btnHariIni = document.getElementById('btnHariIni');
    const btnKemarin = document.getElementById('btnKemarin');
    const form = document.getElementById('laporanHarianForm');

    const formatDate = (date) => {
        let d = new Date(date),
            month = '' + (d.getMonth() + 1),
            day = '' + d.getDate(),
            year = d.getFullYear();
        if (month.length < 2) month = '0' + month;
        if (day.length < 2) day = '0' + day;
        return [year, month, day].join('-');
    }

    const today = new Date();
    const yesterday = new Date();
    yesterday.setDate(yesterday.getDate() - 1);

    const maxDate = formatDate(today);
    
    if (tanggalInput) {
        tanggalInput.max = maxDate;
        tanggalInput.value = maxDate; 
    }

    if (btnHariIni) {
        btnHariIni.addEventListener('click', () => { 
            tanggalInput.value = formatDate(today); 
        });
    }
    
    if (btnKemarin) {
        btnKemarin.addEventListener('click', () => { 
            tanggalInput.value = formatDate(yesterday); 
        });
    }
    
    // =======================================================
    // PENAMBAHAN BARU: Fungsi helper untuk menampilkan modal
    // =======================================================
    function tampilkanPesanValidasi(pesan) {
        const modalPesanElement = document.getElementById('modalValidasiPesan');
        if (modalPesanElement) {
            modalPesanElement.textContent = pesan;
            const validasiModal = new bootstrap.Modal(document.getElementById('validasiModal'));
            validasiModal.show();
        }
    }

    // =======================================================
    // PERUBAHAN: Form submission handling dengan Modal
    // =======================================================
    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();

            const tanggal = tanggalInput.value;
                    document.getElementById('hidden_tanggal_laporan').value = tanggal;

            if (!tanggal) {
                // GANTI ALERT LAMA
                tampilkanPesanValidasi('Tanggal laporan wajib diisi!');
                return;
            }

            let ringkasan = '';
            let adaInput = false;
            const inputs = form.querySelectorAll('input[type="number"]:not([disabled])');
            inputs.forEach(input => {
                if (input.value && parseInt(input.value, 10) > 0) {
                    adaInput = true;
                    const namaKomponen = input.closest('tr').querySelector('td:nth-child(2) strong').innerText;
                    ringkasan += `- ${namaKomponen}: ${input.value} PCS\n`;
                }
            });

            if (!adaInput) {
                // GANTI ALERT LAMA
                tampilkanPesanValidasi('Mohon isi minimal satu jumlah material yang selesai dikerjakan.');
                return;
            }

            // Logika untuk menampilkan modal konfirmasi simpan (tetap sama)
            document.getElementById('modalTanggalLaporan').textContent = tanggal;
            document.getElementById('modalRingkasanProduksi').textContent = ringkasan;

            const simpanModal = new bootstrap.Modal(document.getElementById('simpanLaporanModal'));
            simpanModal.show();
        });
    }
    
    // =======================================================
    // PENAMBAHAN BARU: Menangani klik tombol konfirmasi di modal
    // =======================================================
    const tombolKonfirmasi = document.getElementById('tombolKonfirmasiSimpan');
    if (tombolKonfirmasi) {
        tombolKonfirmasi.addEventListener('click', function() {
            // Reset dirty form flag karena kita memang mau submit
            formIsDirty = false;

            // Tampilkan loading state di tombol utama
            const submitBtn = form.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<span class="loading-spinner me-2"></span>Menyimpan...';
            submitBtn.disabled = true;

            // Tutup modal
            const simpanModal = bootstrap.Modal.getInstance(document.getElementById('simpanLaporanModal'));
            simpanModal.hide();
            
            // Kirim (submit) form setelah jeda singkat
            setTimeout(() => {
                form.submit();
            }, 500); // Jeda 0.5 detik agar animasi modal terlihat mulus
        });
    }
    
    // JavaScript untuk modal hapus (Existing)
    const hapusKomponenModal = document.getElementById('hapusKomponenModal');
    if (hapusKomponenModal) {
        hapusKomponenModal.addEventListener('show.bs.modal', function (event) {
            // Tombol yang memicu modal
            const button = event.relatedTarget;

            // Ambil data dari atribut data-*
            const namaKomponen = button.getAttribute('data-nama-komponen');
            const urlHapus = button.getAttribute('data-url-hapus');

            // Perbarui konten modal
            const modalNamaKomponen = hapusKomponenModal.querySelector('#hapus_nama_komponen');
            const linkHapus = hapusKomponenModal.querySelector('#linkHapusKomponen');

            modalNamaKomponen.textContent = namaKomponen;
            linkHapus.setAttribute('href', urlHapus);
        });
    }

    // Input validation for number fields
    // Input validation for number fields with modern alert
document.querySelectorAll('input[type="number"][max]').forEach(input => {
    input.addEventListener('input', function() {
        // Menggunakan 'parseFloat' untuk mengakomodasi angka desimal jika diperlukan
        const max = parseFloat(this.getAttribute('max')); 
        let value = parseFloat(this.value);

        // Jika input bukan angka (kosong), jangan lakukan apa-apa
        if (isNaN(value)) {
            return;
        }
        
        // Memastikan nilai tidak kurang dari 0
        if (value < 0) {
            this.value = 0;
            return;
        }

        // Jika nilai melebihi batas maksimal
        if (value > max) {
            // Menampilkan pop-up SweetAlert2 yang modern
            Swal.fire({
                title: 'Input Melebihi Batas!',
                text: `Jumlah yang Anda masukkan tidak boleh melebihi sisa kebutuhan, yaitu ${max}.`,
                icon: 'warning', // Ikon peringatan
                confirmButtonText: 'Mengerti',
                confirmButtonColor: '#667eea', // Warna tombol sesuai tema Anda
                background: 'rgba(255, 255, 255, 0.98)', // Latar belakang yang sedikit transparan
                backdrop: `
                    rgba(0,0,0,0.4)
                    left top
                    no-repeat
                `
            });
            
            // Otomatis mengoreksi nilai input kembali ke nilai maksimal
            this.value = max; 
        }
    });
});

    // Animate stats on load
    function animateStats() {
        const statValues = document.querySelectorAll('.stat-card h3');
        statValues.forEach(stat => {
            const originalText = stat.textContent;
            const value = parseInt(originalText.match(/\d+/)[0]);
            const suffix = originalText.replace(/\d+/, '');
            stat.textContent = '0' + suffix;
            
            let current = 0;
            const increment = value / 20;
            const timer = setInterval(() => {
                current += increment;
                if (current >= value) {
                    stat.textContent = value + suffix;
                    clearInterval(timer);
                } else {
                    stat.textContent = Math.floor(current) + suffix;
                }
            }, 50);
        });
    }

    // Initialize animations
    setTimeout(animateStats, 500);
});


// Variable untuk menyimpan status yang dipilih
let selectedStatus = 'Sedang Dikerjakan';

// Fungsi untuk memilih status di modal
function selectStatus(element) {
    // Hapus class selected dari semua opsi
    document.querySelectorAll('.modal-status-option').forEach(option => {
        option.classList.remove('border-primary');
        option.style.transform = 'scale(1)';
    });
    
    // Tambahkan class selected ke opsi yang dipilih
    element.classList.add('border-primary');
    element.style.transform = 'scale(1.02)';
    
    // Update radio button
    const radio = element.querySelector('input[type="radio"]');
    radio.checked = true;
    
    // Simpan status yang dipilih
    selectedStatus = element.getAttribute('data-status');
}

// Fungsi untuk update status (dipanggil saat tombol konfirmasi diklik)
document.getElementById('btnKonfirmasiStatus')?.addEventListener('click', function() {
    const statusBaru = selectedStatus;
    const idTarget = <?php echo $id_target; ?>;
    const idAlur = <?php echo $id_alur; ?>; // Variabel id_alur ditambahkan
    
    // Disable button dan tampilkan loading
    this.innerHTML = '<span class="loading-spinner me-2"></span>Mengubah Status...';
    this.disabled = true;
    
    const formData = new FormData();
    formData.append('id_target', idTarget);
    formData.append('id_alur', idAlur); // id_alur ditambahkan ke data yang akan dikirim
    formData.append('status', statusBaru);

    // Pastikan file proses_update_status_pengerjaan.php sudah diupdate untuk menerima dan memproses `id_alur`
    fetch('proses_update_status_pengerjaan.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Tutup modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('ubahStatusModal'));
            modal.hide();
            
            // Tampilkan notifikasi sukses dengan SweetAlert2
            Swal.fire({
                title: 'Berhasil!',
                text: data.message,
                icon: 'success',
                confirmButtonText: 'OK',
                confirmButtonColor: '#667eea',
                timer: 2000,
                timerProgressBar: true
            }).then(() => {
                // Reload halaman setelah notifikasi
                location.reload();
            });
        } else {
            // Tampilkan error
            Swal.fire({
                title: 'Gagal!',
                text: data.message,
                icon: 'error',
                confirmButtonText: 'OK',
                confirmButtonColor: '#f5576c'
            });
            
            // Reset button
            this.innerHTML = '<i class="bi bi-check-lg me-1"></i> Ubah Status';
            this.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire({
            title: 'Error!',
            text: 'Terjadi kesalahan saat menghubungi server.',
            icon: 'error',
            confirmButtonText: 'OK',
            confirmButtonColor: '#f5576c'
        });
        
        // Reset button
        this.innerHTML = '<i class="bi bi-check-lg me-1"></i> Ubah Status';
        this.disabled = false;
    });
});

// Set status default saat modal dibuka
document.getElementById('ubahStatusModal')?.addEventListener('shown.bs.modal', function() {
    const currentStatus = '<?php echo $header_info['status_pengerjaan']; ?>';
    const defaultOption = document.querySelector(`.modal-status-option[data-status="${currentStatus}"]`);
    if (defaultOption) {
        selectStatus(defaultOption);
    }
});
</script>

<?php include '../../../templates/footer.php'; ?>