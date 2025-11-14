<?php
$page_title = 'Master Material';
include '../../../templates/header_superadmin.php';
include '../../../system/database_connection.php';

$items_per_page = 15;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$offset = ($page - 1) * $items_per_page;

try {
    // Query dasar sekarang memfilter hanya komponen yang aktif
    $base_sql = "FROM master_komponen WHERE is_active = 1";
    $count_sql = "SELECT COUNT(*) " . $base_sql;
    $sql = "SELECT * " . $base_sql;
    $params = [];

    if (!empty($search)) {
        $sql .= " AND (nama_komponen LIKE :search OR kode_komponen LIKE :search)";
        $count_sql .= " AND (nama_komponen LIKE :search OR kode_komponen LIKE :search)";
        $params[':search'] = "%$search%";
    }

    $total_stmt = $pdo->prepare($count_sql);
    $total_stmt->execute($params);
    $total_items = $total_stmt->fetchColumn();
    $total_pages = ceil($total_items / $items_per_page);

    $sql .= " ORDER BY id_komponen DESC LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':limit', $items_per_page, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    if (!empty($search)) {
        $stmt->bindParam(':search', $params[':search'], PDO::PARAM_STR);
    }
    $stmt->execute();
    $komponens = $stmt->fetchAll();

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>

<!-- Custom CSS -->
<style>
.main-card {
    background: linear-gradient(145deg, #ffffff, #f8f9fa);
    border: none;
    border-radius: 20px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
    transition: all 0.3s ease;
}

.main-card:hover {
    box-shadow: 0 15px 40px rgba(0, 0, 0, 0.12);
}

.card-header-custom {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    border-radius: 20px 20px 0 0;
    padding: 25px 30px;
}

.card-header-custom h6 {
    color: white;
    font-weight: 600;
    font-size: 1.1rem;
    margin: 0;
}

.search-container {
    background: #f8f9fa;
    border-radius: 15px;
    padding: 20px;
    margin-bottom: 25px;
    border: 1px solid #e9ecef;
}

.search-input {
    border: 2px solid #e9ecef;
    border-radius: 50px;
    padding: 12px 20px;
    font-size: 14px;
    transition: all 0.3s ease;
    background: white;
}

.search-input:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
    outline: none;
}

.search-btn {
    border: none;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 50px;
    padding: 12px 25px;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
}

.search-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
    color: white;
}

.reset-btn {
    border: 2px solid #6c757d;
    background: white;
    color: #6c757d;
    border-radius: 50px;
    padding: 12px 25px;
    transition: all 0.3s ease;
}

.reset-btn:hover {
    background: #6c757d;
    color: white;
    transform: translateY(-2px);
}

.btn-add-new {
    background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    border: none;
    border-radius: 50px;
    padding: 12px 25px;
    color: white;
    font-weight: 500;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(17, 153, 142, 0.3);
}

.btn-add-new:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(17, 153, 142, 0.4);
    color: white;
}

.alert-modern {
    border: none;
    border-radius: 15px;
    padding: 20px;
    margin-bottom: 25px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    animation: slideInDown 0.5s ease-out;
}

.alert-success {
    background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
    color: #155724;
}

.alert-danger {
    background: linear-gradient(135deg, #f8d7da 0%, #f1aeb5 100%);
    color: #721c24;
}

.table-modern {
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
    border: none;
}

.table-modern thead th {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.85rem;
    letter-spacing: 0.5px;
    border: none;
    padding: 20px 15px;
    text-align: center;
}

.table-modern tbody td {
    padding: 20px 15px;
    vertical-align: middle;
    border: none;
    border-bottom: 1px solid #f1f3f4;
    transition: background-color 0.2s ease;
    text-align: center;
}

.table-modern tbody tr:hover {
    background-color: #f8f9ff;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}

.table-modern tbody tr:last-child td {
    border-bottom: none;
}

.id-badge {
    background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
    color: #1976d2;
    padding: 8px 15px;
    border-radius: 25px;
    font-weight: 600;
    font-size: 0.85rem;
    min-width: 50px;
    display: inline-block;
}

.kode-badge {
    background: linear-gradient(135deg, #f3e5f5 0%, #e1bee7 100%);
    color: #7b1fa2;
    padding: 8px 15px;
    border-radius: 25px;
    font-weight: 500;
    font-size: 0.85rem;
    font-family: monospace;
}

.nama-komponen {
    font-weight: 600;
    color: #2c3e50;
    font-size: 0.95rem;
}

.btn-edit-modern {
    background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%);
    border: none;
    color: #8b4513;
    padding: 8px 16px;
    border-radius: 25px;
    font-size: 0.85rem;
    font-weight: 500;
    transition: all 0.3s ease;
    box-shadow: 0 3px 10px rgba(252, 182, 159, 0.3);
    margin-right: 5px;
}

.btn-edit-modern:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(252, 182, 159, 0.4);
    color: #8b4513;
}

.btn-delete-modern {
    background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%);
    border: none;
    color: #8b5a5a;
    padding: 8px 16px;
    border-radius: 25px;
    font-size: 0.85rem;
    font-weight: 500;
    transition: all 0.3s ease;
    box-shadow: 0 3px 10px rgba(255, 154, 158, 0.3);
}

