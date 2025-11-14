<?php
$page_title = 'Detail Barang';
include_once '../../templates/header_admin.php';

// --- AWAL BLOK PERUBAHAN ---
// Logika baru untuk menangani id_barang dan id (sebagai id_target)

$id_barang = null; // Inisialisasi variabel id_barang

// Cek apakah id_barang diberikan langsung
if (isset($_GET['id_barang']) && is_numeric($_GET['id_barang'])) {
    $id_barang = (int)$_GET['id_barang'];
// Jika tidak ada id_barang, cek apakah id (sebagai id_target) diberikan
} elseif (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id_target = (int)$_GET['id'];

    // Cari id_barang berdasarkan id_target
    try {
        $stmt_get_barang_id = $pdo->prepare("SELECT id_barang FROM production_targets WHERE id_target = ?");
        $stmt_get_barang_id->execute([$id_target]);
        $result_barang_id = $stmt_get_barang_id->fetch(PDO::FETCH_ASSOC);

        if ($result_barang_id) {
            $id_barang = (int)$result_barang_id['id_barang'];
        } else {
            // id_target valid tapi tidak ditemukan di production_targets
            echo "<script>window.location.href='master_barang.php?error=target_not_found';</script>";
            exit;
        }
    } catch (PDOException $e) {
         // Redirect dengan pesan error yang lebih umum untuk pengguna
         echo "<script>window.location.href='master_barang.php?error=db_error';</script>";
         exit;
    }
// Jika tidak ada parameter id_barang atau id yang valid
} else {
    // Redirect jika ID tidak valid atau tidak ada
    echo "<script>window.location.href='master_barang.php?error=invalid_id';</script>";
    exit;
}

// Setelah blok di atas, variabel $id_barang seharusnya sudah berisi ID barang yang benar

// Lakukan validasi akhir apakah $id_barang berhasil didapatkan
if ($id_barang === null) {
     // Ini seharusnya tidak terjadi jika logika di atas benar, tapi sebagai pengaman
     echo "<script>window.location.href='master_barang.php?error=unknown_error';</script>";
     exit;
}
// --- AKHIR BLOK PERUBAHAN ---


function calculate_progress($pdo, $id_target) {
    $query_total = "SELECT SUM(tm.jumlah_per_unit * pt.jumlah_unit) AS total_kebutuhan
                          FROM target_material tm
                          JOIN production_targets pt ON tm.id_target = pt.id_target
                          WHERE tm.id_target = ?";
    $stmt_total = $pdo->prepare($query_total);
    $stmt_total->execute([$id_target]);
    $total_kebutuhan = (int)($stmt_total->fetchColumn() ?: 0);

    if ($total_kebutuhan === 0) return 0;

    $query_selesai = "SELECT SUM(lh.jumlah_selesai) AS total_selesai
                                FROM laporan_harian lh
                                JOIN target_material tm ON lh.id_material = tm.id_material
                                WHERE tm.id_target = ?";
    $stmt_selesai = $pdo->prepare($query_selesai);
    $stmt_selesai->execute([$id_target]);
    $total_selesai = (int)($stmt_selesai->fetchColumn() ?: 0);
    
    return round(($total_selesai / $total_kebutuhan) * 100);
}

