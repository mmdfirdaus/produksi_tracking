<?php
$page_title = 'Input Laporan Harian';
include_once '../../../templates/header_admin.php';

// Validasi ID dari URL
if (!isset($_GET['id_target']) || !is_numeric($_GET['id_target']) || !isset($_GET['id_alur']) || !is_numeric($_GET['id_alur'])) {
    echo "<script>alert('ID Target atau Alur tidak valid!'); window.location.href='../dashboard.php';</script>";
    exit;
}
$id_target = (int)$_GET['id_target'];
$id_alur = (int)$_GET['id_alur'];
$id_user_admin = $_SESSION['user_id'];

try {
    // 1. Cek hak akses admin
    $check_access_stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM admin_tahapan_access
        WHERE id_user = :id_user AND id_tahapan = :id_alur
    ");
    $check_access_stmt->execute([
        ':id_user' => $id_user_admin, 
        ':id_alur' => $id_alur
    ]);
    $is_allowed = $check_access_stmt->fetchColumn();
    
    // 2. Ambil info header DAN status pengerjaan
    $header_stmt = $pdo->prepare("
        SELECT 
            pt.nama_permintaan, pt.jumlah_unit,pt.no_spk, mb.nama_barang,mb.kode_barang, ma.nama_alur, pt.id_barang,
            COALESCE(tas.status_pengerjaan, 'Pending') AS status_pengerjaan
        FROM production_targets pt
        JOIN master_barang mb ON pt.id_barang = mb.id_barang
        JOIN master_alur ma ON ma.id_alur = :id_alur
        LEFT JOIN target_alur_status tas ON pt.id_target = tas.id_target AND ma.id_alur = tas.id_alur
        WHERE pt.id_target = :id_target
    ");
    $header_stmt->execute([':id_target' => $id_target, ':id_alur' => $id_alur]);
    $header_info = $header_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$header_info) {
        throw new Exception("Data Target atau Alur tidak ditemukan.");
    }
    
    // 3. Buat variabel untuk menonaktifkan form berdasarkan status
    $is_form_disabled = ($header_info['status_pengerjaan'] === 'Pending');

    // Query dasar untuk mengambil komponen dan progresnya
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
    
    // Hitung statistik untuk kartu di atas
    $stats_stmt = $pdo->prepare("
        SELECT 
            COUNT(tm.id_material) as total_material,
            SUM(CASE WHEN (tm.jumlah_per_unit * pt.jumlah_unit) <= COALESCE(lh.total_selesai, 0) THEN 1 ELSE 0 END) as terpenuhi,
            SUM(CASE WHEN (tm.jumlah_per_unit * pt.jumlah_unit) > COALESCE(lh.total_selesai, 0) THEN 1 ELSE 0 END) as belum_terpenuhi
        " . $base_sql
    );
    $stats_stmt->execute($params);
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

    // Ambil semua komponen untuk ditampilkan di tabel
    $material_stmt = $pdo->prepare("
        SELECT 
            tm.id_material, mk.nama_komponen, tm.jumlah_per_unit, 
            (tm.jumlah_per_unit * pt.jumlah_unit) AS kebutuhan_total,
            COALESCE(lh.total_selesai, 0) AS total_selesai
        " . $base_sql . " 
        ORDER BY mk.nama_komponen ASC
    ");
    $material_stmt->execute($params);
    $materials = $material_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    die("Database Error: " . $e->getMessage());
}
?>

<style>
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
    padding: 2rem 2rem 5rem; 
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
    margin-bottom: 1rem; 
    position: relative;
    z-index: 2;
}

.back-btn:hover { 
    background: rgba(255,255,255,0.3); 
    color: white; 
    transform: translateX(-5px); 
    text-decoration: none; 
}

.stats-cards { 
    display: grid; 
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); 
    gap: 1rem; 
    margin: -70px 2rem 2rem; 
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

