<?php
session_start();
// Pastikan pengguna sudah login dan memiliki role 'admin'
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../auth/login.php");
    exit;
}

// Gunakan header_admin.php yang sudah dibuat
include_once '../../templates/header_admin.php';

date_default_timezone_set('Asia/Jakarta');

// Koneksi database sudah di-include dari header
$id_user = $_SESSION['user_id'];
$data_deadline = [];
$data_terakhir_input = [];
$data_terhenti = [];
$data_monitor = [];
$total_ongoing = 0;
$total_selesai = 0;
$total_prioritas = 0;
$limit_list = 3;
// Variabel default untuk deadline di card
$deadline_terdekat_display = "Tidak ada";

try {
    // 1. Dapatkan semua alur yang diakses oleh admin ini
    $stmt_alurs = $pdo->prepare("
        SELECT ma.id_alur, ma.nama_alur, ma.urutan
        FROM master_alur ma
        JOIN admin_tahapan_access ata ON ma.id_alur = ata.id_tahapan 
        WHERE ata.id_user = :id_user
        ORDER BY ma.urutan
    ");
    $stmt_alurs->execute(['id_user' => $id_user]);
    $admin_alurs = $stmt_alurs->fetchAll(PDO::FETCH_ASSOC);
    
    $admin_alur_ids = array_column($admin_alurs, 'id_alur');

    // 2. Query untuk KPI Cards (GLOBAL SCOPE)
    $sql_ongoing = "SELECT COUNT(id_target) FROM production_targets 
                    WHERE status = 'ongoing' AND is_active = 1";
    $stmt_ongoing = $pdo->prepare($sql_ongoing);
    $stmt_ongoing->execute();
    $total_ongoing = $stmt_ongoing->fetchColumn();

    $sql_selesai = "SELECT COUNT(id_target) FROM production_targets 
                    WHERE status = 'Selesai'";
    $stmt_selesai = $pdo->prepare($sql_selesai);
    $stmt_selesai->execute(); 
    $total_selesai = $stmt_selesai->fetchColumn();

    $sql_prioritas = "SELECT COUNT(id_target) FROM production_targets WHERE (prioritas = 'Prioritas' OR is_priority = 1) AND status = 'ongoing' AND is_active = 1";
    $stmt_prioritas = $pdo->prepare($sql_prioritas);
    $stmt_prioritas->execute(); 
    $total_prioritas = $stmt_prioritas->fetchColumn();
    
    if ($total_prioritas > 0) {
        $stmt_deadline_card = $pdo->prepare("
            SELECT priority_deadline 
            FROM production_targets 
            WHERE (prioritas = 'Prioritas' OR is_priority = 1) 
            AND status = 'ongoing' AND is_active = 1 
            AND priority_deadline IS NOT NULL
            ORDER BY priority_deadline ASC 
            LIMIT 1
        ");
        $stmt_deadline_card->execute();
        $deadline_terdekat = $stmt_deadline_card->fetchColumn();
        
        if ($deadline_terdekat) {
            $hari_ini = new DateTime('today');
            $tanggal_deadline = new DateTime(date('Y-m-d', strtotime($deadline_terdekat)));

            if ($hari_ini > $tanggal_deadline) {
                $selisih = $hari_ini->diff($tanggal_deadline);
                $hari_terlewat = $selisih->days; 
                $deadline_terdekat_display = '<span style="color: #e74c3c; font-weight: bold;">Lewat ' . $hari_terlewat . ' hari</span>';
            } else {
                $deadline_terdekat_display = date('d M Y', strtotime($deadline_terdekat));
            }
        }
    }
    
    if (empty($admin_alur_ids)) {
        // Admin tidak punya akses
    } else {
        $placeholders = implode(',', array_fill(0, count($admin_alur_ids), '?'));
        
        // 3. Query untuk Pop-up Deadline (UPDATE: Tambah pt.no_spk)
        $sql_deadline = "
            SELECT pt.id_target, pt.no_spk, mb.nama_barang, pt.nama_permintaan, pt.tanggal_selesai, 
                   DATEDIFF(pt.tanggal_selesai, CURDATE()) AS sisa_hari
            FROM production_targets pt
            JOIN master_barang mb ON pt.id_barang = mb.id_barang
            JOIN (SELECT DISTINCT id_target FROM target_alur_status WHERE id_alur IN ($placeholders)) AS admin_targets
                ON pt.id_target = admin_targets.id_target
            WHERE pt.status = 'ongoing'
              AND pt.tanggal_selesai BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 15 DAY)
            ORDER BY sisa_hari ASC
        ";
        $stmt_deadline = $pdo->prepare($sql_deadline);
        $stmt_deadline->execute($admin_alur_ids);
        $data_deadline = $stmt_deadline->fetchAll(PDO::FETCH_ASSOC);

        // 4. Query untuk "Terakhir Kali Diinput" (UPDATE: Tambah pt.no_spk)
        $sql_terakhir_input = "
            SELECT
                pt.id_target,
                pt.no_spk, 
                mb.nama_barang,
                pt.nama_permintaan,
                lh.created_at,
                ma.nama_alur,
                lh.jumlah_selesai 
            FROM laporan_harian lh
            JOIN target_material tm ON lh.id_material = tm.id_material 
            JOIN production_targets pt ON tm.id_target = pt.id_target 
            JOIN master_barang mb ON pt.id_barang = mb.id_barang
            JOIN master_alur ma ON tm.id_alur = ma.id_alur 
            WHERE tm.id_alur IN ($placeholders) 
              AND pt.id_target IN (SELECT DISTINCT id_target FROM target_alur_status WHERE id_alur IN ($placeholders)) 
              AND pt.status = 'ongoing' 
            ORDER BY lh.created_at DESC
            LIMIT ?
        ";
        $stmt_terakhir_input = $pdo->prepare($sql_terakhir_input);
        $param_index = 1;
        foreach ($admin_alur_ids as $id_alur) {
            $stmt_terakhir_input->bindValue($param_index++, $id_alur);
        }
         foreach ($admin_alur_ids as $id_alur) {
            $stmt_terakhir_input->bindValue($param_index++, $id_alur);
        }
        $stmt_terakhir_input->bindValue($param_index, $limit_list, PDO::PARAM_INT);
        $stmt_terakhir_input->execute();
        $data_terakhir_input = $stmt_terakhir_input->fetchAll(PDO::FETCH_ASSOC);

        // 5. Query untuk "Target Terhenti" (UPDATE: Tambah pt.no_spk)
        $sql_terhenti = "
            SELECT
                pt.id_target,
                pt.no_spk,
                mb.nama_barang,
                pt.nama_permintaan,
                MAX(lh.created_at) AS last_report_time,
                DATEDIFF(NOW(), COALESCE(MAX(lh.created_at), pt.created_at)) as days_stalled
            FROM production_targets pt
            JOIN master_barang mb ON pt.id_barang = mb.id_barang
            JOIN (SELECT DISTINCT id_target FROM target_alur_status WHERE id_alur IN ($placeholders)) AS admin_targets
                ON pt.id_target = admin_targets.id_target
            LEFT JOIN target_material tm ON pt.id_target = tm.id_target 
            LEFT JOIN laporan_harian lh ON tm.id_material = lh.id_material 
            WHERE pt.status = 'ongoing' AND pt.is_active = 1
            GROUP BY pt.id_target, pt.no_spk, mb.nama_barang, pt.nama_permintaan, pt.created_at
            HAVING days_stalled > 1
            ORDER BY days_stalled DESC, last_report_time ASC
            LIMIT ?
        ";
        $stmt_terhenti = $pdo->prepare($sql_terhenti);
        $param_index_henti = 1;
        foreach ($admin_alur_ids as $id_alur) {
            $stmt_terhenti->bindValue($param_index_henti++, $id_alur);
        }
        $stmt_terhenti->bindValue($param_index_henti, $limit_list, PDO::PARAM_INT);
        $stmt_terhenti->execute();
        $data_terhenti = $stmt_terhenti->fetchAll(PDO::FETCH_ASSOC);

        // 6. Query untuk "Monitor Lini Produksi"
        $stmt_all_alurs = $pdo->query("SELECT urutan, id_alur FROM master_alur");
        $alur_map_by_urutan = $stmt_all_alurs->fetchAll(PDO::FETCH_KEY_PAIR); 

        foreach ($admin_alurs as $alur) {
            $id_alur_current = $alur['id_alur'];
            $urutan_current = $alur['urutan'];
            
            // 6a. Get Status (Sedang Dikerjakan) (UPDATE: Tambah pt.no_spk)
            $stmt_status = $pdo->prepare("
                SELECT pt.id_target, pt.no_spk, mb.nama_barang, pt.nama_permintaan 
                FROM target_alur_status tas
                JOIN production_targets pt ON tas.id_target = pt.id_target
                JOIN master_barang mb ON pt.id_barang = mb.id_barang
                WHERE tas.id_alur = ? 
                  AND tas.status_pengerjaan = 'Sedang Dikerjakan'
                  AND pt.status = 'ongoing' 
                LIMIT 2
            ");
            $stmt_status->execute([$id_alur_current]);
            $status_pengerjaan = $stmt_status->fetch(PDO::FETCH_ASSOC);
            
            // 6b. Get Antrian
            $antrian_count = 0;
            $urutan_previous = $urutan_current - 1;
            
            if (isset($alur_map_by_urutan[$urutan_previous])) {
                $id_alur_previous = $alur_map_by_urutan[$urutan_previous];
                
                $stmt_antrian = $pdo->prepare("
                    SELECT COUNT(DISTINCT tas_curr.id_target)
                    FROM target_alur_status tas_curr
                    LEFT JOIN target_alur_status tas_prev ON tas_curr.id_target = tas_prev.id_target AND tas_prev.id_alur = ? 
                    WHERE tas_curr.id_alur = ? 
                      AND tas_curr.status_pengerjaan = 'Pending' 
                      AND (tas_prev.id_alur IS NULL OR tas_prev.status_pengerjaan = 'Sedang Dikerjakan') 
                ");
                $stmt_antrian->execute([$id_alur_previous, $id_alur_current]);
                $antrian_count = $stmt_antrian->fetchColumn();
            }

            $data_monitor[] = [
                'id_alur' => $id_alur_current, 
                'nama_alur' => $alur['nama_alur'],
                'status' => $status_pengerjaan, 
                'antrian' => $antrian_count
            ];
        }
    }

} catch (PDOException $e) {
    echo '<div class="alert alert-danger">Error mengambil data dashboard: ' . $e->getMessage() . '</div>';
}
?>

<style>
/* ... (Gunakan Style CSS dari dashboard.php sebelumnya) ... */
:root {
    /* Modern Color Palette - Ramah di Segala Pencahayaan */
    --primary-color: #2c3e50;
    --primary-light: #34495e;
    --accent-color: #3498db;
    --accent-light: #5dade2;
    --success-color: #27ae60;
    --success-light: #2ecc71;
    --warning-color: #f39c12;
    --warning-light: #f1c40f;
    --danger-color: #e74c3c;
    --danger-light: #ec7063;
    --bg-main: #f8f9fa;
    --bg-card: #ffffff;
    --text-primary: #2c3e50;
    --text-secondary: #7f8c8d;
    --text-muted: #95a5a6;
    --border-color: #ecf0f1;
    --shadow-sm: 0 2px 8px rgba(44, 62, 80, 0.08);
    --shadow-md: 0 4px 16px rgba(44, 62, 80, 0.12);
    --shadow-lg: 0 8px 24px rgba(44, 62, 80, 0.15);
    --border-radius: 12px;
    --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    background: var(--bg-main);
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    color: var(--text-primary);
    line-height: 1.6;
}

/* ============================================ */
/* DASHBOARD HEADER */
/* ============================================ */
.dashboard-header {
    background: var(--bg-card);
    padding: 2rem 2.5rem;
    margin-bottom: 2rem;
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-sm);
    position: relative;
    overflow: hidden;
}

.dashboard-header::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--primary-color), var(--accent-color));
}

