<?php
session_start();
include '../../../system/database_connection.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'superadmin') {
    header("location: ../../auth/login.php");
    exit;
}

// Proses Tambah Manual
if (isset($_POST['tambah_manual'])) {
    $id_target = $_POST['id_target'];
    $id_alur = $_POST['id_alur'];
    $id_komponen = $_POST['id_komponen'];
    $jumlah_per_unit = $_POST['jumlah_per_unit'];

    $redirect_url = "material.php?id_target=$id_target&id_alur=$id_alur";

    try {
        // Cek dulu apakah komponen sudah ada di target & alur ini
        $stmt_cek = $pdo->prepare("SELECT COUNT(*) FROM target_material WHERE id_target = ? AND id_alur = ? AND id_komponen = ?");
        $stmt_cek->execute([$id_target, $id_alur, $id_komponen]);
        if ($stmt_cek->fetchColumn() > 0) {
            throw new Exception("Komponen tersebut sudah ada di dalam alur ini.");
        }

        // Ambil kode gudang dari master komponen
        $stmt_komp = $pdo->prepare("SELECT kode_komponen FROM master_komponen WHERE id_komponen = ?");
        $stmt_komp->execute([$id_komponen]);
        $kode_gudang = $stmt_komp->fetchColumn();

        $stmt = $pdo->prepare("INSERT INTO target_material (id_target, id_alur, id_komponen, kode_gudang, jumlah_per_unit) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$id_target, $id_alur, $id_komponen, $kode_gudang, $jumlah_per_unit]);

        header("location: $redirect_url&status=success&message=" . urlencode("Komponen berhasil ditambahkan."));
    } catch (Exception $e) {
        header("location: $redirect_url&status=error&message=" . urlencode($e->getMessage()));
    }
    exit;
}

// --- LOGIKA HAPUS MATERIAL ---
if (isset($_GET['hapus_material'])) {
    $id_material = (int)$_GET['hapus_material'];
    $id_target = (int)$_GET['id_target'];
    $id_alur = (int)$_GET['id_alur'];
    
    $redirect_url = "material.php?id_target=$id_target&id_alur=$id_alur";

    try {
        // Hapus juga laporan harian terkait agar data tetap konsisten
        $stmt_delete_laporan = $pdo->prepare("DELETE FROM laporan_harian WHERE id_material = ?");
        $stmt_delete_laporan->execute([$id_material]);

        // Hapus material dari target
        $stmt_delete_material = $pdo->prepare("DELETE FROM target_material WHERE id_material = ?");
        $stmt_delete_material->execute([$id_material]);

        header("location: $redirect_url&status=success&message=" . urlencode("Komponen berhasil dihapus dari target ini."));
    } catch (PDOException $e) {
        header("location: $redirect_url&status=error&message=" . urlencode("Gagal menghapus komponen: " . $e->getMessage()));
    }
    exit;
}


// Jika tidak ada aksi yang cocok
header("Location: ../master_data/kelola_master_barang.php");
exit;
?>