.btn-delete-modern:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(255, 154, 158, 0.4);
    color: #8b5a5a;
}

.pagination-modern .page-link {
    border: none;
    color: #667eea;
    background: white;
    margin: 0 3px;
    border-radius: 12px;
    padding: 12px 18px;
    transition: all 0.3s ease;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.pagination-modern .page-item.active .page-link {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
}

.pagination-modern .page-link:hover {
    color: white;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
}

.modal-modern .modal-content {
    border: none;
    border-radius: 25px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
}

.modal-modern .modal-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    border-radius: 25px 25px 0 0;
    padding: 25px 30px;
}

.modal-modern .modal-title {
    font-weight: 600;
    font-size: 1.2rem;
}

.modal-modern .modal-body {
    padding: 30px;
}

.form-label-modern {
    font-weight: 600;
    color: #495057;
    margin-bottom: 8px;
    font-size: 0.9rem;
}

.form-control-modern {
    border: 2px solid #e9ecef;
    border-radius: 12px;
    padding: 12px 18px;
    transition: all 0.3s ease;
    font-size: 0.9rem;
}

.form-control-modern:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
}

.btn-save-modern {
    background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    border: none;
    color: white;
    padding: 12px 30px;
    border-radius: 25px;
    font-weight: 500;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(17, 153, 142, 0.3);
}

.btn-save-modern:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(17, 153, 142, 0.4);
    color: white;
}

.btn-primary-modern {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    color: white;
    padding: 12px 30px;
    border-radius: 25px;
    font-weight: 500;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
}

.btn-primary-modern:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
    color: white;
}

.btn-secondary-modern {
    background: white;
    border: 2px solid #6c757d;
    color: #6c757d;
    padding: 12px 30px;
    border-radius: 25px;
    font-weight: 500;
    transition: all 0.3s ease;
}

.btn-secondary-modern:hover {
    background: #6c757d;
    color: white;
    transform: translateY(-2px);
}

.btn-danger-modern {
    background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%);
    border: none;
    color: #8b5a5a;
    padding: 12px 30px;
    border-radius: 25px;
    font-weight: 500;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(255, 154, 158, 0.3);
}

.btn-danger-modern:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(255, 154, 158, 0.4);
    color: #8b5a5a;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #6c757d;
}

.empty-state i {
    font-size: 4rem;
    color: #e9ecef;
    margin-bottom: 20px;
}

.stats-info {
    background: linear-gradient(135deg, #e8f5e8 0%, #d4edda 100%);
    border-radius: 15px;
    padding: 15px 20px;
    color: #155724;
    font-size: 0.9rem;
    margin-bottom: 20px;
}

@keyframes slideInDown {
    from {
        opacity: 0;
        transform: translate3d(0, -100%, 0);
    }
    to {
        opacity: 1;
        transform: translate3d(0, 0, 0);
    }
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translate3d(0, 30px, 0);
    }
    to {
        opacity: 1;
        transform: translate3d(0, 0, 0);
    }
}

.main-card {
    animation: fadeInUp 0.6s ease-out;
}

.search-container {
    animation: fadeInUp 0.6s ease-out 0.1s both;
}

.table-responsive {
    animation: fadeInUp 0.6s ease-out 0.2s both;
}

@media (max-width: 768px) {
    .card-header-custom {
        padding: 20px;
        text-align: center;
        flex-direction: column;
        gap: 15px;
    }
    
    .search-container {
        padding: 15px;
    }
    
    .table-responsive {
        border-radius: 15px;
    }
    
    .modal-modern .modal-body {
        padding: 20px;
    }
    
    .btn-edit-modern,
    .btn-delete-modern {
        margin-bottom: 5px;
        display: block;
        width: 100%;
    }
    
    .table-modern tbody td {
        padding: 15px 10px;
        font-size: 0.85rem;
    }
}

/* ============================================
   RESPONSIVE DESIGN - FIXED VERSION
   Master Material Mobile Layout (Data PASTI MUNCUL!)
   ============================================ */

/* Tablet View (768px - 1024px) */
@media (max-width: 1024px) {
    .container-fluid {
        padding-left: 1rem;
        padding-right: 1rem;
    }
    
    .card-header-custom {
        flex-direction: column;
        gap: 1rem;
        align-items: stretch !important;
    }
    
    .btn-add-new {
        width: 100%;
        justify-content: center;
    }
}

