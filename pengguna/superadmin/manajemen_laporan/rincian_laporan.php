<?php
// (Nama file baru: rincian_laporan.php - UNTUK SUPERADMIN)

// [DIUBAH] Menggunakan header superadmin
include_once '../../../templates/header_superadmin.php'; 
include_once '../../../system/database_connection.php';

// Helper function dari laporan_detail.php untuk mengambil semua bulan
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

// 1. Validasi ID dari URL
if (!isset($_GET['id_target']) || !is_numeric($_GET['id_target'])) {
    echo "<script>alert('ID Target tidak valid!'); window.location.href='../dashboard.php';</script>";
    exit;
}
$id_target = (int)$_GET['id_target'];

// Ambil bulan dari URL, default ke bulan saat ini jika tidak ada
$selected_month = isset($_GET['bulan']) ? $_GET['bulan'] : date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $selected_month)) {
    $selected_month = date('Y-m');
}
$nama_bulan_tahun = date('F Y', strtotime($selected_month . '-01'));
$days_in_month = cal_days_in_month(CAL_GREGORIAN, date('m', strtotime($selected_month)), date('Y', strtotime($selected_month)));

$history_by_alur = [];
$min_date = null;
$total_alur = 0;
$total_material = 0;
$available_months = [];
$durasi_hari = 0;

