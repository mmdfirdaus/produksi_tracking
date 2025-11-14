<?php
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'superadmin') {
    header("location: ../../auth/login.php");
    exit;
}

include '../../../templates/header_superadmin.php';
include '../../../system/database_connection.php';

try {
    // Mengambil semua data area dari database
    $stmt = $pdo->query("SELECT * FROM master_area ORDER BY nama_area ASC");
    $areas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Area Produksi</title>
    
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <style>
        /* Menggunakan CSS modern yang sama persis dengan halaman sebelumnya */
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
        
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --success-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --danger-gradient: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            --warning-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --bg-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --glass-bg: rgba(255, 255, 255, 0.25);
            --glass-border: rgba(255, 255, 255, 0.18);
            --shadow-soft: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            --shadow-glow: 0 0 20px rgba(102, 126, 234, 0.4);
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
            top: 0; left: 0;
            width: 100%; height: 100%;
            background-image:
                radial-gradient(circle at 25% 25%, rgba(255, 255, 255, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 75% 75%, rgba(255, 255, 255, 0.1) 0%, transparent 50%);
            pointer-events: none;
            z-index: 0;
        }

        .container-fluid { position: relative; padding: 2rem; }
        .page-header { text-align: center; margin-bottom: 3rem; animation: slideInDown 0.8s ease-out; }
        .page-title { font-size: 3rem; font-weight: 800; background: linear-gradient(135deg, #ffffff 0%, rgba(255, 255, 255, 0.8) 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; margin-bottom: 0.5rem; text-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .page-subtitle { color: rgba(255, 255, 255, 0.8); font-size: 1.2rem; font-weight: 400; }
        .glass-card { background: var(--glass-bg); backdrop-filter: blur(20px); border: 1px solid var(--glass-border); border-radius: 20px; box-shadow: var(--shadow-soft); transition: all 0.3s ease; }
        .glass-card:hover { transform: translateY(-5px); box-shadow: var(--shadow-glow); }
        .form-control-modern { background: rgba(255, 255, 255, 0.1); border: 1px solid rgba(255, 255, 255, 0.2); border-radius: 12px; color: white; padding: 0.75rem 1rem; font-weight: 500; transition: all 0.3s ease; }
        .form-control-modern:focus { background: rgba(255, 255, 255, 0.15); border-color: rgba(255, 255, 255, 0.4); box-shadow: 0 0 0 0.2rem rgba(255, 255, 255, 0.15); color: white; }
        .form-control-modern::placeholder { color: rgba(255, 255, 255, 0.6); }
        .card-header-modern { background: transparent; border: none; padding: 1.5rem 1.5rem 0; }
        .card-title-modern { font-size: 1.25rem; font-weight: 700; color: white; display: flex; align-items: center; gap: 0.75rem; }
        .card-title-modern i { font-size: 1.5rem; background: var(--success-gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .card-body-modern { padding: 1.5rem; }
        .form-label-modern { color: rgba(255, 255, 255, 0.9); font-weight: 600; margin-bottom: 0.5rem; font-size: 0.95rem; }
        .btn-modern { font-weight: 600; padding: 0.75rem 1.5rem; border-radius: 12px; border: none; transition: all 0.3s ease; text-transform: uppercase; letter-spacing: 0.5px; font-size: 0.9rem; }
        .btn-success-modern { background: var(--success-gradient); color: white; box-shadow: 0 4px 15px rgba(79, 172, 254, 0.4); }
        .btn-warning-modern { background: var(--warning-gradient); color: white; box-shadow: 0 4px 15px rgba(240, 147, 251, 0.4); }
        .btn-danger-modern { background: var(--danger-gradient); color: white; box-shadow: 0 4px 15px rgba(250, 112, 154, 0.4); }
        .btn-secondary-modern { background-color: rgba(255, 255, 255, 0.2); color: white; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1); }
        .table-modern { background: transparent; }
        .table-modern thead th { background: rgba(0, 0, 0, 0.3); color: white; font-weight: 700; border: none; padding: 1rem; text-transform: uppercase; letter-spacing: 0.5px; }
        .table-modern tbody td { background: rgba(255, 255, 255, 0.1); color: white; border: 1px solid rgba(255, 255, 255, 0.1); padding: 1rem; font-weight: 500; vertical-align: middle; }
        .alert-modern { background: var(--glass-bg); backdrop-filter: blur(20px); border: 1px solid var(--glass-border); border-radius: 15px; color: white; padding: 1rem 1.5rem; margin-bottom: 2rem; animation: slideInDown 0.5s ease-out; }
        .modal-content-modern { background: var(--glass-bg); backdrop-filter: blur(20px); border: 1px solid var(--glass-border); border-radius: 20px; color: white; }
        .modal-header-modern { border-bottom: 1px solid rgba(255, 255, 255, 0.2); }
        .btn-close-modern { filter: invert(1); opacity: 0.8; }
        .action-buttons { display: flex; gap: 0.5rem; }
        .btn-sm-modern { padding: 0.5rem 0.75rem; font-size: 0.85rem; border-radius: 8px; }
        
        @keyframes slideInDown { from { transform: translateY(-50px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        @keyframes slideInUp { from { transform: translateY(50px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .slide-in-up { animation: slideInUp 0.8s ease-out; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="page-header">
            <h1 class="page-title"><i class="fas fa-map-signs"></i> Kelola Area Produksi</h1>
            <p class="page-subtitle">Manajemen daftar induk untuk area atau departemen produksi</p>
        </div>

        <?php if (isset($_GET['status'])): ?>
        <div class="alert alert-<?php echo $_GET['status'] == 'success' ? 'success' : 'danger'; ?> alert-modern alert-dismissible fade show" role="alert">
            <i class="fas <?php echo $_GET['status'] == 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?> me-2"></i>
            <?php echo htmlspecialchars(urldecode($_GET['message'])); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <div class="row g-4">
            <div class="col-lg-4">
                <div class="glass-card slide-in-up">
                    <div class="card-header-modern">
                        <h6 class="card-title-modern"><i class="fas fa-plus-circle"></i> Tambah Area Baru</h6>
                    </div>
                    <div class="card-body-modern">
                        <form action="proses_area.php" method="POST">
                            <div class="mb-3">
                                <label for="nama_area" class="form-label-modern"><i class="fas fa-tag me-1"></i> Nama Area</label>
                                <input type="text" class="form-control form-control-modern" id="nama_area" name="nama_area" placeholder="Contoh: Area Marking" required>
                            </div>
                            <button type="submit" name="tambah_area" class="btn btn-success-modern w-100">
                                <i class="fas fa-plus me-2"></i> Tambah Area
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="glass-card slide-in-up" style="animation-delay: 0.2s;">
                    <div class="card-header-modern">
                         <h6 class="card-title-modern"><i class="fas fa-list-ul"></i> Daftar Area Tersedia</h6>
                    </div>
                    <div class="card-body-modern">
                        <div class="table-responsive">
                            <table class="table table-modern">
                                <thead>
                                    <tr>
                                        <th>Nama Area</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($areas)): ?>
                                        <tr>
                                            <td colspan="2" class="text-center">Belum ada area yang ditambahkan.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($areas as $area): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($area['nama_area']); ?></strong></td>
                                            <td>
                                                <div class="action-buttons">
                                                    <button class="btn btn-warning-modern btn-sm-modern" data-bs-toggle="modal" data-bs-target="#editAreaModal" data-id="<?php echo $area['id_area']; ?>" data-nama="<?php echo htmlspecialchars($area['nama_area']); ?>"><i class="fas fa-edit"></i></button>
                                                    <button class="btn btn-danger-modern btn-sm-modern" data-bs-toggle="modal" data-bs-target="#hapusAreaModal" data-id="<?php echo $area['id_area']; ?>" data-nama="<?php echo htmlspecialchars($area['nama_area']); ?>"><i class="fas fa-trash"></i></button>
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

    <div class="modal fade" id="editAreaModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content modal-content-modern">
                <form action="proses_area.php" method="POST">
                    <div class="modal-header modal-header-modern">
                        <h5 class="modal-title"><i class="fas fa-edit me-2"></i> Edit Area</h5>
                        <button type="button" class="btn-close btn-close-modern" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="id_area" id="edit_id_area">
                        <div class="mb-3">
                            <label for="edit_nama_area" class="form-label-modern">Nama Area</label>
                            <input type="text" class="form-control form-control-modern" id="edit_nama_area" name="nama_area" required>
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-secondary-modern" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="update_area" class="btn btn-success-modern"><i class="fas fa-save me-1"></i> Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="hapusAreaModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content modal-content-modern">
                <form action="proses_area.php" method="POST">
                    <div class="modal-header modal-header-modern" style="background: var(--danger-gradient);">
                        <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i> Konfirmasi Hapus</h5>
                        <button type="button" class="btn-close btn-close-modern" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body text-center py-4">
                        <input type="hidden" name="id_area" id="hapus_id_area">
                        <p>Anda yakin ingin menghapus area: <br><strong><span id="hapus_nama_area"></span></strong>?</p>
                        <p class="text-warning"><small>Menghapus area akan menghapus tahapan terkait.</small></p>
                    </div>
                    <div class="modal-footer border-0 justify-content-center">
                        <button type="button" class="btn btn-secondary-modern" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="hapus_area" class="btn btn-danger-modern"><i class="fas fa-trash me-1"></i> Ya, Hapus</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Script untuk mengisi data ke Modal Edit
        const editAreaModal = document.getElementById('editAreaModal');
        editAreaModal.addEventListener('show.bs.modal', event => {
            const button = event.relatedTarget;
            const id = button.getAttribute('data-id');
            const nama = button.getAttribute('data-nama');
            
            editAreaModal.querySelector('#edit_id_area').value = id;
            editAreaModal.querySelector('#edit_nama_area').value = nama;
        });

        // Script untuk mengisi data ke Modal Hapus
        const hapusAreaModal = document.getElementById('hapusAreaModal');
        hapusAreaModal.addEventListener('show.bs.modal', event => {
            const button = event.relatedTarget;
            const id = button.getAttribute('data-id');
            const nama = button.getAttribute('data-nama');

            hapusAreaModal.querySelector('#hapus_id_area').value = id;
            hapusAreaModal.querySelector('#hapus_nama_area').textContent = nama;
        });
    </script>
</body>
</html>