/* Mobile Devices (≤ 768px) - FIXED VERSION */
@media (max-width: 768px) {
    /* ========== GLOBAL MOBILE SETTINGS ========== */
    body {
        font-size: 14px;
    }
    
    .container-fluid {
        padding-left: 0.5rem;
        padding-right: 0.5rem;
    }
    
    /* ========== HEADER & BREADCRUMB ========== */
    .d-flex.justify-content-between.align-items-center.mb-4 {
        flex-direction: column;
        align-items: flex-start !important;
        gap: 1rem;
    }
    
    .d-flex.justify-content-between.align-items-center.mb-4 h2 {
        font-size: 1.5rem;
        margin-bottom: 0;
    }
    
    .breadcrumb {
        font-size: 0.8rem;
    }
    
    /* ========== ALERT MESSAGES ========== */
    .alert-modern {
        padding: 15px;
        font-size: 0.85rem;
        margin-bottom: 1rem;
        border-radius: 12px;
    }
    
    .alert-modern i {
        font-size: 1rem;
    }
    
    .alert-modern strong {
        display: block;
        margin-bottom: 0.25rem;
    }
    
    /* ========== STATS INFO ========== */
    .stats-info {
        padding: 12px 15px;
        font-size: 0.8rem;
        margin-bottom: 1rem;
        border-radius: 12px;
        text-align: center;
    }
    
    .stats-info i {
        display: block;
        margin-bottom: 0.25rem;
        font-size: 1.2rem;
    }
    
    /* ========== MAIN CARD ========== */
    .main-card {
        border-radius: 15px;
        margin-bottom: 1rem;
    }
    
    /* ========== CARD HEADER ========== */
    .card-header-custom {
        padding: 15px;
        border-radius: 15px 15px 0 0;
        flex-direction: column;
        gap: 0.75rem;
        align-items: stretch !important;
    }
    
    .card-header-custom h6 {
        font-size: 1rem;
        text-align: center;
    }
    
    .btn-add-new {
        width: 100%;
        justify-content: center;
        padding: 12px 20px;
        font-size: 0.9rem;
    }
    
    /* ========== CARD BODY ========== */
    .card-body {
        padding: 1rem !important;
    }
    
    /* ========== SEARCH CONTAINER ========== */
    .search-container {
        padding: 15px;
        border-radius: 12px;
        margin-bottom: 1rem;
    }
    
    .search-container form {
        flex-direction: column !important;
        gap: 0.75rem !important;
    }
    
    .search-container .flex-grow-1 {
        width: 100%;
    }
    
    .search-input {
        font-size: 16px !important; /* Prevent iOS zoom */
        padding: 10px 15px;
    }
    
    .input-group-text.search-input {
        padding: 10px 12px;
    }
    
    .search-btn,
    .reset-btn {
        width: 100%;
        padding: 12px 20px;
        font-size: 0.9rem;
        justify-content: center;
    }
    
    .search-container small {
        font-size: 0.75rem;
        display: block;
        text-align: center;
        margin-top: 0.5rem;
    }
    
    /* ========== TABLE TO CARD TRANSFORMATION - FIXED! ========== */
    .table-responsive {
        border-radius: 12px;
        overflow: visible !important; /* PENTING! */
    }
    
    /* CRITICAL FIX: Sembunyikan thead tapi TAMPILKAN tbody! */
    .table-modern thead {
        display: none !important;
    }
    
    .table-modern {
        display: block !important; /* PENTING: Table tetap tampil */
        width: 100%;
    }
    
    /* PENTING: Tampilkan tbody sebagai block */
    .table-modern tbody {
        display: block !important;
        width: 100%;
    }
    
    /* Transform setiap row jadi card */
    .table-modern tbody tr {
        display: block !important; /* CRITICAL! */
        background: white;
        border: 1px solid #e9ecef;
        border-radius: 12px;
        margin-bottom: 1rem;
        padding: 15px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        transition: all 0.3s ease;
        width: 100%;
    }
    
    .table-modern tbody tr:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
    }
    
    /* Empty state khusus */
    .table-modern tbody tr:has(td[colspan]) {
        display: block !important;
        text-align: center;
        padding: 40px 20px;
        border: 2px dashed #dee2e6;
    }
    
    /* Transform td jadi block dengan label */
    .table-modern tbody td {
        display: block !important; /* CRITICAL! */
        text-align: left !important;
        padding: 8px 0 !important;
        border: none !important;
        width: 100%;
    }
    
    /* Add labels before content - LEBIH SPESIFIK */
    .table-modern tbody tr:not(:has(td[colspan])) td:nth-child(1)::before {
        content: "ID: ";
        font-weight: 600;
        color: #495057;
        margin-right: 0.5rem;
    }
    
    .table-modern tbody tr:not(:has(td[colspan])) td:nth-child(2)::before {
        content: "Kode: ";
        font-weight: 600;
        color: #495057;
        margin-right: 0.5rem;
    }
    
    .table-modern tbody tr:not(:has(td[colspan])) td:nth-child(3)::before {
        content: "Nama: ";
        font-weight: 600;
        color: #495057;
        display: block;
        margin-bottom: 0.25rem;
    }
    
    .table-modern tbody tr:not(:has(td[colspan])) td:nth-child(4)::before {
        content: "Aksi: ";
        font-weight: 600;
        color: #495057;
        display: block;
        margin-bottom: 0.5rem;
    }
    
    /* Style for badges in card */
    .id-badge {
        font-size: 0.8rem;
        padding: 6px 12px;
    }
    
    .kode-badge {
        font-size: 0.8rem;
        padding: 6px 12px;
        display: inline-block;
        margin-top: 0.25rem;
    }
    
    .nama-komponen {
        font-size: 0.95rem;
        margin-top: 0.25rem;
        display: block;
        line-height: 1.4;
    }
    
    /* ========== ACTION BUTTONS IN CARD ========== */
    .table-modern tbody tr:not(:has(td[colspan])) td:nth-child(4) {
        display: flex !important;
        gap: 0.5rem;
        margin-top: 1rem;
        padding-top: 1rem !important;
        border-top: 1px solid #e9ecef !important;
    }
    
    .btn-edit-modern,
    .btn-delete-modern {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 10px 15px;
        font-size: 0.85rem;
        margin: 0 !important;
        width: auto !important;
    }
    
    .btn-edit-modern i,
    .btn-delete-modern i {
        font-size: 1rem;
        margin-right: 0.25rem;
    }
    
    /* ========== EMPTY STATE ========== */
    .empty-state {
        padding: 40px 15px !important;
        display: block !important;
    }
    
    .empty-state i {
        font-size: 3rem;
    }
    
    .empty-state h5 {
        font-size: 1.1rem;
        margin-bottom: 0.75rem;
    }
    
    .empty-state p {
        font-size: 0.85rem;
    }
    
    /* ========== PAGINATION ========== */
    .d-flex.justify-content-between.align-items-center.mt-4 {
        flex-direction: column;
        gap: 1rem;
        align-items: center !important;
    }
    
    .d-flex.justify-content-between.align-items-center.mt-4 > div:first-child {
        order: 2;
    }
    
    .d-flex.justify-content-between.align-items-center.mt-4 > nav {
        order: 1;
        width: 100%;
    }
    
    .pagination-modern {
        flex-wrap: wrap;
        justify-content: center;
        gap: 0.25rem;
    }
    
    .pagination-modern .page-link {
        padding: 8px 12px;
        font-size: 0.85rem;
        min-width: 40px;
        text-align: center;
    }
    
    .pagination-modern .page-item:first-child .page-link,
    .pagination-modern .page-item:last-child .page-link {
        padding: 8px 15px;
    }
    
    /* ========== MODALS ========== */
    .modal-modern .modal-dialog {
        margin: 0.5rem;
        max-width: calc(100% - 1rem);
    }
    
    .modal-modern .modal-content {
        border-radius: 20px;
    }
    
    .modal-modern .modal-header {
        padding: 20px;
        border-radius: 20px 20px 0 0;
    }
    
    .modal-modern .modal-title {
        font-size: 1.1rem;
        line-height: 1.4;
    }
    
    .modal-modern .modal-title i {
        font-size: 1.1rem;
    }
    
    .modal-modern .modal-body {
        padding: 20px;
    }
    
    .form-label-modern {
        font-size: 0.85rem;
        margin-bottom: 0.5rem;
    }
    
    .form-control-modern {
        font-size: 16px !important;
        padding: 12px 15px;
        border-radius: 10px;
    }
    
    .form-control-modern::placeholder {
        font-size: 0.9rem;
    }
    
    .modal-body .form-text {
        font-size: 0.75rem;
        margin-top: 0.25rem;
    }
    
    .modal-modern .text-center.py-3 {
        padding: 20px 10px !important;
    }
    
    .modal-modern .text-center.py-3 i {
        font-size: 3rem !important;
    }
    
    .modal-modern .text-center.py-3 h5 {
        font-size: 1.1rem;
        margin-top: 1rem !important;
        margin-bottom: 1rem !important;
    }
    
    .modal-modern .text-center.py-3 p {
        font-size: 0.85rem;
    }
    
    .modal-modern .alert-warning {
        font-size: 0.9rem;
        padding: 10px;
        margin: 1rem 0;
    }
    
    .modal-modern .modal-footer {
        padding: 15px 20px;
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .modal-modern .modal-footer .btn {
        width: 100%;
        margin: 0 !important;
        padding: 12px 20px;
        font-size: 0.9rem;
    }
    
    .modal-modern .modal-footer.justify-content-center {
        flex-direction: row;
        flex-wrap: wrap;
    }
    
    .modal-modern .modal-footer.justify-content-center .btn {
        flex: 1;
        min-width: 120px;
    }
}

