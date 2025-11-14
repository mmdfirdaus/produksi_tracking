<?php
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'superadmin') {
    header("location: ../../auth/login.php");
    exit;
}

include '../../../templates/header_superadmin.php'; 
include '../../../system/database_connection.php';

try {
    $stmt = $pdo->query("SELECT * FROM master_alur ORDER BY urutan ASC");
    $alurs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Alur Produksi</title>
    
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.0/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <style>
        /* CSS Anda tetap sama, tidak perlu diubah */
        #editAlurModal, #hapusAlurModal {
            z-index: 1051 !important; 
        }

        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
        
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --success-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --danger-gradient: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            --warning-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --bg-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --glass-bg: rgba(255, 255, 255, 0.25);
            --glass-border: rgba(255, 255, 255, 0.18);
            --text-primary: #2d3748;
            --text-secondary: #718096;
            --shadow-soft: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            --shadow-glow: 0 0 20px rgba(102, 126, 234, 0.4);
        }

        /* ... (seluruh CSS Anda yang lain ada di sini) ... */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-gradient);
            min-height: 100vh;
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
            background-image:
                radial-gradient(circle at 25% 25%, rgba(255, 255, 255, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 75% 75%, rgba(255, 255, 255, 0.1) 0%, transparent 50%);
            pointer-events: none;
            z-index: 0;
        }

        .container-fluid {
            position: relative;
            /*z-index: 1;*/
            padding: 2rem;
        }

        .page-header {
            text-align: center;
            margin-bottom: 3rem;
            animation: slideInDown 0.8s ease-out;
        }

        .page-title {
            font-size: 3rem;
            font-weight: 800;
            background: linear-gradient(135deg, #ffffff 0%, rgba(255, 255, 255, 0.8) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
            text-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .page-subtitle {
            color: rgba(255, 255, 255, 0.8);
            font-size: 1.2rem;
            font-weight: 400;
        }

        .glass-card {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            box-shadow: var(--shadow-soft);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .glass-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
        }

        .glass-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-glow);
        }

        .card-header-modern {
            background: transparent;
            border: none;
            padding: 1.5rem 1.5rem 0;
        }

        .card-title-modern {
            font-size: 1.25rem;
            font-weight: 700;
            color: white;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .card-title-modern i {
            font-size: 1.5rem;
            background: var(--success-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .card-body-modern {
            padding: 1.5rem;
        }

        .form-control-modern {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            color: white;
            padding: 0.75rem 1rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .form-control-modern:focus {
            background: rgba(255, 255, 255, 0.15);
            border-color: rgba(255, 255, 255, 0.4);
            box-shadow: 0 0 0 0.2rem rgba(255, 255, 255, 0.15);
            color: white;
        }

        .form-control-modern::placeholder {
            color: rgba(255, 255, 255, 0.6);
        }
        
        /* Mengatasi warna text autofill di browser Chrome/Edge */
        .form-control-modern:-webkit-autofill,
        .form-control-modern:-webkit-autofill:hover,
        .form-control-modern:-webkit-autofill:focus,
        .form-control-modern:-webkit-autofill:active {
            -webkit-box-shadow: 0 0 0 30px rgba(255, 255, 255, 0.1) inset !important;
            -webkit-text-fill-color: white !important;
        }

        .form-label-modern {
            color: rgba(255, 255, 255, 0.9);
            font-weight: 600;
            margin-bottom: 0.5rem;
            font-size: 0.95rem;
        }

        .btn-modern {
            font-weight: 600;
            padding: 0.75rem 1.5rem;
            border-radius: 12px;
            border: none;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 0.9rem;
            position: relative;
            overflow: hidden;
        }

        .btn-modern::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s ease;
        }

        .btn-modern:hover::before {
            left: 100%;
        }

        .btn-success-modern {
            background: var(--success-gradient);
            color: white;
            box-shadow: 0 4px 15px rgba(79, 172, 254, 0.4);
        }

        .btn-success-modern:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(79, 172, 254, 0.6);
        }

        .btn-warning-modern {
            background: var(--warning-gradient);
            color: white;
            box-shadow: 0 4px 15px rgba(240, 147, 251, 0.4);
        }

        .btn-warning-modern:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(240, 147, 251, 0.6);
        }

        .btn-danger-modern {
            background: var(--danger-gradient);
            color: white;
            box-shadow: 0 4px 15px rgba(250, 112, 154, 0.4);
        }

        .btn-danger-modern:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(250, 112, 154, 0.6);
        }
        
        .btn-secondary-modern {
            background-color: rgba(255, 255, 255, 0.2);
            color: white;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .btn-secondary-modern:hover {
            background-color: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.2);
        }

        .table-modern {
            background: transparent;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: var(--shadow-soft);
        }

        .table-modern thead th {
            background: rgba(0, 0, 0, 0.3);
            color: white;
            font-weight: 700;
            border: none;
            padding: 1rem;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .table-modern tbody td {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 1rem;
            font-weight: 500;
            vertical-align: middle;
        }

        .table-modern tbody tr:hover td {
            background: rgba(255, 255, 255, 0.15);
        }

        .badge-urutan {
            background: var(--primary-gradient);
            color: white;
            min-width: 40px;
            font-size: 1rem;
            padding: 0.6rem;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .alert-modern {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 15px;
            color: white;
            padding: 1rem 1.5rem;
            margin-bottom: 2rem;
            animation: slideInDown 0.5s ease-out;
            display: flex;
            align-items: center;
        }
        .alert-modern.alert-success {
            border-left: 5px solid #4facfe;
        }
        .alert-modern.alert-danger {
            border-left: 5px solid #fa709a;
        }

        .modal-content-modern {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            color: white;
        }

        .modal-header-modern {
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            padding: 1.5rem;
        }

        .modal-title-modern {
            font-weight: 700;
            font-size: 1.3rem;
        }

        .btn-close-modern {
            filter: invert(1);
            opacity: 0.8;
        }

        .btn-close-modern:hover {
            opacity: 1;
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .btn-sm-modern {
            padding: 0.5rem 0.75rem;
            font-size: 0.85rem;
            border-radius: 8px;
            min-width: 40px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        
        @keyframes slideInDown {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        @keyframes slideInUp {
            from { transform: translateY(50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .slide-in-up { animation: slideInUp 0.8s ease-out; }

        @media (max-width: 768px) {
            .page-title { font-size: 2rem; }
            .container-fluid { padding: 1rem; }
            .action-buttons { flex-direction: column; gap: 0.25rem; }
            .btn-sm-modern { width: 100%; }
        }

        /* Custom scrollbar */
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: rgba(255, 255, 255, 0.1); }
        ::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.3); border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: rgba(255, 255, 255, 0.5); }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-industry"></i>
                Kelola Alur Produksi
            </h1>
            <p class="page-subtitle">Manajemen alur produksi yang efisien dan terorganisir</p>
        </div>

        <?php if (isset($_GET['status'])): ?>
        <div class="alert alert-<?php echo $_GET['status'] == 'success' ? 'success' : 'danger'; ?> alert-modern alert-dismissible fade show" role="alert">
            <i class="fas <?php echo $_GET['status'] == 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?> me-2"></i>
            <?php echo htmlspecialchars(urldecode($_GET['message'])); ?>
            <button type="button" class="btn-close btn-close-modern" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <div class="row g-4">
            <div class="col-lg-4">
                <div class="glass-card slide-in-up" style="animation-delay: 0.2s;">
                    <div class="card-header-modern">
                        <h6 class="card-title-modern">
                            <i class="fas fa-plus-circle"></i> Tambah Alur Baru
                        </h6>
                    </div>
                    <div class="card-body-modern">
                        <form action="proses_alur_mesin.php" method="POST">
                            <div class="mb-3">
                                <label for="nama_alur" class="form-label-modern">
                                    <i class="fas fa-tag me-1"></i> Nama Alur
                                </label>
                                <input type="text" class="form-control form-control-modern" id="nama_alur" name="nama_alur" placeholder="Masukkan nama alur..." required>
                            </div>
                            <div class="mb-4">
                                <label for="urutan" class="form-label-modern">
                                    <i class="fas fa-sort-numeric-up me-1"></i> Nomor Urut
                                </label>
                                <input type="number" class="form-control form-control-modern" id="urutan" name="urutan" min="1" placeholder="Nomor urutan..." required>
                            </div>
                            <button type="submit" name="tambah_alur" class="btn btn-success-modern btn-modern w-100">
                                <i class="fas fa-plus me-2"></i> Tambah Alur
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="glass-card slide-in-up" style="animation-delay: 0.4s;">
                    <div class="card-header-modern">
                        <h6 class="card-title-modern">
                            <i class="fas fa-list-ol"></i> Daftar Alur Produksi
                        </h6>
                    </div>
                    <div class="card-body-modern">
                        <div class="table-responsive">
                            <table class="table table-modern" id="alurTable">
                                <thead>
                                    <tr>
                                        <th class="text-center">No. Urut</th>
                                        <th>Nama Alur</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($alurs)): ?>
                                        <tr>
                                            <td colspan="3" class="text-center">Belum ada alur yang ditambahkan.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($alurs as $alur): ?>
                                        <tr>
                                            <td class="text-center">
                                                <span class="badge-urutan"><?php echo htmlspecialchars($alur['urutan']); ?></span>
                                            </td>
                                            <td><strong><?php echo htmlspecialchars($alur['nama_alur']); ?></strong></td>
                                            <td>
                                                <div class="action-buttons">
                                                    <button type="button" class="btn btn-warning-modern btn-sm-modern" data-bs-toggle="modal" data-bs-target="#editAlurModal"
                                                            data-id="<?php echo $alur['id_alur']; ?>"
                                                            data-nama="<?php echo htmlspecialchars($alur['nama_alur']); ?>"
                                                            data-urutan="<?php echo htmlspecialchars($alur['urutan']); ?>">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    
                                                    <button type="button" class="btn btn-danger-modern btn-sm-modern" data-bs-toggle="modal" data-bs-target="#hapusAlurModal"
                                                            data-id="<?php echo $alur['id_alur']; ?>"
                                                            data-nama="<?php echo htmlspecialchars($alur['nama_alur']); ?>">
                                                        <i class="fas fa-trash"></i>
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
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editAlurModal" tabindex="-1" aria-labelledby="editAlurModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content modal-content-modern">
                <form action="proses_alur_mesin.php" method="POST">
                    <div class="modal-header modal-header-modern">
                        <h5 class="modal-title modal-title-modern" id="editAlurModalLabel">
                            <i class="fas fa-edit me-2"></i> Edit Alur
                        </h5>
                        <button type="button" class="btn-close btn-close-modern" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="id_alur" id="edit_id_alur">
                        <div class="mb-3">
                            <label for="edit_nama_alur" class="form-label-modern">
                                <i class="fas fa-tag me-1"></i> Nama Alur
                            </label>
                            <input type="text" class="form-control form-control-modern" id="edit_nama_alur" name="nama_alur" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_urutan" class="form-label-modern">
                                <i class="fas fa-sort-numeric-up me-1"></i> Nomor Urut
                            </label>
                            <input type="number" class="form-control form-control-modern" id="edit_urutan" name="urutan" min="1" required>
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-secondary-modern btn-modern" data-bs-dismiss="modal">
                             Tutup
                        </button>
                        <button type="submit" name="update_alur" class="btn btn-success-modern btn-modern">
                            <i class="fas fa-save me-1"></i> Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="hapusAlurModal" tabindex="-1" aria-labelledby="hapusAlurModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content modal-content-modern">
                 <form action="proses_alur_mesin.php" method="POST">
                    <div class="modal-header modal-header-modern" style="background: var(--danger-gradient);">
                        <h5 class="modal-title modal-title-modern" id="hapusAlurModalLabel">
                            <i class="fas fa-exclamation-triangle me-2"></i> Konfirmasi Hapus Alur
                        </h5>
                        <button type="button" class="btn-close btn-close-modern" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body text-center py-4">
                        <input type="hidden" name="id_alur" id="hapus_id_alur">
                        <i class="fas fa-trash-alt fa-3x mb-3" style="color: #fa709a;"></i>
                        <h5 class="mb-3">Apakah Anda benar-benar yakin?</h5>
                        <p class="text-white-50">
                            Anda akan menghapus alur produksi:
                        </p>
                        <div class="alert alert-danger" style="background-color: rgba(250, 112, 154, 0.2); border: none; color: white;">
                           <strong id="hapus_nama_alur"></strong>
                        </div>
                        <p class="fw-bold" style="color: #fee140;">
                            <i class="fas fa-info-circle me-1"></i>
                            Tindakan ini tidak dapat dibatalkan!
                        </p>
                    </div>
                    <div class="modal-footer border-0 justify-content-center">
                        <button type="button" class="btn btn-secondary-modern btn-modern" data-bs-dismiss="modal">
                             Batal
                        </button>
                        <button type="submit" name="hapus_alur" class="btn btn-danger-modern btn-modern">
                            <i class="fas fa-trash me-1"></i> Ya, Hapus
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>


    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var editAlurModal = document.getElementById('editAlurModal');
        var hapusAlurModal = document.getElementById('hapusAlurModal');

        // Fungsionalitas untuk mengisi modal edit
        editAlurModal.addEventListener('show.bs.modal', function(event) {
            var button = event.relatedTarget;
            var id = button.getAttribute('data-id');
            var nama = button.getAttribute('data-nama');
            var urutan = button.getAttribute('data-urutan');
            
            var modalTitle = editAlurModal.querySelector('.modal-title');
            var idInput = editAlurModal.querySelector('#edit_id_alur');
            var namaInput = editAlurModal.querySelector('#edit_nama_alur');
            var urutanInput = editAlurModal.querySelector('#edit_urutan');
            
            modalTitle.innerHTML = `<i class="fas fa-edit me-2"></i>Edit Alur: ${nama}`;
            idInput.value = id;
            namaInput.value = nama;
            urutanInput.value = urutan;
        });

        // Fungsionalitas untuk mengisi modal hapus
        hapusAlurModal.addEventListener('show.bs.modal', function(event) {
            var button = event.relatedTarget;
            var id = button.getAttribute('data-id');
            var nama = button.getAttribute('data-nama');

            var idInput = hapusAlurModal.querySelector('#hapus_id_alur');
            var namaDisplay = hapusAlurModal.querySelector('#hapus_nama_alur');

            idInput.value = id;
            namaDisplay.textContent = nama;
        });

        // Script Animasi Tambahan
        const tableRows = document.querySelectorAll('#alurTable tbody tr');
        tableRows.forEach((row, index) => {
            row.style.opacity = '0';
            row.style.transform = 'translateY(20px)';
            setTimeout(() => {
                row.style.transition = 'all 0.5s ease';
                row.style.opacity = '1';
                row.style.transform = 'translateY(0)';
            }, 600 + (index * 100));
        });
    });
    </script>
    
    </body>
</html>

<?php include '../../../templates/footer.php'; ?>