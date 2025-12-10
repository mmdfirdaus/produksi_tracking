<?php
session_start();

// 1. Pengecekan sesi dan peran dari KODE BARU (lebih aman)
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'superadmin') {
    header("location: ../../auth/login.php");
    exit;
}

require '../../../vendor/autoload.php';
include '../../../system/database_connection.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

// Validasi input dari KODE BARU
$id_target = isset($_POST['id_target']) ? (int)$_POST['id_target'] : 0;
$bulan_laporan = isset($_POST['bulan_laporan']) ? $_POST['bulan_laporan'] : ''; // Variabel disesuaikan

if ($id_target === 0 || empty($bulan_laporan)) {
    die("Parameter tidak valid. Mohon pilih target dan bulan laporan.");
}

try {
    // Ambil Informasi Umum Target (dari kode lama)
    $info_stmt = $pdo->prepare("
        SELECT 
            pt.nama_permintaan, pt.jumlah_unit,pt.no_spk,
            mb.id_barang, mb.nama_barang, mb.kode_barang
        FROM production_targets pt
        JOIN master_barang mb ON pt.id_barang = mb.id_barang
        WHERE pt.id_target = :id_target
    ");
    $info_stmt->execute([':id_target' => $id_target]);
    $target_info = $info_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$target_info) { die("Target produksi tidak ditemukan."); }

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $nama_bulan_tahun = date('F Y', strtotime($bulan_laporan . '-01'));
    $sheet->setTitle($nama_bulan_tahun);

    // =================================================================
    // ## 2. Kumpulan Style dari KODE BARU ##
    // =================================================================
    $mainHeaderStyle = ['font' => ['bold' => true, 'size' => 14]];
    $categoryStyle = ['font' => ['bold' => true], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFDDEBF7']]];
    $tableHeaderStyle = [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1F4E78']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    ];
    $borderStyle = [
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['argb' => 'FF000000'],
            ],
        ],
    ];
    $centerAlignment = [
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER,
        ],
    ];
    // =================================================================

    // Header Laporan
    $sheet->setCellValue('A1', 'Laporan Produksi: ' . htmlspecialchars($target_info['nama_barang']) . ' (ID: ' . htmlspecialchars($target_info['kode_barang']) . ')');
    $sheet->setCellValue('A2', 'Target Permintaan: ' . htmlspecialchars($target_info['nama_permintaan']) . ' (SPK: ' . htmlspecialchars($target_info['no_spk']) . ')');
    $sheet->setCellValue('A3', 'Bulan: ' . $nama_bulan_tahun);
    $sheet->getStyle('A1:A3')->applyFromArray($mainHeaderStyle);

    // Query untuk mengambil alur (dari kode lama)
    $alurs_stmt = $pdo->prepare("
        SELECT DISTINCT ma.id_alur, ma.nama_alur, ma.urutan
        FROM master_alur ma
        JOIN alur_barang ab ON ma.id_alur = ab.id_alur
        WHERE ab.id_barang = :id_barang AND EXISTS (
            SELECT 1 FROM target_material tm WHERE tm.id_target = :id_target AND tm.id_alur = ma.id_alur
        ) ORDER BY ma.urutan ASC
    ");
    $alurs_stmt->execute([':id_barang' => $target_info['id_barang'], ':id_target' => $id_target]);
    $alurs = $alurs_stmt->fetchAll(PDO::FETCH_ASSOC);

    $row = 5;

    foreach ($alurs as $alur) {
        $startRowTable = $row; // Tandai baris awal dari setiap tabel alur

        // Header Kategori Alur
        $sheet->setCellValue('A' . $row, 'Alur Produksi: ' . htmlspecialchars($alur['nama_alur']));
        $sheet->getStyle('A' . $row)->applyFromArray($categoryStyle);
        $row++;
        
        // Header Tabel
        $headerRow = $row;
        $headers = ['No', 'Nama Komponen', 'Jml/Unit', 'Total Kebutuhan', 'Total Selesai', 'Sisa', '% Selesai'];
        $sheet->fromArray($headers, NULL, 'A' . $row);
        
        $days_in_month = cal_days_in_month(CAL_GREGORIAN, date('m', strtotime($bulan_laporan)), date('Y', strtotime($bulan_laporan)));
        for ($i = 1; $i <= $days_in_month; $i++) {
            $colIndex = count($headers) + $i;
            $colLetter = Coordinate::stringFromColumnIndex($colIndex);
            $sheet->setCellValue($colLetter . $row, $i);
        }
        $highestColumnLetter = $sheet->getHighestColumn();
        $sheet->getStyle('A'.$row.':' . $highestColumnLetter . $row)->applyFromArray($tableHeaderStyle);
        $sheet->mergeCells('A' . $startRowTable . ':' . $highestColumnLetter . $startRowTable); // Merge header kategori
        $row++;
        $dataStartRow = $row; // Baris data mulai

        // Query Material (dari kode lama)
        $material_stmt = $pdo->prepare("
            SELECT
                tm.id_material, mk.nama_komponen, tm.jumlah_per_unit,
                (SELECT SUM(jumlah_selesai) FROM laporan_harian WHERE id_material = tm.id_material AND DATE_FORMAT(tanggal_laporan, '%Y-%m') = :bulan) as total_selesai,
                GROUP_CONCAT(lh.tanggal_laporan, ':', lh.jumlah_selesai SEPARATOR ';') as harian
            FROM target_material tm
            JOIN master_komponen mk ON tm.id_komponen = mk.id_komponen
            LEFT JOIN laporan_harian lh ON tm.id_material = lh.id_material AND DATE_FORMAT(lh.tanggal_laporan, '%Y-%m') = :bulan
            WHERE tm.id_target = :id_target AND tm.id_alur = :id_alur
            GROUP BY tm.id_material ORDER BY mk.nama_komponen
        ");
        $material_stmt->execute([':bulan' => $bulan_laporan, ':id_target' => $id_target, ':id_alur' => $alur['id_alur']]);
        $materials = $material_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($materials)) {
            $sheet->setCellValue('A' . $row, 'Tidak ada komponen untuk alur ini.');
            $sheet->mergeCells('A' . $row . ':' . $highestColumnLetter . $row);
            $row++;
        } else {
            $no = 1;
            foreach ($materials as $mat) {
                $kebutuhan_total = $mat['jumlah_per_unit'] * $target_info['jumlah_unit'];
                $total_selesai = (int)$mat['total_selesai'];
                $sisa = $kebutuhan_total - $total_selesai;
                $persen_selesai = $kebutuhan_total > 0 ? ($total_selesai / $kebutuhan_total) : 0;

                $sheet->setCellValue('A' . $row, $no++);
                $sheet->setCellValue('B' . $row, htmlspecialchars($mat['nama_komponen']));
                $sheet->setCellValue('C' . $row, $mat['jumlah_per_unit']);
                $sheet->setCellValue('D' . $row, $kebutuhan_total);
                $sheet->setCellValue('E' . $row, $total_selesai);
                $sheet->setCellValue('F' . $row, $sisa);
                $sheet->setCellValue('G' . $row, $persen_selesai);
                $sheet->getStyle('G' . $row)->getNumberFormat()->setFormatCode('0.00%');
                
                $harian_data = [];
                if ($mat['harian']) {
                    $pairs = explode(';', $mat['harian']);
                    foreach ($pairs as $pair) {
                        list($tanggal, $jumlah) = explode(':', $pair);
                        $hari = (int)date('d', strtotime($tanggal));
                        $harian_data[$hari] = ($harian_data[$hari] ?? 0) + $jumlah;
                    }
                }
                for ($i = 1; $i <= $days_in_month; $i++) {
                    if (isset($harian_data[$i])) {
                        $colIndex = count($headers) + $i;
                        $colLetter = Coordinate::stringFromColumnIndex($colIndex);
                        $sheet->setCellValue($colLetter . $row, $harian_data[$i]);
                    }
                }
                $row++;
            }
        }

        // =================================================================
        // ## 3. Terapkan Border dan Alignment dari KODE BARU per Tabel ##
        // =================================================================
        $tableRange = 'A' . $headerRow . ':' . $highestColumnLetter . ($row - 1);
        $sheet->getStyle($tableRange)->applyFromArray($borderStyle);

        // Terapkan alignment tengah untuk kolom non-teks
        $dataRange = 'A' . $dataStartRow . ':' . 'A' . ($row - 1);
        $sheet->getStyle($dataRange)->applyFromArray($centerAlignment);
        $dataRange = 'C' . $dataStartRow . ':' . $highestColumnLetter . ($row - 1);
        $sheet->getStyle($dataRange)->applyFromArray($centerAlignment);
        // =================================================================

        $row++; // Spasi antar tabel alur
    }

    // =================================================================
    // ## 4. Atur lebar kolom otomatis dari KODE BARU ##
    // =================================================================
    foreach (range('A', $sheet->getHighestColumn()) as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    $spreadsheet->setActiveSheetIndex(0);

    // Nama file yang lebih aman dan spesifik
    $safe_filename = preg_replace('/[^A-Za-z0-9-_\.]/', '_', $target_info['nama_permintaan'] . '_' . $nama_bulan_tahun);
    $filename = 'Laporan_Progress_' . $safe_filename . '.xlsx';

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
} catch (\PhpOffice\PhpSpreadsheet\Exception $e) {
    die("Spreadsheet Error: " . $e->getMessage());
}
?>