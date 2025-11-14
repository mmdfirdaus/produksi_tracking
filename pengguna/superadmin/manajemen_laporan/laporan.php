<?php
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'superadmin') {
    header("location: ../../auth/login.php");
    exit;
}

$page_title = 'Manajemen Laporan Selesai';
include '../../../templates/header_superadmin.php';
include '../../../system/database_connection.php';

// =================================================================
// SEMUA LOGIKA PHP DARI KODE LAMA ANDA TETAP DIPERTAHANKAN
// =================================================================
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

    $count_sql = "SELECT COUNT(mb.id_barang) FROM master_barang mb " . $base_where_clause;
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_results = $count_stmt->fetchColumn();
    $total_pages = ceil($total_results / $limit);

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

    // STATISTIK TAMBAHAN DARI KODE LAMA (UNTUK KARTU STATISTIK UI BARU)
    $total_barang_selesai = $total_results; // Total barang unik yang punya laporan selesai
    $total_laporan_query = $pdo->query("SELECT COUNT(*) FROM production_targets WHERE UPPER(status) = 'SELESAI'");
    $total_laporan_selesai = $total_laporan_query->fetchColumn();


} catch (PDOException $e) {
    die("Error saat mengambil data: " . $e->getMessage());
}
?>
<head>
    <style>
        /* ==================== BASE STYLES (DESKTOP) ==================== */
        :root {
            --primary-color: #6366f1; --secondary-color: #8b5cf6; --success-color: #10b981;
            --warning-color: #f59e0b; --danger-color: #ef4444; --info-color: #06b6d4;
            --dark-color: #1f2937; --light-bg: #f8fafc;
            --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --card-shadow-hover: 0 10px 25px -3px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
            --glass-bg: rgba(255, 255, 255, 0.95);
            --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        body {
            background: var(--gradient-primary);
            min-height: 100vh; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            position: relative;
        }
        body::before {
            content: ''; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 1000"><defs><radialGradient id="a" cx="500" cy="500" r="500"><stop offset="0%" stop-color="%23ffffff" stop-opacity="0.1"/><stop offset="100%" stop-color="%23ffffff" stop-opacity="0"/></radialGradient></defs><circle cx="200" cy="200" r="100" fill="url(%23a)"/><circle cx="800" cy="300" r="150" fill="url(%23a)"/><circle cx="300" cy="700" r="80" fill="url(%23a)"/><circle cx="700" cy="800" r="120" fill="url(%23a)"/></svg>');
            opacity: 0.3; z-index: -1;
        }
        .main-container {
            padding: 2rem 1rem; max-width: 1400px; margin: 0 auto;
        }
        .page-header {
            background: var(--glass-bg); backdrop-filter: blur(20px); border-radius: 24px;
            padding: 2rem; margin-bottom: 2rem; box-shadow: var(--card-shadow);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .page-title {
            font-size: 2.5rem; font-weight: 700; color: var(--dark-color); margin-bottom: 0.5rem;
            display: flex; align-items: center; gap: 1rem;
        }
        .title-icon {
            background: linear-gradient(135deg, var(--success-color), #059669); color: white;
            width: 60px; height: 60px; border-radius: 16px; display: flex; align-items: center;
            justify-content: center; font-size: 1.5rem; box-shadow: 0 8px 20px rgba(16, 185, 129, 0.3);
        }
        .search-container {
            background: white; border-radius: 20px; padding: 1rem; box-shadow: var(--card-shadow);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }
        .search-input {
            border: none; border-radius: 16px; padding: 1rem 1.5rem; background: var(--light-bg);
            font-size: 1.1rem; transition: all 0.3s ease;
        }
        .search-input:focus {
            background: white; box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
            border: 2px solid var(--primary-color);
        }
        .search-btn {
            background: linear-gradient(135deg, var(--primary-color), #4f46e5); border: none;
            border-radius: 16px; padding: 1rem 2rem; color: white; font-weight: 600;
            transition: all 0.3s ease; box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
        }
        .search-btn:hover {
            transform: translateY(-2px); box-shadow: 0 6px 16px rgba(99, 102, 241, 0.4);
        }
        .stats-container {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem; margin-bottom: 2rem;
        }
        .stat-card {
            background: var(--glass-bg); backdrop-filter: blur(10px); border-radius: 20px;
            padding: 1.5rem; text-align: center; box-shadow: var(--card-shadow);
            border: 1px solid rgba(255, 255, 255, 0.2); transition: all 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-5px); box-shadow: var(--card-shadow-hover);
        }
        .stat-number {
            font-size: 2.5rem; font-weight: 800; margin-bottom: 0.5rem;
        }
        .stat-label { color: #6b7280; font-weight: 600; }
        .stat-icon { font-size: 2rem; margin-bottom: 1rem; }
        .products-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 2rem; margin-bottom: 3rem;
        }
        .product-card {
            background: var(--glass-bg); backdrop-filter: blur(10px); border-radius: 24px;
            overflow: hidden; box-shadow: var(--card-shadow);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94); position: relative;
        }
        .product-card:hover {
            transform: translateY(-8px) scale(1.02); box-shadow: var(--card-shadow-hover);
        }
        .product-card::before {
            content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
            opacity: 0; transition: opacity 0.3s ease;
        }
        .product-card:hover::before { opacity: 1; }
        .product-image-container {
            position: relative; height: 240px; overflow: hidden;
            background: linear-gradient(135deg, #f3f4f6, #e5e7eb);
        }
        .product-image {
            width: 100%; height: 100%; object-fit: cover; transition: transform 0.4s ease;
        }
        .product-card:hover .product-image { transform: scale(1.1); }
        .product-image.default-image {
    object-fit: contain; /* 👈 INI SOLUSINYA */
    background-color: #f8f9fa; /* Opsional: Latar belakang abu-abu muda */
}

/* Nonaktifkan efek zoom-on-hover HANYA untuk gambar default */
.product-card:hover .product-image.default-image {
    transform: scale(1);
}
        .product-content { padding: 2rem; }
        .product-title {
            font-size: 1.5rem; font-weight: 700; color: var(--dark-color); margin-bottom: 0.5rem;
        }
        .product-category {
            color: #6b7280; font-weight: 500; margin-bottom: 1rem;
            display: flex; align-items: center; gap: 0.5rem;
        }
        .reports-badge {
            background: linear-gradient(135deg, var(--success-color), #059669); color: white;
            padding: 0.5rem 1rem; border-radius: 20px; font-weight: 600; font-size: 0.9rem;
            display: inline-flex; align-items: center; gap: 0.5rem; margin-bottom: 1.5rem;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }
        .view-details-btn {
            background: linear-gradient(135deg, var(--primary-color), #4f46e5); color: white;
            border: none; border-radius: 16px; padding: 1rem 1.5rem; width: 100%;
            font-weight: 600; font-size: 1rem; display: flex; align-items: center;
            justify-content: center; gap: 0.5rem; transition: all 0.3s ease;
            text-decoration: none; box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
        }
        .view-details-btn:hover {
            color: white; transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(99, 102, 241, 0.4);
        }
        .empty-state {
            background: var(--glass-bg); backdrop-filter: blur(10px); border-radius: 24px;
            padding: 4rem 2rem; text-align: center; box-shadow: var(--card-shadow);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .empty-state-icon { font-size: 5rem; color: #d1d5db; margin-bottom: 2rem; }
        .empty-state h4 {
            font-size: 1.75rem; font-weight: 700; color: var(--dark-color); margin-bottom: 1rem;
        }
        .empty-state p { color: #6b7280; font-size: 1.1rem; }
        .pagination-container {
            display: flex; justify-content: center; margin-top: 3rem;
        }
        .pagination-modern {
            background: var(--glass-bg); backdrop-filter: blur(10px); border-radius: 20px;
            padding: 1rem; box-shadow: var(--card-shadow);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .pagination-modern .page-item { margin: 0 0.25rem; }
        .pagination-modern .page-link {
            border: none; border-radius: 12px; padding: 0.75rem 1rem; color: var(--dark-color);
            background: transparent; font-weight: 600; transition: all 0.3s ease;
        }
        .pagination-modern .page-item.active .page-link {
            background: linear-gradient(135deg, var(--primary-color), #4f46e5);
            color: white; box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
        }
        .pagination-modern .page-link:hover {
            background: rgba(99, 102, 241, 0.1); transform: translateY(-2px);
        }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        .fade-in { animation: fadeInUp 0.6s ease forwards; }

        /* ==================== RESPONSIVE STYLES FOR MOBILE ==================== */

        /* Tablet (max-width: 992px) */
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
            
            .products-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1.5rem;
            }
        }

        /* Mobile (max-width: 768px) */
        @media (max-width: 768px) {
            .main-container {
                padding: 1rem 0.75rem;
            }
            
            /* Page Header */
            .page-header {
                border-radius: 16px;
                padding: 1.5rem 1rem;
                margin-bottom: 1.5rem;
            }
            
            .d-flex.justify-content-between {
                flex-direction: column !important;
                gap: 1rem !important;
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
            
            .page-header p {
                font-size: 0.9rem !important;
            }
            
            /* Search Container */
            .search-container {
                min-width: 100% !important;
                padding: 0.75rem;
                border-radius: 12px;
            }
            
            .search-input {
                padding: 0.75rem 1rem;
                font-size: 0.9rem;
                border-radius: 12px;
            }
            
            .search-btn {
                padding: 0.75rem 1.25rem;
                font-size: 0.9rem;
                border-radius: 12px;
            }
            
            /* Stats Container */
            .stats-container {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
                margin-bottom: 1.5rem;
            }
            
            .stat-card {
                padding: 1rem;
                border-radius: 16px;
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
                font-size: 0.8rem;
            }
            
            /* CRITICAL: Products Grid - WAJIB 2 KOLOM */
            .products-grid {
                grid-template-columns: repeat(2, 1fr) !important; /* FIXED 2 COLUMNS */
                gap: 0.75rem;
                margin-bottom: 2rem;
            }
            
            /* Product Card - Optimized for 2 Column Layout */
            .product-card {
                border-radius: 16px;
            }
            
            .product-card:hover {
                transform: translateY(-4px) scale(1.01);
            }
            
            .product-image-container {
                height: 180px;
            }
            
            .product-content {
                padding: 1rem;
            }
            
            .product-title {
                font-size: 1rem;
                margin-bottom: 0.4rem;
                line-height: 1.3;
                /* Limit to 2 lines */
                display: -webkit-box;
                -webkit-line-clamp: 2;
                -webkit-box-orient: vertical;
                overflow: hidden;
            }
            
            .product-category {
                font-size: 0.75rem;
                margin-bottom: 0.75rem;
                gap: 0.3rem;
            }
            
            .product-category i {
                font-size: 0.7rem;
            }
            
            .reports-badge {
                padding: 0.4rem 0.75rem;
                font-size: 0.75rem;
                border-radius: 12px;
                margin-bottom: 1rem;
                gap: 0.3rem;
            }
            
            .reports-badge i {
                font-size: 0.7rem;
            }
            
            .view-details-btn {
                padding: 0.75rem 1rem;
                font-size: 0.85rem;
                border-radius: 12px;
                gap: 0.4rem;
            }
            
            .view-details-btn i {
                font-size: 0.85rem;
            }
            
            /* Empty State */
            .empty-state {
                grid-column: 1 / -1;
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
            
            /* Pagination */
            .pagination-container {
                margin-top: 2rem;
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

        /* Small Mobile (max-width: 480px) */
        @media (max-width: 480px) {
            .main-container {
                padding: 0.75rem 0.5rem;
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
            
            .page-header p {
                font-size: 0.85rem !important;
            }
            
            /* Stats - Stack to Single Column on Very Small Screens */
            .stats-container {
                grid-template-columns: repeat(2, 1fr);
                gap: 0.75rem;
            }
            
            .stat-card {
                padding: 0.75rem;
            }
            
            .stat-number {
                font-size: 1.5rem;
            }
            
            /* Products Grid - TETAP 2 KOLOM BAHKAN DI LAYAR KECIL */
            .products-grid {
                grid-template-columns: repeat(2, 1fr) !important; /* TETAP 2 KOLOM */
                gap: 0.5rem;
            }
            
            .product-card {
                border-radius: 12px;
            }
            
            .product-image-container {
                height: 150px;
            }
            
            .product-content {
                padding: 0.75rem;
            }
            
            .product-title {
                font-size: 0.9rem;
                margin-bottom: 0.3rem;
            }
            
            .product-category {
                font-size: 0.7rem;
                margin-bottom: 0.6rem;
            }
            
            .reports-badge {
                padding: 0.35rem 0.6rem;
                font-size: 0.7rem;
                margin-bottom: 0.75rem;
            }
            
            .view-details-btn {
                padding: 0.6rem 0.75rem;
                font-size: 0.8rem;
            }
            
            .search-container {
                padding: 0.5rem;
            }
            
            .search-input {
                padding: 0.6rem 0.75rem;
                font-size: 0.85rem;
            }
            
            .search-btn {
                padding: 0.6rem 1rem;
                font-size: 0.85rem;
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

        /* Extra Small Mobile (max-width: 360px) - TETAP 2 KOLOM */
        @media (max-width: 360px) {
            .products-grid {
                grid-template-columns: repeat(2, 1fr) !important; /* TETAP KONSISTEN 2 KOLOM */
                gap: 0.4rem;
            }
            
            .product-image-container {
                height: 130px;
            }
            
            .product-content {
                padding: 0.6rem;
            }
            
            .product-title {
                font-size: 0.85rem;
            }
            
            .product-category {
                font-size: 0.65rem;
            }
            
            .reports-badge {
                padding: 0.3rem 0.5rem;
                font-size: 0.65rem;
            }
            
            .view-details-btn {
                padding: 0.5rem 0.6rem;
                font-size: 0.75rem;
            }
        }
    </style>
</head>
<body class="sb-nav-fixed">
    <div id="layoutSidenav_content">
        <main>
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
                        
                        <form action="" method="GET" class="search-container" style="min-width: 350px;">
                            <div class="d-flex gap-2">
                                <input type="text" class="form-control search-input" name="search"
                                    placeholder="🔍 Cari nama barang..." value="<?php echo htmlspecialchars($search_query); ?>">
                                <button class="btn search-btn" type="submit">
                                    <i class="bi bi-search me-1"></i> Cari
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="stats-container fade-in">
                    <div class="stat-card">
                        <div class="stat-icon" style="color: var(--success-color);"><i class="bi bi-check-circle-fill"></i></div>
                        <div class="stat-number" style="color: var(--success-color);"><?php echo $total_barang_selesai; ?></div>
                        <div class="stat-label">Total Barang Selesai</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="color: var(--primary-color);"><i class="bi bi-file-earmark-text-fill"></i></div>
                        <div class="stat-number" style="color: var(--primary-color);"><?php echo $total_laporan_selesai; ?></div>
                        <div class="stat-label">Total Laporan</div>
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
                        <?php foreach ($barangs as $barang): ?>
                        <div class="product-card fade-in">
    <div class="product-image-container">
        <?php
            // Logika untuk menentukan gambar default
            $is_default = empty($barang['gambar']);
            $img_src = '../../../uploads/' . htmlspecialchars($is_default ? 'default.png' : $barang['gambar']);
            $img_class = 'product-image' . ($is_default ? ' default-image' : '');
        ?>
        <img src="<?php echo $img_src; ?>"
             class="<?php echo $img_class; ?>"
             alt="<?php echo htmlspecialchars($barang['nama_barang']); ?>"
             loading="lazy"
             onerror="this.onerror=null; this.src='../../../uploads/default.png'; this.classList.add('default-image');">
    </div>
                            <div class="product-content">
                                <h3 class="product-title"><?php echo htmlspecialchars($barang['nama_barang']); ?></h3>
                                <div class="product-category">
                                    <i class="bi bi-tag-fill"></i>
                                    <?php echo htmlspecialchars($barang['kategori']); ?>
                                </div>
                                <div class="reports-badge">
                                    <i class="bi bi-clipboard-check-fill"></i>
                                    <?php echo $barang['jumlah_laporan']; ?> Laporan
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
                                
                                for ($i = 1; $i <= $total_pages; $i++): 
                            ?>
                            <li class="page-item <?php if ($i == $page) echo 'active'; ?>">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($url_params, ['page' => $i])); ?>"><?php echo $i; ?></a>
                            </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>
            </div>
        </main>
        <?php include '../../../templates/footer.php'; ?>
    </div>
</body>
</html>