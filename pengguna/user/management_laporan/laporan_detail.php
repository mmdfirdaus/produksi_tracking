<?php
$page_title = 'Detail Laporan per Barang';
include_once '../../../templates/header_user.php';
include_once '../../../system/database_connection.php'; // Ditambahkan untuk koneksi DB

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
    $stmt_barang = $pdo->prepare("SELECT nama_barang, kode_barang FROM master_barang WHERE id_barang = ?");
    $stmt_barang->execute([$id_barang]);
    $barang = $stmt_barang->fetch(PDO::FETCH_ASSOC);

    if (!$barang) {
        throw new Exception("Barang tidak ditemukan.");
    }

    // Persiapan Query & Parameter
    $base_sql = "FROM production_targets WHERE id_barang = :id_barang AND UPPER(status) = 'SELESAI'";
    $params = [':id_barang' => $id_barang];

    if (!empty($search_query)) {
        $base_sql .= " AND (nama_permintaan LIKE :search OR no_spk LIKE :search)";
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
<head>
    <style>
        :root {
            --primary-color: #6366f1;
            --secondary-color: #8b5cf6;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --info-color: #06b6d4;
            --dark-color: #1f2937;
            --light-bg: #f8fafc;
            --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --card-shadow-hover: 0 10px 25px -3px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
            --glass-bg: rgba(255, 255, 255, 0.95);
            --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        body {
            background: var(--gradient-primary);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            position: relative;
        }
        
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('data:image/svg+xml,%3Csvg width="60" height="60" viewBox="0 0 60 60" xmlns="http://www.w3.org/2000/svg"%3E%3Cg fill="none" fill-rule="evenodd"%3E%3Cg fill="%23ffffff" fill-opacity="0.1"%3E%3Cpath d="M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z"/%3E%3C/g%3E%3C/g%3E%3C/svg%3E');
            opacity: 0.3;
            z-index: -1;
        }
        
        .main-container {
            padding: 2rem 1rem;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .page-header, .stat-card, .table-container, .target-card, .pagination-modern {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 24px;
        }
        
        .page-header {
            padding: 2rem;
            margin-bottom: 2rem;
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
            margin-bottom: 2rem;
        }
        
        .back-button:hover {
            background: rgba(255, 255, 255, 0.3);
            color: white;
            transform: translateX(-5px);
        }
        
        .page-title {
            font-size: 2.25rem;
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .title-icon {
            background: linear-gradient(135deg, var(--info-color), #0891b2);
            color: white;
            width: 60px;
            height: 60px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            box-shadow: 0 8px 20px rgba(6, 182, 212, 0.3);
        }
        
        .search-container {
            background: white;
            border-radius: 20px;
            padding: 1rem;
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(0, 0, 0, 0.05);
            max-width: 400px;
        }
        
        .search-input {
            border: none;
            border-radius: 16px;
            padding: 0.75rem 1rem;
            background: var(--light-bg);
            transition: all 0.3s ease;
        }
        
        .search-input:focus {
            background: white;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
            border: 2px solid var(--primary-color);
        }
        
        .search-btn {
            background: linear-gradient(135deg, var(--primary-color), #4f46e5);
            border: none;
            border-radius: 16px;
            padding: 0.75rem 1rem;
            color: white;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
        }
        
        .search-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(99, 102, 241, 0.4);
        }
        
        .stats-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            padding: 1.5rem;
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--card-shadow-hover);
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: #6b7280;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .table-container {
            overflow: hidden;
        }
        
        .table-header {
            padding: 1.5rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .table-title {
            font-size: 1.25rem;
            font-weight: 700;
            margin: 0;
            color: var(--dark-color);
        }
        
        .modern-table {
            width: 100%;
            margin: 0;
            background: transparent;
        }
        
        .modern-table thead th {
            background: var(--light-bg);
            color: #4b5563;
            font-weight: 700;
            padding: 1rem;
            border-bottom: 2px solid #e5e7eb;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.5px;
        }
        
        .modern-table tbody tr {
            transition: all 0.3s ease;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .modern-table tbody tr:last-child {
            border-bottom: none;
        }
        
        .modern-table tbody tr:hover {
            background: rgba(99, 102, 241, 0.05);
        }
        
        .modern-table td {
            padding: 1.25rem 1rem;
            border: none;
            vertical-align: middle;
        }
        
        .targets-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .target-card {
            padding: 1.5rem;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .target-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--card-shadow-hover);
        }
        
        .target-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--success-color), var(--info-color));
            border-radius: 24px 24px 0 0;
        }
        
        .target-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: 1rem;
        }
        
        .target-info {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .info-item {
            text-align: center;
            padding: 0.75rem;
            background: var(--light-bg);
            border-radius: 12px;
        }
        
        .info-value {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--primary-color);
        }
        
        .info-label {
            font-size: 0.8rem;
            color: #6b7280;
            margin-top: 0.25rem;
        }
        
        .duration-badge {
            background: linear-gradient(135deg, var(--info-color), #0891b2);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.875rem;
            box-shadow: 0 4px 12px rgba(6, 182, 212, 0.3);
        }
        
        .status-badge {
            background: linear-gradient(135deg, var(--success-color), #059669);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .download-btn {
            background: linear-gradient(135deg, var(--success-color), #059669);
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
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
            width: 100%;
        }

        .detail-btn {
            background: linear-gradient(135deg, var(--primary-color), #4f46e5);
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
        }
        
        .download-btn:disabled {
            background: #9ca3af;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #6b7280;
        }
        
        .empty-state-icon {
            font-size: 5rem;
            color: #d1d5db;
            margin-bottom: 2rem;
        }
        
        .empty-state h4 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: 1rem;
        }
        
        .pagination-container {
            display: flex;
            justify-content: center;
            margin-top: 2rem;
            padding: 2rem 0;
        }
        
        .pagination-modern {
            padding: 1rem;
        }
        
        .pagination-modern .page-item {
            margin: 0 0.25rem;
        }
        
        .pagination-modern .page-link {
            border: none;
            border-radius: 12px;
            padding: 0.75rem 1rem;
            color: var(--dark-color);
            background: transparent;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .pagination-modern .page-item.active .page-link {
            background: linear-gradient(135deg, var(--primary-color), #4f46e5);
            color: white;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
        }
        
        .pagination-modern .page-link:hover {
            background: rgba(99, 102, 241, 0.1);
            transform: translateY(-2px);
        }
        
        .pagination-modern .page-item.disabled .page-link {
            color: #9ca3af;
            background-color: transparent;
            pointer-events: none;
            cursor: not-allowed;
        }
        
        .pagination-modern .page-item.disabled .page-link:hover {
            transform: none;
            background: transparent;
        }
        
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
            padding: 0.75rem 1rem;
            color: #6b7280;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .toggle-btn.active {
            background: linear-gradient(135deg, var(--primary-color), #4f46e5);
            color: white;
            box-shadow: 0 2px 8px rgba(99, 102, 241, 0.3);
        }
        
        .table-responsive-modern {
            border-radius: 0 0 24px 24px;
            overflow-x: auto;
            padding: 0.5rem 0;
        }
        
        .table-view, .card-view {
            display: none;
        }
        
        .fade-in {
            animation: fadeInUp 0.6s ease forwards;
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

        /* (Style responsive dari admin/superadmin) */
        @media (min-width: 992px) {
            .table-view.active-view {
                display: block;
            }
            .card-view.active-view {
                display: none;
            }
        }
        
        @media (max-width: 991.98px) {
            .table-view.active-view {
                display: none;
            }
            .card-view.active-view {
                display: block;
            }
        }
        
        @media (max-width: 992px) {
            .main-container {
                padding: 1.5rem 1rem;
            }
            
            .page-title {
                font-size: 2rem;
            }
            
            .title-icon {
                width: 50px;
                height: 50px;
                font-size: 1.25rem;
            }
        }

        @media (max-width: 768px) {
            .main-container {
                padding: 1rem 0.75rem;
            }
            
            .back-button {
                padding: 0.6rem 1.25rem;
                font-size: 0.9rem;
                margin-bottom: 1.5rem;
                border-radius: 40px;
            }
            
            .page-header {
                padding: 1.5rem 1rem;
                margin-bottom: 1.5rem;
                border-radius: 16px;
            }
            
            .page-header .d-flex {
                flex-direction: column !important;
                align-items: flex-start !important;
            }
            
            .page-title {
                font-size: 1.5rem;
                gap: 0.75rem;
                margin-bottom: 1rem;
            }
            
            .title-icon {
                width: 45px;
                height: 45px;
                font-size: 1.1rem;
                border-radius: 12px;
            }
            
            .page-header h2 {
                font-size: 1rem !important;
                margin-bottom: 1rem !important;
            }
            
            .view-toggle {
                width: 100%;
                padding: 0.2rem;
                border-radius: 12px;
                margin-bottom: 1rem;
            }
            
            .toggle-btn {
                flex: 1;
                padding: 0.6rem 0.75rem;
                font-size: 0.85rem;
                border-radius: 10px;
            }
            
            .toggle-btn i {
                font-size: 0.85rem;
            }
            
            .search-container {
                max-width: 100% !important;
                width: 100%;
                padding: 0.75rem;
                border-radius: 12px;
            }
            
            .search-input {
                padding: 0.6rem 0.75rem;
                font-size: 0.9rem;
                border-radius: 12px;
            }
            
            .search-btn {
                padding: 0.6rem 0.75rem;
                font-size: 0.9rem;
                border-radius: 12px;
            }
            
            .stats-summary {
                grid-template-columns: repeat(3, 1fr);
                gap: 0.75rem;
                margin-bottom: 1.5rem;
            }
            
            .stat-card {
                padding: 1rem;
                border-radius: 16px;
            }
            
            .stat-number {
                font-size: 1.75rem;
                margin-bottom: 0.25rem;
            }
            
            .stat-label {
                font-size: 0.75rem;
            }
            
            .table-container {
                border-radius: 16px;
            }
            
            .table-header {
                padding: 1rem;
                flex-direction: column;
                align-items: flex-start !important;
            }
            
            .table-title {
                font-size: 1.1rem;
                margin-bottom: 0.5rem;
            }
            
            .table-header .text-muted {
                font-size: 0.85rem;
            }
            
            .table-responsive-modern {
                border-radius: 0 0 16px 16px;
            }
            
            .modern-table {
                font-size: 0.85rem;
            }
            
            .modern-table thead th {
                padding: 0.75rem 0.5rem;
                font-size: 0.7rem;
            }
            
            .modern-table tbody td {
                padding: 1rem 0.5rem;
                font-size: 0.85rem;
            }
            
            .targets-grid {
                grid-template-columns: repeat(2, 1fr) !important;
                gap: 0.75rem;
                margin-bottom: 1.5rem;
            }
            
            .target-card {
                padding: 1rem;
                border-radius: 16px;
            }
            
            .target-card:hover {
                transform: translateY(-3px);
            }
            
            .target-title {
                font-size: 1rem;
                margin-bottom: 0.75rem;
                line-height: 1.3;
                display: -webkit-box;
                -webkit-line-clamp: 2;
                -webkit-box-orient: vertical;
                overflow: hidden;
            }
            
            .target-info {
                gap: 0.5rem;
                margin-bottom: 1rem;
            }
            
            .info-item {
                padding: 0.5rem;
                border-radius: 10px;
            }
            
            .info-value {
                font-size: 0.95rem;
            }
            
            .info-label {
                font-size: 0.7rem;
            }
            
            .download-btn {
                padding: 0.6rem 1rem;
                font-size: 0.85rem;
                border-radius: 10px;
            }
            
            .download-btn i {
                font-size: 0.85rem;
            }
            
            .duration-badge {
                padding: 0.4rem 0.75rem;
                font-size: 0.75rem;
                border-radius: 12px;
            }
            
            .empty-state {
                padding: 3rem 1.5rem;
                border-radius: 16px;
            }
            
            .empty-state-icon {
                font-size: 3.5rem;
                margin-bottom: 1.5rem;
            }
            
            .empty-state h4 {
                font-size: 1.25rem;
                margin-bottom: 0.75rem;
            }
            
            .empty-state p {
                font-size: 0.95rem;
            }
            
            .pagination-container {
                margin-top: 1.5rem;
                padding: 1.5rem 0;
            }
            
            .pagination-modern {
                padding: 0.75rem;
                border-radius: 16px;
            }
            
            .pagination-modern .page-link {
                padding: 0.5rem 0.75rem;
                font-size: 0.85rem;
                border-radius: 8px;
            }
        }
        
        @media (max-width: 480px) {
            .main-container {
                padding: 0.75rem 0.5rem;
            }
            
            .back-button {
                padding: 0.5rem 1rem;
                font-size: 0.85rem;
            }
            
            .page-header {
                padding: 1.25rem 0.75rem;
            }
            
            .page-title {
                font-size: 1.25rem;
            }
            
            .title-icon {
                width: 40px;
                height: 40px;
                font-size: 1rem;
            }
            
            .page-header h2 {
                font-size: 0.9rem !important;
            }
            
            .stats-summary {
                grid-template-columns: 1fr;
                gap: 0.75rem;
            }
            
            .stat-card {
                padding: 0.75rem;
            }
            
            .stat-number {
                font-size: 1.5rem;
            }
            
            .stat-label {
                font-size: 0.7rem;
            }
            
            .targets-grid {
                grid-template-columns: repeat(2, 1fr) !important;
                gap: 0.5rem;
            }
            
            .target-card {
                padding: 0.75rem;
                border-radius: 12px;
            }
            
            .target-title {
                font-size: 0.9rem;
                margin-bottom: 0.6rem;
            }
            
            .target-info {
                gap: 0.4rem;
                margin-bottom: 0.75rem;
            }
            
            .info-item {
                padding: 0.4rem;
            }
            
            .info-value {
                font-size: 0.85rem;
            }
            
            .info-label {
                font-size: 0.65rem;
            }
            
            .download-btn {
                padding: 0.5rem 0.75rem;
                font-size: 0.8rem;
            }
            
            .toggle-btn {
                padding: 0.5rem 0.5rem;
                font-size: 0.8rem;
            }
            
            .search-input {
                padding: 0.5rem 0.75rem;
                font-size: 0.85rem;
            }
            
            .search-btn {
                padding: 0.5rem 0.75rem;
                font-size: 0.85rem;
            }
            
            .table-title {
                font-size: 1rem;
            }
            
            .modern-table {
                font-size: 0.8rem;
            }
            
            .modern-table thead th {
                padding: 0.6rem 0.4rem;
                font-size: 0.65rem;
            }
            
            .modern-table tbody td {
                padding: 0.75rem 0.4rem;
                font-size: 0.8rem;
            }
            
            .duration-badge {
                padding: 0.3rem 0.6rem;
                font-size: 0.7rem;
            }
            
            .empty-state {
                padding: 2.5rem 1rem;
            }
            
            .empty-state-icon {
                font-size: 3rem;
            }
            
            .empty-state h4 {
                font-size: 1.1rem;
            }
            
            .empty-state p {
                font-size: 0.9rem;
            }
        }
        
        @media (max-width: 360px) {
            .targets-grid {
                grid-template-columns: repeat(2, 1fr) !important;
                gap: 0.4rem;
            }
            
            .target-card {
                padding: 0.6rem;
            }
            
            .target-title {
                font-size: 0.85rem;
            }
            
            .info-value {
                font-size: 0.8rem;
            }
            
            .info-label {
                font-size: 0.6rem;
            }
            
            .download-btn {
                padding: 0.45rem 0.6rem;
                font-size: 0.75rem;
            }
            
            .modern-table {
                font-size: 0.75rem;
            }
        }
    </style>
</head>

<div class="container-fluid px-4 mt-4">
    <div class="main-container">
        <a href="laporan.php" class="back-button fade-in">
            <i class="bi bi-arrow-left"></i> Kembali ke Daftar Laporan
        </a>

        <div class="page-header fade-in">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
                <h1 class="page-title">
                    <div class="title-icon"><i class="bi bi-file-earmark-text-fill"></i></div>
                    <?php echo $page_title; ?>
                </h1>
                <div class="view-toggle">
                    <button id="table-toggle-btn" class="toggle-btn"><i class="bi bi-table me-2"></i>Tabel</button>
                    <button id="card-toggle-btn" class="toggle-btn"><i class="bi bi-grid-3x3-gap-fill me-2"></i>Kartu</button>
                </div>
            </div>
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h2 class="h4 text-muted mb-0">
    Barang: <strong class="text-primary"><?php echo htmlspecialchars($barang['nama_barang']); ?></strong>
    <span class="badge bg-light text-dark border ms-2" style="font-size: 0.9rem; font-weight: normal;">
        <i class="bi bi-upc-scan me-1"></i><?php echo htmlspecialchars($barang['kode_barang']); ?>
    </span>
</h2>
                </div>
                <form action="" method="GET" class="search-container">
                    <input type="hidden" name="id_barang" value="<?php echo $id_barang; ?>">
                    <div class="d-flex gap-2">
                        <input type="text" class="form-control search-input" name="search"
                            placeholder="🔍 Cari nama target atau No. SPK..." value="<?php echo htmlspecialchars($search_query); ?>">
                        <button class="btn search-btn" type="submit"><i class="bi bi-search"></i></button>
                    </div>
                </form>
            </div>
        </div>

        <div class="stats-summary fade-in">
            <div class="stat-card">
                <div class="stat-number text-success"><?php echo $stats['total_target'] ?? 0; ?></div>
                <div class="stat-label">Total Target Selesai</div>
            </div>
            <div class="stat-card">
                <div class="stat-number text-info"><?php echo number_format($stats['total_unit'] ?? 0); ?></div>
                <div class="stat-label">Total Unit Diproduksi</div>
            </div>
            <div class="stat-card">
                <div class="stat-number text-primary"><?php echo round($stats['avg_duration'] ?? 0); ?></div>
                <div class="stat-label">Rata-rata Hari Pengerjaan</div>
            </div>
        </div>

        <?php if (empty($targets)): ?>
            <div class="table-container fade-in">
                <div class="empty-state">
                    <div class="empty-state-icon"><i class="bi bi-inbox"></i></div>
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
            <div class="table-container table-view fade-in">
                <div class="table-header">
                    <h3 class="table-title">Daftar Target Selesai</h3>
                    <div class="text-muted"><i class="bi bi-info-circle me-2"></i>Klik tombol download untuk mendapatkan laporan lengkap.</div>
                </div>
                <div class="table-responsive-modern">
                    <table class="modern-table">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Nama Permintaan / Target</th>
                                <th>No. SPK</th> <th class="text-center">Unit</th>
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
                                <td>
    <span class="badge bg-light text-dark border">
        <i class="bi bi-hash me-1"></i><?php echo htmlspecialchars($target['no_spk'] ?? '-'); ?>
    </span>
</td>
                                <td class="text-center"><span class="badge bg-primary fs-6 rounded-pill"><?php echo number_format($target['jumlah_unit']); ?></span></td>
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

            <div class="card-view">
                <div class="targets-grid fade-in">
                    <?php foreach ($targets as $target): ?>
                    <div class="target-card">
                        <h3 class="target-title"><?php echo htmlspecialchars($target['nama_permintaan']); ?></h3>
                        <div class="mb-3">
    <small class="text-muted">
        <i class="bi bi-upc-scan me-1"></i>No. SPK: <strong><?php echo htmlspecialchars($target['no_spk'] ?? '-'); ?></strong>
    </small>
</div>
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
                                
                                <button type="button" 
                                        class="download-btn download-options-trigger" 
                                        data-bs-toggle="modal" 
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

                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?php if ($i == $page) echo 'active'; ?>">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($url_params, ['page' => $i])); ?>"><?php echo $i; ?></a>
                    </li>
                    <?php endfor; ?>
                    
                    <li class_item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
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

<script>
document.addEventListener('DOMContentLoaded', function() {
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

    // ============================================
    // LOGIKA UNTUK MODAL OPSI DOWNLOAD (BARU)
    // ============================================
    const downloadOptionsModal = document.getElementById('downloadOptionsModal');
    if (downloadOptionsModal) {
        
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

            // 3. Set ID Target untuk form Excel
            const excelInput = downloadOptionsModal.querySelector('#modal-excel-id-target');
            excelInput.value = idTarget;

            // 4. Buat hidden input untuk bulan (form Excel)
            const monthsContainer = downloadOptionsModal.querySelector('#modal-excel-months-container');
            monthsContainer.innerHTML = ''; // Kosongkan dulu
            
            months.forEach(month => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'bulan_laporan[]';
                input.value = month;
                monthsContainer.appendChild(input);
            });

            // 5. Nonaktifkan tombol Excel jika tidak ada bulan
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

    if (tableBtn) {
        tableBtn.addEventListener('click', () => setView('table'));
    }
    if (cardBtn) {
        cardBtn.addEventListener('click', () => setView('card'));
    }

    // Cek preferensi dari local storage, atau default berdasarkan ukuran layar
    const preferredView = localStorage.getItem('reportDetailView');
    if (preferredView) {
        setView(preferredView);
    } else {
        if (window.innerWidth < 992) {
            setView('card');
        } else {
            setView('table');
        }
    }

    // Animasi fade-in
    const elements = document.querySelectorAll('.fade-in');
    elements.forEach((el, index) => {
        el.style.animationDelay = `${index * 0.05}s`;
    });
});
</script>

<div class="modal fade" id="downloadOptionsModal" tabindex="-1" aria-labelledby="downloadOptionsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 16px; background: var(--glass-bg); backdrop-filter: blur(20px);">
            <div class="modal-header" style="border-bottom: 1px solid rgba(255,255,255,0.2);">
                <h5 class="modal-title" id="downloadOptionsModalLabel">Opsi Download</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-dark">Pilih format laporan untuk: <br><strong id="modal-nama-target" class="text-primary">...</strong></p>
                
                <div class="d-grid gap-3">
                    <form action="proses_download_laporan.php" method="POST" target="_blank" class="m-0">
                        <input type="hidden" name="id_target" id="modal-excel-id-target" value="">
                        <div id="modal-excel-months-container"></div>
                        
                        <button type="submit" class="btn btn-success w-100" id="modal-excel-button" title="Download Laporan Lengkap Bulanan">
                            <i class="bi bi-file-earmark-spreadsheet-fill me-2"></i> Download Laporan Lengkap
                        </button>
                    </form>

                    <form action="proses_download_laporan_pdf.php" method="POST" target="_blank" class="m-0">
                        <input type="hidden" name="id_target" id="modal-pdf-id-target" value="">
                        <button type="submit" class="btn btn-danger w-100" title="Download Laporan Selesai PDF">
                            <i class="bi bi-file-earmark-pdf-fill me-2"></i> Download PDF
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../../../templates/footer.php'; ?>