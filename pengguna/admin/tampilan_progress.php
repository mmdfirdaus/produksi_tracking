<?php
session_start();

// Pengecekan sesi dan peran
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !in_array($_SESSION["role"], ['admin', 'superadmin'])) {
    header("location: ../../auth/login.php");
    exit;
}

include '../../system/database_connection.php';

// Validasi ID Target dari URL
$id_target = isset($_GET['id_target']) ? (int)$_GET['id_target'] : 0;
if ($id_target === 0) {
    die("<h1>ID Target tidak valid.</h1>");
}

// Ambil informasi nama target dan nama barang untuk judul
$info_stmt = $pdo->prepare("
    SELECT pt.nama_permintaan, mb.nama_barang 
    FROM production_targets pt 
    JOIN master_barang mb ON pt.id_barang = mb.id_barang 
    WHERE pt.id_target = ?
");
$info_stmt->execute([$id_target]);
$info = $info_stmt->fetch(PDO::FETCH_ASSOC);

if (!$info) {
    die("<h1>Target Produksi tidak ditemukan.</h1>");
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Progress Produksi: <?php echo htmlspecialchars($info['nama_permintaan']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 2rem 1rem;
            position: relative;
            overflow-x: hidden;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('data:image/svg+xml,<svg width="100" height="100" xmlns="http://www.w3.org/2000/svg"><defs><pattern id="grid" width="100" height="100" patternUnits="userSpaceOnUse"><path d="M 100 0 L 0 0 0 100" fill="none" stroke="rgba(255,255,255,0.03)" stroke-width="1"/></pattern></defs><rect width="100%" height="100%" fill="url(%23grid)"/></svg>');
            z-index: 0;
            pointer-events: none;
        }

        .container-fluid {
            position: relative;
            z-index: 1;
            max-width: 1400px;
            margin: 0 auto;
        }

        /* Header Styles */
        .header-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 2.5rem;
            margin-bottom: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            animation: fadeInDown 0.6s ease-out;
        }

        .header-title {
            color: #ffffff;
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            text-shadow: 2px 2px 8px rgba(0, 0, 0, 0.2);
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .header-subtitle {
            color: #ffd93d;
            font-size: 1.5rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Stats Cards */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
            animation: fadeInUp 0.6s ease-out 0.2s both;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 1.5rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, #4facfe 0%, #00f2fe 100%);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.2);
        }

        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 0.25rem;
        }

        .stat-label {
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* Alur Section */
        .alur-section {
            margin-bottom: 2rem;
            animation: fadeInUp 0.6s ease-out;
        }

        .alur-header {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 16px 16px 0 0;
            padding: 1.5rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-bottom: none;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .alur-title {
            font-size: 1.8rem;
            color: #ffffff;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alur-badge {
            background: rgba(255, 255, 255, 0.2);
            padding: 0.5rem 1rem;
            border-radius: 12px;
            font-size: 0.9rem;
            font-weight: 500;
        }

        /* Table Styles */
        .table-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 0 0 16px 16px;
            overflow: hidden;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .modern-table {
            width: 100%;
            margin: 0;
            background: transparent;
        }

        .modern-table thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .modern-table thead th {
            color: #ffffff;
            font-weight: 600;
            padding: 1.25rem 1rem;
            border: none;
            font-size: 1rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .modern-table tbody tr {
            transition: all 0.3s ease;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }

        .modern-table tbody tr:hover {
            background: rgba(102, 126, 234, 0.08);
            transform: scale(1.01);
        }

        .modern-table tbody td {
            padding: 1.25rem 1rem;
            color: #2d3748;
            font-size: 1rem;
            vertical-align: middle;
        }

        .component-name {
            font-weight: 600;
            color: #1a202c;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .component-icon {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .number-badge {
            background: rgba(102, 126, 234, 0.1);
            padding: 0.5rem 1rem;
            border-radius: 10px;
            font-weight: 600;
            display: inline-block;
            min-width: 60px;
            text-align: center;
        }

        .number-success {
            background: rgba(16, 185, 129, 0.1);
            color: #059669;
        }

        .number-warning {
            background: rgba(245, 158, 11, 0.1);
            color: #d97706;
        }

        /* Modern Progress Bar */
        .progress-modern {
            height: 32px;
            background: rgba(0, 0, 0, 0.05);
            border-radius: 16px;
            overflow: hidden;
            position: relative;
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .progress-bar-modern {
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.9rem;
            transition: width 0.6s ease;
            position: relative;
            overflow: hidden;
        }

        .progress-bar-modern::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            animation: shimmer 2s infinite;
        }

        @keyframes shimmer {
            0% { left: -100%; }
            100% { left: 100%; }
        }

        .progress-success {
            background: linear-gradient(90deg, #10b981 0%, #059669 100%);
            color: white;
        }

        .progress-primary {
            background: linear-gradient(90deg, #3b82f6 0%, #2563eb 100%);
            color: white;
        }

        .progress-warning {
            background: linear-gradient(90deg, #f59e0b 0%, #d97706 100%);
            color: white;
        }

        .progress-percentage {
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        /* Loading Indicator */
        .loading-indicator {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: rgba(0, 0, 0, 0.85);
            backdrop-filter: blur(10px);
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 16px;
            display: none;
            align-items: center;
            gap: 0.75rem;
            z-index: 1000;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
            animation: slideInRight 0.3s ease;
        }

        .loading-indicator.show {
            display: flex;
        }

        /* Animations */
        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
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

        @keyframes slideInRight {
            from {
                transform: translateX(100%);
            }
            to {
                transform: translateX(0);
            }
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .spinner {
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        /* Alert Styles */
        .alert-modern {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 2rem;
            border: none;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            animation: fadeInUp 0.6s ease-out;
        }

        .alert-modern.alert-danger {
            border-left: 4px solid #ef4444;
        }

        .alert-modern.alert-info {
            border-left: 4px solid #3b82f6;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 16px;
            animation: fadeInUp 0.6s ease-out;
        }

        .empty-state i {
            font-size: 4rem;
            color: #667eea;
            margin-bottom: 1rem;
        }

        .empty-state h3 {
            color: #1a202c;
            margin-bottom: 0.5rem;
        }

        .empty-state p {
            color: #718096;
        }

        /* Last Update Badge */
        .last-update {
            position: fixed;
            top: 20px;
            right: 20px;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            padding: 0.75rem 1.25rem;
            border-radius: 12px;
            color: white;
            font-size: 0.9rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            z-index: 100;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .header-title {
                font-size: 1.8rem;
            }
            
            .header-subtitle {
                font-size: 1.2rem;
            }

            .stats-container {
                grid-template-columns: 1fr;
            }

            .modern-table {
                font-size: 0.9rem;
            }

            .modern-table thead th,
            .modern-table tbody td {
                padding: 0.75rem 0.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="last-update">
        <i class="bi bi-clock-history"></i>
        <span id="last-update-time">Memuat...</span>
    </div>

    <div class="container-fluid">
        <div class="header-card">
            <h1 class="header-title">
                <i class="bi bi-box-seam"></i>
                <?php echo htmlspecialchars($info['nama_barang']); ?>
            </h1>
            <h2 class="header-subtitle">
                <i class="bi bi-clipboard-check"></i>
                <?php echo htmlspecialchars($info['nama_permintaan']); ?>
            </h2>
        </div>

        <div class="stats-container" id="stats-container"></div>

        <div id="progress-container"></div>

        <div class="loading-indicator" id="loading-indicator">
            <div class="spinner"></div>
            <span>Memperbarui data...</span>
        </div>
    </div>

    <script>
        const targetId = <?php echo $id_target; ?>;
        const progressContainer = document.getElementById('progress-container');
        const loadingIndicator = document.getElementById('loading-indicator');
        const statsContainer = document.getElementById('stats-container');
        const lastUpdateTime = document.getElementById('last-update-time');

        function updateLastUpdateTime() {
            const now = new Date();
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const seconds = String(now.getSeconds()).padStart(2, '0');
            lastUpdateTime.textContent = `Update: ${hours}:${minutes}:${seconds}`;
        }

        function calculateStats(data) {
            let totalKomponen = 0;
            let totalKebutuhan = 0;
            let totalSelesai = 0;
            let totalAlur = Object.keys(data).length;

            for (const alur in data) {
                data[alur].forEach(komponen => {
                    totalKomponen++;
                    totalKebutuhan += parseInt(komponen.kebutuhan_total);
                    totalSelesai += parseInt(komponen.total_selesai);
                });
            }

            const overallPercentage = totalKebutuhan > 0 ? ((totalSelesai / totalKebutuhan) * 100).toFixed(1) : 0;

            return { totalKomponen, totalKebutuhan, totalSelesai, totalAlur, overallPercentage };
        }

        function renderStats(stats) {
            const progressColor = stats.overallPercentage >= 100 ? '#10b981' : 
                                 stats.overallPercentage > 70 ? '#3b82f6' : '#f59e0b';

            statsContainer.innerHTML = `
                <div class="stat-card">
                    <div class="stat-icon" style="color: ${progressColor}">
                        <i class="bi bi-bullseye"></i>
                    </div>
                    <div class="stat-value">${stats.overallPercentage}%</div>
                    <div class="stat-label">Progress Keseluruhan</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="color: #667eea">
                        <i class="bi bi-layers"></i>
                    </div>
                    <div class="stat-value">${stats.totalAlur}</div>
                    <div class="stat-label">Total Alur</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="color: #f093fb">
                        <i class="bi bi-gear"></i>
                    </div>
                    <div class="stat-value">${stats.totalKomponen}</div>
                    <div class="stat-label">Total Komponen</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="color: #10b981">
                        <i class="bi bi-check-circle"></i>
                    </div>
                    <div class="stat-value">${stats.totalSelesai.toLocaleString()}</div>
                    <div class="stat-label">Total Selesai</div>
                </div>
            `;
        }

        function fetchProgress() {
            loadingIndicator.classList.add('show');
            
            fetch(`get_progress_preview.php?id_target=${targetId}`)
                .then(response => response.ok ? response.json() : Promise.reject('Network response was not ok'))
                .then(data => {
                    if (data.error) {
                        progressContainer.innerHTML = `<div class="alert alert-danger alert-modern"><i class="bi bi-exclamation-triangle"></i> ${data.error}</div>`;
                        statsContainer.innerHTML = '';
                        return;
                    }

                    if (Object.keys(data).length === 0) {
                        progressContainer.innerHTML = `
                            <div class="empty-state">
                                <i class="bi bi-inbox"></i>
                                <h3>Belum Ada Data</h3>
                                <p>Belum ada data progres untuk target produksi ini.</p>
                            </div>
                        `;
                        statsContainer.innerHTML = '';
                        return;
                    }

                    // Calculate and render stats
                    const stats = calculateStats(data);
                    renderStats(stats);

                    // Render progress data
                    let html = '';
                    for (const alur in data) {
                        const komponenCount = data[alur].length;
                        html += `
                            <div class="alur-section">
                                <div class="alur-header">
                                    <h3 class="alur-title">
                                        <i class="bi bi-arrow-right-circle"></i>
                                        ${alur}
                                    </h3>
                                    <span class="alur-badge">${komponenCount} Komponen</span>
                                </div>
                                <div class="table-container">
                                    <table class="modern-table">
                                        <thead>
                                            <tr>
                                                <th style="width: 35%;">Nama Komponen</th>
                                                <th class="text-center">Kebutuhan</th>
                                                <th class="text-center">Selesai</th>
                                                <th class="text-center">Sisa</th>
                                                <th style="width: 30%;">Progress</th>
                                            </tr>
                                        </thead>
                                        <tbody>`;
                        
                        data[alur].forEach(komponen => {
                            const kebutuhan = parseInt(komponen.kebutuhan_total);
                            const selesai = parseInt(komponen.total_selesai);
                            const sisa = kebutuhan - selesai;
                            const percentage = kebutuhan > 0 ? ((selesai / kebutuhan) * 100).toFixed(1) : 0;
                            const progressBarClass = percentage >= 100 ? 'progress-success' : 
                                                    (percentage > 70 ? 'progress-primary' : 'progress-warning');
                            const icon = percentage >= 100 ? '✓' : percentage > 70 ? '⏳' : '○';

                            html += `
                                <tr>
                                    <td>
                                        <div class="component-name">
                                            <span class="component-icon"></span>
                                            ${komponen.nama_komponen}
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <span class="number-badge">${kebutuhan.toLocaleString()}</span>
                                    </td>
                                    <td class="text-center">
                                        <span class="number-badge number-success">${selesai.toLocaleString()}</span>
                                    </td>
                                    <td class="text-center">
                                        <span class="number-badge number-warning">${sisa.toLocaleString()}</span>
                                    </td>
                                    <td>
                                        <div class="progress-modern">
                                            <div class="progress-bar-modern ${progressBarClass}" style="width: ${percentage}%;">
                                                <span class="progress-percentage">${icon} ${percentage}%</span>
                                            </div>
                                        </div>
                                    </td>
                                </tr>`;
                        });
                        html += `</tbody></table></div></div>`;
                    }
                    progressContainer.innerHTML = html;
                    updateLastUpdateTime();
                })
                .catch(error => {
                    progressContainer.innerHTML = `<div class="alert alert-danger alert-modern"><i class="bi bi-exclamation-triangle"></i> Terjadi kesalahan saat mengambil data.</div>`;
                    console.error('Error fetching progress:', error);
                })
                .finally(() => {
                    setTimeout(() => { 
                        loadingIndicator.classList.remove('show');
                    }, 500);
                });
        }

        // Panggil fungsi pertama kali saat halaman dimuat
        fetchProgress();

        // Atur interval untuk refresh data setiap 30 detik
        setInterval(fetchProgress, 30000);
    </script>
</body>
</html>