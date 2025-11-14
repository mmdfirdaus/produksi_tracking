<?php
include_once '../../../templates/header_user.php';
include_once '../../../system/database_connection.php';

// Query untuk mengambil semua data dari master_alur, diurutkan berdasarkan urutan
try {
    $stmt = $pdo->query("SELECT id_alur, nama_alur, urutan FROM master_alur ORDER BY urutan ASC");
    $alur_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error: Tidak bisa mengambil data alur produksi. " . $e->getMessage());
}
?>

<div class="container mt-4">
    <div class="card">
        <div class="card-header">
            <h3 class="mb-0">Daftar Alur Produksi</h3>
        </div>
        <div class="card-body">
            <p>Berikut adalah semua tahapan proses produksi yang terdaftar dalam sistem.</p>
            
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover">
                    <thead class="thead-dark">
                        <tr>
                            <th scope="col">No</th>
                            <th scope="col">Nama Alur/Tahapan</th>
                            <th scope="col">Urutan Prioritas</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($alur_list)): ?>
                            <tr>
                                <td colspan="3" class="text-center">Belum ada data alur produksi.</td>
                            </tr>
                        <?php else: ?>
                            <?php $no = 1; foreach ($alur_list as $alur): ?>
                                <tr>
                                    <td><?php echo $no++; ?></td>
                                    <td><?php echo htmlspecialchars($alur['nama_alur']); ?></td>
                                    <td><?php echo htmlspecialchars($alur['urutan']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include_once '../../../templates/footer.php'; ?>