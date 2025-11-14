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
// Validasi tipe untuk keamanan (Diambil dari kode baru)
$type = $_GET['type'] ?? 'ongoing';
$allowed_types = ['ongoing', 'selesai', 'prioritas', 'terakhir_input', 'terhenti'];
if (!in_array($type, $allowed_types)) {
    http_response_code(400); // Bad Request
    echo '<div class="alert alert-danger m-3">Tipe permintaan tidak valid.</div>';
    exit;
}

try {
    // Dapatkan alur yang diakses admin (QUERY DARI KODE LAMA ANDA)
    $stmt_alurs = $pdo->prepare("
        SELECT ma.id_alur
        FROM master_alur ma
        JOIN admin_tahapan_access ata ON ma.id_alur = ata.id_tahapan -- Langsung join ke admin_tahapan_access
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

    // === PERBAIKAN: DEFINISIKAN KEDUA SUBQUERY DI SINI ===

    // Subquery 1: Untuk 'ongoing', 'selesai', 'prioritas'
    // Mengambil SEMUA barang yang alurnya bisa diakses admin
    $admin_barang_subquery = "SELECT DISTINCT id_barang FROM alur_barang WHERE id_alur IN ($placeholders)";

    // Subquery 2: Untuk 'terakhir_input', 'terhenti'
    // Mengambil HANYA target yang SUDAH DIMULAI dan alurnya bisa diakses admin
    $admin_targets_subquery = "SELECT DISTINCT id_target FROM target_alur_status WHERE id_alur IN ($placeholders)";

    switch ($type) {
        case 'selesai':
            // === PERBAIKAN: Tambahkan filter $admin_barang_subquery ===
            // === PERUBAHAN 1 DITERAPKAN (CASE WHEN) ===
            $sql = "SELECT pt.id_target, mb.nama_barang, pt.nama_permintaan, pt.status, (CASE WHEN pt.prioritas = 'Prioritas' OR pt.is_priority = 1 THEN 'Prioritas' ELSE 'Normal' END) AS prioritas, pt.tanggal_selesai
                    FROM production_targets pt
                    JOIN master_barang mb ON pt.id_barang = mb.id_barang
                    WHERE pt.status = 'Selesai'
                    AND pt.id_barang IN ($admin_barang_subquery)"; // <-- FILTER DITAMBAHKAN
            
            $params = $admin_alur_ids; // Parameter harus $admin_alur_ids
            break;

        case 'prioritas':
            // Ini sudah benar (sesuai kode lama Anda yang sudah diperbarui)
            // === PERUBAHAN 2 DITERAPKAN (CASE WHEN) ===
            $sql = "SELECT DISTINCT pt.id_target, mb.nama_barang, pt.nama_permintaan, pt.status, (CASE WHEN pt.prioritas = 'Prioritas' OR pt.is_priority = 1 THEN 'Prioritas' ELSE 'Normal' END) AS prioritas,
                                pt.priority_deadline AS tanggal_selesai
                    FROM production_targets pt
                    JOIN master_barang mb ON pt.id_barang = mb.id_barang
                    WHERE (pt.prioritas = 'Prioritas' OR pt.is_priority = 1) AND pt.status = 'ongoing' AND pt.is_active = 1
                    AND pt.id_barang IN ($admin_barang_subquery)
                    ORDER BY pt.priority_deadline ASC";
            
            $params = $admin_alur_ids;
            break;

        case 'terakhir_input':
            // === PENAMBAHAN FILTER TANGGAL (DIMULAI) ===
            $start_date = $_GET['start_date'] ?? null;
            $end_date = $_GET['end_date'] ?? null;
    
            // Parameter dasar untuk dua subquery IN()
            $params = array_merge($admin_alur_ids, $admin_alur_ids);
            $date_filter_sql = ""; // String SQL filter tanggal
    
            // Jika kedua tanggal diisi, tambahkan filter
            if (!empty($start_date) && !empty($end_date)) {
                // Menambahkan waktu agar filter 'BETWEEN' mencakup keseluruhan hari
                $start_date_sql = $start_date . ' 00:00:00';
                $end_date_sql = $end_date . ' 23:59:59';
    
                // Kita gunakan '?' (placeholder) agar aman
                $date_filter_sql = " AND lh.created_at BETWEEN ? AND ?";
    
                // Tambahkan parameter tanggal ke array $params
                $params[] = $start_date_sql; // Tambah ke akhir array
                $params[] = $end_date_sql; // Tambah ke akhir array
            }
            // === PENAMBAHAN FILTER TANGGAL (SELESAI) ===
    
            // ... (logika filter tanggal di atasnya)
            // === PENAMBAHAN FILTER TANGGAL (SELESAI) ===

            // --- TAMBAHAN LOGIKA LIMIT (OPTIMALISASI 1) ---
            $limit_sql = "";
            // Terapkan LIMIT 50 hanya jika filter tanggal TIDAK aktif
            if (empty($start_date) && empty($end_date)) {
                $limit_sql = " LIMIT 50";
            }
            // --- AKHIR TAMBAHAN LOGIKA LIMIT ---
    
            $sql = "SELECT
                        pt.id_target,
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
                    $date_filter_sql -- <-- FILTER TANGGAL DISISIPKAN DI SINI
                    ORDER BY lh.created_at DESC
                    $limit_sql"; // <-- TAMBAHKAN VARIABEL LIMIT DI SINI
            
            // $params sudah berisi parameter yang benar (alur + tanggal jika ada)
            break;

        case 'terhenti':
            // Query ini benar, tapi variabelnya harus didefinisikan di atas (sudah kita lakukan)
            $sql = "SELECT
                            pt.id_target,
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
                    GROUP BY pt.id_target, mb.nama_barang, pt.nama_permintaan, pt.created_at
                    HAVING days_stalled > 1
                    ORDER BY days_stalled DESC, last_report_time ASC";
            $params = $admin_alur_ids;
            break;

        case 'ongoing':
        default:
            // === PERBAIKAN OPSIONAL DITERAPKAN ===
            // Menggunakan subquery barang dan MENGECUALIKAN yang prioritas
            // === PERUBAHAN 3 DITERAPKAN (CASE WHEN) ===
            $sql = "SELECT pt.id_target, mb.nama_barang, pt.nama_permintaan, pt.status, (CASE WHEN pt.prioritas = 'Prioritas' OR pt.is_priority = 1 THEN 'Prioritas' ELSE 'Normal' END) AS prioritas, pt.tanggal_selesai
                    FROM production_targets pt
                    JOIN master_barang mb ON pt.id_barang = mb.id_barang
                    WHERE pt.status = 'ongoing' AND pt.is_active = 1
                    AND pt.prioritas != 'Prioritas' -- <-- PERUBAHAN OPSIONAL DARI KODE BARU
                    AND pt.id_barang IN ($admin_barang_subquery)
                    ORDER BY pt.prioritas DESC, pt.tanggal_selesai ASC";
            
            $params = $admin_alur_ids; // Parameter tetap $admin_alur_ids untuk subquery
            break;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $targets = $stmt->fetchAll(PDO::FETCH_ASSOC); // Tetap menggunakan $targets

    // =========================================================================
    // || EMBEDDED CSS UNTUK STYLING MODERN ||
    // =========================================================================
    ?>
    <style>
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

        /* Filter Form Styling */
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

        .filter-title i {
            color: var(--modal-accent);
        }

        .filter-form-group {
            margin-bottom: 0;
        }

        .filter-label {
            font-weight: 600;
            color: var(--modal-text);
            font-size: 0.875rem;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.35rem;
        }

        .filter-label i {
            color: var(--modal-accent);
            font-size: 0.85rem;
        }

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

        .filter-btn-secondary {
            background: #95a5a6;
            color: white;
        }

        .filter-btn-secondary:hover {
            background: #7f8c8d;
            transform: translateY(-2px);
        }

        /* Table Container Styling */
        .table-container {
            padding: 1.5rem;
            background: white;
        }

        /* Enhanced Table Styling */
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

        .modal-table thead {
            background: linear-gradient(135deg, var(--modal-primary), #34495e);
        }

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

        .modal-table thead th:first-child {
            border-top-left-radius: var(--modal-radius);
        }

        .modal-table thead th:last-child {
            border-top-right-radius: var(--modal-radius);
        }

        .modal-table tbody tr {
            transition: all 0.2s ease;
            border-bottom: 1px solid var(--modal-border);
        }

        .modal-table tbody tr:last-child {
            border-bottom: none;
        }

        .modal-table tbody tr:hover {
            background: rgba(52, 152, 219, 0.04);
            transform: scale(1.005);
        }

        .modal-table tbody td {
            padding: 1rem 1.25rem;
            color: var(--modal-text);
            font-size: 0.9rem;
            vertical-align: middle;
        }

        /* Enhanced Badge Styling */
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

        /* Enhanced Button Styling */
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

        /* Alert Styling */
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

        .alert-modern i {
            font-size: 1.5rem;
        }

        .alert-info-modern {
            background: linear-gradient(135deg, #e8f4fd, #d4ebf7);
            color: #1f6fa8;
        }

        /* Empty State Styling */
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
        }

        .empty-state-icon {
            font-size: 3.5rem;
            color: var(--modal-text-muted);
            margin-bottom: 1rem;
            opacity: 0.6;
        }

        .empty-state-text {
            color: var(--modal-text-muted);
            font-size: 1.05rem;
            font-weight: 500;
        }

        /* DataTables Custom Styling */
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

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .filter-container {
                padding: 1.25rem 1rem;
            }

            .filter-title {
                font-size: 1rem;
            }

            .table-container {
                padding: 1rem;
            }

            .modal-table thead th,
            .modal-table tbody td {
                padding: 0.75rem 0.875rem;
                font-size: 0.85rem;
            }

            .btn-detail {
                padding: 0.4rem 0.75rem;
                font-size: 0.8rem;
            }

            .badge-modern {
                padding: 0.35rem 0.65rem;
                font-size: 0.75rem;
            }
        }

        /* Loading Animation Enhancement */
        .spinner-border {
            width: 3rem;
            height: 3rem;
            color: var(--modal-accent);
        }

        /* Smooth Transitions */
        * {
            transition: background-color 0.3s ease, border-color 0.3s ease, transform 0.2s ease;
        }
    </style>
    <?php

    // =========================================================================
    // || BLOK HTML DENGAN STYLING MODERN ||
    // =========================================================================

    if (empty($targets)) { // <-- Disesuaikan menjadi $targets
        echo '<div class="alert alert-modern alert-info-modern">';
        echo '<i class="fas fa-info-circle"></i>';
        echo '<div>Tidak ada data target yang ditemukan untuk kategori ini.</div>';
        echo '</div>';
    } else {
        // Tentukan header kolom tanggal berdasarkan type
        
        $date_column_header = '';
        $show_date_column = true;
        if ($type === 'prioritas') {
            $date_column_header = 'Deadline';
        } elseif ($type === 'ongoing') {
            $show_date_column = false; // Jangan tampilkan kolom tanggal untuk ongoing
        } elseif ($type === 'terakhir_input') {
            $date_column_header = 'Tgl Input'; // Ganti header untuk terakhir_input
        } elseif ($type === 'terhenti') {
            $date_column_header = 'Tgl Input Terakhir'; // Ganti header untuk terhenti
        } else {
            $date_column_header = 'Tgl Selesai'; // Default untuk 'selesai' dll
        }

        
        // Kita tampilkan formulir ini HANYA jika tipenya 'terakhir_input'
        if ($type === 'terakhir_input') {
            // Ambil nilai tanggal dari URL (jika ada) untuk mengisi kembali form
            $form_start_date = $_GET['start_date'] ?? '';
            $form_end_date = $_GET['end_date'] ?? '';

            echo '<div class="filter-container">';
            echo '<form id="filterFormModal">';
            echo '<h6 class="filter-title">';
            echo '<i class="fas fa-filter"></i>';
            echo 'Filter Berdasarkan Tanggal Input';
            echo '</h6>';
            echo '<div class="row g-3 align-items-end">';
            
            // Tanggal Mulai
            echo '<div class="col-md-4">';
            echo '<div class="filter-form-group">';
            echo '<label for="startDateModal" class="filter-label">';
            echo '<i class="far fa-calendar-alt"></i>';
            echo 'Tanggal Mulai';
            echo '</label>';
            echo '<input type="date" class="form-control filter-input" id="startDateModal" value="' . htmlspecialchars($form_start_date) . '">';
            echo '</div>';
            echo '</div>';
            
            // Tanggal Selesai
            echo '<div class="col-md-4">';
            echo '<div class="filter-form-group">';
            echo '<label for="endDateModal" class="filter-label">';
            echo '<i class="far fa-calendar-check"></i>';
            echo 'Tanggal Selesai';
            echo '</label>';
            echo '<input type="date" class="form-control filter-input" id="endDateModal" value="' . htmlspecialchars($form_end_date) . '">';
            echo '</div>';
            echo '</div>';
            
            // Tombol Filter
            echo '<div class="col-md-2">';
            echo '<button type="submit" class="btn filter-btn filter-btn-primary w-100" id="btnFilterModal">';
            echo '<i class="fas fa-search"></i>';
            echo 'Filter';
            echo '</button>';
            echo '</div>';
            
            // Tombol Reset
            echo '<div class="col-md-2">';
            echo '<button type="button" class="btn filter-btn filter-btn-secondary w-100" id="btnResetModal">';
            echo '<i class="fas fa-sync-alt"></i>';
            echo 'Reset';
            echo '</button>';
            echo '</div>';
            
            echo '</div>'; // row
            echo '</form>';
            echo '</div>'; // filter-container
        }

        echo '<div class="table-container">';
        echo '<div class="table-responsive">';
        // Menggunakan class table yang sudah ditambahkan styling custom
        echo '<table id="targetsTable" class="modal-table table table-hover" style="width:100%">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>ID Target</th>';
        echo '<th>Nama Barang</th>';
        echo '<th>Permintaan</th>';

        // === PERUBAHAN HEADER KOLOM TANGGAL ===
        if ($show_date_column) {
            echo '<th>' . htmlspecialchars($date_column_header) . '</th>';
        }
        // === AKHIR PERUBAHAN HEADER ===

        // Tambah kolom spesifik untuk type tertentu
        if ($type === 'terakhir_input') {
            echo '<th>Nama Alur</th>';
            echo '<th>Jumlah Input</th>';
        } elseif ($type === 'terhenti') {
            echo '<th>Hari Terhenti</th>';
        } else {
            // Kolom standar untuk ongoing, selesai, prioritas
            echo '<th>Status</th>';
            echo '<th>Prioritas</th>';
        }
        echo '<th>Aksi</th>'; // Kolom aksi
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        foreach ($targets as $row) { // <-- Disesuaikan menjadi $targets, $row tetap
            echo '<tr>';
            echo '<td><strong>' . htmlspecialchars($row['id_target']) . '</strong></td>';
            echo '<td>' . htmlspecialchars($row['nama_barang']) . '</td>';
            echo '<td>' . htmlspecialchars($row['nama_permintaan']) . '</td>';

            // === PERUBAHAN DATA KOLOM TANGGAL (DENGAN LOGIKA DEADLINE) ===
            if ($show_date_column) {
                $tanggal_display = '-'; // Default

                if ($type === 'terakhir_input' && !empty($row['created_at'])) {
                    $tanggal_display = date('d M Y, H:i', strtotime($row['created_at']));
                
                } elseif ($type === 'terhenti' && !empty($row['last_report_time'])) {
                    $tanggal_display = date('d M Y', strtotime($row['last_report_time']));
                
                } elseif ($type === 'terhenti' && empty($row['last_report_time'])) {
                    $tanggal_display = '<span class="badge badge-modern badge-secondary-modern"><i class="fas fa-minus-circle"></i> Belum Ada Input</span>';
                
                } elseif (isset($row['tanggal_selesai']) && !empty($row['tanggal_selesai'])) {
                    // Logika ini sekarang berlaku untuk 'prioritas' dan 'selesai'
                    
                    // --- AWAL LOGIKA BARU ---
                    if ($type === 'prioritas') {
                        // $row['tanggal_selesai'] adalah alias dari priority_deadline
                        $hari_ini = new DateTime('today');
                        $tanggal_deadline = new DateTime(date('Y-m-d', strtotime($row['tanggal_selesai'])));

                        if ($hari_ini > $tanggal_deadline) {
                            $selisih = $hari_ini->diff($tanggal_deadline);
                            $hari_terlewat = $selisih->days; // Ambil jumlah hari
                            // Tampilkan HTML dengan badge modern
                            $tanggal_display = '<span class="badge badge-modern badge-danger-modern"><i class="fas fa-exclamation-triangle"></i> Lewat ' . $hari_terlewat . ' hari</span>';
                        } else {
                            $tanggal_display = date('d M Y', strtotime($row['tanggal_selesai']));
                        }
                    } else {
                        // Untuk 'selesai' atau tipe lain, cukup tampilkan tanggal
                        $tanggal_display = date('d M Y', strtotime($row['tanggal_selesai']));
                    }
                    // --- AKHIR LOGIKA BARU ---
                }

                echo '<td>' . $tanggal_display . '</td>';
            }
            // === AKHIR PERUBAHAN DATA ===

            // Kolom spesifik type
            if ($type === 'terakhir_input') {
                echo '<td><span class="badge badge-modern badge-info-modern"><i class="fas fa-stream"></i> ' . htmlspecialchars($row['nama_alur']) . '</span></td>';
                // Disesuaikan menjadi 'jumlah_selesai'
                echo '<td><strong>' . htmlspecialchars($row['jumlah_selesai'] ?? 'N/A') . '</strong></td>';
            } elseif ($type === 'terhenti') {
                echo '<td><span class="badge badge-modern badge-danger-modern"><i class="fas fa-clock"></i> ' . htmlspecialchars($row['days_stalled']) . ' Hari</span></td>';
            } else {
                // Kolom standar
                // Class badge disesuaikan dengan badge modern
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
                
                // --- AWAL LOGIKA BARU UNTUK PRIORITAS ---
                $prioritas_class = 'badge-secondary-modern'; // Default 'Normal'
                $prioritas_icon = 'fa-minus';
                
                if (isset($row['prioritas']) && $row['prioritas'] == 'Prioritas') {
                    $prioritas_class = 'badge-danger-modern'; // 'Prioritas'
                    $prioritas_icon = 'fa-exclamation-triangle';
                }
                echo '<td><span class="badge badge-modern ' . $prioritas_class . '"><i class="fas ' . $prioritas_icon . '"></i> ' . htmlspecialchars($row['prioritas'] ?? 'Normal') . '</span></td>';
                // --- AKHIR LOGIKA BARU ---
            }

            // Kolom Aksi (Tombol Detail)
            // === PERBAIKAN UX (DIMULAI) ===
            $button_text = '';
            $button_icon = '';
            $detail_link = '';

            // Cek tipe tabel yang sedang ditampilkan
if ($type === 'selesai') {
    // Sesuai permintaan: Arahkan ke halaman laporan dengan filter id_target
    // Pastikan path (jalur) file ini benar.
    $detail_link = "manajemen_laporan/rincian_laporan.php?id_target=" . htmlspecialchars($row['id_target']); // <-- PERUBAHAN DI SINI
    $button_icon = 'fa-file-alt';
    $button_text = 'Lihat Laporan';
            } else {
                // Perilaku default untuk 'ongoing', 'prioritas', 'terhenti', dll.
                $detail_link = "alur_produksi.php?id_target=" . htmlspecialchars($row['id_target']);
                $button_icon = 'fa-sitemap'; // Ikon baru (lebih relevan)
                $button_text = 'Lihat Alur'; // Teks baru (lebih jelas)
            }
            // === PERBAIKAN UX (SELESAI) ===

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
        echo '</div>'; // table-responsive
        echo '</div>'; // table-container
    }
    // =========================================================================
    // || AKHIR BLOK HTML ||
    // =========================================================================

} catch (PDOException $e) {
    http_response_code(500);
    echo '<div class="alert alert-modern alert-danger-modern m-3">';
    echo '<i class="fas fa-exclamation-triangle"></i>';
    echo '<div><strong>Error API:</strong> ' . $e->getMessage() . '</div>';
    echo '</div>';
}
?>