/* Extra Small Devices (≤ 375px) */
@media (max-width: 375px) {
    .container-fluid {
        padding-left: 0.25rem;
        padding-right: 0.25rem;
    }
    
    .d-flex.justify-content-between.align-items-center.mb-4 h2 {
        font-size: 1.3rem;
    }
    
    .d-flex.justify-content-between.align-items-center.mb-4 h2 i {
        font-size: 1.2rem;
    }
    
    .card-header-custom h6 {
        font-size: 0.95rem;
    }
    
    .btn-add-new {
        font-size: 0.85rem;
        padding: 10px 15px;
    }
    
    .search-container {
        padding: 12px;
    }
    
    .stats-info {
        font-size: 0.75rem;
        padding: 10px 12px;
    }
    
    .table-modern tbody tr {
        padding: 12px;
        margin-bottom: 0.75rem;
    }
    
    .table-modern tbody td {
        padding: 6px 0 !important;
        font-size: 0.85rem;
    }
    
    .nama-komponen {
        font-size: 0.85rem;
    }
    
    .btn-edit-modern,
    .btn-delete-modern {
        padding: 8px 12px;
        font-size: 0.8rem;
    }
    
    .pagination-modern .page-link {
        padding: 6px 10px;
        font-size: 0.8rem;
        min-width: 36px;
    }
    
    .modal-modern .modal-dialog {
        margin: 0.25rem;
    }
    
    .modal-modern .modal-body {
        padding: 15px;
    }
    
    .modal-modern .modal-footer .btn {
        padding: 10px 15px;
        font-size: 0.85rem;
    }
}

