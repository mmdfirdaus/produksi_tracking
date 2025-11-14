<?php
// kelola_kategori.php

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'superadmin') {
    header("Location: ../../auth/login.php");
    exit;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/produksi_tracking/system/database_connection.php';
$page_title = "Kelola Kategori Barang";
include_once $_SERVER['DOCUMENT_ROOT'] . '/produksi_tracking/templates/header_superadmin.php';

// Ambil semua data kategori
$query = "SELECT * FROM master_kategori ORDER BY nama_kategori ASC";
$result = mysqli_query($conn, $query);
?>

<style>
/* Modern UI Styles */
.modern-container {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
    padding: 2rem 0;
}

.content-wrapper {
    background: #f8fafc;
    border-radius: 20px;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    overflow: hidden;
}

.modern-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 2rem;
    text-align: center;
}

.modern-header h1 {
    font-weight: 700;
    font-size: 2.5rem;
    margin-bottom: 0.5rem;
    text-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.modern-header p {
    opacity: 0.9;
    font-size: 1.1rem;
}

.main-content {
    padding: 2rem;
}

.modern-alert {
    border: none;
    border-radius: 12px;
    padding: 1rem 1.5rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

.modern-alert.alert-success {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
}

.modern-alert.alert-danger {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: white;
}

.modern-card {
    background: white;
    border-radius: 16px;
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    border: none;
    overflow: hidden;
}

.modern-card-header {
    background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
    color: white;
    padding: 1.5rem 2rem;
    border: none;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modern-card-header h6 {
    font-size: 1.25rem;
    font-weight: 600;
    margin: 0;
}

.modern-btn {
    border-radius: 10px;
    font-weight: 600;
    padding: 0.75rem 1.5rem;
    border: none;
    transition: all 0.3s ease;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-size: 0.875rem;
}

.modern-btn-primary {
    background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
    color: white;
    box-shadow: 0 4px 6px -1px rgba(59, 130, 246, 0.3);
}

.modern-btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 15px -3px rgba(59, 130, 246, 0.4);
}

.modern-btn-warning {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    color: white;
    box-shadow: 0 4px 6px -1px rgba(245, 158, 11, 0.3);
}

.modern-btn-warning:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 15px -3px rgba(245, 158, 11, 0.4);
}

.modern-btn-danger {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: white;
    box-shadow: 0 4px 6px -1px rgba(239, 68, 68, 0.3);
}

.modern-btn-danger:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 15px -3px rgba(239, 68, 68, 0.4);
}

.modern-btn-secondary {
    background: #6b7280;
    color: white;
}

.modern-btn-secondary:hover {
    background: #4b5563;
    transform: translateY(-2px);
}

.modern-table {
    margin: 0;
    border: none;
}

.modern-table thead th {
    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
    color: #475569;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-size: 0.875rem;
    padding: 1rem;
    border: none;
}

.modern-table tbody tr {
    border: none;
    transition: all 0.2s ease;
}

.modern-table tbody tr:hover {
    background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 50%);
    transform: scale(1.01);
}

.modern-table tbody td {
    padding: 1rem;
    border: none;
    vertical-align: middle;
}

.modern-table tbody tr:not(:last-child) {
    border-bottom: 1px solid #e2e8f0;
}

.modern-modal .modal-content {
    border: none;
    border-radius: 16px;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    overflow: hidden;
}

.modern-modal .modal-header {
    background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
    color: white;
    border: none;
    padding: 1.5rem 2rem;
}

.modern-modal .modal-title {
    font-weight: 600;
    font-size: 1.25rem;
}

.modern-modal .modal-body {
    padding: 2rem;
}

.modern-modal .modal-footer {
    background: #f8fafc;
    border: none;
    padding: 1.5rem 2rem;
}

.modern-form-control {
    border: 2px solid #e2e8f0;
    border-radius: 10px;
    padding: 0.75rem 1rem;
    transition: all 0.3s ease;
    background: white;
}

.modern-form-control:focus {
    border-color: #4f46e5;
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
    outline: none;
}

.modern-form-label {
    font-weight: 600;
    color: #374151;
    margin-bottom: 0.5rem;
}

.action-buttons {
    display: flex;
    gap: 0.5rem;
}

@media (max-width: 768px) {
    .modern-header h1 {
        font-size: 2rem;
    }
    
    .main-content {
        padding: 1rem;
    }
    
    .modern-card-header {
        flex-direction: column;
        gap: 1rem;
        text-align: center;
    }
    
    .action-buttons {
        flex-direction: column;
    }
}
</style>

