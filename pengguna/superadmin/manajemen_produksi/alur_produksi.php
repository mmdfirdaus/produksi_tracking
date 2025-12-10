<?php
session_start();

// Periksa otentikasi dan otorisasi
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'superadmin') {
    header("location: ../../auth/login.php");
    exit;
}

include '../../../system/database_connection.php';

// Ambil dan validasi ID Target & ID Barang dari URL
$id_target = isset($_GET['id_target']) ? (int)$_GET['id_target'] : 0;
$id_barang = isset($_GET['id_barang']) ? (int)$_GET['id_barang'] : 0;

if ($id_target === 0 || $id_barang === 0) {
    header("Location: ../master_data/kelola_master_barang.php");
    exit;
}

try {
    // 1. Ambil data target produksi dan nama barang (query header)
    $stmt = $pdo->prepare("
        SELECT pt.nama_permintaan, pt.jumlah_unit, pt.no_spk, mb.nama_barang, mb.kode_barang
        FROM production_targets pt
        JOIN master_barang mb ON pt.id_barang = mb.id_barang
        WHERE pt.id_target = :id_target
    ");
    $stmt->execute([':id_target' => $id_target]);
    $target = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$target) {
        die("Target produksi tidak ditemukan.");
    }

    // Query utama
    $alurs_stmt = $pdo->prepare("
        SELECT
            ma.id_alur,
            ma.nama_alur,
            ma.urutan,
            COALESCE(tas.status_pengerjaan, 'Pending') AS status_pengerjaan,
            COUNT(tm.id_material) AS total_komponen,
            SUM(
                CASE WHEN (tm.jumlah_per_unit * pt.jumlah_unit) <= COALESCE(lh.total_selesai, 0)
                THEN 1 ELSE 0 END
            ) AS komponen_terpenuhi,
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
            FROM laporan_harian
            GROUP BY id_material
        ) lh ON tm.id_material = lh.id_material
        WHERE ab.id_barang = :id_barang
        GROUP BY ma.id_alur, ma.nama_alur, ma.urutan
        ORDER BY ma.urutan ASC
    ");
    
    $alurs_stmt->execute([':id_target' => $id_target, ':id_barang' => $id_barang]);
    $alurs = $alurs_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error saat mengambil data: " . $e->getMessage());
}

$page_title = 'Alur Produksi';
include '../../../templates/header_superadmin.php';
?>

<style>
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
.modern-back-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    background: linear-gradient(45deg, #667eea, #764ba2);
    color: white;
    text-decoration: none;
    border-radius: 50px;
    font-weight: 500;
    transition: all 0.3s ease;
    margin-bottom: 1.5rem;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
}
.modern-back-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
    color: white;
    text-decoration: none;
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
.card-body-modern {
    padding: 2rem;
    text-align: center;
    background: rgba(255, 255, 255, 0.95);
}
.card-body-modern p {
    color: #666;
    line-height: 1.6;
    margin: 0;
}

/* ========== STYLING BARU UNTUK STATUS PENGERJAAN ========== */
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
}

.pic-name.no-pic {
    color: #dc3545;
    font-style: italic;
}

/* ========== MODAL KONFIRMASI CUSTOM ========== */
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

