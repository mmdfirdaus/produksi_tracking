<?php
$page_title = 'Laporan Produksi Selesai';
include_once '../../../templates/header_admin.php';

// Pagination dan Pencarian
$limit = 12;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

try {
    $params = [];
    $base_where_clause = "
        WHERE EXISTS (
            SELECT 1 
            FROM production_targets pt 
            WHERE pt.id_barang = mb.id_barang AND UPPER(pt.status) = 'SELESAI'
        )
    ";

    if (!empty($search_query)) {
        $base_where_clause .= " AND mb.nama_barang LIKE :search";
        $params[':search'] = "%" . $search_query . "%";
    }

    // Hitung total barang untuk pagination
    $count_sql = "SELECT COUNT(mb.id_barang) FROM master_barang mb " . $base_where_clause;
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_results = $count_stmt->fetchColumn();
    $total_pages = ceil($total_results / $limit);

    // Query utama dengan pagination
    $sql = "
        SELECT 
            mb.id_barang, 
            mb.nama_barang, 
            mb.kategori, 
            mb.gambar,
            (SELECT COUNT(*) FROM production_targets pt WHERE pt.id_barang = mb.id_barang AND UPPER(pt.status) = 'SELESAI') as jumlah_laporan
        FROM master_barang mb
        " . $base_where_clause . "
        ORDER BY mb.nama_barang ASC 
        LIMIT :limit OFFSET :offset
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    if (!empty($search_query)) {
        $stmt->bindParam(':search', $params[':search'], PDO::PARAM_STR);
    }
    $stmt->execute();
    $barangs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Statistik
    $total_barang_selesai = $total_results;
    $total_laporan_query = $pdo->query("SELECT COUNT(*) FROM production_targets WHERE UPPER(status) = 'SELESAI'");
    $total_laporan_selesai = $total_laporan_query->fetchColumn();

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}

$base_url_uploads = $base_url . '/uploads/';
?>

<!-- Add viewport meta tag for proper mobile rendering -->
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">

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
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 1000"><defs><radialGradient id="a" cx="500" cy="500" r="500"><stop offset="0%" stop-color="%23ffffff" stop-opacity="0.1"/><stop offset="100%" stop-color="%23ffffff" stop-opacity="0"/></radialGradient></defs><circle cx="200" cy="200" r="100" fill="url(%23a)"/><circle cx="800" cy="300" r="150" fill="url(%23a)"/><circle cx="300" cy="700" r="80" fill="url(%23a)"/><circle cx="700" cy="800" r="120" fill="url(%23a)"/></svg>');
            opacity: 0.3;
            z-index: -1;
        }
        
        .main-container {
            padding: 2rem 1rem;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .page-header {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .page-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .title-icon {
            background: linear-gradient(135deg, var(--success-color), #059669);
            color: white;
            width: 60px;
            height: 60px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            box-shadow: 0 8px 20px rgba(16, 185, 129, 0.3);
        }
        
        .search-container {
            background: white;
            border-radius: 20px;
            padding: 1rem;
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(0, 0, 0, 0.05);
            min-width: 350px;
        }
        
        .search-input {
            border: none;
            border-radius: 16px;
            padding: 1rem 1.5rem;
            background: var(--light-bg);
            font-size: 1.1rem;
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
            padding: 1rem 2rem;
            color: white;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
            white-space: nowrap;
        }
        
        .search-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(99, 102, 241, 0.4);
        }
        
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(255, 255, 255, 0.2);
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
        }
        
        .stat-icon {
            font-size: 2rem;
            margin-bottom: 1rem;
        }
        
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }
        
        .product-card {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            position: relative;
        }
        
        .product-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: var(--card-shadow-hover);
        }
        
        .product-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .product-card:hover::before {
            opacity: 1;
        }
        
        .product-image-container {
            position: relative;
            height: 240px;
            overflow: hidden;
            background: linear-gradient(135deg, #f3f4f6, #e5e7eb);
        }
        
        .product-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.4s ease;
        }
        
        .product-card:hover .product-image {
            transform: scale(1.1);
        }
        
        .product-image.default-image {
    object-fit: contain; /* 👈 INI SOLUSINYA */
    background-color: #f8f9fa; /* Opsional: Latar belakang abu-abu muda */
}