<div class="modern-container">
    <div class="container">
        <div class="content-wrapper">
            <div class="modern-header">
                <h1><i class="fas fa-tags"></i> Kelola Kategori Barang</h1>
                <p>Kelola data master kategori barang dengan mudah dan efisien</p>
            </div>

            <div class="main-content">
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert modern-alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo $_SESSION['success_message']; ?>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert modern-alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?php echo $_SESSION['error_message']; ?>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['error_message']); ?>
                <?php endif; ?>

                <div class="modern-card">
                    <div class="modern-card-header">
                        <h6><i class="fas fa-list me-2"></i>Daftar Kategori</h6>
                        <button class="modern-btn modern-btn-primary" data-bs-toggle="modal" data-bs-target="#tambahKategoriModal">
                            <i class="fas fa-plus me-2"></i> Tambah Kategori
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table modern-table" id="dataTable" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th width="10%"><i class="fas fa-hashtag me-1"></i>No</th>
                                        <th><i class="fas fa-tag me-1"></i>Nama Kategori</th>
                                        <th width="20%"><i class="fas fa-cogs me-1"></i>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (mysqli_num_rows($result) > 0): ?>
                                        <?php $no = 1; while ($row = mysqli_fetch_assoc($result)): ?>
                                            <tr>
                                                <td><span class="badge bg-primary rounded-pill"><?php echo $no++; ?></span></td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($row['nama_kategori']); ?></strong>
                                                </td>
                                                <td>
                                                    <div class="action-buttons">
                                                        <button class="modern-btn modern-btn-warning btn-sm btn-edit" 
                                                                data-id="<?php echo $row['id_kategori']; ?>" 
                                                                data-nama="<?php echo htmlspecialchars($row['nama_kategori']); ?>">
                                                            <i class="fas fa-edit me-1"></i> Ubah
                                                        </button>
                                                        <a href="proses_kategori.php?action=delete&id=<?php echo $row['id_kategori']; ?>"
                                                           class="modern-btn modern-btn-danger btn-sm btn-hapus"
                                                           data-nama-kategori="<?php echo htmlspecialchars($row['nama_kategori']); ?>">
                                                            <i class="fas fa-trash me-1"></i> Hapus
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="3" class="text-center py-5">
                                                <i class="fas fa-inbox fa-3x text-muted mb-3 d-block"></i>
                                                <h5 class="text-muted">Belum ada data kategori</h5>
                                                <p class="text-muted">Silakan tambah kategori baru untuk memulai</p>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade modern-modal" id="tambahKategoriModal" tabindex="-1" aria-labelledby="tambahKategoriModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <form action="proses_kategori.php" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="tambahKategoriModalLabel">
                        <i class="fas fa-plus-circle me-2"></i>Tambah Kategori Baru
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="nama_kategori" class="form-label modern-form-label">
                            <i class="fas fa-tag me-2"></i>Nama Kategori
                        </label>
                        <input type="text" class="form-control modern-form-control" id="nama_kategori" name="nama_kategori" required placeholder="Masukkan nama kategori...">
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="modern-btn modern-btn-secondary" type="button" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i> Batal
                    </button>
                    <button class="modern-btn modern-btn-primary" type="submit" name="tambah_kategori">
                        <i class="fas fa-save me-1"></i> Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade modern-modal" id="ubahKategoriModal" tabindex="-1" aria-labelledby="ubahKategoriModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <form action="proses_kategori.php" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="ubahKategoriModalLabel">
                        <i class="fas fa-edit me-2"></i>Ubah Kategori
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id_kategori" id="edit_id_kategori">
                    <div class="mb-3">
                        <label for="edit_nama_kategori" class="form-label modern-form-label">
                            <i class="fas fa-tag me-2"></i>Nama Kategori
                        </label>
                        <input type="text" class="form-control modern-form-control" id="edit_nama_kategori" name="nama_kategori" required placeholder="Masukkan nama kategori...">
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="modern-btn modern-btn-secondary" type="button" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i> Batal
                    </button>
                    <button class="modern-btn modern-btn-primary" type="submit" name="ubah_kategori">
                        <i class="fas fa-save me-1"></i> Update
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include_once $_SERVER['DOCUMENT_ROOT'] . '/produksi_tracking/templates/footer.php'; ?>

<script>
$(document).ready(function() {
    // Script untuk modal "Ubah Kategori" (sudah ada)
    $('.btn-edit').on('click', function() {
        var id = $(this).data('id');
        var nama = $(this).data('nama');
        $('#edit_id_kategori').val(id);
        $('#edit_nama_kategori').val(nama);
        var ubahModal = new bootstrap.Modal(document.getElementById('ubahKategoriModal'));
        ubahModal.show();
    });

    // === PENAMBAHAN BARU: Script untuk konfirmasi hapus dengan SweetAlert2 ===
    $('.btn-hapus').on('click', function(e) {
        // Mencegah link langsung dieksekusi
        e.preventDefault();

        // Mengambil data dari link yang diklik
        var deleteUrl = $(this).attr('href');
        var namaKategori = $(this).data('nama-kategori');

        Swal.fire({
            title: 'Anda Yakin?',
            html: `Anda akan menghapus kategori: <br><strong>${namaKategori}</strong>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal',
            confirmButtonColor: '#dc3545', // Warna merah untuk tombol konfirmasi hapus
            cancelButtonColor: '#6c757d',
            background: '#fff',
            customClass: {
                title: 'text-dark',
                htmlContainer: 'text-dark'
            }
        }).then((result) => {
            // Jika pengguna menekan tombol "Ya, Hapus!"
            if (result.isConfirmed) {
                // Arahkan ke URL penghapusan
                window.location.href = deleteUrl;
            }
        });
    });
});
</script>