.dashboard-title {
    font-size: 2rem;
    font-weight: 700;
    color: var(--primary-color);
    margin-bottom: 0.5rem;
    letter-spacing: -0.5px;
}

.breadcrumb {
    background: none;
    padding: 0;
    margin: 0;
}

.breadcrumb-item.active {
    font-size: 1rem;
    color: var(--text-secondary);
    font-weight: 500;
}

/* ============================================ */
/* KPI STATS CARDS */
/* ============================================ */
.stats-wrapper {
    margin-bottom: 2rem;
}

.stats-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.5rem;
}

.stats-card {
    background: var(--bg-card);
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-sm);
    border: none;
    transition: var(--transition);
    position: relative;
    overflow: hidden;
    cursor: pointer;
    min-height: 180px;
}

.stats-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    transition: height 0.3s ease;
}

.stats-card.warning::before {
    background: var(--warning-color);
}

.stats-card.success::before {
    background: var(--success-color);
}

.stats-card.danger::before {
    background: var(--danger-color);
}

.stats-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-lg);
}

.stats-card:hover::before {
    height: 6px;
}

.stats-card .card-body {
    padding: 1.75rem;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    height: 100%;
}

.stats-icon-container {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    margin-bottom: 1rem;
}

.stats-icon {
    width: 56px;
    height: 56px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
    transition: var(--transition);
}

