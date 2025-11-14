<?php
include_once '../../../templates/header_user.php';
include_once '../../../system/database_connection.php';

// 1. Validasi ID Barang dari URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "<script>window.location.href='master_barang.php';</script>";
    exit;
}
$id_barang = (int)$_GET['id'];

// Get sorting parameter
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'terbaru';
$sort = in_array($sort, ['terbaru', 'terlama']) ? $sort : 'terbaru';

// 2. Fungsi untuk menghitung progres
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

// 3. Fungsi countdown deadline
function get_countdown($deadline) {
    if (empty($deadline)) return null;
    
    $now = new DateTime();
    $target = new DateTime($deadline);
    $diff = $now->diff($target);
    
    if ($diff->invert) {
        return ['text' => 'Terlambat ' . $diff->days . ' hari', 'class' => 'danger', 'days' => -$diff->days];
    }
    
    if ($diff->days == 0) {
        return ['text' => 'Hari ini!', 'class' => 'danger', 'days' => 0];
    }
    
    if ($diff->days <= 3) {
        return ['text' => $diff->days . ' hari lagi', 'class' => 'danger', 'days' => $diff->days];
    }
    
    if ($diff->days <= 7) {
        return ['text' => $diff->days . ' hari lagi', 'class' => 'warning', 'days' => $diff->days];
    }
    
    return ['text' => $diff->days . ' hari lagi', 'class' => 'success', 'days' => $diff->days];
}

try {
    // 4. Query untuk Mengambil Detail Barang
    $barang_stmt = $pdo->prepare("
        SELECT mb.nama_barang, mb.gambar, mk.nama_kategori
        FROM master_barang mb
        LEFT JOIN master_kategori mk ON mb.id_kategori = mk.id_kategori
        WHERE mb.id_barang = ? AND mb.is_active = 1
    ");
    $barang_stmt->execute([$id_barang]);
    $barang = $barang_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$barang) {
        echo "<script>window.location.href='master_barang.php';</script>";
        exit;
    }

    // 5. Pagination
    $data_per_halaman = 5;
    $query_jumlah_data = $pdo->prepare("SELECT COUNT(*) as total FROM production_targets WHERE id_barang = ? AND status = 'ongoing' AND is_active = 1");
    $query_jumlah_data->execute([$id_barang]);
    $total_data = $query_jumlah_data->fetch(PDO::FETCH_ASSOC)['total'];
    
    $jumlah_halaman = ceil($total_data / $data_per_halaman);
    $halaman_aktif = (isset($_GET['halaman'])) ? (int)$_GET['halaman'] : 1;
    $awal_data = ($halaman_aktif - 1) * $data_per_halaman;

    // 6. Query targets dengan sorting logic
    // Priority ALWAYS top (sorted by deadline ASC), then regular items sorted by user choice
    $sort_order = ($sort === 'terbaru') ? 'DESC' : 'ASC';
    
    $target_stmt = $pdo->prepare("
        SELECT id_target, nama_permintaan, jumlah_unit, status, prioritas, is_priority, priority_deadline, created_at
        FROM production_targets 
        WHERE id_barang = ? AND status = 'ongoing' AND is_active = 1
        ORDER BY 
            /* Gunakan is_priority untuk pengurutan */
            CASE WHEN is_priority = 1 THEN 0 ELSE 1 END,
            /* Gunakan is_priority untuk pengurutan deadline */
            CASE WHEN is_priority = 1 THEN priority_deadline END ASC,
            /* Gunakan is_priority untuk pengurutan non-prioritas */
            CASE WHEN is_priority != 1 THEN created_at END $sort_order
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

$base_url = '/produksi_tracking';
$base_url_uploads = $base_url . '/uploads/';
?>

<style>
/* ... [SEMUA CSS ANDA TETAP SAMA, TIDAK ADA PERUBAHAN] ... */
:root {
    --glass-bg: rgba(255, 255, 255, 0.95);
    --glass-border: rgba(255, 255, 255, 0.18);
    --accent-color: #667eea;
    --accent-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --success-color: #28a745;
    --warning-color: #ffc107;
    --danger-color: #dc3545;
    --text-dark: #2c3e50;
}

.detail-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 2rem 1rem;
}

/* Page Header */
.page-header-glass {
    background: var(--glass-bg);
    backdrop-filter: blur(10px);
    border-radius: 25px;
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
    border: 1px solid var(--glass-border);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}

.page-title-main {
    font-size: 2rem;
    font-weight: 700;
    color: var(--text-dark);
    margin: 0;
}

.btn-back-custom {
    background: var(--accent-gradient);
    color: white;
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 50px;
    font-weight: 600;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-back-custom:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
    color: white;
}

/* Info Card */
.info-card {
    background: var(--glass-bg);
    backdrop-filter: blur(10px);
    border-radius: 25px;
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
    border: 1px solid var(--glass-border);
}

.info-content {
    display: grid;
    grid-template-columns: 300px 1fr;
    gap: 2rem;
    align-items: start;
}

.image-container {
    position: relative;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
    background: white;
}

.image-container img {
    width: 100%;
    height: 300px;
    object-fit: cover;
    display: block;
}

.image-container img.default-image {
    object-fit: contain; /* 👈 INI SOLUSINYA */
    background-color: #f8f9fa; /* Opsional: Latar belakang abu-abu */
}

.image-skeleton {
    width: 100%;
    height: 300px;
    background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
    background-size: 200% 100%;
    animation: shimmer 1.5s infinite;
}

@keyframes shimmer {
    0% { background-position: -200% 0; }
    100% { background-position: 200% 0; }
}

.info-details h4 {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text-dark);
    margin-bottom: 1.5rem;
}

