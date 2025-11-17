<?php
$page_title = 'Detail Laporan per Barang';
include_once '../../../templates/header_admin.php';

// Validasi id_barang dari URL
if (!isset($_GET['id_barang']) || !is_numeric($_GET['id_barang'])) {
    echo "<script>alert('ID Barang tidak valid!'); window.location.href='laporan.php';</script>";
    exit;
}
$id_barang = (int)$_GET['id_barang'];

// Ambil parameter pencarian dan pagination
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Fungsi untuk mendapatkan semua bulan produksi untuk sebuah target
function getAllProductionMonths($pdo, $id_target) {
    $stmt = $pdo->prepare("
        SELECT DISTINCT DATE_FORMAT(lh.tanggal_laporan, '%Y-%m') AS production_month
        FROM laporan_harian lh
        JOIN target_material tm ON lh.id_material = tm.id_material
        WHERE tm.id_target = :id_target
        ORDER BY production_month ASC
    ");
    $stmt->execute([':id_target' => $id_target]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

try {
    // Ambil informasi barang untuk judul
    $stmt_barang = $pdo->prepare("SELECT nama_barang FROM master_barang WHERE id_barang = ?");
    $stmt_barang->execute([$id_barang]);
    $barang = $stmt_barang->fetch(PDO::FETCH_ASSOC);

    if (!$barang) {
        throw new Exception("Barang tidak ditemukan.");
    }

    // Persiapan Query & Parameter
    $base_sql = "FROM production_targets WHERE id_barang = :id_barang AND UPPER(status) = 'SELESAI'";
    $params = [':id_barang' => $id_barang];

    if (!empty($search_query)) {
        $base_sql .= " AND nama_permintaan LIKE :search";
        $params[':search'] = "%" . $search_query . "%";
    }

    // Hitung Total untuk Paginasi
    $count_stmt = $pdo->prepare("SELECT COUNT(*) " . $base_sql);
    $count_stmt->execute($params);
    $total_results = $count_stmt->fetchColumn();
    $total_pages = ceil($total_results / $limit);

    // Query Utama untuk Mengambil Data Target
    $target_stmt = $pdo->prepare("SELECT * " . $base_sql . " ORDER BY tanggal_selesai DESC LIMIT :limit OFFSET :offset");
    $target_stmt->bindParam(':id_barang', $id_barang, PDO::PARAM_INT);
    $target_stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $target_stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    if (!empty($search_query)) {
        $search_param = "%" . $search_query . "%";
        $target_stmt->bindParam(':search', $search_param, PDO::PARAM_STR);
    }
    $target_stmt->execute();
    $targets = $target_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Query untuk Statistik di Kartu Atas
    $stats_stmt = $pdo->prepare("
        SELECT
            COUNT(*) as total_target,
            SUM(jumlah_unit) as total_unit,
            AVG(DATEDIFF(tanggal_selesai, created_at)) as avg_duration
        " . $base_sql
    );
    $stats_stmt->execute($params);
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>

<style>
    /* ============================================ */
    /* ROOT VARIABLES - Matching Header Admin Theme */
    /* ============================================ */
    :root {
        --glass-bg: rgba(255, 255, 255, 0.95);
        --glass-border: rgba(255, 255, 255, 0.18);
        --accent-color: #667eea;
        --accent-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        --success-gradient: linear-gradient(135deg, #10b981 0%, #059669 100%);
        --info-gradient: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);
        --warning-gradient: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        --hover-bg: rgba(255, 255, 255, 0.15);
        --text-primary: #1f2937;
        --text-secondary: #6b7280;
        --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        --card-shadow-hover: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        --transition-smooth: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    /* ============================================ */
    /* SKELETON LOADER STYLES */
    /* ============================================ */
    .skeleton {
        background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
        background-size: 200% 100%;
        animation: shimmer 1.5s infinite;
        border-radius: 12px;
    }

    @keyframes shimmer {
        0% { background-position: 200% 0; }
        100% { background-position: -200% 0; }
    }

    .skeleton-card {
        background: var(--glass-bg);
        backdrop-filter: blur(20px);
        border: 1px solid var(--glass-border);
        border-radius: 24px;
        padding: 1.5rem;
        box-shadow: var(--card-shadow);
    }

    .skeleton-stat {
        height: 80px;
        margin-bottom: 1rem;
    }

    .skeleton-table {
        height: 400px;
        margin-top: 1rem;
    }

    .skeleton-text {
        height: 20px;
        margin-bottom: 0.5rem;
    }

    .skeleton-title {
        height: 40px;
        width: 60%;
        margin-bottom: 1rem;
    }

    /* ============================================ */
    /* MAIN CONTAINER */
    /* ============================================ */
    .main-container {
        padding: 2rem 1rem;
        max-width: 1400px;
        margin: 0 auto;
        min-height: calc(100vh - 160px);
    }

    /* ============================================ */
    /* BACK BUTTON - Glassmorphism Style */
    /* ============================================ */
    .back-button {
        background: rgba(255, 255, 255, 0.25);
        color: white;
        border: 1px solid rgba(255, 255, 255, 0.3);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border-radius: 50px;
        padding: 0.75rem 1.5rem;
        transition: var(--transition-smooth);
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 1.5rem;
        font-weight: 600;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        will-change: transform;
    }

    .back-button:hover {
        background: rgba(255, 255, 255, 0.35);
        color: white;
        transform: translateX(-5px);
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
    }

    .back-button i {
        transition: transform 0.3s ease;
    }

    .back-button:hover i {
        transform: translateX(-3px);
    }

    /* ============================================ */
    /* PAGE HEADER - Glassmorphism Card */
    /* ============================================ */
    .page-header {
        background: var(--glass-bg);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid var(--glass-border);
        border-radius: 24px;
        padding: 2rem;
        margin-bottom: 2rem;
        box-shadow: var(--card-shadow);
        transition: var(--transition-smooth);
    }

    .page-header:hover {
        box-shadow: var(--card-shadow-hover);
    }

    .page-title {
        font-size: 2rem;
        font-weight: 700;
        color: var(--text-primary);
        margin-bottom: 0.5rem;
        display: flex;
        align-items: center;
        gap: 1rem;
        flex-wrap: wrap;
    }

    .title-icon {
        background: var(--info-gradient);
        color: white;
        width: 60px;
        height: 60px;
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        box-shadow: 0 8px 20px rgba(6, 182, 212, 0.3);
        transition: var(--transition-smooth);
    }

    .page-header:hover .title-icon {
        transform: scale(1.05) rotate(5deg);
    }

    /* ============================================ */
    /* SEARCH CONTAINER - Modern Design */
    /* ============================================ */
    .search-container {
        background: white;
        border-radius: 20px;
        padding: 0.5rem;
        box-shadow: var(--card-shadow);
        border: 1px solid rgba(0, 0, 0, 0.05);
        max-width: 450px;
        transition: var(--transition-smooth);
    }

    .search-container:focus-within {
        box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        border-color: var(--accent-color);
    }

    .search-input {
        border: none;
        border-radius: 16px;
        padding: 0.75rem 1rem;
        background: #f8fafc;
        transition: var(--transition-smooth);
        font-size: 0.95rem;
    }

    .search-input:focus {
        background: white;
        box-shadow: none;
        outline: none;
    }

    .search-input::placeholder {
        color: #9ca3af;
    }

    .search-btn {
        background: var(--accent-gradient);
        border: none;
        border-radius: 16px;
        padding: 0.75rem 1.25rem;
        color: white;
        font-weight: 600;
        transition: var(--transition-smooth);
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
    }

    .search-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(102, 126, 234, 0.4);
        color: white;
    }

    /* Realtime search indicator */
    .search-indicator {
        position: absolute;
        right: 80px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--accent-color);
        font-size: 0.875rem;
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    .search-indicator.active {
        opacity: 1;
    }

    /* ============================================ */
    /* STATISTICS CARDS - With Counting Animation */
    /* ============================================ */
    .stats-summary {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .stat-card {
        background: var(--glass-bg);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid var(--glass-border);
        border-radius: 24px;
        padding: 2rem 1.5rem;
        text-align: center;
        transition: var(--transition-smooth);
        box-shadow: var(--card-shadow);
        position: relative;
        overflow: hidden;
        will-change: transform;
    }

    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: var(--accent-gradient);
        transform: scaleX(0);
        transition: transform 0.6s ease;
    }

    .stat-card:hover::before {
        transform: scaleX(1);
    }

    .stat-card:hover {
        transform: translateY(-8px) scale(1.02);
        box-shadow: var(--card-shadow-hover);
    }

    .stat-card:nth-child(1) { animation-delay: 0.1s; }
    .stat-card:nth-child(2) { animation-delay: 0.2s; }
    .stat-card:nth-child(3) { animation-delay: 0.3s; }

    .stat-icon {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        margin-bottom: 1rem;
        transition: var(--transition-smooth);
    }

    .stat-card:hover .stat-icon {
        transform: scale(1.1) rotate(5deg);
    }

    .stat-card:nth-child(1) .stat-icon {
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.2), rgba(5, 150, 105, 0.2));
        color: #059669;
    }

    .stat-card:nth-child(2) .stat-icon {
        background: linear-gradient(135deg, rgba(6, 182, 212, 0.2), rgba(8, 145, 178, 0.2));
        color: #0891b2;
    }

    .stat-card:nth-child(3) .stat-icon {
        background: linear-gradient(135deg, rgba(102, 126, 234, 0.2), rgba(118, 75, 162, 0.2));
        color: #764ba2;
    }

    .stat-number {
        font-size: 2.5rem;
        font-weight: 800;
        margin-bottom: 0.5rem;
        line-height: 1;
        background: var(--accent-gradient);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    .stat-label {
        color: var(--text-secondary);
        font-weight: 600;
        font-size: 0.9rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    /* Mobile Layout for Stats: 1 full width, 2 side by side */
    @media (max-width: 767.98px) {
        .stats-summary {
            grid-template-columns: 1fr;
            gap: 1rem;
        }
        
        .stat-card:first-child {
            grid-column: 1;
        }
        
        .stats-summary {
            display: grid;
            grid-template-columns: 1fr 1fr;
        }
        
        .stat-card:first-child {
            grid-column: 1 / -1;
        }
    }

    /* ============================================ */
    /* VIEW TOGGLE BUTTONS */
    /* ============================================ */
    .view-toggle {
        display: flex;
        background: white;
        border-radius: 16px;
        padding: 0.25rem;
        box-shadow: var(--card-shadow);
        border: 1px solid rgba(0, 0, 0, 0.05);
    }

    .toggle-btn {
        background: transparent;
        border: none;
        border-radius: 12px;
        padding: 0.75rem 1.25rem;
        color: var(--text-secondary);
        font-weight: 600;
        transition: var(--transition-smooth);
        display: flex;
        align-items: center;
        gap: 0.5rem;
        white-space: nowrap;
    }

    .toggle-btn:hover {
        background: rgba(102, 126, 234, 0.1);
        color: var(--accent-color);
    }

    .toggle-btn.active {
        background: var(--accent-gradient);
        color: white;
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
    }

    /* ============================================ */
    /* TABLE CONTAINER - Modern Design */
    /* ============================================ */
    .table-container {
        background: var(--glass-bg);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid var(--glass-border);
        border-radius: 24px;
        box-shadow: var(--card-shadow);
        overflow: hidden;
        transition: var(--transition-smooth);
    }

    .table-container:hover {
        box-shadow: var(--card-shadow-hover);
    }

    .table-header {
        padding: 1.5rem 2rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 1rem;
        border-bottom: 2px solid rgba(0, 0, 0, 0.05);
    }

    .table-title {
        font-size: 1.25rem;
        font-weight: 700;
        margin: 0;
        color: var(--text-primary);
    }

    .table-responsive-modern {
        overflow-x: auto;
        border-radius: 0 0 24px 24px;
        /* Enable hardware acceleration */
        transform: translateZ(0);
        -webkit-overflow-scrolling: touch;
    }

    .modern-table {
        width: 100%;
        margin: 0;
        background: transparent;
    }

    .modern-table thead th {
        background: #f8fafc;
        color: var(--text-secondary);
        font-weight: 700;
        padding: 1rem;
        border-bottom: 2px solid #e5e7eb;
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 0.5px;
        white-space: nowrap;
        position: sticky;
        top: 0;
        z-index: 10;
    }

    .modern-table tbody tr {
        transition: var(--transition-smooth);
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    }

    .modern-table tbody tr:last-child {
        border-bottom: none;
    }

    .modern-table tbody tr:hover {
        background: rgba(102, 126, 234, 0.05);
        transform: scale(1.005);
    }

    .modern-table td {
        padding: 1.25rem 1rem;
        border: none;
        vertical-align: middle;
        font-size: 0.9rem;
    }

    /* ============================================ */
    /* CARD VIEW - Mobile Optimized */
    /* ============================================ */
    .targets-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 1.5rem;
        padding: 1rem;
    }

    .target-card {
        background: var(--glass-bg);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid var(--glass-border);
        border-radius: 20px;
        padding: 1.5rem;
        transition: var(--transition-smooth);
        box-shadow: var(--card-shadow);
        position: relative;
        overflow: hidden;
        will-change: transform;
    }

    .target-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: var(--success-gradient);
        transform: scaleX(0);
        transform-origin: left;
        transition: transform 0.6s ease;
    }

    .target-card:hover::before {
        transform: scaleX(1);
    }

    .target-card:hover {
        transform: translateY(-8px) scale(1.02);
        box-shadow: var(--card-shadow-hover);
    }

    .target-title {
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--text-primary);
        margin-bottom: 1.5rem;
        line-height: 1.4;
    }

    .target-info {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    .info-item {
        text-align: center;
        padding: 1rem;
        background: #f8fafc;
        border-radius: 12px;
        transition: var(--transition-smooth);
    }

    .info-item:hover {
        background: rgba(102, 126, 234, 0.05);
        transform: scale(1.05);
    }

    .info-value {
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--accent-color);
        margin-bottom: 0.25rem;
    }

    .info-label {
        font-size: 0.75rem;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-weight: 600;
    }

    /* ============================================ */
    /* BADGES */
    /* ============================================ */
    .duration-badge {
        background: var(--info-gradient);
        color: white;
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-weight: 600;
        font-size: 0.875rem;
        box-shadow: 0 4px 12px rgba(6, 182, 212, 0.3);
        display: inline-block;
    }

    /* ============================================ */
    /* DOWNLOAD BUTTON */
    /* ============================================ */
    .download-btn {
        background: var(--success-gradient);
        color: white;
        border: none;
        border-radius: 12px;
        padding: 0.75rem 1.25rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        transition: var(--transition-smooth);
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        width: 100%;
        cursor: pointer;
    }
    .detail-btn {
            background: var(--accent-gradient);
            color: white;
            border: none;
            border-radius: 12px;
            padding: 0.75rem 1.25rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
            width: 100%;
            text-decoration: none; /* Untuk <a> tag */
        }
        
        .detail-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(99, 102, 241, 0.4);
            color: white;
        }

        /* Penyesuaian untuk tombol di dalam tabel */
        .btn-primary.btn-sm {
            padding: 0.4rem 0.8rem;
            font-size: 0.8rem;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
        }

    .download-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(16, 185, 129, 0.4);
        color: white;
    }

    .download-btn:active {
        transform: translateY(0);
    }

    .download-btn:disabled {
        background: linear-gradient(135deg, #9ca3af, #6b7280);
        cursor: not-allowed;
        transform: none;
        box-shadow: none;
        opacity: 0.6;
    }

    .download-btn i {
        font-size: 1.1rem;
        transition: transform 0.3s ease;
    }

    .download-btn:hover:not(:disabled) i {
        transform: translateY(-2px);
    }

    /* ============================================ */
    /* EMPTY STATE */
    /* ============================================ */
    .empty-state {
        text-align: center;
        padding: 4rem 2rem;
        color: var(--text-secondary);
    }

    .empty-state-icon {
        font-size: 5rem;
        color: #d1d5db;
        margin-bottom: 2rem;
        animation: float 3s ease-in-out infinite;
    }

    @keyframes float {
        0%, 100% { transform: translateY(0px); }
        50% { transform: translateY(-20px); }
    }

    .empty-state h4 {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--text-primary);
        margin-bottom: 1rem;
    }

    .empty-state p {
        font-size: 1rem;
        color: var(--text-secondary);
    }

    /* ============================================ */
    /* PAGINATION - Modern Design */
    /* ============================================ */
    .pagination-container {
        display: flex;
        justify-content: center;
        margin-top: 2rem;
        padding: 2rem 0;
    }

    .pagination-modern {
        background: var(--glass-bg);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid var(--glass-border);
        border-radius: 20px;
        padding: 1rem;
        box-shadow: var(--card-shadow);
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
        justify-content: center;
    }

    .pagination-modern .page-item {
        margin: 0;
    }

    .pagination-modern .page-link {
        border: none;
        border-radius: 12px;
        padding: 0.75rem 1rem;
        min-width: 44px;
        color: var(--text-primary);
        background: transparent;
        font-weight: 600;
        transition: var(--transition-smooth);
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .pagination-modern .page-item.active .page-link {
        background: var(--accent-gradient);
        color: white;
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
    }

    .pagination-modern .page-link:hover:not(.disabled) {
        background: rgba(102, 126, 234, 0.1);
        transform: translateY(-2px);
        color: var(--accent-color);
    }

    .pagination-modern .page-item.disabled .page-link {
        color: #9ca3af;
        background-color: transparent;
        pointer-events: none;
        cursor: not-allowed;
        opacity: 0.5;
    }

    /* ============================================ */
    /* BACK TO TOP BUTTON */
    /* ============================================ */
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
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        cursor: pointer;
        opacity: 0;
        visibility: hidden;
        transition: var(--transition-smooth);
        z-index: 1000;
        box-shadow: 0 4px 20px rgba(102, 126, 234, 0.4);
    }

    .back-to-top.show {
        opacity: 1;
        visibility: visible;
    }

    .back-to-top:hover {
        transform: translateY(-5px) scale(1.1);
        box-shadow: 0 6px 25px rgba(102, 126, 234, 0.5);
    }

    .back-to-top:active {
        transform: translateY(-3px) scale(1.05);
    }

    /* ============================================ */
    /* ANIMATIONS */
    /* ============================================ */
    .fade-in {
        animation: fadeInUp 0.6s ease forwards;
        opacity: 0;
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

    /* ============================================ */
    /* RESPONSIVE DESIGN */
    /* ============================================ */
    @media (max-width: 991.98px) {
        .main-container {
            padding: 1rem 0.5rem;
        }

        .page-header {
            padding: 1.5rem;
        }

        .page-title {
            font-size: 1.5rem;
        }

        .title-icon {
            width: 50px;
            height: 50px;
            font-size: 1.25rem;
        }

        .search-container {
            max-width: 100%;
        }

        .table-header {
            padding: 1rem;
        }

        .targets-grid {
            grid-template-columns: 1fr;
            gap: 1rem;
            padding: 0.5rem;
        }

        .view-toggle {
            width: 100%;
            justify-content: stretch;
        }

        .toggle-btn {
            flex: 1;
            justify-content: center;
        }

        .back-to-top {
            bottom: 20px;
            right: 20px;
            width: 45px;
            height: 45px;
        }
    }

    @media (max-width: 575.98px) {
        .page-title {
            font-size: 1.25rem;
            flex-direction: column;
            text-align: center;
        }

        .stat-number {
            font-size: 2rem;
        }

        .stat-card {
            padding: 1.5rem 1rem;
        }

        .toggle-btn {
            padding: 0.65rem 1rem;
            font-size: 0.875rem;
        }

        .toggle-btn i {
            font-size: 0.875rem;
        }
    }

    /* ============================================ */
    /* VIEW DISPLAY LOGIC */
    /* ============================================ */
    .table-view, .card-view {
        display: none;
    }

    /* ============================================ */
    /* PERFORMANCE OPTIMIZATION */
    /* ============================================ */
    * {
        /* Enable hardware acceleration for smooth animations */
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
    }

    /* Optimize transitions for GPU */
    .stat-card,
    .target-card,
    .back-button,
    .download-btn,
    .toggle-btn,
    .back-to-top {
        will-change: transform;
        transform: translateZ(0);
    }
</style>

<!-- Skeleton Loader (Hidden after page load) -->
<div id="skeleton-loader" class="container-fluid px-4 mt-4">
    <div class="main-container">
        <!-- Skeleton Back Button -->
        <div class="skeleton" style="width: 250px; height: 45px; border-radius: 50px; margin-bottom: 1.5rem;"></div>

        <!-- Skeleton Header -->
        <div class="skeleton-card">
            <div class="skeleton skeleton-title"></div>
            <div class="skeleton skeleton-text" style="width: 80%;"></div>
        </div>

        <!-- Skeleton Stats -->
        <div class="stats-summary" style="margin-top: 2rem;">
            <div class="skeleton-card">
                <div class="skeleton skeleton-stat"></div>
            </div>
            <div class="skeleton-card">
                <div class="skeleton skeleton-stat"></div>
            </div>
            <div class="skeleton-card">
                <div class="skeleton skeleton-stat"></div>
            </div>
        </div>

        <!-- Skeleton Table -->
        <div class="skeleton-card" style="margin-top: 2rem;">
            <div class="skeleton skeleton-table"></div>
        </div>
    </div>
</div>

<!-- Main Content (Hidden during loading) -->
<div id="main-content" style="display: none;">
    <div class="container-fluid px-4 mt-4">
        <div class="main-container">
            <a href="laporan.php" class="back-button fade-in">
                <i class="bi bi-arrow-left"></i> Kembali ke Daftar Laporan
            </a>

            <div class="page-header fade-in">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
                    <h1 class="page-title">
                        <div class="title-icon"><i class="bi bi-file-earmark-text-fill"></i></div>
                        <span><?php echo $page_title; ?></span>
                    </h1>
                    <div class="view-toggle">
                        <button id="table-toggle-btn" class="toggle-btn">
                            <i class="bi bi-table"></i>
                            <span>Tabel</span>
                        </button>
                        <button id="card-toggle-btn" class="toggle-btn">
                            <i class="bi bi-grid-3x3-gap-fill"></i>
                            <span>Kartu</span>
                        </button>
                    </div>
                </div>
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div>
                        <h2 class="h4 text-muted mb-0">
                            Untuk Barang: <strong class="text-primary"><?php echo htmlspecialchars($barang['nama_barang']); ?></strong>
                        </h2>
                    </div>
                    <form action="" method="GET" class="search-container position-relative">
                        <input type="hidden" name="id_barang" value="<?php echo $id_barang; ?>">
                        <div class="d-flex gap-2">
                            <input 
                                type="text" 
                                class="form-control search-input" 
                                name="search"
                                id="realtime-search"
                                placeholder="🔍 Cari nama target..." 
                                value="<?php echo htmlspecialchars($search_query); ?>"
                                autocomplete="off">
                            <span class="search-indicator" id="search-indicator">
                                <i class="bi bi-arrow-clockwise"></i>
                            </span>
                            <button class="btn search-btn" type="submit">
                                <i class="bi bi-search"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Statistics Cards with Counting Animation -->
            <div class="stats-summary fade-in">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="bi bi-check-circle-fill"></i>
                    </div>
                    <div class="stat-number" data-target="<?php echo $stats['total_target'] ?? 0; ?>">0</div>
                    <div class="stat-label">Total Target Selesai</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="bi bi-box-seam-fill"></i>
                    </div>
                    <div class="stat-number" data-target="<?php echo $stats['total_unit'] ?? 0; ?>">0</div>
                    <div class="stat-label">Total Unit Diproduksi</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="bi bi-calendar-check-fill"></i>
                    </div>
                    <div class="stat-number" data-target="<?php echo round($stats['avg_duration'] ?? 0); ?>">0</div>
                    <div class="stat-label">Rata-rata Hari Pengerjaan</div>
                </div>
            </div>

            <?php if (empty($targets)): ?>
                <!-- Empty State -->
                <div class="table-container fade-in">
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <i class="bi bi-inbox"></i>
                        </div>
                        <h4>
                            <?php if (!empty($search_query)): ?>
                                Target "<?php echo htmlspecialchars($search_query); ?>" Tidak Ditemukan
                            <?php else: ?>
                                Tidak Ada Laporan Selesai
                            <?php endif; ?>
                        </h4>
                        <p>Belum ada target yang diselesaikan untuk barang ini.</p>
                    </div>
                </div>
            <?php else: ?>
                <!-- Table View -->
                <div class="table-container table-view fade-in">
                    <div class="table-header">
                        <h3 class="table-title">Daftar Target Selesai</h3>
                        <div class="text-muted">
                            <i class="bi bi-info-circle me-2"></i>Klik tombol download untuk mendapatkan laporan lengkap.
                        </div>
                    </div>
                    <div class="table-responsive-modern">
                        <table class="modern-table">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Nama Permintaan / Target</th>
                                    <th class="text-center">Unit</th>
                                    <th>Tanggal Dibuat</th>
                                    <th>Tanggal Selesai</th>
                                    <th class="text-center">Durasi</th>
                                    <th class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $no = $offset + 1; foreach ($targets as $target): ?>
                                <tr>
                                    <td><strong><?php echo $no++; ?></strong></td>
                                    <td><strong><?php echo htmlspecialchars($target['nama_permintaan']); ?></strong></td>
                                    <td class="text-center">
                                        <span class="badge bg-primary fs-6 rounded-pill">
                                            <?php echo number_format($target['jumlah_unit']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d M Y', strtotime($target['created_at'])); ?></td>
                                    <td><?php echo date('d M Y', strtotime($target['tanggal_selesai'])); ?></td>
                                    <td class="text-center">
                                        <?php
                                            $tgl_dibuat = new DateTime($target['created_at']);
                                            $tgl_selesai = new DateTime($target['tanggal_selesai']);
                                            $durasi = $tgl_dibuat->diff($tgl_selesai)->days;
                                        ?>
                                        <span class="duration-badge"><?php echo $durasi . ' Hari'; ?></span>
                                    </td>
                                    <td class="text-center">
    <div class="d-flex gap-2 justify-content-center">
    
        <a href="rincian_laporan.php?id_target=<?php echo $target['id_target']; ?>" 
           class="btn btn-sm btn-primary" 
           title="Lihat Rincian">
            <i class="bi bi-search"></i>
        </a>

        <button type="button" 
                class="btn btn-sm btn-success download-options-trigger" 
                data-bs-toggle="modal" 
                data-bs-target="#downloadOptionsModal"
                data-id-target="<?php echo $target['id_target']; ?>"
                data-nama-target="<?php echo htmlspecialchars($target['nama_permintaan']); ?>"
                data-months='<?php 
                    $all_months = getAllProductionMonths($pdo, $target['id_target']);
                    echo json_encode($all_months);
                ?>'
                title="Opsi Download">
            <i class="bi bi-download"></i>
        </button>

    </div>
</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Card View -->
                <div class="card-view">
                    <div class="targets-grid fade-in">
                        <?php foreach ($targets as $target): ?>
                        <div class="target-card">
                            <h3 class="target-title"><?php echo htmlspecialchars($target['nama_permintaan']); ?></h3>
                            <div class="target-info">
                                <div class="info-item">
                                    <div class="info-value"><?php echo number_format($target['jumlah_unit']); ?></div>
                                    <div class="info-label">Unit</div>
                                </div>
                                <div class="info-item">
                                    <?php
                                        $tgl_dibuat_card = new DateTime($target['created_at']);
                                        $tgl_selesai_card = new DateTime($target['tanggal_selesai']);
                                        $durasi_card = $tgl_dibuat_card->diff($tgl_selesai_card)->days;
                                    ?>
                                    <div class="info-value"><?php echo $durasi_card; ?></div>
                                    <div class="info-label">Hari Pengerjaan</div>
                                </div>
                                <div class="info-item">
                                    <div class="info-value"><?php echo date('d M Y', strtotime($target['created_at'])); ?></div>
                                    <div class="info-label">Dibuat</div>
                                </div>
                                <div class="info-item">
                                    <div class="info-value"><?php echo date('d M Y', strtotime($target['tanggal_selesai'])); ?></div>
                                    <div class="info-label">Selesai</div>
                                </div>
                            </div>
                            <div class="d-grid gap-2">
                                <a href="rincian_laporan.php?id_target=<?php echo $target['id_target']; ?>" class="detail-btn">
                                    <i class="bi bi-search"></i> Lihat Rincian
                                </a>
                            
                                <button 
                            type="button" 
                            class="btn download-btn download-options-trigger"  data-bs-toggle="modal" 
                            data-bs-target="#downloadOptionsModal"
                            data-id-target="<?php echo $target['id_target']; ?>"
                            data-nama-target="<?php echo htmlspecialchars($target['nama_permintaan']); ?>"
                            data-months='<?php 
                                $all_months = getAllProductionMonths($pdo, $target['id_target']);
                                echo json_encode($all_months);
                            ?>'
                            <?php echo empty($all_months) ? 'disabled' : ''; ?>>
                            <i class="bi bi-download"></i> Opsi Download
                        </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination-container fade-in">
                <nav>
                    <ul class="pagination pagination-modern">
                        <?php
                            $url_params = ['id_barang' => $id_barang];
                            if (!empty($search_query)) $url_params['search'] = $search_query;
                        ?>

                        <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($url_params, ['page' => $page - 1])); ?>">
                                <i class="bi bi-chevron-left"></i>
                            </a>
                        </li>

                        <?php 
                        // Smart pagination: show max 7 pages
                        $start_page = max(1, $page - 3);
                        $end_page = min($total_pages, $page + 3);
                        
                        if ($start_page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($url_params, ['page' => 1])); ?>">1</a>
                            </li>
                            <?php if ($start_page > 2): ?>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                        <li class="page-item <?php if ($i == $page) echo 'active'; ?>">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($url_params, ['page' => $i])); ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                        <?php endfor; ?>

                        <?php if ($end_page < $total_pages): ?>
                            <?php if ($end_page < $total_pages - 1): ?>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php endif; ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($url_params, ['page' => $total_pages])); ?>">
                                    <?php echo $total_pages; ?>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($url_params, ['page' => $page + 1])); ?>">
                                <i class="bi bi-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Back to Top Button -->