try {
    // 2. Ambil info header target produksi (Ditambah tanggal mulai & selesai)
    $header_stmt = $pdo->prepare("
        SELECT pt.nama_permintaan, pt.jumlah_unit, mb.nama_barang, pt.id_barang,
               pt.created_at AS tanggal_mulai, pt.tanggal_selesai
        FROM production_targets pt
        JOIN master_barang mb ON pt.id_barang = mb.id_barang
        WHERE pt.id_target = :id_target
    ");
    $header_stmt->execute([':id_target' => $id_target]);
    $header_info = $header_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$header_info) {
        throw new Exception("Data Target tidak ditemukan.");
    }

    // Hitung Durasi Pengerjaan
    if ($header_info['tanggal_mulai'] && $header_info['tanggal_selesai']) {
        $tgl_mulai = new DateTime($header_info['tanggal_mulai']);
        $tgl_selesai = new DateTime($header_info['tanggal_selesai']);
        $durasi = $tgl_mulai->diff($tgl_selesai);
        $durasi_hari = $durasi->days;
    }

    // 3. Ambil daftar bulan yang ada laporannya (UNTUK LIMIT MONTH PICKER)
    $available_months_stmt = $pdo->prepare("
        SELECT DISTINCT DATE_FORMAT(lh.tanggal_laporan, '%Y-%m') AS bulan_tahun
        FROM laporan_harian lh
        JOIN target_material tm ON lh.id_material = tm.id_material
        WHERE tm.id_target = :id_target
        ORDER BY bulan_tahun ASC
    ");
    $available_months_stmt->execute([':id_target' => $id_target]);
    $available_months = $available_months_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Ambil semua bulan untuk tombol download lengkap
    $all_months_for_download = getAllProductionMonths($pdo, $id_target);

    // Tentukan min dan max date untuk month picker
    $min_month = !empty($available_months) ? $available_months[0]['bulan_tahun'] : date('Y-m');
    $max_month = !empty($available_months) ? end($available_months)['bulan_tahun'] : date('Y-m');

    // 4. TAB RINGKAS - Query (Sudah di-fix dengan JOIN)
    $sql_ringkas = "
        SELECT
            tm.id_material,
            mk.nama_komponen,
            ma.nama_alur,
            ma.id_alur,
            tm.jumlah_per_unit,
            (tm.jumlah_per_unit * pt.jumlah_unit) AS kebutuhan_total,
            COALESCE(lh_total.total_selesai, 0) AS total_selesai_keseluruhan,
            'Selesai' AS status_pengerjaan, 
            GROUP_CONCAT(DISTINCT u.full_name SEPARATOR ', ') AS penanggung_jawab,
            ma.urutan
        FROM target_material tm
        JOIN master_komponen mk ON tm.id_komponen = mk.id_komponen
        JOIN master_alur ma ON tm.id_alur = ma.id_alur
        JOIN production_targets pt ON tm.id_target = pt.id_target
        JOIN alur_barang mba ON pt.id_barang = mba.id_barang AND ma.id_alur = mba.id_alur
        LEFT JOIN (
            SELECT id_material, SUM(jumlah_selesai) as total_selesai
            FROM laporan_harian
            GROUP BY id_material
        ) lh_total ON tm.id_material = lh_total.id_material
        LEFT JOIN admin_tahapan_access ata ON ma.id_alur = ata.id_tahapan
        LEFT JOIN users u ON ata.id_user = u.id
        WHERE tm.id_target = :id_target
        GROUP BY tm.id_material, mk.nama_komponen, ma.nama_alur, ma.id_alur, kebutuhan_total, ma.urutan
        ORDER BY ma.urutan ASC, mk.nama_komponen ASC
    ";

    $stmt_ringkas = $pdo->prepare($sql_ringkas);
    $stmt_ringkas->execute([':id_target' => $id_target]);
    $materials_raw_ringkas = $stmt_ringkas->fetchAll(PDO::FETCH_ASSOC);

    // Mengelompokkan material berdasarkan alur untuk TAB RINGKAS
    $materials_by_alur_ringkas = [];
    $alur_set = [];
    $total_kebutuhan_all = 0;
    $total_selesai_all = 0;
    
    foreach ($materials_raw_ringkas as $material) {
        $nama_alur = $material['nama_alur'];
        $alur_set[$nama_alur] = true;
        
        if (!isset($materials_by_alur_ringkas[$nama_alur])) {
            $materials_by_alur_ringkas[$nama_alur] = [
                'status' => $material['status_pengerjaan'],
                'pic'    => $material['penanggung_jawab'] ?? 'Tidak ditentukan',
                'items'  => []
            ];
        }
        $materials_by_alur_ringkas[$nama_alur]['items'][] = $material;
        
        $total_kebutuhan_all += (int)$material['kebutuhan_total'];
        $total_selesai_all += (int)$material['total_selesai_keseluruhan'];
    }
    
    $total_alur = count($alur_set);
    $total_material = count($materials_raw_ringkas);

    // 5. TAB DETAIL - Query untuk bulan terpilih (Sudah di-fix dengan JOIN)
    $sql_detail = "
         SELECT
            tm.id_material,
            mk.nama_komponen,
            ma.nama_alur,
            ma.id_alur,
            tm.jumlah_per_unit,
            pt.jumlah_unit AS target_jumlah_unit,
            'Selesai' AS status_pengerjaan, 
            GROUP_CONCAT(DISTINCT u.full_name SEPARATOR ', ') AS penanggung_jawab,
            ma.urutan,
            GROUP_CONCAT(
                CASE
                    WHEN DATE_FORMAT(lh_harian.tanggal_laporan, '%Y-%m') = :bulan_harian THEN CONCAT(DAY(lh_harian.tanggal_laporan), ':', lh_harian.jumlah_selesai)
                    ELSE NULL
                END
                SEPARATOR ';'
            ) as harian
        FROM target_material tm
        JOIN master_komponen mk ON tm.id_komponen = mk.id_komponen
        JOIN master_alur ma ON tm.id_alur = ma.id_alur
        JOIN production_targets pt ON tm.id_target = pt.id_target
        JOIN alur_barang mba ON pt.id_barang = mba.id_barang AND ma.id_alur = mba.id_alur
        LEFT JOIN admin_tahapan_access ata ON ma.id_alur = ata.id_tahapan
        LEFT JOIN users u ON ata.id_user = u.id
        LEFT JOIN laporan_harian lh_harian ON tm.id_material = lh_harian.id_material AND DATE_FORMAT(lh_harian.tanggal_laporan, '%Y-%m') = :bulan_join
        WHERE tm.id_target = :id_target
        GROUP BY tm.id_material, mk.nama_komponen, ma.nama_alur, ma.id_alur, tm.jumlah_per_unit, pt.jumlah_unit, ma.urutan
        ORDER BY ma.urutan ASC, mk.nama_komponen ASC
    ";

    $stmt_detail = $pdo->prepare($sql_detail);
    $stmt_detail->execute([
        ':id_target' => $id_target,
        ':bulan_harian' => $selected_month,
        ':bulan_join' => $selected_month
    ]);
    $materials_raw_detail = $stmt_detail->fetchAll(PDO::FETCH_ASSOC);

    // Mengelompokkan material berdasarkan alur untuk TAB DETAIL
    $materials_by_alur_detail = [];
    foreach ($materials_raw_detail as $material) {
        $nama_alur = $material['nama_alur'];
        if (!isset($materials_by_alur_detail[$nama_alur])) {
            $materials_by_alur_detail[$nama_alur] = [
                'status' => $material['status_pengerjaan'],
                'pic'    => $material['penanggung_jawab'] ?? 'Tidak ditentukan',
                'items'  => []
            ];
        }

        $harian_array = [];
        $total_selesai_bulan_ini = 0;
        if (!empty($material['harian'])) {
            $pairs = explode(';', $material['harian']);
            foreach ($pairs as $pair) {
                if (strpos($pair, ':') !== false) {
                    list($hari, $jumlah) = explode(':', $pair);
                    $hari_int = (int)$hari;
                    if ($hari_int > 0 && $hari_int <= $days_in_month) {
                        $jumlah_int = (int)$jumlah;
                        $harian_array[$hari_int] = ($harian_array[$hari_int] ?? 0) + $jumlah_int;
                        $total_selesai_bulan_ini += $jumlah_int;
                    }
                }
            }
        }
        $material['harian_processed'] = $harian_array;
        $material['total_selesai_bulan_ini'] = $total_selesai_bulan_ini;
        $materials_by_alur_detail[$nama_alur]['items'][] = $material;
    }

} catch (Exception $e) {
    error_log("Database Error: " . $e->getMessage());
    die("Terjadi kesalahan saat mengambil data. Silakan coba lagi nanti.");
}

// Helper function untuk navigasi bulan
function getMonthNavigation($current_month, $available_months) {
    $current_index = array_search($current_month, array_column($available_months, 'bulan_tahun'));
    $prev_month = null;
    $next_month = null;
    
    if ($current_index !== false) {
        if ($current_index > 0) {
            $prev_month = $available_months[$current_index - 1]['bulan_tahun'];
        }
        if ($current_index < count($available_months) - 1) {
            $next_month = $available_months[$current_index + 1]['bulan_tahun'];
        }
    }
    
    return ['prev' => $prev_month, 'next' => $next_month];
}

$month_nav = getMonthNavigation($selected_month, $available_months);
?>

<style>
/* Modern Glassmorphism Theme - Consistent with history_laporan.php */
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

.material-container {
    max-width: 1400px;
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
}

.page-title-main {
    font-size: 2rem;
    font-weight: 700;
    color: var(--text-dark);
    margin: 0 0 0.5rem 0;
}

.page-subtitle {
    color: #666;
    font-size: 1.1rem;
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

/* Stats Dashboard */
.stats-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: var(--glass-bg);
    backdrop-filter: blur(10px);
    border-radius: 20px;
    padding: 1.5rem;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    border: 1px solid var(--glass-border);
    text-align: center;
    transition: transform 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
}

.stat-icon {
    font-size: 2rem;
    color: var(--accent-color);
    margin-bottom: 0.5rem;
}

.stat-value {
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--text-dark);
    margin: 0;
}

