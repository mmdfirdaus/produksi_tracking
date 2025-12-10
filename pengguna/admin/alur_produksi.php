<?php
$page_title = 'Alur Produksi';
include_once '../../templates/header_admin.php';

// Validasi id_target dari URL
if (!isset($_GET['id_target']) || !is_numeric($_GET['id_target'])) {
    echo "<script>alert('ID Target tidak valid!'); window.location.href='dashboard.php';</script>";
    exit;
}
$id_target = (int)$_GET['id_target'];
$id_user_admin = $_SESSION['user_id'];

try {
    // 1. Ambil data target produksi dan nama barang
    $stmt = $pdo->prepare("
        SELECT pt.nama_permintaan, pt.jumlah_unit, pt.no_spk, pt.id_barang, mb.nama_barang, mb.kode_barang
        FROM production_targets pt
        JOIN master_barang mb ON pt.id_barang = mb.id_barang
        WHERE pt.id_target = :id_target
    ");
    $stmt->execute([':id_target' => $id_target]);
    $target = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$target) {
        throw new Exception("Target produksi tidak ditemukan.");
    }
    $id_barang = $target['id_barang'];

    // 2. Kueri utama untuk mengambil semua data alur, progres, status, dan hak akses
    $alurs_stmt = $pdo->prepare("
        SELECT
            ma.id_alur,
            ma.nama_alur,
            ma.urutan,
            COALESCE(tas.status_pengerjaan, 'Pending') AS status_pengerjaan,
            (SELECT COUNT(*) FROM admin_tahapan_access WHERE id_user = :id_user AND id_tahapan = ma.id_alur) AS has_access,
            COUNT(tm.id_material) AS total_komponen,
            SUM(CASE WHEN (tm.jumlah_per_unit * pt.jumlah_unit) <= COALESCE(lh.total_selesai, 0) THEN 1 ELSE 0 END) AS komponen_terpenuhi,
            GROUP_CONCAT(DISTINCT u.full_name SEPARATOR ', ') AS penanggung_jawab
        FROM alur_barang ab
        JOIN master_alur ma ON ab.id_alur = ma.id_alur
        LEFT JOIN target_alur_status tas ON ma.id_alur = tas.id_alur AND tas.id_target = :id_target
        LEFT JOIN admin_tahapan_access ata ON ma.id_alur = ata.id_tahapan
        LEFT JOIN users u ON ata.id_user = u.id
        LEFT JOIN target_material tm ON ma.id_alur = tm.id_alur AND tm.id_target = :id_target
        LEFT JOIN production_targets pt ON tm.id_target = pt.id_target
        LEFT JOIN (
            SELECT id_material, SUM(jumlah_selesai) as total_selesai
            FROM laporan_harian GROUP BY id_material
        ) lh ON tm.id_material = lh.id_material
        WHERE ab.id_barang = :id_barang
        GROUP BY ma.id_alur, ma.nama_alur, ma.urutan
        ORDER BY ma.urutan ASC
    ");
    
    $alurs_stmt->execute([':id_target' => $id_target, ':id_barang' => $id_barang, ':id_user' => $id_user_admin]);
    $alurs = $alurs_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    die("Error saat mengambil data: " . $e->getMessage());
}
?>

<style>
/* Modern UI Styles - Base */
.modern-container {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
    padding: 2rem 0;
}

.content-wrapper {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 1rem;
}

.modern-header {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border-radius: 20px;
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.breadcrumb-modern {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(5px);
    border-radius: 50px;
    padding: 0.75rem 1.5rem;
    margin-bottom: 1.5rem;
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.breadcrumb-modern .breadcrumb {
    background: none;
    margin: 0;
    padding: 0;
}

.breadcrumb-modern .breadcrumb-item a {
    color: white;
    text-decoration: none;
    font-weight: 500;
    transition: all 0.3s ease;
}

.breadcrumb-modern .breadcrumb-item a:hover {
    color: #f093fb;
    text-shadow: 0 0 10px rgba(240, 147, 251, 0.5);
}

.breadcrumb-modern .breadcrumb-item.active {
    color: rgba(255, 255, 255, 0.8);
}

.breadcrumb-modern .breadcrumb-item + .breadcrumb-item::before {
    content: "›";
    color: rgba(255, 255, 255, 0.6);
    font-weight: bold;
}

.target-info {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-top: 1.5rem;
}

.info-card {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    color: white;
    padding: 1.5rem;
    border-radius: 15px;
    text-align: center;
    box-shadow: 0 10px 30px rgba(240, 147, 251, 0.3);
    transition: all 0.3s ease;
}

.info-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 40px rgba(240, 147, 251, 0.4);
}

.info-card h5 {
    margin: 0 0 0.5rem 0;
    font-size: 0.9rem;
    opacity: 0.9;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.info-card .value {
    font-size: 1.4rem;
    font-weight: bold;
    margin: 0;
}

.section-title {
    color: white;
    font-size: 1.8rem;
    font-weight: 600;
    margin: 2rem 0 1.5rem 0;
    text-align: center;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
}

.production-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 1.5rem;
    margin-top: 2rem;
}

.process-card {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border-radius: 20px;
    overflow: hidden;
    transition: all 0.4s ease;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    position: relative;
}

.process-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
}

.process-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 5px;
    background: linear-gradient(90deg, #667eea, #764ba2, #f093fb, #f5576c);
    animation: shimmer 3s ease-in-out infinite;
}

@keyframes shimmer {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.7; }
}

