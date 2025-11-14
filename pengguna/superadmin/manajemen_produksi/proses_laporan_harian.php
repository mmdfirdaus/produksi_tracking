<?php
session_start();
include '../../../system/database_connection.php';

// Cek hak akses
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !in_array($_SESSION["role"], ['superadmin', 'admin'])) {
    header("location: ../../auth/login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['laporan'])) {
    $id_target = $_POST['id_target'];
    $id_alur = $_POST['id_alur'];
    $tanggal_laporan = $_POST['tanggal_laporan'];
    $laporan_data = $_POST['laporan'];
    
    $page_param = isset($_POST['page']) && (int)$_POST['page'] > 1 ? '&page='.(int)$_POST['page'] : '';
    $redirect_url = "material.php?id_target=$id_target&id_alur=$id_alur" . $page_param;

    // --- BLOK KUNCI: CEK STATUS PENGERJAAN DI SERVER ---
    // Mengambil status spesifik untuk ALUR INI, bukan status target utama
    $check_status_stmt = $pdo->prepare("
        SELECT COALESCE(status_pengerjaan, 'Pending') 
        FROM target_alur_status 
        WHERE id_target = :id_target AND id_alur = :id_alur
    ");
    $check_status_stmt->execute([':id_target' => $id_target, ':id_alur' => $id_alur]);
    $alur_status = $check_status_stmt->fetchColumn();

    // Jika datanya tidak ada di tabel target_alur_status, anggap 'Pending' (sesuai logika di material.php)
    if (!$alur_status) {
        $alur_status = 'Pending';
    }

    if ($alur_status === 'Pending') {
        // Jika status alur 'Pending', hentikan proses dan kembalikan dengan pesan error yang benar.
        $error_message = urlencode("Simpan Laporan Gagal! Status alur pengerjaan ini masih 'Pending'.");
        header("Location: {$redirect_url}&status=error&message={$error_message}");
        exit;
    }
    // --- AKHIR BLOK KUNCI ---
    // --- AKHIR BLOK KUNCI ---

    if (empty($tanggal_laporan)) {
        header("location: $redirect_url&status=error&message=Tanggal laporan wajib diisi.");
        exit;
    }

    try {
        $pdo->beginTransaction();
        
        $adaData = false;
        foreach ($laporan_data as $id_material => $jumlah_selesai) {
            if (!empty($jumlah_selesai) && is_numeric($jumlah_selesai) && $jumlah_selesai > 0) {
                
                // --- VALIDASI SERVER-SIDE ---
                $validasi_stmt = $pdo->prepare("
                    SELECT 
                        (pt.jumlah_unit * tm.jumlah_per_unit) AS total_kebutuhan,
                        COALESCE((SELECT SUM(jumlah_selesai) FROM laporan_harian WHERE id_material = tm.id_material), 0) AS total_selesai
                    FROM target_material tm
                    JOIN production_targets pt ON tm.id_target = pt.id_target
                    WHERE tm.id_material = :id_material
                ");
                $validasi_stmt->execute([':id_material' => (int)$id_material]);
                $data = $validasi_stmt->fetch(PDO::FETCH_ASSOC);

                if ($data) {
                    $sisa = $data['total_kebutuhan'] - $data['total_selesai'];
                    if ((int)$jumlah_selesai > $sisa) {
                        $pdo->rollBack();
                        $komponen_stmt = $pdo->prepare("SELECT mk.nama_komponen FROM target_material tm JOIN master_komponen mk ON tm.id_komponen = mk.id_komponen WHERE tm.id_material = ?");
                        $komponen_stmt->execute([(int)$id_material]);
                        $nama_komponen = $komponen_stmt->fetchColumn();

                        $message = "Input gagal untuk '$nama_komponen'. Jumlah yang dimasukkan (".(int)$jumlah_selesai.") melebihi sisa kebutuhan (".$sisa.").";
                        header("location: $redirect_url&status=error&message=" . urlencode($message));
                        exit;
                    }
                } else {
                    throw new Exception("Material dengan ID " . (int)$id_material . " tidak ditemukan.");
                }
                
                $adaData = true;
                $stmt = $pdo->prepare(
                    "INSERT INTO laporan_harian (id_material, jumlah_selesai, tanggal_laporan) VALUES (?, ?, ?)"
                );
                $stmt->execute([(int)$id_material, (int)$jumlah_selesai, $tanggal_laporan]);
            }
        }

        if (!$adaData) {
            header("location: $redirect_url&status=warning&message=Tidak ada data untuk disimpan.");
            exit;
        }

        $pdo->commit();
        header("location: $redirect_url&status=success&message=Laporan harian berhasil disimpan.");

    } catch (Exception $e) {
        $pdo->rollBack();
        header("location: $redirect_url&status=error&message=Gagal menyimpan data. Error: " . $e->getMessage());
    }
    exit;
}

header("location: ../master_barang.php");
exit;
?>