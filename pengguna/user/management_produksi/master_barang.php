<?php
$page_title = 'Master Barang';
// Include header user yang baru dengan glassmorphism
include_once '../../../templates/header_user.php';
include_once '../../../system/database_connection.php';

// Logika untuk pagination dan pencarian
$limit = 12; // Jumlah kartu per halaman
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

try {
    // Base SQL untuk fleksibilitas query
    $base_sql = "FROM master_barang mb LEFT JOIN master_kategori mk ON mb.id_kategori = mk.id_kategori WHERE mb.is_active = 1";
    $params = [];

    if (!empty($search_query)) {
        // Menambahkan kondisi pencarian jika ada input dari user
        $base_sql .= " AND mb.nama_barang LIKE :search";
        $params[':search'] = "%" . $search_query . "%";
    }

    // Query untuk menghitung total hasil yang sesuai
    $count_sql = "SELECT COUNT(mb.id_barang) " . $base_sql;
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_results = $count_stmt->fetchColumn();
    $total_pages = ceil($total_results / $limit);

    // Query utama untuk mengambil data barang dengan JOIN, search, dan limit
    $sql = "SELECT mb.id_barang, mb.nama_barang, mb.gambar, mk.nama_kategori " . $base_sql . " ORDER BY mb.nama_barang ASC LIMIT :limit OFFSET :offset";
    
    $stmt = $pdo->prepare($sql);
    
    // Bind parameter untuk search (jika ada)
    if (!empty($search_query)) {
        $stmt->bindParam(':search', $params[':search'], PDO::PARAM_STR);
    }
    
    // Bind parameter untuk limit dan offset
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $barangs = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

$base_url = '/produksi_tracking';
$base_url_uploads = $base_url . '/uploads/';
?>

<!-- Add viewport meta tag for proper mobile rendering -->
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">

<style>
/* Modern Master Barang UI - User Version with Glassmorphism */
:root {
    --glass-bg: rgba(255, 255, 255, 0.1);
    --glass-border: rgba(255, 255, 255, 0.18);
    --accent-color: #667eea;
    --accent-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --hover-bg: rgba(255, 255, 255, 0.15);
    --text-primary: #ffffff;
    --text-secondary: rgba(255, 255, 255, 0.85);
}

.content-wrapper {
    max-width: 1400px;
    margin: 0 auto;
    padding: 2rem 1rem;
}

/* Page Header with Glassmorphism */
.page-header {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border-radius: 25px;
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}

.page-title {
    color: #2c3e50;
    font-weight: 700;
    font-size: 2rem;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.title-icon {
    background: var(--accent-gradient);
    color: white;
    width: 50px;
    height: 50px;
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
}

/* Search Container */
.search-container {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border-radius: 20px;
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    transition: all 0.3s ease;
}

.search-container:hover {
    transform: translateY(-2px);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
}

.search-form {
    position: relative;
    max-width: 600px;
    margin: 0 auto;
}

.search-input {
    width: 100%;
    padding: 1rem 1.5rem;
    padding-right: 60px;
    border: 2px solid rgba(102, 126, 234, 0.3);
    border-radius: 50px;
    font-size: 1rem;
    background: white;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
}

.search-input:focus {
    outline: none;
    border-color: var(--accent-color);
    box-shadow: 0 0 25px rgba(102, 126, 234, 0.3);
    transform: translateY(-2px);
}

.search-btn {
    position: absolute;
    right: 8px;
    top: 50%;
    transform: translateY(-50%);
    background: var(--accent-gradient);
    color: white;
    border: none;
    border-radius: 50px;
    width: 45px;
    height: 45px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
}

.search-btn:hover {
    transform: translateY(-50%) scale(1.1);
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
}

/* Stats Bar */
.stats-bar {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border-radius: 20px;
    padding: 1.5rem 2rem;
    margin-bottom: 2rem;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    text-align: center;
}

.stats-content {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 2rem;
    flex-wrap: wrap;
}

.stat-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #2c3e50;
    font-weight: 600;
}

.stat-icon {
    color: var(--accent-color);
    font-size: 1.2rem;
}

/* Products Grid */
.products-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 2rem;
    margin-bottom: 3rem;
}