.stats-card.warning .stats-icon {
    background: var(--warning-color);
}

.stats-card.success .stats-icon {
    background: var(--success-color);
}

.stats-card.danger .stats-icon {
    background: var(--danger-color);
}

.stats-card:hover .stats-icon {
    transform: scale(1.05);
}

.stats-content {
    text-align: right;
    flex-grow: 1;
}

.stats-number {
    font-size: 2.5rem;
    font-weight: 700;
    line-height: 1;
    margin-bottom: 0.5rem;
    color: var(--text-primary);
}

.stats-label {
    font-size: 0.95rem;
    font-weight: 600;
    color: var(--text-secondary);
    margin-bottom: 0;
}

.stats-footer {
    background: rgba(248, 249, 250, 0.5);
    margin: 1rem -1.75rem -1.75rem -1.75rem;
    padding: 1rem 1.75rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 0.875rem;
    color: var(--text-secondary);
    font-weight: 600;
    border-top: 1px solid var(--border-color);
}

.stats-footer i {
    transition: transform 0.3s ease;
    color: var(--text-muted);
}

.stats-card:hover .stats-footer i {
    transform: translateX(4px);
    color: var(--accent-color);
}

/* ============================================ */
/* LIST CARDS (Terakhir Input & Target Terhenti) */
/* ============================================ */
.list-card {
    background: var(--bg-card);
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-sm);
    border: none;
    transition: var(--transition);
    margin-bottom: 2rem;
}

.list-card:hover {
    box-shadow: var(--shadow-md);
}

.list-card .card-header {
    background: var(--bg-card);
    border-bottom: 2px solid var(--border-color);
    padding: 1.5rem 1.75rem;
    font-weight: 700;
    font-size: 1.1rem;
    color: var(--text-primary);
}

.list-card .card-header i {
    margin-right: 0.5rem;
    color: var(--accent-color);
}

.list-card .card-body {
    padding: 0;
}

.card-body-placeholder {
    padding: 3rem 2rem;
    text-align: center;
    color: var(--text-muted);
    font-style: italic;
}

.list-group-flush .list-group-item {
    padding: 1.25rem 1.75rem;
    border-color: var(--border-color);
    background: transparent;
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: var(--transition);
}

.list-group-flush .list-group-item:hover {
    background: rgba(52, 152, 219, 0.03);
}

.list-group-flush .list-group-item a {
    color: var(--primary-color);
    text-decoration: none;
    font-weight: 600;
    transition: var(--transition);
}

.list-group-flush .list-group-item a:hover {
    color: var(--accent-color);
}

.list-group-flush .list-group-item .text-muted {
    font-size: 0.875rem;
    color: var(--text-muted);
    margin-top: 0.25rem;
}

.list-card .card-footer {
    background: rgba(248, 249, 250, 0.5);
    border-top: 1px solid var(--border-color);
    padding: 1rem 1.75rem;
}

.list-card .card-footer a {
    color: var(--accent-color);
    text-decoration: none;
    font-weight: 600;
    font-size: 0.9rem;
    transition: var(--transition);
}

.list-card .card-footer a:hover {
    color: var(--accent-light);
}

/* ============================================ */
/* MONITOR LINI PRODUKSI */
/* ============================================ */
.monitor-card {
    background: var(--bg-card);
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-sm);
    border: none;
    transition: var(--transition);
    margin-bottom: 2rem;
}

.monitor-card:hover {
    box-shadow: var(--shadow-md);
}

.monitor-card .card-header {
    background: var(--bg-card);
    border-bottom: 2px solid var(--border-color);
    padding: 1.5rem 1.75rem;
    font-weight: 700;
    font-size: 1.1rem;
    color: var(--text-primary);
}

.monitor-card .card-header i {
    margin-right: 0.5rem;
    color: var(--accent-color);
}

.monitor-item {
    padding: 1.5rem 1.75rem;
    border-bottom: 1px solid var(--border-color);
    transition: var(--transition);
}

.monitor-item:last-child {
    border-bottom: none;
}

.monitor-item:hover {
    background: rgba(52, 152, 219, 0.03);
}

.monitor-item-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.monitor-item-title {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--primary-color);
}

.monitor-item-queue {
    padding: 0.4rem 1rem;
    border-radius: 20px;
    font-size: 0.875rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
}

.monitor-item-queue.queue-low {
    background: rgba(39, 174, 96, 0.1);
    color: var(--success-color);
}

.monitor-item-queue.queue-high {
    background: rgba(231, 76, 60, 0.1);
    color: var(--danger-color);
}