<button class="back-to-top" id="backToTop" aria-label="Kembali ke atas">
    <i class="bi bi-arrow-up"></i>
</button>

<!-- Hidden form for download -->
<form id="download-form" action="proses_download_laporan.php" method="POST" target="_blank" style="display: none;">
    <input type="hidden" name="id_target" id="download-target-id">
    <div id="download-months-container"></div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // ============================================
    // SKELETON LOADER - Show content after delay
    // ============================================
    setTimeout(function() {
        document.getElementById('skeleton-loader').style.display = 'none';
        document.getElementById('main-content').style.display = 'block';
        
        // Trigger animations after content is visible
        initAnimations();
    }, 800);

    // ============================================
    // LOGIKA UNTUK MODAL OPSI DOWNLOAD (BARU)
    // ============================================
    const downloadOptionsModal = document.getElementById('downloadOptionsModal');
    if (downloadOptionsModal) {
        
        // Tangkap event TEPAT SEBELUM modal ditampilkan
        downloadOptionsModal.addEventListener('show.bs.modal', function (event) {
            
            // Dapatkan tombol yang di-klik
            const button = event.relatedTarget;
            
            // Ambil data dari atribut data-*
            const idTarget = button.getAttribute('data-id-target');
            const namaTarget = button.getAttribute('data-nama-target');
            const monthsJson = button.getAttribute('data-months');
            const months = JSON.parse(monthsJson || '[]');

            // 1. Set nama target di modal body
            const modalNamaTarget = downloadOptionsModal.querySelector('#modal-nama-target');
            modalNamaTarget.textContent = namaTarget;

            // 2. Set ID Target untuk form PDF
            const pdfInput = downloadOptionsModal.querySelector('#modal-pdf-id-target');
            pdfInput.value = idTarget;

            // 3. Set ID Target untuk form Excel/Lengkap
            const excelInput = downloadOptionsModal.querySelector('#modal-excel-id-target');
            excelInput.value = idTarget;

            // 4. Buat hidden input untuk bulan (form Excel/Lengkap)
            const monthsContainer = downloadOptionsModal.querySelector('#modal-excel-months-container');
            monthsContainer.innerHTML = ''; // Kosongkan dulu
            
            months.forEach(month => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'bulan_laporan[]';
                input.value = month;
                monthsContainer.appendChild(input);
            });

            // 5. (Opsional) Nonaktifkan tombol Excel jika tidak ada bulan
            const excelButton = downloadOptionsModal.querySelector('#modal-excel-button');
            if (months.length === 0) {
                excelButton.setAttribute('disabled', 'true');
                excelButton.title = "Tidak ada data bulanan untuk didownload";
            } else {
                excelButton.removeAttribute('disabled');
                excelButton.title = "Download Laporan Lengkap Bulanan";
            }
        });
    }


    // ============================================
    // COUNTING ANIMATION FOR STATISTICS
    // ============================================
    function initAnimations() {
        const statNumbers = document.querySelectorAll('.stat-number');
        
        statNumbers.forEach((stat, index) => {
            const target = parseInt(stat.getAttribute('data-target'));
            const duration = 2000; // 2 seconds
            const increment = target / (duration / 16); // 60fps
            let current = 0;
            
            const timer = setInterval(() => {
                current += increment;
                if (current >= target) {
                    stat.textContent = target.toLocaleString('id-ID');
                    clearInterval(timer);
                } else {
                    stat.textContent = Math.floor(current).toLocaleString('id-ID');
                }
            }, 16);
        });

        // Fade-in animations with stagger
        const fadeElements = document.querySelectorAll('.fade-in');
        fadeElements.forEach((el, index) => {
            el.style.animationDelay = `${index * 0.1}s`;
        });
    }

    // ============================================
    // VIEW TOGGLE (Table/Card)
    // ============================================
    const tableView = document.querySelector('.table-view');
    const cardView = document.querySelector('.card-view');
    const tableBtn = document.getElementById('table-toggle-btn');
    const cardBtn = document.getElementById('card-toggle-btn');

    function setView(view) {
        if (view === 'table') {
            if(tableView) tableView.style.display = 'block';
            if(cardView) cardView.style.display = 'none';
            if(tableBtn) tableBtn.classList.add('active');
            if(cardBtn) cardBtn.classList.remove('active');
            localStorage.setItem('reportDetailView', 'table');
        } else {
            if(tableView) tableView.style.display = 'none';
            if(cardView) cardView.style.display = 'block';
            if(tableBtn) tableBtn.classList.remove('active');
            if(cardBtn) cardBtn.classList.add('active');
            localStorage.setItem('reportDetailView', 'card');
        }
    }

    if (tableBtn) {
        tableBtn.addEventListener('click', () => setView('table'));
    }
    if (cardBtn) {
        cardBtn.addEventListener('click', () => setView('card'));
    }

    // Set initial view based on screen size or saved preference
    const preferredView = localStorage.getItem('reportDetailView');
    if (preferredView) {
        setView(preferredView);
    } else {
        setView(window.innerWidth < 992 ? 'card' : 'table');
    }

    // ============================================
    // REALTIME SEARCH WITH DEBOUNCING
    // ============================================
    const searchInput = document.getElementById('realtime-search');
    const searchIndicator = document.getElementById('search-indicator');
    let searchTimeout;

    if (searchInput) {
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            
            // Show searching indicator
            searchIndicator.classList.add('active');
            
            // Debounce: wait 500ms after user stops typing
            searchTimeout = setTimeout(() => {
                const searchValue = this.value.trim();
                const currentUrl = new URL(window.location.href);
                
                if (searchValue) {
                    currentUrl.searchParams.set('search', searchValue);
                } else {
                    currentUrl.searchParams.delete('search');
                }
                
                // Reset to page 1 on new search
                currentUrl.searchParams.set('page', '1');
                
                // Navigate to new URL
                window.location.href = currentUrl.toString();
            }, 500);
        });
    }

    // ============================================
    // DOWNLOAD WITH SWEETALERT CONFIRMATION
    // ============================================
    const downloadButtons = document.querySelectorAll('.download-trigger');
    
    downloadButtons.forEach(button => {
        button.addEventListener('click', function() {
            if (this.disabled) return;
            
            const targetId = this.getAttribute('data-target-id');
            const targetName = this.getAttribute('data-target-name');
            const months = JSON.parse(this.getAttribute('data-months') || '[]');
            
            // Show confirmation dialog
            Swal.fire({
                title: 'Download Laporan?',
                html: `Anda akan mendownload laporan untuk:<br><strong>"${targetName}"</strong>`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#10b981',
                cancelButtonColor: '#6b7280',
                confirmButtonText: '<i class="bi bi-download me-2"></i>Ya, Download!',
                cancelButtonText: 'Batal',
                background: 'rgba(30, 60, 114, 0.95)',
                color: '#fff',
                backdrop: `rgba(0,0,0,0.4)`,
                customClass: {
                    popup: 'swal-dark-blue-popup',
                    confirmButton: 'swal-download-btn',
                    cancelButton: 'swal-cancel-btn'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    // Show loading
                    Swal.fire({
                        title: 'Menyiapkan laporan...',
                        html: '<div style="margin-top: 20px;"><i class="bi bi-file-earmark-arrow-down" style="font-size: 3rem; animation: bounce 1s infinite;"></i></div>',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        showConfirmButton: false,
                        background: 'rgba(30, 60, 114, 0.95)',
                        color: '#fff',
                        backdrop: `rgba(0,0,0,0.6)`,
                        didOpen: () => {
                            const style = document.createElement('style');
                            style.innerHTML = `
                                @keyframes bounce {
                                    0%, 100% { transform: translateY(0); }
                                    50% { transform: translateY(-20px); }
                                }
                            `;
                            document.head.appendChild(style);
                        }
                    });
                    
                    // Populate form and submit
                    const form = document.getElementById('download-form');
                    const targetIdInput = document.getElementById('download-target-id');
                    const monthsContainer = document.getElementById('download-months-container');
                    
                    targetIdInput.value = targetId;
                    monthsContainer.innerHTML = '';
                    
                    months.forEach(month => {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'bulan_laporan[]';
                        input.value = month;
                        monthsContainer.appendChild(input);
                    });
                    
                    // Submit form
                    setTimeout(() => {
                        form.submit();
                        
                        // Close loading after short delay
                        setTimeout(() => {
                            Swal.close();
                            
                            // Show success message
                            Swal.fire({
                                title: 'Berhasil!',
                                text: 'Laporan sedang didownload...',
                                icon: 'success',
                                timer: 2000,
                                showConfirmButton: false,
                                background: 'rgba(30, 60, 114, 0.95)',
                                color: '#fff'
                            });
                        }, 1000);
                    }, 800);
                }
            });
        });
    });

    // ============================================
    // BACK TO TOP BUTTON
    // ============================================
    const backToTopBtn = document.getElementById('backToTop');
    
    // Show/hide button based on scroll position
    window.addEventListener('scroll', () => {
        if (window.pageYOffset > 300) {
            backToTopBtn.classList.add('show');
        } else {
            backToTopBtn.classList.remove('show');
        }
    });
    
    // Smooth scroll to top
    backToTopBtn.addEventListener('click', () => {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    });

    // ============================================
    // RESPONSIVE VIEW ADJUSTMENT
    // ============================================
    let resizeTimer;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(() => {
            // Auto-switch view based on screen size if no preference saved
            if (!localStorage.getItem('reportDetailView')) {
                setView(window.innerWidth < 992 ? 'card' : 'table');
            }
        }, 250);
    });

    // ============================================
    // PERFORMANCE: Lazy load images if any
    // ============================================
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src;
                    img.classList.remove('lazy');
                    imageObserver.unobserve(img);
                }
            });
        });

        document.querySelectorAll('img.lazy').forEach(img => {
            imageObserver.observe(img);
        });
    }
});
</script>