.stat-label {
    color: #666;
    font-size: 0.9rem;
    margin: 0;
}

/* Download Section - Modern & Compact */
.download-section {
    background: var(--glass-bg);
    backdrop-filter: blur(10px);
    border-radius: 20px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
    border: 1px solid var(--glass-border);
}

.download-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.download-icon {
    background: var(--accent-gradient);
    color: white;
    width: 40px;
    height: 40px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
}

.download-title {
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--text-dark);
    margin: 0;
}

.download-form {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 1rem;
    align-items: center;
}

.form-group-download {
    margin: 0;
}

.form-label-download {
    font-weight: 600;
    color: var(--text-dark);
    margin-bottom: 0.5rem;
    display: block;
    font-size: 0.9rem;
}

.form-select-download {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 2px solid rgba(102, 126, 234, 0.3);
    border-radius: 12px;
    font-size: 1rem;
    transition: all 0.3s ease;
    background: white;
}

.form-select-download:focus {
    outline: none;
    border-color: var(--accent-color);
    box-shadow: 0 0 15px rgba(102, 126, 234, 0.2);
}

.btn-download {
    background: var(--success-color);
    color: white;
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 12px;
    font-weight: 600;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-download:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(40, 167, 69, 0.4);
}

.btn-pdf {
            background: var(--danger-color); /* Variabel warna merah dari root */
        }

        .btn-pdf:hover {
            background: var(--danger-color);
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.4);
        }

/* Tabs */
.nav-tabs-custom {
    background: var(--glass-bg);
    backdrop-filter: blur(10px);
    border-radius: 20px;
    padding: 0.5rem;
    margin-bottom: 2rem;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    border: 1px solid var(--glass-border);
    display: flex;
    gap: 0.5rem;
}