/* Nonaktifkan efek zoom-on-hover HANYA untuk gambar default */
.product-card:hover .product-image.default-image {
    transform: scale(1);
}

        .product-content {
            padding: 2rem;
        }
        
        .product-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
        }
        
        .product-category {
            color: #6b7280;
            font-weight: 500;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .reports-badge {
            background: linear-gradient(135deg, var(--success-color), #059669);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }
        
        .view-details-btn {
            background: linear-gradient(135deg, var(--primary-color), #4f46e5);
            color: white;
            border: none;
            border-radius: 16px;
            padding: 1rem 1.5rem;
            width: 100%;
            font-weight: 600;
            font-size: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            text-decoration: none;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
            -webkit-tap-highlight-color: transparent;
        }
        
        .view-details-btn:hover {
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(99, 102, 241, 0.4);
        }
        
        .empty-state {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            padding: 4rem 2rem;
            text-align: center;
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .empty-state-icon {
            font-size: 5rem;
            color: #d1d5db;
            margin-bottom: 2rem;
        }
        
        .empty-state h4 {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: 1rem;
        }
        
        .empty-state p {
            color: #6b7280;
            font-size: 1.1rem;
        }
        
        .pagination-container {
            display: flex;
            justify-content: center;
            margin-top: 3rem;
        }
        
        .pagination-modern {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 1rem;
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(255, 255, 255, 0.2);
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
            -webkit-tap-highlight-color: transparent;
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
        
        .fade-in {
            animation: fadeInUp 0.6s ease forwards;
        }
        
        /* ========================================= */
/* ENHANCED RESPONSIVE DESIGN FOR MOBILE */
/* ========================================= */

/* Large Tablet (max-width: 1024px) */
@media (max-width: 1024px) {
    .products-grid {
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 1.5rem;
    }
    
    .main-container {
        padding: 1.5rem 1rem;
    }
}

/* Tablet (max-width: 768px) */
@media (max-width: 768px) {
    .main-container {
        padding: 1rem 0.75rem;
    }
    
    /* Page Header */
    .page-header {
        padding: 1.5rem 1rem;
        border-radius: 20px;
        margin-bottom: 1.5rem;
    }
    
    .page-header .d-flex {
        flex-direction: column;
        align-items: stretch !important;
    }
    
    .page-title {
        font-size: 2rem;
        margin-bottom: 1rem;
        justify-content: center;
    }
    
    .title-icon {
        width: 50px;
        height: 50px;
        font-size: 1.25rem;
        border-radius: 14px;
    }
    
    .text-muted.fs-5 {
        font-size: 1rem !important;
        text-align: center;
        margin-bottom: 1.5rem !important;
    }
    
    /* Search Container */
    .search-container {
        min-width: 100%;
        padding: 0.875rem;
        border-radius: 16px;
    }
    
    .search-input {
        font-size: 1rem;
        padding: 0.875rem 1.25rem;
        border-radius: 12px;
    }
    
    .search-input:focus {
        transform: none;
    }
    
    .search-btn {
        padding: 0.875rem 1.5rem;
        font-size: 0.95rem;
        border-radius: 12px;
    }
    
    .search-btn:hover {
        transform: none;
    }
    
    /* Stats Container */
    .stats-container {
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
        margin-bottom: 1.5rem;
    }
    
    .stat-card {
        padding: 1.25rem;
        border-radius: 16px;
    }
    
    .stat-card:hover {
        transform: translateY(-3px);
    }
    
    .stat-icon {
        font-size: 1.75rem;
        margin-bottom: 0.75rem;
    }
    
    .stat-number {
        font-size: 2rem;
        margin-bottom: 0.25rem;
    }
    
    .stat-label {
        font-size: 0.9rem;
    }
    
    /* CRITICAL: Products Grid - WAJIB 2 KOLOM */
    .products-grid {
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 1rem;
        margin-bottom: 2rem;
    }
    
    /* Product Card - Vertical Layout (NOT Horizontal) */
    .product-card {
        display: flex;
        flex-direction: column;
        border-radius: 20px;
    }
    
    .product-card:hover {
        transform: translateY(-5px) scale(1.01);
    }
    
    .product-image-container {
        width: 100%;
        height: 180px;
        flex-shrink: 0;
    }
    
    .product-content {
        padding: 1.25rem;
    }
    
    .product-title {
        font-size: 1.1rem;
        margin-bottom: 0.4rem;
        line-height: 1.3;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    
    .product-category {
        font-size: 0.85rem;
        margin-bottom: 0.75rem;
    }
    
    .reports-badge {
        font-size: 0.8rem;
        padding: 0.4rem 0.8rem;
        margin-bottom: 1rem;
    }
    
    .view-details-btn {
        padding: 0.875rem 1.25rem;
        font-size: 0.9rem;
        border-radius: 14px;
    }
    
    /* Empty State */
    .empty-state {
        padding: 3rem 1.5rem;
        border-radius: 20px;
    }
    
    .empty-state-icon {
        font-size: 4rem;
    }
    
    .empty-state h4 {
        font-size: 1.5rem;
    }
    
    .empty-state p {
        font-size: 1rem;
    }
    
    /* Pagination */
    .pagination-container {
        margin-top: 2rem;
    }
    
    .pagination-modern {
        padding: 0.75rem;
        border-radius: 16px;
    }
    
    .pagination-modern .page-link {
        padding: 0.6rem 0.875rem;
        font-size: 0.95rem;
        border-radius: 10px;
    }

    /* Back to Top Button */
    .back-to-top {
        width: 45px;
        height: 45px;
        bottom: 1.5rem;
        right: 1.5rem;
        font-size: 1.3rem;
    }
}

/* Small Mobile (max-width: 576px) */
@media (max-width: 576px) {
    body::before {
        opacity: 0.2;
    }
    
    .main-container {
        padding: 0.75rem 0.5rem;
    }
    
    .page-header {
        padding: 1.25rem 0.75rem;
        border-radius: 16px;
        margin-bottom: 1.25rem;
    }
    
    .page-title {
        font-size: 1.5rem;
        gap: 0.75rem;
    }
    
    .title-icon {
        width: 45px;
        height: 45px;
        font-size: 1.1rem;
        border-radius: 12px;
    }
    
    .text-muted.fs-5 {
        font-size: 0.9rem !important;
        line-height: 1.4;
    }
    
    /* Search Container - Stack Vertically */
    .search-container {
        padding: 0.75rem;
        border-radius: 14px;
    }
    
    .search-container .d-flex {
        flex-direction: column;
        gap: 0.75rem !important;
    }
    
    .search-input {
        font-size: 0.95rem;
        padding: 0.75rem 1rem;
        width: 100%;
    }
    
    .search-btn {
        width: 100%;
        padding: 0.75rem 1rem;
        font-size: 0.9rem;
    }
    
    /* Stats - Keep 2 Columns */
    .stats-container {
        grid-template-columns: 1fr 1fr;
        gap: 0.75rem;
        margin-bottom: 1.25rem;
    }
    
    .stat-card {
        padding: 1rem;
        border-radius: 14px;
    }
    
    .stat-icon {
        font-size: 1.5rem;
        margin-bottom: 0.5rem;
    }
    
    .stat-number {
        font-size: 1.75rem;
        margin-bottom: 0.25rem;
    }
    
    .stat-label {
        font-size: 0.85rem;
    }
    
    /* Products Grid - TETAP 2 KOLOM VERTICAL LAYOUT */
    .products-grid {
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 0.75rem;
    }
    
    .product-card {
        display: flex;
        flex-direction: column;
        border-radius: 16px;
        height: auto;
    }
    
    .product-image-container {
        width: 100%;
        height: 150px;
    }
    
    .product-content {
        padding: 1rem;
    }
    
    .product-title {
        font-size: 1rem;
        margin-bottom: 0.35rem;
        line-height: 1.25;
    }
    
    .product-category {
        font-size: 0.8rem;
        margin-bottom: 0.6rem;
    }
    
    .product-category i {
        font-size: 0.75rem;
    }
    
    .reports-badge {
        font-size: 0.75rem;
        padding: 0.35rem 0.65rem;
        margin-bottom: 0.85rem;
        border-radius: 12px;
    }
    
    .reports-badge i {
        font-size: 0.7rem;
    }
    
    .view-details-btn {
        padding: 0.7rem 1rem;
        font-size: 0.85rem;
        border-radius: 12px;
        gap: 0.4rem;
    }
    
    .view-details-btn i {
        font-size: 0.85rem;
    }
    
    .empty-state {
        padding: 2.5rem 1.25rem;
        border-radius: 16px;
    }
    
    .empty-state-icon {
        font-size: 3rem;
        margin-bottom: 1.5rem;
    }
    
    .empty-state h4 {
        font-size: 1.25rem;
        margin-bottom: 0.75rem;
    }
    
    .empty-state p {
        font-size: 0.9rem;
        line-height: 1.4;
    }
    
    .pagination-modern {
        padding: 0.5rem;
        border-radius: 14px;
    }
    
    .pagination-modern .page-item {
        margin: 0 0.1rem;
    }
    
    .pagination-modern .page-link {
        padding: 0.5rem 0.75rem;
        font-size: 0.85rem;
        border-radius: 8px;
        min-width: 36px;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    /* Hide some page numbers on mobile, keep navigation */
    .pagination-modern .page-item:not(:first-child):not(:last-child):not(.active) {
        display: none;
    }

    /* Back to Top Button */
    .back-to-top {
        width: 40px;
        height: 40px;
        bottom: 1rem;
        right: 1rem;
        font-size: 1.2rem;
    }
    
    /* Animation adjustments for mobile */
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
}

/* Extra Small Mobile (max-width: 400px) */
@media (max-width: 400px) {
    .main-container {
        padding: 0.5rem 0.375rem;
    }
    
    .page-header {
        padding: 1rem;
    }
    
    .page-title {
        font-size: 1.35rem;
    }
    
    .title-icon {
        width: 40px;
        height: 40px;
        font-size: 1rem;
    }
    
    .text-muted.fs-5 {
        font-size: 0.85rem !important;
    }
    
    .search-container {
        padding: 0.625rem;
    }
    
    .search-input {
        font-size: 0.9rem;
        padding: 0.625rem 0.875rem;
    }
    
    .search-btn {
        padding: 0.625rem 0.875rem;
        font-size: 0.85rem;
    }
    
    .stat-card {
        padding: 0.875rem;
    }
    
    .stat-icon {
        font-size: 1.25rem;
    }
    
    .stat-number {
        font-size: 1.5rem;
    }
    
    .stat-label {
        font-size: 0.8rem;
    }
    
    /* Products Grid - TETAP 2 KOLOM */
    .products-grid {
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 0.5rem;
    }
    
    .product-image-container {
        height: 130px;
    }
    
    .product-content {
        padding: 0.875rem;
    }
    
    .product-title {
        font-size: 0.9rem;
    }
    
    .product-category {
        font-size: 0.75rem;
    }
    
    .reports-badge {
        font-size: 0.7rem;
        padding: 0.3rem 0.55rem;
    }
    
    .view-details-btn {
        padding: 0.6rem 0.875rem;
        font-size: 0.8rem;
    }
    
    .pagination-modern .page-link {
        padding: 0.4rem 0.6rem;
        font-size: 0.8rem;
        min-width: 32px;
        height: 32px;
    }
}

/* Extra Extra Small Mobile (max-width: 360px) */
@media (max-width: 360px) {
    .products-grid {
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 0.4rem;
    }
    
    .product-image-container {
        height: 120px;
    }
    
    .product-content {
        padding: 0.75rem;
    }
    
    .product-title {
        font-size: 0.85rem;
    }
    
    .product-category {
        font-size: 0.7rem;
    }
    
    .reports-badge {
        padding: 0.25rem 0.5rem;
        font-size: 0.65rem;
    }
    
    .view-details-btn {
        padding: 0.5rem 0.75rem;
        font-size: 0.75rem;
    }
}

/* Landscape Mode Adjustments */
@media (max-width: 768px) and (orientation: landscape) {
    .main-container {
        padding: 0.75rem;
    }
    
    .page-header {
        padding: 1rem;
        margin-bottom: 1rem;
    }
    
    .page-title {
        font-size: 1.5rem;
    }
    
    .stats-container {
        grid-template-columns: 1fr 1fr;
    }
    
    .products-grid {
        grid-template-columns: repeat(3, 1fr) !important;
    }
    
    .product-card {
        flex-direction: column;
    }
    
    .product-image-container {
        width: 100%;
        height: 140px;
    }
}

/* Touch Device Optimizations */
@media (hover: none) and (pointer: coarse) {
    .product-card:hover {
        transform: none;
        box-shadow: var(--card-shadow);
    }
    
    .product-card:hover::before {
        opacity: 0;
    }
    
    .product-card:hover .product-image {
        transform: none;
    }
    
    .product-card:active {
        transform: scale(0.98);
        transition: transform 0.1s ease;
    }
    
    .stat-card:hover {
        transform: none;
    }
    
    .stat-card:active {
        transform: scale(0.98);
    }
    
    .view-details-btn:hover {
        transform: none;
        box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
    }
    
    .view-details-btn:active {
        transform: scale(0.95);
    }
    
    .search-btn:hover {
        transform: none;
    }
    
    .search-btn:active {
        transform: scale(0.95);
    }
    
    .pagination-modern .page-link:hover {
        transform: none;
        background: transparent;
    }
    
    .pagination-modern .page-link:active {
        transform: scale(0.95);
        background: rgba(99, 102, 241, 0.1);
    }
    
    .pagination-modern .page-item.active .page-link:active {
        transform: none;
    }

    .back-to-top:hover {
        transform: none;
    }

    .back-to-top:active {
        transform: scale(0.95);
    }
}

/* Safe Area Insets for Modern Phones */
@supports (padding: max(0px)) {
    .container-fluid {
        padding-left: max(1rem, env(safe-area-inset-left));
        padding-right: max(1rem, env(safe-area-inset-right));
        padding-bottom: env(safe-area-inset-bottom);
    }
    
    .main-container {
        padding-left: max(0.5rem, env(safe-area-inset-left));
        padding-right: max(0.5rem, env(safe-area-inset-right));
    }
}

/* iOS Specific Fixes */
@supports (-webkit-touch-callout: none) {
    .search-input {
        -webkit-appearance: none;
        -moz-appearance: none;
        appearance: none;
    }
    
    .search-btn,
    .view-details-btn,
    .pagination-modern .page-link {
        -webkit-tap-highlight-color: transparent;
    }
    
    body {
        -webkit-font-smoothing: antialiased;
        -webkit-overflow-scrolling: touch;
    }
}

/* Accessibility Improvements */
@media (prefers-reduced-motion: reduce) {
    * {
        animation: none !important;
        transition: none !important;
    }
    
    .product-card:hover {
        transform: none !important;
    }
    
    .product-image {
        transform: none !important;
    }

    html {
        scroll-behavior: auto;
    }
}

/* High Contrast Mode */
@media (prefers-contrast: high) {
    .product-card,
    .stat-card,
    .page-header,
    .search-container {
        border: 2px solid currentColor;
    }
    
    .search-input {
        border: 2px solid currentColor;
    }
    
    .view-details-btn {
        border: 2px solid currentColor;
    }
}

/* Print Styles */
@media print {
    body::before {
        display: none;
    }
    
    .search-container,
    .pagination-container,
    .back-to-top {
        display: none;
    }
    
    .products-grid {
        grid-template-columns: repeat(3, 1fr);
    }
    
    .product-card {
        box-shadow: none;
        border: 1px solid #ddd;
        page-break-inside: avoid;
    }
    
    .stat-card {
        box-shadow: none;
        border: 1px solid #ddd;
    }
}

/* Smooth scrolling */
html {
    scroll-behavior: smooth;
}

/* Prevent text selection on interactive elements */
.view-details-btn,
.search-btn,
.stat-card,
.pagination-modern .page-link {
    -webkit-user-select: none;
    -moz-user-select: none;
    -ms-user-select: none;
    user-select: none;
}
    </style>
</head>

<div class="container-fluid px-4 mt-4">
    <div class="main-container">
        <div class="page-header fade-in">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h1 class="page-title">
                        <div class="title-icon">
                            <i class="bi bi-archive-fill"></i>
                        </div>
                        <?php echo $page_title; ?>
                    </h1>
                    <p class="text-muted fs-5 mb-0">Kelola dan pantau semua laporan produksi yang telah diselesaikan</p>
                </div>
                
                <form action="" method="GET" class="search-container">
                    <div class="d-flex gap-2">
                        <input type="text" class="form-control search-input" name="search"
                            placeholder="🔍 Cari nama barang..." value="<?php echo htmlspecialchars($search_query); ?>" autocomplete="off">
                        <button class="btn search-btn" type="submit" aria-label="Cari">
                            <i class="bi bi-search me-1"></i> Cari
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="stats-container fade-in">
            <div class="stat-card">
                <div class="stat-icon" style="color: var(--success-color);"><i class="bi bi-check-circle-fill"></i></div>
                <div>
                    <div class="stat-number" style="color: var(--success-color);"><?php echo number_format($total_barang_selesai); ?></div>
                    <div class="stat-label">Total Barang Selesai</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="color: var(--primary-color);"><i class="bi bi-file-earmark-text-fill"></i></div>
                <div>
                    <div class="stat-number" style="color: var(--primary-color);"><?php echo number_format($total_laporan_selesai); ?></div>
                    <div class="stat-label">Total Laporan</div>
                </div>
            </div>
        </div>

        <div class="products-grid">
            <?php if (empty($barangs)): ?>
                <div class="empty-state fade-in" style="grid-column: 1 / -1;">
                    <div class="empty-state-icon"><i class="bi bi-inbox"></i></div>
                    <h4>
                        <?php if (!empty($search_query)): ?>
                            Barang "<?php echo htmlspecialchars($search_query); ?>" Tidak Ditemukan
                        <?php else: ?>
                            Belum Ada Laporan Selesai
                        <?php endif; ?>
                    </h4>
                    <p>
                        <?php if (!empty($search_query)): ?>
                            Pastikan kata kunci pencarian benar atau coba kata kunci lain.
                        <?php else: ?>
                            Belum ada laporan produksi yang selesai. Pastikan status target di database sudah diubah menjadi 'Selesai'.
                        <?php endif; ?>
                    </p>
                </div>
            <?php else: ?>
    <?php
    foreach ($barangs as $index => $barang):
        // Logika untuk menentukan gambar default
        $is_default = empty($barang['gambar']);
        $img_src = $base_url_uploads . htmlspecialchars($is_default ? 'default.png' : $barang['gambar']);
        $img_class = 'product-image' . ($is_default ? ' default-image' : '');
    ?>
    <div class="product-card fade-in" style="animation-delay: <?php echo 0.1 + ($index * 0.05); ?>s;">
        <div class="product-image-container">
            <img src="<?php echo $img_src; ?>"
                 class="<?php echo $img_class; ?>"
                 alt="<?php echo htmlspecialchars($barang['nama_barang']); ?>"
                 loading="lazy"
                 onerror="this.onerror=null; this.src='<?php echo $base_url_uploads; ?>default.png'; this.classList.add('default-image');">
        </div>
                    <div class="product-content">
                        <h3 class="product-title"><?php echo htmlspecialchars($barang['nama_barang']); ?></h3>
                        <div class="product-category">
                            <i class="bi bi-tag-fill"></i>
                            <?php echo htmlspecialchars($barang['kategori']); ?>
                        </div>
                        <div class="reports-badge">
                            <i class="bi bi-clipboard-check-fill"></i>
                            <?php echo number_format($barang['jumlah_laporan']); ?> Laporan Selesai
                        </div>
                        <a href="laporan_detail.php?id_barang=<?php echo $barang['id_barang']; ?>" class="view-details-btn">
                            <i class="bi bi-folder2-open"></i>
                            Lihat Detail
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if ($total_pages > 1): ?>
        <div class="pagination-container fade-in">
            <nav>
                <ul class="pagination pagination-modern">
                    <?php
                        $url_params = [];
                        if (!empty($search_query)) $url_params['search'] = $search_query;
                        
                        // Detect mobile
                        $is_mobile = isset($_SERVER['HTTP_USER_AGENT']) && preg_match('/Mobile|Android|iPhone/i', $_SERVER['HTTP_USER_AGENT']);
                        $range = $is_mobile ? 1 : 2;
                    ?>
                    
                    <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($url_params, ['page' => $page - 1])); ?>" aria-label="Previous">
                            <i class="bi bi-chevron-left"></i>
                        </a>
                    </li>
                    
                    <?php if (!$is_mobile): ?>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php if ($i == $page) echo 'active'; ?>">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($url_params, ['page' => $i])); ?>"><?php echo $i; ?></a>
                        </li>
                        <?php endfor; ?>
                    <?php else: ?>
                        <?php 
                        // Mobile pagination logic
                        $start = max(1, $page - $range);
                        $end = min($total_pages, $page + $range);
                        
                        if ($start > 1) {
                            echo '<li class="page-item"><a class="page-link" href="?' . http_build_query(array_merge($url_params, ['page' => 1])) . '">1</a></li>';
                            if ($start > 2) {
                                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            }
                        }
                        
                        for ($i = $start; $i <= $end; $i++): ?>
                        <li class="page-item <?php if ($i == $page) echo 'active'; ?>">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($url_params, ['page' => $i])); ?>"><?php echo $i; ?></a>
                        </li>
                        <?php 
                        endfor;
                        
                        if ($end < $total_pages) {
                            if ($end < $total_pages - 1) {
                                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            }
                            echo '<li class="page-item"><a class="page-link" href="?' . http_build_query(array_merge($url_params, ['page' => $total_pages])) . '">' . $total_pages . '</a></li>';
                        }
                        ?>
                    <?php endif; ?>
                    
                    <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($url_params, ['page' => $page + 1])); ?>" aria-label="Next">
                            <i class="bi bi-chevron-right"></i>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Back to Top Button -->
<button class="back-to-top" id="backToTopBtn" aria-label="Kembali ke atas">
    <i class="bi bi-arrow-up"></i>
</button>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Animasi fade-in bertahap
    const elements = document.querySelectorAll('.fade-in');
    elements.forEach((el, index) => {
        const delay = el.style.animationDelay || `${index * 0.05}s`;
        el.style.animationDelay = delay;
    });
    
    // Enhanced search functionality
    const searchInput = document.querySelector('.search-input');
    const searchBtn = document.querySelector('.search-btn');
    
    if (searchInput) {
        // Add clear button for mobile
        if (window.innerWidth <= 768 && searchInput.value.length > 0) {
            const clearBtn = document.createElement('button');
            clearBtn.type = 'button';
            clearBtn.className = 'clear-search';
            clearBtn.innerHTML = '×';
            clearBtn.style.cssText = `
                position: absolute;
                right: 10px;
                top: 50%;
                transform: translateY(-50%);
                width: 24px;
                height: 24px;
                border: none;
                background: #e0e0e0;
                border-radius: 50%;
                font-size: 16px;
                color: #666;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 10;
            `;
            
            clearBtn.addEventListener('click', function() {
                searchInput.value = '';
                searchInput.focus();
                this.style.display = 'none';
            });
            
            const searchContainer = searchInput.parentElement;
            searchContainer.style.position = 'relative';
            searchContainer.appendChild(clearBtn);
            
            searchInput.addEventListener('input', function() {
                clearBtn.style.display = this.value.length > 0 ? 'flex' : 'none';
            });
        }
    }
    
    // Touch feedback for mobile
    if ('ontouchstart' in window) {
        const touchElements = document.querySelectorAll('.view-details-btn, .search-btn, .stat-card, .page-link');
        touchElements.forEach(elem => {
            elem.addEventListener('touchstart', function() {
                this.style.opacity = '0.7';
            });
            
            elem.addEventListener('touchend', function() {
                setTimeout(() => {
                    this.style.opacity = '1';
                }, 100);
            });
        });
    }
    
    // Lazy loading for images
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.classList.add('loaded');
                    observer.unobserve(img);
                }
            });
        }, {
            rootMargin: '50px 0px',
            threshold: 0.01
        });
        
        const images = document.querySelectorAll('.product-image');
        images.forEach(img => imageObserver.observe(img));
    }
    
    // Smooth scroll to top when clicking pagination
    const paginationLinks = document.querySelectorAll('.page-link');
    paginationLinks.forEach(link => {
        if (link.href) {
            link.addEventListener('click', function(e) {
                // Add loading state
                document.body.style.opacity = '0.7';
                
                // Smooth scroll to top on mobile
                if (window.innerWidth <= 768) {
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }
            });
        }
    });
    
    // Optimize animations for mobile
    if (window.innerWidth <= 768) {
        document.querySelectorAll('.product-card').forEach(card => {
            card.style.transition = 'transform 0.2s ease, opacity 0.3s ease';
        });
    }
    
    // Handle orientation change
    window.addEventListener('orientationchange', function() {
        setTimeout(() => {
            window.location.reload();
        }, 500);
    });

    // --- BACK TO TOP BUTTON FUNCTIONALITY ---
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
    // --- END BACK TO TOP ---
});

