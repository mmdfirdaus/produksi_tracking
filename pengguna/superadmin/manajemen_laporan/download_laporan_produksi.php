<?php
session_start();

// Periksa otentikasi dan otorisasi
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'superadmin') {
    header("location: ../../auth/login.php");
    exit;
}

// Panggil semua file yang dibutuhkan
require '../../../vendor/autoload.php';
include '../../../system/database_connection.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

// Validasi input dari form modal
if ($_SERVER["REQUEST_METHOD"] !== "POST" || !isset($_POST['id_target']) || !isset($_POST['bulan_laporan'])) {
    die("Akses tidak sah atau data tidak lengkap.");
}

$id_target = (int)$_POST['id_target'];
$bulan_laporan = $_POST['bulan_laporan']; // Format: YYYY-MM

// Validasi format bulan
if (!preg_match('/^\d{4}-\d{2}$/', $bulan_laporan)) {
    die("Format bulan tidak valid.");
}

try {
    // Pecah string YYYY-MM menjadi tahun dan bulan
    list($production_year, $production_month) = explode('-', $bulan_laporan);
    $production_month = (int)$production_month;
    $production_year = (int)$production_year;

    // Buat objek tanggal dari bulan yang dipilih untuk mendapatkan nama bulan dan jumlah hari
    $report_date = new DateTime($bulan_laporan . '-01');
    $days_in_month = (int)$report_date->format('t');
    $month_name = $report_date->format('F');

    // 1. Ambil informasi detail target produksi dan nama barang
    $target_stmt = $pdo->prepare("
        SELECT 
            pt.nama_permintaan, 
            pt.jumlah_unit, 
            mb.nama_barang
        FROM production_targets pt
        JOIN master_barang mb ON pt.id_barang = mb.id_barang
        WHERE pt.id_target = ?
    ");
    $target_stmt->execute([$id_target]);
    $target_info = $target_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$target_info) {
        die("Target produksi tidak ditemukan.");
    }

    // 2. Ambil data material dan HITUNG kebutuhan total
    $material_stmt = $pdo->prepare("
        SELECT 
            ma.nama_alur, 
            mc.nama_komponen, 
            mc.kode_komponen, 
            tm.id_material, 
            tm.jumlah_per_unit, 
            (tm.jumlah_per_unit * pt.jumlah_unit) AS kebutuhan_total
        FROM target_material tm
        JOIN master_alur ma ON tm.id_alur = ma.id_alur
        JOIN master_komponen mc ON tm.id_komponen = mc.id_komponen
        JOIN production_targets pt ON tm.id_target = pt.id_target
        WHERE tm.id_target = ?
        ORDER BY ma.urutan, mc.nama_komponen
    ");
    $material_stmt->execute([$id_target]);
    $materials_raw = $material_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Kelompokkan material
    $grouped_materials = [];
    foreach ($materials_raw as $material) {
        $grouped_materials[$material['nama_alur']][] = $material;
    }

    // 3. Ambil data laporan harian untuk target ini pada bulan & tahun YANG DIPILIH
    $laporan_stmt = $pdo->prepare("
        SELECT 
            lh.id_material,
            DAY(lh.tanggal_laporan) as tanggal,
            SUM(lh.jumlah_selesai) as total_harian
        FROM laporan_harian lh
        JOIN target_material tm ON lh.id_material = tm.id_material
        WHERE tm.id_target = ? AND MONTH(lh.tanggal_laporan) = ? AND YEAR(lh.tanggal_laporan) = ?
        GROUP BY lh.id_material, tanggal
    ");
    $laporan_stmt->execute([$id_target, $production_month, $production_year]);
    $laporan_harian = $laporan_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Olah data laporan harian ke dalam format yang mudah diakses
    $progress_data = [];
    foreach ($laporan_harian as $laporan) {
        $progress_data[$laporan['id_material']][$laporan['tanggal']] = $laporan['total_harian'];
    }

    // --- MULAI MEMBUAT FILE EXCEL ---

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Laporan ' . $month_name);

    // Informasi Umum
    $sheet->setCellValue('A1', 'Nama Barang');
    $sheet->setCellValue('C1', $target_info['nama_barang']);
    $sheet->mergeCells('C1:F1');
    $sheet->setCellValue('A2', 'Bulan');
    $sheet->setCellValue('C2', $month_name . ' ' . $production_year);
    $sheet->mergeCells('C2:F2');
    $sheet->getStyle('A1:C2')->getFont()->setBold(true);

    $row = 4;

    $header_style = [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F81BD']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
    ];

    foreach ($grouped_materials as $nama_alur => $komponens) {
        // ... (Sisa kode untuk generate Excel tetap sama persis) ...
        $sheet->setCellValue('A' . $row, $nama_alur);
        $sheet->mergeCells('A' . $row . ':G' . $row);
        $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('DCE6F1');
        $row += 2;

        $start_header_row = $row;
        $sheet->setCellValue('A' . $row, 'NO');
        $sheet->setCellValue('B' . $row, 'NAMA KOMPONEN');
        $sheet->setCellValue('C' . $row, 'KODE KOMPONEN');
        $sheet->setCellValue('D' . $row, 'JML');
        $sheet->setCellValue('E' . $row, 'KEBUTUHAN TOTAL');
        
        $col_index = 6;
        for ($day = 1; $day <= $days_in_month; $day++) {
            $sheet->setCellValue([$col_index, $row], $day);
            $col_index++;
        }
        
        $total_col_letter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col_index);
        $status_col_letter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col_index + 1);
        
        $sheet->setCellValue($total_col_letter . $row, 'Total Selesai');
        $sheet->setCellValue($status_col_letter . $row, 'Status');

        $last_header_col_letter = $status_col_letter;
        $sheet->getStyle('A' . $start_header_row . ':' . $last_header_col_letter . $start_header_row)->applyFromArray($header_style);

        $row++;
        
        $no = 1;
        foreach ($komponens as $komponen) {
            $sheet->setCellValue('A' . $row, $no++);
            $sheet->setCellValue('B' . $row, $komponen['nama_komponen']);
            $sheet->setCellValue('C' . $row, $komponen['kode_komponen']); 
            $sheet->setCellValue('D' . $row, $komponen['jumlah_per_unit']);
            $sheet->setCellValue('E' . $row, $komponen['kebutuhan_total']);
            
            $total_selesai = 0;
            $col_index = 6;
            for ($day = 1; $day <= $days_in_month; $day++) {
                $jumlah_hari_ini = $progress_data[$komponen['id_material']][$day] ?? 0;
                if ($jumlah_hari_ini > 0) {
                    $sheet->setCellValue([$col_index, $row], $jumlah_hari_ini);
                }
                $total_selesai += $jumlah_hari_ini;
                $col_index++;
            }
            
            $sheet->setCellValue($total_col_letter . $row, $total_selesai);
            
            $status = ($total_selesai >= $komponen['kebutuhan_total']) ? 'Terpenuhi' : 'Belum Terpenuhi';
            $sheet->setCellValue($status_col_letter . $row, $status);
            
            $row++;
        }
        $row += 2;
    }
    
    foreach (range('A', $last_header_col_letter) as $columnID) {
        $sheet->getColumnDimension($columnID)->setAutoSize(true);
    }

    // --- KIRIM FILE KE BROWSER ---
    $file_name = 'Laporan ' . $target_info['nama_barang'] . ' - ' . $month_name . ' ' . $production_year . '.xlsx';
    $file_name = preg_replace('/[^A-Za-z0-9\-\. ]/', '_', $file_name);

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $file_name . '"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;

} catch (PDOException $e) {
    die("Error saat mengambil data: " . $e->getMessage());
} catch (\Exception $e) {
    die("Error saat memproses data: " . $e->getMessage());
}