.nav-link-custom {
    flex: 1;
    padding: 1rem;
    border: none;
    background: transparent;
    color: var(--text-dark);
    font-weight: 600;
    border-radius: 15px;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.nav-link-custom:hover {
    background: rgba(102, 126, 234, 0.1);
}

.nav-link-custom.active {
    background: var(--accent-gradient);
    color: white;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
}

/* Month Filter Section */
.month-filter-section {
    background: var(--glass-bg);
    backdrop-filter: blur(10px);
    border-radius: 20px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
    border: 1px solid var(--glass-border);
}

.month-filter-content {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 1rem;
    flex-wrap: wrap;
}

.month-nav-btn {
    background: var(--glass-bg);
    color: var(--accent-color);
    border: 2px solid var(--accent-color);
    width: 45px;
    height: 45px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 1.2rem;
}

.month-nav-btn:hover:not(:disabled) {
    background: var(--accent-gradient);
    color: white;
    transform: scale(1.1);
}

.month-nav-btn:disabled {
    opacity: 0.3;
    cursor: not-allowed;
}

.month-picker-group {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.month-input-custom {
    padding: 0.75rem 1rem;
    border: 2px solid rgba(102, 126, 234, 0.3);
    border-radius: 12px;
    font-size: 1rem;
    transition: all 0.3s ease;
    background: white;
}

.month-input-custom:focus {
    outline: none;
    border-color: var(--accent-color);
    box-shadow: 0 0 15px rgba(102, 126, 234, 0.2);
}

.btn-filter-month {
    background: var(--accent-gradient);
    color: white;
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 12px;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-filter-month:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
}

/* Accordion Alur Sections */
.alur-accordion {
    margin-bottom: 1.5rem;
}

.alur-card {
    background: var(--glass-bg);
    backdrop-filter: blur(10px);
    border-radius: 20px;
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
    border: 1px solid var(--glass-border);
    overflow: hidden;
    transition: all 0.3s ease;
}

.alur-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem;
    cursor: pointer;
    transition: background 0.3s ease;
    position: relative; /* <-- TAMBAHKAN INI */
     z-index: 2;
}

.alur-header:hover {
    background: rgba(102, 126, 234, 0.05);
}

.alur-title {
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--text-dark);
    margin: 0;
}

.alur-badges {
    display: flex;
    gap: 0.75rem;
    align-items: center;
    flex-wrap: wrap;
}

.status-badge {
    padding: 0.5rem 1rem;
    border-radius: 50px;
    font-weight: 600;
    font-size: 0.85rem;
}

.status-success {
    background: var(--success-color);
    color: white;
}

.status-warning {
    background: var(--warning-color);
    color: #333;
}

.pic-badge {
    background: #343a40;
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 50px;
    font-size: 0.85rem;
}

.toggle-icon {
    font-size: 1.5rem;
    color: var(--accent-color);
    transition: transform 0.3s ease;
}

.alur-header.collapsed .toggle-icon {
    transform: rotate(-90deg);
}

.alur-body {
    padding: 0 1.5rem 1.5rem;
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.3s ease;
    position: relative; /* <-- TAMBAHKAN INI */
    z-index: 1;
}

.alur-body.show {
    max-height: 10000px;
}

/* Desktop Table Styling */
.table-custom {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    border-radius: 12px;
    overflow: hidden;
}

.table-custom thead {
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
}

.table-custom th {
    padding: 1rem;
    font-weight: 600;
    color: var(--text-dark);
    text-align: center;
    font-size: 0.9rem;
}

.table-custom td {
    padding: 1rem;
    border-top: 1px solid rgba(0, 0, 0, 0.05);
    font-size: 0.9rem;
}

.table-custom tbody tr:hover {
    background: rgba(102, 126, 234, 0.05);
}

/* Progress Bar */
.progress-custom {
    height: 20px;
    border-radius: 10px;
    background: rgba(0, 0, 0, 0.1);
    overflow: hidden;
}

.progress-bar-custom {
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    font-weight: 600;
    transition: width 0.3s ease;
}

/* Table Detail - Horizontal Scroll */
.table-detail-wrapper {
    overflow-x: auto;
    border-radius: 12px;
}

.table-detail {
    min-width: 1200px;
    font-size: 0.85rem;
}

.table-detail th {
    position: sticky;
    top: 0;
    background: #343a40;
    color: white;
    z-index: 10;
}

/* Freeze Sisa Column in Mobile */
.freeze-sisa {
    position: sticky;
    right: 0;
    background: white;
    box-shadow: -2px 0 5px rgba(0, 0, 0, 0.1);
    z-index: 5;
}

.freeze-sisa-header {
    position: sticky;
    right: 0;
    background: #343a40 !important;
    box-shadow: -2px 0 5px rgba(0, 0, 0, 0.2);
    z-index: 11;
}

/* Mobile Cards (Ringkas Tab) */
.mobile-cards {
    display: none;
    flex-direction: column;
    gap: 1rem;
}

