<?php
session_start();
require '../../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'superadmin') {
    header("location: ../../auth/login.php");
    exit;
}

include '../../../system/database_connection.php';

$id_target = isset($_POST['id_target']) ? (int)$_POST['id_target'] : 0;
$bulan_laporan_array = isset($_POST['bulan_laporan']) ? (array)$_POST['bulan_laporan'] : [];

if ($id_target === 0 || empty($bulan_laporan_array)) {
    die("Parameter tidak valid atau tidak ada bulan laporan yang dipilih.");
}

try {
    // Ambil Informasi Umum Target
    $info_stmt = $pdo->prepare("
        SELECT 
            pt.nama_permintaan, pt.jumlah_unit, pt.no_spk, pt.created_at, pt.tanggal_selesai,
            mb.id_barang, mb.nama_barang, mb.kode_barang
        FROM production_targets pt
        JOIN master_barang mb ON pt.id_barang = mb.id_barang
        WHERE pt.id_target = :id_target
    ");
    $info_stmt->execute([':id_target' => $id_target]);
    $target_info = $info_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$target_info) { die("Target produksi tidak ditemukan."); }

    // Ambil data statistik
    $dates_stmt = $pdo->prepare("
        SELECT MIN(lh.tanggal_laporan) as start_date, MAX(lh.tanggal_laporan) as end_date
        FROM laporan_harian lh
        JOIN target_material tm ON lh.id_material = tm.id_material
        WHERE tm.id_target = :id_target
    ");
    $dates_stmt->execute([':id_target' => $id_target]);
    $production_dates = $dates_stmt->fetch(PDO::FETCH_ASSOC);

    $total_pcs_stmt = $pdo->prepare("
        SELECT SUM(jumlah_selesai) 
        FROM laporan_harian lh
        JOIN target_material tm ON lh.id_material = tm.id_material
        WHERE tm.id_target = :id_target
    ");
    $total_pcs_stmt->execute([':id_target' => $id_target]);
    $total_pcs = $total_pcs_stmt->fetchColumn();

    $spreadsheet = new Spreadsheet();
    
    // =================================================================
    // ## STYLING ARRAYS ##
    // =================================================================
    $centerAlignment = ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER];
    
    $headerStyle = [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
        'alignment' => $centerAlignment
    ];
    
    $categoryStyle = [
        'font' => ['bold' => true],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DDEBF7']],
        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER]
    ];
    
    $borderStyle = [
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]]
    ];

    // =================================================================
    // ## HALAMAN RINGKASAN (SUMMARY SHEET) ##
    // =================================================================
    $summarySheet = $spreadsheet->getActiveSheet();
    $summarySheet->setTitle('Ringkasan Laporan');

    $summarySheet->mergeCells('A1:B1');
    $summarySheet->setCellValue('A1', 'RINGKASAN LAPORAN PRODUKSI');
    $summarySheet->getStyle('A1')->applyFromArray($headerStyle)->getFont()->setSize(16);
    $summarySheet->getRowDimension(1)->setRowHeight(30);

    // Informasi Umum
    $summarySheet->mergeCells('A3:B3')->setCellValue('A3', 'INFORMASI UMUM TARGET');
    $summarySheet->getStyle('A3:B3')->applyFromArray($categoryStyle);
    $summarySheet->setCellValue('A4', 'Nama Barang')->setCellValue('B4', $target_info['nama_barang'] . ' (' . $target_info['kode_barang'] . ')');
    $summarySheet->setCellValue('A5', 'Nama Target')->setCellValue('B5', $target_info['nama_permintaan'] . ' (SPK: ' . $target_info['no_spk'] . ')');
    $summarySheet->setCellValue('A6', 'Jumlah Unit')->setCellValue('B6', $target_info['jumlah_unit'] . ' Unit');
    $summarySheet->setCellValue('A7', 'Tanggal Mulai Produksi')->setCellValue('B7', $production_dates['start_date'] ? date('d F Y', strtotime($production_dates['start_date'])) : 'N/A');
    $summarySheet->setCellValue('A8', 'Tanggal Selesai')->setCellValue('B8', $target_info['tanggal_selesai'] ? date('d F Y', strtotime($target_info['tanggal_selesai'])) : 'N/A');
    $summarySheet->getStyle('A3:B8')->applyFromArray($borderStyle);

    // Statistik Kunci
    $summarySheet->mergeCells('A10:B10')->setCellValue('A10', 'STATISTIK KUNCI');
    $summarySheet->getStyle('A10:B10')->applyFromArray($categoryStyle);
    $summarySheet->setCellValue('A11', 'Total Unit Material Diproduksi')->setCellValue('B11', number_format((float)$total_pcs) . ' Pcs');
    $summarySheet->getStyle('A10:B11')->applyFromArray($borderStyle);

    $summarySheet->getColumnDimension('A')->setWidth(30);
    $summarySheet->getColumnDimension('B')->setWidth(40);

    // =================================================================
    // ## HALAMAN DETAIL BULANAN ##
    // =================================================================
    foreach ($bulan_laporan_array as $bulan) {
        $detailSheet = $spreadsheet->createSheet();
        $sheet_title = date('F Y', strtotime($bulan . '-01'));
        $detailSheet->setTitle($sheet_title);

        // Header Info
        $detailSheet->setCellValue('A1', 'Laporan Produksi: ' . $target_info['nama_barang']);
        $detailSheet->setCellValue('A2', 'Target: ' . $target_info['nama_permintaan']);
        $detailSheet->setCellValue('A3', 'Bulan: ' . $sheet_title);
        $detailSheet->getStyle('A1:A3')->getFont()->setBold(true);

        $alurs_stmt = $pdo->prepare("
            SELECT DISTINCT ma.id_alur, ma.nama_alur, ma.urutan
            FROM master_alur ma
            JOIN alur_barang ab ON ma.id_alur = ab.id_alur
            JOIN target_material tm ON ab.id_alur = tm.id_alur
            WHERE ab.id_barang = :id_barang AND tm.id_target = :id_target
            ORDER BY ma.urutan ASC
        ");
        $alurs_stmt->execute([':id_barang' => $target_info['id_barang'], ':id_target' => $id_target]);
        $alurs = $alurs_stmt->fetchAll(PDO::FETCH_ASSOC);

        $row = 5;

        foreach ($alurs as $alur) {
            $start_row_alur = $row;
            $header_row = $row + 1;
            
            // Header Alur
            $detailSheet->setCellValue('A' . $row, 'Alur Produksi: ' . $alur['nama_alur']);
            $detailSheet->getStyle('A' . $row)->applyFromArray($categoryStyle)->getFont()->setSize(12);
            $detailSheet->mergeCells('A' . $row . ':G' . $row);
            $row++;
            
            // Header Tabel
            $headers = ['No', 'Nama Komponen', 'Jml/Unit', 'Kebutuhan', 'Selesai', 'Sisa', '%'];
            $detailSheet->fromArray($headers, NULL, 'A' . $row);

            $days_in_month = cal_days_in_month(CAL_GREGORIAN, date('m', strtotime($bulan)), date('Y', strtotime($bulan)));
            for ($i = 1; $i <= $days_in_month; $i++) {
                $colLetter = Coordinate::stringFromColumnIndex(count($headers) + $i);
                $detailSheet->setCellValue($colLetter . $row, $i);
            }
            $row++;
            
            // =================================================================
            // ## PERBAIKAN QUERY DI SINI: Mengganti master_material menjadi master_komponen ##
            // =================================================================
            $material_stmt = $pdo->prepare("
                SELECT
                    tm.id_material, mk.nama_komponen, tm.jumlah_per_unit,
                    (SELECT SUM(jumlah_selesai) FROM laporan_harian WHERE id_material = tm.id_material) as total_selesai_global,
                    GROUP_CONCAT(lh.tanggal_laporan, ':', lh.jumlah_selesai SEPARATOR ';') as harian
                FROM target_material tm
                JOIN master_komponen mk ON tm.id_komponen = mk.id_komponen
                LEFT JOIN laporan_harian lh ON tm.id_material = lh.id_material AND DATE_FORMAT(lh.tanggal_laporan, '%Y-%m') = :bulan
                WHERE tm.id_target = :id_target AND tm.id_alur = :id_alur
                GROUP BY tm.id_material, mk.nama_komponen, tm.jumlah_per_unit
                ORDER BY mk.nama_komponen
            ");
            $material_stmt->execute([':bulan' => $bulan, ':id_target' => $id_target, ':id_alur' => $alur['id_alur']]);
            $materials = $material_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $no = 1;
            $start_data_row = $row;
            foreach ($materials as $mat) {
                $kebutuhan_total = $mat['jumlah_per_unit'] * $target_info['jumlah_unit'];
                $total_selesai_global = (int)$mat['total_selesai_global'];
                $sisa = $kebutuhan_total - $total_selesai_global;
                $persen_selesai = $kebutuhan_total > 0 ? ($total_selesai_global / $kebutuhan_total) : 0;

                $detailSheet->fromArray([
                    $no++,
                    $mat['nama_komponen'],
                    $mat['jumlah_per_unit'],
                    $kebutuhan_total,
                    $total_selesai_global,
                    $sisa,
                    $persen_selesai
                ], NULL, 'A' . $row);
                $detailSheet->getStyle('G' . $row)->getNumberFormat()->setFormatCode('0.00%');
                
                $harian_data = [];
                if ($mat['harian']) {
                    foreach (explode(';', $mat['harian']) as $pair) {
                        list($tanggal, $jumlah) = explode(':', $pair);
                        $hari = (int)date('d', strtotime($tanggal));
                        $harian_data[$hari] = ($harian_data[$hari] ?? 0) + $jumlah;
                    }
                }

                for ($i = 1; $i <= $days_in_month; $i++) {
                    if (isset($harian_data[$i])) {
                        $colIndex = count($headers) + $i;
                        $colLetter = Coordinate::stringFromColumnIndex($colIndex);
                        $detailSheet->setCellValue($colLetter . $row, $harian_data[$i]);
                    }
                }
                $row++;
            }

            // Apply Styling per Alur
            $highestColumnLetter = $detailSheet->getHighestColumn();
            $detailSheet->getStyle('A' . $header_row . ':' . $highestColumnLetter . ($row - 1))->applyFromArray($borderStyle);
            $detailSheet->getStyle('A' . $header_row . ':' . $highestColumnLetter . $header_row)->applyFromArray($headerStyle);
            
            // Tengahkan teks angka
            $rangeToCenter = 'A' . $start_data_row . ':A' . ($row - 1);
            $detailSheet->getStyle($rangeToCenter)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $rangeToCenter = 'C' . $start_data_row . ':' . $highestColumnLetter . ($row - 1);
            $detailSheet->getStyle($rangeToCenter)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $row++; // Spasi antar alur
        }

        // Auto-size columns
        $detailSheet->getColumnDimension('B')->setWidth(35); // Nama Komponen
        $highestColumn = $detailSheet->getHighestColumn();
        if ($highestColumn != '') {
            foreach (range('C', $highestColumn) as $col) {
                $detailSheet->getColumnDimension($col)->setAutoSize(true);
            }
        }
    }

    $spreadsheet->setActiveSheetIndex(0);

    $filename = "Laporan Selesai - " . preg_replace('/[^A-Za-z0-9\-]/', '', $target_info['nama_permintaan']) . ".xlsx";
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
} catch (\PhpOffice\PhpSpreadsheet\Exception $e) {
    die("Spreadsheet Error: " . $e->getMessage());
}
?>