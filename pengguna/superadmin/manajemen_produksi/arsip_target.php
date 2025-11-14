<?php
session_start();

// Pengecekan sesi dan peran
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'superadmin') {
    header("location: ../../auth/login.php");
    exit;
}

include '../../../templates/header_superadmin.php';
include '../../../system/database_connection.php';

if (!isset($_GET['id_barang'])) {
    header("Location: ../master_data/kelola_master_barang.php");
    exit;
}
$id_barang = (int)$_GET['id_barang'];

// Ambil nama barang untuk judul
$barang_stmt = $pdo->prepare("SELECT nama_barang FROM master_barang WHERE id_barang = ?");
$barang_stmt->execute([$id_barang]);
$barang = $barang_stmt->fetch(PDO::FETCH_ASSOC);

if (!$barang) {
    die("Barang tidak ditemukan.");
}

// Ambil semua target yang tidak aktif (diarsip) untuk barang ini
$targets_stmt = $pdo->prepare("
    SELECT * FROM production_targets 
    WHERE id_barang = ? AND is_active = 0 
    ORDER BY completed_at DESC, created_at DESC
");
$targets_stmt->execute([$id_barang]);
$targets_arsip = $targets_stmt->fetchAll(PDO::FETCH_ASSOC);

// Hitung statistik
$total_arsip = count($targets_arsip);
$total_unit_arsip = array_sum(array_column($targets_arsip, 'jumlah_unit'));
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Arsip Target - <?php echo htmlspecialchars($barang['nama_barang']); ?></title> 
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* CSS Anda tetap sama, tidak ada perubahan */
        :root {
            --primary-color: #4f46e5;
            --primary-hover: #4338ca;
            --secondary-color: #64748b;
            --success-color: #10b981;
            --success-hover: #059669;
            --danger-color: #ef4444;
            --danger-hover: #dc2626;
            --warning-color: #f59e0b;
            --gray-50: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e1;
            --gray-400: #94a3b8;
            --gray-500: #64748b;
            --gray-600: #475569;
            --gray-700: #334155;
            --gray-800: #1e293b;
            --gray-900: #0f172a;
            --border-radius: 12px;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        * { box-sizing: border-box; }
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .main-container { padding: 2rem 1rem; max-width: 1200px; margin: 0 auto; min-height: calc(100vh - 4rem); }
        .page-header { background: white; padding: 2rem; border-radius: var(--border-radius); box-shadow: var(--shadow-md); margin-bottom: 2rem; border: 1px solid var(--gray-200); position: relative; overflow: hidden; }
        .page-header::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, var(--primary-color), var(--success-color)); }
        .page-title { display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem; }
        .page-title i { width: 2.5rem; height: 2.5rem; background: linear-gradient(135deg, var(--primary-color), #6366f1); color: white; display: flex; align-items: center; justify-content: center; border-radius: 10px; font-size: 1.25rem; box-shadow: var(--shadow); }
        .page-title h1 { font-size: 1.875rem; font-weight: 700; color: var(--gray-800); margin: 0; letter-spacing: -0.025em; }
        .breadcrumb-text { color: var(--gray-600); font-weight: 500; margin: 0; }
        .back-btn { background: white; color: var(--gray-700); border: 2px solid var(--gray-200); padding: 0.75rem 1.5rem; border-radius: 10px; font-weight: 600; text-decoration: none; transition: all 0.2s ease; display: inline-flex; align-items: center; gap: 0.5rem; box-shadow: var(--shadow-sm); }
        .back-btn:hover { background: var(--gray-50); border-color: var(--gray-300); color: var(--gray-800); transform: translateY(-1px); box-shadow: var(--shadow); }
        .modern-card { background: white; border-radius: var(--border-radius); box-shadow: var(--shadow-md); border: 1px solid var(--gray-200); overflow: hidden; backdrop-filter: blur(10px); }
        .card-header-custom { background: linear-gradient(135deg, var(--gray-50), white); padding: 1.5rem 2rem; border-bottom: 1px solid var(--gray-200); }
        .card-header-custom h5 { margin: 0; font-weight: 600; color: var(--gray-800); display: flex; align-items: center; gap: 0.5rem; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
        .stat-card { background: white; padding: 1.5rem; border-radius: var(--border-radius); box-shadow: var(--shadow); border: 1px solid var(--gray-200); text-align: center; transition: all 0.2s ease; }
        .stat-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-lg); }
        .stat-icon { width: 3rem; height: 3rem; background: linear-gradient(135deg, var(--primary-color), #6366f1); color: white; border-radius: 12px; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; font-size: 1.25rem; }
        .stat-number { font-size: 2rem; font-weight: 700; color: var(--gray-800); margin-bottom: 0.25rem; }
        .stat-label { color: var(--gray-600); font-size: 0.875rem; font-weight: 500; }
        .table-container { padding: 0; overflow: hidden; }
        .table { margin: 0; }
        .table thead th { background: var(--gray-50); border-bottom: 2px solid var(--gray-200); color: var(--gray-700); font-weight: 600; font-size: 0.875rem; text-transform: uppercase; letter-spacing: 0.05em; padding: 1rem 1.5rem; }
        .table tbody td { padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--gray-200); vertical-align: middle; font-weight: 500; }
        .table tbody tr:hover { background: var(--gray-50); }
        .table tbody tr:last-child td { border-bottom: none; }
        .status-badge { padding: 0.5rem 1rem; border-radius: 20px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; }
        .status-ongoing { background: rgba(249, 115, 22, 0.1); color: #ea580c; border: 1px solid rgba(249, 115, 22, 0.2); }
        .status-completed { background: rgba(16, 185, 129, 0.1); color: #059669; border: 1px solid rgba(16, 185, 129, 0.2); }
        .status-cancelled { background: rgba(239, 68, 68, 0.1); color: #dc2626; border: 1px solid rgba(239, 68, 68, 0.2); }
        .empty-state { text-align: center; padding: 4rem 2rem; color: var(--gray-500); }
        .empty-state i { font-size: 4rem; margin-bottom: 1rem; opacity: 0.5; }
        .empty-state h5 { font-weight: 600; margin-bottom: 0.5rem; color: var(--gray-600); }
        .alert { border-radius: 10px; border: 1px solid; padding: 1rem 1.5rem; margin-bottom: 1.5rem; }
        .alert-success { background: rgba(16, 185, 129, 0.05); border-color: rgba(16, 185, 129, 0.2); color: var(--success-hover); }
        .alert-danger { background: rgba(239, 68, 68, 0.05); border-color: rgba(239, 68, 68, 0.2); color: var(--danger-hover); }
        .date-text { color: var(--gray-600); font-size: 0.875rem; }
        .quantity-badge { background: linear-gradient(135deg, var(--primary-color), #6366f1); color: white; padding: 0.25rem 0.75rem; border-radius: 20px; font-weight: 600; font-size: 0.875rem; }
        tr.reason-row:hover { background-color: white !important; }
        tr.reason-row td { border-bottom: 1px solid var(--gray-200) !important; padding: 0 1.5rem 1rem 1.5rem; }
        @media (max-width: 768px) { .main-container { padding: 1rem 0.5rem; } .page-header { padding: 1.5rem; } .page-title h1 { font-size: 1.5rem; } .table-responsive { font-size: 0.875rem; } .stats-grid { grid-template-columns: 1fr; } }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .fade-in-up { animation: fadeInUp 0.5s ease-out; }
    </style>
</head>
<body>

    <div aria-live="polite" aria-atomic="true" class="position-relative">
        <div id="toast-container" class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1055;">
            </div>
    </div>

    <div class="main-container">
        <div class="page-header fade-in-up">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                <div class="page-title">
                    <i class="bi bi-archive-fill"></i>
                    <div>
                        <h1>Arsip Target Produksi</h1>
                        <p class="breadcrumb-text">Untuk barang: <strong><?php echo htmlspecialchars($barang['nama_barang']); ?></strong></p>
                    </div>
                </div>
                <a href="detail_barang.php?id=<?php echo $id_barang; ?>" class="back-btn">
                    <i class="bi bi-arrow-left"></i>
                    Kembali ke Target Aktif
                </a>
            </div>
        </div>

        <div class="stats-grid fade-in-up">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="bi bi-archive"></i>
                </div>
                <div class="stat-number"><?php echo $total_arsip; ?></div>
                <div class="stat-label">Total Arsip</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="bi bi-box-seam"></i> 
                </div>
                <div class="stat-number"><?php echo $total_unit_arsip; ?></div>
                <div class="stat-label">Total Unit</div>
            </div>
        </div>

        <div class="modern-card fade-in-up">
            <div class="card-header-custom">
                <h5><i class="bi bi-list-ul me-2"></i>Daftar Target yang Diarsipkan</h5>
            </div>
            <div class="table-container">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Nama Permintaan</th>
                                <th class="text-center">Jumlah Unit</th>
                                <th class="text-center">Status</th>
                                <th class="text-center">Tanggal Dibuat</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($targets_arsip)): ?>
                                <tr>
                                    <td colspan="5" class="empty-state">
                                        <i class="bi bi-inbox"></i>
                                        <h5>Arsip Kosong</h5>
                                        <p class="mb-0">Belum ada target yang diarsipkan untuk barang ini.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($targets_arsip as $target): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold"><?php echo htmlspecialchars($target['nama_permintaan']); ?></div>
                                        </td>
                                        <td class="text-center">
                                            <span class="quantity-badge"><?php echo htmlspecialchars($target['jumlah_unit']); ?></span>
                                        </td>
                                        <td class="text-center">
                                            <?php
                                                $status = strtolower($target['status']);
                                                $badge_class = 'status-ongoing'; 
                                                if ($status == 'completed') $badge_class = 'status-completed';
                                                if ($status == 'cancelled') $badge_class = 'status-cancelled';
                                            ?>
                                            <span class="status-badge <?php echo $badge_class; ?>"><?php echo htmlspecialchars(ucfirst($target['status'])); ?></span>
                                        </td>
                                        <td class="text-center">
                                            <span class="date-text"><?php echo date('d M Y', strtotime($target['created_at'])); ?></span>
                                        </td>
                                        <td class="text-end">
                                            <button type="button" class="btn btn-success btn-sm me-2" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#aktifkanTargetModal"
                                                    data-id-target="<?php echo $target['id_target']; ?>"
                                                    data-id-barang="<?php echo $id_barang; ?>"
                                                    data-nama-barang="<?php echo htmlspecialchars($barang['nama_barang'], ENT_QUOTES, 'UTF-8'); ?>">
                                                <i class="bi bi-arrow-counterclockwise"></i> Aktifkan
                                            </button>
                                            
                                            <button type="button" class="btn btn-danger btn-sm"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#hapusPermanenTargetModal"
                                                    data-id-target="<?php echo $target['id_target']; ?>"
                                                    data-id-barang="<?php echo $id_barang; ?>"
                                                    data-nama-barang="<?php echo htmlspecialchars($barang['nama_barang'], ENT_QUOTES, 'UTF-8'); ?>">
                                                <i class="bi bi-trash"></i> Hapus Permanen
                                            </button>
                                        </td>
                                    </tr>
                                    <?php if (!empty($target['alasan_nonaktif'])): ?>
                                    <tr class="reason-row">
                                        <td colspan="5">
                                            <div class="alert alert-secondary mt-2 mb-0 py-2 px-3">
                                                <h6 class="alert-heading mb-1" style="font-size: 0.9rem;">
                                                    <i class="bi bi-info-circle-fill me-2"></i>Alasan Dinonaktifkan:
                                                </h6>
                                                <p class="mb-0 fst-italic">
                                                    "<?php echo htmlspecialchars($target['alasan_nonaktif']); ?>"
                                                </p>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="aktifkanTargetModal" tabindex="-1" aria-labelledby="aktifkanTargetModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 20px;">
                <div class="modal-header bg-success text-white" style="border-top-left-radius: 20px; border-top-right-radius: 20px;">
                    <h5 class="modal-title" id="aktifkanTargetModalLabel">
                        <i class="bi bi-check-circle-fill me-2"></i> Konfirmasi Aktivasi Target
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center py-3">
                        <i class="bi bi-arrow-counterclockwise text-success" style="font-size: 4rem;"></i>
                        <h5 class="mt-3 mb-3">Aktifkan kembali target ini?</h5>
                        <p class="text-muted mb-2">
                            Anda akan mengaktifkan kembali target untuk barang:
                        </p>
                        <div class="alert alert-success">
                            <strong id="aktifkan_nama_barang"></strong>
                        </div>
                        <p class="text-muted mt-3">
                            Target ini akan muncul kembali di halaman detail barang dan dapat digunakan untuk produksi.
                        </p>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0 justify-content-center">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius: 50px; padding: 0.75rem 1.5rem;">
                        <i class="bi bi-x-lg me-1"></i> Batal
                    </button>
                    <a id="btn-confirm-aktifkan" class="btn btn-success" href="#" style="border-radius: 50px; padding: 0.75rem 1.5rem;">
                        <i class="bi bi-check-lg me-1"></i> Ya, Aktifkan
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="hapusPermanenTargetModal" tabindex="-1" aria-labelledby="hapusPermanenTargetModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 20px;">
                <div class="modal-header bg-danger text-white" style="border-top-left-radius: 20px; border-top-right-radius: 20px;">
                    <h5 class="modal-title" id="hapusPermanenTargetModalLabel">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i> Konfirmasi Hapus Permanen
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center py-3">
                        <i class="bi bi-trash3-fill text-danger" style="font-size: 4rem;"></i>
                        <h5 class="mt-3 mb-3">Apakah Anda benar-benar yakin?</h5>
                        <p class="text-muted mb-2">
                            Anda akan menghapus target ini secara permanen:
                        </p>
                        <div class="alert alert-danger">
                            <strong id="hapus_permanen_nama_barang"></strong>
                        </div>
                        <p class="text-danger fw-bold mt-3">
                            <i class="bi bi-info-circle me-1"></i>
                            Tindakan ini tidak dapat dibatalkan!
                        </p>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0 justify-content-center">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius: 50px; padding: 0.75rem 1.5rem;">
                        <i class="bi bi-x-lg me-1"></i> Batal
                    </button>
                    <a id="btn-confirm-hapus-permanen" class="btn btn-danger" href="#" style="border-radius: 50px; padding: 0.75rem 1.5rem;">
                        <i class="bi bi-trash me-1"></i> Ya, Hapus Permanen
                    </a>
                </div>
            </div>
        </div>
    </div>

    
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        // === Logic untuk Modal Aktifkan Target ===
        var aktifkanModal = document.getElementById('aktifkanTargetModal');
        aktifkanModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var idTarget = button.getAttribute('data-id-target');
            var idBarang = button.getAttribute('data-id-barang');
            var namaBarang = button.getAttribute('data-nama-barang');
            var confirmUrl = `proses_arsip_target.php?action=aktifkan&id_target=${idTarget}&id_barang=${idBarang}`;
            var modalNamaBarang = aktifkanModal.querySelector('#aktifkan_nama_barang');
            var confirmButton = aktifkanModal.querySelector('#btn-confirm-aktifkan');
            modalNamaBarang.textContent = namaBarang;
            confirmButton.setAttribute('href', confirmUrl);
        });

        // === Logic untuk Modal Hapus Permanen Target ===
        var hapusPermanenModal = document.getElementById('hapusPermanenTargetModal');
        hapusPermanenModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var idTarget = button.getAttribute('data-id-target');
            var idBarang = button.getAttribute('data-id-barang');
            var namaBarang = button.getAttribute('data-nama-barang');
            var confirmUrl = `proses_arsip_target.php?action=hapus_permanen&id_target=${idTarget}&id_barang=${idBarang}`;
            var modalNamaBarang = hapusPermanenModal.querySelector('#hapus_permanen_nama_barang');
            var confirmButton = hapusPermanenModal.querySelector('#btn-confirm-hapus-permanen');
            modalNamaBarang.textContent = namaBarang;
            confirmButton.setAttribute('href', confirmUrl);
        });

        // Add fade-in animation to table rows (Kode lama yang dipertahankan)
        const tableRows = document.querySelectorAll('tbody tr');
        tableRows.forEach((row, index) => {
            if (!row.querySelector('.empty-state')) { // Jangan animasikan pesan kosong
                row.style.opacity = '0';
                row.style.animation = `fadeInUp 0.5s ease-out ${index * 0.07}s forwards`;
            }
        });
    });
    </script>

    <?php
    // Cek apakah ada flash message di session
    if (isset($_SESSION['flash_message'])) {
        // Ambil data pesan dari session
        $flashMessage = $_SESSION['flash_message'];
        $status = $flashMessage['status']; // 'success' atau 'danger'
        $message = $flashMessage['message'];

        // Hapus pesan dari session agar tidak muncul lagi
        unset($_SESSION['flash_message']);
    ?>

    <script>
    // Jalankan script setelah seluruh halaman dimuat
    document.addEventListener('DOMContentLoaded', function() {
        // Panggil fungsi untuk menampilkan toast dengan data dari PHP
        showToast('<?php echo $status; ?>', '<?php echo addslashes($message); ?>');
    });

    /**
     * Fungsi untuk membuat dan menampilkan Bootstrap Toast secara dinamis
     * @param {string} status - 'success' or 'danger'
     * @param {string} message - The message to display
     */
    function showToast(status, message) {
        var toastContainer = document.getElementById('toast-container');
        if (!toastContainer) return;

        // Tentukan ikon dan judul berdasarkan status
        var icon = (status === 'success') ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill';
        var title = (status === 'success') ? 'Berhasil' : 'Terjadi Kesalahan';
        
        // Buat elemen toast baru
        var toastEl = document.createElement('div');
        toastEl.classList.add('toast', 'align-items-center', 'text-white', `bg-${status}`, 'border-0');
        toastEl.setAttribute('role', 'alert');
        toastEl.setAttribute('aria-live', 'assertive');
        toastEl.setAttribute('aria-atomic', 'true');

        // Isi konten toast dengan HTML
        toastEl.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">
                    <i class="bi ${icon} me-2"></i>
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        `;

        // Tambahkan toast baru ke dalam container
        toastContainer.appendChild(toastEl);

        // Inisialisasi toast menggunakan Bootstrap 5
        var toast = new bootstrap.Toast(toastEl, {
            delay: 5000, // Durasi 5 detik
            autohide: true
        });

        // Tampilkan toast
        toast.show();

        // Hapus elemen toast dari DOM setelah selesai ditampilkan
        toastEl.addEventListener('hidden.bs.toast', function () {
            toastEl.remove();
        });
    }
    </script>

    <?php
    } // Tutup blok if
    ?>
</body>
</html>

<?php include '../../../templates/footer.php'; ?>