<?php
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'superadmin') {
    header("location: ../auth/login.php");
    exit;
}

$page_title = 'Master Barang';
include '../../templates/header_superadmin.php';
include '../../system/database_connection.php';

// Logika untuk pagination dan pencarian
$limit = 12; // 4 kartu per baris
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

try {
    // --- PERUBAHAN 1: Menggabungkan Query JOIN dengan Logika Pencarian dan Pagination ---
    
    // Base SQL dengan JOIN
    $base_sql = "FROM master_barang mb LEFT JOIN master_kategori mk ON mb.id_kategori = mk.id_kategori";
    $params = [];

    if (!empty($search_query)) {
        // Menambahkan kondisi pencarian
        $base_sql .= " WHERE mb.nama_barang LIKE :search";
        $params[':search'] = "%" . $search_query . "%";
    }

    // Query untuk menghitung total hasil
    $count_sql = "SELECT COUNT(mb.id_barang) " . $base_sql;
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_results = $count_stmt->fetchColumn();
    $total_pages = ceil($total_results / $limit);

    // Query utama untuk mengambil data barang dengan JOIN, search, dan limit
    $sql = "SELECT mb.*, mk.nama_kategori " . $base_sql . " ORDER BY mb.id_barang DESC LIMIT :limit OFFSET :offset";
    
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
?>

<style>
/* Modern Master Barang UI */
.modern-master-container {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
    padding: 2rem 0;
}

.content-wrapper {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 1rem;
}

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
    background: linear-gradient(45deg, #667eea, #764ba2);
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

.action-buttons {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
}

.modern-btn {
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 25px;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    white-space: nowrap;
    cursor: pointer;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
}

.btn-success-modern {
    background: linear-gradient(45deg, #4CAF50, #45a049);
    color: white;
}

.btn-success-modern:hover {
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(76, 175, 80, 0.4);
    text-decoration: none;
}

.btn-primary-modern {
    background: linear-gradient(45deg, #2196F3, #1976D2);
    color: white;
}

.btn-primary-modern:hover {
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(33, 150, 243, 0.4);
    text-decoration: none;
}

.search-container {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border-radius: 20px;
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.search-form {
    position: relative;
    max-width: 600px;
    margin: 0 auto;
}

.search-input {
    width: 100%;
    padding: 1rem 1.5rem;
    border: 2px solid rgba(102, 126, 234, 0.3);
    border-radius: 50px;
    font-size: 1rem;
    background: white;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
}

.search-input:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 25px rgba(102, 126, 234, 0.3);
    transform: translateY(-2px);
}

.search-btn {
    position: absolute;
    right: 8px;
    top: 50%;
    transform: translateY(-50%);
    background: linear-gradient(45deg, #667eea, #764ba2);
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

.products-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 2rem;
    margin-bottom: 3rem;
}

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
}

.product-image {
    width: 100%;
    height: 250px;
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
    background: linear-gradient(45deg, #2c3e50, #34495e);
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
    box-shadow: 0 4px 15px rgba(44, 62, 80, 0.4);
}

.detail-btn:hover {
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(44, 62, 80, 0.6);
    text-decoration: none;
}

.empty-state {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border-radius: 25px;
    padding: 4rem 2rem;
    text-align: center;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    grid-column: 1 / -1;
}

.empty-icon {
    font-size: 4rem;
    color: #667eea;
    margin-bottom: 1.5rem;
    opacity: 0.8;
}

.empty-title {
    font-size: 1.5rem;
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 1rem;
}

.empty-message {
    color: #666;
    font-size: 1.1rem;
    margin: 0;
    line-height: 1.6;
}

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
    width: 50px;
    height: 50px;
    border-radius: 15px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
    background: rgba(255, 255, 255, 0.9);
    color: #2c3e50;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
}

.page-link-modern:hover {
    background: linear-gradient(45deg, #667eea, #764ba2);
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
    text-decoration: none;
}

.page-item-modern.active .page-link-modern {
    background: linear-gradient(45deg, #667eea, #764ba2);
    color: white;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
}

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
    color: #667eea;
    font-size: 1.2rem;
}

.back-to-top {
    position: fixed;
    bottom: 30px;
    right: 30px;
    width: 50px;
    height: 50px;
    /* Menggunakan gradient yang sama dengan search-btn di halaman ini */
    background: linear-gradient(45deg, #667eea, #764ba2); 
    color: white;
    border: none;
    border-radius: 50%;
    display: none; /* Mulai tersembunyi */
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    cursor: pointer;
    /* Menggunakan shadow yang sama dengan search-btn di halaman ini */
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4); 
    transition: all 0.3s ease;
    z-index: 1000;
}

.back-to-top.show {
    display: flex; /* Tampilkan saat di-scroll */
}

.back-to-top:hover {
    transform: scale(1.1);
    /* Menggunakan shadow hover yang sama dengan search-btn di halaman ini */
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6); 
}

/* Responsive Design */
@media (max-width: 768px) {
    .content-wrapper {
        padding: 0 0.5rem;
    }
    
    .page-header {
        flex-direction: column;
        text-align: center;
        padding: 1.5rem;
    }
    
    .page-title {
        font-size: 1.8rem;
    }
    
    .action-buttons {
        justify-content: center;
    }
    
    .products-grid {
        grid-template-columns: 1fr;
        gap: 1.5rem;
    }
    
    .search-container {
        padding: 1.5rem;
    }
    
    .stats-content {
        gap: 1rem;
    }
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

/* Hover effect for search */
.search-container:hover {
    transform: translateY(-2px);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
}

/* ============================================
   RESPONSIVE DESIGN UNTUK MASTER BARANG - 2 COLUMN MOBILE
   Tambahkan di akhir CSS yang sudah ada
   ============================================ */

/* Tablet View (768px - 1024px) */
@media (max-width: 1024px) {
    .content-wrapper {
        padding: 0 0.75rem;
    }
    
    .products-grid {
        grid-template-columns: repeat(3, 1fr);
        gap: 1.5rem;
    }
    
    .page-header {
        padding: 1.5rem;
    }
    
    .page-title {
        font-size: 1.75rem;
    }
}

/* Mobile Devices (≤ 768px) - CRITICAL: 2 COLUMN LAYOUT */
@media (max-width: 768px) {
    /* ========== GLOBAL MOBILE SETTINGS ========== */
    body {
        font-size: 14px;
    }
    
    .modern-master-container {
        padding: 1rem 0;
    }
    
    .content-wrapper {
        padding: 0 0.5rem;
    }
    
    /* ========== PAGE HEADER ========== */
    .page-header {
        padding: 1rem;
        border-radius: 15px;
        margin-bottom: 1rem;
        flex-direction: column;
        text-align: center;
        gap: 0.75rem;
    }
    
    .page-title {
        font-size: 1.3rem;
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .title-icon {
        width: 45px;
        height: 45px;
        font-size: 1.3rem;
        border-radius: 12px;
    }
    
    /* ========== ACTION BUTTONS ========== */
    .action-buttons {
        flex-direction: column;
        width: 100%;
        gap: 0.5rem;
    }
    
    .modern-btn {
        width: 100%;
        justify-content: center;
        padding: 0.65rem 1.25rem;
        font-size: 0.85rem;
        border-radius: 20px;
    }
    
    .modern-btn span {
        font-size: 0.85rem;
    }
    
    .modern-btn i {
        font-size: 1rem;
    }
    
    /* ========== SEARCH CONTAINER ========== */
    .search-container {
        padding: 1rem;
        border-radius: 15px;
        margin-bottom: 1rem;
    }
    
    .search-form {
        max-width: 100%;
    }
    
    .search-input {
        font-size: 16px; /* Prevent iOS zoom */
        padding: 0.75rem 3.5rem 0.75rem 1rem;
        border-radius: 25px;
    }
    
    .search-btn {
        width: 40px;
        height: 40px;
        right: 5px;
    }
    
    .search-btn i {
        font-size: 1rem;
    }
    
    /* ========== STATS BAR ========== */
    .stats-bar {
        padding: 1rem;
        border-radius: 15px;
        margin-bottom: 1rem;
    }
    
    .stats-content {
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .stat-item {
        font-size: 0.8rem;
        justify-content: center;
    }
    
    .stat-icon {
        font-size: 1rem;
    }
    
    /* ========== PRODUCTS GRID - 2 COLUMNS FIXED! ========== */
    .products-grid {
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 0.75rem;
        margin-bottom: 2rem;
    }
    
    /* ========== PRODUCT CARD COMPACT ========== */
    .product-card {
        border-radius: 15px;
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
    }
    
    .product-card:hover {
        transform: translateY(-5px) scale(1.01);
    }
    
    /* ========== PRODUCT IMAGE ========== */
    .product-image {
        height: 150px;
        object-fit: cover;
    }
    
    .product-card:hover .product-image {
        transform: scale(1.05);
    }
    
    /* ========== PRODUCT CONTENT ========== */
    .product-content {
        padding: 0.75rem;
    }
    
    /* ========== PRODUCT TITLE ========== */
    .product-title {
        font-size: 0.85rem;
        line-height: 1.3;
        margin-bottom: 0.5rem;
        
        /* Limit to 2 lines with ellipsis */
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        text-overflow: ellipsis;
        min-height: 2.2em;
    }
    
    /* ========== PRODUCT CATEGORY ========== */
    .product-category {
        font-size: 0.7rem;
        padding: 0.35rem 0.75rem;
        margin-bottom: 0.75rem;
        border-radius: 12px;
        
        /* Limit to 1 line with ellipsis */
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    
    /* ========== PRODUCT ACTIONS ========== */
    .product-actions {
        margin-top: 0.5rem;
    }
    
    .detail-btn {
        padding: 0.6rem 0.75rem;
        font-size: 0.75rem;
        border-radius: 25px;
        gap: 0.25rem;
    }
    
    .detail-btn span {
        /* Hide text on very small screens, keep icons */
        display: inline;
        font-size: 0.75rem;
    }
    
    .detail-btn i {
        font-size: 0.85rem;
    }
    
    /* ========== EMPTY STATE ========== */
    .empty-state {
        grid-column: 1 / -1;
        padding: 2rem 1rem;
        border-radius: 15px;
    }
    
    .empty-icon {
        font-size: 3rem;
        margin-bottom: 1rem;
    }
    
    .empty-title {
        font-size: 1.1rem;
        margin-bottom: 0.75rem;
    }
    
    .empty-message {
        font-size: 0.85rem;
        line-height: 1.5;
    }
    
    /* ========== PAGINATION ========== */
    .pagination-container {
        margin-top: 2rem;
    }
    
    .pagination-modern {
        flex-wrap: wrap;
        gap: 0.35rem;
        justify-content: center;
    }
    
    .page-link-modern {
        width: 40px;
        height: 40px;
        border-radius: 12px;
        font-size: 0.85rem;
    }
    
    /* Smaller "..." indicators */
    .page-link-modern:not([href]) {
        width: 30px;
        font-size: 0.75rem;
    }
    
    /* ========== MODAL ========== */
    .modal-dialog {
        margin: 0.5rem;
    }
    
    .modal-content {
        border-radius: 20px;
    }
    
    .modal-header {
        padding: 1rem;
    }
    
    .modal-title {
        font-size: 1rem;
    }
    
    .modal-body {
        padding: 1.5rem 1rem;
    }
    
    .modal-body i {
        font-size: 3rem !important;
    }
    
    .modal-body h5 {
        font-size: 1rem;
        margin-top: 1rem !important;
    }
    
    .modal-body p {
        font-size: 0.85rem;
    }
    
    .modal-footer {
        padding: 1rem;
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .modal-footer .btn {
        width: 100%;
        padding: 0.65rem 1.25rem;
        font-size: 0.85rem;
    }
}

/* Extra Small Devices (≤ 375px) - Still 2 columns! */
@media (max-width: 375px) {
    .content-wrapper {
        padding: 0 0.25rem;
    }
    
    /* CRITICAL: Maintain 2 columns */
    .products-grid {
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 0.5rem;
    }
    
    .page-title {
        font-size: 1.15rem;
    }
    
    .title-icon {
        width: 40px;
        height: 40px;
        font-size: 1.2rem;
    }
    
    .modern-btn {
        padding: 0.6rem 1rem;
        font-size: 0.8rem;
    }
    
    .search-container {
        padding: 0.75rem;
    }
    
    .search-input {
        font-size: 14px;
        padding: 0.65rem 3rem 0.65rem 0.85rem;
    }
    
    .stats-bar {
        padding: 0.75rem;
    }
    
    .stat-item {
        font-size: 0.75rem;
    }
    
    /* Extra compact cards */
    .product-image {
        height: 140px;
    }
    
    .product-content {
        padding: 0.65rem;
    }
    
    .product-title {
        font-size: 0.8rem;
        min-height: 2em;
    }
    
    .product-category {
        font-size: 0.65rem;
        padding: 0.3rem 0.6rem;
        margin-bottom: 0.5rem;
    }
    
    .detail-btn {
        padding: 0.55rem 0.65rem;
        font-size: 0.7rem;
    }
    
    .detail-btn span {
        font-size: 0.7rem;
    }
    
    .detail-btn i {
        font-size: 0.8rem;
    }
    
    .page-link-modern {
        width: 36px;
        height: 36px;
        font-size: 0.8rem;
    }
}

/* Very Small Devices (≤ 320px) - Still 2 columns! */
@media (max-width: 320px) {
    /* CRITICAL: Force 2 columns even on smallest screens */
    .products-grid {
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 0.4rem;
    }
    
    .page-title {
        font-size: 1rem;
    }
    
    .product-image {
        height: 130px;
    }
    
    .product-content {
        padding: 0.5rem;
    }
    
    .product-title {
        font-size: 0.75rem;
        -webkit-line-clamp: 2;
    }
    
    .product-category {
        font-size: 0.6rem;
        padding: 0.25rem 0.5rem;
    }
    
    .detail-btn {
        padding: 0.5rem;
        font-size: 0.65rem;
    }
    
    .detail-btn span {
        /* Show minimal text or hide on very small */
        font-size: 0.65rem;
    }
}

/* Landscape Orientation on Mobile */
@media (max-width: 768px) and (orientation: landscape) {
    /* In landscape, we can show 3 columns */
    .products-grid {
        grid-template-columns: repeat(3, 1fr) !important;
        gap: 0.75rem;
    }
    
    .product-image {
        height: 140px;
    }
    
    .page-header {
        flex-direction: row;
        justify-content: space-between;
    }
    
    .action-buttons {
        flex-direction: row;
        width: auto;
    }
    
    .modern-btn {
        width: auto;
    }
}

/* ============================================
   TOUCH & ACCESSIBILITY ENHANCEMENTS
   ============================================ */
@media (max-width: 768px) {
    /* Minimum touch target size */
    .modern-btn,
    .detail-btn,
    .page-link-modern,
    .search-btn {
        min-height: 44px;
        min-width: 44px;
    }
    
    /* Better form controls */
    input,
    button {
        font-size: 16px; /* Prevents zoom on iOS */
    }
    
    /* Focus states for accessibility */
    .modern-btn:focus,
    .detail-btn:focus,
    .page-link-modern:focus,
    .search-input:focus {
        outline: 3px solid #667eea;
        outline-offset: 2px;
    }
    
    /* Better tap highlighting */
    .product-card,
    .modern-btn,
    .detail-btn,
    .page-link-modern {
        -webkit-tap-highlight-color: rgba(102, 126, 234, 0.2);
    }
    
    /* Smooth scrolling */
    html {
        scroll-behavior: smooth;
    }
    
    /* Improve scrolling performance */
    .products-grid {
        -webkit-overflow-scrolling: touch;
    }
}

/* ============================================
   PERFORMANCE OPTIMIZATION FOR MOBILE
   ============================================ */
@media (max-width: 768px) {
    /* Reduce complex animations on mobile */
    .product-card::before {
        display: none;
    }
    
    /* Simplify hover effects */
    .search-container:hover {
        transform: none;
    }
    
    /* Optimize transitions */
    * {
        transition-duration: 0.2s !important;
    }
    
    /* Reduce backdrop blur for performance */
    .page-header,
    .search-container,
    .stats-bar,
    .product-card,
    .empty-state {
        backdrop-filter: blur(5px);
    }
    
    /* Disable hover effects on touch devices */
    @media (hover: none) {
        .product-card:hover,
        .modern-btn:hover,
        .detail-btn:hover {
            transform: none;
            box-shadow: initial;
        }
        
        .product-card:hover .product-image {
            transform: none;
        }
    }
}

/* ============================================
   LOADING ANIMATION ADJUSTMENTS
   ============================================ */
@media (max-width: 768px) {
    .loading {
        animation-duration: 0.4s;
    }
    
    /* Stagger animations faster on mobile */
    .product-card.loading {
        animation-delay: 0s !important;
    }
}

/* ============================================
   UTILITY CLASSES FOR MOBILE
   ============================================ */
@media (max-width: 768px) {
    .mobile-stack {
        flex-direction: column !important;
    }
    
    .mobile-full-width {
        width: 100% !important;
    }
    
    .mobile-text-center {
        text-align: center !important;
    }
    
    .mobile-hidden {
        display: none !important;
    }
    
    /* Two column grid helper */
    .mobile-2-col {
        grid-template-columns: repeat(2, 1fr) !important;
    }
}

/* ============================================
   PRINT STYLES (Bonus)
   ============================================ */
@media print {
    .page-header .action-buttons,
    .search-container,
    .stats-bar,
    .pagination-container,
    .modal {
        display: none !important;
    }
    
    .products-grid {
        grid-template-columns: repeat(3, 1fr) !important;
        gap: 1rem;
    }
    
    .product-card {
        page-break-inside: avoid;
        box-shadow: none;
        border: 1px solid #ddd;
    }
    
    .modern-master-container {
        background: white !important;
    }
}

/* ============================================
   DARK MODE SUPPORT (Optional Enhancement)
   ============================================ */
@media (prefers-color-scheme: dark) and (max-width: 768px) {
    /* Uncomment if you want dark mode support */
    /*
    .modern-master-container {
        background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
    }
    
    .page-header,
    .search-container,
    .stats-bar,
    .product-card,
    .empty-state {
        background: rgba(30, 30, 30, 0.95);
        color: #e0e0e0;
    }
    
    .product-title {
        color: #e0e0e0;
    }
    
    .product-category {
        background: linear-gradient(45deg, rgba(102, 126, 234, 0.2), rgba(118, 75, 162, 0.2));
        color: #e0e0e0;
    }
    */
}

/* ============================================
   SPECIAL: Button Text Visibility Control
   ============================================ */
@media (max-width: 480px) {
    /* Option 1: Shorten button text */
    .detail-btn span {
        /* You can change text via JS or use ::before/::after */
    }
    
    /* Option 2: Icon only mode for very small screens */
    @media (max-width: 360px) {
        .detail-btn span {
            /* Uncomment to hide text completely */
            /* display: none; */
        }
        
        .detail-btn {
            gap: 0;
            padding: 0.5rem 0.65rem;
        }
    }
}

</style>

<div class="modern-master-container">
    <div class="content-wrapper">
        
        <div class="page-header loading">
            <div class="page-title">
                <div class="title-icon">
                    <i class="bi bi-box-seam"></i>
                </div>
                <span>Master Barang</span>
            </div>
            <div class="action-buttons">
                <button type="button" class="modern-btn btn-success-modern" data-bs-toggle="modal" data-bs-target="#konfirmasiTambahModal">
                    <i class="bi bi-plus-circle"></i>
                    <span>Tambah Barang Baru</span>
                </button>
                <a href="master_data/kelola_master_barang.php" class="modern-btn btn-primary-modern">
                    <i class="bi bi-pencil-square"></i>
                    <span>Kelola Master Barang</span>
                </a>
            </div>
        </div>

        <div class="search-container loading" style="animation-delay: 0.2s;">
            <form action="" method="GET" class="search-form">
                <input type="text" 
                       class="search-input" 
                       name="search" 
                       placeholder="🔍 Cari nama barang..." 
                       value="<?php echo htmlspecialchars($search_query); ?>"
                       autocomplete="off">
                <button class="search-btn" type="submit">
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
                    <span>Hasil pencarian: "<?php echo htmlspecialchars($search_query); ?>"</span>
                </div>
                <?php endif; ?>
                <div class="stat-item">
                    <i class="bi bi-file-earmark-text stat-icon"></i>
                    <span>Halaman <?php echo $page; ?> dari <?php echo $total_pages; ?></span>
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
                            <i class="bi bi-box"></i>
                        <?php endif; ?>
                    </div>
                    <h3 class="empty-title">
                        <?php if (!empty($search_query)): ?>
                            Barang Tidak Ditemukan
                        <?php else: ?>
                            Belum Ada Data Barang
                        <?php endif; ?>
                    </h3>
                    <p class="empty-message">
                        <?php if (!empty($search_query)): ?>
                            Barang dengan nama "<?php echo htmlspecialchars($search_query); ?>" tidak ditemukan.<br>
                            Coba gunakan kata kunci yang berbeda atau periksa ejaan.
                        <?php else: ?>
                            Belum ada data barang dalam sistem.<br>
                            Silakan tambahkan barang baru untuk memulai.
                        <?php endif; ?>
                    </p>
                </div>
            <?php else: ?>
    <?php
    foreach ($barangs as $index => $barang):
        // Logika untuk menentukan gambar default
        $is_default = empty($barang['gambar']);
        $img_src = '../../uploads/' . htmlspecialchars($is_default ? 'default.png' : $barang['gambar']);
        $img_class = 'product-image' . ($is_default ? ' default-image' : '');
    ?>
    <div class="product-card loading" style="animation-delay: <?php echo 0.6 + ($index * 0.1); ?>s;">
        <img src="<?php echo $img_src; ?>"
             class="<?php echo $img_class; ?>"
             alt="<?php echo htmlspecialchars($barang['nama_barang']); ?>"
             loading="lazy"
             onerror="this.onerror=null; this.src='../../uploads/default.png'; this.classList.add('default-image');">
        <div class="product-content">
                        <h5 class="product-title"><?php echo htmlspecialchars($barang['nama_barang']); ?></h5>
                        
                        <div class="product-category">
                            <?php echo htmlspecialchars($barang['nama_kategori'] ?? 'Tidak ada kategori'); ?>
                        </div>
                        
                        <div class="product-actions">
                            <a href="manajemen_produksi/detail_barang.php?id=<?php echo $barang['id_barang']; ?>" class="detail-btn">
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
                
                <?php if ($page > 1): ?>
                <li class="page-item-modern">
                    <a class="page-link-modern" href="?<?php echo http_build_query(array_merge($url_params, ['page' => $page - 1])); ?>">
                        <i class="bi bi-chevron-left"></i>
                    </a>
                </li>
                <?php endif; ?>
                
                <?php
                $start = max(1, $page - 2);
                $end = min($total_pages, $page + 2);
                
                if ($start > 1) {
                    echo '<li class="page-item-modern"><a class="page-link-modern" href="?' . http_build_query(array_merge($url_params, ['page' => 1])) . '">1</a></li>';
                    if ($start > 2) {
                        echo '<li class="page-item-modern"><span class="page-link-modern">...</span></li>';
                    }
                }
                
                for ($i = $start; $i <= $end; $i++):
                ?>
                <li class="page-item-modern <?php if ($i == $page) echo 'active'; ?>">
                    <a class="page-link-modern" href="?<?php echo http_build_query(array_merge($url_params, ['page' => $i])); ?>"><?php echo $i; ?></a>
                </li>
                <?php
                endfor;
                
                if ($end < $total_pages) {
                    if ($end < $total_pages - 1) {
                        echo '<li class="page-item-modern"><span class="page-link-modern">...</span></li>';
                    }
                    echo '<li class="page-item-modern"><a class="page-link-modern" href="?' . http_build_query(array_merge($url_params, ['page' => $total_pages])) . '">' . $total_pages . '</a></li>';
                }
                ?>
                
                <?php if ($page < $total_pages): ?>
                <li class="page-item-modern">
                    <a class="page-link-modern" href="?<?php echo http_build_query(array_merge($url_params, ['page' => $page + 1])); ?>">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="konfirmasiTambahModal" tabindex="-1" aria-labelledby="konfirmasiTambahModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="konfirmasiTambahModalLabel">
                    <i class="bi bi-box-arrow-in-right me-2"></i> Konfirmasi Navigasi
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center py-4">
                <i class="bi bi-question-circle-fill text-primary" style="font-size: 4rem;"></i>
                <h5 class="mt-3">Pindah ke Halaman Kelola Master Barang?</h5>
                <p class="text-muted">
                    Anda akan diarahkan ke halaman lain untuk menambahkan data barang baru. Apakah Anda ingin melanjutkan?
                </p>
            </div>
            <div class="modal-footer border-0 justify-content-center">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius: 50px; padding: 0.75rem 1.5rem;">
                    <i class="bi bi-x-lg me-1"></i> Batal
                </button>
                <a href="master_data/kelola_master_barang.php?action=add" class="btn btn-primary" style="border-radius: 50px; padding: 0.75rem 1.5rem;">
    <i class="bi bi-check-lg me-1"></i> Ya, Lanjutkan
</a>
            </div>
        </div>
    </div>
</div>

<button class="back-to-top" id="backToTop" onclick="scrollToTop()">
    <i class="bi bi-arrow-up"></i>
</button>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Loading animation
    const elements = document.querySelectorAll('.loading');
    elements.forEach((el, index) => {
        el.style.animationDelay = el.style.animationDelay || (index * 0.1) + 's';
    });
    
    // PERUBAHAN 3: Kode JavaScript untuk konfirmasi lama telah dihapus.
    // Kode di bawah ini adalah fungsionalitas lain yang sudah ada sebelumnya.
    
    // Enhanced search functionality
    const searchInput = document.querySelector('.search-input');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(() => {
                // Auto-submit after 1 second of no typing (optional)
                // this.form.submit();
            }, 1000);
        });
        
        // Focus effect
        searchInput.addEventListener('focus', function() {
            this.parentElement.style.transform = 'scale(1.02)';
        });
        
        searchInput.addEventListener('blur', function() {
            this.parentElement.style.transform = 'scale(1)';
        });
    }
    
    // Card hover effects enhancement
    const productCards = document.querySelectorAll('.product-card');
    productCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.zIndex = '10';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.zIndex = '1';
        });
    });
    
    // Smooth scrolling for pagination
    const paginationLinks = document.querySelectorAll('.page-link-modern');
    paginationLinks.forEach(link => {
        if (link.href) {
            link.addEventListener('click', function(e) {
                // Add loading effect while navigating
                document.body.style.opacity = '0.7';
            });
        }
    });
    
    // Image error handling
    const images = document.querySelectorAll('.product-image');
    images.forEach(img => {
        img.addEventListener('error', function() {
            this.src = '../../uploads/default.jpg';
            this.style.opacity = '0.7';
        });
        
        img.addEventListener('load', function() {
            this.style.opacity = '1';
        });
    });
});

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Focus search with Ctrl+F or /
    if ((e.ctrlKey && e.key === 'f') || e.key === '/') {
        e.preventDefault();
        const searchInput = document.querySelector('.search-input');
        if (searchInput) {
            searchInput.focus();
        }
    }
});

window.addEventListener('scroll', function() {
    const backToTop = document.getElementById('backToTop');
    if (window.scrollY > 300) {
        backToTop.classList.add('show');
    } else {
        backToTop.classList.remove('show');
    }
});

function scrollToTop() {
    window.scrollTo({
        top: 0,
        behavior: 'smooth'
    });
}

</script>

<?php include '../../templates/footer.php'; ?>