.monitor-item-status {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.status-icon {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
}

.status-icon.busy {
    background: var(--warning-color); /* GANTI: Jadi solid oranye */
    color: white; /* GANTI: Jadi putih agar kontras */
}

.status-icon.idle {
    background: var(--text-muted); /* Latar belakang solid abu-abu */
    color: white; /* Ikon putih */
}

.status-text {
    flex: 1;
}

.status-text span {
    font-weight: 600;
    font-size: 1rem;
}

.status-text span.busy {
    color: var(--warning-color);
}

.status-text span.idle {
    color: var(--text-muted);
}

.status-text .text-muted {
    font-size: 0.875rem;
    margin-top: 0.25rem;
}

/* ============================================ */
/* BADGE STYLES */
/* ============================================ */
.badge {
    padding: 0.45rem 0.85rem;
    border-radius: 6px;
    font-weight: 600;
    font-size: 0.8rem;
}

.badge.bg-light {
    background: rgba(52, 152, 219, 0.1) !important;
    color: var(--accent-color) !important;
}

.badge.bg-danger {
    background: var(--danger-color) !important;
}

/* ============================================ */
/* MODAL STYLES */
/* ============================================ */
.modal-content {
    border: none;
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-lg);
}

.modal-header {
    background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
    color: white;
    border-bottom: none;
    padding: 1.5rem 2rem;
    border-radius: var(--border-radius) var(--border-radius) 0 0;
}

.modal-header.modal-header-warning {
    background: linear-gradient(135deg, var(--warning-color), var(--warning-light));
}

.modal-header.modal-header-danger {
    background: linear-gradient(135deg, var(--danger-color), var(--danger-light));
}

/* ========== PERUBAHAN BARU: TAMBAHKAN STYLE UNTUK MODAL ANTRIAN ========== */
.modal-header.modal-header-info {
    background: linear-gradient(135deg, var(--accent-color), var(--accent-light));
}
/* ========== AKHIR PERUBAHAN BARU ========== */

.modal-title {
    font-weight: 600;
    font-size: 1.25rem;
}

.btn-close {
    filter: brightness(0) invert(1);
    opacity: 0.8;
}

.btn-close:hover {
    opacity: 1;
}

.modal-body {
    padding: 0;
    background: #fafbfc;
}

.modal-body .list-group-item {
    padding: 1rem 2rem;
    border-color: var(--border-color);
}

.modal-body .list-group-item:hover {
    background-color: rgba(52, 152, 219, 0.03);
}

.modal-footer {
    background: white;
    border-top: 1px solid var(--border-color);
    padding: 1.5rem 2rem;
    border-radius: 0 0 var(--border-radius) var(--border-radius);
}

/* ============================================ */
/* BACK TO TOP BUTTON */
/* ============================================ */
.back-to-top {
    position: fixed;
    bottom: 30px;
    right: 30px;
    width: 50px;
    height: 50px;
    background: linear-gradient(135deg, var(--accent-color), var(--accent-light));
    color: white;
    border: none;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    cursor: pointer;
    opacity: 0;
    visibility: hidden;
    transform: translateY(20px);
    transition: var(--transition);
    z-index: 1000;
    box-shadow: var(--shadow-md);
}

.back-to-top.show {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

.back-to-top:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-lg);
}

/* ============================================ */
/* LOADING ANIMATION */
/* ============================================ */
.spinner-border {
    width: 3rem;
    height: 3rem;
    color: var(--accent-color);
}

/* ============================================ */
/* FADE IN ANIMATIONS */
/* ============================================ */
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

.animate-fade-in {
    animation: fadeInUp 0.6s ease forwards;
}

.stats-card {
    animation: fadeInUp 0.6s ease forwards;
    opacity: 0;
}

.stats-card:nth-child(1) { animation-delay: 0.1s; }
.stats-card:nth-child(2) { animation-delay: 0.2s; }
.stats-card:nth-child(3) { animation-delay: 0.3s; }

.list-card, .monitor-card {
    animation: fadeInUp 0.6s ease forwards;
    opacity: 0;
    animation-delay: 0.4s;
}

/* ============================================ */
/* RESPONSIVE DESIGN - TABLET */
/* ============================================ */
@media (max-width: 992px) {
    .container-fluid {
        padding-left: 1rem;
        padding-right: 1rem;
    }
    
    .dashboard-header {
        padding: 1.75rem 1.5rem;
    }
    
    .dashboard-title {
        font-size: 1.75rem;
    }
    
    .stats-container {
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 1.25rem;
    }
    
    .stats-card {
        min-height: 170px;
    }
}

