<?php
session_start();
// 1. Load Vendor (termasuk Dompdf) dan Koneksi Database
require '../../../vendor/autoload.php';
include '../../../system/database_connection.php';

// 2. Gunakan namespace Dompdf
use Dompdf\Dompdf;
use Dompdf\Options;

// 3. Cek Sesi Superadmin
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'admin') {
    header("location: ../../auth/login.php");
    exit;
}

// 4. Validasi Input
$id_target = isset($_POST['id_target']) ? (int)$_POST['id_target'] : 0;
/// 4. Validasi Input
$id_target = isset($_POST['id_target']) ? (int)$_POST['id_target'] : 0;
// Kita AKAN menerima array, karena form PDF mengirimkan name="bulan_laporan[]"
$bulan_laporan_array = isset($_POST['bulan_laporan']) ? $_POST['bulan_laporan'] : [];

if ($id_target === 0 || empty($bulan_laporan_array)) {
    die("Parameter tidak valid atau tidak ada bulan laporan yang dipilih.");
}

// Ambil HANYA bulan pertama dari array
$bulan = $bulan_laporan_array[0]; // <-- Ini akan menjadi string '2025-11'
if (!preg_match('/^\d{4}-\d{2}$/', $bulan)) { // <-- Ini (line 30) sekarang akan benar
    die("Format bulan tidak valid.");
}

// Hitung jumlah hari (Pindahkan dari baris 33 ke sini)
$days_in_month = cal_days_in_month(CAL_GREGORIAN, date('m', strtotime($bulan)), date('Y', strtotime($bulan)));
$nama_bulan_tahun = date('F Y', strtotime($bulan . '-01'));

$days_in_month = cal_days_in_month(CAL_GREGORIAN, date('m', strtotime($bulan)), date('Y', strtotime($bulan)));
$nama_bulan_tahun = date('F Y', strtotime($bulan . '-01'));

// Helper function untuk memformat tanggal
function format_tanggal_indonesia($date_str) {
    if (empty($date_str) || $date_str === null) return 'N/A';
    try {
        if (setlocale(LC_TIME, 'id_ID.UTF-8', 'id_ID', 'Indonesian') === false) {
            $date = new DateTime($date_str);
            return $date->format('d F Y, H:i');
        }
        $timestamp = strtotime($date_str);
        return strftime('%d %B %Y, %H:%M', $timestamp);
    } catch (Exception $e) {
        return $date_str;
    }
}