try {
    $barang_stmt = $pdo->prepare("
        SELECT mb.nama_barang, mb.gambar, mk.nama_kategori
        FROM master_barang mb
        LEFT JOIN master_kategori mk ON mb.id_kategori = mk.id_kategori
        WHERE mb.id_barang = ? AND mb.is_active = 1
    ");
    $barang_stmt->execute([$id_barang]);
    $barang = $barang_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$barang) {
        // --- PERUBAHAN KECIL: Menambahkan parameter error ---
        echo "<script>window.location.href='master_barang.php?error=barang_not_found';</script>"; 
        exit;
    }

    // Ambil informasi mesin dari alur produksi
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
    $mesin_display = !empty($mesin_list_raw)
        ? implode(', ', array_column($mesin_list_raw, 'nama_alur'))
        : null;

    // Pagination
    $data_per_halaman = 8;
    $query_jumlah_data = $pdo->prepare("SELECT COUNT(*) as total FROM production_targets WHERE id_barang = ? AND status = 'ongoing' AND is_active = 1");
    $query_jumlah_data->execute([$id_barang]);
    $result_jumlah = $query_jumlah_data->fetch(PDO::FETCH_ASSOC);
    $total_data = $result_jumlah['total'];
    
    $jumlah_halaman = ceil($total_data / $data_per_halaman);
    // Cek parameter halaman, pastikan ada id_barang atau id di URL
    $halaman_aktif = (isset($_GET['halaman'])) ? (int)$_GET['halaman'] : 1;
    $awal_data = ($halaman_aktif - 1) * $data_per_halaman;

    // --- PERUBAHAN LANGKAH 1 (SELECT) ---
    // Query target dengan pengurutan prioritas
    $target_stmt = $pdo->prepare("
        SELECT id_target, nama_permintaan, jumlah_unit, status, prioritas, is_priority, priority_deadline, created_at, status_pengerjaan /* Tambahkan kolom lain jika perlu */
        FROM production_targets 
        WHERE id_barang = ? AND status = 'ongoing' AND is_active = 1
        
        /* --- PERUBAHAN LANGKAH 2 (ORDER BY) --- */
        ORDER BY 
            /* Prioritaskan berdasarkan is_priority (1 di atas 0) */
            is_priority DESC, 
            /* Jika sama-sama prioritas, urutkan berdasarkan deadline terdekat */
            priority_deadline ASC, 
            /* Jika bukan prioritas atau deadline sama, urutkan berdasarkan tanggal dibuat */
            created_at DESC 
        LIMIT ?, ?
    ");
    $target_stmt->bindValue(1, $id_barang, PDO::PARAM_INT);
    $target_stmt->bindValue(2, $awal_data, PDO::PARAM_INT);
    $target_stmt->bindValue(3, $data_per_halaman, PDO::PARAM_INT);
    $target_stmt->execute();
    $targets = $target_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Terjadi kesalahan saat mengambil data: " . $e->getMessage());
}

$base_url_uploads = $base_url . '/uploads/';
?>

<style>
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
        min-height: 100vh;
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
        margin-bottom: 2rem;
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
        background: var(--light-bg);
        border-radius: 12px;
        border-left: 4px solid var(--primary-color);
    }
    
    .info-icon {
        color: var(--primary-color);
        font-size: 1.5rem;
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
        margin: 0;
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
        margin: 0;
    }
    
    .priority-badge {
        background: linear-gradient(135deg, var(--danger-color), #dc2626);
        color: white;
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.875rem;
        font-weight: 600;
    }
    
    .status-badge {
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
        align-items: center;
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
    
    .btn-download {
        background: linear-gradient(135deg, var(--success-color), #059669);
        color: white;
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
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
        background: var(--light-bg);
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
    
    .pagination {
        gap: 0.5rem;
    }
    
    .page-link {
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        color: var(--primary-color);
        font-weight: 600;
    }
    
    .page-link:hover {
        background: var(--primary-color);
        color: white;
    }
    
    .page-item.active .page-link {
        background: var(--primary-color);
        border-color: var(--primary-color);
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

    /* Smooth Scrolling */
    html {
        scroll-behavior: smooth;
    }

    /* ============================================
        RESPONSIVE DESIGN - MOBILE OPTIMIZATION
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
            grid-template-columns: repeat(3, 1fr);
        }
    }

    /* Mobile Devices (≤ 768px) */
    @media (max-width: 768px) {
        /* ========== GLOBAL MOBILE SETTINGS ========== */
        body {
            font-size: 14px;
        }
        
        .main-container {
            padding: 1rem 0.5rem;
        }
        
        /* ========== BACK BUTTON ========== */
        .back-button {
            width: 100%;
            justify-content: center;
            padding: 0.65rem 1.25rem;
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }
        
        .back-button i {
            font-size: 1rem;
        }
        
        /* ========== MODERN CARD ========== */
        .modern-card {
            border-radius: 15px;
            margin-bottom: 1rem;
        }
        
        .modern-card:hover {
            transform: none;
        }
        
        /* ========== PRODUCT DETAIL CARD ========== */
        .product-detail-card {
            padding: 1.5rem 1rem;
        }
        
        .product-detail-card .row {
            flex-direction: column;
        }
        
        .product-image {
            max-height: 220px;
            margin-bottom: 1.5rem;
            border-radius: 12px;
        }
        
        .product-image:hover {
            transform: none;
        }
        
        .product-title {
            font-size: 1.6rem;
            margin-bottom: 1rem;
            text-align: center;
            line-height: 1.3;
        }
        
        .product-info {
            gap: 0.75rem;
        }
        
        .info-item {
            padding: 0.75rem;
            font-size: 0.85rem;
        }
        
        .info-icon {
            font-size: 1.2rem;
        }
        
        /* ========== TARGETS SECTION ========== */
        .targets-section {
            padding: 1.5rem 1rem;
        }
        
        .section-header {
            flex-direction: column;
            align-items: stretch;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .section-title {
            font-size: 1.3rem;
            justify-content: center;
            text-align: center;
        }
        
        .section-title i {
            font-size: 1.3rem;
        }
        
        /* Button "Lihat Laporan Selesai" */
        .section-header .btn-outline-secondary {
            width: 100%;
            justify-content: center;
            padding: 0.75rem 1.25rem;
            font-size: 0.9rem;
        }
        
        /* ========== TARGET CARDS ========== */
        .target-card {
            border-radius: 12px;
            margin-bottom: 1.25rem;
        }
        
        .target-card.priority {
            border-width: 3px;
            animation: pulse-priority 2s ease-in-out infinite;
        }
        
        @keyframes pulse-priority {
            0%, 100% {
                box-shadow: 0 4px 20px rgba(239, 68, 68, 0.2);
            }
            50% {
                box-shadow: 0 4px 25px rgba(239, 68, 68, 0.4);
            }
        }
        
        /* ========== TARGET HEADER ========== */
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
            word-break: break-word;
        }
        
        /* Badge Container */
        .target-header .d-flex.gap-2 {
            flex-direction: row;
            flex-wrap: wrap;
            align-items: center !important;
            width: 100%;
            gap: 0.5rem !important;
        }
        
        /* Status Badge */
        .status-badge {
            padding: 0.4rem 0.85rem;
            font-size: 0.75rem;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        /* Priority Badge */
        .priority-badge {
            padding: 0.4rem 0.85rem;
            font-size: 0.75rem;
            display: inline-flex;
            align-items: center;
        }
        
        /* Deadline Text */
        .target-header small {
            font-size: 0.75rem;
            display: block;
            width: 100%;
            margin-top: 0.25rem;
            padding: 0.25rem 0.5rem;
            background: rgba(239, 68, 68, 0.1);
            border-radius: 6px;
            font-weight: 600;
            color: var(--danger-color);
        }
        
        /* ========== TARGET BODY ========== */
        .target-body {
            padding: 1rem;
        }
        
        /* Target Info Grid - OPTION A: 3 kolom compact */
        .target-info {
            grid-template-columns: repeat(3, 1fr);
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .info-card {
            padding: 0.75rem 0.5rem;
        }
        
        .info-value {
            font-size: 1.2rem;
            word-break: break-word;
        }
        
        .info-label {
            font-size: 0.7rem;
            margin-top: 0.25rem;
        }
        
        /* ========== PROGRESS BAR (PROMINENT) ========== */
        .target-body > .mb-3 {
            margin-bottom: 1rem !important;
        }
        
        .target-body .form-label {
            font-size: 0.85rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .target-body .progress {
            height: 28px !important;
            border-radius: 14px;
            box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.1);
        }
        
        .target-body .progress-bar {
            font-size: 0.85rem !important;
        }
        
        .target-body .progress-bar strong {
            font-size: 0.85rem;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
        }
        
        /* ========== ACTION BUTTONS - PRIORITY ORDER ========== */
        .action-buttons {
            flex-direction: column;
            gap: 0.5rem;
            margin-top: 1rem;
        }
        
        /* All buttons full width */
        .action-buttons .btn-modern {
            width: 100%;
            justify-content: center;
            padding: 0.8rem 1rem;
            font-size: 0.9rem;
            font-weight: 600;
        }
        
        .action-buttons .btn-modern i {
            font-size: 1.1rem;
        }
        
        /* PRIMARY ACTION: Input Progres (Most Important) */
        .action-buttons .btn-flow {
            order: -3;
            padding: 0.9rem 1rem;
            font-size: 0.95rem;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.4);
        }
        
        /* SECONDARY ACTIONS */
        .action-buttons .btn-download {
            order: -2;
        }
        
        .action-buttons .btn-preview {
            order: -1;
        }
        
        /* TERTIARY: Dropdown (More Options) */
        .action-buttons .dropdown {
            width: 100%;
            order: 1;
        }
        
        .action-buttons .dropdown-toggle-modern {
            width: 100%;
            padding: 0.75rem 1rem;
            text-align: center;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        
        .action-buttons .dropdown-toggle-modern::after {
            margin-left: 0.5rem;
        }
        
        .action-buttons .dropdown-toggle-modern i {
            font-size: 1rem;
        }
        
        /* Remove hover transform on mobile */
        .action-buttons .btn-modern:hover {
            transform: none;
        }
        
        /* ========== DROPDOWN MENU ========== */
        .dropdown-menu {
            font-size: 0.85rem;
            min-width: 100%;
            border-radius: 12px;
        }
        
        .dropdown-item {
            padding: 0.75rem 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .dropdown-item i {
            font-size: 1.1rem;
        }
        
        /* ========== EMPTY STATE ========== */
        .empty-state {
            padding: 2rem 1rem;
            border-radius: 12px;
        }
        
        .empty-state-icon {
            font-size: 3rem;
        }
        
        .empty-state h4 {
            font-size: 1.15rem;
            margin-bottom: 0.75rem;
        }
        
        .empty-state p {
            font-size: 0.85rem;
            margin: 0;
        }
        
        /* ========== PAGINATION - COMPACT MOBILE ========== */
        .pagination {
            flex-wrap: wrap;
            gap: 0.25rem;
            justify-content: center;
        }
        
        .page-link {
            padding: 0.5rem 0.75rem;
            font-size: 0.85rem;
            min-width: 40px;
            text-align: center;
        }
        
        /* Hide middle pages - show max 5 pages */
        .pagination .page-item:not(.active):not(:first-child):not(:last-child):not(:nth-child(2)):not(:nth-last-child(2)) {
            display: none;
        }
        
        /* ========== MODALS ========== */
        .modal-dialog {
            margin: 0.5rem;
        }
        
        .modal-dialog-scrollable {
            max-height: calc(100vh - 1rem);
        }
        
        .modal-content {
            border-radius: 15px;
        }
        
        /* Modal Header */
        .modal-header {
            padding: 1rem;
            border-top-left-radius: 15px;
            border-top-right-radius: 15px;
        }
        
        .modal-title {
            font-size: 1rem;
            line-height: 1.4;
        }
        
        .modal-title i {
            font-size: 1rem;
        }
        
        /* Modal Body */
        .modal-body {
            padding: 1rem;
        }
        
        .modal-body .form-label {
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .modal-body .form-control,
        .modal-body .form-select {
            font-size: 16px; /* Prevent iOS zoom */
            padding: 0.75rem;
        }
        
        .modal-body .alert {
            font-size: 0.85rem;
            padding: 0.75rem;
        }
        
        /* Modal Footer */
        .modal-footer {
            padding: 1rem;
            flex-direction: column;
            gap: 0.5rem;
            border-bottom-left-radius: 15px;
            border-bottom-right-radius: 15px;
        }
        
        .modal-footer .btn {
            width: 100%;
            margin: 0 !important;
            padding: 0.75rem;
        }
        
        /* ========== MODAL XL (Preview Progress) ========== */
        .modal-xl {
            max-width: 95%;
            margin: 0.5rem auto;
        }
        
        .modal-xl .modal-body {
            padding: 0.75rem;
        }
        
        /* Table in Modal - HORIZONTAL SCROLL */
        .modal-xl .table-responsive {
            font-size: 0.75rem;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        .modal-xl .table {
            margin-bottom: 0;
            min-width: 600px; /* Force horizontal scroll */
        }
        
        /* STICKY COLUMN HEADERS */
        .modal-xl .table thead th {
            position: sticky;
            top: 0;
            background: var(--dark-color);
            z-index: 10;
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 0.5rem 0.35rem;
        }
        
        .modal-xl .table tbody td {
            padding: 0.5rem 0.35rem;
            font-size: 0.7rem;
        }
        
        .modal-xl .progress {
            height: 20px !important;
        }
        
        .modal-xl .progress-bar {
            font-size: 0.65rem !important;
        }
        
        /* Modal heading for each alur */
        .modal-xl h5 {
            font-size: 0.95rem;
            margin-top: 1rem !important;
            margin-bottom: 0.75rem !important;
            padding: 0.5rem !important;
            position: sticky;
            top: 0;
            background: var(--light-bg);
            z-index: 10;
        }
        
        /* ========== BACK TO TOP BUTTON - MOBILE ========== */
        .back-to-top {
            width: 45px;
            height: 45px;
            bottom: 1.5rem;
            right: 1.5rem;
            font-size: 1.3rem;
        }
    }

    /* Extra Small Devices (≤ 375px) */
    @media (max-width: 375px) {
        .main-container {
            padding: 0.75rem 0.25rem;
        }
        
        .product-title {
            font-size: 1.35rem;
        }
        
        .section-title {
            font-size: 1.15rem;
        }
        
        .target-title {
            font-size: 1rem;
        }
        
        /* Stack info cards 2x2 with Progress full-width */
        .target-info {
            grid-template-columns: 1fr 1fr;
        }
        
        .target-info .info-card:nth-child(3) {
            grid-column: 1 / -1;
        }
        
        .info-value {
            font-size: 1.1rem;
        }
        
        .info-label {
            font-size: 0.65rem;
        }
        
        .btn-modern {
            padding: 0.7rem 0.85rem;
            font-size: 0.85rem;
        }
        
        .action-buttons .btn-modern i {
            font-size: 1rem;
        }
        
        /* Progress bar slightly smaller */
        .target-body .progress {
            height: 26px !important;
        }
        
        .target-body .progress-bar strong {
            font-size: 0.75rem;
        }
        
        /* Modal adjustments */
        .modal-xl .table th,
        .modal-xl .table td {
            padding: 0.4rem 0.25rem;
            font-size: 0.65rem;
        }
        
        .back-to-top {
            width: 40px;
            height: 40px;
            bottom: 1rem;
            right: 1rem;
            font-size: 1.2rem;
        }
    }

    /* Landscape Mode for Mobile */
    @media (max-width: 768px) and (orientation: landscape) {
        /* Restore some desktop layout in landscape */
        .product-detail-card .row {
            flex-direction: row;
        }
        
        .product-image {
            max-height: 200px;
        }
        
        .target-info {
            grid-template-columns: repeat(3, 1fr);
        }
        
        .section-header {
            flex-direction: row;
            justify-content: space-between;
            align-items: center;
        }
        
        .section-header .btn-outline-secondary {
            width: auto;
        }
        
        /* Keep buttons stacked for better touch targets */
        .action-buttons {
            flex-direction: column;
        }
    }

    /* ============================================
        TOUCH & ACCESSIBILITY ENHANCEMENTS
        ============================================ */
    @media (max-width: 768px) {
        /* Minimum touch target size (Apple HIG & Material Design) */
        .btn, 
        .dropdown-toggle, 
        a.btn,
        .page-link {
            min-height: 44px;
            min-width: 44px;
        }
        
        /* Better form controls */
        .form-control, 
        .form-select {
            font-size: 16px; /* Prevents zoom on iOS */
            padding: 0.75rem;
            border-radius: 8px;
        }
        
        /* Improved readability */
        p, 
        .text-muted,
        .info-label {
            line-height: 1.6;
        }
        
        /* Focus states more visible */
        .btn:focus,
        .form-control:focus,
        .form-select:focus,
        .dropdown-toggle:focus {
            outline: 3px solid var(--primary-color);
            outline-offset: 2px;
        }
        
        /* Better contrast */
        .text-muted {
            color: #495057 !important;
        }
        
        /* Loading states */
        .spinner-border {
            width: 2rem;
            height: 2rem;
        }
    }

    /* ============================================
        PERFORMANCE OPTIMIZATION FOR MOBILE
        ============================================ */
    @media (max-width: 768px) {
        /* Reduce animations for better performance */
        .target-card,
        .btn,
        .dropdown-menu {
            transition: box-shadow 0.2s ease, opacity 0.2s ease;
        }
        
        /* Optimize backdrop blur on mobile */
        .modern-card {
            backdrop-filter: blur(5px);
        }
    }

    /* ============================================
        REDUCED MOTION SUPPORT (Accessibility)
        ============================================ */
    @media (prefers-reduced-motion: reduce) {
        * {
            animation-duration: 0.01ms !important;
            animation-iteration-count: 1 !important;
            transition-duration: 0.01ms !important;
        }
        
        html {
            scroll-behavior: auto;
        }
    }

    /* ============================================
        PRINT STYLES (Bonus Feature)
        ============================================ */
    @media print {
        .back-button,
        .action-buttons,
        .pagination,
        .modal,
        .dropdown,
        .back-to-top {
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
        
        .target-card {
            page-break-inside: avoid;
        }
    }
</style>

<div class="container-fluid main-container">
    <div class="mb-4">
        <a href="master_barang.php" class="back-button">
            <i class="bi bi-arrow-left"></i>
            Kembali ke Master Barang
        </a>
    </div>

    <div class="modern-card product-detail-card">
        <div class="row align-items-center">
            <div class="col-lg-4 col-md-5 text-center mb-4 mb-md-0">
                <img src="<?php echo $base_url_uploads . htmlspecialchars(!empty($barang['gambar']) ? $barang['gambar'] : 'default.png'); ?>" 
                     class="product-image" 
                     alt="Gambar Barang"
                     onerror="this.onerror=null; this.src='<?php echo $base_url_uploads; ?>default.png';">
            </div>
            <div class="col-lg-8 col-md-7">
                <h1 class="product-title"><?php echo htmlspecialchars($barang['nama_barang']); ?></h1>
                <div class="product-info">
                    <div class="info-item">
                        <i class="bi bi-tag-fill info-icon"></i>
                        <div>
                            <strong>Kategori:</strong>
                            <span class="ms-2"><?php echo htmlspecialchars($barang['nama_kategori'] ?? 'Tidak Terdefinisi'); ?></span>
                        </div>
                    </div>
                    <?php if ($mesin_display): ?>
                    <div class="info-item">
                        <i class="bi bi-gear-fill info-icon"></i>
                        <div>
                            <strong>Mesin:</strong>
                            <span class="ms-2 fw-bold"><?php echo htmlspecialchars($mesin_display); ?></span>
                        </div>
                    </div>
                    <?php endif; ?>
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
            <a href="manajemen_laporan/laporan.php" class="btn btn-outline-secondary" style="border-radius: 50px; padding: 0.75rem 1.5rem;">
                <i class="bi bi-archive-fill me-1"></i> Lihat Laporan Selesai
            </a>
        </div>
        
        <?php if (empty($targets)): ?>
            <div class="empty-state">
                <div class="empty-state-icon"><i class="bi bi-inbox"></i></div>
                <h4>Tidak Ada Target Aktif</h4>
                <p>Tidak ada target produksi yang sedang berjalan untuk barang ini.</p>
            </div>
        <?php else: ?>
            <?php foreach ($targets as $target): ?>
                <?php 
                    // --- PERUBAHAN LANGKAH 3 (Bagian 1) ---
                    // Gunakan is_priority (angka 1 atau 0)
                    $card_class = (int)$target['is_priority'] === 1 ? 'priority' : ''; 
                    $progress = calculate_progress($pdo, $target['id_target']);
                ?>
                <div class="target-card <?php echo $card_class; ?>">
                    <div class="target-header">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <h3 class="target-title">
                                <?php echo htmlspecialchars($target['nama_permintaan']); ?>
                            </h3>
                            <div class="d-flex gap-2 align-items-center">
                                <span class="status-badge bg-warning text-dark">
                                    <?php echo ucfirst($target['status']); ?>
                                </span>
                                
                                <?php if((int)$target['is_priority'] === 1): ?> 
                                <span class="priority-badge">
                                    <i class="bi bi-star-fill me-1"></i> Prioritas
                                </span>
                                <?php if(!empty($target['priority_deadline'])): ?>
                                <small class="text-muted fst-italic" style="font-weight: 500;">
                                    Tenggat: <?php echo date('d M Y', strtotime($target['priority_deadline'])); ?>
                                </small>
                                <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="target-body">
                        <div class="target-info">
                            <div class="info-card">
                                <div class="info-value"><?php echo number_format($target['jumlah_unit']); ?></div>
                                <div class="info-label">Jumlah Unit</div>
                            </div>
                            <div class="info-card">
                                <div class="info-value"><?php echo date('d M Y', strtotime($target['created_at'])); ?></div>
                                <div class="info-label">Tanggal Dibuat</div>
                            </div>
                            <div class="info-card">
                                <div class="info-value"><?php echo $progress; ?>%</div>
                                <div class="info-label">Progress</div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Progres Produksi</label>
                            <div class="progress" style="height: 24px;">
                                <div class="progress-bar progress-bar-striped progress-bar-animated <?php echo $progress >= 100 ? 'bg-success' : ($progress > 70 ? 'bg-primary' : 'bg-warning'); ?>" 
                                     role="progressbar" 
                                     style="width: <?php echo $progress; ?>%;" 
                                     aria-valuenow="<?php echo $progress; ?>">
                                    <strong><?php echo $progress; ?>%</strong>
                                </div>
                            </div>
                        </div>

                        <div class="action-buttons mt-4">
                            <a href="alur_produksi.php?id_target=<?php echo $target['id_target']; ?>" class="btn btn-modern btn-flow">
                                <i class="bi bi-pencil-square"></i> Input Progres
                            </a>
                            
                            <button type="button" class="btn btn-modern btn-download" data-bs-toggle="modal" data-bs-target="#downloadLaporanModal" data-target-id="<?php echo $target['id_target']; ?>">
                                <i class="bi bi-download"></i> Download Laporan
                            </button>

                            <button type="button" class="btn btn-modern btn-preview" data-bs-toggle="modal" data-bs-target="#previewProgressModal" data-target-id="<?php echo $target['id_target']; ?>" data-target-name="<?php echo htmlspecialchars($target['nama_permintaan']); ?>">
                                <i class="bi bi-eye-fill"></i> Lihat Progress
                            </button>

                            <div class="dropdown">
                                <button class="btn dropdown-toggle-modern" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Opsi Lainnya">
                                    <i class="bi bi-three-dots-vertical"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <a class="dropdown-item" href="tampilan_progress.php?id_target=<?php echo $target['id_target']; ?>" target="_blank">
                                            <i class="bi bi-tv-fill text-primary me-2"></i> Buka Tampilan TV
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if ($total_data > $data_per_halaman): ?>
                <nav aria-label="Page navigation" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?php echo ($halaman_aktif <= 1) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?id_barang=<?php echo $id_barang; ?>&halaman=<?php echo $halaman_aktif - 1; ?>">Previous</a>
                        </li>
                        <?php for ($i = 1; $i <= $jumlah_halaman; $i++): ?>
                            <li class="page-item <?php echo ($i == $halaman_aktif) ? 'active' : ''; ?>">
                                <a class="page-link" href="?id_barang=<?php echo $id_barang; ?>&halaman=<?php echo $i; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?php echo ($halaman_aktif >= $jumlah_halaman) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?id_barang=<?php echo $id_barang; ?>&halaman=<?php echo $halaman_aktif + 1; ?>">Next</a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <button class="back-to-top" id="backToTopBtn" aria-label="Kembali ke atas">
        <i class="bi bi-arrow-up"></i>
    </button>
</div>

<div class="modal fade" id="downloadLaporanModal" tabindex="-1" aria-labelledby="downloadLaporanModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="manajemen_laporan/download_laporan_ongoing.php" method="post" target="_blank">
                <div class="modal-header">
                    <h5 class="modal-title" id="downloadLaporanModalLabel"><i class="bi bi-download me-2"></i>Pilih Bulan Laporan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id_target" id="download_id_target">
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
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-download"></i> Download
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

<?php include_once '../../templates/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Back to Top Button
    const backToTopBtn = document.getElementById('backToTopBtn');
    
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

    // Modal Download Laporan
    const downloadModal = document.getElementById('downloadLaporanModal');
    if (downloadModal) {
        const modalContent = document.getElementById('download-laporan-content');
        const modalFooter = document.getElementById('download-laporan-footer');
        const hiddenInputTargetId = document.getElementById('download_id_target');

        downloadModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const targetId = button.getAttribute('data-target-id'); 
            
            hiddenInputTargetId.value = targetId;
            modalContent.innerHTML = '<div class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>';
            modalFooter.style.display = 'none';

            fetch(`manajemen_laporan/api_get_months.php?id_target=${targetId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        modalContent.innerHTML = `<div class="alert alert-danger">${data.error}</div>`;
                    } else if (data.months.length === 0) {
                        modalContent.innerHTML = '<div class="alert alert-warning">Belum ada data laporan harian untuk target ini.</div>';
                        modalFooter.style.display = 'flex';
                        modalFooter.querySelector('button[type="submit"]').style.display = 'none';
                    } else {
                        let optionsHtml = '<option value="">-- Pilih Bulan --</option>';
                        data.months.forEach(monthData => {
                            optionsHtml += `<option value="${monthData.value}">${monthData.name}</option>`;
                        });
                        
                        modalContent.innerHTML = `
                            <div class="mb-3">
                                <label class="form-label">Bulan Produksi</label>
                                <select class="form-select" name="bulan_laporan" required>
                                    ${optionsHtml}
                                </select>
                            </div>`;
                        
                        modalFooter.style.display = 'flex';
                        modalFooter.querySelector('button[type="submit"]').style.display = 'inline-block';
                    }
                })
                .catch(err => {
                    modalContent.innerHTML = `<div class="alert alert-danger">Gagal memuat data bulan. Silakan coba lagi.</div>`;
                    console.error("Fetch error:", err);
                });
        });
    }

    // Modal Preview Progress
    const previewModal = document.getElementById('previewProgressModal');
    if (previewModal) {
        const previewContent = document.getElementById('preview-content');
        const modalTitle = document.getElementById('previewProgressModalLabel');

        previewModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const targetId = button.getAttribute('data-target-id');
            const targetName = button.getAttribute('data-target-name');

            modalTitle.innerHTML = '<i class="bi bi-graph-up me-2"></i>Progress Produksi: ' + targetName;
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
});
</script>

</body>
</html>