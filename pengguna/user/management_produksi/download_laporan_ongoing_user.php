<?php
session_start();

// 1. Pengecekan sesi dan peran untuk 'user'
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["role"]) || $_SESSION["role"] !== 'user') {
    header("location: ../../../auth/login.php");
    exit;
}

// Sesuaikan path ke autoload dan koneksi database
require '../../../vendor/autoload.php';
include '../../../system/database_connection.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

// Validasi input dari POST
$id_target = isset($_POST['id_target']) ? (int)$_POST['id_target'] : 0;
$bulan_laporan = isset($_POST['bulan_laporan']) ? $_POST['bulan_laporan'] : ''; // Format YYYY-MM

// Validasi tambahan untuk format bulan
if ($id_target === 0 || empty($bulan_laporan) || !preg_match('/^\d{4}-\d{2}$/', $bulan_laporan)) {
    die("Parameter tidak valid. Mohon pilih target dan bulan laporan yang benar.");
}

try {
    // Ambil Informasi Umum Target
    $info_stmt = $pdo->prepare("
        SELECT
            pt.nama_permintaan, pt.jumlah_unit,
            mb.id_barang, mb.nama_barang
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
    $sheet->setTitle($nama_bulan_tahun); // Nama sheet Excel

    // =================================================================
    // ## 2. Kumpulan Style ##
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
    $percentFormat = [
           'numberFormat' => [
               'formatCode' => '0.00%'
        ]
    ];
    // =================================================================

    // Header Laporan di Excel
    $sheet->setCellValue('A1', 'Laporan Produksi: ' . htmlspecialchars($target_info['nama_barang']));
    $sheet->setCellValue('A2', 'Target Permintaan: ' . htmlspecialchars($target_info['nama_permintaan']));
    $sheet->setCellValue('A3', 'Bulan: ' . $nama_bulan_tahun);
    $sheet->getStyle('A1:A3')->applyFromArray($mainHeaderStyle);

    // Query untuk mengambil alur
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

    $row = 5; // Mulai baris data setelah header
    
    // --- VARIABEL $firstHeaderRowForFreeze DIHAPUS DARI SINI ---

    foreach ($alurs as $alur) {
        $startRowTable = $row; 

        // Header Kategori Alur
        $sheet->setCellValue('A' . $row, 'Alur Produksi: ' . htmlspecialchars($alur['nama_alur']));
        $sheet->getStyle('A' . $row)->applyFromArray($categoryStyle);
        $row++;

        // Header Tabel
        $headerRow = $row;
        
        // --- BLOK IF UNTUK $firstHeaderRowForFreeze DIHAPUS DARI SINI ---

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
        $sheet->mergeCells('A' . ($startRowTable) . ':' . $highestColumnLetter . ($startRowTable)); 
        $sheet->getStyle('A' . ($startRowTable))->applyFromArray($centerAlignment); 
        $row++;
        $dataStartRow = $row; 

        // Query Material
        $material_stmt = $pdo->prepare("
            SELECT
                tm.id_material, mk.nama_komponen, tm.jumlah_per_unit,
                (SELECT SUM(jumlah_selesai) FROM laporan_harian WHERE id_material = tm.id_material AND DATE_FORMAT(tanggal_laporan, '%Y-%m') = :bulan) as total_selesai,
                GROUP_CONCAT(CONCAT(DAY(lh.tanggal_laporan), ':', lh.jumlah_selesai) SEPARATOR ';') as harian
            FROM target_material tm
            JOIN master_komponen mk ON tm.id_komponen = mk.id_komponen
            LEFT JOIN laporan_harian lh ON tm.id_material = lh.id_material AND DATE_FORMAT(lh.tanggal_laporan, '%Y-%m') = :bulan_join
            WHERE tm.id_target = :id_target AND tm.id_alur = :id_alur
            GROUP BY tm.id_material, mk.nama_komponen, tm.jumlah_per_unit
            ORDER BY mk.nama_komponen ASC
        ");
        $material_stmt->execute([
            ':bulan' => $bulan_laporan,
            ':bulan_join' => $bulan_laporan,
            ':id_target' => $id_target,
            ':id_alur' => $alur['id_alur']
        ]);
        $materials = $material_stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($materials)) {
            $sheet->setCellValue('A' . $row, 'Tidak ada komponen untuk alur ini pada bulan ini.');
            $sheet->mergeCells('A' . $row . ':' . $highestColumnLetter . $row);
            $sheet->getStyle('A' . $row)->applyFromArray($centerAlignment);
            $row++;
        } else {
            $no = 1;
            foreach ($materials as $mat) {
                $kebutuhan_total = (int)$mat['jumlah_per_unit'] * (int)$target_info['jumlah_unit'];
                $total_selesai = (int)$mat['total_selesai'];
                $sisa = $kebutuhan_total - $total_selesai;
                $sisa = max(0, $sisa); 
                $persen_selesai = $kebutuhan_total > 0 ? ($total_selesai / $kebutuhan_total) : 0;
                $persen_selesai = min(1, $persen_selesai); 

                $sheet->setCellValue('A' . $row, $no++);
                $sheet->setCellValue('B' . $row, htmlspecialchars($mat['nama_komponen']));
                $sheet->setCellValue('C' . $row, $mat['jumlah_per_unit']);
                $sheet->setCellValue('D' . $row, $kebutuhan_total);
                $sheet->setCellValue('E' . $row, $total_selesai > 0 ? $total_selesai : '0'); 
                $sheet->setCellValue('F' . $row, $sisa);
                $sheet->setCellValue('G' . $row, $persen_selesai);
                $sheet->getStyle('G' . $row)->applyFromArray($percentFormat); 

                // Proses data harian
                $harian_data = [];
                if (!empty($mat['harian'])) {
                    $pairs = explode(';', $mat['harian']);
                    foreach ($pairs as $pair) {
                         if (strpos($pair, ':') !== false) {
                             list($hari, $jumlah) = explode(':', $pair);
                             $hari_int = (int)$hari;
                             if ($hari_int > 0 && $hari_int <= $days_in_month) {
                                 $harian_data[$hari_int] = ($harian_data[$hari_int] ?? 0) + (int)$jumlah;
                             }
                         }
                    }
                }

                // Isi kolom tanggal
                for ($i = 1; $i <= $days_in_month; $i++) {
                    $colIndex = count($headers) + $i;
                    $colLetter = Coordinate::stringFromColumnIndex($colIndex);
                    if (isset($harian_data[$i])) {
                        $sheet->setCellValue($colLetter . $row, $harian_data[$i]);
                    } else {
                         $sheet->setCellValue($colLetter . $row, ''); 
                    }
                }
                $row++;
            }
        }

        // =================================================================
        // ## 3. Terapkan Border dan Alignment per Tabel ##
        // =================================================================
        $tableRange = 'A' . $headerRow . ':' . $highestColumnLetter . ($row - 1);
        $sheet->getStyle($tableRange)->applyFromArray($borderStyle); 

        $dataColsToCenter = ['A', 'C', 'D', 'E', 'F', 'G']; 
        foreach($dataColsToCenter as $colChar) {
             $sheet->getStyle($colChar . $dataStartRow . ':' . $colChar . ($row - 1))->applyFromArray($centerAlignment);
        }
        $firstDateColIndex = count($headers) + 1;
        $firstDateColLetter = Coordinate::stringFromColumnIndex($firstDateColIndex);
        $sheet->getStyle($firstDateColLetter . $dataStartRow . ':' . $highestColumnLetter . ($row - 1))->applyFromArray($centerAlignment);
        // =================================================================

        $row++; // Beri spasi antar tabel alur
    }

    // =================================================================
    // ## 4. Atur lebar kolom otomatis ##
    // =================================================================
    foreach (range('A', $highestColumnLetter) as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    // --- BLOK KODE FREEZE PANE DIHAPUS DARI SINI ---

    $spreadsheet->setActiveSheetIndex(0);

    // Nama file yang lebih aman dan spesifik
    $safe_permintaan = preg_replace('/[^A-Za-z0-9-_\.]/', '_', $target_info['nama_permintaan']);
    $safe_barang = preg_replace('/[^A-Za-z0-9-_\.]/', '_', $target_info['nama_barang']);
    $filename = 'Laporan_Ongoing_' . $safe_barang . '_' . $safe_permintaan . '_' . $nama_bulan_tahun . '.xlsx';

    // Header untuk download file Excel
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    header('Cache-Control: max-age=1');
    header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); 
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT'); 
    header('Cache-Control: cache, must-revalidate'); 
    header('Pragma: public'); 

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output'); 
    exit;

} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    die("Terjadi kesalahan pada database saat membuat laporan.");
} catch (\PhpOffice\PhpSpreadsheet\Exception $e) {
    error_log("Spreadsheet Error: " . $e->getMessage());
    die("Terjadi kesalahan saat membuat file Excel laporan.");
}
?>