/* Landscape Orientation on Mobile */
@media (max-width: 768px) and (orientation: landscape) {
    .table-modern {
        display: table !important;
        font-size: 0.8rem;
    }
    
    .table-modern thead {
        display: table-header-group !important;
    }
    
    .table-modern tbody {
        display: table-row-group !important;
    }
    
    .table-modern tbody tr {
        display: table-row !important;
        margin-bottom: 0;
        padding: 0;
        border-radius: 0;
    }
    
    .table-modern tbody td {
        display: table-cell !important;
        padding: 12px 8px !important;
        text-align: center !important;
    }
    
    .table-modern tbody td::before {
        content: none !important;
    }
    
    .table-modern tbody td:nth-child(4) {
        display: table-cell !important;
        margin-top: 0;
        padding-top: 12px !important;
        border-top: none !important;
    }
    
    .btn-edit-modern,
    .btn-delete-modern {
        padding: 6px 10px;
        font-size: 0.75rem;
        display: inline-flex;
    }
}

/* ============================================
   TOUCH & ACCESSIBILITY
   ============================================ */
@media (max-width: 768px) {
    .btn,
    a.btn,
    button,
    .page-link {
        min-height: 44px;
        min-width: 44px;
    }
    
    input,
    select,
    textarea {
        font-size: 16px;
    }
    
    .btn:focus,
    .form-control:focus,
    .page-link:focus {
        outline: 3px solid #667eea;
        outline-offset: 2px;
    }
    
    .btn,
    .page-link,
    .dropdown-item {
        -webkit-tap-highlight-color: rgba(102, 126, 234, 0.2);
    }
    
    .table-responsive {
        -webkit-overflow-scrolling: touch;
    }
    
    .text-muted {
        color: #495057 !important;
    }
}

/* ============================================
   PERFORMANCE OPTIMIZATION
   ============================================ */
@media (max-width: 768px) {
    * {
        transition-duration: 0.2s !important;
    }
    
    @media (hover: none) {
        .btn:hover,
        .page-link:hover {
            transform: none;
            box-shadow: initial;
        }
    }
}

/* ============================================
   PRINT STYLES
   ============================================ */
@media print {
    .btn,
    .pagination,
    .search-container,
    .modal,
    .alert {
        display: none !important;
    }
    
    .table-modern {
        display: table !important;
    }
    
    .table-modern thead {
        display: table-header-group !important;
    }
    
    .table-modern tbody {
        display: table-row-group !important;
    }
    
    .table-modern tbody tr {
        display: table-row !important;
        page-break-inside: avoid;
    }
    
    .table-modern tbody td {
        display: table-cell !important;
    }
    
    .table-modern tbody td::before {
        content: none !important;
    }
    
    .main-card {
        box-shadow: none;
        border: 1px solid #dee2e6;
    }
}

</style>