/* ============================================ */
/* RESPONSIVE DESIGN - MOBILE */
/* ============================================ */
@media (max-width: 768px) {
    :root {
        --border-radius: 10px;
    }
    
    .container-fluid {
        padding-left: 0.75rem;
        padding-right: 0.75rem;
    }
    
    /* Dashboard Header Mobile */
    .dashboard-header {
        padding: 1.25rem;
        margin-bottom: 1.5rem;
    }
    
    .dashboard-title {
        font-size: 1.5rem;
        line-height: 1.3;
    }
    
    .breadcrumb-item.active {
        font-size: 0.9rem;
    }
    
    /* KPI Cards - Horizontal Scroll pada Mobile */
    .stats-wrapper {
        margin: 0 -0.75rem 1.5rem -0.75rem;
        padding: 0 0.75rem;
    }
    
    .stats-container {
        display: flex;
        overflow-x: auto;
        scroll-snap-type: x mandatory;
        -webkit-overflow-scrolling: touch;
        gap: 1rem;
        padding-bottom: 0.5rem;
    }
    
    .stats-container::-webkit-scrollbar {
        height: 4px;
    }
    
    .stats-container::-webkit-scrollbar-track {
        background: var(--border-color);
        border-radius: 10px;
    }
    
    .stats-container::-webkit-scrollbar-thumb {
        background: var(--accent-color);
        border-radius: 10px;
    }
    
    .stats-card {
        min-width: 280px;
        flex-shrink: 0;
        scroll-snap-align: start;
        min-height: 160px;
        margin-bottom: 0;
    }
    
    .stats-card .card-body {
        padding: 1.25rem;
    }
    
    .stats-icon {
        width: 48px;
        height: 48px;
        font-size: 1.3rem;
    }
    
    .stats-number {
        font-size: 2rem;
    }
    
    .stats-label {
        font-size: 0.85rem;
    }
    
    .stats-footer {
        padding: 0.875rem 1.25rem;
        font-size: 0.8rem;
        margin: 0.75rem -1.25rem -1.25rem -1.25rem;
    }
    
    /* List Cards Mobile */
    .list-card {
        margin-bottom: 1.25rem;
    }
    
    .list-card .card-header {
        padding: 1.25rem;
        font-size: 1rem;
    }
    
    .list-group-flush .list-group-item {
        padding: 1rem 1.25rem;
        flex-direction: column;
        align-items: flex-start;
        gap: 0.75rem;
    }
    
    .list-group-flush .list-group-item > div {
        width: 100%;
    }
    
    .list-group-flush .list-group-item > div:last-child {
        text-align: left !important;
    }
    
    .list-card .card-footer {
        padding: 1rem 1.25rem;
    }
    
    /* Monitor Card Mobile */
    .monitor-card .card-header {
        padding: 1.25rem;
        font-size: 1rem;
    }
    
    .monitor-item {
        padding: 1.25rem;
    }
    
    .monitor-item-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.75rem;
    }
    
    .monitor-item-title {
        font-size: 1rem;
    }
    
    .monitor-item-status {
        gap: 0.75rem;
    }
    
    .status-icon {
        width: 40px;
        height: 40px;
        font-size: 1rem;
    }
    
    .status-text span {
        font-size: 0.9rem;
    }
    
    /* Modal Mobile */
    .modal-dialog {
        margin: 0.5rem;
        max-width: calc(100% - 1rem);
    }
    
    .modal-header {
        padding: 1.25rem 1.5rem;
    }
    
    .modal-title {
        font-size: 1.05rem;
    }
    
    .modal-body .list-group-item {
        padding: 1rem 1.5rem;
    }
    
    .modal-footer {
        padding: 1rem 1.5rem;
    }
    
    /* Back to Top Button Mobile */
    .back-to-top {
        bottom: 20px;
        right: 20px;
        width: 45px;
        height: 45px;
        font-size: 1.1rem;
    }
}

/* ============================================ */
/* SMALL MOBILE OPTIMIZATION */
/* ============================================ */
@media (max-width: 576px) {
    .container-fluid {
        padding-left: 0.5rem;
        padding-right: 0.5rem;
    }
    
    .dashboard-header {
        padding: 1rem;
        margin-bottom: 1.25rem;
    }
    
    .dashboard-title {
        font-size: 1.35rem;
    }
    
    .stats-card {
        min-width: 260px;
    }
    
    .stats-number {
        font-size: 1.85rem;
    }
    
    .back-to-top {
        bottom: 15px;
        right: 15px;
        width: 40px;
        height: 40px;
        font-size: 1rem;
    }
}

/* ============================================ */
/* RIPPLE EFFECT FOR TOUCH INTERACTIONS */
/* ============================================ */
.stats-card {
    position: relative;
    overflow: hidden;
    -webkit-tap-highlight-color: transparent;
}

.ripple {
    position: absolute;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.4);
    transform: scale(0);
    animation: ripple-animation 0.6s ease-out;
    pointer-events: none;
}

@keyframes ripple-animation {
    to {
        transform: scale(4);
        opacity: 0;
    }
}

/* ============================================ */
/* UTILITY CLASSES */
/* ============================================ */
.text-truncate-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.shadow-custom {
    box-shadow: var(--shadow-sm);
}

.transition-smooth {
    transition: var(--transition);
}
</style>

<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">