.mobile-card {
    background: white;
    border-radius: 12px;
    padding: 1rem;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
}

.mobile-card-row {
    display: flex;
    justify-content: space-between;
    padding: 0.75rem 0;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
}

.mobile-card-row:last-child {
    border-bottom: none;
}

.mobile-label {
    font-weight: 600;
    color: #666;
    font-size: 0.85rem;
}

.mobile-value {
    font-weight: 600;
    color: var(--text-dark);
    text-align: right;
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
    .material-container {
        padding: 1rem 0.5rem;
    }

    .page-header-glass {
        padding: 1.5rem;
        border-radius: 20px;
    }

    .page-title-main {
        font-size: 1.5rem;
    }

    .stats-container {
        grid-template-columns: 1fr 1fr;
        gap: 0.75rem;
    }

    .download-form {
        grid-template-columns: 1fr;
    }

    .nav-tabs-custom {
        flex-direction: column;
    }

    .month-filter-content {
        flex-direction: column;
    }

    .month-picker-group {
        width: 100%;
        flex-direction: column;
    }

    .month-input-custom {
        width: 100%;
    }

    .alur-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }

    .alur-badges {
        width: 100%;
    }

    /* Hide desktop table, show mobile cards for Ringkas */
    #tab-ringkas .table-custom {
        display: none;
    }

    #tab-ringkas .mobile-cards {
        display: flex;
    }

    /* For Detail tab - show table with horizontal scroll */
    .table-detail-wrapper {
        margin: 0 -1.5rem;
        padding: 0 1.5rem;
    }

    .back-to-top {
        bottom: 20px;
        right: 20px;
        width: 45px;
        height: 45px;
    }
}

/* Desktop: Hide mobile cards */
@media (min-width: 769px) {
    .mobile-cards {
        display: none;
    }
}
</style>

