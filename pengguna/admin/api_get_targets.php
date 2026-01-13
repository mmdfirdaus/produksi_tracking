<?php
session_start();
include_once '../../system/database_connection.php';
date_default_timezone_set('Asia/Jakarta');

// Validasi sesi
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo '<div class="alert alert-danger m-3">Akses ditolak. Silakan login kembali.</div>';
    exit;
}

$id_user = $_SESSION['user_id'];
$type = $_GET['type'] ?? 'ongoing';
$allowed_types = ['ongoing', 'selesai', 'prioritas', 'terakhir_input', 'terhenti'];
if (!in_array($type, $allowed_types)) {
    http_response_code(400); 
    echo '<div class="alert alert-danger m-3">Tipe permintaan tidak valid.</div>';
    exit;
}

try {
    $stmt_alurs = $pdo->prepare("
        SELECT ma.id_alur
        FROM master_alur ma
        JOIN admin_tahapan_access ata ON ma.id_alur = ata.id_tahapan 
        WHERE ata.id_user = :id_user
    ");
    $stmt_alurs->execute(['id_user' => $id_user]);
    $admin_alur_ids = $stmt_alurs->fetchAll(PDO::FETCH_COLUMN);

    if (empty($admin_alur_ids)) {
        echo '<div class="alert alert-warning m-3">Anda tidak memiliki akses ke alur produksi manapun.</div>';
        exit;
    }

    $placeholders = implode(',', array_fill(0, count($admin_alur_ids), '?'));
    $sql = "";
    $params = $admin_alur_ids;

    $admin_barang_subquery = "SELECT DISTINCT id_barang FROM alur_barang WHERE id_alur IN ($placeholders)";
    $admin_targets_subquery = "SELECT DISTINCT id_target FROM target_alur_status WHERE id_alur IN ($placeholders)";

    // === UPDATE QUERY: MENAMBAHKAN pt.no_spk ===
    switch ($type) {
        case 'selesai':
            $sql = "SELECT pt.id_target, pt.no_spk, mb.nama_barang, pt.nama_permintaan, pt.status, (CASE WHEN pt.prioritas = 'Prioritas' OR pt.is_priority = 1 THEN 'Prioritas' ELSE 'Normal' END) AS prioritas, pt.tanggal_selesai
                    FROM production_targets pt
                    JOIN master_barang mb ON pt.id_barang = mb.id_barang
                    WHERE pt.status = 'Selesai'
                    AND pt.id_barang IN ($admin_barang_subquery)"; 
            
            $params = $admin_alur_ids; 
            break;

        case 'prioritas':
            $sql = "SELECT DISTINCT pt.id_target, pt.no_spk, mb.nama_barang, pt.nama_permintaan, pt.status, (CASE WHEN pt.prioritas = 'Prioritas' OR pt.is_priority = 1 THEN 'Prioritas' ELSE 'Normal' END) AS prioritas,
                                pt.priority_deadline AS tanggal_selesai
                    FROM production_targets pt
                    JOIN master_barang mb ON pt.id_barang = mb.id_barang
                    WHERE (pt.prioritas = 'Prioritas' OR pt.is_priority = 1) AND pt.status = 'ongoing' AND pt.is_active = 1
                    AND pt.id_barang IN ($admin_barang_subquery)
                    ORDER BY pt.priority_deadline ASC";
            
            $params = $admin_alur_ids;
            break;

        case 'terakhir_input':
            $start_date = $_GET['start_date'] ?? null;
            $end_date = $_GET['end_date'] ?? null;
    
            $params = array_merge($admin_alur_ids, $admin_alur_ids);
            $date_filter_sql = ""; 
    
            if (!empty($start_date) && !empty($end_date)) {
                $start_date_sql = $start_date . ' 00:00:00';
                $end_date_sql = $end_date . ' 23:59:59';
                $date_filter_sql = " AND lh.created_at BETWEEN ? AND ?";
                $params[] = $start_date_sql; 
                $params[] = $end_date_sql; 
            }

            $limit_sql = "";
            if (empty($start_date) && empty($end_date)) {
                $limit_sql = " LIMIT 50";
            }
    
            $sql = "SELECT
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
                    AND pt.id_target IN ($admin_targets_subquery)
                    AND pt.status = 'ongoing' 
                    $date_filter_sql 
                    ORDER BY lh.created_at DESC
                    $limit_sql"; 
            break;

        case 'terhenti':
            $sql = "SELECT
                            pt.id_target,
                            pt.no_spk,
                            mb.nama_barang,
                            pt.nama_permintaan,
                            MAX(lh.created_at) AS last_report_time,
                            DATEDIFF(NOW(), COALESCE(MAX(lh.created_at), pt.created_at)) as days_stalled
                    FROM production_targets pt
                    JOIN master_barang mb ON pt.id_barang = mb.id_barang
                    JOIN ($admin_targets_subquery) AS admin_targets
                        ON pt.id_target = admin_targets.id_target
                    LEFT JOIN target_material tm ON pt.id_target = tm.id_target
                    LEFT JOIN laporan_harian lh ON tm.id_material = lh.id_material
                    WHERE pt.status = 'ongoing' AND pt.is_active = 1
                    GROUP BY pt.id_target, pt.no_spk, mb.nama_barang, pt.nama_permintaan, pt.created_at
                    HAVING days_stalled > 1
                    ORDER BY days_stalled DESC, last_report_time ASC";
            $params = $admin_alur_ids;
            break;

        case 'ongoing':
        default:
            $sql = "SELECT pt.id_target, pt.no_spk, mb.nama_barang, pt.nama_permintaan, pt.status, (CASE WHEN pt.prioritas = 'Prioritas' OR pt.is_priority = 1 THEN 'Prioritas' ELSE 'Normal' END) AS prioritas, pt.tanggal_selesai
                    FROM production_targets pt
                    JOIN master_barang mb ON pt.id_barang = mb.id_barang
                    WHERE pt.status = 'ongoing' AND pt.is_active = 1
                    AND pt.prioritas != 'Prioritas' 
                    AND pt.id_barang IN ($admin_barang_subquery)
                    ORDER BY pt.prioritas DESC, pt.tanggal_selesai ASC";
            
            $params = $admin_alur_ids; 
            break;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $targets = $stmt->fetchAll(PDO::FETCH_ASSOC); 

    // =========================================================================
    // || EMBEDDED CSS (Tidak Diubah) ||
    // =========================================================================
    ?>
    <style>
        /* ... Style CSS Sama ... */
        :root {
            --modal-primary: #2c3e50;
            --modal-accent: #3498db;
            --modal-success: #27ae60;
            --modal-warning: #f39c12;
            --modal-danger: #e74c3c;
            --modal-bg: #ffffff;
            --modal-border: #ecf0f1;
            --modal-text: #2c3e50;
            --modal-text-muted: #7f8c8d;
            --modal-shadow: 0 2px 8px rgba(44, 62, 80, 0.08);
            --modal-radius: 10px;
        }
        /* ... sisa CSS ... */
        .filter-container {
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            border-bottom: 2px solid var(--modal-border);
            padding: 1.75rem 1.5rem;
            margin: -1rem -1rem 0 -1rem;
        }
        .filter-title {
            color: var(--modal-primary);
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .filter-title i { color: var(--modal-accent); }
        .filter-form-group { margin-bottom: 0; }
        .filter-label {
            font-weight: 600;
            color: var(--modal-text);
            font-size: 0.875rem;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.35rem;
        }
        .filter-label i { color: var(--modal-accent); font-size: 0.85rem; }
        .filter-input {
            border: 2px solid var(--modal-border);
            border-radius: 8px;
            padding: 0.65rem 0.875rem;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            background: white;
        }
        .filter-input:focus {
            border-color: var(--modal-accent);
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.15);
            outline: none;
        }
        .filter-btn {
            border-radius: 8px;
            padding: 0.65rem 1.25rem;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            border: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        .filter-btn-primary {
            background: linear-gradient(135deg, var(--modal-accent), #5dade2);
            color: white;
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.25);
        }
        .filter-btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(52, 152, 219, 0.35);
            background: linear-gradient(135deg, #2980b9, var(--modal-accent));
        }
        .filter-btn-secondary { background: #95a5a6; color: white; }
        .filter-btn-secondary:hover { background: #7f8c8d; transform: translateY(-2px); }
        .table-container { padding: 1.5rem; background: white; }
        .modal-table {
            border-collapse: separate;
            border-spacing: 0;
            width: 100%;
            margin: 0;
            background: white;
            border-radius: var(--modal-radius);
            overflow: hidden;
            box-shadow: var(--modal-shadow);
        }
        .modal-table thead { background: linear-gradient(135deg, var(--modal-primary), #34495e); }
        .modal-table thead th {
            color: #0e0e0eff !important;
            font-weight: 700;
            font-size: 0.95rem;
            padding: 1rem 1.25rem;
            text-align: left;
            border: none;
            white-space: nowrap;
            text-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
            letter-spacing: 0.3px;
        }
        .modal-table thead th:first-child { border-top-left-radius: var(--modal-radius); }
        .modal-table thead th:last-child { border-top-right-radius: var(--modal-radius); }
        .modal-table tbody tr { transition: all 0.2s ease; border-bottom: 1px solid var(--modal-border); }
        .modal-table tbody tr:last-child { border-bottom: none; }
        .modal-table tbody tr:hover { background: rgba(52, 152, 219, 0.04); transform: scale(1.005); }
        .modal-table tbody td {
            padding: 1rem 1.25rem;
            color: var(--modal-text);
            font-size: 0.9rem;
            vertical-align: middle;
        }
        .badge-modern {
            padding: 0.45rem 0.85rem;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.8rem;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            white-space: nowrap;
        }
        .badge-warning-modern {
            background: linear-gradient(135deg, #f39c12, #f1c40f);
            color: white;
            box-shadow: 0 2px 8px rgba(243, 156, 18, 0.3);
        }
        .badge-success-modern {
            background: linear-gradient(135deg, #27ae60, #2ecc71);
            color: white;
            box-shadow: 0 2px 8px rgba(39, 174, 96, 0.3);
        }
        .badge-danger-modern {
            background: linear-gradient(135deg, #e74c3c, #ec7063);
            color: white;
            box-shadow: 0 2px 8px rgba(231, 76, 60, 0.3);
        }
        .badge-secondary-modern {
            background: linear-gradient(135deg, #95a5a6, #bdc3c7);
            color: white;
            box-shadow: 0 2px 8px rgba(149, 165, 166, 0.3);
        }
        .badge-info-modern {
            background: linear-gradient(135deg, var(--modal-accent), #5dade2);
            color: white;
            box-shadow: 0 2px 8px rgba(52, 152, 219, 0.3);
        }
        .btn-detail {
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            text-decoration: none;
            border: none;
            white-space: nowrap;
        }
        .btn-detail-primary {
            background: linear-gradient(135deg, var(--modal-accent), #5dade2);
            color: white;
            box-shadow: 0 2px 8px rgba(52, 152, 219, 0.25);
        }
        .btn-detail-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.35);
            color: white;
        }
        .alert-modern {
            border-radius: var(--modal-radius);
            border: none;
            padding: 1.25rem 1.5rem;
            margin: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            box-shadow: var(--modal-shadow);
        }
        .alert-modern i { font-size: 1.5rem; }
        .alert-info-modern { background: linear-gradient(135deg, #e8f4fd, #d4ebf7); color: #1f6fa8; }
        .empty-state { text-align: center; padding: 3rem 2rem; }
        .empty-state-icon { font-size: 3.5rem; color: var(--modal-text-muted); margin-bottom: 1rem; opacity: 0.6; }
        .empty-state-text { color: var(--modal-text-muted); font-size: 1.05rem; font-weight: 500; }
        .dataTables_wrapper .dataTables_length select,
        .dataTables_wrapper .dataTables_filter input {
            border: 2px solid var(--modal-border);
            border-radius: 6px;
            padding: 0.45rem 0.75rem;
            transition: all 0.3s ease;
        }
        .dataTables_wrapper .dataTables_length select:focus,
        .dataTables_wrapper .dataTables_filter input:focus {
            border-color: var(--modal-accent);
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.15);
            outline: none;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: var(--modal-accent) !important;
            border-color: var(--modal-accent) !important;
            color: white !important;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: #ecf0f1 !important;
            border-color: #ecf0f1 !important;
        }
        @media (max-width: 768px) {
            .filter-container { padding: 1.25rem 1rem; }
            .filter-title { font-size: 1rem; }
            .table-container { padding: 1rem; }
            .modal-table thead th, .modal-table tbody td { padding: 0.75rem 0.875rem; font-size: 0.85rem; }
            .btn-detail { padding: 0.4rem 0.75rem; font-size: 0.8rem; }
            .badge-modern { padding: 0.35rem 0.65rem; font-size: 0.75rem; }
        }
        .spinner-border { width: 3rem; height: 3rem; color: var(--modal-accent); }
        * { transition: background-color 0.3s ease, border-color 0.3s ease, transform 0.2s ease; }
    </style>
    <?php

    if (empty($targets)) { 
        echo '<div class="alert alert-modern alert-info-modern">';
        echo '<i class="fas fa-info-circle"></i>';
        echo '<div>Tidak ada data target yang ditemukan untuk kategori ini.</div>';
        echo '</div>';
    } else {
        $date_column_header = '';
        $show_date_column = true;
        if ($type === 'prioritas') {
            $date_column_header = 'Deadline';
        } elseif ($type === 'ongoing') {
            $show_date_column = false; 
        } elseif ($type === 'terakhir_input') {
            $date_column_header = 'Tgl Input'; 
        } elseif ($type === 'terhenti') {
            $date_column_header = 'Tgl Input Terakhir'; 
        } else {
            $date_column_header = 'Tgl Selesai'; 
        }

        if ($type === 'terakhir_input') {
            $form_start_date = $_GET['start_date'] ?? '';
            $form_end_date = $_GET['end_date'] ?? '';

            echo '<div class="filter-container">';
            echo '<form id="filterFormModal">';
            echo '<h6 class="filter-title">';
            echo '<i class="fas fa-filter"></i>';
            echo 'Filter Berdasarkan Tanggal Input';
            echo '</h6>';
            echo '<div class="row g-3 align-items-end">';
            
            echo '<div class="col-md-4">';
            echo '<div class="filter-form-group">';
            echo '<label for="startDateModal" class="filter-label"><i class="far fa-calendar-alt"></i> Tanggal Mulai</label>';
            echo '<input type="date" class="form-control filter-input" id="startDateModal" value="' . htmlspecialchars($form_start_date) . '">';
            echo '</div></div>';
            
            echo '<div class="col-md-4">';
            echo '<div class="filter-form-group">';
            echo '<label for="endDateModal" class="filter-label"><i class="far fa-calendar-check"></i> Tanggal Selesai</label>';
            echo '<input type="date" class="form-control filter-input" id="endDateModal" value="' . htmlspecialchars($form_end_date) . '">';
            echo '</div></div>';
            
            echo '<div class="col-md-2">';
            echo '<button type="submit" class="btn filter-btn filter-btn-primary w-100" id="btnFilterModal"><i class="fas fa-search"></i> Filter</button>';
            echo '</div>';
            
            echo '<div class="col-md-2">';
            echo '<button type="button" class="btn filter-btn filter-btn-secondary w-100" id="btnResetModal"><i class="fas fa-sync-alt"></i> Reset</button>';
            echo '</div>';
            
            echo '</div>'; 
            echo '</form>';
            echo '</div>'; 
        }

        echo '<div class="table-container">';
        echo '<div class="table-responsive">';
        echo '<table id="targetsTable" class="modal-table table table-hover" style="width:100%">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>ID Target</th>';
        echo '<th>No SPK</th>'; // <-- UPDATE: Tambah Kolom Header
        echo '<th>Nama Barang</th>';
        echo '<th>Permintaan</th>';

        if ($show_date_column) {
            echo '<th>' . htmlspecialchars($date_column_header) . '</th>';
        }

        if ($type === 'terakhir_input') {
            echo '<th>Nama Alur</th>';
            echo '<th>Jumlah Input</th>';
        } elseif ($type === 'terhenti') {
            echo '<th>Hari Terhenti</th>';
        } else {
            echo '<th>Status</th>';
            echo '<th>Prioritas</th>';
        }
        echo '<th>Aksi</th>'; 
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        foreach ($targets as $row) { 
            echo '<tr>';
            echo '<td><strong>' . htmlspecialchars($row['id_target']) . '</strong></td>';
            // UPDATE: Tampilkan No SPK
            echo '<td><span class="badge badge-modern badge-secondary-modern">' . htmlspecialchars($row['no_spk'] ?? '-') . '</span></td>';
            echo '<td>' . htmlspecialchars($row['nama_barang']) . '</td>';
            echo '<td>' . htmlspecialchars($row['nama_permintaan']) . '</td>';

            if ($show_date_column) {
                $tanggal_display = '-'; 

                if ($type === 'terakhir_input' && !empty($row['created_at'])) {
                    $tanggal_display = date('d M Y, H:i', strtotime($row['created_at']));
                
                } elseif ($type === 'terhenti' && !empty($row['last_report_time'])) {
                    $tanggal_display = date('d M Y', strtotime($row['last_report_time']));
                
                } elseif ($type === 'terhenti' && empty($row['last_report_time'])) {
                    $tanggal_display = '<span class="badge badge-modern badge-secondary-modern"><i class="fas fa-minus-circle"></i> Belum Ada Input</span>';
                
                } elseif (isset($row['tanggal_selesai']) && !empty($row['tanggal_selesai'])) {
                    if ($type === 'prioritas') {
                        $hari_ini = new DateTime('today');
                        $tanggal_deadline = new DateTime(date('Y-m-d', strtotime($row['tanggal_selesai'])));

                        if ($hari_ini > $tanggal_deadline) {
                            $selisih = $hari_ini->diff($tanggal_deadline);
                            $hari_terlewat = $selisih->days; 
                            $tanggal_display = '<span class="badge badge-modern badge-danger-modern"><i class="fas fa-exclamation-triangle"></i> Lewat ' . $hari_terlewat . ' hari</span>';
                        } else {
                            $tanggal_display = date('d M Y', strtotime($row['tanggal_selesai']));
                        }
                    } else {
                        $tanggal_display = date('d M Y', strtotime($row['tanggal_selesai']));
                    }
                }

                echo '<td>' . $tanggal_display . '</td>';
            }

            if ($type === 'terakhir_input') {
                echo '<td><span class="badge badge-modern badge-info-modern"><i class="fas fa-stream"></i> ' . htmlspecialchars($row['nama_alur']) . '</span></td>';
                echo '<td><strong>' . htmlspecialchars($row['jumlah_selesai'] ?? 'N/A') . '</strong></td>';
            } elseif ($type === 'terhenti') {
                echo '<td><span class="badge badge-modern badge-danger-modern"><i class="fas fa-clock"></i> ' . htmlspecialchars($row['days_stalled']) . ' Hari</span></td>';
            } else {
                $status_badge_class = 'badge-secondary-modern';
                $status_icon = 'fa-circle';
                
                if ($row['status'] == 'ongoing') {
                    $status_badge_class = 'badge-warning-modern';
                    $status_icon = 'fa-spinner fa-pulse';
                } elseif ($row['status'] == 'Selesai') {
                    $status_badge_class = 'badge-success-modern';
                    $status_icon = 'fa-check-circle';
                }
                
                echo '<td><span class="badge badge-modern ' . $status_badge_class . '"><i class="fas ' . $status_icon . '"></i> ' . htmlspecialchars($row['status']) . '</span></td>';
                
                $prioritas_class = 'badge-secondary-modern'; 
                $prioritas_icon = 'fa-minus';
                
                if (isset($row['prioritas']) && $row['prioritas'] == 'Prioritas') {
                    $prioritas_class = 'badge-danger-modern'; 
                    $prioritas_icon = 'fa-exclamation-triangle';
                }
                echo '<td><span class="badge badge-modern ' . $prioritas_class . '"><i class="fas ' . $prioritas_icon . '"></i> ' . htmlspecialchars($row['prioritas'] ?? 'Normal') . '</span></td>';
            }

            $button_text = '';
            $button_icon = '';
            $detail_link = '';

            if ($type === 'selesai') {
                $detail_link = "manajemen_laporan/rincian_laporan.php?id_target=" . htmlspecialchars($row['id_target']); 
                $button_icon = 'fa-file-alt';
                $button_text = 'Lihat Laporan';
            } else {
                $detail_link = "alur_produksi.php?id_target=" . htmlspecialchars($row['id_target']);
                $button_icon = 'fa-sitemap'; 
                $button_text = 'Lihat Alur'; 
            }

            echo '<td>';
            echo '<a href="' . $detail_link . '" class="btn btn-detail btn-detail-primary">';
            echo '<i class="fas ' . $button_icon . '"></i>';
            echo $button_text;
            echo '</a>';
            echo '</td>';
            
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
        echo '</div>'; 
        echo '</div>'; 
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo '<div class="alert alert-modern alert-danger-modern m-3">';
    echo '<i class="fas fa-exclamation-triangle"></i>';
    echo '<div><strong>Error API:</strong> ' . $e->getMessage() . '</div>';
    echo '</div>';
}
?>