<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold text-dark mb-0">
            <i class="bi bi-box-seam me-2 text-primary"></i>
            <?php echo $page_title; ?>
        </h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="#" class="text-decoration-none">Master Data</a></li>
                <li class="breadcrumb-item active">Master Material</li>
            </ol>
        </nav>
    </div>
    
    <!-- Alert Messages -->
    <?php if (isset($_GET['status'])): ?>
    <div class="alert alert-<?php echo $_GET['status'] == 'success' ? 'success' : 'danger'; ?> alert-modern alert-dismissible fade show" role="alert">
        <i class="bi bi-<?php echo $_GET['status'] == 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
        <strong><?php echo $_GET['status'] == 'success' ? 'Berhasil!' : 'Error!'; ?></strong>
        <?php echo htmlspecialchars($_GET['message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <!-- Stats Info -->
    <?php if ($total_items > 0): ?>
    <div class="stats-info">
        <i class="bi bi-info-circle me-2"></i>
        Total <strong><?php echo $total_items; ?></strong> komponen aktif dalam database
        <?php if (!empty($search)): ?>
        | Menampilkan hasil pencarian untuk "<strong><?php echo htmlspecialchars($search); ?></strong>"
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="card main-card">
        <div class="card-header card-header-custom d-flex justify-content-between align-items-center">
            <h6 class="mb-0">
                <i class="bi bi-grid-3x3-gap me-2"></i>
                Daftar Master Komponen
            </h6>
            <button type="button" class="btn btn-add-new" data-bs-toggle="modal" data-bs-target="#tambahKomponenModal">
                <i class="bi bi-plus-circle me-2"></i>Tambah Komponen Manual
            </button>
        </div>
        
        <div class="card-body p-4">
            <!-- Search Container -->
            <div class="search-container">
                <form method="get" action="" class="d-flex gap-3 align-items-center">
                    <div class="flex-grow-1">
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0 search-input" style="border-right: none !important;">
                                <i class="bi bi-search text-muted"></i>
                            </span>
                            <input type="text" name="search" class="form-control search-input border-start-0" 
                                   placeholder="Cari berdasarkan nama atau kode komponen..." 
                                   value="<?php echo htmlspecialchars($search); ?>"
                                   style="border-left: none !important;">
                        </div>
                    </div>
                    <button class="btn search-btn" type="submit">
                        <i class="bi bi-search me-2"></i>Cari
                    </button>
                    <?php if (!empty($search)): ?>
                    <a href="kelola_material.php" class="btn reset-btn">
                        <i class="bi bi-arrow-clockwise me-1"></i>Reset
                    </a>
                    <?php endif; ?>
                </form>
                
                <?php if (!empty($search)): ?>
                <div class="mt-3">
                    <small class="text-muted">
                        <i class="bi bi-info-circle me-1"></i>
                        Menampilkan <strong><?php echo count($komponens); ?></strong> hasil dari total <strong><?php echo $total_items; ?></strong> komponen
                    </small>
                </div>
                <?php endif; ?>
            </div>

            <!-- Table Container -->
            <div class="table-responsive">
                <table class="table table-modern mb-0">
                    <thead>
                        <tr>
                            <th style="width: 80px;">ID</th>
                            <th style="width: 200px;">Kode Komponen</th>
                            <th>Nama Komponen</th>
                            <th style="width: 200px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($komponens)): ?>
                            <tr>
                                <td colspan="4" class="empty-state">
                                    <i class="bi bi-box"></i>
                                    <h5 class="fw-bold mb-2">Tidak Ada Data</h5>
                                    <p class="mb-0">
                                        <?php if (!empty($search)): ?>
                                            Tidak ditemukan komponen dengan kata kunci "<?php echo htmlspecialchars($search); ?>"
                                        <?php else: ?>
                                            Belum ada data komponen. Silakan tambah komponen baru.
                                        <?php endif; ?>
                                    </p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($komponens as $komponen): ?>
                                <tr>
                                    <td>
                                        <span class="id-badge"><?php echo $komponen['id_komponen']; ?></span>
                                    </td>
                                    <td>
                                        <?php if (!empty($komponen['kode_komponen'])): ?>
                                            <span class="kode-badge"><?php echo htmlspecialchars($komponen['kode_komponen']); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted fst-italic">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="nama-komponen"><?php echo htmlspecialchars($komponen['nama_komponen']); ?></div>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-edit-modern edit-btn" 
                                                data-bs-toggle="modal" data-bs-target="#editKomponenModal"
                                                data-id="<?php echo $komponen['id_komponen']; ?>"
                                                data-kode="<?php echo htmlspecialchars($komponen['kode_komponen']); ?>"
                                                data-nama="<?php echo htmlspecialchars($komponen['nama_komponen']); ?>">
                                            <i class="bi bi-pencil-square me-1"></i>
                                        </button>
                                        <button type="button" class="btn btn-delete-modern delete-btn"
                                                data-bs-toggle="modal" data-bs-target="#hapusKomponenModal"
                                                data-id="<?php echo $komponen['id_komponen']; ?>"
                                                data-nama="<?php echo htmlspecialchars($komponen['nama_komponen']); ?>">
                                            <i class="bi bi-trash me-1"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="d-flex justify-content-between align-items-center mt-4">
                <div class="text-muted">
                    <small>
                        Menampilkan <?php echo $offset + 1; ?> - <?php echo min($offset + count($komponens), $total_items); ?> 
                        dari <?php echo $total_items; ?> data
                    </small>
                </div>
                <nav>
                    <ul class="pagination pagination-modern mb-0">
                        <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>">
                                <i class="bi bi-chevron-left"></i>
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
                        <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                        </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>">
                                <i class="bi bi-chevron-right"></i>
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal Tambah Komponen -->
<div class="modal fade modal-modern" id="tambahKomponenModal" tabindex="-1" aria-labelledby="tambahKomponenModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="tambahKomponenModalLabel">
                    <i class="bi bi-plus-circle me-2"></i>
                    Tambah Komponen Baru
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="proses_kelola_material.php" method="POST" id="formTambahKomponen">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="kode_komponen" class="form-label form-label-modern">Kode Komponen (Opsional)</label>
                        <input type="text" class="form-control form-control-modern" id="kode_komponen" name="kode_komponen" 
                               placeholder="Masukkan kode komponen (misal: ABC123)">
                        <small class="form-text text-muted">Kode unik untuk identifikasi komponen</small>
                    </div>
                    <div class="mb-3">
                        <label for="nama_komponen" class="form-label form-label-modern">Nama Komponen *</label>
                        <input type="text" class="form-control form-control-modern" id="nama_komponen" name="nama_komponen" 
                               placeholder="Masukkan nama komponen" required>
                        <small class="form-text text-muted">Nama lengkap komponen/material</small>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-secondary-modern" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg me-1"></i>Batal
                    </button>
                    <button type="submit" name="tambah_komponen" class="btn btn-save-modern">
                        <i class="bi bi-check-lg me-1"></i>Tambah Komponen
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit Komponen -->
<div class="modal fade modal-modern" id="editKomponenModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-pencil-square me-2"></i>
                    Edit Komponen
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="proses_kelola_material.php" method="POST" id="formEditKomponen">
                <div class="modal-body">
                    <input type="hidden" name="id_komponen" id="edit_id_komponen">
                    <div class="mb-3">
                        <label for="edit_kode_komponen" class="form-label form-label-modern">Kode Komponen (Opsional)</label>
                        <input type="text" class="form-control form-control-modern" name="kode_komponen" id="edit_kode_komponen"
                               placeholder="Masukkan kode komponen">
                        <small class="form-text text-muted">Kode unik untuk identifikasi komponen</small>
                    </div>
                    <div class="mb-3">
                        <label for="edit_nama_komponen" class="form-label form-label-modern">Nama Komponen *</label>
                        <input type="text" class="form-control form-control-modern" name="nama_komponen" id="edit_nama_komponen" 
                               placeholder="Masukkan nama komponen" required>
                        <small class="form-text text-muted">Nama lengkap komponen/material</small>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-secondary-modern" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg me-1"></i>Batal
                    </button>
                    <button type="submit" name="edit_komponen" class="btn btn-primary-modern">
                        <i class="bi bi-check-lg me-1"></i>Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Hapus Komponen -->
<div class="modal fade modal-modern" id="hapusKomponenModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    Konfirmasi Hapus
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="proses_kelola_material.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="id_komponen" id="hapus_id_komponen">
                    <div class="text-center py-3">
                        <i class="bi bi-exclamation-triangle-fill text-warning" style="font-size: 4rem;"></i>
                        <h5 class="mt-3 mb-3">Apakah Anda yakin?</h5>
                        <p class="text-muted mb-2">
                            Anda akan menghapus komponen:
                        </p>
                        <div class="alert alert-warning">
                            <strong id="hapus_nama_komponen"></strong>
                        </div>
                        <p class="text-muted">
                            <i class="bi bi-info-circle me-1"></i>
                            Tindakan ini tidak dapat dibatalkan!
                        </p>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0 justify-content-center">
                    <button type="button" class="btn btn-secondary-modern me-3" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg me-1"></i>Batal
                    </button>
                    <button type="submit" name="hapus_komponen" class="btn btn-danger-modern">
                        <i class="bi bi-trash me-1"></i>Ya, Hapus Sekarang
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Enhanced JavaScript -->
<script>
// GANTIKAN SELURUH BLOK <script> LAMA ANDA DENGAN INI
document.addEventListener('DOMContentLoaded', function() {
    // Fungsi untuk mengisi data ke Modal Edit
const editModal = document.getElementById('editKomponenModal');
    if (editModal) {
        editModal.addEventListener('show.bs.modal', function (event) {
            // Tombol yang di-klik untuk memicu modal
            const button = event.relatedTarget; 
            
            // Ambil data dari atribut data-* tombol
            const id = button.getAttribute('data-id');
            const kode = button.getAttribute('data-kode');
            const nama = button.getAttribute('data-nama');

            // Ambil elemen input di dalam modal
            const modalIdInput = editModal.querySelector('#edit_id_komponen');
            const modalKodeInput = editModal.querySelector('#edit_kode_komponen');
            const modalNamaInput = editModal.querySelector('#edit_nama_komponen');

            // Isi nilai ke dalam form di modal (INI KUNCINYA)
            if(modalIdInput) modalIdInput.value = id;
            if(modalKodeInput) modalKodeInput.value = kode;
            if(modalNamaInput) modalNamaInput.value = nama;

            // Logika animasi loading yang BENAR:
            // Terapkan pada tombol yang diklik, BUKAN pada seluruh modal.
            const originalButtonContent = button.innerHTML;
            button.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';
            button.disabled = true;

            // Kembalikan konten tombol setelah modal selesai ditampilkan
            editModal.addEventListener('shown.bs.modal', () => {
                button.innerHTML = originalButtonContent;
                button.disabled = false;
            }, { once: true }); // Opsi { once: true } agar listener ini hanya berjalan sekali
        });
    }

    const hapusModal = document.getElementById('hapusKomponenModal');
    if(hapusModal) {
        hapusModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const id = button.getAttribute('data-id');
            const nama = button.getAttribute('data-nama');
            
            const modalIdInput = hapusModal.querySelector('#hapus_id_komponen');
            const modalNamaElement = hapusModal.querySelector('#hapus_nama_komponen');

            if(modalIdInput) modalIdInput.value = id;
            if(modalNamaElement) modalNamaElement.textContent = nama;
        });
    };

    // Auto dismiss alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert-modern');
    alerts.forEach(alert => {
        setTimeout(() => {
            if (alert.parentNode && alert.classList.contains('show')) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }
        }, 5000);
    });
    
    // Add real-time character count for inputs
    const inputs = document.querySelectorAll('input[type="text"]');
    inputs.forEach(input => {
        input.addEventListener('input', function() {
            const maxLength = 255; 
            const currentLength = this.value.length;
            
            const existingCounter = this.parentNode.querySelector('.char-counter');
            if (existingCounter) {
                existingCounter.remove();
            }
            
            if (document.activeElement === this && currentLength > 0) {
                const counter = document.createElement('small');
                counter.className = 'char-counter text-muted mt-1';
                counter.textContent = `${currentLength}/${maxLength}`;
                
                if (currentLength > maxLength * 0.8) {
                    counter.classList.add('text-warning');
                }
                if (currentLength > maxLength) {
                    counter.classList.remove('text-warning');
                    counter.classList.add('text-danger');
                }
                
                this.parentNode.appendChild(counter);
            }
        });
        
        input.addEventListener('blur', function() {
            const counter = this.parentNode.querySelector('.char-counter');
            if (counter) {
                setTimeout(() => {
                    if (counter.parentNode) {
                        counter.remove();
                    }
                }, 2000);
            }
        });
    });
});