try {
    // 5. [QUERY 1] Ambil Data Header (Info Target)
    $header_stmt = $pdo->prepare("
        SELECT 
            pt.nama_permintaan, pt.jumlah_unit, pt.created_at,
            mb.nama_barang, mk.nama_kategori
        FROM production_targets pt
        JOIN master_barang mb ON pt.id_barang = mb.id_barang
        LEFT JOIN master_kategori mk ON mb.id_kategori = mk.id_kategori
        WHERE pt.id_target = :id_target
    ");
    $header_stmt->execute([':id_target' => $id_target]);
    $header_info = $header_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$header_info) { die("Target produksi tidak ditemukan."); }

    // 6. [QUERY 2] Ambil Daftar Alur yang digunakan, LENGKAP DENGAN PIC
    // (Query ini digabung dari 'download_laporan_ongoing.php' dan 'alur_produksi.php')
    $alur_stmt = $pdo->prepare("
        SELECT
            ma.id_alur,
            ma.nama_alur,
            ma.urutan,
            GROUP_CONCAT(DISTINCT u.full_name SEPARATOR ', ') AS pic
        FROM (
            -- Ambil alur unik dari target_material untuk target ini
            SELECT DISTINCT id_alur FROM target_material WHERE id_target = :id_target
        ) tm_unique
        JOIN master_alur ma ON tm_unique.id_alur = ma.id_alur
        LEFT JOIN admin_tahapan_access ata ON ma.id_alur = ata.id_tahapan
        LEFT JOIN users u ON ata.id_user = u.id
        GROUP BY ma.id_alur, ma.nama_alur, ma.urutan
        ORDER BY ma.urutan ASC
    ");
    $alur_stmt->execute([':id_target' => $id_target]);
    $alurs = $alur_stmt->fetchAll(PDO::FETCH_ASSOC);


    // 7. Mulai membangun string HTML untuk PDF
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Laporan On-Going</title>
        <style>
            @page {
                margin: 20px; /* Margin kecil untuk layout landscape */
            }
            body {
                font-family: "Helvetica", "Arial", sans-serif;
                font-size: 8px; /* Ukuran font sangat kecil agar muat */
                margin: 0;
            }
            .container {
                width: 100%;
            }
            .header-title {
                text-align: center;
                font-size: 14px;
                font-weight: bold;
                margin-bottom: 5px;
                color: #333;
            }
            .header-subtitle {
                text-align: center;
                font-size: 12px;
                font-weight: normal;
                margin-bottom: 15px;
                color: #555;
            }
            
            /* Tabel Header (Info Target) */
            .table-header {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 15px; 
            }
            .table-header th, .table-header td {
                border: 1px solid #555;
                padding: 4px; /* Padding kecil */
                text-align: left;
                vertical-align: top;
                font-size: 9px;
            }
            .table-header th {
                background-color: #f0f0f0;
                font-weight: bold;
                width: 20%;
            }

            /* Judul Alur */
            .alur-title {
                font-size: 11px;
                font-weight: bold;
                color: #333;
                background-color: #DDEBF7; /* Biru muda (sesuai style Excel) */
                padding: 5px;
                margin-top: 10px; /* Jarak antar alur (tidak terlalu besar) */
                margin-bottom: 3px;
                border: 1px solid #999;
                page-break-after: avoid; /* Jangan pisah halaman setelah judul */
            }
            .alur-title span {
                font-weight: normal;
                color: #004a99;
            }
            
            /* Tabel Rincian Komponen */
            .table-komponen {
                width: 100%;
                border-collapse: collapse;
                page-break-inside: auto; 
            }
            .table-komponen th, .table-komponen td {
                border: 1px solid #888;
                padding: 3px 4px; /* Sangat padat */
                text-align: center;
            }
            .table-komponen th {
                background-color: #4472C4; /* Biru header Excel */
                color: white;
                font-weight: bold;
                font-size: 8px;
                padding: 4px;
            }
            .table-komponen td.text-left {
                text-align: left;
                padding-left: 5px;
            }
            .table-komponen td.daily-data {
                font-weight: bold;
                color: #004a99;
            }
            tr {
                page-break-inside: avoid;
            }

        </style>
    </head>
    <body>
        <div class="header-title">LAPORAN PRODUKSI ON-GOING</div>
        <div class="header-subtitle">' . htmlspecialchars($header_info['nama_barang']) . ' - ' . htmlspecialchars($header_info['nama_permintaan']) . '</div>
        <div class="container">
    ';

    // 8. Render Bagian Header (Info Target)
    // Sesuai permintaan, TANPA TANGGAL SELESAI
    $html .= '
            <table class="table-header">
                <tr>
                    <th>Nama Target</th>
                    <td>' . htmlspecialchars($header_info['nama_permintaan']) . '</td>
                    <th>Bulan Laporan</th>
                    <td>' . htmlspecialchars($nama_bulan_tahun) . '</td>
                </tr>
                <tr>
                    <th>Kategori</th>
                    <td>' . htmlspecialchars($header_info['nama_kategori'] ?? 'N/A') . '</td>
                    <th>Jumlah Unit</th>
                    <td>' . htmlspecialchars($header_info['jumlah_unit']) . ' Unit</td>
                </tr>
                <tr>
                    <th>Tanggal Mulai</th>
                    <td colspan="3">' . format_tanggal_indonesia($header_info['created_at']) . '</td>
                </tr>
            </table>
    ';

    // 9. Loop per Alur untuk render Tabel Komponen
    foreach ($alurs as $alur) {
        $html .= '<div class="alur-title">
                      Alur Produksi: ' . htmlspecialchars($alur['nama_alur']) . ' 
                      <span>| PIC: ' . htmlspecialchars($alur['pic'] ?? 'N/A') . '</span>
                  </div>';

        // [QUERY 3] Ambil data material/komponen untuk alur DAN bulan ini
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
        $material_stmt->execute([
            ':bulan' => $bulan, 
            ':id_target' => $id_target, 
            ':id_alur' => $alur['id_alur']
        ]);
        $materials = $material_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Buat Header Tabel Komponen
        $html .= '
            <table class="table-komponen">
                <thead>
                    <tr>
                        <th style="width:2%;">No</th>
                        <th style="width:20%;">Nama Komponen</th>
                        <th style="width:5%;">Jml/Unit</th>
                        <th style="width:6%;">Kebutuhan</th>
                        <th style="width:6%;">Selesai</th>
                        <th style="width:5%;">Sisa</th>
                        <th style="width:4%;">%</th>';
        
        // Buat header tanggal 1-31
        for ($i = 1; $i <= $days_in_month; $i++) {
            $html .= '<th style="width:1.5%;">' . $i . '</th>';
        }

        $html .= '
                    </tr>
                </thead>
                <tbody>
        ';

        if (empty($materials)) {
            $html .= '<tr><td colspan="' . (7 + $days_in_month) . '">Tidak ada data komponen untuk alur ini.</td></tr>';
        } else {
            $no = 1;
            foreach ($materials as $mat) {
                $kebutuhan_total = $mat['jumlah_per_unit'] * $header_info['jumlah_unit'];
                $total_selesai_global = (int)$mat['total_selesai_global'];
                $sisa = $kebutuhan_total - $total_selesai_global;
                $persen_selesai = $kebutuhan_total > 0 ? round(($total_selesai_global / $kebutuhan_total) * 100) : 0;

                // Proses data harian
                $harian_data = [];
                if ($mat['harian']) {
                    foreach (explode(';', $mat['harian']) as $pair) {
                        if (strpos($pair, ':') !== false) {
                            list($tanggal, $jumlah) = explode(':', $pair);
                            $hari = (int)date('d', strtotime($tanggal));
                            $harian_data[$hari] = ($harian_data[$hari] ?? 0) + $jumlah;
                        }
                    }
                }

                $html .= '
                    <tr>
                        <td>' . $no++ . '</td>
                        <td class="text-left">' . htmlspecialchars($mat['nama_komponen']) . '</td>
                        <td>' . number_format($mat['jumlah_per_unit']) . '</td>
                        <td>' . number_format($kebutuhan_total) . '</td>
                        <td>' . number_format($total_selesai_global) . '</td>
                        <td>' . number_format($sisa) . '</td>
                        <td>' . $persen_selesai . '%</td>';
                
                // Render data harian 1-31
                for ($i = 1; $i <= $days_in_month; $i++) {
                    if (isset($harian_data[$i])) {
                        $html .= '<td class="daily-data">' . $harian_data[$i] . '</td>';
                    } else {
                        $html .= '<td>-</td>';
                    }
                }

                $html .= '
                    </tr>
                ';
            }
        }

        $html .= '
                </tbody>
            </table>
        ';
    } // End foreach $alurs

    $html .= '
        </div>
    </body>
    </html>
    ';

    // 10. Inisialisasi dan Render Dompdf
    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true); 
    $options->set('defaultFont', 'Helvetica');

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    
    // [PENTING] Set Kertas ke A4 Landscape
    $dompdf->setPaper('A4', 'landscape');
    
    $dompdf->render();

    $filename = "Laporan OnGoing - " . preg_replace('/[^A-Za-z0-9\-]/', '', $header_info['nama_permintaan']) . " - $bulan.pdf";
    $dompdf->stream($filename, ["Attachment" => true]);
    exit;

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
} catch (Exception $e) {
    die("General Error: " . $e->getMessage());
}
?>