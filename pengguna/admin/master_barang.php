<?php
$page_title = 'Master Barang';
// Pastikan sesi dimulai dan header admin disertakan
// Ini akan menangani otentikasi dan memuat semua library CSS/JS yang diperlukan
include_once '../../templates/header_admin.php';

// Logika untuk pagination dan pencarian
$limit = 12; // Jumlah kartu per halaman
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

try {
    // --- PERBAIKAN: Menggabungkan Query JOIN dengan Logika Pencarian dan Pagination ---
    
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

$base_url_uploads = $base_url . '/uploads/';
?>

<!-- Add viewport meta tag for proper mobile rendering -->
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">

<style>
/* Modern Master Barang UI - Admin Version */
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
    z-index: 10;
}

.product-image-container {
    width: 100%;
    height: 250px;
    overflow: hidden;
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

/* === BLOK CSS BARU DI SINI === */
.product-image.default-image {
    object-fit: contain; /* 👈 INI SOLUSINYA */
    background-color: #f8f9fa; /* Opsional: Memberi latar belakang abu-abu muda */
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

.page-item-modern.disabled .page-link-modern {
    opacity: 0.5;
    cursor: not-allowed;
    pointer-events: none;
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

/* ========================================= */
/* ENHANCED RESPONSIVE DESIGN FOR MOBILE */
/* ========================================= */

/* Large Tablet (max-width: 1024px) */
@media (max-width: 1024px) {
    .products-grid {
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 1.5rem;
    }
    
    .content-wrapper {
        padding: 0 1rem;
    }
}

/* Tablet (max-width: 768px) */
@media (max-width: 768px) {
    .modern-master-container {
        padding: 1.5rem 0;
    }
    
    .content-wrapper {
        padding: 0 0.75rem;
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
        font-size: 3.5rem;
    }
    
    .empty-title {
        font-size: 1.35rem;
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
    .modern-master-container {
        padding: 1rem 0;
    }
    
    .content-wrapper {
        padding: 0 0.5rem;
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
        display: none;
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
        padding: 2rem 1.25rem;
        border-radius: 16px;
    }
    
    .empty-icon {
        font-size: 3rem;
        margin-bottom: 1rem;
    }
    
    .empty-title {
        font-size: 1.25rem;
        margin-bottom: 0.75rem;
    }
    
    .empty-message {
        font-size: 0.9rem;
        line-height: 1.5;
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
    
    .back-to-top {
        width: 40px;
        height: 40px;
        bottom: 1rem;
        right: 1rem;
        font-size: 1.2rem;
    }
    
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
}

/* Landscape Mode Adjustments */
@media (max-width: 768px) and (orientation: landscape) {
    .modern-master-container {
        padding: 1rem 0;
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
        background: linear-gradient(45deg, #667eea, #764ba2);
        color: white;
    }
}

/* Safe Area Insets for Modern Phones */
@supports (padding: max(0px)) {
    .modern-master-container {
        padding-left: env(safe-area-inset-left);
        padding-right: env(safe-area-inset-right);
        padding-bottom: env(safe-area-inset-bottom);
    }
    
    .content-wrapper {
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
    .detail-btn,
    .page-link-modern {
        -webkit-tap-highlight-color: transparent;
    }
    
    body {
        -webkit-font-smoothing: antialiased;
    }
    
    .modern-master-container {
        -webkit-overflow-scrolling: touch;
    }
}

/* Print Styles */
@media print {
    .search-container,
    .pagination-container,
    .stats-bar,
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
}

/* Accessibility Improvements */
@media (prefers-reduced-motion: reduce) {
    * {
        animation: none !important;
        transition: none !important;
    }
    
    html {
        scroll-behavior: auto;
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

/* Dark Mode Support (optional) */
@media (prefers-color-scheme: dark) {
    /* Add dark mode styles here if needed */
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
                            Belum ada data barang dalam sistem.
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
<div class="product-card loading" style="animation-delay: <?php echo 0.6 + ($index * 0.1); ?>s;">
    <div class="product-image-container">
        <img src="<?php echo $img_src; ?>" 
             class="<?php echo $img_class; ?>" 
             alt="<?php echo htmlspecialchars($barang['nama_barang']); ?>"
             loading="lazy"
             onerror="this.onerror=null; this.src='<?php echo $base_url_uploads; ?>default.png'; this.classList.add('default-image');">
    </div>
                    <div class="product-content">
                        <h5 class="product-title"><?php echo htmlspecialchars($barang['nama_barang']); ?></h5>
                        
                        <div class="product-category">
                            <?php echo htmlspecialchars($barang['nama_kategori'] ?? 'Tidak ada kategori'); ?>
                        </div>
                        
                        <div class="product-actions">
                            <a href="detail_barang.php?id_barang=<?php echo $barang['id_barang']; ?>" class="detail-btn">
                                <i class="bi bi-eye-fill"></i>
                                <span>Lihat Detail & Target</span>
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
                        echo '<li class="page-item-modern"><span class="page-link-modern">...</span></li>';
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
                        echo '<li class="page-item-modern"><span class="page-link-modern">...</span></li>';
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
</div>

<!-- Back to Top Button -->
<button class="back-to-top" id="backToTopBtn" aria-label="Kembali ke atas">
    <i class="bi bi-arrow-up"></i>
</button>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Loading animation
    const elements = document.querySelectorAll('.loading');
    elements.forEach((el, index) => {
        el.style.animationDelay = el.style.animationDelay || (index * 0.1) + 's';
    });
    
    // --- MULAI KODE BACK TO TOP ---
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
    // --- AKHIR KODE BACK TO TOP ---
    
    // Enhanced search functionality
    const searchInput = document.querySelector('.search-input');
    if (searchInput) {
        // Focus effect for desktop only
        if (window.innerWidth > 768) {
            searchInput.addEventListener('focus', function() {
                this.parentElement.style.transform = 'scale(1.02)';
            });
            
            searchInput.addEventListener('blur', function() {
                this.parentElement.style.transform = 'scale(1)';
            });
        }
        
        // Clear button functionality for mobile
        if (searchInput.value.length > 0 && window.innerWidth <= 768) {
            const clearBtn = document.createElement('button');
            clearBtn.type = 'button';
            clearBtn.className = 'clear-search-btn';
            clearBtn.innerHTML = '×';
            clearBtn.style.cssText = `
                position: absolute;
                right: 60px;
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
            `;
            
            clearBtn.addEventListener('click', function() {
                searchInput.value = '';
                searchInput.focus();
                this.style.display = 'none';
            });
            
            searchInput.parentElement.appendChild(clearBtn);
            
            searchInput.addEventListener('input', function() {
                clearBtn.style.display = this.value.length > 0 ? 'flex' : 'none';
            });
        }
    }
    
    // Card hover effects enhancement (desktop only)
    if (window.innerWidth > 768) {
        const productCards = document.querySelectorAll('.product-card');
        productCards.forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.zIndex = '10';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.zIndex = '1';
            });
        });
    }
    
    // Touch feedback for mobile
    if ('ontouchstart' in window) {
        const touchElements = document.querySelectorAll('.detail-btn, .search-btn, .page-link-modern, .back-to-top');
        touchElements.forEach(elem => {
            elem.addEventListener('touchstart', function() {
                this.style.opacity = '0.7';
            });
            
            elem.addEventListener('touchend', function() {
                this.style.opacity = '1';
            });
        });
    }
    
    // Smooth scrolling for pagination
    const paginationLinks = document.querySelectorAll('.page-link-modern');
    paginationLinks.forEach(link => {
        if (link.href) {
            link.addEventListener('click', function(e) {
                document.body.style.opacity = '0.7';
            });
        }
    });
    
    // Lazy loading for images
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src || img.src;
                    img.classList.add('loaded');
                    observer.unobserve(img);
                }
            });
        });
        
        const images = document.querySelectorAll('.product-image');
        images.forEach(img => imageObserver.observe(img));
    }
    
    // Detect mobile device for better optimization
    function isMobileDevice() {
        return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
    }
    
    // Adjust animations for mobile
    if (isMobileDevice()) {
        document.querySelectorAll('.product-card').forEach(card => {
            card.style.transition = 'transform 0.2s ease';
        });
    }
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
        
        // Navigate with arrow keys
        if (e.key === 'ArrowLeft' || e.key === 'ArrowRight') {
            const activeLink = document.querySelector('.page-item-modern.active');
            if (activeLink) {
                const isNext = e.key === 'ArrowRight';
                const targetLink = isNext ? 
                    activeLink.nextElementSibling?.querySelector('.page-link-modern') :
                    activeLink.previousElementSibling?.querySelector('.page-link-modern');
                
                if (targetLink && targetLink.href) {
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
}, false);

// Optimize scroll performance
let ticking = false;
function requestTick() {
    if (!ticking) {
        window.requestAnimationFrame(updateCards);
        ticking = true;
    }
}

function updateCards() {
    // Add any scroll-based animations here
    ticking = false;
}

// Throttle scroll events for better performance
window.addEventListener('scroll', requestTick, { passive: true });
</script>

<?php
// Memanggil footer
include_once '../../templates/footer.php';
?>