/* ========== END STYLING BARU ========== */

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
@media (max-width: 768px) {
    .content-wrapper { padding: 0 0.5rem; }
    .modern-header { padding: 1.5rem; border-radius: 15px; }
.target-info {
        display: grid; /* Pastikan tetap grid */
        grid-template-columns: 1fr 1fr; /* Buat menjadi 2 kolom */
        gap: 1rem; /* Beri sedikit jarak antar kartu */
    }

    .target-info .info-card:first-child,
    .target-info .info-card:nth-child(2) {
        grid-column: 1 / span 2; 
    }
    .production-grid { grid-template-columns: 1fr; }
    .section-title { font-size: 1.5rem; }
    .modal-footer-custom { flex-direction: column; }
    .status-display { flex-direction: column; }
}
.loading {
    opacity: 0;
    animation: fadeIn 0.6s ease-in-out forwards;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>

<div class="modern-container">
    <div class="content-wrapper">
        <div class="loading">
            <div class="modern-header">
                <a href="detail_barang.php?id=<?php echo htmlspecialchars($id_barang); ?>" class="modern-back-btn">
                    <i class="bi bi-arrow-left"></i>
                    <span>Kembali ke Detail Barang</span>
                </a>
                <h2 style="color: #2c3e50; margin: 0; font-weight: 700;">🏭 Alur Produksi</h2>
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
        <h5>Target Permintaan</h5>
        <p class="value"><?php echo htmlspecialchars($target['nama_permintaan']); ?></p>
        <div class="mt-1">
            <span class="badge bg-white text-dark">
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

            <!-- TAMBAHKAN KODE INI SETELAH TAG <h3 class="section-title"> -->

<!-- View Mode Toggle -->
<div class="view-toggle-container">
    <button class="view-toggle-btn active" data-view="grid" onclick="switchView('grid')">
        <i class="bi bi-grid-3x3-gap-fill"></i>
        <span>Grid</span>
    </button>
    <button class="view-toggle-btn" data-view="compact" onclick="switchView('compact')">
        <i class="bi bi-list"></i>
        <span>Compact</span>
    </button>
</div>

<!-- CSS UNTUK MODE TAMPILAN -->
<style>
/* ========== VIEW TOGGLE BUTTON ========== */
.view-toggle-container {
    display: flex;
    justify-content: center;
    gap: 1rem;
    margin: 2rem 0;
}

.view-toggle-btn {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    background: rgba(255, 255, 255, 0.95);
    border: 2px solid rgba(102, 126, 234, 0.3);
    border-radius: 50px;
    color: #667eea;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
}

.view-toggle-btn:hover {
    background: rgba(102, 126, 234, 0.1);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
}

.view-toggle-btn.active {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    border-color: transparent;
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
}

.view-toggle-btn i {
    font-size: 1.2rem;
}

/* ========== COMPACT MODE - 2 KOLOM TETAP ========== */
.production-grid.compact-mode {
    grid-template-columns: repeat(2, 1fr) !important;
    gap: 1rem;
}

.production-grid.compact-mode .process-card {
    border-radius: 15px;
}

.production-grid.compact-mode .card-header-modern {
    padding: 1rem;
    flex-direction: column;
    gap: 0.5rem;
    text-align: center;
}

.production-grid.compact-mode .process-name {
    font-size: 0.9rem;
}

.production-grid.compact-mode .process-name span {
    display: block;
    font-size: 0.7rem;
    margin-bottom: 0.25rem;
}

.production-grid.compact-mode .status-badge {
    padding: 0.4rem 0.75rem;
    font-size: 0.7rem;
    width: 100%;
    justify-content: center;
}

.production-grid.compact-mode .card-body-modern {
    padding: 1rem;
}

.production-grid.compact-mode .card-body-modern > p:first-child {
    display: none; /* Sembunyikan deskripsi panjang */
}

.production-grid.compact-mode .status-section {
    padding: 1rem;
    margin: 1rem 0 0.75rem;
}

.production-grid.compact-mode .status-header {
    margin-bottom: 0.5rem;
}

.production-grid.compact-mode .status-header i {
    font-size: 1rem;
}

.production-grid.compact-mode .status-header span {
    font-size: 0.7rem;
}

.production-grid.compact-mode .current-status-badge {
    padding: 0.5rem 1rem;
    font-size: 0.75rem;
    width: 100%;
    justify-content: center;
}

.production-grid.compact-mode .change-status-btn {
    padding: 0.5rem 1rem;
    font-size: 0.75rem;
    width: 100%;
}

.production-grid.compact-mode .pic-section {
    padding: 0.75rem;
    margin-top: 0.75rem;
}

.production-grid.compact-mode .pic-header i {
    font-size: 1rem;
}

.production-grid.compact-mode .pic-header span {
    font-size: 0.7rem;
}

.production-grid.compact-mode .pic-name {
    font-size: 0.85rem;
}

.production-grid.compact-mode .card-footer-modern {
    padding: 1rem;
}

.production-grid.compact-mode .detail-btn {
    padding: 0.75rem 1rem;
    font-size: 0.85rem;
}

.production-grid.compact-mode .detail-btn span {
    display: none; /* Sembunyikan teks, hanya icon */
}

/* Progress bar di compact mode */
.production-grid.compact-mode .card-body-modern > div[style*="margin-top"] {
    margin-top: 0.75rem !important;
    padding: 0.5rem !important;
}

.production-grid.compact-mode .card-body-modern small {
    font-size: 0.7rem !important;
}

/* ========== RESPONSIVE UNTUK MOBILE ========== */
@media (max-width: 768px) {
    .view-toggle-container {
        gap: 0.5rem;
        margin: 1.5rem 1rem;
    }
    
    .view-toggle-btn {
        padding: 0.6rem 1rem;
        font-size: 0.85rem;
    }
    
    .view-toggle-btn i {
        font-size: 1rem;
    }
    
    .view-toggle-btn span {
        font-size: 0.8rem;
    }
    
    /* PENTING: Tetap 2 kolom di mobile untuk compact mode */
    .production-grid.compact-mode {
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 0.75rem;
        padding: 0 0.5rem;
    }
    
    .production-grid.compact-mode .process-card {
        border-radius: 12px;
    }
    
    .production-grid.compact-mode .card-header-modern {
        padding: 0.75rem 0.5rem;
    }
    
    .production-grid.compact-mode .process-name {
        font-size: 0.75rem;
        line-height: 1.2;
    }
    
    .production-grid.compact-mode .process-name span {
        font-size: 0.65rem;
    }
    
    .production-grid.compact-mode .status-badge {
        padding: 0.3rem 0.5rem;
        font-size: 0.6rem;
    }
    
    .production-grid.compact-mode .status-badge i {
        font-size: 0.7rem;
    }
    
    .production-grid.compact-mode .card-body-modern {
        padding: 0.75rem 0.5rem;
    }
    
    .production-grid.compact-mode .status-section {
        padding: 0.75rem 0.5rem;
        margin: 0.75rem 0 0.5rem;
    }
    
    .production-grid.compact-mode .status-header {
        margin-bottom: 0.5rem;
    }
    
    .production-grid.compact-mode .status-header i {
        font-size: 0.9rem;
    }
    
    .production-grid.compact-mode .status-header span {
        font-size: 0.65rem;
    }
    
    .production-grid.compact-mode .current-status-badge {
        padding: 0.4rem 0.75rem;
        font-size: 0.65rem;
    }
    
    .production-grid.compact-mode .current-status-badge i {
        font-size: 0.75rem;
    }
    
    .production-grid.compact-mode .change-status-btn {
        padding: 0.4rem 0.75rem;
        font-size: 0.65rem;
    }
    
    .production-grid.compact-mode .change-status-btn i {
        font-size: 0.75rem;
    }
    
    .production-grid.compact-mode .pic-section {
        padding: 0.5rem;
        margin-top: 0.5rem;
    }
    
    .production-grid.compact-mode .pic-header i {
        font-size: 0.85rem;
    }
    
    .production-grid.compact-mode .pic-header span {
        font-size: 0.6rem;
    }
    
    .production-grid.compact-mode .pic-name {
        font-size: 0.7rem;
    }
    
    .production-grid.compact-mode .card-footer-modern {
        padding: 0.75rem 0.5rem;
    }
    
    .production-grid.compact-mode .detail-btn {
        padding: 0.6rem 0.75rem;
        font-size: 0.75rem;
    }
    
    .production-grid.compact-mode .card-body-modern > div[style*="margin-top"] {
        margin-top: 0.5rem !important;
        padding: 0.4rem !important;
    }
    
    .production-grid.compact-mode .card-body-modern small {
        font-size: 0.65rem !important;
    }
    
    /* Grid mode default di mobile (existing) */
    .production-grid:not(.compact-mode) {
        grid-template-columns: 1fr !important;
    }
}

/* Extra Small Devices - Tetap 2 kolom untuk compact */
@media (max-width: 375px) {
    .production-grid.compact-mode {
        gap: 0.5rem;
    }
    
    .production-grid.compact-mode .process-name {
        font-size: 0.7rem;
    }
    
    .production-grid.compact-mode .process-name span {
        font-size: 0.6rem;
    }
    
    .production-grid.compact-mode .status-badge {
        font-size: 0.55rem;
        padding: 0.25rem 0.4rem;
    }
    
    .production-grid.compact-mode .current-status-badge {
        font-size: 0.6rem;
        padding: 0.35rem 0.6rem;
    }
    
    .production-grid.compact-mode .change-status-btn {
        font-size: 0.6rem;
        padding: 0.35rem 0.6rem;
    }
    
    .production-grid.compact-mode .pic-name {
        font-size: 0.65rem;
    }
    
    .production-grid.compact-mode .detail-btn {
        font-size: 0.7rem;
        padding: 0.5rem 0.6rem;
    }
}

/* Landscape mode - tetap 2 kolom */
@media (max-width: 768px) and (orientation: landscape) {
    .production-grid.compact-mode {
        grid-template-columns: repeat(2, 1fr) !important;
    }
}
</style>

<!-- JAVASCRIPT UNTUK TOGGLE VIEW -->
<script>
// Fungsi untuk switch view mode
function switchView(mode) {
    const grid = document.querySelector('.production-grid');
    const buttons = document.querySelectorAll('.view-toggle-btn');
    
    // Update active button
    buttons.forEach(btn => {
        if (btn.dataset.view === mode) {
            btn.classList.add('active');
        } else {
            btn.classList.remove('active');
        }
    });
    
    // Toggle class
    if (mode === 'compact') {
        grid.classList.add('compact-mode');
        // Simpan preferensi ke localStorage
        localStorage.setItem('viewMode', 'compact');
    } else {
        grid.classList.remove('compact-mode');
        localStorage.setItem('viewMode', 'grid');
    }
}

// Load saved view preference
document.addEventListener('DOMContentLoaded', function() {
    const savedView = localStorage.getItem('viewMode');
    if (savedView === 'compact') {
        switchView('compact');
    }
});
</script>
            <?php if (empty($alurs)): ?>
                <div class="empty-state loading">
                    <div class="empty-state-icon"><i class="bi bi-info-circle"></i></div>
                    <h4>Belum Ada Alur Produksi</h4>
                    <p>
                        Alur produksi untuk barang ini belum diatur.<br>
                        Silakan atur melalui <strong>Kelola Master Barang</strong>.
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
                                    if ($is_completed):
                                ?>
                                    <div class="status-badge status-completed">
                                        <i class="bi bi-check-circle-fill"></i>
                                        <span>Selesai</span>
                                    </div>
                                <?php else: ?>
                                    <div class="status-badge" style="background: linear-gradient(45deg, #ff9a56, #ff6b6b); color: white;">
                                        <i class="bi bi-clock-fill"></i>
                                        <span>Proses</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="card-body-modern">
                                <p>
                                    Klik tombol di bawah untuk melihat detail progres dan menginput data material pada tahap ini.
                                </p>
                                <?php if ($alur['total_komponen'] > 0): ?>
                                    <div style="margin-top: 1rem; padding: 0.75rem; background: rgba(102, 126, 234, 0.1); border-radius: 10px;">
                                        <small style="color: #667eea; font-weight: 600;">
                                            Progress: <?php echo (int)$alur['komponen_terpenuhi']; ?> / <?php echo (int)$alur['total_komponen']; ?> komponen
                                        </small>
                                    </div>
                                <?php endif; ?>

                                <!-- ========== BAGIAN STATUS PENGERJAAN YANG DIPERBAIKI ========== -->
                                <div class="status-section">
                                    <div class="status-header">
                                        <i class="bi bi-gear-fill"></i>
                                        <span>Status Pengerjaan</span>
                                    </div>
                                    <div class="status-display">
                                        <?php
                                            $status_class = ($alur['status_pengerjaan'] == 'Sedang Dikerjakan') ? 'status-dikerjakan' : 'status-pending';
                                            $status_icon = ($alur['status_pengerjaan'] == 'Sedang Dikerjakan') ? 'bi-play-circle-fill' : 'bi-pause-circle-fill';
                                        ?>
                                        <div class="current-status-badge <?php echo $status_class; ?>">
                                            <i class="bi <?php echo $status_icon; ?>"></i>
                                            <span><?php echo htmlspecialchars($alur['status_pengerjaan']); ?></span>
                                        </div>
                                        <button class="change-status-btn" onclick="showStatusModal(<?php echo $id_target; ?>, <?php echo $alur['id_alur']; ?>, '<?php echo htmlspecialchars($alur['status_pengerjaan']); ?>')">
                                            <i class="bi bi-pencil-square"></i>
                                            <span>Ubah Status</span>
                                        </button>
                                    </div>
                                </div>

                                <!-- ========== BAGIAN PENANGGUNG JAWAB ========== -->
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
                            <div class="card-footer-modern">
                                <a href="material.php?id_target=<?php echo $id_target; ?>&id_alur=<?php echo $alur['id_alur']; ?>&id_barang=<?php echo $id_barang; ?>" class="detail-btn">
                                    <i class="bi bi-eye-fill"></i>
                                    <span>Lihat Detail Tahap</span>
                                    <i class="bi bi-arrow-right-circle-fill"></i>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ========== MODAL KONFIRMASI CUSTOM ========== -->
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

<script>
// Animasi
document.addEventListener('DOMContentLoaded', function() {
    const elements = document.querySelectorAll('.loading');
    elements.forEach((el, index) => {
        el.style.animationDelay = (index * 0.1) + 's';
    });
});

document.querySelectorAll('.process-card').forEach(card => {
    card.addEventListener('mouseenter', function() {
        this.style.transform = 'translateY(-10px) scale(1.02)';
    });
    
    card.addEventListener('mouseleave', function() {
        this.style.transform = 'translateY(0) scale(1)';
    });
});

// ========== FUNGSI MODAL KONFIRMASI BARU ========== 
let pendingStatusChange = null;

function showStatusModal(id_target, id_alur, currentStatus) {
    const newStatus = (currentStatus === 'Sedang Dikerjakan') ? 'Pending' : 'Sedang Dikerjakan';
    
    // Set informasi di modal
    document.getElementById('currentStatus').textContent = currentStatus;
    document.getElementById('newStatus').textContent = newStatus;
    
    // Simpan data untuk diproses nanti
    pendingStatusChange = {
        id_target: id_target,
        id_alur: id_alur,
        status: newStatus
    };
    
    // Tampilkan modal
    document.getElementById('statusModal').classList.add('active');
    
    // Set event listener untuk tombol confirm
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

    // Tutup modal dan tampilkan loading
    closeStatusModal();
    
    // Kirim request ke server
    fetch('proses_update_status_pengerjaan.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Tampilkan notifikasi sukses
            showNotification('✅ ' + data.message, 'success');
            // Reload halaman setelah delay singkat
            setTimeout(() => {
                location.reload();
            }, 1500);
        } else {
            showNotification('❌ Error: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('❌ Terjadi kesalahan saat menghubungi server.', 'error');
    });
}

// Fungsi notifikasi sederhana
function showNotification(message, type) {
    const notif = document.createElement('div');
    notif.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 1rem 1.5rem;
        background: ${type === 'success' ? 'linear-gradient(135deg, #28a745, #20c997)' : 'linear-gradient(135deg, #dc3545, #c82333)'};
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
document.getElementById('statusModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeStatusModal();
    }
});

// Tambahkan animasi CSS untuk notifikasi
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateX(100px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }
    @keyframes slideOut {
        from {
            opacity: 1;
            transform: translateX(0);
        }
        to {
            opacity: 0;
            transform: translateX(100px);
        }
    }
`;
document.head.appendChild(style);
</script>

<?php include '../../../templates/footer.php'; ?>