.info-table {
    width: 100%;
}

.info-row {
    display: flex;
    padding: 1rem 0;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
}

.info-row:last-child {
    border-bottom: none;
}

.info-label {
    font-weight: 600;
    color: #666;
    width: 150px;
    flex-shrink: 0;
}

.info-value {
    font-weight: 600;
    color: var(--text-dark);
}

/* Sorting Section */
.sorting-section {
    background: var(--glass-bg);
    backdrop-filter: blur(10px);
    border-radius: 20px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
    border: 1px solid var(--glass-border);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}

.section-title {
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--text-dark);
    margin: 0;
}

.sort-buttons {
    display: flex;
    gap: 0.5rem;
}

.sort-btn {
    padding: 0.65rem 1.5rem;
    border-radius: 50px;
    border: 2px solid var(--accent-color);
    background: white;
    color: var(--accent-color);
    font-weight: 600;
    text-decoration: none;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.9rem;
}

.sort-btn:hover, .sort-btn.active {
    background: var(--accent-gradient);
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
}

/* Target Card */
.target-card {
    background: var(--glass-bg);
    backdrop-filter: blur(10px);
    border-radius: 20px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
    border: 1px solid var(--glass-border);
    transition: all 0.3s ease;
}

.target-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
}

.target-card.priority {
    border: 2px solid var(--danger-color);
    background: linear-gradient(135deg, rgba(220, 53, 69, 0.05), rgba(255, 255, 255, 0.95));
}

.target-header {
    display: flex;
    justify-content: space-between;
    align-items: start;
    margin-bottom: 1.5rem;
    gap: 1rem;
}

.target-title {
    font-size: 1.4rem;
    font-weight: 700;
    color: var(--text-dark);
    margin: 0;
}

.target-badges {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
    align-items: center;
}

