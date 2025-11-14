<?php
session_start();

// Pengecekan sesi dan peran superadmin (dari kode lama)
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'superadmin') {
    header("location: ../../auth/login.php");
    exit;
}

// Koneksi dan fungsi-fungsi PHP (dari kode lama)
include '../../../templates/header_superadmin.php';
include '../../../system/database_connection.php';

// Validasi ID barang dari URL (dari kode lama)
$id_barang = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id_barang === 0) {
    header("Location: ../master_data/kelola_master_barang.php");
    exit;
}

// =================================================================
// FUNGSI PHP (Dipertahankan dari kode lama karena penting)
// =================================================================

/**
 * Cek apakah semua material untuk sebuah target sudah selesai diproduksi.
 */
function isTargetCompleted($pdo, $id_target, $id_barang) {
    $stmt = $pdo->prepare("
        SELECT
            COUNT(tm.id_material) AS total_material,
            SUM(CASE WHEN (tm.jumlah_per_unit * pt.jumlah_unit) <= COALESCE(lh.total_selesai, 0) THEN 1 ELSE 0 END) AS material_selesai
        FROM target_material tm
        JOIN production_targets pt ON tm.id_target = pt.id_target
        JOIN alur_barang ab ON tm.id_alur = ab.id_alur AND ab.id_barang = :id_barang
        LEFT JOIN (
            SELECT id_material, SUM(jumlah_selesai) as total_selesai
            FROM laporan_harian
            GROUP BY id_material
        ) lh ON tm.id_material = lh.id_material
        WHERE tm.id_target = :id_target
    ");
    $stmt->execute([':id_target' => $id_target, ':id_barang' => $id_barang]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result && $result['total_material'] > 0 && $result['total_material'] == $result['material_selesai']) {
        return true;
    }
    return false;
}

// =================================================================
// PENGAMBILAN DATA DARI DATABASE (dari kode lama)
// =================================================================
try {
    // Ambil data detail barang
    // DENGAN BARIS INI:
$barang_stmt = $pdo->prepare("
    SELECT mb.*, mk.nama_kategori 
    FROM master_barang mb 
    LEFT JOIN master_kategori mk ON mb.id_kategori = mk.id_kategori 
    WHERE mb.id_barang = ?
");
    $barang_stmt->execute([$id_barang]);
    $barang = $barang_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$barang) {
        die("Barang tidak ditemukan.");
    }

    // --- AWAL LOGIKA PAGINATION (DIGABUNGKAN DARI KODE BARU) ---
    $data_per_halaman = 8; // Jumlah target per halaman, bisa disesuaikan
    
    // Query untuk menghitung total data target yang 'ongoing'
    $query_jumlah_data = $pdo->prepare("SELECT COUNT(*) as total FROM production_targets WHERE id_barang = ? AND status = 'ongoing' AND is_active = 1");
    $query_jumlah_data->execute([$id_barang]);
    $result_jumlah = $query_jumlah_data->fetch(PDO::FETCH_ASSOC);
    $total_data = $result_jumlah['total'];
    
    $jumlah_halaman = ceil($total_data / $data_per_halaman);
    $halaman_aktif = (isset($_GET['halaman'])) ? (int)$_GET['halaman'] : 1;
    $awal_data = ($halaman_aktif - 1) * $data_per_halaman;
    // --- AKHIR LOGIKA PAGINATION ---

    // --- PERUBAHAN BARU #1: Query SQL dimodifikasi untuk pengurutan prioritas ---
    // Logika pengurutan:
    // 1. is_priority DESC: Target prioritas (1) akan selalu di atas target normal (0).
    // 2. priority_deadline ASC: Di antara target prioritas, yang tenggatnya paling dekat akan muncul lebih dulu.
    // 3. created_at DESC: Untuk target normal atau prioritas dengan tenggat yang sama, yang terbaru akan di atas.
    // --- PERBAIKAN PADA QUERY UTAMA ---
$target_stmt = $pdo->prepare("
    SELECT * FROM production_targets 
    WHERE id_barang = :id_barang AND status = 'ongoing' AND is_active = 1
    ORDER BY is_priority DESC, priority_deadline ASC, created_at DESC
    LIMIT :awal_data, :data_per_halaman
");
// Binding semua parameter yang dibutuhkan
$target_stmt->bindValue(':id_barang', $id_barang, PDO::PARAM_INT); // Tambahkan ini
$target_stmt->bindValue(':awal_data', $awal_data, PDO::PARAM_INT);
$target_stmt->bindValue(':data_per_halaman', $data_per_halaman, PDO::PARAM_INT);

// Cukup eksekusi satu kali saja
$target_stmt->execute(); 

$targets = $target_stmt->fetchAll(PDO::FETCH_ASSOC);
// --- AKHIR PERBAIKAN ---
    // --- AKHIR PERUBAHAN BARU #1 ---

    $mesin_stmt = $pdo->prepare("
        SELECT DISTINCT ma.nama_alur
        FROM alur_barang ab
        JOIN master_alur ma ON ab.id_alur = ma.id_alur
        WHERE ab.id_barang = ? AND (
            ma.nama_alur LIKE '%CNC%' OR
            ma.nama_alur LIKE '%BENDING%' OR
            ma.nama_alur LIKE '%MILLING%' OR
            ma.nama_alur LIKE '%JQ%' OR
            ma.nama_alur LIKE '%PAINTING%' OR
            ma.nama_alur LIKE '%FINISHING%' OR
            ma.nama_alur LIKE '%TREATMENT%' OR
            ma.nama_alur LIKE '%ASSY%' OR
            ma.nama_alur LIKE '%FABRIKASI%' OR
            ma.nama_alur LIKE '%NC%' OR
            ma.nama_alur LIKE '%MESIN%' OR
            ma.nama_alur LIKE '%PLAT%' OR
            ma.nama_alur LIKE '%WELDING%'
        )
    ");
    $mesin_stmt->execute([$id_barang]);
    $mesin_list_raw = $mesin_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Ubah array hasil query menjadi string yang siap ditampilkan
    $mesin_display = !empty($mesin_list_raw)
        ? implode(', ', array_column($mesin_list_raw, 'nama_alur'))
        : null;

} catch (PDOException $e) {
    die("Error saat mengambil data: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Barang - <?php echo htmlspecialchars($barang['nama_barang']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        /* Semua CSS dari kode lama Anda tetap dipertahankan di sini */
        :root {
            --primary-color: #6366f1;
            --secondary-color: #8b5cf6;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --dark-color: #1f2937;
            --light-bg: #f8fafc;
            --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --card-shadow-hover: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .main-container {
            padding: 2rem 1rem;
            max-width: 1400px;
            margin: 0 auto;
        }
        .modern-card {
            background: rgba(255, 255, 255, 0.90);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
        }
        .modern-card:hover {
            box-shadow: var(--card-shadow-hover);
            transform: translateY(-2px);
        }
        .back-button {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
            backdrop-filter: blur(10px);
            border-radius: 50px;
            padding: 0.75rem 1.5rem;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 600;
        }
        .back-button:hover {
            background: var(--primary-color);
            color: white;
            transform: translateX(-5px);
        }
        .product-detail-card {
            padding: 2rem;
            margin-bottom: 2rem;
        }
        .product-image {
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
            max-height: 300px;
            object-fit: cover;
            width: 100%;
        }
        .product-image:hover {
            transform: scale(1.05);
        }
        .product-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: 1rem;
        }
        .product-info {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        .info-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            border-radius: 12px;
            border-left: 4px solid var(--primary-color);
            border: 1px solid #d1d5db;
        }
        .info-icon {
            color: var(--primary-color);
            font-size: 1.25rem;
        }
        .targets-section {
            padding: 2rem;
        }
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .section-title {
            font-size: 1.75rem;
            font-weight: 600;
            color: var(--dark-color);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .add-target-btn {
            background: linear-gradient(135deg, var(--success-color), #059669);
            border: none;
            border-radius: 50px;
            padding: 0.75rem 1.5rem;
            color: white;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }
        .add-target-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(16, 185, 129, 0.4);
        }
        .target-card {
            background: white;
            border-radius: 16px;
            margin-bottom: 1.5rem;
            overflow: hidden;
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
            border: 1px solid #e2e8f0;
        }
        .target-card:hover {
            box-shadow: var(--card-shadow-hover);
        }
        .target-card.priority {
            border: 2px solid var(--danger-color);
            box-shadow: 0 4px 20px rgba(239, 68, 68, 0.2);
        }
        .target-header {
            background: var(--light-bg);
            padding: 1.5rem;
            border-bottom: 1px solid #e2e8f0;
        }
        .target-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 0;
        }
        .priority-badge {
            background: linear-gradient(135deg, var(--danger-color), #dc2626);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 600;
        }
        .target-body {
            padding: 1.5rem;
        }
        .target-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .info-card {
            background: var(--light-bg);
            padding: 1rem;
            border-radius: 12px;
            text-align: center;
        }
        .info-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
        }
        .info-label {
            font-size: 0.875rem;
            color: #6b7280;
            margin-top: 0.25rem;
        }
        .action-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            justify-content: flex-end;
            align-items: center; /* Menyelaraskan item secara vertikal */
        }
        .btn-modern {
            border-radius: 12px;
            padding: 0.6rem 1rem;
            font-weight: 600;
            border: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
        }
        .btn-modern:hover {
            transform: translateY(-2px);
        }
        .btn-complete {
            background: linear-gradient(135deg, var(--success-color), #059669);
            color: white;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }
        .btn-flow {
            background: linear-gradient(135deg, var(--primary-color), #4f46e5);
            color: white;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
        }
        .btn-preview {
            background: linear-gradient(135deg, var(--secondary-color), #7c3aed);
            color: white;
            box-shadow: 0 4px 12px rgba(139, 92, 246, 0.3);
        }
        .dropdown-toggle-modern {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 0.6rem;
            color: #6b7280;
            transition: all 0.3s ease;
        }
        .dropdown-toggle-modern:hover {
            background: #f1f5f9;
        }
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: #6b7280;
            background-color: var(--light-bg);
            border-radius: 16px;
        }
        .empty-state-icon {
            font-size: 4rem;
            color: #d1d5db;
            margin-bottom: 1rem;
        }
        .modal-content {
            border-radius: 20px;
        }

        /* ============================================
   RESPONSIVE DESIGN UNTUK MOBILE - PROFESSIONAL
   Tambahkan di akhir CSS yang sudah ada
   ============================================ */

/* Tablet & Small Desktop (768px - 1024px) */
@media (max-width: 1024px) {
    .main-container {
        padding: 1.5rem 0.75rem;
    }
    
    .product-title {
        font-size: 2rem;
    }
    
    .target-info {
        grid-template-columns: repeat(2, 1fr);
    }
}

/* Mobile Devices (≤ 768px) */
@media (max-width: 768px) {
    /* ========== CONTAINER & SPACING ========== */
    body {
        font-size: 14px;
    }
    
    .main-container {
        padding: 1rem 0.5rem;
    }
    
    /* ========== BACK BUTTON ========== */
    .back-button {
        padding: 0.6rem 1rem;
        font-size: 0.85rem;
        width: 100%;
        justify-content: center;
        margin-bottom: 1rem;
    }
    
    .back-button i {
        font-size: 1rem;
    }
    
    /* ========== PRODUCT DETAIL CARD ========== */
    .modern-card {
        border-radius: 15px;
        margin-bottom: 1rem;
    }
    
    .product-detail-card {
        padding: 1.5rem 1rem;
    }
    
    .product-detail-card .row {
        flex-direction: column;
    }
    
    .product-image {
        max-height: 200px;
        margin-bottom: 1.5rem;
        border-radius: 12px;
    }
    
    .product-title {
        font-size: 1.5rem;
        margin-bottom: 1rem;
        text-align: center;
    }
    
    .product-info {
        gap: 0.75rem;
    }
    
    .info-item {
        padding: 0.75rem;
        font-size: 0.85rem;
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
    
    .info-icon {
        font-size: 1.1rem;
    }
    
    /* ========== TARGETS SECTION ========== */
    .targets-section {
        padding: 1.5rem 1rem;
    }
    
    .section-header {
        flex-direction: column;
        align-items: stretch;
        gap: 1rem;
    }
    
    .section-title {
        font-size: 1.25rem;
        justify-content: center;
        text-align: center;
    }
    
    .section-title i {
        font-size: 1.25rem;
    }
    
    /* ========== ACTION BUTTONS IN HEADER ========== */
    .section-header > div {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }
    
    .section-header .btn {
        width: 100%;
        justify-content: center;
    }
    
    .add-target-btn {
        padding: 0.75rem 1.25rem;
        font-size: 0.9rem;
    }
    
    /* ========== TARGET CARDS ========== */
    .target-card {
        border-radius: 12px;
        margin-bottom: 1rem;
    }
    
    .target-card.priority {
        border-width: 3px;
    }
    
    .target-header {
        padding: 1rem;
    }
    
    .target-header .d-flex {
        flex-direction: column;
        align-items: flex-start !important;
        gap: 0.75rem;
    }
    
    .target-title {
        font-size: 1.1rem;
        line-height: 1.4;
    }
    
    .priority-badge {
        padding: 0.35rem 0.75rem;
        font-size: 0.75rem;
    }
    
    .target-header small {
        font-size: 0.75rem;
        display: block;
        margin-top: 0.25rem;
    }
    
    /* ========== TARGET BODY ========== */
    .target-body {
        padding: 1rem;
    }
    
    .target-info {
        grid-template-columns: 1fr 1fr;
        gap: 0.75rem;
        margin-bottom: 1rem;
    }
    
    .info-card {
        padding: 0.75rem;
    }
    
    .info-value {
        font-size: 1.25rem;
    }
    
    .info-label {
        font-size: 0.75rem;
    }
    
    /* ========== ACTION BUTTONS (CRITICAL FOR MOBILE) ========== */
    .action-buttons {
        flex-direction: column;
        gap: 0.5rem;
        margin-top: 1rem;
    }
    
    /* Semua button full width */
    .action-buttons .btn-modern {
        width: 100%;
        justify-content: center;
        padding: 0.75rem 1rem;
        font-size: 0.9rem;
    }
    
    .action-buttons .btn-modern i {
        font-size: 1rem;
    }
    
    /* Dropdown tetap inline dengan button terakhir */
    .action-buttons .dropdown {
        width: 100%;
    }
    
    .action-buttons .dropdown .dropdown-toggle-modern {
        width: 100%;
        padding: 0.75rem 1rem;
        text-align: center;
        font-size: 0.9rem;
    }
    
    .action-buttons .dropdown .dropdown-toggle-modern i {
        font-size: 1rem;
    }
    
    /* ========== EMPTY STATE ========== */
    .empty-state {
        padding: 2rem 1rem;
    }
    
    .empty-state-icon {
        font-size: 3rem;
    }
    
    .empty-state h4 {
        font-size: 1.1rem;
    }
    
    .empty-state p {
        font-size: 0.85rem;
    }
    
    /* ========== PAGINATION ========== */
    .pagination {
        flex-wrap: wrap;
        gap: 0.25rem;
    }
    
    .pagination .page-link {
        padding: 0.5rem 0.75rem;
        font-size: 0.85rem;
    }
    
    /* ========== MODALS ========== */
    .modal-dialog {
        margin: 0.5rem;
    }
    
    .modal-content {
        border-radius: 15px;
    }
    
    .modal-header {
        padding: 1rem;
    }
    
    .modal-title {
        font-size: 1rem;
    }
    
    .modal-body {
        padding: 1rem;
    }
    
    .modal-body .form-label {
        font-size: 0.85rem;
    }
    
    .modal-body .form-control,
    .modal-body .form-select {
        font-size: 0.9rem;
    }
    
    .modal-footer {
        padding: 1rem;
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .modal-footer .btn {
        width: 100%;
        margin: 0 !important;
    }
    
    /* Modal khusus untuk preview progress */
    .modal-xl {
        max-width: 95%;
        margin: 0.5rem auto;
    }
    
    .modal-xl .table-responsive {
        font-size: 0.75rem;
    }
    
    .modal-xl .table th,
    .modal-xl .table td {
        padding: 0.5rem 0.25rem;
    }
    
    /* ========== DROPDOWN MENU ========== */
    .dropdown-menu {
        font-size: 0.85rem;
        min-width: 100%;
    }
    
    .dropdown-item {
        padding: 0.75rem 1rem;
    }
    
    .dropdown-item i {
        font-size: 1rem;
    }
}

/* Extra Small Devices (≤ 375px) */
@media (max-width: 375px) {
    .main-container {
        padding: 0.75rem 0.25rem;
    }
    
    .product-title {
        font-size: 1.25rem;
    }
    
    .section-title {
        font-size: 1.1rem;
    }
    
    .target-title {
        font-size: 1rem;
    }
    
    .target-info {
        grid-template-columns: 1fr;
    }
    
    .info-value {
        font-size: 1.1rem;
    }
    
    .btn-modern {
        padding: 0.6rem 0.75rem;
        font-size: 0.85rem;
    }
    
    .action-buttons .btn-modern i {
        font-size: 0.9rem;
    }
    
    /* Stack info items di extra small */
    .info-item {
        font-size: 0.8rem;
    }
}

/* Landscape Mode untuk Mobile */
@media (max-width: 768px) and (orientation: landscape) {
    .product-detail-card .row {
        flex-direction: row;
    }
    
    .product-image {
        max-height: 180px;
    }
    
    .target-info {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .section-header {
        flex-direction: row;
        justify-content: space-between;
    }
    
    .section-header > div {
        flex-direction: row;
        width: auto;
    }
}

/* ============================================
   ENHANCEMENT: Touch-Friendly Improvements
   ============================================ */
@media (max-width: 768px) {
    /* Larger touch targets */
    .btn, .dropdown-toggle, a {
        min-height: 44px;
    }
    
    /* Better form controls */
    .form-control, .form-select {
        font-size: 16px; /* Prevents zoom on iOS */
        padding: 0.75rem;
    }
    
    /* Better spacing for readability */
    p, .text-muted {
        line-height: 1.6;
    }
    
    /* Alert improvements */
    .alert {
        font-size: 0.9rem;
        padding: 1rem;
    }
}

/* ============================================
   ACCESSIBILITY IMPROVEMENTS
   ============================================ */
@media (max-width: 768px) {
    /* Focus states more visible on mobile */
    .btn:focus,
    .form-control:focus,
    .form-select:focus {
        outline: 2px solid var(--primary-color);
        outline-offset: 2px;
    }
    
    /* Better contrast for text */
    .text-muted {
        color: #495057 !important;
    }
}

/* ============================================
   LOADING & ANIMATION OPTIMIZATION
   ============================================ */
@media (max-width: 768px) {
    /* Reduce animations on mobile for better performance */
    .modern-card:hover {
        transform: none;
    }
    
    .btn-modern:hover {
        transform: none;
    }
    
    /* Keep only essential animations */
    .target-card {
        transition: box-shadow 0.2s ease;
    }
}

/* ============================================
   PRINT STYLES (Bonus)
   ============================================ */
@media print {
    .back-button,
    .add-target-btn,
    .action-buttons,
    .modal {
        display: none !important;
    }
    
    .modern-card {
        break-inside: avoid;
        box-shadow: none;
        border: 1px solid #ddd;
    }
    
    body {
        background: white !important;
    }
}

    </style>
</head>
<body>
    <div class="main-container">
        <div class="mb-4">
            <a href="../master_data/kelola_master_barang.php" class="back-button">
                <i class="bi bi-arrow-left"></i>
                Kembali ke Daftar Barang
            </a>
        </div>

        <div class="modern-card product-detail-card">
            <div class="row align-items-center">
                <div class="col-lg-4 col-md-5 text-center mb-4 mb-md-0">
                    <img src="../../../uploads/<?php echo htmlspecialchars($barang['gambar'] ?? 'default.png'); ?>" class="product-image" alt="Gambar Barang <?php echo htmlspecialchars($barang['nama_barang']); ?>">
                </div>
                <div class="col-lg-8 col-md-7">
                    <h1 class="product-title"><?php echo htmlspecialchars($barang['nama_barang']); ?></h1>
                    <div class="product-info">
                        <div class="info-item">
                            <i class="bi bi-tag-fill info-icon"></i>
                            <div>
                                <strong>Kategori:</strong>
                                <span class="ms-2"><?php echo htmlspecialchars($barang['nama_kategori'] ?? '-'); ?></span>
                            </div>
                        </div>
                        <div class="info-item">
                            <i class="bi bi-gear-fill info-icon"></i>
                            <div>
                                <strong>Mesin:</strong>
                                <?php if ($mesin_display): ?>
                                    <span class="ms-2 fw-bold"><?php echo htmlspecialchars($mesin_display); ?></span>
                                <?php else: ?>
                                    <span class="ms-2 text-muted fst-italic">Tidak ada mesin terhubung di alur produksi</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="modern-card targets-section">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="bi bi-bullseye"></i>
                    Target Produksi (On-Going)
                </h2>
                <div>
                    <a href="arsip_target.php?id_barang=<?php echo $id_barang; ?>" class="btn btn-outline-secondary me-2">
                        <i class="bi bi-archive"></i> Lihat Arsip
                    </a>
                    <button type="button" class="btn add-target-btn" data-bs-toggle="modal" data-bs-target="#tambahTargetModal">
                        <i class="bi bi-plus-circle"></i>
                        Tambah Target Baru
                    </button>
                </div>
            </div>

            <?php if (empty($targets)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon"><i class="bi bi-inbox"></i></div>
                    <h4>Tidak ada target produksi</h4>
                    <p>Belum ada target produksi yang sedang berjalan untuk barang ini.</p>
                </div>
            <?php else: ?>
                <?php foreach ($targets as $target): ?>
                    <?php 
                        // Kelas 'priority' kini dikontrol oleh kolom boolean 'is_priority'
                        $card_class = $target['is_priority'] ? 'priority' : ''; 
                    ?>
                    <div class="target-card <?php echo $card_class; ?>">
                        <div class="target-header">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <h3 class="target-title">
                                    <?php echo htmlspecialchars($target['nama_permintaan']); ?>
                                </h3>
                                <?php if($target['is_priority']): ?>
                                <div>
                                    <span class="badge bg-danger"><i class="bi bi-star-fill me-1"></i> Prioritas</span>
                                    <small class="text-muted fst-italic ms-2">
                                        Tenggat: <?php echo date('d M Y', strtotime($target['priority_deadline'])); ?>
                                    </small>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="target-body">
                            <div class="target-info">
                                <div class="info-card">
                                    <div class="info-value"><?php echo htmlspecialchars($target['jumlah_unit']); ?></div>
                                    <div class="info-label">Jumlah Unit</div>
                                </div>
                                <div class="info-card">
                                    <div class="info-value"><?php echo date('d M Y', strtotime($target['created_at'])); ?></div>
                                    <div class="info-label">Tanggal Dibuat</div>
                                </div>
                            </div>
                            <div class="action-buttons mt-4">
                                <?php if (isTargetCompleted($pdo, $target['id_target'], $id_barang)): ?>
                                <button type="button" class="btn btn-modern btn-complete" data-bs-toggle="modal" data-bs-target="#selesaikanTargetModal" data-target-id="<?php echo $target['id_target']; ?>" data-target-name="<?php echo htmlspecialchars($target['nama_permintaan']); ?>">
                                    <i class="bi bi-check-circle-fill"></i> Selesaikan
                                </button>
                                <?php endif; ?>
                                <a href="alur_produksi.php?id_target=<?php echo $target['id_target']; ?>&id_barang=<?php echo $id_barang; ?>" class="btn btn-modern btn-flow">
                                    <i class="bi bi-diagram-3-fill"></i> Alur Produksi
                                </a>
                                <button type="button" class="btn btn-modern btn-preview" data-bs-toggle="modal" data-bs-target="#previewProgressModal" data-target-id="<?php echo $target['id_target']; ?>" data-target-name="<?php echo htmlspecialchars($target['nama_permintaan']); ?>">
                                    <i class="bi bi-eye-fill"></i> Lihat Progress
                                </button>

                                <button type="button" class="btn btn-modern <?php echo $target['is_priority'] ? 'btn-outline-danger' : 'btn-outline-warning'; ?>"
                                        data-bs-toggle="modal"
                                        data-bs-target="#prioritasTargetModal"
                                        data-id-target="<?php echo $target['id_target']; ?>"
                                        data-is-priority="<?php echo $target['is_priority']; ?>"
                                        data-deadline="<?php echo $target['priority_deadline'] ? date('Y-m-d', strtotime($target['priority_deadline'])) : ''; ?>">
                                    <i class="bi <?php echo $target['is_priority'] ? 'bi-star-slash-fill' : 'bi-star-fill'; ?>"></i>
                                    <?php echo $target['is_priority'] ? 'Batalkan Prioritas' : 'Jadikan Prioritas'; ?>
                                </button>

                                <div class="dropdown">
                                    <button class="btn dropdown-toggle-modern" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Opsi Lainnya">
                                        <i class="bi bi-three-dots-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li>
                                            <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#downloadLaporanModal" data-target-id="<?php echo $target['id_target']; ?>">
                                                <i class="bi bi-download text-primary me-2"></i> Download Laporan
                                            </button>
                                        </li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <button type="button" class="dropdown-item text-warning" data-bs-toggle="modal" data-bs-target="#nonaktifkanModal" data-target-id="<?php echo $target['id_target']; ?>" data-target-name="<?php echo htmlspecialchars($target['nama_permintaan']); ?>">
                                                <i class="bi bi-archive me-2"></i> Nonaktifkan Target
                                            </button>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if ($total_data > $data_per_halaman) : ?>
    <nav aria-label="Page navigation" class="mt-4">
        <ul class="pagination justify-content-center">
            <li class="page-item <?php echo ($halaman_aktif <= 1) ? 'disabled' : ''; ?>">
                <a class="page-link" href="?id=<?php echo $id_barang; ?>&halaman=<?php echo $halaman_aktif - 1; ?>">Previous</a>
            </li>
            <?php for ($i = 1; $i <= $jumlah_halaman; $i++) : ?>
                <li class="page-item <?php echo ($i == $halaman_aktif) ? 'active' : ''; ?>">
                    <a class="page-link" href="?id=<?php echo $id_barang; ?>&halaman=<?php echo $i; ?>"><?php echo $i; ?></a>
                </li>
            <?php endfor; ?>
            <li class="page-item <?php echo ($halaman_aktif >= $jumlah_halaman) ? 'disabled' : ''; ?>">
                <a class="page-link" href="?id=<?php echo $id_barang; ?>&halaman=<?php echo $halaman_aktif + 1; ?>">Next</a>
            </li>
        </ul>
    </nav>
<?php endif; ?>
                <?php endif; ?>
        </div>
    </div>

    <div class="modal fade" id="downloadLaporanModal" tabindex="-1" aria-labelledby="downloadLaporanModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                
                    <div class="modal-header">
                        <h5 class="modal-title" id="downloadLaporanModalLabel">Pilih Bulan Laporan</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form id="form-download-excel" action="download_laporan_ongoing.php" method="post" target="_blank" style="display: none;">
                            <input type="hidden" name="id_target" id="download_id_target_excel">
                            <input type="hidden" name="bulan_laporan" id="download_bulan_laporan_excel">
                        </form>
                        <form id="form-download-pdf" action="proses_download_laporan_ongoing_pdf.php" method="post" target="_blank" style="display: none;">
                            <input type="hidden" name="id_target" id="download_id_target_pdf">
                            <input type="hidden" name="bulan_laporan[]" id="download_bulan_laporan_pdf">
                        </form>

                        <div id="download-laporan-content">
                            <div class="text-center">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer" id="download-laporan-footer" style="display: none;">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                        <button type="submit" class="btn btn-success" id="btn-download-excel" form="form-download-excel" disabled>
                            <i class="bi bi-file-earmark-excel"></i> Download Excel
                        </button>
                        <button type="submit" class="btn btn-danger" id="btn-download-pdf" form="form-download-pdf" disabled>
                            <i class="bi bi-file-earmark-pdf"></i> Download PDF
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="tambahTargetModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="proses_target.php" method="post">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Tambah Target Produksi Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id_barang" value="<?php echo $id_barang; ?>">
                    <div class="mb-3">
                        <label class="form-label">Nama Permintaan / Target</label>
                        <input type="text" class="form-control" name="nama_permintaan" placeholder="Contoh: Produksi Oktober 2025" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Jumlah Unit</label>
                        <input type="number" class="form-control" name="jumlah_unit" min="1" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Prioritas</label>
                        <select name="prioritas" class="form-select" id="selectPrioritas">
                            <option value="Normal">Normal</option>
                            <option value="Prioritas">Prioritas</option>
                        </select>
                    </div>
                    <div class="mb-3" id="containerTanggalPrioritas" style="display: none;">
                        <label class="form-label">Tanggal Tenggat Prioritas</label>
                        <input type="date" class="form-control" name="priority_deadline">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="add_target" class="btn btn-modern btn-flow">
                        <i class="bi bi-save"></i> Simpan Target
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

    <div class="modal fade" id="previewProgressModal" tabindex="-1" aria-labelledby="previewProgressModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="previewProgressModalLabel"><i class="bi bi-graph-up me-2"></i>Progress Produksi: </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="preview-content">
                        <div class="text-center">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-2">Memuat data progress...</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="selesaikanTargetModal" tabindex="-1" aria-labelledby="selesaikanTargetModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="selesaikanTargetModalLabel">
                        <i class="bi bi-check-circle-fill me-2"></i> Konfirmasi Penyelesaian Target
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center py-3">
                        <i class="bi bi-patch-question-fill text-success" style="font-size: 4rem;"></i>
                        <h5 class="mt-3 mb-3">Apakah Anda yakin?</h5>
                        <p class="text-muted mb-2">
                            Anda akan menyelesaikan dan mengarsipkan target produksi:
                        </p>
                        <div class="alert alert-success">
                            <strong id="selesaikan_nama_target"></strong>
                        </div>
                        <p class="text-muted small">
                            <i class="bi bi-info-circle me-1"></i>
                            Target ini akan dipindahkan ke halaman Laporan Selesai dan tidak akan muncul di sini lagi.
                        </p>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0 justify-content-center">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius: 50px; padding: 0.75rem 1.5rem;">
                        <i class="bi bi-x-lg me-1"></i> Batal
                    </button>
                    <a href="#" id="confirmSelesaikanBtn" class="btn btn-success" style="border-radius: 50px; padding: 0.75rem 1.5rem;">
                        <i class="bi bi-check-lg me-1"></i> Ya, Selesaikan Sekarang
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="modal fade" id="nonaktifkanModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="proses_arsip_target.php" method="post">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title"><i class="bi bi-archive-fill me-2"></i> Konfirmasi Nonaktifkan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id_target" id="nonaktifkan_id_target">
                    <input type="hidden" name="id_barang" value="<?php echo $id_barang; ?>">
                    <input type="hidden" name="action" value="nonaktifkan">

                    <p class="text-center">Anda yakin ingin menonaktifkan target:</p>
                    <h5 id="nonaktifkan_nama_target" class="text-center mb-3"></h5>
                    
                    <div class="mb-3">
                        <label for="alasan_nonaktif" class="form-label">
                            <strong>Alasan Nonaktifkan (Wajib diisi)</strong>
                        </label>
                        <textarea class="form-control" id="alasan_nonaktif" name="alasan_nonaktif" rows="3" placeholder="Contoh: Permintaan dibatalkan oleh klien, stok sudah terpenuhi, dll." required></textarea>
                    </div>
                    <p class="text-muted small text-center">Target akan dipindahkan ke arsip dan dapat diaktifkan kembali nanti.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-warning">Ya, Nonaktifkan</button>
                </div>
            </form>
        </div>
    </div>
</div>

    <div class="modal fade" id="prioritasTargetModal" tabindex="-1" aria-labelledby="prioritasTargetModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form action="proses_update_prioritas.php" method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title" id="prioritasTargetModalLabel">Atur Prioritas Target</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="id_target" id="prioritas_id_target">
                        <input type="hidden" name="id_barang" value="<?php echo $id_barang; ?>">
                        <input type="hidden" name="action" id="prioritas_action">

                        <div id="set-priority-view">
                            <p>Pilih tanggal tenggat untuk target prioritas ini.</p>
                            <div class="mb-3">
                                <label for="priority_deadline" class="form-label">Tenggat Waktu</label>
                                <input type="date" class="form-control" id="priority_deadline" name="priority_deadline" required>
                            </div>
                        </div>

                        <div id="unset-priority-view" class="text-center">
                            <i class="bi bi-star-slash-fill text-danger" style="font-size: 3rem;"></i>
                            <h5 class="mt-3">Batalkan Status Prioritas?</h5>
                            <p class="text-muted">Target ini akan kembali menjadi target reguler.</p>
                        </div>

                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary" id="btn-confirm-prioritas">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // --- SEMUA KODE JAVASCRIPT LAMA ANDA TETAP ADA DAN BERFUNGSI ---
        const previewModal = document.getElementById('previewProgressModal');
        if (previewModal) {
            const previewContent = document.getElementById('preview-content');
            const modalTitle = document.getElementById('previewProgressModalLabel');

            previewModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const targetId = button.getAttribute('data-target-id');
                const targetName = button.getAttribute('data-target-name');

                modalTitle.textContent = 'Progress Produksi: ' + targetName;
                previewContent.innerHTML = `<div class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div><p class="mt-2">Memuat data...</p></div>`;

                fetch(`get_progress_preview.php?id_target=${targetId}`)
                    .then(response => response.ok ? response.json() : Promise.reject('Network response was not ok'))
                    .then(data => {
                        if (data.error) {
                            previewContent.innerHTML = `<div class="alert alert-danger">${data.error}</div>`;
                            return;
                        }

                        let html = '';
                        if (Object.keys(data).length === 0) {
                            html = '<div class="alert alert-info">Belum ada data progres untuk target ini.</div>';
                        } else {
                            for (const alur in data) {
                                html += `<h5 class="mt-4 mb-3 bg-light p-2 rounded">${alur}</h5>`;
                                html += `<div class="table-responsive"><table class="table table-sm table-bordered table-striped">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Nama Komponen</th>
                                            <th class="text-center">Kebutuhan</th>
                                            <th class="text-center">Selesai</th>
                                            <th class="text-center">Sisa</th>
                                            <th style="width: 30%;">Progress</th>
                                        </tr>
                                    </thead>
                                    <tbody>`;
                                
                                data[alur].forEach(komponen => {
                                    const kebutuhan = parseInt(komponen.kebutuhan_total);
                                    const selesai = parseInt(komponen.total_selesai);
                                    const sisa = kebutuhan - selesai;
                                    const percentage = kebutuhan > 0 ? ((selesai / kebutuhan) * 100).toFixed(2) : 0;
                                    const progressBarClass = percentage >= 100 ? 'bg-success' : (percentage > 70 ? 'bg-primary' : 'bg-warning');

                                    html += `<tr>
                                        <td>${komponen.nama_komponen}</td>
                                        <td class="text-center">${kebutuhan.toLocaleString()}</td>
                                        <td class="text-center">${selesai.toLocaleString()}</td>
                                        <td class="text-center">${sisa.toLocaleString()}</td>
                                        <td>
                                            <div class="progress" style="height: 22px; font-size: 0.8rem;">
                                                <div class="progress-bar ${progressBarClass} progress-bar-striped progress-bar-animated" role="progressbar" style="width: ${percentage}%;" aria-valuenow="${percentage}" aria-valuemin="0" aria-valuemax="100"><b>${percentage}%</b></div>
                                            </div>
                                        </td>
                                    </tr>`;
                                });
                                html += `</tbody></table></div>`;
                            }
                        }
                        previewContent.innerHTML = html;
                    })
                    .catch(error => {
                        previewContent.innerHTML = `<div class="alert alert-danger">Terjadi kesalahan saat mengambil data. Silakan coba lagi.</div>`;
                        console.error('Error fetching preview:', error);
                    });
            });
        }

        const selesaikanModal = document.getElementById('selesaikanTargetModal');
        if (selesaikanModal) {
            selesaikanModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const targetId = button.getAttribute('data-target-id');
                const targetName = button.getAttribute('data-target-name');
                const idBarang = '<?php echo $id_barang; ?>';

                const modalTargetName = selesaikanModal.querySelector('#selesaikan_nama_target');
                const confirmButton = selesaikanModal.querySelector('#confirmSelesaikanBtn');
                
                modalTargetName.textContent = targetName;
                const url = `proses_update_status.php?id_target=${targetId}&id_barang=${idBarang}`;
                confirmButton.setAttribute('href', url);
            });
        }
        
        const nonaktifkanModal = document.getElementById('nonaktifkanModal');
        if (nonaktifkanModal) {
            nonaktifkanModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const targetId = button.getAttribute('data-target-id');
                const targetName = button.getAttribute('data-target-name');

                nonaktifkanModal.querySelector('#nonaktifkan_nama_target').textContent = targetName;
                nonaktifkanModal.querySelector('#nonaktifkan_id_target').value = targetId;

                nonaktifkanModal.querySelector('#alasan_nonaktif').value = ''; 
            });
        }

        const downloadModal = document.getElementById('downloadLaporanModal');
        if (downloadModal) {
            const modalContent = document.getElementById('download-laporan-content');
            const modalFooter = document.getElementById('download-laporan-footer');
            
            // [BARU] Dapatkan referensi ke input/button baru
            const hiddenTargetExcel = document.getElementById('download_id_target_excel');
            const hiddenTargetPdf = document.getElementById('download_id_target_pdf');
            const hiddenBulanExcel = document.getElementById('download_bulan_laporan_excel');
            const hiddenBulanPdf = document.getElementById('download_bulan_laporan_pdf');
            const btnExcel = document.getElementById('btn-download-excel');
            const btnPdf = document.getElementById('btn-download-pdf');

            downloadModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const targetId = button.getAttribute('data-target-id'); 
                
                // [DIUBAH] Set ID target untuk KEDUA form
                hiddenTargetExcel.value = targetId;
                hiddenTargetPdf.value = targetId;

                // Reset state
                modalContent.innerHTML = '<div class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>';
                modalFooter.style.display = 'none'; // Sembunyikan footer saat loading
                btnExcel.disabled = true; // Matikan tombol
                btnPdf.disabled = true;  // Matikan tombol
                hiddenBulanExcel.value = ''; // Kosongkan value
                hiddenBulanPdf.value = '';   // Kosongkan value

                fetch(`api_get_months.php?id_target=${targetId}`)
                    .then(response => response.json())
                    .then(data => {
                        modalFooter.style.display = 'flex'; // Tampilkan footer (untuk tombol Tutup/Download)

                        if (data.error) {
                            modalContent.innerHTML = `<div class="alert alert-danger">${data.error}</div>`;
                        } else if (data.months.length === 0) {
                            modalContent.innerHTML = '<div class="alert alert-warning">Belum ada data laporan harian untuk target ini.</div>';
                        } else {
                            let optionsHtml = '<option value="">-- Pilih Bulan --</option>';
                            data.months.forEach(monthData => {
                                optionsHtml += `<option value="${monthData.value}">${monthData.name}</option>`;
                            });
                            
                            // [BARU] Buat fungsi onchange untuk mengupdate form
                            // Kita letakkan di 'onchange' langsung di tag select
                            const onMonthChange = `
                                var month = this.value;
                                var btnExcel = document.getElementById('btn-download-excel');
                                var btnPdf = document.getElementById('btn-download-pdf');
                                // Update value di kedua form tersembunyi
                                document.getElementById('download_bulan_laporan_excel').value = month;
                                document.getElementById('download_bulan_laporan_pdf').value = month;
                                
                                // Aktifkan/Matikan tombol berdasarkan pilihan
                                if (month) {
                                    btnExcel.disabled = false;
                                    btnPdf.disabled = false;
                                } else {
                                    btnExcel.disabled = true;
                                    btnPdf.disabled = true;
                                }
                            `;

                            // [DIUBAH] Tambahkan onchange ke tag select
                            modalContent.innerHTML = `
                                <div class="mb-3">
                                    <label class="form-label">Bulan Produksi</label>
                                    <select class="form-select" onchange="${onMonthChange}" required>
                                        ${optionsHtml}
                                    </select>
                                </div>`;
                        }
                    })
                    .catch(err => {
                        modalFooter.style.display = 'flex';
                        modalContent.innerHTML = `<div class="alert alert-danger">Gagal memuat data bulan.</div>`;
                        console.error("Fetch error:", err);
                    });
            });
        }
        
        // --- PERUBAHAN BARU #4: JavaScript untuk modal prioritas ditambahkan di sini ---
        var prioritasModal = document.getElementById('prioritasTargetModal');
        prioritasModal.addEventListener('show.bs.modal', function(event) {
            var button = event.relatedTarget;
            var idTarget = button.getAttribute('data-id-target');
            var isPriority = button.getAttribute('data-is-priority');
            var deadline = button.getAttribute('data-deadline');

            var modal = this;
            modal.querySelector('#prioritas_id_target').value = idTarget;
            
            var setView = modal.querySelector('#set-priority-view');
            var unsetView = modal.querySelector('#unset-priority-view');
            var deadlineInput = modal.querySelector('#priority_deadline');
            var confirmButton = modal.querySelector('#btn-confirm-prioritas');

            if (isPriority === '1') {
                // Tampilan untuk membatalkan prioritas
                setView.style.display = 'none';
                unsetView.style.display = 'block';
                modal.querySelector('#prioritas_action').value = 'unset_priority';
                confirmButton.textContent = 'Ya, Batalkan Prioritas';
                confirmButton.classList.remove('btn-primary');
                confirmButton.classList.add('btn-danger');
                deadlineInput.removeAttribute('required');
            } else {
                // Tampilan untuk mengatur prioritas
                setView.style.display = 'block';
                unsetView.style.display = 'none';
                modal.querySelector('#prioritas_action').value = 'set_priority';
                confirmButton.textContent = 'Jadikan Prioritas';
                confirmButton.classList.remove('btn-danger');
                confirmButton.classList.add('btn-primary');
                deadlineInput.setAttribute('required', 'required');
                // Set tanggal minimum ke hari ini
                deadlineInput.min = new Date().toISOString().split("T")[0];
                deadlineInput.value = deadline;
            }
        });

        // ==============================================================================
        // JAVASCRIPT UNTUK MODAL TAMBAH TARGET (DENGAN PENAMBAHAN FUNGSI)
        // ==============================================================================
        const selectPrioritas = document.getElementById('selectPrioritas');
        const containerTanggalPrioritas = document.getElementById('containerTanggalPrioritas');
        // KOREKSI: Selector diubah ke 'priority_deadline' agar sesuai dengan HTML
        const inputTanggalPrioritas = containerTanggalPrioritas.querySelector('input[name="priority_deadline"]');

        selectPrioritas.addEventListener('change', function() {
            if (this.value === 'Prioritas') {
                containerTanggalPrioritas.style.display = 'block';
                inputTanggalPrioritas.setAttribute('required', 'required');
                // ========== BARIS BARU DITAMBAHKAN DI SINI ==========
                // Mengatur tanggal minimum ke hari ini untuk mencegah pemilihan tanggal lampau
                inputTanggalPrioritas.min = new Date().toISOString().split("T")[0];
            } else {
                containerTanggalPrioritas.style.display = 'none';
                inputTanggalPrioritas.removeAttribute('required');
                inputTanggalPrioritas.value = ''; // Mengosongkan nilai jika kembali ke Normal
            }
        });

    });

    // JavaScript untuk menampilkan notifikasi dari session
    <?php if (isset($_SESSION['flash_message'])): ?>
    const toastContainer = document.createElement('div');
    toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
    document.body.appendChild(toastContainer);
    
    const toastEl = document.createElement('div');
    toastEl.className = 'toast';
    toastEl.setAttribute('role', 'alert');
    toastEl.innerHTML = `
      <div class="toast-header">
        <strong class="me-auto text-<?php echo $_SESSION['flash_message']['status']; ?>"><?php echo $_SESSION['flash_message']['status'] == 'success' ? 'Berhasil' : 'Error'; ?></strong>
        <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
      </div>
      <div class="toast-body">
        <?php echo addslashes($_SESSION['flash_message']['message']); ?>
      </div>
    `;
    toastContainer.appendChild(toastEl);
    
    const toast = new bootstrap.Toast(toastEl);
    toast.show();
    
    <?php unset($_SESSION['flash_message']); ?>
    <?php endif; ?>
    </script>
</body>
</html>
<?php include '../../../templates/footer.php'; ?>