<div class="container-fluid px-4">
    <div class="dashboard-header animate-fade-in">
        <h1 class="dashboard-title">Dashboard Admin</h1>
        <ol class="breadcrumb">
            <li class="breadcrumb-item active">
                <i class="fas fa-hand-wave me-2"></i>
                <span>Selamat datang, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</span>
            </li>
        </ol>
    </div>

    <div class="stats-wrapper">
        <div class="stats-container">
            <div class="stats-card warning card-clickable" data-type="ongoing" data-bs-toggle="modal" data-bs-target="#targetsModal">
                <div class="card-body">
                    <div class="stats-icon-container">
                        <div class="stats-icon">
                            <i class="fas fa-tasks"></i>
                        </div>
                        <div class="stats-content">
                            <div class="stats-number"><?php echo $total_ongoing; ?></div>
                            <div class="stats-label">Target On Going</div>
                        </div>
                    </div>
                </div>
                <div class="stats-footer">
                    <span>Lihat Detail (Area Anda)</span> <i class="fas fa-arrow-right"></i>
                </div>
            </div>

            <div class="stats-card success card-clickable" data-type="selesai" data-bs-toggle="modal" data-bs-target="#targetsModal">
                <div class="card-body">
                    <div class="stats-icon-container">
                        <div class="stats-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stats-content">
                            <div class="stats-number"><?php echo $total_selesai; ?></div>
                            <div class="stats-label">Total Target Selesai</div>
                        </div>
                    </div>
                </div>
                <div class="stats-footer">
                    <span>Lihat Detail (Area Anda)</span> <i class="fas fa-arrow-right"></i>
                </div>
            </div>

            <div class="stats-card danger card-clickable" data-type="prioritas" data-bs-toggle="modal" data-bs-target="#targetsModal">
                <div class="card-body">
                    <div class="stats-icon-container">
                        <div class="stats-icon">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div class="stats-content">
                            <div class="stats-number"><?php echo $total_prioritas; ?></div>
                            <div class="stats-label">Total Target Prioritas</div>
                        </div>
                    </div>
                </div>
                <div class="stats-footer">
                    <span>Batas Waktu: <?php echo $deadline_terdekat_display; ?></span>
                    <i class="fas fa-arrow-right"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mt-2">
        <div class="col-lg-6">
            <div class="card list-card">
                <div class="card-header">
                    <i class="fas fa-history"></i>
                    Terakhir Kali Diinput (Di Area Anda)
                </div>
                <div class="card-body p-0">
                    <?php if (empty($data_terakhir_input)): ?>
                        <div class="card-body-placeholder">
                            <i class="fas fa-inbox fa-2x mb-3" style="color: var(--text-muted);"></i>
                            <p>Belum ada data input hari ini.</p>
                        </div>
                    <?php else: ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($data_terakhir_input as $item): ?>
                                <li class="list-group-item">
                                    <div>
                                        <a href="alur_produksi.php?id_target=<?php echo $item['id_target']; ?>">
                                            <?php echo htmlspecialchars($item['nama_barang']); ?>
                                        </a>
                                        <div class="text-muted small">
                                            <i class="fas fa-hashtag me-1"></i>SPK: <?php echo htmlspecialchars($item['no_spk'] ?? '-'); ?>
                                        </div>
                                        <div class="text-muted">
                                            <?php echo htmlspecialchars($item['nama_permintaan']); ?>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-light shadow-sm">
                                            <?php echo htmlspecialchars($item['nama_alur']); ?>
                                        </span>
                                        <div class="text-muted mt-1">
                                            <?php echo date('d M, H:i', strtotime($item['created_at'])); ?>
                                        </div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
                <div class="card-footer">
                    <a href="#" class="card-clickable" data-type="terakhir_input" data-bs-toggle="modal" data-bs-target="#targetsModal">
                        Lihat Semua Input
                        <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card list-card">
                <div class="card-header">
                    <i class="fas fa-pause-circle text-danger"></i>
                    Target Terhenti (> 1 Hari)
                </div>
                <div class="card-body p-0">
                    <?php if (empty($data_terhenti)): ?>
                        <div class="card-body-placeholder">
                            <i class="fas fa-check-circle fa-2x mb-3" style="color: var(--success-color);"></i>
                            <p>Tidak ada target yang terhenti.</p>
                        </div>
                    <?php else: ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($data_terhenti as $item): ?>
                                <li class="list-group-item">
                                    <div>
                                        <a href="alur_produksi.php?id_target=<?php echo $item['id_target']; ?>">
                                            <?php echo htmlspecialchars($item['nama_barang']); ?>
                                        </a>
                                        <div class="text-muted small">
                                            <i class="fas fa-hashtag me-1"></i>SPK: <?php echo htmlspecialchars($item['no_spk'] ?? '-'); ?>
                                        </div>
                                        <div class="text-muted">
                                            <?php echo htmlspecialchars($item['nama_permintaan']); ?>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-danger shadow-sm">
                                            <?php echo $item['days_stalled']; ?> Hari Terhenti
                                        </span>
                                        <div class="text-muted mt-1">
                                            Input terakhir: <?php echo $item['last_report_time'] ? date('d M Y', strtotime($item['last_report_time'])) : 'Belum ada Progress'; ?>
                                        </div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
                <div class="card-footer">
                    <a href="#" class="card-clickable" data-type="terhenti" data-bs-toggle="modal" data-bs-target="#targetsModal">
                        Lihat Semua Target Terhenti
                        <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mt-2">
        <div class="col-12">
            <div class="card monitor-card">
                <div class="card-header">
                    <i class="fas fa-server"></i>
                    Monitor Lini Produksi (Area Anda)
                </div>
                <div class="card-body p-0">
                    <?php if (empty($data_monitor)): ?>
                        <div class="card-body-placeholder" style="min-height: 150px;">
                            <i class="fas fa-cogs fa-2x mb-3" style="color: var(--text-muted);"></i>
                            <p>Anda tidak memiliki akses ke lini produksi manapun.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($data_monitor as $monitor): ?>
                            <div class="monitor-item">
                                <div class="monitor-item-header">
                                    <div class="monitor-item-title">
                                        <?php echo htmlspecialchars($monitor['nama_alur']); ?>
                                    </div>
                                    <a href="#" 
                                       class="monitor-item-queue <?php echo $monitor['antrian'] > 5 ? 'queue-high' : 'queue-low'; ?> text-decoration-none monitor-queue-clickable" 
                                       data-bs-toggle="modal" 
                                       data-bs-target="#antrianModal" 
                                       data-id-alur="<?php echo $monitor['id_alur']; ?>" 
                                       data-nama-alur="<?php echo htmlspecialchars($monitor['nama_alur']); ?>">
                                        
                                        <i class="fas fa-layer-group me-1"></i>
                                        <?php echo $monitor['antrian']; ?> Antrian
                                        
                                        <?php if ($monitor['antrian'] > 0): ?>
                                            <i class="fas fa-search-plus ms-1" style="opacity: 0.75;"></i>
                                        <?php endif; ?>
                                    </a>
                                </div>
                                <div class="monitor-item-status">
                                    <?php if ($monitor['status']): ?>
                                        <span class="status-icon busy">
                                            <i class="fas fa-cogs fa-spin"></i>
                                        </span>
                                        <div class="status-text">
                                            <span class="busy">Sedang Mengerjakan</span>
                                            <div class="text-muted">
                                                <a href="alur_produksi.php?id_target=<?php echo $monitor['status']['id_target']; ?>" class="text-reset text-decoration-none">
                                                    <?php echo htmlspecialchars($monitor['status']['nama_barang'] . ' (' . $monitor['status']['nama_permintaan'] . ')'); ?>
                                                </a>
                                                <br><small><i class="fas fa-hashtag me-1"></i>SPK: <?php echo htmlspecialchars($monitor['status']['no_spk'] ?? '-'); ?></small>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <span class="status-icon idle">
                                            <i class="fas fa-power-off"></i>
                                        </span>
                                        <div class="status-text">
                                            <span class="idle">Idle / Menganggur</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="targetsModal" tabindex="-1" aria-labelledby="targetsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="targetsModalLabel">Daftar Target Produksi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="text-center p-5">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-3 text-muted">Memuat data...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Tutup
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="deadlineModal" tabindex="-1" aria-labelledby="deadlineModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header modal-header-danger">
                <h5 class="modal-title" id="deadlineModalLabel">
                    <i class="fas fa-bell me-2"></i>Peringatan: Target Mendekati Deadline!
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="deadlineModalBody">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Tutup
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="antrianModal" tabindex="-1" aria-labelledby="antrianModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header modal-header-info">
                <h5 class="modal-title" id="antrianModalLabel"><i class="fas fa-layer-group me-2"></i>Daftar Antrian</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="antrianModalBody">
                <div class="text-center p-5">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-3 text-muted">Memuat data antrian...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Tutup
                </button>
            </div>
        </div>
    </div>