<div class="material-container">
    <div class="page-header-glass loading">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
            <div>
                <h1 class="page-title-main">📋 Rincian Laporan Selesai</h1>
                <p class="page-subtitle">
                    <?php echo htmlspecialchars($header_info['nama_barang']); ?> - 
                    <?php echo htmlspecialchars($header_info['nama_permintaan']); ?>
                </p>
            </div>
            <a href="laporan_detail.php?id_barang=<?php echo $header_info['id_barang']; ?>" class="btn-back-custom">
                <i class="bi bi-arrow-left"></i> Kembali ke Laporan
            </a>
        </div>
    </div>

    <div class="stats-container loading" style="animation-delay: 0.2s;">
        <div class="stat-card">
            <div class="stat-icon"><i class="bi bi-diagram-3"></i></div>
            <p class="stat-value"><?php echo $total_alur; ?></p>
            <p class="stat-label">Total Alur</p>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="bi bi-box-seam"></i></div>
            <p class="stat-value"><?php echo $total_material; ?></p>
            <p class="stat-label">Total Material</p>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="bi bi-stopwatch"></i></div>
            <p class="stat-value"><?php echo $durasi_hari; ?> <span style="font-size: 1.2rem;">Hari</span></p>
            <p class="stat-label">Durasi Pengerjaan</p>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="bi bi-check-all"></i></div>
            <p class="stat-value"><?php echo number_format($total_selesai_all); ?></p>
            <p class="stat-label">Total Unit Selesai (Pcs)</p>
        </div>
    </div>

    <?php if (!empty($all_months_for_download)): ?>
    <div class="download-section loading" style="animation-delay: 0.4s;">
        <div class="download-header">
            <div class="download-icon"><i class="bi bi-download"></i></div>
            <h5 class="download-title">Download Laporan Lengkap</h5>
        </div>

        <div class="download-form">
            
            <div class="form-group-download">
                <p class="mb-0" style="color: #666;">Pilih format download untuk semua data produksi (Total: <?php echo count($all_months_for_download); ?> bulan).</p>
                
                <form id="form-download-excel" action="proses_download_laporan.php" method="post" target="_blank" style="display: none;">
                    <input type="hidden" name="id_target" value="<?php echo $id_target; ?>">
                    <?php foreach ($all_months_for_download as $month): ?>
                        <input type="hidden" name="bulan_laporan[]" value="<?php echo htmlspecialchars($month); ?>">
                    <?php endforeach; ?>
                </form>

                <form id="form-download-pdf" action="proses_download_laporan_pdf.php" method="post" target="_blank" style="display: none;">
                    <input type="hidden" name="id_target" value="<?php echo $id_target; ?>">
                    </form>
            </div>

            <div class="button-group d-flex gap-2 justify-content-end">
                <button type="submit" form="form-download-excel" class="btn-download" <?php echo empty($all_months_for_download) ? 'disabled' : ''; ?>>
                    <i class="bi bi-file-excel-fill"></i> Download Excel
                </button>
                
                <button type="submit" form="form-download-pdf" class="btn-download btn-pdf" <?php echo empty($all_months_for_download) ? 'disabled' : ''; ?>>
                    <i class="bi bi-file-pdf-fill"></i> Download PDF
                </button>
            </div>

        </div>
    </div>
    <?php endif; ?>

    <div class="nav-tabs-custom loading" style="animation-delay: 0.6s;">
        <button class="nav-link-custom active" onclick="switchTab('ringkas')">
            <i class="bi bi-list-ul"></i> Ringkasan
        </button>
        <button class="nav-link-custom" onclick="switchTab('detail')">
            <i class="bi bi-calendar3"></i> Detail per Bulan
        </button>
    </div>

    <div id="tab-content">
        <div id="tab-ringkas">
            <?php if (empty($materials_by_alur_ringkas)): ?>
                <div class="empty-state">
                    <div class="empty-icon"><i class="bi bi-inbox"></i></div>
                    <h3>Belum Ada Data Material</h3>
                    <p>Belum ada material yang di-assign untuk target produksi ini.</p>
                </div>
            <?php else: ?>
                <?php foreach ($materials_by_alur_ringkas as $nama_alur => $data_alur): ?>
                <div class="alur-accordion loading" style="animation-delay: <?php echo 0.8 + (array_search($nama_alur, array_keys($materials_by_alur_ringkas)) * 0.1); ?>s;">
                    <div class="alur-card">
                        <div class="alur-header" onclick="toggleAlur(this)">
                            <h5 class="alur-title"><?php echo htmlspecialchars($nama_alur); ?></h5>
                            <div class="alur-badges">
                                <span class="status-badge status-success">
                                    Selesai
                                </span>
                                <span class="pic-badge">
                                    <i class="bi bi-person"></i> <?php echo htmlspecialchars($data_alur['pic']); ?>
                                </span>
                                <i class="bi bi-chevron-down toggle-icon"></i>
                            </div>
                        </div>
                        <div class="alur-body">
                            <table class="table-custom">
                                <thead>
                                    <tr>
                                        <th style="width: 5%;">No</th>
                                        <th>Nama Komponen</th>
                                        <th style="width: 15%;">Total Kebutuhan</th>
                                        <th style="width: 15%;">Telah Selesai</th>
                                        <th style="width: 10%;">Sisa</th>
                                        <th style="width: 20%;">Progress</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $no = 1; foreach ($data_alur['items'] as $mat): ?>
                                        <?php
                                            $kebutuhan = (int)$mat['kebutuhan_total'];
                                            $selesai = (int)$mat['total_selesai_keseluruhan'];
                                            $sisa = max(0, $kebutuhan - $selesai);
                                            $percentage = $kebutuhan > 0 ? min(100, round(($selesai / $kebutuhan) * 100)) : 0;
                                            $progress_class = 'bg-success';
                                        ?>
                                        <tr>
                                            <td class="text-center"><?php echo $no++; ?></td>
                                            <td><?php echo htmlspecialchars($mat['nama_komponen']); ?></td>
                                            <td class="text-center"><?php echo number_format($kebutuhan); ?></td>
                                            <td class="text-center fw-bold"><?php echo number_format($selesai); ?></td>
                                            <td class="text-center fw-bold text-success">
                                                <?php echo number_format($sisa); ?>
                                            </td>
                                            <td>
                                                <div class="progress-custom">
                                                    <div class="progress-bar-custom <?php echo $progress_class; ?>" style="width: <?php echo $percentage; ?>%;">
                                                        <?php if ($percentage > 10): echo $percentage . '%'; endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>

                            <div class="mobile-cards">
                                <?php foreach ($data_alur['items'] as $mat): ?>
                                    <?php
                                        $kebutuhan = (int)$mat['kebutuhan_total'];
                                        $selesai = (int)$mat['total_selesai_keseluruhan'];
                                        $sisa = max(0, $kebutuhan - $selesai);
                                        $percentage = $kebutuhan > 0 ? min(100, round(($selesai / $kebutuhan) * 100)) : 0;
                                        $progress_class = 'bg-success';
                                    ?>
                                    <div class="mobile-card">
                                        <div class="mobile-card-row">
                                            <span class="mobile-label">Komponen</span>
                                            <span class="mobile-value"><?php echo htmlspecialchars($mat['nama_komponen']); ?></span>
                                        </div>
                                        <div class="mobile-card-row">
                                            <span class="mobile-label">Kebutuhan</span>
                                            <span class="mobile-value"><?php echo number_format($kebutuhan); ?></span>
                                        </div>
                                        <div class="mobile-card-row">
                                            <span class="mobile-label">Selesai</span>
                                            <span class="mobile-value"><?php echo number_format($selesai); ?></span>
                                        </div>
                                        <div class="mobile-card-row">
                                            <span class="mobile-label">Sisa</span>
                                            <span class="mobile-value" style="color: var(--success-color);">
                                                <?php echo number_format($sisa); ?>
                                            </span>
                                        </div>
                                        <div class="mobile-card-row">
                                            <span class="mobile-label">Progress</span>
                                            <div style="flex: 1; max-width: 150px;">
                                                <div class="progress-custom">
                                                    <div class="progress-bar-custom <?php echo $progress_class; ?>" style="width: <?php echo $percentage; ?>%;">
                                                        <?php echo $percentage; ?>%
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div id="tab-detail" style="display: none;">
            <div class="month-filter-section loading" style="animation-delay: 0.8s;">
                <div class="month-filter-content">
                    <?php if (count($available_months) > 1): ?>
                        <button type="button" class="month-nav-btn" onclick="navigateMonth('prev')" <?php echo !$month_nav['prev'] ? 'disabled' : ''; ?>>
                            <i class="bi bi-chevron-left"></i>
                        </button>
                    <?php endif; ?>
                    
                    <form method="get" class="month-picker-group">
                        <input type="hidden" name="id_target" value="<?php echo $id_target; ?>">
                        <input type="month" name="bulan" class="month-input-custom" 
                               value="<?php echo $selected_month; ?>"
                               min="<?php echo $min_month; ?>"
                               max="<?php echo $max_month; ?>">
                        <button type="submit" class="btn-filter-month">
                            <i class="bi bi-calendar-check"></i> Tampilkan
                        </button>
                    </form>

                    <?php if (count($available_months) > 1): ?>
                        <button type="button" class="month-nav-btn" onclick="navigateMonth('next')" <?php echo !$month_nav['next'] ? 'disabled' : ''; ?>>
                            <i class="bi bi-chevron-right"></i>
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (empty($materials_by_alur_detail)): ?>
                <div class="empty-state">
                    <div class="empty-icon"><i class="bi bi-inbox"></i></div>
                    <h3>Tidak Ada Data</h3>
                    <p>Tidak ada data material untuk bulan <?php echo htmlspecialchars($nama_bulan_tahun); ?>.</p>
                </div>
            <?php else: ?>
                <?php foreach ($materials_by_alur_detail as $nama_alur => $data_alur): ?>
                <div class="alur-accordion loading" style="animation-delay: <?php echo 1 + (array_search($nama_alur, array_keys($materials_by_alur_detail)) * 0.1); ?>s;">
                    <div class="alur-card">
                        <div class="alur-header" onclick="toggleAlur(this)">
                            <h5 class="alur-title"><?php echo htmlspecialchars($nama_alur); ?></h5>
                            <div class="alur-badges">
                                <span class="status-badge status-success">
                                    Selesai
                                </span>
                                <span class="pic-badge">
                                    <i class="bi bi-person"></i> <?php echo htmlspecialchars($data_alur['pic']); ?>
                                </span>
                                <i class="bi bi-chevron-down toggle-icon"></i>
                            </div>
                        </div>
                        <div class="alur-body">
                            <div class="table-detail-wrapper">
                                <table class="table-custom table-detail">
                                    <thead>
                                        <tr>
                                            <th rowspan="2">No</th>
                                            <th rowspan="2" style="min-width: 150px;">Nama Komponen</th>
                                            <th rowspan="2">Jml/Unit</th>
                                            <th rowspan="2">Total Kebutuhan</th>
                                            <th colspan="2"><?php echo $nama_bulan_tahun; ?></th>
                                            <th rowspan="2" class="freeze-sisa-header">Sisa (Bulan Ini)</th>
                                            <th colspan="<?php echo $days_in_month; ?>">Tanggal</th>
                                        </tr>
                                        <tr>
                                            <th>Total Selesai</th>
                                            <th>% Selesai</th>
                                            <?php for ($i = 1; $i <= $days_in_month; $i++): ?>
                                                <th style="min-width: 40px;"><?php echo $i; ?></th>
                                            <?php endfor; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $no = 1; foreach ($data_alur['items'] as $mat): ?>
                                            <?php
                                                $kebutuhan_total = (int)$mat['jumlah_per_unit'] * (int)$mat['target_jumlah_unit'];
                                                $total_selesai_bulan = (int)$mat['total_selesai_bulan_ini'];
                                                $sisa_bulan = max(0, $kebutuhan_total - $total_selesai_bulan);
                                                $persen_selesai_bulan = $kebutuhan_total > 0 ? min(100, round(($total_selesai_bulan / $kebutuhan_total) * 100)) : 0;
                                                $progress_class = $persen_selesai_bulan >= 100 ? 'bg-success' : ($persen_selesai_bulan > 70 ? 'bg-info' : ($persen_selesai_bulan > 30 ? 'bg-warning' : 'bg-danger'));
                                            ?>
                                            <tr>
                                                <td class="text-center"><?php echo $no++; ?></td>
                                                <td><?php echo htmlspecialchars($mat['nama_komponen']); ?></td>
                                                <td class="text-center"><?php echo number_format($mat['jumlah_per_unit']); ?></td>
                                                <td class="text-center"><?php echo number_format($kebutuhan_total); ?></td>
                                                <td class="text-center fw-bold"><?php echo number_format($total_selesai_bulan); ?></td>
                                                <td class="text-center">
                                                    <div class="progress-custom" style="min-width: 60px;">
                                                        <div class="progress-bar-custom <?php echo $progress_class; ?>" style="width: <?php echo $persen_selesai_bulan; ?>%;">
                                                            <?php if ($persen_selesai_bulan > 15): echo $persen_selesai_bulan . '%'; endif; ?>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="text-center fw-bold freeze-sisa <?php echo ($sisa_bulan <= 0) ? 'text-success' : 'text-danger'; ?>">
                                                    <?php echo number_format($sisa_bulan); ?>
                                                </td>
                                                <?php for ($i = 1; $i <= $days_in_month; $i++): ?>
                                                    <td class="text-center">
                                                        <?php
                                                            $jumlah_hari_ini = $mat['harian_processed'][$i] ?? null;
                                                            if ($jumlah_hari_ini !== null && $jumlah_hari_ini > 0) {
                                                                echo '<span class="text-primary fw-bold">' . number_format($jumlah_hari_ini) . '</span>';
                                                            } else {
                                                                echo '-';
                                                            }
                                                        ?>
                                                    </td>
                                                <?php endfor; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<button class="back-to-top" id="backToTop" onclick="scrollToTop()">
    <i class="bi bi-arrow-up"></i>