.badge-priority {
    background: var(--danger-color);
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 50px;
    font-weight: 600;
    font-size: 0.85rem;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.countdown-badge {
    padding: 0.5rem 1rem;
    border-radius: 50px;
    font-weight: 600;
    font-size: 0.85rem;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.countdown-badge.danger {
    background: var(--danger-color);
    color: white;
}

.countdown-badge.warning {
    background: var(--warning-color);
    color: #333;
}

.countdown-badge.success {
    background: var(--success-color);
    color: white;
}

.target-body {
    display: grid;
    grid-template-columns: 1fr 2fr 1fr;
    gap: 2rem;
    align-items: center;
}

.target-info p {
    margin: 0 0 0.75rem 0;
    color: var(--text-dark);
}

.target-info strong {
    font-weight: 600;
}

.progress-section {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.progress-label {
    font-weight: 600;
    color: #666;
    font-size: 0.9rem;
}

.progress-custom {
    height: 30px;
    border-radius: 15px;
    background: rgba(0, 0, 0, 0.1);
    overflow: hidden;
    position: relative;
}

.progress-bar-custom {
    height: 100%;
    background: var(--accent-gradient);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.85rem;
    font-weight: 700;
    color: white;
    transition: width 0.3s ease;
    position: relative;
}

.progress-bar-custom::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
    animation: shimmerProgress 2s infinite;
}

@keyframes shimmerProgress {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}

.target-actions {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.btn-action {
    padding: 0.75rem 1.25rem;
    border-radius: 12px;
    border: none;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    font-size: 0.9rem;
}

.btn-primary-custom {
    background: var(--accent-gradient);
    color: white;
}

.btn-primary-custom:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
    color: white;
}

.btn-secondary-custom {
    background: #6c757d;
    color: white;
}

.btn-secondary-custom:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(108, 117, 125, 0.4);
    color: white;
}

/* Pagination */
.pagination-container {
    display: flex;
    justify-content: center;
    margin-top: 2rem;
}

.pagination-custom {
    display: flex;
    gap: 0.5rem;
    list-style: none;
    padding: 0;
    margin: 0;
}

.page-item-custom {
    margin: 0;
}

.page-link-custom {
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 45px;
    height: 45px;
    padding: 0 1rem;
    border-radius: 12px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
    background: var(--glass-bg);
    color: var(--text-dark);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
}

.page-link-custom:hover {
    background: var(--accent-gradient);
    color: white;
    transform: translateY(-2px);
    text-decoration: none;
}

.page-item-custom.active .page-link-custom {
    background: var(--accent-gradient);
    color: white;
}

.page-item-custom.disabled .page-link-custom {
    opacity: 0.5;
    cursor: not-allowed;
    pointer-events: none;
}

/* Empty State */
.empty-state {
    background: var(--glass-bg);
    backdrop-filter: blur(10px);
    border-radius: 25px;
    padding: 4rem 2rem;
    text-align: center;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
    border: 1px solid var(--glass-border);
}

.empty-icon {
    font-size: 5rem;
    color: var(--accent-color);
    margin-bottom: 1.5rem;
}

.empty-title {
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--text-dark);
    margin-bottom: 1rem;
}

.empty-text {
    color: #666;
    font-size: 1.1rem;
    margin: 0;
}

/* Back to Top Button */
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
    display: none;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    cursor: pointer;
    box-shadow: 0 4px 20px rgba(102, 126, 234, 0.4);
    transition: all 0.3s ease;
    z-index: 1000;
}

.back-to-top.show {
    display: flex;
}

.back-to-top:hover {
    transform: scale(1.1);
    box-shadow: 0 6px 25px rgba(102, 126, 234, 0.6);
}

/* Animations */
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