// Keyboard shortcuts (desktop only)
if (window.innerWidth > 768) {
    document.addEventListener('keydown', function(e) {
        // Focus search with Ctrl+F or /
        if ((e.ctrlKey && e.key === 'f') || e.key === '/') {
            e.preventDefault();
            const searchInput = document.querySelector('.search-input');
            if (searchInput) {
                searchInput.focus();
                searchInput.select();
            }
        }
        
        // Navigate pagination with arrow keys
        if (e.key === 'ArrowLeft' || e.key === 'ArrowRight') {
            const activeLink = document.querySelector('.page-item.active');
            if (activeLink) {
                const isNext = e.key === 'ArrowRight';
                const targetLink = isNext ? 
                    activeLink.nextElementSibling?.querySelector('.page-link') :
                    activeLink.previousElementSibling?.querySelector('.page-link');
                
                if (targetLink && targetLink.href && !targetLink.parentElement.classList.contains('disabled')) {
                    window.location.href = targetLink.href;
                }
            }
        }
    });
}

// Prevent double-tap zoom on mobile
let lastTouchEnd = 0;
document.addEventListener('touchend', function(event) {
    const now = Date.now();
    if (now - lastTouchEnd <= 300) {
        event.preventDefault();
    }
    lastTouchEnd = now;
}, { passive: false });

// Performance optimization for scroll
let ticking = false;
function requestTick() {
    if (!ticking) {
        window.requestAnimationFrame(updateOnScroll);
        ticking = true;
    }
}

function updateOnScroll() {
    // Add any scroll-based animations here
    ticking = false;
}

window.addEventListener('scroll', requestTick, { passive: true });
</script>

<?php include_once '../../../templates/footer.php'; ?>