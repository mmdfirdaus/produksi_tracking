<?php
$page_title = 'Kelola Area Produksi';
include_once '../../../templates/header_superadmin.php';
include_once '../../../system/database_connection.php';

// Fetch all areas
$areas = $pdo->query("SELECT * FROM master_area ORDER BY nama_area ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid">
    <h3 class="my-4"><i class="fas fa-map-signs me-2"></i> Kelola Area Produksi</h3>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success"><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger"><?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?></div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0" id="form-title"><i class="fas fa-plus-circle me-2"></i> Tambah Area Baru</h5>
                </div>
                <div class="card-body">
                    <form action="proses_area.php" method="POST">
                        <input type="hidden" name="id_area" id="id_area">
                        <div class="mb-3">
                            <label for="nama_area" class="form-label">Nama Area</label>
                            <input type="text" class="form-control" id="nama_area" name="nama_area" required>
                        </div>
                        <div class="d-grid gap-2">
                            <button type="submit" name="tambah" class="btn btn-primary" id="btn-submit">
                                <i class="fas fa-save me-2"></i> Simpan
                            </button>
                            <button type="button" class="btn btn-secondary" id="btn-batal" style="display: none;" onclick="resetForm()">
                                Batal Edit
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-list-ul me-2"></i> Daftar Area</h5>
                </div>
                <div class="card-body">
                    <table class="table table-bordered table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>No</th>
                                <th>Nama Area</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($areas)): ?>
                                <tr><td colspan="3" class="text-center">Belum ada data area.</td></tr>
                            <?php else: ?>
                                <?php $no = 1; foreach ($areas as $area): ?>
                                    <tr>
                                        <td><?php echo $no++; ?></td>
                                        <td><?php echo htmlspecialchars($area['nama_area']); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-warning" onclick="editArea(<?php echo $area['id_area']; ?>, '<?php echo htmlspecialchars(addslashes($area['nama_area'])); ?>')">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <a href="proses_area.php?hapus=<?php echo $area['id_area']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Apakah Anda yakin ingin menghapus area ini?');">
                                                <i class="fas fa-trash"></i>
                                            </a>
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

<script>
function editArea(id, nama) {
    document.getElementById('form-title').innerHTML = '<i class="fas fa-edit me-2"></i> Edit Area';
    document.getElementById('id_area').value = id;
    document.getElementById('nama_area').value = nama;
    document.getElementById('btn-submit').name = 'edit';
    document.getElementById('btn-submit').innerHTML = '<i class="fas fa-save me-2"></i> Simpan Perubahan';
    document.getElementById('btn-batal').style.display = 'block';
    window.scrollTo(0, 0); // Scroll ke atas
}

function resetForm() {
    document.getElementById('form-title').innerHTML = '<i class="fas fa-plus-circle me-2"></i> Tambah Area Baru';
    document.getElementById('id_area').value = '';
    document.getElementById('nama_area').value = '';
    document.getElementById('btn-submit').name = 'tambah';
    document.getElementById('btn-submit').innerHTML = '<i class="fas fa-save me-2"></i> Simpan';
    document.getElementById('btn-batal').style.display = 'none';
}
</script>

<?php include_once '../../../templates/footer.php'; ?>