</div>

<button class="back-to-top" id="backToTop" aria-label="Kembali ke atas">
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="1.4rem" height="1.4rem" fill="currentColor">
        <path d="M0 0h24v24H0V0z" fill="none"/>
        <path d="M4 12l1.41 1.41L11 7.83V20h2V7.83l5.59 5.58L20 12l-8-8-8 8z"/>
    </svg>
</button>

<?php
// Gunakan footer generik
include_once '../../templates/footer.php';
?>

<script>
    const deadlineTargetsData = <?php echo json_encode($data_deadline); ?>;
</script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // ============================================
    // CARD CLICK HANDLERS
    // ============================================
    const cards = document.querySelectorAll('.card-clickable');
    const modalBody = document.querySelector('#targetsModal .modal-body');
    const modalTitle = document.querySelector('#targetsModalLabel');
    const modalHeader = document.querySelector('#targetsModal .modal-header');

    cards.forEach(card => {
        card.addEventListener('click', function() {
            const type = this.getAttribute('data-type');
            
            modalHeader.classList.remove('modal-header-danger');
            modalHeader.classList.remove('modal-header-warning');

            let titleHtml = '';
            if(type === 'ongoing') {
                titleHtml = '<i class="fas fa-tasks me-2"></i>Daftar Target Produksi Berjalan (Area Anda)';
                modalHeader.classList.add('modal-header-warning');
            } else if(type === 'selesai') {
                titleHtml = '<i class="fas fa-check-circle me-2"></i>Daftar Target Produksi Selesai (Area Anda)';
            } else if(type === 'prioritas') {
                titleHtml = '<i class="fas fa-exclamation-triangle me-2"></i>Daftar Target Produksi Prioritas (Area Anda)';
                modalHeader.classList.add('modal-header-danger');
            } else if(type === 'terakhir_input') {
                titleHtml = '<i class="fas fa-history me-2"></i>Semua Riwayat Input Terakhir';
            } else if(type === 'terhenti') {
                titleHtml = '<i class="fas fa-pause-circle me-2"></i>Semua Target Terhenti';
                modalHeader.classList.add('modal-header-danger');
            }
            modalTitle.innerHTML = titleHtml;

            modalBody.innerHTML = `
                <div class="text-center p-5">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-3 text-muted">Memuat data...</p>
                </div>
            `;

            fetch(`api_get_targets.php?type=${type}`)
                .then(response => response.text())
                .then(data => {
                    modalBody.innerHTML = data;
                    if (window.jQuery && window.jQuery.fn.DataTable) {
                        $('#targetsTable').DataTable({
                            responsive: true,
                            order: [[0, 'desc']],
                            "language": {
                                "url": "https://cdn.datatables.net/plug-ins/1.10.25/i18n/Indonesian.json"
                            }
                        });
                    }
                })
                .catch(error => {
                    modalBody.innerHTML = `
                        <div class="alert alert-danger d-flex align-items-center m-3">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <div>Gagal memuat data. File 'api_get_targets.php' mungkin perlu diperbarui.</div>
                        </div>
                    `;
                    console.error('Error:', error);
                });
        });
    });

    // ============================================
    // || HANDLER MODAL ANTRIAN ||
    // ============================================
    const antrianLinks = document.querySelectorAll('.monitor-queue-clickable');
    const antrianModalBody = document.querySelector('#antrianModalBody');
    const antrianModalTitle = document.querySelector('#antrianModalLabel');
    const antrianModalInstance = new bootstrap.Modal(document.getElementById('antrianModal'));

    antrianLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            
            const idAlur = this.getAttribute('data-id-alur');
            const namaAlur = this.getAttribute('data-nama-alur');
            const antrianCountText = this.innerText.trim().match(/^(\d+)/);
            const antrianCount = antrianCountText ? parseInt(antrianCountText[1]) : 0;

            antrianModalTitle.innerHTML = `<i class="fas fa-layer-group me-2"></i>Daftar Antrian: ${namaAlur}`;

            if (antrianCount === 0) {
                antrianModalBody.innerHTML = `
                    <div class="card-body-placeholder" style="min-height: 150px; display: flex; flex-direction: column; align-items: center; justify-content: center;">
                        <i class="fas fa-check-circle fa-2x mb-3" style="color: var(--success-color);"></i>
                        <p class="text-muted mb-0">Tidak ada antrian di lini produksi ini.</p>
                    </div>
                `;
                antrianModalInstance.show(); 
                return;
            }

            antrianModalBody.innerHTML = `
                <div class="text-center p-5">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-3 text-muted">Memuat data antrian...</p>
                </div>
            `;
            antrianModalInstance.show();

            fetch(`api_get_antrian.php?id_alur=${idAlur}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.text();
                })
                .then(data => {
                    antrianModalBody.innerHTML = data;
                    if (window.jQuery && window.jQuery.fn.DataTable) {
                        if ($.fn.DataTable.isDataTable('#antrianTable')) {
                            $('#antrianTable').DataTable().destroy();
                        }
                        $('#antrianTable').DataTable({
                            responsive: true,
                            order: [[0, 'desc']],
                            "language": {
                                "url": "https://cdn.datatables.net/plug-ins/1.10.25/i18n/Indonesian.json"
                            }
                        });
                    }
                })
                .catch(error => {
                    antrianModalBody.innerHTML = `
                        <div class="alert alert-danger d-flex align-items-center m-3">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <div>Gagal memuat data. Pastikan file 'api_get_antrian.php' ada di folder yang sama.</div>
                        </div>
                    `;
                    console.error('Error:', error);
                });
        });
    });

    // ============================================
    // DEADLINE POP-UP LOGIC
    // ============================================
    function checkDeadlineTargets() {
        if (!deadlineTargetsData || deadlineTargetsData.length === 0) {
            return; 
        }

        const deadlineModal = new bootstrap.Modal(document.getElementById('deadlineModal'));
        const deadlineModalBody = document.getElementById('deadlineModalBody');
        
        let listHtml = '<ul class="list-group list-group-flush">';
        deadlineTargetsData.forEach(target => {
            let badgeClass = 'bg-danger';
            let sisaHariText = `${target.sisa_hari} hari lagi`;
            if (target.sisa_hari == 0) {
                sisaHariText = 'Hari Ini!';
            } else if (target.sisa_hari > 3) {
                badgeClass = 'bg-warning text-dark';
            } else if (target.sisa_hari > 7) {
                badgeClass = 'bg-info text-dark';
            }
            
            // UPDATE: Menambahkan SPK di popup deadline
            listHtml += `
                <li class="list-group-item d-flex justify-content-between align-items-center position-relative">
                    <div>
                        <strong>${target.nama_barang}</strong> (${target.nama_permintaan})
                        <br>
                        <small class="text-muted">
                            <i class="fas fa-hashtag"></i> SPK: ${target.no_spk || '-'} |
                            Deadline: ${new Date(target.tanggal_selesai).toLocaleDateString('id-ID', {
                                day: '2-digit', 
                                month: 'long', 
                                year: 'numeric'
                            })}
                        </small>
                    </div>
                    <div class="text-end">
                        <span class="badge ${badgeClass} fs-6 shadow-sm">${sisaHariText}</span>
                        <a href="#" class="btn btn-sm btn-outline-primary ms-2 deadline-link"
                           data-id="${target.id_target}"
                           data-name="${target.nama_barang}">
                            <i class="fas fa-eye me-1"></i> Lihat
                        </a>
                    </div>
                </li>
            `;
        });
        listHtml += '</ul>';
        deadlineModalBody.innerHTML = listHtml;
        
        addDeadlineModalListeners();
        deadlineModal.show();
    }

    function addDeadlineModalListeners() {
        document.getElementById('deadlineModalBody').addEventListener('click', function(e) {
            const link = e.target.closest('a.deadline-link');
            if (link) {
                e.preventDefault();
                const targetId = link.getAttribute('data-id');
                const targetName = link.getAttribute('data-name');
                
                if (confirm(`Anda akan dialihkan ke halaman detail untuk target:\n\n${targetName}\n\nLanjutkan?`)) {
                    window.location.href = `detail_barang.php?id=${targetId}`;
                }
            }
        });
    }

    checkDeadlineTargets();

    const statsCards = document.querySelectorAll('.stats-card');
    
    statsCards.forEach(card => {
        card.addEventListener('click', function(e) {
            const ripple = document.createElement('span');
            ripple.classList.add('ripple');
            
            const rect = this.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            const x = e.clientX - rect.left - size / 2;
            const y = e.clientY - rect.top - size / 2;
            
            ripple.style.width = ripple.style.height = size + 'px';
            ripple.style.left = x + 'px';
            ripple.style.top = y + 'px';
            
            this.appendChild(ripple);
            
            setTimeout(() => ripple.remove(), 600);
        });
    });

    const backToTopBtn = document.getElementById('backToTop');
    
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

    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            const href = this.getAttribute('href');
            if (href !== '#' && document.querySelector(href)) {
                e.preventDefault();
                document.querySelector(href).scrollIntoView({
                    behavior: 'smooth'
                });
            }
        });
    });

(function() {
    'use strict';

    const CONFIG = {
        type: 'terakhir_input',
        apiEndpoint: 'api_get_targets.php',
        modalSelector: '#targetsModal .modal-body',
        tableSelector: '#targetsTable',
        dataTableConfig: {
            responsive: true,
            order: [[0, 'desc']],
            language: {
                url: "https://cdn.datatables.net/plug-ins/1.10.25/i18n/Indonesian.json"
            }
        }
    };

    function showLoadingSpinner() {
        $(CONFIG.modalSelector).html(`
            <div class="d-flex justify-content-center align-items-center my-5">
                <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
        `);
    }

    function initializeDataTable() {
        if (window.jQuery && window.jQuery.fn.DataTable) {
            if ($.fn.DataTable.isDataTable(CONFIG.tableSelector)) {
                $(CONFIG.tableSelector).DataTable().destroy();
            }
            $(CONFIG.tableSelector).DataTable(CONFIG.dataTableConfig);
        }
    }

    function loadModalData(startDate = '', endDate = '') {
        let apiUrl = `${CONFIG.apiEndpoint}?type=${CONFIG.type}`;
        
        if (startDate && endDate) {
            apiUrl += `&start_date=${startDate}&end_date=${endDate}`;
        }

        showLoadingSpinner();

        $(CONFIG.modalSelector).load(apiUrl, function(response, status, xhr) {
            if (status === "error") {
                $(CONFIG.modalSelector).html(`
                    <div class="alert alert-danger" role="alert">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <strong>Gagal memuat data!</strong> ${xhr.status} ${xhr.statusText}
                    </div>
                `);
                return;
            }
            
            initializeDataTable();
        });
    }

    function validateDateRange(startDate, endDate) {
        if (!startDate || !endDate) {
            alert('⚠️ Silakan isi Tanggal Mulai dan Tanggal Selesai.');
            return false;
        }

        if (new Date(startDate) > new Date(endDate)) {
            alert('⚠️ Tanggal Mulai tidak boleh lebih besar dari Tanggal Selesai.');
            return false;
        }

        return true;
    }

    $(document).on('submit', '#filterFormModal', function(e) {
        e.preventDefault();

        const startDate = $('#startDateModal').val();
        const endDate = $('#endDateModal').val();

        if (!validateDateRange(startDate, endDate)) {
            return;
        }

        loadModalData(startDate, endDate);
    });

    $(document).on('click', '#btnResetModal', function() {
        $('#startDateModal').val('');
        $('#endDateModal').val('');
        loadModalData();
    });

})();

});
</script>