</button>

<script>
// Tab Switching
function switchTab(tab) {
    const ringkas = document.getElementById('tab-ringkas');
    const detail = document.getElementById('tab-detail');
    const buttons = document.querySelectorAll('.nav-link-custom');
    
    if (tab === 'ringkas') {
        ringkas.style.display = 'block';
        detail.style.display = 'none';
        buttons[0].classList.add('active');
        buttons[1].classList.remove('active');
    } else {
        ringkas.style.display = 'none';
        detail.style.display = 'block';
        buttons[0].classList.remove('active');
        buttons[1].classList.add('active');
    }
}

// Accordion Toggle
function toggleAlur(header) {
    const body = header.nextElementSibling;
    const isCollapsed = header.classList.contains('collapsed');
    
    if (isCollapsed) {
        header.classList.remove('collapsed');
        body.classList.add('show');
    } else {
        header.classList.add('collapsed');
        body.classList.remove('show');
    }
}

// Month Navigation
function navigateMonth(direction) {
    const prevMonth = <?php echo $month_nav['prev'] ? "'" . $month_nav['prev'] . "'" : 'null'; ?>;
    const nextMonth = <?php echo $month_nav['next'] ? "'" . $month_nav['next'] . "'" : 'null'; ?>;
    const idTarget = <?php echo $id_target; ?>;
    
    let targetMonth = null;
    if (direction === 'prev' && prevMonth) {
        targetMonth = prevMonth;
    } else if (direction === 'next' && nextMonth) {
        targetMonth = nextMonth;
    }
    
    if (targetMonth) {
        // [DIUBAH] Pastikan URL mengarah ke file ini (rincian_laporan.php)
        window.location.href = `rincian_laporan.php?id_target=${idTarget}&bulan=${targetMonth}#detail`;
    }
}

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

// Auto-expand first alur on page load
document.addEventListener('DOMContentLoaded', function() {
    const firstAlur = document.querySelector('.alur-header');
    if (firstAlur && !firstAlur.classList.contains('collapsed')) {
        const body = firstAlur.nextElementSibling;
        body.classList.add('show');
    }
    
    // Handle hash for tab switching
    if (window.location.hash === '#detail') {
        switchTab('detail');
    }
});
</script>

<?php include_once '../../../templates/footer.php'; ?>