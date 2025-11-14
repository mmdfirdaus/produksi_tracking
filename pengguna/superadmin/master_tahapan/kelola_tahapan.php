<?php
$page_title = 'Kelola Tahapan Produksi';
include '../../../templates/header_superadmin.php';
include '../../../system/database_connection.php';

// Ambil semua data alur dan mesin
try {
    $alurs_stmt = $pdo->query("SELECT * FROM master_alur ORDER BY urutan ASC");
    $alurs = $alurs_stmt->fetchAll(PDO::FETCH_ASSOC);

    $mesins_stmt = $pdo->query("SELECT * FROM master_mesin");
    $mesins_by_alur = [];
    foreach ($mesins_stmt->fetchAll(PDO::FETCH_ASSOC) as $mesin) {
        $mesins_by_alur[$mesin['id_alur']][] = $mesin;
    }
} catch (PDOException $e) {
    die("Error fetching data: " . $e->getMessage());
}
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3><i class="bi bi-diagram-3"></i> <?php echo $page_title; ?></h3>
        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#tambahAlurModal">
            <i class="bi bi-plus-lg"></i> Tambah Alur/Tahapan
        </button>
    </div>

    <div class="row">
        <?php foreach ($alurs as $alur): ?>
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header d-flex justify-content-between">
                    <strong><?php echo htmlspecialchars($alur['nama_alur']); ?> (Urutan: <?php echo $alur['urutan']; ?>)</strong>
                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#tambahMesinModal" data-id-alur="<?php echo $alur['id_alur']; ?>">
                        <i class="bi bi-plus"></i> Tambah Mesin
                    </button>
                </div>
                <div class="card-body">
                    <ul class="list-group">
                        <?php if (isset($mesins_by_alur[$alur['id_alur']])): ?>
                            <?php foreach ($mesins_by_alur[$alur['id_alur']] as $mesin): ?>
                                <li class="list-group-item"><?php echo htmlspecialchars($mesin['nama_mesin']); ?></li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li class="list-group-item text-muted">Belum ada mesin di alur ini.</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="modal fade" id="tambahAlurModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Tambah Alur/Tahapan Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="proses_tahapan.php" method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="nama_alur" class="form-label">Nama Alur</label>
                        <input type="text" class="form-control" name="nama_alur" required>
                    </div>
                    <div class="mb-3">
                        <label for="urutan" class="form-label">Nomor Urut</label>
                        <input type="number" class="form-control" name="urutan" required>
                        <div class="form-text">Urutan yang lebih kecil akan tampil lebih dulu.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="tambah_alur" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="tambahMesinModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Tambah Mesin Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="proses_tahapan.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="id_alur_for_mesin" id="id_alur_for_mesin">
                    <div class="mb-3">
                        <label for="nama_mesin" class="form-label">Nama Mesin</label>
                        <input type="text" class="form-control" name="nama_mesin" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="tambah_mesin" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Script untuk mengirim id_alur ke modal tambah mesin
document.addEventListener('DOMContentLoaded', function () {
    var tambahMesinModal = document.getElementById('tambahMesinModal');
    tambahMesinModal.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget;
        var idAlur = button.getAttribute('data-id-alur');
        var modalInput = tambahMesinModal.querySelector('#id_alur_for_mesin');
        modalInput.value = idAlur;
    });
});
</script>


<?php include '../../../templates/footer.php'; ?>