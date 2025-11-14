<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo "Akses ditolak.";
    exit;
}

include_once '../../../system/database_connection.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_target = $_POST['id_target'];
    $id_alur = $_POST['id_alur'];
    $id_user_admin = $_SESSION['user_id']; // Mengambil ID admin dari session

    // ================================================================
    // BAGIAN BARU: SISI VALIDASI HAK AKSES SERVER
    // ================================================================
    // Cek ke database apakah admin ini punya hak akses ke alur produksi yang dituju.
    // ================= SISI VALIDASI HAK AKSES SERVER (VERSI BENAR) =================
$stmt_check = $pdo->prepare("
    SELECT COUNT(*) 
    FROM admin_tahapan_access 
    WHERE id_user = :id_user AND id_tahapan = :id_alur
");
$stmt_check->execute([
    'id_user' => $id_user_admin, 
    'id_alur' => $id_alur
]);
$is_allowed = $stmt_check->fetchColumn();

if ($is_allowed == 0) {
    // Jika tidak diizinkan, hentikan proses
    $_SESSION['error_message'] = "GAGAL! Anda tidak memiliki hak untuk menyimpan data pada alur produksi ini.";
    header("Location: input_harian.php?id_target=$id_target&id_alur=$id_alur");
    exit();
}
// ================= AKHIR DARI VALIDASI =================
    // ================================================================
    // AKHIR DARI VALIDASI
    // ================================================================

    // Jika validasi berhasil, kode di bawah ini akan dijalankan.
    $tanggal_laporan = $_POST['tanggal_laporan'];
    $jumlah_selesai_array = $_POST['jumlah_selesai'];

    // Mulai transaksi
    $pdo->beginTransaction();

    try {
        $query = "INSERT INTO laporan_harian (id_material, jumlah_selesai, tanggal_laporan) VALUES (?, ?, ?)";
        $stmt = $pdo->prepare($query);

        $data_diinput = false;
        foreach ($jumlah_selesai_array as $id_material => $jumlah) {
            // Hanya proses jika jumlah yang diinput valid (angka dan lebih dari 0)
            if (is_numeric($jumlah) && $jumlah > 0) {
                $stmt->execute([$id_material, $jumlah, $tanggal_laporan]);
                $data_diinput = true;
            }
        }

        // Commit transaksi
        $pdo->commit();

        if ($data_diinput) {
            $_SESSION['success_message'] = "Progres harian berhasil disimpan.";
        } else {
            $_SESSION['success_message'] = "Tidak ada data baru yang diinput.";
        }

    } catch (PDOException $e) {
        // Rollback jika terjadi error
        $pdo->rollBack();
        $_SESSION['error_message'] = "Gagal menyimpan progres: " . $e->getMessage();
    }

    // Redirect kembali ke halaman input
    header("Location: input_harian.php?id_target=$id_target&id_alur=$id_alur");
    exit;
} else {
    // Jika bukan metode POST, redirect ke dashboard
    header("Location: ../dashboard.php");
    exit;
}
?>