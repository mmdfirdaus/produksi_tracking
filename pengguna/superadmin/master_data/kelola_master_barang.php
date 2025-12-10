<?php
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'superadmin') {
    header("location: ../../auth/login.php");
    exit;
}

$page_title = 'Kelola Master Barang';
include '../../../templates/header_superadmin.php';
include '../../../system/database_connection.php';

// ### PERUBAHAN BARU 1: Ambil data kategori untuk dropdown ###
$kategori_stmt = $pdo->query("SELECT id_kategori, nama_kategori FROM master_kategori ORDER BY nama_kategori ASC");
$kategori_options = $kategori_stmt->fetchAll(PDO::FETCH_ASSOC);

// Ambil semua alur untuk form
$alurs_stmt = $pdo->query("SELECT id_alur, nama_alur FROM master_alur ORDER BY urutan ASC");
$all_alurs = $alurs_stmt->fetchAll(PDO::FETCH_ASSOC);

// Logika pagination dan pencarian
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

try {
    // ### PERUBAHAN BARU 2: Menggunakan LEFT JOIN dan menyesuaikan query pencarian ###
    $base_sql = "FROM master_barang mb LEFT JOIN master_kategori mk ON mb.id_kategori = mk.id_kategori ";
    $params = [];
    if (!empty($search_query)) {
        // Pencarian sekarang berdasarkan nama barang atau nama kategori
        $base_sql .= " WHERE mb.nama_barang LIKE :search OR mk.nama_kategori LIKE :search";
        $params[':search'] = "%" . $search_query . "%";
    }

    $count_stmt = $pdo->prepare("SELECT COUNT(mb.id_barang) " . $base_sql);
    $count_stmt->execute($params);
    $total_results = $count_stmt->fetchColumn();
    $total_pages = ceil($total_results / $limit);
    
    // Query utama untuk mengambil data barang beserta nama kategorinya
    $barang_stmt = $pdo->prepare("SELECT mb.*, mk.nama_kategori " . $base_sql . " ORDER BY mb.created_at DESC LIMIT :limit OFFSET :offset");
    if (!empty($search_query)) {
        $barang_stmt->bindParam(':search', $params[':search']);
    }
    $barang_stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $barang_stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $barang_stmt->execute();
    $barangs = $barang_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>

<style>
/* ==================== BASE STYLES (DESKTOP) ==================== */
.main-card { background: linear-gradient(145deg, #ffffff, #f8f9fa); border: none; border-radius: 20px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08); }
.card-header-custom { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; border-radius: 20px 20px 0 0; padding: 25px 30px; }
.card-header-custom h6 { color: white; font-weight: 600; font-size: 1.1rem; margin: 0; }
.search-container { background: #f8f9fa; border-radius: 15px; padding: 20px; margin-bottom: 25px; border: 1px solid #e9ecef; }
.search-input { border: 2px solid #e9ecef; border-radius: 50px; padding: 12px 20px; font-size: 14px; background: white; }
.search-input:focus { border-color: #667eea; box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25); outline: none; }
.search-btn { border: none; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 50px; padding: 12px 25px; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3); }
.btn-add-new { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); border: none; border-radius: 50px; padding: 12px 25px; color: white; font-weight: 500; box-shadow: 0 4px 15px rgba(17, 153, 142, 0.3); }
.table-modern { border-radius: 15px; overflow: hidden; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05); border: none; }
.table-modern thead th { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; font-weight: 600; text-transform: uppercase; font-size: 0.8rem; border: none; padding: 20px 15px; }
.table-modern tbody td { padding: 20px 15px; vertical-align: middle; border-bottom: 1px solid #f1f3f4; }
.table-modern tbody tr:last-child td { border-bottom: none; }
.product-image { width: 80px; height: 80px; object-fit: cover; border-radius: 15px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15); }
.product-image.default-image {
    object-fit: contain; /* 👈 SOLUSI TABEL */
    background-color: #f8f9fa; 
}
.category-badge { background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%); color: #8b4513; padding: 8px 15px; border-radius: 25px; font-size: 0.85rem; font-weight: 500; text-transform: capitalize; }
.alur-tags { display: flex; flex-wrap: wrap; gap: 5px; }
.alur-tag { background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%); color: #495057; padding: 5px 12px; border-radius: 15px; font-size: 0.75rem; font-weight: 500; }
.btn-edit-modern { background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%); border: none; color: #8b4513; padding: 8px 16px; border-radius: 25px; font-size: 0.85rem; font-weight: 500; box-shadow: 0 3px 10px rgba(252, 182, 159, 0.3); }
.btn-info-modern { 
    background: linear-gradient(135deg, #0dcaf0, #0a9ebf); 
    border: none; 
    color: white; 
    padding: 8px 16px; 
    border-radius: 25px; 
    font-size: 0.85rem; 
    font-weight: 500; 
    box-shadow: 0 3px 10px rgba(13, 202, 240, 0.3); 
    text-decoration: none;
    display: inline-block;
    vertical-align: middle;
}
.btn-info-modern:hover {
    color: white;
    transform: translateY(-1px);
}
.btn-delete-modern { background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%); border: none; color: #8b5a5a; padding: 12px 30px; border-radius: 25px; font-weight: 500; box-shadow: 0 4px 15px rgba(255, 154, 158, 0.3); }
.pagination-modern .page-link { border: none; color: #667eea; background: white; margin: 0 3px; border-radius: 12px; padding: 12px 18px; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1); }
.pagination-modern .page-item.active .page-link { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3); }
.modal-modern .modal-content { border: none; border-radius: 25px; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2); }
.modal-modern .modal-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 25px 25px 0 0; padding: 25px 30px; }
.modal-modern .modal-title { font-weight: 600; font-size: 1.2rem; }
.modal-modern .modal-body { padding: 30px; }
.form-label-modern { font-weight: 600; color: #495057; margin-bottom: 8px; font-size: 0.9rem; }
.form-control-modern { border: 2px solid #e9ecef; border-radius: 12px; padding: 12px 18px; font-size: 0.9rem; }
.form-control-modern:focus { border-color: #667eea; box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25); }
.checkbox-modern { background: #f8f9fa; border-radius: 12px; padding: 15px; margin-bottom: 10px; border: 2px solid transparent; }
.checkbox-modern .form-check-input:checked { background-color: #667eea; border-color: #667eea; }
.btn-save-modern { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); border: none; color: white; padding: 12px 30px; border-radius: 25px; font-weight: 500; box-shadow: 0 4px 15px rgba(17, 153, 142, 0.3); }
.empty-state { text-align: center; padding: 60px 20px; color: #6c757d; }
.empty-state i { font-size: 4rem; color: #e9ecef; margin-bottom: 20px; }

/* Desktop: Table View */
.table-view { display: block; }
.card-view { display: none; }

/* Card View Styles (Hidden on Desktop, Shown on Mobile) */
.products-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.product-card {
    background: white;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
    transition: all 0.3s ease;
    position: relative;
}

.product-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
}

.product-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #667eea, #764ba2);
    opacity: 0;
    transition: opacity 0.3s ease;
}

.product-card:hover::before {
    opacity: 1;
}

.product-card-image-container {
    position: relative;
    height: 200px;
    overflow: hidden;
    background: linear-gradient(135deg, #f3f4f6, #e5e7eb);
}

.product-card-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.4s ease;
}

.product-card:hover .product-card-image {
    transform: scale(1.1);
}
.product-card-image.default-image {
    object-fit: contain; /* 👈 SOLUSI KARTU */
    background-color: #f8f9fa;
}

/* Nonaktifkan efek zoom-on-hover HANYA untuk gambar default */
.product-card:hover .product-card-image.default-image {
    transform: scale(1);
}

.product-card-content {
    padding: 1.5rem;
}

.product-card-title {
    font-size: 1.1rem;
    font-weight: 700;
    color: #1f2937;
    margin-bottom: 0.75rem;
    line-height: 1.3;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.product-card-category {
    margin-bottom: 1rem;
}

.product-card-alur {
    margin-bottom: 1.25rem;
    min-height: 40px;
}

.product-card-actions {
    display: flex;
    gap: 0.5rem;
}

.product-card-actions .btn {
    flex: 1;
    padding: 0.75rem;
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

/* ==================== RESPONSIVE STYLES FOR MOBILE ==================== */

/* Tablet (max-width: 992px) */
@media (max-width: 992px) {
    .card-header-custom {
        padding: 20px;
    }
    
    .card-header-custom h6 {
        font-size: 1rem;
    }
    
    .table-modern thead th {
        font-size: 0.75rem;
        padding: 15px 10px;
    }
    
    .table-modern tbody td {
        padding: 15px 10px;
    }
}

/* Mobile (max-width: 768px) */
@media (max-width: 768px) {
    /* Hide Table, Show Card View */
    .table-view {
        display: none !important;
    }
    
    .card-view {
        display: block !important;
    }
    
    /* Container adjustments */
    .container-fluid {
        padding-left: 15px !important;
        padding-right: 15px !important;
    }
    
    /* Header title */
    .d-flex.justify-content-between h2 {
        font-size: 1.3rem;
    }
    
    /* Card adjustments */
    .main-card {
        border-radius: 15px;
        margin-bottom: 20px;
    }
    
    .card-header-custom {
        padding: 15px;
        border-radius: 15px 15px 0 0;
        flex-direction: column;
        gap: 15px;
        text-align: center;
    }
    
    .card-header-custom h6 {
        font-size: 0.95rem;
    }
    
    .btn-add-new {
        width: 100%;
        padding: 10px 20px;
        font-size: 0.9rem;
    }
    
    /* Search container */
    .search-container {
        padding: 15px;
        border-radius: 10px;
    }
    
    .search-container form {
        flex-direction: column !important;
        gap: 10px !important;
    }
    
    .search-container .flex-grow-1 {
        width: 100%;
    }
    
    .search-btn {
        width: 100%;
        padding: 10px;
    }
    
    .btn-outline-secondary {
        width: 100%;
    }
    
    /* CRITICAL: Products Grid - WAJIB 2 KOLOM DI MOBILE */
    .products-grid {
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 0.75rem;
        margin-bottom: 1.5rem;
    }
    
    /* Product Card - Optimized for 2 Column Layout */
    .product-card {
        border-radius: 16px;
    }
    
    .product-card:hover {
        transform: translateY(-4px);
    }
    
    .product-card-image-container {
        height: 150px;
    }
    
    .product-card-content {
        padding: 1rem;
    }
    
    .product-card-title {
        font-size: 0.95rem;
        margin-bottom: 0.6rem;
    }
    
    .product-card-category {
        margin-bottom: 0.75rem;
    }
    
    .category-badge {
        font-size: 0.75rem;
        padding: 5px 10px;
    }
    
    .product-card-alur {
        margin-bottom: 1rem;
        min-height: 35px;
    }
    
    .alur-tag {
        font-size: 0.7rem;
        padding: 4px 8px;
    }
    
    .product-card-actions {
        gap: 0.4rem;
    }
    
    .product-card-actions .btn {
        padding: 0.6rem 0.5rem;
        font-size: 0.8rem;
    }
    
    .product-card-actions .btn i {
        font-size: 0.9rem;
    }
    
    /* Pagination */
    .pagination-modern {
        flex-wrap: wrap;
        gap: 5px;
    }
    
    .pagination-modern .page-link {
        padding: 8px 12px;
        font-size: 0.85rem;
        margin: 0;
    }
    
    /* Modal adjustments */
    .modal-modern .modal-dialog {
        margin: 10px;
    }
    
    .modal-modern .modal-header {
        padding: 15px 20px;
    }
    
    .modal-modern .modal-title {
        font-size: 1rem;
    }
    
    .modal-modern .modal-body {
        padding: 20px;
    }
    
    .form-control-modern {
        font-size: 0.9rem;
        padding: 10px 15px;
    }
    
    .btn-save-modern,
    .btn-delete-modern {
        padding: 10px 20px;
        font-size: 0.9rem;
    }
    
    /* Checkbox grid for mobile */
    .modal-modern .row > div[class*="col-"] {
        flex: 0 0 100%;
        max-width: 100%;
    }
    
    /* Empty state */
    .empty-state {
        padding: 40px 15px;
        grid-column: 1 / -1;
    }
    
    .empty-state i {
        font-size: 3rem;
    }
}

/* Small Mobile (max-width: 480px) */
@media (max-width: 480px) {
    .d-flex.justify-content-between h2 {
        font-size: 1.1rem;
    }
    
    .d-flex.justify-content-between h2 i {
        font-size: 1rem;
    }
    
    .card-header-custom h6 {
        font-size: 0.9rem;
    }
    
    .btn-add-new {
        font-size: 0.85rem;
        padding: 8px 15px;
    }
    
    /* Products Grid - TETAP 2 KOLOM */
    .products-grid {
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 0.5rem;
    }
    
    .product-card {
        border-radius: 12px;
    }
    
    .product-card-image-container {
        height: 130px;
    }
    
    .product-card-content {
        padding: 0.75rem;
    }
    
    .product-card-title {
        font-size: 0.85rem;
        margin-bottom: 0.5rem;
    }
    
    .product-card-category {
        margin-bottom: 0.6rem;
    }
    
    .category-badge {
        font-size: 0.7rem;
        padding: 4px 8px;
    }
    
    .product-card-alur {
        margin-bottom: 0.75rem;
        min-height: 30px;
    }
    
    .alur-tag {
        font-size: 0.65rem;
        padding: 3px 6px;
    }
    
    .product-card-actions .btn {
        padding: 0.5rem 0.4rem;
        font-size: 0.75rem;
    }
    
    .product-card-actions .btn i {
        font-size: 0.85rem;
    }
    
    .pagination-modern .page-link {
        padding: 6px 10px;
        font-size: 0.8rem;
    }
}

/* Extra Small Mobile (max-width: 360px) - TETAP 2 KOLOM */
@media (max-width: 360px) {
    .products-grid {
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 0.4rem;
    }
    
    .product-card-image-container {
        height: 120px;
    }
    
    .product-card-content {
        padding: 0.6rem;
    }
    
    .product-card-title {
        font-size: 0.8rem;
    }
    
    .category-badge {
        font-size: 0.65rem;
        padding: 3px 6px;
    }
    
    .alur-tag {
        font-size: 0.6rem;
        padding: 2px 5px;
    }
    
    .product-card-actions .btn {
        padding: 0.45rem 0.35rem;
        font-size: 0.7rem;
    }
}
</style>

<div class="container-fluid px-4">

    <?php
    if (isset($_GET['status']) && isset($_GET['message'])) {
        $status = $_GET['status'];
        $message = htmlspecialchars(urldecode($_GET['message']));
        $alert_type = ($status === 'success') ? 'success' : 'danger';
        echo "<div class='alert alert-{$alert_type} alert-dismissible fade show' role='alert'>
                  {$message}
                  <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
              </div>";
    }
    ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold text-dark mb-0">
            <i class="bi bi-box-seam me-2 text-primary"></i> Kelola Master Barang
        </h2>
    </div>

    <div class="card main-card">
        <div class="card-header card-header-custom d-flex justify-content-between align-items-center">
            <h6 class="mb-0">
                <i class="bi bi-grid-3x3-gap me-2"></i> Daftar Master Barang
            </h6>
            <button type="button" class="btn btn-add-new" data-bs-toggle="modal" data-bs-target="#tambahBarangModal">
                <i class="bi bi-plus-circle me-2"></i>Tambah Barang Baru
            </button>
        </div>
        
        <div class="card-body p-4">
            <div class="search-container">
                <form action="" method="GET" class="d-flex gap-3 align-items-center">
                    <div class="flex-grow-1">
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0 search-input" style="border-right: none !important;"><i class="bi bi-search text-muted"></i></span>
                            <input type="text" name="search" class="form-control search-input border-start-0" 
                                   placeholder="Cari berdasarkan nama barang atau kategori..." 
                                   value="<?php echo htmlspecialchars($search_query); ?>"
                                   style="border-left: none !important;">
                        </div>
                    </div>
                    <button class="btn search-btn" type="submit"><i class="bi bi-search me-2"></i>Cari</button>
                    <?php if (!empty($search_query)): ?>
                    <a href="?" class="btn btn-outline-secondary" style="border-radius: 50px;"><i class="bi bi-x-lg me-1"></i>Reset</a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- TABLE VIEW (Desktop Only) -->
            <div class="table-view">
                <div class="table-responsive">
                    <table class="table table-modern mb-0">
                        <thead>
                            <tr>
                                <th style="width: 60px;">No</th>
                                <th style="width: 120px;">Gambar</th>
                                <th>Nama Barang</th>
                                <th style="width: 150px;">Kategori</th>
                                <th>Alur Produksi</th>
                                <th style="width: 120px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($barangs)): ?>
                                <tr>
                                    <td colspan="6" class="empty-state">
                                        <i class="bi bi-box"></i>
                                        <h5 class="fw-bold mb-2">Tidak Ada Data</h5>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php $no = $offset + 1; foreach ($barangs as $barang): ?>
                                    <tr>
                                        <td class="text-center fw-bold"><?php echo $no++; ?></td>
                                        <td class="text-center">
    <?php
        // Logika untuk gambar default (Tabel)
        $is_default_table = empty($barang['gambar']);
        $img_src_table = '../../../uploads/' . htmlspecialchars($is_default_table ? 'default.png' : $barang['gambar']);
        $img_class_table = 'product-image' . ($is_default_table ? ' default-image' : '');
    ?>
    <img src="<?php echo $img_src_table; ?>"
         alt="<?php echo htmlspecialchars($barang['nama_barang']); ?>"
         class="<?php echo $img_class_table; ?>"
         onerror="this.onerror=null; this.src='../../../uploads/default.png'; this.classList.add('default-image');">
</td>
                                        <td>
                                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($barang['nama_barang']); ?></div>
                                        </td>
                                        <td>
                                            <span class="category-badge"><?php echo htmlspecialchars($barang['nama_kategori'] ?? 'Tanpa Kategori'); ?></span>
                                        </td>
                                        <td>
                                            <div class="alur-tags">
                                                <?php
                                                $alur_terhubung_stmt = $pdo->prepare("SELECT ma.nama_alur FROM master_alur ma JOIN alur_barang ab ON ma.id_alur = ab.id_alur WHERE ab.id_barang = ? ORDER BY ma.urutan");
                                                $alur_terhubung_stmt->execute([$barang['id_barang']]);
                                                $alurs = $alur_terhubung_stmt->fetchAll(PDO::FETCH_COLUMN);
                                                
                                                if (!empty($alurs)) {
                                                    foreach ($alurs as $alur_name) {
                                                        echo '<span class="alur-tag">' . htmlspecialchars($alur_name) . '</span>';
                                                    }
                                                } else {
                                                    echo '<span class="text-muted fst-italic">Belum ada alur</span>';
                                                }
                                                ?>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <div class="d-flex justify-content-center gap-2">
                                                <a href="../manajemen_produksi/detail_barang.php?id=<?php echo $barang['id_barang']; ?>" class="btn btn-info-modern" title="Lihat Detail & Target">
                                                    <i class="bi bi-eye-fill"></i>
                                                </a>
                                                <button class="btn btn-edit-modern" data-bs-toggle="modal" data-bs-target="#editBarangModal<?php echo $barang['id_barang']; ?>" title="Edit Barang">
                                                    <i class="bi bi-pencil-square"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- CARD VIEW (Mobile Only) -->
            <div class="card-view">
                <?php if (empty($barangs)): ?>
                    <div class="empty-state">
                        <i class="bi bi-box"></i>
                        <h5 class="fw-bold mb-2">Tidak Ada Data</h5>
                        <p>Belum ada barang yang terdaftar</p>
                    </div>
                <?php else: ?>
                    <div class="products-grid">
                        <?php foreach ($barangs as $barang): ?>
                            <div class="product-card">
                                <div class="product-card-image-container">
    <?php
        // Logika untuk gambar default (Kartu)
        $is_default_card = empty($barang['gambar']);
        $img_src_card = '../../../uploads/' . htmlspecialchars($is_default_card ? 'default.png' : $barang['gambar']);
        $img_class_card = 'product-card-image' . ($is_default_card ? ' default-image' : '');
    ?>
    <img src="<?php echo $img_src_card; ?>"
         alt="<?php echo htmlspecialchars($barang['nama_barang']); ?>"
         class="<?php echo $img_class_card; ?>"
         onerror="this.onerror=null; this.src='../../../uploads/default.png'; this.classList.add('default-image');">
</div>
                                <div class="product-card-content">
                                    <h3 class="product-card-title"><?php echo htmlspecialchars($barang['nama_barang']); ?></h3>
                                    
                                    <div class="product-card-category">
                                        <span class="category-badge"><?php echo htmlspecialchars($barang['nama_kategori'] ?? 'Tanpa Kategori'); ?></span>
                                    </div>
                                    
                                    <div class="product-card-alur">
                                        <div class="alur-tags">
                                            <?php
                                            $alur_terhubung_stmt = $pdo->prepare("SELECT ma.nama_alur FROM master_alur ma JOIN alur_barang ab ON ma.id_alur = ab.id_alur WHERE ab.id_barang = ? ORDER BY ma.urutan");
                                            $alur_terhubung_stmt->execute([$barang['id_barang']]);
                                            $alurs = $alur_terhubung_stmt->fetchAll(PDO::FETCH_COLUMN);
                                            
                                            if (!empty($alurs)) {
                                                foreach ($alurs as $alur_name) {
                                                    echo '<span class="alur-tag">' . htmlspecialchars($alur_name) . '</span>';
                                                }
                                            } else {
                                                echo '<span class="text-muted fst-italic" style="font-size: 0.75rem;">Belum ada alur</span>';
                                            }
                                            ?>
                                        </div>
                                    </div>
                                    
                                    <div class="product-card-actions">
                                        <a href="../manajemen_produksi/detail_barang.php?id=<?php echo $barang['id_barang']; ?>" class="btn btn-info-modern" title="Lihat Detail">
                                            <i class="bi bi-eye-fill"></i> Detail
                                        </a>
                                        <button class="btn btn-edit-modern" data-bs-toggle="modal" data-bs-target="#editBarangModal<?php echo $barang['id_barang']; ?>" title="Edit">
                                            <i class="bi bi-pencil-square"></i> Edit
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($total_pages > 1): ?>
            <div class="d-flex justify-content-center mt-4">
                <nav>
                    <ul class="pagination pagination-modern mb-0">
                        <?php if ($page > 1): ?>
                        <li class="page-item"><a class="page-link" href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search_query); ?>"><i class="bi bi-chevron-left"></i></a></li>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>"><a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search_query); ?>"><?php echo $i; ?></a></li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                        <li class="page-item"><a class="page-link" href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search_query); ?>"><i class="bi bi-chevron-right"></i></a></li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="modal fade modal-modern" id="tambahBarangModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="proses_kelola_barang.php" method="post" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Tambah Barang Baru</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label form-label-modern">Nama Barang</label>
                        <input type="text" class="form-control form-control-modern" name="nama_barang" required>
                    </div>
                    <div class="mb-3">
    <label class="form-label form-label-modern">ID Barang / Kode Unik</label>
    <input type="text" class="form-control form-control-modern" name="kode_barang" placeholder="Contoh: BRG-001" required>
</div>
                    <div class="mb-3">
                        <label class="form-label form-label-modern">Kategori</label>
                        <select class="form-control form-control-modern" name="id_kategori" required>
                            <option value="">-- Pilih Kategori --</option>
                            <?php foreach ($kategori_options as $kategori): ?>
                                <option value="<?php echo $kategori['id_kategori']; ?>">
                                    <?php echo htmlspecialchars($kategori['nama_kategori']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label form-label-modern">Gambar</label>
                        <input type="file" class="form-control form-control-modern" name="gambar" accept="image/*">
                    </div>
                    <div class="mb-3">
                        <label class="form-label form-label-modern">Pilih Alur Produksi</label>
                        <div class="row pt-2">
                            <?php foreach ($all_alurs as $alur): ?>
                            <div class="col-md-6 col-lg-4">
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="alurs[]" value="<?php echo $alur['id_alur']; ?>" id="add_alur_<?php echo $alur['id_alur']; ?>">
                                    <label class="form-check-label" for="add_alur_<?php echo $alur['id_alur']; ?>">
                                        <?php echo htmlspecialchars($alur['nama_alur']); ?>
                                    </label>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius: 25px;">Batal</button>
                    <button type="submit" name="tambah_barang" class="btn btn-save-modern">Simpan Barang</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php foreach ($barangs as $barang): ?>
<div class="modal fade modal-modern" id="editBarangModal<?php echo $barang['id_barang']; ?>" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="proses_kelola_barang.php" method="post" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edit Barang: <?php echo htmlspecialchars($barang['nama_barang']); ?></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id_barang" value="<?php echo $barang['id_barang']; ?>">
                    <div class="mb-3">
                        <label class="form-label form-label-modern">Nama Barang</label>
                        <input type="text" class="form-control form-control-modern" name="nama_barang" value="<?php echo htmlspecialchars($barang['nama_barang']); ?>" required>
                    </div>
                    <div class="mb-3">
    <label class="form-label form-label-modern">ID Barang / Kode Unik</label>
    <input type="text" class="form-control form-control-modern" name="kode_barang" value="<?php echo htmlspecialchars($barang['kode_barang']); ?>" required>
</div>
                    <div class="mb-3">
                        <label class="form-label form-label-modern">Kategori</label>
                        <select class="form-control form-control-modern" name="id_kategori" required>
                            <option value="">-- Pilih Kategori --</option>
                            <?php foreach ($kategori_options as $kategori): ?>
                                <option value="<?php echo $kategori['id_kategori']; ?>" <?php echo ($kategori['id_kategori'] == $barang['id_kategori']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($kategori['nama_kategori']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label form-label-modern">Ganti Gambar (Opsional)</label>
                        <input type="file" class="form-control form-control-modern" name="gambar" accept="image/*">
                    </div>
                    <div class="mb-3">
                        <label class="form-label form-label-modern">Pilih Alur Produksi</label>
                        <div class="row pt-2">
                            <?php
                                $alur_barang_stmt = $pdo->prepare("SELECT id_alur FROM alur_barang WHERE id_barang = ?");
                                $alur_barang_stmt->execute([$barang['id_barang']]);
                                $alur_barang_ids = $alur_barang_stmt->fetchAll(PDO::FETCH_COLUMN);
                            ?>
                            <?php foreach ($all_alurs as $alur): ?>
                            <div class="col-md-6 col-lg-4">
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="alurs[]" value="<?php echo $alur['id_alur']; ?>" id="edit_alur_<?php echo $barang['id_barang']; ?>_<?php echo $alur['id_alur']; ?>" <?php echo in_array($alur['id_alur'], $alur_barang_ids) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="edit_alur_<?php echo $barang['id_barang']; ?>_<?php echo $alur['id_alur']; ?>">
                                        <?php echo htmlspecialchars($alur['nama_alur']); ?>
                                    </label>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius: 25px;">Batal</button>
                    <button type="button" class="btn btn-delete-modern" data-bs-toggle="modal" data-bs-target="#hapusBarangModal" data-id-barang="<?php echo $barang['id_barang']; ?>" data-nama-barang="<?php echo htmlspecialchars($barang['nama_barang']); ?>">
                        <i class="bi bi-trash3-fill"></i> Hapus
                    </button>
                    <button type="submit" name="edit_barang" class="btn btn-save-modern">
                        <i class="bi bi-check-lg me-1"></i>Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endforeach; ?>

</main>

<div class="modal fade" id="hapusBarangModal" tabindex="-1" aria-labelledby="hapusBarangModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 20px;">
            <form action="proses_kelola_barang.php" method="POST">
                <div class="modal-header bg-danger text-white" style="border-top-left-radius: 20px; border-top-right-radius: 20px;">
                    <h5 class="modal-title" id="hapusBarangModalLabel">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i> Konfirmasi Hapus Barang
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id_barang" id="hapus_id_barang">
                    <div class="text-center py-3">
                        <i class="bi bi-trash3-fill text-danger" style="font-size: 4rem;"></i>
                        <h5 class="mt-3 mb-3">Apakah Anda benar-benar yakin?</h5>
                        <p class="text-muted mb-2">
                            Anda akan menghapus barang:
                        </p>
                        <div class="alert alert-danger">
                            <strong id="hapus_nama_barang"></strong>
                        </div>
                        <p class="text-danger fw-bold">
                            <i class="bi bi-info-circle me-1"></i>
                            Tindakan ini tidak dapat dibatalkan!
                        </p>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0 justify-content-center">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius: 50px; padding: 0.75rem 1.5rem;">
                        <i class="bi bi-x-lg me-1"></i> Batal
                    </button>
                    <button type="submit" name="hapus_barang" class="btn btn-danger" style="border-radius: 50px; padding: 0.75rem 1.5rem;">
                        <i class="bi bi-trash me-1"></i> Ya, Hapus
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
        
<script>
// JavaScript untuk modal hapus
document.addEventListener('DOMContentLoaded', function() {
    const hapusModal = document.getElementById('hapusBarangModal');
    if (hapusModal) {
        hapusModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const idBarang = button.getAttribute('data-id-barang');
            const namaBarang = button.getAttribute('data-nama-barang');
            const modalNamaBarang = hapusModal.querySelector('#hapus_nama_barang');
            const modalIdBarangInput = hapusModal.querySelector('#hapus_id_barang');
            modalNamaBarang.textContent = namaBarang;
            modalIdBarangInput.value = idBarang;
        });
    }
});
</script>

<?php include '../../../templates/footer.php'; ?>

<script>
// JavaScript untuk membuka modal via URL
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('action') === 'add') {
        var tambahModal = new bootstrap.Modal(document.getElementById('tambahBarangModal'));
        tambahModal.show();
    }
});
</script>