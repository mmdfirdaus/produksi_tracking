<?php
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'superadmin') {
    header("location: ../../auth/login.php");
    exit;
}

require '../../../vendor/autoload.php';
include '../../../system/database_connection.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// --- LOGIKA ADAPTIF BARU ---
$custom_template_path = '../../../templates/excel/template_impor_kustom.xlsx';

if (file_exists($custom_template_path)) {
    // Jika ada template kustom, langsung berikan file tersebut
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="Template Impor Kustom.xlsx"');
    header('Cache-Control: max-age=0');
    readfile($custom_template_path);
    exit;
}
// --- AKHIR LOGIKA ADAPTIF BARU ---


// --- JIKA TIDAK ADA TEMPLATE KUSTOM, LANJUTKAN MEMBUAT TEMPLATE STANDAR (KODE LAMA) ---
$id_target = isset($_GET['id_target']) ? (int)$_GET['id_target'] : 0;
if ($id_target === 0) {
    die("ID Target tidak valid.");
}

try {
    // Ambil id_barang dan nama_barang dari target
    $target_stmt = $pdo->prepare("SELECT pt.id_barang, mb.nama_barang FROM production_targets pt JOIN master_barang mb ON pt.id_barang = mb.id_barang WHERE pt.id_target = ?");
    $target_stmt->execute([$id_target]);
    $barang_info = $target_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$barang_info) {
        die("Target atau Barang tidak ditemukan.");
    }
    $id_barang = $barang_info['id_barang'];

    // Ambil alur yang terdaftar untuk barang ini
    $alur_stmt = $pdo->prepare("SELECT ma.nama_alur FROM alur_barang ab JOIN master_alur ma ON ab.id_alur = ma.id_alur WHERE ab.id_barang = ? ORDER BY ma.urutan ASC");
    $alur_stmt->execute([$id_barang]);
    $alurs = $alur_stmt->fetchAll(PDO::FETCH_COLUMN, 0);

    // Buat file Excel
    $spreadsheet = new Spreadsheet();
    
    // --- SHEET TEMPLATE IMPOR ---
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Template Impor');

    // Header kolom
    $header = ['NO', 'NAMA KOMPONEN', 'KODE GUDANG', 'JUMLAH PER UNIT'];
    $headerStyle = [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F81BD']]
    ];

    $row = 1;
    foreach ($alurs as $alur) {
        // Tulis nama alur sebagai header bagian
        $sheet->setCellValue('A' . $row, htmlspecialchars($alur));
        $sheet->mergeCells("A{$row}:D{$row}");
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle("A{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('DCE6F1');
        $row++;

        // Tulis header kolom
        $sheet->fromArray($header, NULL, 'A' . $row);
        $sheet->getStyle("A{$row}:D{$row}")->applyFromArray($headerStyle);
        $row += 5; // Beri 5 baris kosong untuk diisi pengguna
    }
    
    // Auto-size kolom
    foreach (range('A', 'D') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    // --- SHEET PETUNJUK (Menggunakan petunjuk lengkap dari kode lama) ---
    $spreadsheet->createSheet();
    $petunjukSheet = $spreadsheet->getSheet(1);
    $petunjukSheet->setTitle('Petunjuk Penggunaan');
    
    $petunjukSheet->setCellValue('A1', 'PETUNJUK PENGISIAN TEMPLATE IMPOR MATERIAL');
    $petunjukSheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);

    $petunjukSheet->setCellValue('A3', '1. Jangan mengubah atau menghapus Nama Alur yang sudah tersedia (contoh: Fabrikasi Awal, Perakitan, dll).');
    $petunjukSheet->setCellValue('A4', '2. Isi data komponen di bawah setiap Nama Alur yang sesuai.');
    $petunjukSheet->setCellValue('A5', '3. Kolom "NO" bersifat opsional, boleh dikosongkan.');
    $petunjukSheet->setCellValue('A6', '4. Kolom "NAMA KOMPONEN" dan "JUMLAH PER UNIT" wajib diisi.');
    $petunjukSheet->setCellValue('A7', '5. Kolom "KODE GUDANG" bersifat opsional.');
    $petunjukSheet->setCellValue('A8', '6. Pastikan "JUMLAH PER UNIT" hanya berisi angka.');
    $petunjukSheet->setCellValue('A9', '7. Setelah selesai mengisi, simpan file ini lalu unggah melalui halaman sistem.');
    
    foreach (range('A', 'B') as $col) {
        $petunjukSheet->getColumnDimension($col)->setAutoSize(true);
    }

    // Set sheet aktif ke yang pertama
    $spreadsheet->setActiveSheetIndex(0);

    // Kirim file ke browser dengan nama file baru
    $filename = "Template Standar - " . preg_replace('/[^a-zA-Z0-9_ -]/s', '', $barang_info['nama_barang']) . ".xlsx";
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;

} catch (Exception $e) {
    die("Error saat membuat template: " . $e->getMessage());
}
?>