<!-- Additional Custom Styles for SweetAlert (Matching Footer Theme) -->
<style>
    /* SweetAlert Dark Blue Theme - Consistent with footer.php */
    .swal-dark-blue-popup {
        border: 1px solid rgba(255, 255, 255, 0.18) !important;
        border-radius: 16px !important;
        box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.5) !important;
    }
    
    .swal-download-btn {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%) !important;
        border: none !important;
        border-radius: 8px !important;
        padding: 10px 24px !important;
        font-weight: 500 !important;
        transition: all 0.3s ease !important;
    }
    
    .swal-download-btn:hover {
        transform: translateY(-2px) !important;
        box-shadow: 0 4px 15px rgba(16, 185, 129, 0.4) !important;
    }
    
    .swal-cancel-btn {
        border-radius: 8px !important;
        padding: 10px 24px !important;
        font-weight: 500 !important;
        transition: all 0.3s ease !important;
    }
    
    .swal-cancel-btn:hover {
        transform: translateY(-2px) !important;
    }
</style>

<div class="modal fade" id="downloadOptionsModal" tabindex="-1" aria-labelledby="downloadOptionsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 16px; background: var(--glass-bg); backdrop-filter: blur(20px);">
            <div class="modal-header" style="border-bottom: 1px solid var(--glass-border);">
                <h5 class="modal-title" id="downloadOptionsModalLabel">Opsi Download</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Pilih format laporan untuk: <br><strong id="modal-nama-target" class="text-primary">...</strong></p>
                
                <div class="d-grid gap-3">
                    <form action="proses_download_laporan_pdf.php" method="POST" target="_blank" class="m-0">
                        <input type="hidden" name="id_target" id="modal-pdf-id-target" value="">
                        <button type="submit" class="btn btn-danger w-100">
                            <i class="bi bi-file-earmark-pdf-fill me-2"></i> Download Laporan Selesai (PDF)
                        </button>
                    </form>

                    <form action="proses_download_laporan.php" method="POST" target="_blank" class="m-0">
                        <input type="hidden" name="id_target" id="modal-excel-id-target" value="">
                        
                        <div id="modal-excel-months-container"></div>
                        
                        <button type="submit" class="btn btn-success w-100 download-btn" id="modal-excel-button">
                            <i class="bi bi-file-earmark-spreadsheet-fill me-2"></i> Download Laporan Lengkap (Bulanan)
                        </button>
                    </form>
                </div>

            </div>
            <div class="modal-footer" style="border-top: 1px solid var(--glass-border);">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<?php include_once '../../../templates/footer.php'; ?>