// Utility function untuk menampilkan alert
function showAlert(message, type = 'info') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type === 'error' ? 'danger' : type} alert-modern alert-dismissible fade show position-fixed`;
    alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; max-width: 400px;';
    
    const icon = type === 'error' ? 'exclamation-triangle' : 
                 type === 'warning' ? 'exclamation-circle' : 
                 type === 'success' ? 'check-circle' : 'info-circle';
    
    alertDiv.innerHTML = `
        <i class="bi bi-${icon} me-2"></i>
        <strong>${type === 'error' ? 'Error!' : type === 'success' ? 'Berhasil!' : 'Info:'}</strong>
        ${message.replace(/\n/g, '<br>')}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(alertDiv);
    
    setTimeout(() => {
        if (alertDiv.parentNode) {
            const bsAlert = new bootstrap.Alert(alertDiv);
            bsAlert.close();
        }
    }, 5000);
}

// Add CSS untuk form validation dan enhancements
const style = document.createElement('style');
style.textContent = `
    .form-control.is-invalid {
        border-color: #dc3545;
        box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
    }
    .spinner-border-sm {
        width: 1rem;
        height: 1rem;
    }
    .table-modern tbody tr {
        transition: all 0.2s ease;
    }
    .char-counter {
        display: block;
        font-size: 0.75rem;
        margin-top: 0.25rem;
        text-align: right;
    }
    .btn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }
    .alert-modern {
        backdrop-filter: blur(10px);
    }
    .modal-backdrop {
        backdrop-filter: blur(5px);
    }
    .table-modern tbody tr:not(.empty-state):hover {
        cursor: pointer;
    }
    .search-input:focus + .search-input {
        border-left-color: #667eea !important;
    }
    .input-group:focus-within .search-input {
        border-color: #667eea;
        box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
    }
    @media (max-width: 576px) {
        .btn-edit-modern,
        .btn-delete-modern {
            padding: 6px 12px;
            font-size: 0.8rem;
        }
        .id-badge,
        .kode-badge {
            padding: 6px 10px;
            font-size: 0.75rem;
        }
        .nama-komponen {
            font-size: 0.85rem;
        }
    }
`;
document.head.appendChild(style);
</script>

<?php include '../../../templates/footer.php'; ?>