.date-picker-section { 
    background: linear-gradient(145deg, #667eea, #764ba2); 
    color: white; 
    border-radius: 20px; 
    padding: 2rem; 
    margin: 0 2rem 2rem; 
    box-shadow: var(--card-shadow); 
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
    box-shadow: 0 0 0 0.2rem rgba(255, 255, 255, 0.25); 
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

.table-modern { 
    background: white; 
    border-radius: 20px; 
    overflow: hidden; 
    box-shadow: var(--card-shadow); 
    margin: 0 2rem; 
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

.form-control[disabled] { 
    background: linear-gradient(145deg, #f8f9fa 0%, #e9ecef 100%); 
    cursor: not-allowed; 
    border-color: #dee2e6; 
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

.btn-primary-modern:hover { 
    color: white; 
    transform: translateY(-2px); 
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4); 
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

.empty-state { 
    padding: 3rem; 
    text-align: center; 
}

.empty-state-icon { 
    font-size: 4rem; 
    color: #667eea; 
    margin-bottom: 1rem; 
}

.alert-modern { 
    border: none; 
    border-radius: 15px; 
    padding: 1.5rem; 
    box-shadow: var(--card-shadow); 
    margin: 0 2rem 2rem; 
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

.status-display-section {
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(10px);
    border-radius: 15px;
    padding: 1.25rem 1.5rem;
    margin-top: 1.5rem;
    border: 1px solid rgba(255, 255, 255, 0.2);
    transition: all 0.3s ease;
}

.status-display-section:hover {
    background: rgba(255, 255, 255, 0.25);
    transform: translateY(-2px);
}

.status-label-modern {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 1rem;
}

.status-label-modern i {
    font-size: 1.3rem;
}

.status-label-modern span {
    font-size: 0.85rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
    opacity: 0.9;
}

.status-content-display {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 1rem;
}

.current-status-badge-modern {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    border-radius: 50px;
    font-size: 1rem;
    font-weight: 700;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
    transition: all 0.3s ease;
    animation: pulse-status 2s ease-in-out infinite;
}

.current-status-badge-modern:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
}

.status-active {
    background: linear-gradient(135deg, #28a745, #20c997);
    color: white;
}

.status-paused {
    background: linear-gradient(135deg, #ffc107, #ff9800);
    color: white;
}

@keyframes pulse-status {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: 0.9;
    }
}

.btn-change-status-modern {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    background: rgba(255, 255, 255, 0.9);
    color: #667eea;
    border: 2px solid rgba(255, 255, 255, 0.5);
    border-radius: 50px;
    font-size: 0.9rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(255, 255, 255, 0.2);
}

.btn-change-status-modern:hover {
    background: white;
    color: #764ba2;
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(255, 255, 255, 0.4);
    border-color: white;
}

.swal2-popup {
    border-radius: 20px !important;
    padding: 0 !important;
}

.swal2-title {
    font-size: 1.5rem !important;
    font-weight: 700 !important;
    padding: 2rem 2rem 1rem !important;
}

.swal2-html-container {
    padding: 0 2rem 2rem !important;
}

.status-option-card {
    background: linear-gradient(145deg, #f8f9fa 0%, #e9ecef 100%);
    border: 2px solid #dee2e6;
    border-radius: 15px;
    padding: 1.25rem;
    cursor: pointer;
    transition: all 0.3s ease;
    margin-bottom: 1rem;
}

.status-option-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
}

.status-option-card.selected {
    border: 3px solid #667eea;
    transform: scale(1.02);
    box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
    background: linear-gradient(145deg, #e3e7ff 0%, #d4d9f9 100%);
}

.status-option-icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
    margin-right: 1rem;
    transition: all 0.3s ease;
}

.status-option-card[data-status="Sedang Dikerjakan"] .status-option-icon {
    background: linear-gradient(135deg, #28a745, #20c997);
}

.status-option-card[data-status="Pending"] .status-option-icon {
    background: linear-gradient(135deg, #ffc107, #ff9800);
}

.status-option-info h6 {
    margin: 0 0 0.25rem 0;
    font-weight: 700;
    color: #2c3e50;
}

.status-option-info p {
    margin: 0;
    font-size: 0.85rem;
    color: #6c757d;
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

/* Back to Top Button */
.back-to-top {
    position: fixed;
    bottom: 2rem;
    right: 2rem;
    width: 50px;
    height: 50px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    border: none;
    border-radius: 50%;
    cursor: pointer;
    display: none;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
    transition: all 0.3s ease;
    z-index: 9998;
    opacity: 0;
}

.back-to-top.show {
    display: flex;
    opacity: 1;
    animation: fadeInBtn 0.3s ease;
}

.back-to-top:hover {
    transform: translateY(-5px);
    box-shadow: 0 12px 35px rgba(102, 126, 234, 0.6);
}

@keyframes fadeInBtn {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

html {
    scroll-behavior: smooth;
}

@media (prefers-reduced-motion: reduce) {
    html {
        scroll-behavior: auto;
    }
}

/* ============================================
   RESPONSIVE DESIGN UNTUK MOBILE
   ============================================ */

@media (max-width: 768px) {
    .main-container {
        margin: 10px;
        border-radius: 15px;
    }
    
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
        margin-bottom: 1rem;
    }
    
    .info-card {
        padding: 0.75rem 1rem;
        margin-bottom: 0.75rem;
    }
    
    .info-label {
        font-size: 0.7rem;
        margin-bottom: 0.25rem;
    }
    
    .info-value {
        font-size: 0.9rem;
    }
    
    .status-display-section {
        padding: 1rem;
        margin-top: 1rem;
    }
    
    .status-label-modern {
        margin-bottom: 0.75rem;
    }
    
    .status-label-modern i {
        font-size: 1.1rem;
    }
    
    .status-label-modern span {
        font-size: 0.75rem;
    }
    
    .status-content-display {
        flex-direction: column;
        align-items: center;
        gap: 0.75rem;
    }
    
    .current-status-badge-modern {
        font-size: 0.85rem;
        padding: 0.6rem 1.25rem;
        justify-content: center;
    }
    
    .current-status-badge-modern i {
        font-size: 1rem;
    }
    
    .btn-change-status-modern {
        font-size: 0.85rem;
        padding: 0.6rem 1.25rem;
        width: 100%;
        justify-content: center;
    }
    
    .stats-cards {
        display: grid;
        grid-template-columns: 1fr 1fr;
        margin: -40px 1rem 1.5rem;
        gap: 0.75rem;
    }
    
    .stats-cards .stat-card:first-child {
        grid-column: 1 / span 2;
    }
    
    .stat-card {
        padding: 1rem;
    }
    
    .stat-card h6 {
        font-size: 0.75rem;
        margin-bottom: 0.5rem;
    }
    
    .stat-card h3 {
        font-size: 1.15rem;
    }
    
    .stat-icon {
        width: 40px;
        height: 40px;
        font-size: 1.2rem;
        margin-bottom: 0.75rem;
    }
    
    .alert-modern {
        margin: 0 1rem 1rem;
        padding: 1rem;
        font-size: 0.85rem;
    }
    
    .date-picker-section {
        padding: 1.25rem;
        margin: 0 1rem 1.5rem;
    }
    
    .date-picker-section h5 {
        font-size: 1rem;
        margin-bottom: 1rem;
    }
    
    .input-modern input {
        padding: 0.75rem;
        font-size: 0.9rem;
    }
    
    .date-picker-section .row.g-3 {
        gap: 0.75rem !important;
    }
    
    .quick-action-btn {
        padding: 0.6rem 1rem;
        font-size: 0.8rem;
        margin: 0;
        display: block;
        width: 100%;
        text-align: center;
    }
    
    .date-picker-section .col-md-8 {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.5rem;
    }
    
    .table-modern {
        margin: 0 1rem 1.5rem;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    
    .table-modern table {
        min-width: 900px;
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
        min-width: 150px;
    }
    
    .table-modern input[type="number"] {
        font-size: 0.85rem;
        padding: 0.5rem;
        width: 80px;
    }
    
    .table-modern .stat-icon {
        width: 30px;
        height: 30px;
        font-size: 0.8rem;
    }
    
    .status-badge {
        font-size: 0.7rem;
        padding: 0.4rem 0.75rem;
        white-space: nowrap;
    }
    
    .empty-state {
        padding: 2rem 1rem;
    }
    
    .empty-state-icon {
        font-size: 3rem;
    }
    
    .empty-state h5 {
        font-size: 0.95rem;
    }
    
    .form-control, .form-select {
        font-size: 0.9rem;
        padding: 0.6rem 0.75rem;
    }
    
    .btn-modern {
        padding: 0.6rem 1.5rem;
        font-size: 0.85rem;
    }
    
    .table-modern .p-3 {
        padding: 1rem !important;
    }
    
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
    
    .status-option-card {
        padding: 1rem;
        margin-bottom: 0.75rem;
    }
    
    .status-option-icon {
        width: 45px;
        height: 45px;
        font-size: 1.3rem;
    }
    
    .status-option-info h6 {
        font-size: 0.9rem;
    }
    
    .status-option-info p {
        font-size: 0.8rem;
    }
    
    .swal2-title {
        font-size: 1.2rem !important;
        padding: 1.5rem 1rem 0.75rem !important;
    }
    
    .swal2-html-container {
        padding: 0 1rem 1.5rem !important;
        font-size: 0.9rem;
    }
    
    .swal2-confirm, .swal2-cancel {
        font-size: 0.85rem !important;
        padding: 0.6rem 1.5rem !important;
    }
    
    /* Back to Top Button Mobile */
    .back-to-top {
        width: 45px;
        height: 45px;
        bottom: 1.5rem;
        right: 1.5rem;
        font-size: 1.3rem;
    }
}

@media (max-width: 576px) {
    .header-section h2 {
        font-size: 1.1rem;
    }
    
    .stat-card h3 {
        font-size: 1.1rem;
    }
    
    .stat-card h6 {
        font-size: 0.7rem;
    }
    
    .btn-modern {
        padding: 0.5rem 1rem;
        font-size: 0.8rem;
    }
    
    .table-modern table {
        font-size: 0.75rem;
    }
    
    .info-card {
        padding: 0.6rem 0.85rem;
    }
    
    .info-label {
        font-size: 0.65rem;
    }
    
    .info-value {
        font-size: 0.85rem;
    }
    
    .current-status-badge-modern {
        font-size: 0.75rem;
        padding: 0.5rem 1rem;
    }
    
    .btn-change-status-modern {
        font-size: 0.75rem;
        padding: 0.5rem 1rem;
    }
    
    .status-label-modern span {
        font-size: 0.7rem;
    }
    
    /* Back to Top Button Extra Small */
    .back-to-top {
        width: 40px;
        height: 40px;
        bottom: 1rem;
        right: 1rem;
        font-size: 1.2rem;
    }
}

@media (min-width: 769px) and (max-width: 992px) {
    .stats-cards {
        grid-template-columns: repeat(3, 1fr);
    }
    
    .header-section {
        padding: 1.75rem;
        padding-bottom: 4.5rem;
    }
}

@media (max-width: 768px) and (orientation: landscape) {
    .header-section {
        padding: 1rem;
        padding-bottom: 3rem;
    }
    
    .stats-cards {
        margin: -30px 1rem 1rem;
    }
    
    .modal-dialog {
        max-height: 90vh;
        overflow-y: auto;
    }
}
</style>

<!-- Back to Top Button -->
<button class="back-to-top" id="backToTopBtn" aria-label="Kembali ke atas">
    <i class="bi bi-arrow-up"></i>
</button>

<div class="main-container">
    <div class="header-section">
        <a href="../alur_produksi.php?id_target=<?php echo $id_target; ?>" class="back-btn">
            <i class="bi bi-arrow-left"></i>
            <span>Kembali ke Alur Produksi</span>
        </a>
        
        <div class="row align-items-center">
            <div class="col-md-8">
                <h2 class="fw-bold mb-3">
                    <i class="bi bi-pencil-square me-2"></i>
                    Input Laporan: <?php echo htmlspecialchars($header_info['nama_alur']); ?>
                </h2>
                
                <div class="row g-3">
    <div class="col-md-6">
        <div class="info-card">
            <h6 class="info-label">Nama Barang</h6>
            <h5 class="info-value"><?php echo htmlspecialchars($header_info['nama_barang']); ?></h5>
            <div class="mt-1">
                <span class="badge bg-white text-primary" style="font-size: 0.75rem; font-weight: 600;">
                    <i class="bi bi-upc-scan me-1"></i><?php echo htmlspecialchars($header_info['kode_barang']); ?>
                </span>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="info-card">
            <h6 class="info-label">PO / Nama Permintaan</h6>
            <h5 class="info-value"><?php echo htmlspecialchars($header_info['nama_permintaan']); ?></h5>
            <div class="mt-1 text-white-50" style="font-size: 0.85rem;">
                <i class="bi bi-hash me-1"></i>SPK: <strong><?php echo htmlspecialchars($header_info['no_spk']); ?></strong>
            </div>
        </div>
    </div>
</div>
                
                <div class="row g-3 mt-2">
                    <div class="col-md-12">
                        <div class="info-card">
                            <h6 class="info-label">Target Produksi</h6>
                            <h5 class="info-value">
                                <i class="bi bi-box-seam me-2"></i>
                                <?php echo number_format($header_info['jumlah_unit']); ?> Unit
                            </h5>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 text-end">
                <div class="stat-icon icon-primary d-inline-flex">
                    <i class="bi bi-clipboard-data"></i>
                </div>
            </div>
        </div>
        
        <div class="status-display-section">
            <div class="status-label-modern">
                <i class="bi bi-gear-wide-connected"></i>
                <span>Status Pengerjaan Tahapan</span>
            </div>
            <div class="status-content-display">
                <div>
                    <?php
                        $status_badge_class_modern = ($header_info['status_pengerjaan'] == 'Sedang Dikerjakan') 
                            ? 'status-active' 
                            : 'status-paused';
                        $status_icon_modern = ($header_info['status_pengerjaan'] == 'Sedang Dikerjakan')
                            ? 'bi-play-circle-fill'
                            : 'bi-pause-circle-fill';
                    ?>
                    <div class="current-status-badge-modern <?php echo $status_badge_class_modern; ?>">
                        <i class="bi <?php echo $status_icon_modern; ?>"></i>
                        <span><?php echo htmlspecialchars($header_info['status_pengerjaan']); ?></span>
                    </div>
                </div>
                <?php if ($is_allowed): ?>
                <button 
                    type="button" 
                    class="btn-change-status-modern"
                    onclick="showChangeStatusModal(<?php echo $id_target; ?>, <?php echo $id_alur; ?>, '<?php echo htmlspecialchars($header_info['status_pengerjaan']); ?>')">
                    <i class="bi bi-pencil-square"></i>
                    <span>Ubah Status</span>
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="stats-cards">
        <div class="stat-card">
            <div class="stat-icon icon-primary"><i class="bi bi-list-task"></i></div>
            <h6 class="text-muted mb-1">Total Komponen</h6>
            <h3 class="fw-bold mb-0"><?php echo (int)($stats['total_material'] ?? 0); ?> Items</h3>
        </div>
        <div class="stat-card">
            <div class="stat-icon icon-success"><i class="bi bi-check-circle"></i></div>
            <h6 class="text-muted mb-1">Terpenuhi</h6>
            <h3 class="fw-bold mb-0 text-success"><?php echo (int)($stats['terpenuhi'] ?? 0); ?> Items</h3>
        </div>
        <div class="stat-card">
            <div class="stat-icon icon-warning"><i class="bi bi-exclamation-triangle"></i></div>
            <h6 class="text-muted mb-1">Belum Terpenuhi</h6>
            <h3 class="fw-bold mb-0 text-warning"><?php echo (int)($stats['belum_terpenuhi'] ?? 0); ?> Items</h3>
        </div>
    </div>
    
    <?php if (!$is_allowed): ?>
    <div class="alert alert-warning alert-modern" role="alert">
        <i class="bi bi-info-circle-fill me-2"></i>
        <strong>Mode Hanya Lihat:</strong> Anda tidak memiliki hak akses untuk menginput progres pada alur ini.
    </div>
    <?php endif; ?>

    <?php if ($is_form_disabled): ?>
        <div class="alert alert-danger alert-modern" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <strong>Input Diblokir:</strong> Status pengerjaan untuk tahapan ini masih "Pending". Ubah status menjadi "Sedang Dikerjakan" untuk dapat menginput laporan.
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-modern" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>
            <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-modern" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($materials)): ?>
    <div class="date-picker-section">
        <h5 class="mb-3"><i class="bi bi-calendar-check me-2"></i>Pilih Tanggal Laporan</h5>
        <div class="row align-items-end g-3">
            <div class="col-md-4">
                <div class="input-modern">
                    <input type="date" id="tanggal_laporan" name="tanggal_laporan" required <?php echo (!$is_allowed || $is_form_disabled) ? 'disabled' : ''; ?>>
                </div>
            </div>
            <div class="col-md-8">
                <button type="button" class="quick-action-btn" id="btnHariIni"><i class="bi bi-calendar-day me-1"></i> Hari Ini</button>
                <button type="button" class="quick-action-btn" id="btnKemarin"><i class="bi bi-calendar-minus me-1"></i> Kemarin</button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (empty($materials)): ?>
        <div class="table-modern">
            <div class="empty-state">
                <div class="empty-state-icon"><i class="bi bi-inbox"></i></div>
                <h5 class="text-muted">Belum ada komponen yang ditambahkan untuk tahapan ini.</h5>
                <p class="text-muted">Silakan hubungi Superadmin untuk menambahkan komponen material.</p>
            </div>
        </div>
    <?php else: ?>
        <form action="proses_input_harian.php" method="POST" id="laporanHarianForm">
            <input type="hidden" name="id_target" value="<?php echo $id_target; ?>">
            <input type="hidden" name="id_alur" value="<?php echo $id_alur; ?>">
            <input type="hidden" name="tanggal_laporan" id="hidden_tanggal_laporan">
            
            <div class="table-modern">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Nama Komponen</th>
                            <th>Total Kebutuhan</th>
                            <th>Telah Selesai</th>
                            <th>Sisa</th>
                            <th>Status</th>
                            <th style="width: 25%;">Input Progres Hari Ini</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no = 1; foreach ($materials as $mat): ?>
                            <?php
                                $kebutuhan = (int)$mat['kebutuhan_total'];
                                $selesai = (int)$mat['total_selesai'];
                                $sisa = $kebutuhan - $selesai;
                                $is_done = $sisa <= 0;
                                $status = $is_done ? 'Terpenuhi' : 'Belum Terpenuhi';
                                $status_class = $is_done ? 'status-success' : 'status-warning';
                            ?>
                            <tr>
                                <td class="text-center fw-bold"><?php echo $no++; ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="stat-icon icon-primary me-2" style="width: 30px; height: 30px; font-size: 0.8rem;"><i class="bi bi-box"></i></div>
                                        <strong><?php echo htmlspecialchars($mat['nama_komponen']); ?></strong>
                                    </div>
                                </td>
                                <td class="text-center"><strong><?php echo number_format($kebutuhan); ?></strong></td>
                                <td class="text-center"><?php echo number_format($selesai); ?></td>
                                <td class="text-center">
                                    <span class="fw-bold <?php echo $is_done ? 'text-success' : 'text-danger'; ?>">
                                        <?php echo number_format($sisa); ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="status-badge <?php echo $status_class; ?>"><?php echo $status; ?></span>
                                </td>
                                <td>
                                    <input type="number" 
                                           name="jumlah_selesai[<?php echo $mat['id_material']; ?>]" 
                                           class="form-control text-center" 
                                           min="0" 
                                           max="<?php echo max(0, $sisa); ?>" 
                                           placeholder="<?php echo $is_done ? 'SELESAI' : '0'; ?>"
                                           <?php echo ($is_done || !$is_allowed || $is_form_disabled) ? 'disabled' : ''; ?>>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div class="p-3 bg-light">
                    <div class="text-end">
                        <?php if ($is_allowed && !$is_form_disabled): ?>
                        <button type="submit" class="btn btn-primary-modern btn-modern">
                            <i class="bi bi-save-fill me-2"></i> Simpan Laporan
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </form>
    <?php endif; ?>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Date picker setup
    const tanggalInput = document.getElementById('tanggal_laporan');
    const btnHariIni = document.getElementById('btnHariIni');
    const btnKemarin = document.getElementById('btnKemarin');
    const form = document.getElementById('laporanHarianForm');
    const today = new Date().toISOString().split('T')[0];
    const yesterday = new Date(new Date().setDate(new Date().getDate() - 1)).toISOString().split('T')[0];
    
    if (tanggalInput) { 
        tanggalInput.max = today; 
        tanggalInput.value = today; 
    }
    
    if (btnHariIni) { 
        btnHariIni.addEventListener('click', () => { 
            if(tanggalInput && !tanggalInput.disabled) tanggalInput.value = today; 
        }); 
    }
    
    if (btnKemarin) { 
        btnKemarin.addEventListener('click', () => { 
            if(tanggalInput && !tanggalInput.disabled) tanggalInput.value = yesterday; 
        }); 
    }

    // --- MULAI KODE BARU UNTUK BACK TO TOP ---
    const backToTopBtn = document.getElementById('backToTopBtn');

    if (backToTopBtn) {
        window.addEventListener('scroll', function() {
            if (window.pageYOffset > 300) {
                backToTopBtn.classList.add('show');
            } else {
                backToTopBtn.classList.remove('show');
            }
        });

        backToTopBtn.addEventListener('click', function() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    }
    // --- AKHIR KODE BARU UNTUK BACK TO TOP ---

    // Fungsi helper untuk menampilkan modal validasi
    function tampilkanPesanValidasi(pesan) {
        const modalPesanElement = document.getElementById('modalValidasiPesan');
        if (modalPesanElement) {
            modalPesanElement.textContent = pesan;
            const validasiModal = new bootstrap.Modal(document.getElementById('validasiModal'));
            validasiModal.show();
        }
    }

    // Form submission handling
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const tanggal = tanggalInput.value;
            document.getElementById('hidden_tanggal_laporan').value = tanggal;
            
            if (!tanggal) {
                tampilkanPesanValidasi('Tanggal laporan wajib diisi!');
                return;
            }
            
            let adaInput = false;
            let ringkasan = '';
            const inputs = form.querySelectorAll('input[type="number"]:not([disabled])');
            
            inputs.forEach(input => {
                if (input.value && parseInt(input.value, 10) > 0) {
                    adaInput = true;
                    const namaKomponen = input.closest('tr').querySelector('td:nth-child(2) strong').innerText;
                    ringkasan += `- ${namaKomponen}: ${input.value} PCS\n`;
                }
            });
            
            if (!adaInput) {
                tampilkanPesanValidasi('Mohon isi minimal satu jumlah komponen yang selesai dikerjakan.');
                return;
            }
            
            document.getElementById('modalTanggalLaporan').textContent = tanggal;
            document.getElementById('modalRingkasanProduksi').textContent = ringkasan;

            const simpanModal = new bootstrap.Modal(document.getElementById('simpanLaporanModal'));
            simpanModal.show();
        });
    }

    // Event listener untuk tombol konfirmasi
    const tombolKonfirmasi = document.getElementById('tombolKonfirmasiSimpan');
    if (tombolKonfirmasi) {
        tombolKonfirmasi.addEventListener('click', function() {
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.innerHTML = '<span class="loading-spinner me-2"></span>Menyimpan...';
                submitBtn.disabled = true;
            }

            const simpanModal = bootstrap.Modal.getInstance(document.getElementById('simpanLaporanModal'));
            simpanModal.hide();
            
            setTimeout(() => {
                form.submit();
            }, 500);
        });
    }

    // Input validation
    document.querySelectorAll('input[type="number"][max]').forEach(input => {
        input.addEventListener('input', function() {
            const max = parseFloat(this.getAttribute('max')); 
            let value = parseFloat(this.value);
            
            if (isNaN(value)) return;
            
            if (value < 0) {
                this.value = 0;
                return;
            }
            
            if (value > max) {
                Swal.fire({
                    title: 'Input Melebihi Batas!',
                    text: `Jumlah tidak boleh melebihi sisa kebutuhan (${max}).`,
                    icon: 'warning',
                    confirmButtonText: 'Mengerti',
                    confirmButtonColor: '#667eea'
                });
                this.value = max; 
            }
        });
    });

    // Animate stats
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
    
    setTimeout(animateStats, 500);
});

// Fungsi update status
function showChangeStatusModal(id_target, id_alur, currentStatus) {
    const statusOptions = `
        <div style="text-align: left;">
            <div class="status-option-card" data-status="Sedang Dikerjakan" onclick="selectStatusOption(this)">
                <div style="display: flex; align-items: center;">
                    <div class="status-option-icon">
                        <i class="bi bi-play-circle-fill"></i>
                    </div>
                    <div class="status-option-info">
                        <h6><i class="bi bi-check-circle me-1"></i>Sedang Dikerjakan</h6>
                        <p>Tahap aktif, input laporan dapat dilakukan</p>
                    </div>
                </div>
            </div>
            
            <div class="status-option-card" data-status="Pending" onclick="selectStatusOption(this)">
                <div style="display: flex; align-items: center;">
                    <div class="status-option-icon">
                        <i class="bi bi-pause-circle-fill"></i>
                    </div>
                    <div class="status-option-info">
                        <h6><i class="bi bi-exclamation-circle me-1"></i>Pending</h6>
                        <p>Tahap ditunda, input laporan dinonaktifkan</p>
                    </div>
                </div>
            </div>
            
            <div style="background: linear-gradient(135deg, #e3f2fd, #bbdefb); padding: 1rem; border-radius: 10px; margin-top: 1rem; border-left: 4px solid #2196F3;">
                <small style="color: #1976D2;">
                    <i class="bi bi-info-circle-fill me-1"></i>
                    <strong>Status saat ini:</strong> ${currentStatus}
                </small>
            </div>
        </div>
    `;
    
    Swal.fire({
        title: '<i class="bi bi-arrows-move me-2"></i>Ubah Status Pengerjaan',
        html: statusOptions,
        showCancelButton: true,
        confirmButtonText: '<i class="bi bi-check-lg me-1"></i>Ubah Status',
        cancelButtonText: '<i class="bi bi-x-lg me-1"></i>Batal',
        confirmButtonColor: '#667eea',
        cancelButtonColor: '#6c757d',
        width: '500px',
        customClass: {
            popup: 'swal-custom-popup',
            confirmButton: 'btn-modern',
            cancelButton: 'btn-modern'
        },
        didOpen: () => {
            const currentOption = document.querySelector(`.status-option-card[data-status="${currentStatus}"]`);
            if (currentOption) {
                selectStatusOption(currentOption);
            }
        },
        preConfirm: () => {
            const selected = document.querySelector('.status-option-card.selected');
            if (!selected) {
                Swal.showValidationMessage('Pilih status terlebih dahulu!');
                return false;
            }
            return selected.getAttribute('data-status');
        }
    }).then((result) => {
        if (result.isConfirmed) {
            updateStatusAPI(id_target, id_alur, result.value);
        }
    });
}

function selectStatusOption(element) {
    document.querySelectorAll('.status-option-card').forEach(card => {
        card.classList.remove('selected');
    });
    element.classList.add('selected');
}

function updateStatusAPI(id_target, id_alur, status) {
    Swal.fire({
        title: 'Mengubah Status...',
        html: '<div style="padding: 2rem;"><i class="bi bi-hourglass-split" style="font-size: 3rem; color: #667eea;"></i></div>',
        showConfirmButton: false,
        allowOutsideClick: false
    });
    
    const formData = new FormData();
    formData.append('id_target', id_target);
    formData.append('id_alur', id_alur);
    formData.append('status', status);

    fetch('../../superadmin/manajemen_produksi/proses_update_status_pengerjaan.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                title: 'Berhasil!',
                text: data.message,
                icon: 'success',
                confirmButtonText: 'OK',
                confirmButtonColor: '#28a745',
                timer: 2000,
                timerProgressBar: true
            }).then(() => {
                location.reload();
            });
        } else {
            Swal.fire({
                title: 'Gagal!',
                text: data.message,
                icon: 'error',
                confirmButtonText: 'OK',
                confirmButtonColor: '#dc3545'
            });
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire({
            title: 'Error!',
            text: 'Terjadi kesalahan saat menghubungi server.',
            icon: 'error',
            confirmButtonText: 'OK',
            confirmButtonColor: '#dc3545'
        });
    });
}
</script>

<?php
include_once '../../../templates/footer.php';
?>