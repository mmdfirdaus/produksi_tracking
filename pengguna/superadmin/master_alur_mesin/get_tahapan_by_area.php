<?php
// Pastikan file ini memiliki akses ke koneksi database
include '../../../system/database_connection.php';

if (isset($_POST["id_area"]) && !empty($_POST["id_area"])) {
    try {
        // Query untuk mengambil tahapan berdasarkan id_area yang dikirim
        $stmt = $pdo->prepare("SELECT id_alur, nama_alur FROM master_alur WHERE id_area = :id_area ORDER BY nama_alur ASC");
        $stmt->bindParam(':id_area', $_POST['id_area'], PDO::PARAM_INT);
        $stmt->execute();
        $tahapans = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($tahapans) > 0) {
            echo '<option value="">-- Pilih Tahapan --</option>';
            foreach ($tahapans as $tahapan) {
                echo '<option value="' . $tahapan['id_alur'] . '">' . htmlspecialchars($tahapan['nama_alur']) . '</option>';
            }
        } else {
            echo '<option value="">-- Tidak ada tahapan di area ini --</option>';
        }
    } catch (PDOException $e) {
        // Sebaiknya jangan tampilkan error detail di produksi
        echo '<option value="">-- Gagal memuat data --</option>';
    }
} else {
    echo '<option value="">-- Pilih Area Terlebih Dahulu --</option>';
}
?>