.card-header-modern {
    background: linear-gradient(135deg, #2c3e50, #34495e);
    color: white;
    padding: 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: relative;
    overflow: hidden;
}

.card-header-modern::after {
    content: '';
    position: absolute;
    top: 0;
    right: -50%;
    width: 50%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
    animation: slide 3s ease-in-out infinite;
}

@keyframes slide {
    0% { right: -50%; }
    50% { right: 100%; }
    100% { right: -50%; }
}

.process-name {
    font-size: 1.1rem;
    font-weight: 600;
    margin: 0;
    z-index: 1;
    position: relative;
}

.status-badge {
    padding: 0.5rem 1rem;
    border-radius: 25px;
    font-size: 0.8rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    z-index: 1;
    position: relative;
    animation: pulse 2s ease-in-out infinite;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}

.status-completed {
    background: linear-gradient(45deg, #4CAF50, #45a049);
    color: white;
    box-shadow: 0 4px 15px rgba(76, 175, 80, 0.4);
}

.status-progress {
    background: linear-gradient(45deg, #ff9a56, #ff6b6b);
    color: white;
    box-shadow: 0 4px 15px rgba(255, 154, 86, 0.4);
}

.card-body-modern {
    padding: 2rem;
    background: rgba(255, 255, 255, 0.95);
}

.progress-info {
    margin-top: 1rem;
    padding: 1rem;
    background: rgba(102, 126, 234, 0.1);
    border-radius: 10px;
    border-left: 4px solid #667eea;
}

.progress-stats {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.progress-number {
    font-size: 1.5rem;
    font-weight: bold;
    color: #2c3e50;
}

.progress-label {
    color: #667eea;
    font-weight: 600;
    font-size: 0.9rem;
}

.progress-bar-container {
    background: rgba(102, 126, 234, 0.1);
    height: 20px;
    border-radius: 10px;
    overflow: hidden;
    position: relative;
}

.progress-bar-fill {
    height: 100%;
    background: linear-gradient(45deg, #667eea, #764ba2);
    border-radius: 10px;
    transition: width 0.8s ease;
    position: relative;
    overflow: hidden;
}

.progress-bar-fill::after {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
    animation: progressShine 2s ease-in-out infinite;
}

@keyframes progressShine {
    0% { left: -100%; }
    100% { left: 100%; }
}

.progress-percentage {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    color: white;
    font-weight: bold;
    font-size: 0.8rem;
    text-shadow: 0 1px 2px rgba(0,0,0,0.3);
}

.card-footer-modern {
    padding: 1.5rem;
    background: rgba(248, 249, 250, 0.95);
    text-align: center;
}

.detail-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 1rem 2rem;
    background: linear-gradient(45deg, #667eea, #764ba2);
    color: white;
    text-decoration: none;
    border-radius: 50px;
    font-weight: 600;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
    width: 100%;
    justify-content: center;
}

.detail-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
    color: white;
    text-decoration: none;
}

.empty-state {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border-radius: 20px;
    padding: 3rem;
    text-align: center;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.empty-state-icon {
    font-size: 4rem;
    color: #667eea;
    margin-bottom: 1rem;
}

.empty-state h4 {
    color: #2c3e50;
    margin-bottom: 1rem;
}

.empty-state p {
    color: #666;
    margin: 0;
}

.back-btn-custom {
    background: rgba(255,255,255,0.2);
    border: 1px solid rgba(255,255,255,0.3);
    color: white;
    border-radius: 50px;
    padding: 0.6rem 1.2rem;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.3s ease;
    font-weight: 500;
}

.back-btn-custom:hover {
    background: rgba(255,255,255,0.3);
    color: white;
    transform: translateX(-5px);
    text-decoration: none;
}

/* Status Section */
.status-section {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: 15px;
    padding: 1.5rem;
    margin: 1.5rem 0;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
    border: 2px solid rgba(102, 126, 234, 0.2);
}

.status-header {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    margin-bottom: 1rem;
}

.status-header i {
    font-size: 1.3rem;
    color: #667eea;
}

.status-header span {
    font-size: 0.85rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: #495057;
}

.status-display {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 1rem;
    flex-wrap: wrap;
}

.current-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    border-radius: 50px;
    font-size: 0.95rem;
    font-weight: 600;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
}

.current-status-badge:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
}

.current-status-badge.status-dikerjakan {
    background: linear-gradient(135deg, #28a745, #20c997);
    color: white;
}

.current-status-badge.status-pending {
    background: linear-gradient(135deg, #ffc107, #ff9800);
    color: white;
}

.change-status-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    border: none;
    border-radius: 50px;
    font-size: 0.9rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
}

.change-status-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
    background: linear-gradient(135deg, #764ba2, #667eea);
}

.pic-section {
    background: rgba(102, 126, 234, 0.05);
    border-radius: 12px;
    padding: 1rem;
    margin-top: 1.5rem;
    border: 1px solid rgba(102, 126, 234, 0.2);
}

.pic-header {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    margin-bottom: 0.75rem;
}

.pic-header i {
    font-size: 1.2rem;
    color: #28a745;
}

.pic-header span {
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: #6c757d;
}

.pic-name {
    font-size: 1rem;
    font-weight: 600;
    color: #2c3e50;
    text-align: center;
}

.pic-name.no-pic {
    color: #dc3545;
    font-style: italic;
}

/* Modal */
.custom-modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(5px);
    z-index: 9999;
    animation: fadeIn 0.3s ease;
}

.custom-modal-overlay.active {
    display: flex;
    align-items: center;
    justify-content: center;
}

.custom-modal {
    background: white;
    border-radius: 20px;
    padding: 0;
    max-width: 450px;
    width: 90%;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    animation: slideUp 0.3s ease;
    overflow: hidden;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(50px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.modal-header-custom {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    padding: 2rem;
    text-align: center;
}

.modal-icon {
    font-size: 3rem;
    margin-bottom: 1rem;
    animation: bounce 1s ease infinite;
}

@keyframes bounce {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-10px); }
}

.modal-header-custom h3 {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 600;
}

.modal-body-custom {
    padding: 2rem;
    text-align: center;
}

.modal-body-custom p {
    font-size: 1.1rem;
    color: #495057;
    margin-bottom: 1.5rem;
    line-height: 1.6;
}

.status-change-info {
    background: linear-gradient(135deg, #f8f9fa, #e9ecef);
    border-radius: 12px;
    padding: 1rem;
    margin: 1rem 0;
    border-left: 4px solid #667eea;
    text-align: left;
}

.status-change-info p {
    margin: 0;
    font-size: 0.95rem;
    color: #2c3e50;
}

.status-change-info strong {
    color: #667eea;
    font-size: 1.1rem;
}

.modal-footer-custom {
    display: flex;
    gap: 1rem;
    padding: 0 2rem 2rem 2rem;
}

.modal-btn {
    flex: 1;
    padding: 1rem;
    border: none;
    border-radius: 50px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.modal-btn-cancel {
    background: linear-gradient(135deg, #6c757d, #495057);
    color: white;
}

.modal-btn-cancel:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(108, 117, 125, 0.4);
}

.modal-btn-confirm {
    background: linear-gradient(135deg, #28a745, #20c997);
    color: white;
}

.modal-btn-confirm:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(40, 167, 69, 0.4);
}

.loading {
    opacity: 0;
    animation: fadeInContent 0.6s ease-in-out forwards;
}

@keyframes fadeInContent {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

/* ============================================
   FLOATING BACK TO TOP BUTTON
   ============================================ */
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

.back-to-top:active {
    transform: translateY(-2px);
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

/* ============================================
   RESPONSIVE DESIGN UNTUK MOBILE
   ============================================ */

@media (max-width: 768px) {
    /* Container */
    .modern-container {
        padding: 1rem 0;
    }
    
    .content-wrapper {
        padding: 0 0.75rem;
    }
    
    /* Breadcrumb - Compact di Mobile */
    .breadcrumb-modern {
        padding: 0.5rem 1rem;
        margin-bottom: 1rem;
    }
    
    .breadcrumb-modern .breadcrumb {
        font-size: 0.85rem;
        flex-wrap: nowrap;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    
    .breadcrumb-modern .breadcrumb-item {
        white-space: nowrap;
    }
    
    /* Back Button */
    .back-btn-custom {
        font-size: 0.85rem;
        padding: 0.5rem 1rem;
        margin-bottom: 1rem;
    }
    
    /* Header */
    .modern-header {
        padding: 1.5rem;
        border-radius: 15px;
        margin-bottom: 1.5rem;
    }
    
    .modern-header h2 {
        font-size: 1.5rem;
        margin-bottom: 0.5rem !important;
    }
    
    .modern-header p {
        font-size: 0.95rem;
    }
    
    /* Info Cards - LAYOUT OPTION B */
    .target-info {
        display: grid;
        grid-template-columns: 1fr 1fr; /* Default 2 kolom */
        gap: 1rem;
        margin-top: 1rem;
    }
    
    .target-info .info-card:first-child,
    .target-info .info-card:nth-child(2) {
        grid-column: 1 / span 2; /* Membentang 2 kolom */
    }
    
    .info-card {
        padding: 1rem;
    }
    
    .info-card h5 {
        font-size: 0.75rem;
        margin-bottom: 0.5rem;
    }
    
    .info-card .value {
        font-size: 1.1rem;
    }
    
    /* Section Title */
    .section-title {
        font-size: 1.4rem;
        margin: 1.5rem 0 1rem 0;
    }
    
    /* Production Grid - 1 Kolom di Mobile */
    .production-grid {
        grid-template-columns: 1fr;
        gap: 1.25rem;
        margin-top: 1.5rem;
    }
    
    /* Process Cards */
    .process-card {
        border-radius: 15px;
    }
    
    .card-header-modern {
        padding: 1.25rem;
        flex-direction: column;
        align-items: flex-start;
        gap: 0.75rem;
    }
    
    .process-name {
        font-size: 1rem;
        width: 100%;
    }
    
    .status-badge {
        font-size: 0.75rem;
        padding: 0.4rem 0.85rem;
        align-self: flex-start;
    }
    
    /* Card Body */
    .card-body-modern {
        padding: 1.5rem;
    }
    
    .card-body-modern > p {
        font-size: 0.9rem;
        margin-bottom: 0.75rem !important;
    }
    
    /* Progress Info */
    .progress-info {
        padding: 0.85rem;
        margin-top: 0.75rem;
    }
    
    .progress-stats {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
        margin-bottom: 0.75rem;
    }
    
    .progress-number {
        font-size: 1.3rem;
    }
    
    .progress-label {
        font-size: 0.85rem;
    }
    
    .progress-bar-container {
        height: 18px;
    }
    
    .progress-percentage {
        font-size: 0.75rem;
    }
    
    /* Status Section */
    .status-section {
        padding: 1.25rem;
        margin: 1.25rem 0;
    }
    
    .status-header {
        margin-bottom: 0.85rem;
    }
    
    .status-header i {
        font-size: 1.1rem;
    }
    
    .status-header span {
        font-size: 0.75rem;
    }
    
    .status-display {
        flex-direction: column;
        gap: 0.75rem;
    }
    
    .current-status-badge {
        font-size: 0.85rem;
        padding: 0.6rem 1.25rem;
        width: 100%;
        justify-content: center;
    }
    
    .current-status-badge i {
        font-size: 1rem;
    }
    
    .change-status-btn {
        font-size: 0.85rem;
        padding: 0.6rem 1.25rem;
        width: 100%;
        justify-content: center;
    }
    
    /* PIC Section */
    .pic-section {
        padding: 0.85rem;
        margin-top: 1.25rem;
    }
    
    .pic-header {
        margin-bottom: 0.5rem;
    }
    
    .pic-header i {
        font-size: 1rem;
    }
    
    .pic-header span {
        font-size: 0.75rem;
    }
    
    .pic-name {
        font-size: 0.9rem;
    }
    
    /* Card Footer */
    .card-footer-modern {
        padding: 1.25rem;
    }
    
    .detail-btn {
        padding: 0.85rem 1.5rem;
        font-size: 0.9rem;
    }
    
    .detail-btn i {
        font-size: 1rem;
    }
    
    /* Empty State */
    .empty-state {
        padding: 2rem 1.5rem;
        border-radius: 15px;
    }
    
    .empty-state-icon {
        font-size: 3rem;
    }
    
    .empty-state h4 {
        font-size: 1.2rem;
    }
    
    .empty-state p {
        font-size: 0.9rem;
    }
    
    /* Modal */
    .custom-modal {
        width: 95%;
        max-width: 400px;
    }
    
    .modal-header-custom {
        padding: 1.5rem;
    }
    
    .modal-icon {
        font-size: 2.5rem;
        margin-bottom: 0.75rem;
    }
    
    .modal-header-custom h3 {
        font-size: 1.2rem;
    }
    
    .modal-body-custom {
        padding: 1.5rem;
    }
    
    .modal-body-custom p {
        font-size: 1rem;
        margin-bottom: 1rem;
    }
    
    .status-change-info {
        padding: 0.85rem;
        margin: 0.75rem 0;
    }
    
    .status-change-info p {
        font-size: 0.85rem;
    }
    
    .status-change-info strong {
        font-size: 1rem;
    }
    
    .modal-footer-custom {
        flex-direction: column;
        padding: 0 1.5rem 1.5rem 1.5rem;
        gap: 0.75rem;
    }
    
    .modal-btn {
        padding: 0.85rem;
        font-size: 0.9rem;
    }
    
    /* Back to Top Button - Ukuran Mobile */
    .back-to-top {
        width: 45px;
        height: 45px;
        bottom: 1.5rem;
        right: 1.5rem;
        font-size: 1.3rem;
    }
}

/* Extra Small Devices (< 576px) */
@media (max-width: 576px) {
    .modern-header h2 {
        font-size: 1.3rem;
    }
    
    .modern-header p {
        font-size: 0.85rem;
    }
    
    .info-card .value {
        font-size: 1rem;
    }
    
    .section-title {
        font-size: 1.2rem;
    }
    
    .process-name {
        font-size: 0.95rem;
    }
    
    .card-body-modern {
        padding: 1.25rem;
    }
    
    .progress-number {
        font-size: 1.2rem;
    }
    
    .detail-btn {
        padding: 0.75rem 1.25rem;
        font-size: 0.85rem;
    }
    
    .back-to-top {
        width: 40px;
        height: 40px;
        bottom: 1rem;
        right: 1rem;
        font-size: 1.2rem;
    }
}

/* Tablet Landscape (768px - 992px) */
@media (min-width: 769px) and (max-width: 992px) {
    .target-info {
        grid-template-columns: repeat(3, 1fr);
    }
    
    .production-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

/* Small Landscape Phones */
@media (max-width: 768px) and (orientation: landscape) {
    .modern-header {
        padding: 1.25rem;
    }
    
    .target-info {
        grid-template-columns: repeat(3, 1fr);
    }
    
    .target-info .info-card:first-child {
        grid-column: auto;
    }
    
    .section-title {
        font-size: 1.3rem;
        margin: 1rem 0 0.75rem 0;
    }
    
    .modal-dialog {
        max-height: 85vh;
        overflow-y: auto;
    }
}

/* Smooth Scrolling */
html {
    scroll-behavior: smooth;
}

/* Reduced Motion Support */
@media (prefers-reduced-motion: reduce) {
    * {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
    }
    
    .back-to-top {
        transition: none;
    }
}
</style>

<div class="modern-container">
    <div class="content-wrapper">
        <div class="loading">
            <div class="breadcrumb-modern">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="master_barang.php">Master Barang</a></li>
                    <li class="breadcrumb-item"><a href="detail_barang.php?id_barang=<?php echo $id_barang; ?>"><?php echo htmlspecialchars($target['nama_barang']); ?></a></li>
                    <li class="breadcrumb-item active">Alur Produksi</li>
                </ol>
            </div>

            <a href="detail_barang.php?id_barang=<?php echo $id_barang; ?>" class="back-btn-custom mb-3">
                <i class="bi bi-arrow-left"></i>
                <span>Kembali ke Detail Barang</span>
            </a>
            
            <div class="modern-header">
                <h2 style="color: #2c3e50; margin: 0; font-weight: 700;">
                    🏭 Alur Produksi
                </h2>
                <p style="color: #666; margin: 0.5rem 0 0 0; font-size: 1.1rem;">
                    Target: "<?php echo htmlspecialchars($target['nama_permintaan']); ?>"
                </p>

                <div class="target-info">
                    <div class="info-card">
                        <h5>Nama Barang</h5>
                        <p class="value"><?php echo htmlspecialchars($target['nama_barang']); ?></p>
                        <small class="d-block mt-1 text-white-50" style="font-size: 0.75rem;">
            <i class="bi bi-upc-scan me-1"></i><?php echo htmlspecialchars($target['kode_barang']); ?>
        </small>
                    </div>
                    <div class="info-card">
        <h5>Target Permintaan</h5> <p class="value"><?php echo htmlspecialchars($target['nama_permintaan']); ?></p>
        <div class="mt-1">
            <span class="badge bg-white text-dark" style="font-weight: 600; font-size: 0.8rem;">
                <i class="bi bi-hash me-1"></i><?php echo htmlspecialchars($target['no_spk']); ?>
            </span>
        </div>
    </div>
                    <div class="info-card">
                        <h5>Jumlah Target</h5>
                        <p class="value"><?php echo number_format($target['jumlah_unit']); ?> Unit</p>
                    </div>
                    <div class="info-card">
                        <h5>Total Tahapan</h5>
                        <p class="value"><?php echo count($alurs); ?> Tahap</p>
                    </div>
                </div>
            </div>

            <h3 class="section-title">📋 Tahapan Proses Produksi</h3>

            <?php if (empty($alurs)): ?>
                <div class="empty-state loading">
                    <div class="empty-state-icon">
                        <i class="bi bi-info-circle"></i>
                    </div>
                    <h4>Belum Ada Alur Produksi</h4>
                    <p>
                        Alur produksi untuk barang ini belum diatur.<br>
                        Silakan hubungi Superadmin untuk konfigurasi.
                    </p>
                </div>
            <?php else: ?>
                <div class="production-grid">
                    <?php foreach ($alurs as $index => $alur): ?>
                        <div class="process-card loading" style="animation-delay: <?php echo $index * 0.1; ?>s;">
                            <div class="card-header-modern">
                                <h4 class="process-name">
                                    <span style="opacity: 0.7;">Tahap <?php echo $index + 1; ?>:</span><br>
                                    <?php echo htmlspecialchars($alur['nama_alur']); ?>
                                </h4>
                                <?php
                                    $is_completed = ($alur['total_komponen'] > 0 && $alur['total_komponen'] == $alur['komponen_terpenuhi']);
                                ?>
                                <?php if ($is_completed): ?>
                                    <div class="status-badge status-completed">
                                        <i class="bi bi-check-circle-fill"></i>
                                        <span>Selesai</span>
                                    </div>
                                <?php else: ?>
                                    <div class="status-badge status-progress">
                                        <i class="bi bi-clock-fill"></i>
                                        <span>Proses</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="card-body-modern">
                                <p style="color: #666; line-height: 1.6; margin: 0 0 1rem 0;">
                                    Progres penyelesaian komponen untuk tahapan ini:
                                </p>
                                
                                <?php if ($alur['total_komponen'] > 0): ?>
                                    <div class="progress-info">
                                        <div class="progress-stats">
                                            <div class="progress-number">
                                                <?php echo (int)$alur['komponen_terpenuhi']; ?> / <?php echo (int)$alur['total_komponen']; ?>
                                            </div>
                                            <div class="progress-label">
                                                Komponen Selesai
                                            </div>
                                        </div>
                                        
                                        <?php
                                            $progress_percent = $alur['total_komponen'] > 0 ? round(((int)$alur['komponen_terpenuhi'] / (int)$alur['total_komponen']) * 100) : 0;
                                        ?>
                                        
                                        <div class="progress-bar-container">
                                            <div class="progress-bar-fill" style="width: <?php echo $progress_percent; ?>%">
                                                <div class="progress-percentage"><?php echo $progress_percent; ?>%</div>
                                            </div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="progress-info">
                                        <div class="progress-stats">
                                            <div class="progress-number">0 / 0</div>
                                            <div class="progress-label">Belum Ada Komponen</div>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <div class="status-section">
                                    <div class="status-header">
                                        <i class="bi bi-gear-fill"></i>
                                        <span>Status Pengerjaan</span>
                                    </div>
                                    <div class="status-display">
                                        <?php
                                            $status_class = ($alur['status_pengerjaan'] == 'Sedang Dikerjakan')
                                                ? 'status-dikerjakan'
                                                : 'status-pending';
                                            $status_icon = ($alur['status_pengerjaan'] == 'Sedang Dikerjakan')
                                                ? 'bi-play-circle-fill'
                                                : 'bi-pause-circle-fill';
                                        ?>
                                        <div class="current-status-badge <?php echo $status_class; ?>">
                                            <i class="bi <?php echo $status_icon; ?>"></i>
                                            <span><?php echo htmlspecialchars($alur['status_pengerjaan']); ?></span>
                                        </div>

                                        <?php if ($alur['has_access']): ?>
                                        <button
                                            class="change-status-btn"
                                            onclick="showStatusModal(<?php echo $id_target; ?>, <?php echo $alur['id_alur']; ?>, <?php echo htmlspecialchars(json_encode($alur['status_pengerjaan']), ENT_QUOTES, 'UTF-8'); ?>)">
                                            <i class="bi bi-pencil-square"></i>
                                            <span>Ubah Status</span>
                                        </button>
                                        <?php endif; ?>
                                    </div>

                                    <div class="pic-section">
                                        <div class="pic-header">
                                            <i class="bi bi-person-check-fill"></i>
                                            <span>Penanggung Jawab</span>
                                        </div>
                                        <div class="pic-name <?php echo empty($alur['penanggung_jawab']) ? 'no-pic' : ''; ?>">
                                            <?php 
                                                echo !empty($alur['penanggung_jawab']) 
                                                    ? htmlspecialchars($alur['penanggung_jawab']) 
                                                    : '⚠️ Belum Ada Penanggung Jawab'; 
                                            ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="card-footer-modern">
                                <a href="produksi/input_harian.php?id_target=<?php echo $id_target; ?>&id_alur=<?php echo $alur['id_alur']; ?>" class="detail-btn">
                                    <i class="bi bi-pencil-square"></i>
                                    <span>Input atau Lihat Detail Tahap</span>
                                    <i class="bi bi-arrow-right-circle-fill"></i>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Modal Konfirmasi -->
        <div class="custom-modal-overlay" id="statusModal">
            <div class="custom-modal">
                <div class="modal-header-custom">
                    <div class="modal-icon">
                        <i class="bi bi-question-circle-fill"></i>
                    </div>
                    <h3>Konfirmasi Perubahan Status</h3>
                </div>
                <div class="modal-body-custom">
                    <p>Apakah Anda yakin ingin mengubah status pengerjaan tahapan ini?</p>
                    <div class="status-change-info">
                        <p>Status saat ini: <strong id="currentStatus"></strong></p>
                        <p style="margin-top: 0.5rem;">Status baru: <strong id="newStatus"></strong></p>
                    </div>
                </div>
                <div class="modal-footer-custom">
                    <button class="modal-btn modal-btn-cancel" onclick="closeStatusModal()">
                        <i class="bi bi-x-circle"></i>
                        <span>Batal</span>
                    </button>
                    <button class="modal-btn modal-btn-confirm" id="confirmBtn">
                        <i class="bi bi-check-circle"></i>
                        <span>Ya, Ubah Status</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Floating Back to Top Button -->
        <button class="back-to-top" id="backToTopBtn" aria-label="Kembali ke atas">
            <i class="bi bi-arrow-up"></i>
        </button>

    </div>
</div>

<script>
// Loading animation
document.addEventListener('DOMContentLoaded', function() {
    const elements = document.querySelectorAll('.loading');
    elements.forEach((el, index) => {
        el.style.animationDelay = (index * 0.1) + 's';
    });
    
    // Animate progress bars
    setTimeout(() => {
        const progressBars = document.querySelectorAll('.progress-bar-fill');
        progressBars.forEach(bar => {
            const width = bar.style.width;
            bar.style.width = '0%';
            setTimeout(() => {
                bar.style.width = width;
            }, 300);
        });
    }, 800);
});

// ========== BACK TO TOP BUTTON ==========
const backToTopBtn = document.getElementById('backToTopBtn');

// Show/hide button based on scroll position
window.addEventListener('scroll', function() {
    if (window.pageYOffset > 300) {
        backToTopBtn.classList.add('show');
    } else {
        backToTopBtn.classList.remove('show');
    }
});

// Scroll to top when clicked
backToTopBtn.addEventListener('click', function() {
    window.scrollTo({
        top: 0,
        behavior: 'smooth'
    });
});

// ========== MODAL KONFIRMASI BARU ========== 
let pendingStatusChange = null;

function showStatusModal(id_target, id_alur, currentStatus) {
    const newStatus = (currentStatus === 'Sedang Dikerjakan') ? 'Pending' : 'Sedang Dikerjakan';
    
    document.getElementById('currentStatus').textContent = currentStatus;
    document.getElementById('newStatus').textContent = newStatus;
    
    pendingStatusChange = {
        id_target: id_target,
        id_alur: id_alur,
        status: newStatus
    };
    
    document.getElementById('statusModal').classList.add('active');
    
    document.getElementById('confirmBtn').onclick = function() {
        confirmStatusChange();
    };
}

function closeStatusModal() {
    document.getElementById('statusModal').classList.remove('active');
    pendingStatusChange = null;
}

function confirmStatusChange() {
    if (!pendingStatusChange) return;
    
    const formData = new FormData();
    formData.append('id_target', pendingStatusChange.id_target);
    formData.append('id_alur', pendingStatusChange.id_alur);
    formData.append('status', pendingStatusChange.status);

    closeStatusModal();
    showNotification('⏳ Memproses perubahan...', 'info');
    
    fetch('manajemen_laporan/proses_update_status_pengerjaan.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            return response.json().then(err => { 
                throw new Error(err.message || 'Terjadi kesalahan pada server.'); 
            });
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            showNotification('✅ ' + data.message, 'success');
            setTimeout(() => {
                location.reload();
            }, 1500);
        } else {
            showNotification('❌ Gagal: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('❌ Terjadi kesalahan: ' + error.message, 'error');
    });
}

// Fungsi notifikasi modern
function showNotification(message, type) {
    const notif = document.createElement('div');
    const bgColor = type === 'success' 
        ? 'linear-gradient(135deg, #28a745, #20c997)' 
        : type === 'info'
        ? 'linear-gradient(135deg, #17a2b8, #20c997)'
        : 'linear-gradient(135deg, #dc3545, #c82333)';
    
    notif.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 1rem 1.5rem;
        background: ${bgColor};
        color: white;
        border-radius: 50px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        z-index: 10000;
        font-weight: 600;
        animation: slideIn 0.3s ease;
    `;
    notif.textContent = message;
    document.body.appendChild(notif);
    
    setTimeout(() => {
        notif.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => notif.remove(), 300);
    }, 3000);
}

// Tutup modal jika klik di luar
document.getElementById('statusModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeStatusModal();
    }
});

// Tambahkan animasi CSS untuk notifikasi
const notificationStyle = document.createElement('style');
notificationStyle.textContent = `
    @keyframes slideIn {
        from { opacity: 0; transform: translateX(100px); }
        to { opacity: 1; transform: translateX(0); }
    }
    @keyframes slideOut {
        from { opacity: 1; transform: translateX(0); }
        to { opacity: 0; transform: translateX(100px); }
    }
`;
document.head.appendChild(notificationStyle);
</script>

<?php
include_once '../../templates/footer.php';
?>