.loading {
    opacity: 0;
    animation: fadeInUp 0.6s ease-out forwards;
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .detail-container {
        padding: 1rem 0.5rem;
    }

    .page-header-glass {
        flex-direction: column;
        text-align: center;
        padding: 1.5rem;
        border-radius: 20px;
    }

    .page-title-main {
        font-size: 1.5rem;
        width: 100%;
    }

    .info-content {
        grid-template-columns: 1fr;
        gap: 1.5rem;
    }

    .image-container {
        max-width: 100%;
    }

    .image-container img {
        height: 250px;
    }

    .info-row {
        flex-direction: column;
        gap: 0.5rem;
    }

    .info-label {
        width: 100%;
    }

    .sorting-section {
        flex-direction: column;
        padding: 1.25rem;
        border-radius: 16px;
    }

    .section-title {
        font-size: 1.1rem;
        width: 100%;
        text-align: center;
    }

    .sort-buttons {
        width: 100%;
        justify-content: center;
    }

    .target-card {
        padding: 1.25rem;
        border-radius: 16px;
    }

    .target-header {
        flex-direction: column;
        gap: 1rem;
    }

    .target-badges {
        width: 100%;
        justify-content: center;
    }

    .target-body {
        grid-template-columns: 1fr;
        gap: 1.5rem;
    }

    .target-info {
        text-align: center;
    }

    .progress-section {
        order: 2;
    }

    .target-actions {
        order: 3;
    }

    .btn-action {
        width: 100%;
    }

    .pagination-custom {
        gap: 0.3rem;
    }

    .page-link-custom {
        min-width: 40px;
        height: 40px;
        font-size: 0.85rem;
    }

    .back-to-top {
        bottom: 20px;
        right: 20px;
        width: 45px;
        height: 45px;
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

    .empty-text {
        font-size: 1rem;
    }
}

/* Extra Small Mobile */
@media (max-width: 480px) {
    .sort-btn {
        font-size: 0.85rem;
        padding: 0.5rem 1rem;
    }

    .target-title {
        font-size: 1.2rem;
    }

    .badge-priority, .countdown-badge {
        font-size: 0.75rem;
        padding: 0.4rem 0.8rem;
    }
}

/* Touch Device Optimizations */
@media (hover: none) and (pointer: coarse) {
    .target-card:hover {
        transform: none;
    }

    .target-card:active {
        transform: scale(0.98);
    }

    .btn-action:hover {
        transform: none;
    }

    .btn-action:active {
        transform: scale(0.95);
    }
}
</style>

<div class="detail-container">
    <div class="page-header-glass loading">
        <h1 class="page-title-main">📦 Detail Barang</h1>
        <a href="master_barang.php" class="btn-back-custom">
            <i class="bi bi-arrow-left"></i> Kembali
        </a>
    </div>

    <div class="info-card loading" style="animation-delay: 0.2s;">
    <div class="info-content">
        <div class="image-container">
            <div class="image-skeleton"></div>
            <?php
                // Logika untuk menentukan gambar default
                $is_default = empty($barang['gambar']);
                $img_src = $base_url_uploads . htmlspecialchars($is_default ? 'default.png' : $barang['gambar']);
                $img_class = $is_default ? 'default-image' : '';
            ?>
            <img src="<?php echo $img_src; ?>"
                 class="<?php echo $img_class; ?>"
                 alt="<?php echo htmlspecialchars($barang['nama_barang']); ?>"
                 loading="lazy"
                 onload="this.previousElementSibling.style.display='none'"
                 onerror="this.onerror=null; this.src='<?php echo $base_url_uploads; ?>default.png'; this.classList.add('default-image'); this.previousElementSibling.style.display='none';">
        </div>
            <div class="info-details">
                <h4>Informasi Barang</h4>
                <div class="info-table">
                    <div class="info-row">
                        <span class="info-label">Nama Barang</span>
                        <span class="info-value"><?php echo htmlspecialchars($barang['nama_barang']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Kategori</span>
                        <span class="info-value"><?php echo htmlspecialchars($barang['nama_kategori'] ?? 'N/A'); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="sorting-section loading" style="animation-delay: 0.4s;">
        <h4 class="section-title">Target Produksi (On-Going)</h4>
        <div class="sort-buttons">
            <a href="?id=<?php echo $id_barang; ?>&sort=terbaru<?php echo isset($_GET['halaman']) ? '&halaman='.$_GET['halaman'] : ''; ?>" 
               class="sort-btn <?php echo ($sort === 'terbaru') ? 'active' : ''; ?>">
                <i class="bi bi-sort-down"></i> Terbaru
            </a>
            <a href="?id=<?php echo $id_barang; ?>&sort=terlama<?php echo isset($_GET['halaman']) ? '&halaman='.$_GET['halaman'] : ''; ?>" 
               class="sort-btn <?php echo ($sort === 'terlama') ? 'active' : ''; ?>">
                <i class="bi bi-sort-up"></i> Terlama
            </a>
        </div>
    </div>

    <?php if (empty($targets)): ?>
        <div class="empty-state loading" style="animation-delay: 0.6s;">
            <div class="empty-icon"><i class="bi bi-inbox"></i></div>
            <h3 class="empty-title">Tidak Ada Target</h3>
            <p class="empty-text">Tidak ada target produksi yang sedang berjalan untuk barang ini.</p>
        </div>
    <?php else: ?>
        <?php foreach ($targets as $index => $target): ?>
            <?php 
                $progress = calculate_progress($pdo, $target['id_target']);
                // Ganti logika ini dari 'prioritas' (teks) ke 'is_priority' (angka)
                $is_priority = (int)$target['is_priority'] === 1; 
                $countdown = $is_priority ? get_countdown($target['priority_deadline']) : null;
            ?>
            <div class="target-card <?php echo $is_priority ? 'priority' : ''; ?> loading" style="animation-delay: <?php echo 0.6 + ($index * 0.1); ?>s;">
                <div class="target-header">
                    <h5 class="target-title"><?php echo htmlspecialchars($target['nama_permintaan']); ?></h5>
                    <?php if ($is_priority): ?>
                        <div class="target-badges">
                            <span class="badge-priority">
                                <i class="bi bi-star-fill"></i> Prioritas
                            </span>
                            <?php if ($countdown): ?>
                                <span class="countdown-badge <?php echo $countdown['class']; ?>">
                                    <i class="bi bi-clock"></i> <?php echo $countdown['text']; ?>
                                </span>
                            <?php endif; ?>

                            <?php if (!empty($target['priority_deadline'])): ?>
                                <small style="color: #666;">
                                    Tenggat: <?php echo date('d M Y', strtotime($target['priority_deadline'])); ?>
                                </small>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="target-body">
                    <div class="target-info">
                        <p><strong>Jumlah Unit:</strong> <?php echo number_format($target['jumlah_unit']); ?></p>
                        <p><strong>Tanggal Dibuat:</strong> <?php echo date('d M Y', strtotime($target['created_at'])); ?></p>
                    </div>

                    <div class="progress-section">
                        <span class="progress-label">Progres Produksi</span>
                        <div class="progress-custom">
                            <div class="progress-bar-custom" style="width: <?php echo $progress; ?>%;">
                                <?php echo $progress; ?>%
                            </div>
                        </div>
                    </div>

                    <div class="target-actions">
                        <a href="material.php?id_target=<?php echo $target['id_target']; ?>" class="btn-action btn-primary-custom">
                            <i class="bi bi-eye"></i> Lihat Progress
                        </a>
                        <a href="../management_laporan/history_laporan.php?id_target=<?php echo $target['id_target']; ?>" class="btn-action btn-secondary-custom">
                            <i class="bi bi-clock-history"></i> History
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if ($jumlah_halaman > 1): ?>
        <div class="pagination-container loading" style="animation-delay: <?php echo 0.6 + (count($targets) * 0.1); ?>s;">
            <ul class="pagination-custom">
                <?php
                $url_params = ['id' => $id_barang, 'sort' => $sort];
                ?>

                <li class="page-item-custom <?php echo ($halaman_aktif <= 1) ? 'disabled' : ''; ?>">
                    <a class="page-link-custom" href="?<?php echo http_build_query(array_merge($url_params, ['halaman' => $halaman_aktif - 1])); ?>">
                        <i class="bi bi-chevron-left"></i>
                    </a>
                </li>

                <?php
                $range = 2;
                $start = max(1, $halaman_aktif - $range);
                $end = min($jumlah_halaman, $halaman_aktif + $range);

                if ($start > 1) {
                    echo '<li class="page-item-custom"><a class="page-link-custom" href="?' . http_build_query(array_merge($url_params, ['halaman' => 1])) . '">1</a></li>';
                    if ($start > 2) echo '<li class="page-item-custom disabled"><span class="page-link-custom">...</span></li>';
                }

                for ($i = $start; $i <= $end; $i++):
                ?>
                <li class="page-item-custom <?php echo ($i == $halaman_aktif) ? 'active' : ''; ?>">
                    <a class="page-link-custom" href="?<?php echo http_build_query(array_merge($url_params, ['halaman' => $i])); ?>">
                        <?php echo $i; ?>
                    </a>
                </li>
                <?php
                endfor;

                if ($end < $jumlah_halaman) {
                    if ($end < $jumlah_halaman - 1) echo '<li class="page-item-custom disabled"><span class="page-link-custom">...</span></li>';
                    echo '<li class="page-item-custom"><a class="page-link-custom" href="?' . http_build_query(array_merge($url_params, ['halaman' => $jumlah_halaman])) . '">' . $jumlah_halaman . '</a></li>';
                }
                ?>

                <li class="page-item-custom <?php echo ($halaman_aktif >= $jumlah_halaman) ? 'disabled' : ''; ?>">
                    <a class="page-link-custom" href="?<?php echo http_build_query(array_merge($url_params, ['halaman' => $halaman_aktif + 1])); ?>">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                </li>
            </ul>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<button class="back-to-top" id="backToTop" onclick="scrollToTop()">
    <i class="bi bi-arrow-up"></i>
</button>

<script>
// Back to Top Button
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

// Lazy Loading Enhancement
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
        rootMargin: '50px'
    });
    
    const images = document.querySelectorAll('.image-container img');
    images.forEach(img => imageObserver.observe(img));
}
</script>

<?php include_once '../../../templates/footer.php'; ?>