/* Product Card */
.product-card {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border-radius: 25px;
    overflow: hidden;
    transition: all 0.4s ease;
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    position: relative;
    height: 100%;
    display: flex;
    flex-direction: column;
}

.product-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #667eea, #764ba2, #f093fb, #f5576c);
    opacity: 0;
    transition: opacity 0.3s ease;
}

.product-card:hover::before {
    opacity: 1;
}

.product-card:hover {
    transform: translateY(-15px) scale(1.02);
    box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
    z-index: 10;
}

.product-image-container {
    width: 100%;
    height: 250px;
    overflow: hidden;
    position: relative;
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

/* Loading Skeleton for Images */
.image-skeleton {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
    background-size: 200% 100%;
    animation: shimmer 1.5s infinite;
}

@keyframes shimmer {
    0% { background-position: -200% 0; }
    100% { background-position: 200% 0; }
}

.product-content {
    padding: 2rem;
    display: flex;
    flex-direction: column;
    flex-grow: 1;
}

.product-title {
    font-size: 1.4rem;
    font-weight: 700;
    color: #2c3e50;
    margin: 0 0 0.5rem 0;
    line-height: 1.3;
}

.product-category {
    color: #666;
    font-size: 1rem;
    margin: 0 0 1.5rem 0;
    padding: 0.5rem 1rem;
    background: linear-gradient(45deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
    border-radius: 15px;
    text-align: center;
    font-weight: 500;
}

.product-actions {
    margin-top: auto;
}

.detail-btn {
    background: linear-gradient(45deg, #28a745, #20c997);
    color: white;
    text-decoration: none;
    padding: 1rem 1.5rem;
    border-radius: 50px;
    font-weight: 600;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    box-shadow: 0 4px 15px rgba(40, 167, 69, 0.4);
    -webkit-tap-highlight-color: transparent;
}

.detail-btn:hover {
    background: linear-gradient(45deg, #218838, #1aa179);
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(40, 167, 69, 0.6);
    text-decoration: none;
}

/* Empty State */
.empty-state {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border-radius: 25px;
    padding: 4rem 2rem;
    text-align: center;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    grid-column: 1 / -1;
    animation: fadeInUp 0.6s ease;
}

.empty-icon {
    font-size: 5rem;
    background: var(--accent-gradient);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin-bottom: 1.5rem;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.7; }
}

.empty-title {
    font-size: 1.8rem;
    font-weight: 700;
    color: #2c3e50;
    margin-bottom: 1rem;
}

.empty-message {
    color: #666;
    font-size: 1.1rem;
    margin: 0 0 2rem 0;
    line-height: 1.6;
}

.empty-action {
    display: inline-block;
    padding: 0.75rem 2rem;
    background: var(--accent-gradient);
    color: white;
    text-decoration: none;
    border-radius: 50px;
    font-weight: 600;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
}

.empty-action:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
    color: white;
    text-decoration: none;
}

/* Pagination */
.pagination-container {
    display: flex;
    justify-content: center;
    margin-top: 3rem;
}

.pagination-modern {
    display: flex;
    gap: 0.5rem;
    list-style: none;
    padding: 0;
    margin: 0;
}

.page-item-modern {
    margin: 0;
}

.page-link-modern {
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 50px;
    height: 50px;
    padding: 0 1rem;
    border-radius: 15px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
    background: rgba(255, 255, 255, 0.9);
    color: #2c3e50;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    -webkit-tap-highlight-color: transparent;
}

.page-link-modern:hover {
    background: var(--accent-gradient);
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
    text-decoration: none;
}

.page-item-modern.active .page-link-modern {
    background: var(--accent-gradient);
    color: white;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
}

.page-item-modern.disabled .page-link-modern {
    opacity: 0.5;
    cursor: not-allowed;
    pointer-events: none;
}

/* Scroll to Top Button - Mobile Only */
.scroll-to-top {
    position: fixed;
    bottom: 30px;
    right: 30px;
    width: 50px;
    height: 50px;
    background: var(--accent-gradient);
    color: white;
    border: none;
    border-radius: 50%;
    display: none;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    cursor: pointer;
    box-shadow: 0 4px 20px rgba(102, 126, 234, 0.4);
    transition: all 0.3s ease;
    z-index: 1000;
    -webkit-tap-highlight-color: transparent;
}

.scroll-to-top.show {
    display: flex;
    animation: fadeInUp 0.3s ease;
}

.scroll-to-top:hover {
    transform: scale(1.1);
    box-shadow: 0 6px 25px rgba(102, 126, 234, 0.6);
}

.scroll-to-top:active {
    transform: scale(0.95);
}

/* Loading Animation */
.loading {
    opacity: 0;
    animation: fadeInUp 0.6s ease-out forwards;
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

/* Loading Overlay */
.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(30, 60, 114, 0.9);
    backdrop-filter: blur(10px);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 9999;
}

.loading-overlay.show {
    display: flex;
}

.loading-spinner {
    width: 60px;
    height: 60px;
    border: 4px solid rgba(255, 255, 255, 0.3);
    border-top: 4px solid white;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.loading-text {
    color: white;
    margin-top: 1rem;
    font-size: 1.1rem;
    font-weight: 600;
}

/* ========================================= */
/* RESPONSIVE DESIGN */
/* ========================================= */

/* Tablet (max-width: 768px) */
@media (max-width: 768px) {
    .content-wrapper {
        padding: 1.5rem 0.75rem;
    }
    
    .page-header {
        flex-direction: column;
        text-align: center;
        padding: 1.5rem;
        border-radius: 20px;
        margin-bottom: 1.5rem;
    }
    
    .page-title {
        font-size: 1.75rem;
        justify-content: center;
        width: 100%;
    }
    
    .title-icon {
        width: 45px;
        height: 45px;
        font-size: 1.25rem;
    }
    
    .search-container {
        padding: 1.5rem;
        border-radius: 16px;
        margin-bottom: 1.5rem;
    }
    
    .search-container:hover {
        transform: none;
    }
    
    .search-input {
        font-size: 0.95rem;
        padding: 0.875rem 1.25rem;
        padding-right: 55px;
    }
    
    .search-input:focus {
        transform: none;
    }
    
    .search-btn {
        width: 40px;
        height: 40px;
        right: 6px;
    }
    
    .products-grid {
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 1.25rem;
        margin-bottom: 2rem;
    }
    
    .product-card {
        border-radius: 20px;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
    }
    
    .product-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 30px rgba(0, 0, 0, 0.12);
    }
    
    .product-image-container {
        height: 220px;
    }
    
    .product-content {
        padding: 1.5rem;
    }
    
    .product-title {
        font-size: 1.25rem;
    }
    
    .product-category {
        font-size: 0.9rem;
        padding: 0.4rem 0.8rem;
        margin: 0 0 1.25rem 0;
    }
    
    .detail-btn {
        padding: 0.875rem 1.25rem;
        font-size: 0.95rem;
    }
    
    .stats-bar {
        padding: 1.25rem 1.5rem;
        border-radius: 16px;
    }
    
    .stats-content {
        gap: 1.5rem;
    }
    
    .stat-item {
        font-size: 0.95rem;
    }
    
    .stat-icon {
        font-size: 1.1rem;
    }
    
    .empty-state {
        padding: 3rem 1.5rem;
        border-radius: 20px;
    }
    
    .empty-icon {
        font-size: 4rem;
    }
    
    .empty-title {
        font-size: 1.5rem;
    }
    
    .empty-message {
        font-size: 1rem;
    }
    
    .pagination-modern {
        flex-wrap: wrap;
        justify-content: center;
    }
    
    .page-link-modern {
        min-width: 45px;
        height: 45px;
        padding: 0 0.875rem;
        font-size: 0.95rem;
    }
    
    /* Show scroll to top button on tablet */
    .scroll-to-top {
        bottom: 25px;
        right: 25px;
        width: 45px;
        height: 45px;
        font-size: 1.3rem;
    }
}

/* Small Mobile (max-width: 576px) */
@media (max-width: 576px) {
    .content-wrapper {
        padding: 1rem 0.5rem;
    }
    
    .page-header {
        padding: 1.25rem 1rem;
        border-radius: 16px;
        margin-bottom: 1.25rem;
    }
    
    .page-title {
        font-size: 1.5rem;
        gap: 0.75rem;
    }
    
    .title-icon {
        width: 40px;
        height: 40px;
        font-size: 1.1rem;
        border-radius: 12px;
    }
    
    .search-container {
        padding: 1.25rem 1rem;
        border-radius: 14px;
        margin-bottom: 1.25rem;
        position: sticky;
        top: 70px;
        z-index: 100;
    }
    
    .search-input {
        font-size: 0.9rem;
        padding: 0.75rem 1rem;
        padding-right: 50px;
        border-radius: 40px;
    }
    
    .search-btn {
        width: 36px;
        height: 36px;
        right: 5px;
    }
    
    .search-btn:hover {
        transform: translateY(-50%);
    }
    
    /* Horizontal Card Layout for Mobile */
    .products-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
        margin-bottom: 1.5rem;
    }
    
    .product-card {
        border-radius: 16px;
        display: flex;
        flex-direction: row;
        height: auto;
        min-height: 140px;
    }
    
    .product-image-container {
        width: 120px;
        height: 140px;
        flex-shrink: 0;
    }
    
    .product-content {
        padding: 1rem;
        flex-grow: 1;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }
    
    .product-title {
        font-size: 1.1rem;
        margin-bottom: 0.25rem;
        line-height: 1.2;
    }
    
    .product-category {
        font-size: 0.8rem;
        padding: 0.3rem 0.6rem;
        margin: 0 0 0.75rem 0;
        border-radius: 10px;
        display: inline-block;
        align-self: flex-start;
    }
    
    .detail-btn {
        padding: 0.6rem 1rem;
        font-size: 0.85rem;
        border-radius: 30px;
        gap: 0.3rem;
    }
    
    .detail-btn i:last-child {
        display: none; /* Hide arrow icon on small mobile */
    }
    
    .stats-bar {
        padding: 1rem;
        border-radius: 12px;
        margin-bottom: 1.25rem;
    }
    
    .stats-content {
        flex-direction: column;
        gap: 0.75rem;
    }
    
    .stat-item {
        font-size: 0.85rem;
        width: 100%;
        justify-content: center;
    }
    
    .stat-icon {
        font-size: 1rem;
    }
    
    .empty-state {
        padding: 2.5rem 1.25rem;
        border-radius: 16px;
    }
    
    .empty-icon {
        font-size: 3.5rem;
        margin-bottom: 1rem;
    }
    
    .empty-title {
        font-size: 1.35rem;
        margin-bottom: 0.75rem;
    }
    
    .empty-message {
        font-size: 0.9rem;
        line-height: 1.5;
    }
    
    .empty-action {
        padding: 0.65rem 1.5rem;
        font-size: 0.9rem;
    }
    
    .pagination-container {
        margin-top: 2rem;
    }
    
    .pagination-modern {
        gap: 0.3rem;
    }
    
    .page-link-modern {
        min-width: 40px;
        height: 40px;
        padding: 0 0.75rem;
        font-size: 0.85rem;
        border-radius: 12px;
    }
    
    /* Show scroll to top button on mobile */
    .scroll-to-top {
        bottom: 20px;
        right: 20px;
        width: 45px;
        height: 45px;
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
    .page-header {
        padding: 1rem;
    }
    
    .page-title {
        font-size: 1.35rem;
    }
    
    .title-icon {
        width: 36px;
        height: 36px;
        font-size: 1rem;
    }
    
    .search-container {
        padding: 1rem;
    }
    
    .search-input {
        font-size: 0.85rem;
        padding: 0.65rem 0.9rem;
        padding-right: 45px;
    }
    
    .search-btn {
        width: 33px;
        height: 33px;
        font-size: 0.9rem;
    }
    
    .product-card {
        min-height: 130px;
    }
    
    .product-image-container {
        width: 100px;
        height: 130px;
    }
    
    .product-content {
        padding: 0.875rem;
    }
    
    .product-title {
        font-size: 1rem;
    }
    
    .product-category {
        font-size: 0.75rem;
        padding: 0.25rem 0.5rem;
    }
    
    .detail-btn {
        padding: 0.5rem 0.875rem;
        font-size: 0.8rem;
    }
    
    .stat-item {
        font-size: 0.8rem;
    }
    
    .page-link-modern {
        min-width: 36px;
        height: 36px;
        padding: 0 0.6rem;
        font-size: 0.8rem;
    }
    
    .scroll-to-top {
        bottom: 15px;
        right: 15px;
        width: 40px;
        height: 40px;
        font-size: 1.1rem;
    }
}

/* Landscape Mode Adjustments */
@media (max-width: 768px) and (orientation: landscape) {
    .content-wrapper {
        padding: 1rem 0.5rem;
    }
    
    .page-header {
        padding: 1rem;
        margin-bottom: 1rem;
    }
    
    .page-title {
        font-size: 1.5rem;
    }
    
    .search-container {
        padding: 1rem;
        margin-bottom: 1rem;
        position: static;
    }
    
    .products-grid {
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 1rem;
    }
    
    .product-card {
        flex-direction: column;
    }
    
    .product-image-container {
        width: 100%;
        height: 150px;
    }
    
    .product-content {
        padding: 1rem;
    }
}

/* Touch Device Optimizations */
@media (hover: none) and (pointer: coarse) {
    .search-container:hover {
        transform: none;
    }
    
    .product-card:hover {
        transform: none;
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
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
    
    .detail-btn:hover {
        transform: none;
        box-shadow: 0 4px 15px rgba(40, 167, 69, 0.4);
    }
    
    .detail-btn:active {
        transform: scale(0.95);
        transition: transform 0.1s ease;
    }
    
    .search-btn:hover {
        transform: translateY(-50%);
    }
    
    .search-btn:active {
        transform: translateY(-50%) scale(0.9);
    }
    
    .page-link-modern:hover {
        transform: none;
        background: rgba(255, 255, 255, 0.9);
        color: #2c3e50;
    }
    
    .page-link-modern:active {
        transform: scale(0.95);
        background: var(--accent-gradient);
        color: white;
    }
}

/* Safe Area Insets for Modern Phones */
@supports (padding: max(0px)) {
    .content-wrapper {
        padding-left: max(0.5rem, env(safe-area-inset-left));
        padding-right: max(0.5rem, env(safe-area-inset-right));
        padding-bottom: max(1rem, env(safe-area-inset-bottom));
    }
    
    .scroll-to-top {
        bottom: max(20px, env(safe-area-inset-bottom) + 20px);
        right: max(20px, env(safe-area-inset-right) + 20px);
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
    .detail-btn,
    .page-link-modern,
    .scroll-to-top {
        -webkit-tap-highlight-color: transparent;
    }
    
    body {
        -webkit-font-smoothing: antialiased;
    }
    
    .products-grid {
        -webkit-overflow-scrolling: touch;
    }
}

/* Accessibility Improvements */
@media (prefers-reduced-motion: reduce) {
    * {
        animation: none !important;
        transition: none !important;
    }
    
    .scroll-to-top {
        transition: opacity 0.3s ease !important;
    }
}

/* High Contrast Mode */
@media (prefers-contrast: high) {
    .product-card {
        border: 2px solid currentColor;
    }
    
    .search-input {
        border-width: 3px;
    }
    
    .detail-btn {
        border: 2px solid currentColor;
    }
}
</style>

<!-- Loading Overlay -->
<div class="loading-overlay" id="loadingOverlay">
    <div class="text-center">
        <div class="loading-spinner"></div>
        <div class="loading-text">Memuat...</div>
    </div>
</div>

<!-- Scroll to Top Button (Mobile Only) -->
<button class="scroll-to-top" id="scrollToTop" aria-label="Scroll to top">
    <i class="bi bi-chevron-up"></i>
</button>

<div class="content-wrapper">
    
    <div class="page-header loading">
        <div class="page-title">
            <div class="title-icon">
                <i class="bi bi-box-seam"></i>
            </div>
            <span>Master Barang</span>
        </div>
    </div>

    <div class="search-container loading" style="animation-delay: 0.2s;">
        <form action="" method="GET" class="search-form">
            <input type="text" 
                   class="search-input" 
                   name="search" 
                   placeholder="Cari nama barang..." 
                   value="<?php echo htmlspecialchars($search_query); ?>"
                   autocomplete="off">
            <button class="search-btn" type="submit" aria-label="Search">
                <i class="bi bi-search"></i>
            </button>
        </form>
    </div>

    <?php if (!empty($barangs) || !empty($search_query)): ?>
    <div class="stats-bar loading" style="animation-delay: 0.4s;">
        <div class="stats-content">
            <div class="stat-item">
                <i class="bi bi-box-seam stat-icon"></i>
                <span>Total: <?php echo number_format($total_results); ?> Barang</span>
            </div>
            <?php if (!empty($search_query)): ?>
            <div class="stat-item">
                <i class="bi bi-search stat-icon"></i>
                <span>Hasil: "<?php echo htmlspecialchars($search_query); ?>"</span>
            </div>
            <?php endif; ?>
            <div class="stat-item">
                <i class="bi bi-file-earmark-text stat-icon"></i>
                <span>Hal <?php echo $page; ?> dari <?php echo $total_pages; ?></span>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="products-grid">
        <?php if (empty($barangs)): ?>
            <div class="empty-state loading" style="animation-delay: 0.6s;">
                <div class="empty-icon">
                    <?php if (!empty($search_query)): ?>
                        <i class="bi bi-search"></i>
                    <?php else: ?>
                        <i class="bi bi-inbox"></i>
                    <?php endif; ?>
                </div>
                <h3 class="empty-title">
                    <?php if (!empty($search_query)): ?>
                        Oops! Barang Tidak Ditemukan
                    <?php else: ?>
                        Belum Ada Data Barang
                    <?php endif; ?>
                </h3>
                <p class="empty-message">
                    <?php if (!empty($search_query)): ?>
                        Barang dengan kata kunci "<strong><?php echo htmlspecialchars($search_query); ?></strong>" tidak ditemukan.<br>
                        Coba gunakan kata kunci yang berbeda atau periksa ejaan Anda.
                    <?php else: ?>
                        Saat ini belum ada data barang yang tersedia dalam sistem.<br>
                        Silakan hubungi administrator untuk informasi lebih lanjut.
                    <?php endif; ?>
                </p>
                <?php if (!empty($search_query)): ?>
                <a href="master_barang.php" class="empty-action">
                    <i class="bi bi-arrow-left"></i> Kembali ke Semua Barang
                </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <?php
foreach ($barangs as $index => $barang):
    // Logika untuk menentukan gambar default
    $is_default = empty($barang['gambar']);
    $img_src = $base_url_uploads . htmlspecialchars($is_default ? 'default.png' : $barang['gambar']);
    $img_class = 'product-image' . ($is_default ? ' default-image' : '');
?>
    <div class="product-card loading" style="animation-delay: <?php echo 0.6 + ($index * 0.1); ?>s;">
        <div class="product-image-container">
            <div class="image-skeleton"></div>
            <img src="<?php echo $img_src; ?>"
                 class="<?php echo $img_class; ?>"
                 alt="<?php echo htmlspecialchars($barang['nama_barang']); ?>"
                 loading="lazy"
                 onload="this.previousElementSibling.style.display='none'"
                 onerror="this.onerror=null; this.src='<?php echo $base_url_uploads; ?>default.png'; this.classList.add('default-image'); this.previousElementSibling.style.display='none';">
        </div>
                <div class="product-content">
                    <h5 class="product-title"><?php echo htmlspecialchars($barang['nama_barang']); ?></h5>
                    
                    <div class="product-category">
                        <i class="bi bi-tag-fill"></i> <?php echo htmlspecialchars($barang['nama_kategori'] ?? 'Tidak ada kategori'); ?>
                    </div>
                    
                    <div class="product-actions">
                        <a href="detail_barang.php?id=<?php echo $barang['id_barang']; ?>" class="detail-btn" data-id="<?php echo $barang['id_barang']; ?>">
                            <i class="bi bi-eye-fill"></i>
                            <span>Lihat Detail</span>
                            <i class="bi bi-arrow-right-circle"></i>
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php if ($total_pages > 1): ?>
    <div class="pagination-container loading" style="animation-delay: 1s;">
        <ul class="pagination-modern">
            <?php
                $url_params = [];
                if (!empty($search_query)) $url_params['search'] = $search_query;
            ?>

            <li class="page-item-modern <?php if($page <= 1){ echo 'disabled'; } ?>">
                <a class="page-link-modern" href="?<?php echo http_build_query(array_merge($url_params, ['page' => $page - 1])); ?>" aria-label="Previous">
                    <i class="bi bi-chevron-left"></i>
                </a>
            </li>

            <?php
            // Mobile: Show less page numbers
            $is_mobile = isset($_SERVER['HTTP_USER_AGENT']) && preg_match('/Mobile|Android|iPhone/i', $_SERVER['HTTP_USER_AGENT']);
            $range = $is_mobile ? 1 : 2;
            
            $start = max(1, $page - $range);
            $end = min($total_pages, $page + $range);
            
            if ($start > 1) {
                echo '<li class="page-item-modern"><a class="page-link-modern" href="?' . http_build_query(array_merge($url_params, ['page' => 1])) . '">1</a></li>';
                if ($start > 2) {
                    echo '<li class="page-item-modern disabled"><span class="page-link-modern">...</span></li>';
                }
            }
            
            for ($i = $start; $i <= $end; $i++):
            ?>
            <li class="page-item-modern <?php if ($i == $page) echo 'active'; ?>">
                <a class="page-link-modern" href="?<?php echo http_build_query(array_merge($url_params, ['page' => $i])); ?>">
                    <?php echo $i; ?>
                </a>
            </li>
            <?php
            endfor;
            
            if ($end < $total_pages) {
                if ($end < $total_pages - 1) {
                    echo '<li class="page-item-modern disabled"><span class="page-link-modern">...</span></li>';
                }
                echo '<li class="page-item-modern"><a class="page-link-modern" href="?' . http_build_query(array_merge($url_params, ['page' => $total_pages])) . '">' . $total_pages . '</a></li>';
            }
            ?>

            <li class="page-item-modern <?php if($page >= $total_pages) { echo 'disabled'; } ?>">
                <a class="page-link-modern" href="?<?php echo http_build_query(array_merge($url_params, ['page' => $page + 1])); ?>" aria-label="Next">
                    <i class="bi bi-chevron-right"></i>
                </a>
            </li>
        </ul>
    </div>
    <?php endif; ?>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Scroll to Top Button Functionality (Mobile Only)
    const scrollToTopBtn = document.getElementById('scrollToTop');
    const isMobile = window.innerWidth <= 768;
    
    if (isMobile && scrollToTopBtn) {
        window.addEventListener('scroll', function() {
            if (window.scrollY > 300) {
                scrollToTopBtn.classList.add('show');
            } else {
                scrollToTopBtn.classList.remove('show');
            }
        });
        
        scrollToTopBtn.addEventListener('click', function() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    }
    
    // Loading Overlay for Page Navigation
    const loadingOverlay = document.getElementById('loadingOverlay');
    const detailButtons = document.querySelectorAll('.detail-btn');
    const paginationLinks = document.querySelectorAll('.page-link-modern');
    
    // Show loading when navigating to detail page
    detailButtons.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            loadingOverlay.classList.add('show');
            setTimeout(() => {
                window.location.href = this.href;
            }, 300);
        });
    });
    
    // Show loading when changing pages
    paginationLinks.forEach(link => {
        if (link.href && !link.parentElement.classList.contains('disabled')) {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                loadingOverlay.classList.add('show');
                setTimeout(() => {
                    window.location.href = this.href;
                }, 300);
            });
        }
    });
    
    // Enhanced Search Functionality
    const searchInput = document.querySelector('.search-input');
    const searchForm = document.querySelector('.search-form');
    
    if (searchInput) {
        // Add clear button for mobile
        if (isMobile && searchInput.value.length > 0) {
            const clearBtn = document.createElement('button');
            clearBtn.type = 'button';
            clearBtn.className = 'clear-search-btn';
            clearBtn.innerHTML = '×';
            clearBtn.style.cssText = `
                position: absolute;
                right: 55px;
                top: 50%;
                transform: translateY(-50%);
                width: 25px;
                height: 25px;
                border: none;
                background: #e0e0e0;
                border-radius: 50%;
                font-size: 18px;
                color: #666;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                line-height: 1;
                z-index: 5;
            `;
            
            clearBtn.addEventListener('click', function() {
                searchInput.value = '';
                searchInput.focus();
                this.style.display = 'none';
            });
            
            searchForm.appendChild(clearBtn);
            
            searchInput.addEventListener('input', function() {
                clearBtn.style.display = this.value.length > 0 ? 'flex' : 'none';
            });
        }
        
        // Show loading on search submit
        searchForm.addEventListener('submit', function(e) {
            if (searchInput.value.trim() !== '' || window.location.search.includes('search=')) {
                e.preventDefault();
                loadingOverlay.classList.add('show');
                setTimeout(() => {
                    this.submit();
                }, 300);
            }
        });
    }
    
    // Lazy Loading Enhancement
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    if (img.dataset.src) {
                        img.src = img.dataset.src;
                    }
                    img.classList.add('loaded');
                    observer.unobserve(img);
                }
            });
        }, {
            rootMargin: '50px'
        });
        
        const images = document.querySelectorAll('.product-image');
        images.forEach(img => imageObserver.observe(img));
    }
    
    // Touch Feedback for Mobile
    if ('ontouchstart' in window) {
        const touchElements = document.querySelectorAll('.detail-btn, .search-btn, .page-link-modern, .scroll-to-top');
        touchElements.forEach(elem => {
            elem.addEventListener('touchstart', function() {
                this.style.opacity = '0.8';
            });
            
            elem.addEventListener('touchend', function() {
                this.style.opacity = '1';
            });
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
    }, false);
    
    // Keyboard Shortcuts (Desktop Only)
    if (!isMobile) {
        document.addEventListener('keydown', function(e) {
            // Focus search with / key
            if (e.key === '/') {
                e.preventDefault();
                searchInput.focus();
                searchInput.select();
            }
            
            // Navigate with arrow keys
            if (e.key === 'ArrowLeft' || e.key === 'ArrowRight') {
                const activeLink = document.querySelector('.page-item-modern.active');
                if (activeLink) {
                    const isNext = e.key === 'ArrowRight';
                    const targetLink = isNext ? 
                        activeLink.nextElementSibling?.querySelector('.page-link-modern') :
                        activeLink.previousElementSibling?.querySelector('.page-link-modern');
                    
                    if (targetLink && targetLink.href && !targetLink.parentElement.classList.contains('disabled')) {
                        loadingOverlay.classList.add('show');
                        setTimeout(() => {
                            window.location.href = targetLink.href;
                        }, 300);
                    }
                }
            }
        });
    }
    
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
});
</script>

<?php include_once '../../../templates/footer.php'; ?></parameter>