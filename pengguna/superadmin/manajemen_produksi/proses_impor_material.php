<?php
session_start();
include '../../../system/database_connection.php';
require '../../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'superadmin') {
    header("location: ../../auth/login.php");
    exit;
}

if (isset($_POST['impor_material'])) {
    $id_target = $_POST['id_target'];
    $id_alur_dari_halaman = $_POST['id_alur'];
    $nama_sheet = $_POST['nama_sheet'];
    $file = $_FILES['excel_file'];

    $redirect_url = "material.php?id_target=$id_target&id_alur=$id_alur_dari_halaman";

    if ($file['error'] !== UPLOAD_ERR_OK) {
        header("location: $redirect_url&status=error&message=" . urlencode("Gagal mengunggah file."));
        exit;
    }

    $file_path = $file['tmp_name'];

    try {
        // --- PERBAIKAN DI SINI ---
        // 1. Ambil SEMUA alur dari database untuk dijadikan referensi
        $master_referensi = [];
        $stmt_master = $pdo->query("SELECT id_alur, TRIM(nama_alur) AS nama_alur FROM master_alur");
        
        while ($row = $stmt_master->fetch(PDO::FETCH_ASSOC)) {
            if (!empty($row['nama_alur'])) {
                $master_referensi[strtoupper($row['nama_alur'])] = $row['id_alur'];
            }
        }

        $spreadsheet = IOFactory::load($file_path);
        $worksheet = $spreadsheet->getSheetByName($nama_sheet);

        if (!$worksheet) {
            throw new Exception("Sheet '$nama_sheet' tidak ditemukan.");
        }

        $pdo->beginTransaction();

        $highestRow = $worksheet->getHighestRow();
        $id_alur_saat_ini = null;
        $komponen_baru_master = 0;
        $komponen_diaktifkan_kembali = 0;
        $komponen_baru_target = 0;
        
        // 2. Iterasi melalui setiap baris di Excel
        for ($row = 1; $row <= $highestRow; ++$row) {
            $cell_A_value = trim($worksheet->getCell('A' . $row)->getValue());
            $cell_B_value = $worksheet->getCell('B' . $row)->getValue();

            // Cek apakah baris ini adalah header alur yang valid
            $is_header_row = is_string($cell_A_value) && !is_numeric($cell_A_value) && $cell_B_value === null;

            if ($is_header_row && isset($master_referensi[strtoupper($cell_A_value)])) {
                // Jika ya, set alur saat ini dan lanjutkan ke baris berikutnya
                $id_alur_saat_ini = $master_referensi[strtoupper($cell_A_value)];
                continue;
            }

            // Jika kita sudah menemukan header yang valid dan baris ini adalah baris data
            $is_data_row = is_numeric($cell_A_value) && !empty($cell_B_value);
            if ($id_alur_saat_ini !== null && $is_data_row) {
                $nama_komponen = trim($worksheet->getCell('B' . $row)->getValue());
                $kode_gudang = trim($worksheet->getCell('C' . $row)->getValue()) ?: NULL;
                $jumlah_per_unit = (int)trim($worksheet->getCell('D' . $row)->getValue());

                if (empty($nama_komponen) || $jumlah_per_unit <= 0) continue;

                // Proses pengecekan/penambahan ke master_komponen
                $stmt_cek_master = $pdo->prepare("SELECT id_komponen, is_active FROM master_komponen WHERE nama_komponen = ?");
                $stmt_cek_master->execute([$nama_komponen]);
                $komponen = $stmt_cek_master->fetch();

                if ($komponen) {
                    $id_komponen = $komponen['id_komponen'];
                    if ($komponen['is_active'] == 0) {
                        $pdo->prepare("UPDATE master_komponen SET is_active = 1 WHERE id_komponen = ?")->execute([$id_komponen]);
                        $komponen_diaktifkan_kembali++;
                    }
                } else {
                    $stmt_ins_komponen = $pdo->prepare("INSERT INTO master_komponen (kode_komponen, nama_komponen) VALUES (?, ?)");
                    $stmt_ins_komponen->execute([$kode_gudang, $nama_komponen]);
                    $id_komponen = $pdo->lastInsertId();
                    $komponen_baru_master++;
                }

                // Masukkan komponen ke target_material dengan id_alur yang benar
                $stmt_cek_target = $pdo->prepare("SELECT COUNT(*) FROM target_material WHERE id_target = ? AND id_alur = ? AND id_komponen = ?");
                $stmt_cek_target->execute([$id_target, $id_alur_saat_ini, $id_komponen]);

                if ($stmt_cek_target->fetchColumn() == 0) {
                    $stmt_ins_material = $pdo->prepare(
                        "INSERT INTO target_material (id_target, id_alur, id_komponen, kode_gudang, jumlah_per_unit) VALUES (?, ?, ?, ?, ?)"
                    );
                    $stmt_ins_material->execute([$id_target, $id_alur_saat_ini, $id_komponen, $kode_gudang, $jumlah_per_unit]);
                    $komponen_baru_target++;
                }
            }
        }

        $pdo->commit();

        // Membuat pesan ringkasan
        $message = "$komponen_baru_target komponen berhasil ditambahkan ke dalam target. ";
        if ($komponen_baru_master > 0) $message .= "($komponen_baru_master komponen baru ditambahkan ke master). ";
        if ($komponen_diaktifkan_kembali > 0) $message .= "($komponen_diaktifkan_kembali komponen diaktifkan kembali).";
        
        header("location: $redirect_url&status=success&message=" . urlencode(trim($message)));

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        header("location: $redirect_url&status=error&message=" . urlencode("Error: " . $e->getMessage()));
    }
    exit;
}
?>