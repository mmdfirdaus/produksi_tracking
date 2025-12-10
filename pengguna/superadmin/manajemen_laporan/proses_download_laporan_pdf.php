<?php
session_start();
// 1. Load Vendor (termasuk Dompdf) dan Koneksi Database
require '../../../vendor/autoload.php';
include '../../../system/database_connection.php';

// 2. Gunakan namespace Dompdf
use Dompdf\Dompdf;
use Dompdf\Options;

// 3. Cek Sesi Superadmin
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'superadmin') {
    header("location: ../../auth/login.php");
    exit;
}

// 4. Validasi Input.
$id_target = isset($_POST['id_target']) ? (int)$_POST['id_target'] : 0;

if ($id_target === 0) {
    die("Parameter ID Target tidak valid.");
}

// Helper function untuk memformat tanggal ke format Indonesia
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
    // 5. Ambil Data Header (Info Target) - Sesuai Gambar 1
    $header_stmt = $pdo->prepare("
        SELECT 
            pt.nama_permintaan, pt.jumlah_unit, pt.created_at, pt.tanggal_selesai,pt.no_spk,
            mb.nama_barang, mk.nama_kategori, mb.kode_barang
        FROM production_targets pt
        JOIN master_barang mb ON pt.id_barang = mb.id_barang
        LEFT JOIN master_kategori mk ON mb.id_kategori = mk.id_kategori
        WHERE pt.id_target = :id_target
    ");
    $header_stmt->execute([':id_target' => $id_target]);
    $header_info = $header_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$header_info) { die("Target produksi tidak ditemukan."); }

    // 6. [QUERY 1] Ambil Data Konten (Ringkasan Alur)
    $alur_stmt = $pdo->prepare("
        SELECT
            ma.id_alur, -- <-- [TAMBAHAN] Ambil id_alur untuk key
            ma.nama_alur,
            'Selesai' AS status_pengerjaan,
            GROUP_CONCAT(DISTINCT u.full_name SEPARATOR ', ') AS pic,
            MIN(lh.created_at) AS waktu_mulai,
            MAX(lh.created_at) AS waktu_selesai
        FROM 
            target_material tm 
        JOIN 
            master_alur ma ON tm.id_alur = ma.id_alur
        LEFT JOIN 
            admin_tahapan_access ata ON ma.id_alur = ata.id_tahapan
        LEFT JOIN 
            users u ON ata.id_user = u.id
        LEFT JOIN 
            laporan_harian lh ON tm.id_material = lh.id_material
        WHERE 
            tm.id_target = :id_target 
        GROUP BY 
            ma.id_alur, ma.nama_alur, ma.urutan
        HAVING 
            waktu_mulai IS NOT NULL -- <-- [PERUBAHAN 1] Filter alur yang tidak terpakai
        ORDER BY 
            ma.urutan ASC
    ");
    $alur_stmt->execute([':id_target' => $id_target]);
    $alur_data = $alur_stmt->fetchAll(PDO::FETCH_ASSOC);

    // 7. [QUERY 2 BARU] Ambil data rincian komponen untuk semua alur yang relevan
    $komponen_stmt = $pdo->prepare("
        SELECT
            tm.id_alur,
            mk.nama_komponen,
            tm.jumlah_per_unit,
            (tm.jumlah_per_unit * :jumlah_unit) AS kebutuhan_total,
            COALESCE((SELECT SUM(jumlah_selesai) FROM laporan_harian WHERE id_material = tm.id_material), 0) AS total_selesai
        FROM
            target_material tm
        JOIN
            master_komponen mk ON tm.id_komponen = mk.id_komponen
        WHERE
            tm.id_target = :id_target
        ORDER BY
            mk.nama_komponen ASC
    ");
    $komponen_stmt->execute([
        ':id_target' => $id_target,
        ':jumlah_unit' => $header_info['jumlah_unit']
    ]);
    $komponen_data_raw = $komponen_stmt->fetchAll(PDO::FETCH_ASSOC);

    // 8. [LOGIKA BARU] Kelompokkan data komponen berdasarkan id_alur
    $komponen_by_alur = [];
    foreach ($komponen_data_raw as $komponen) {
        $id_alur = $komponen['id_alur'];
        if (!isset($komponen_by_alur[$id_alur])) {
            $komponen_by_alur[$id_alur] = [];
        }
        $komponen_by_alur[$id_alur][] = $komponen;
    }


    // 9. Mulai membangun string HTML untuk PDF
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Laporan Selesai</title>
        <style>
            body {
                font-family: "Helvetica", "Arial", sans-serif;
                font-size: 10px;
                margin: 25px;
            }
            .container {
                width: 100%;
            }
            .header-title {
                text-align: center;
                font-size: 16px;
                font-weight: bold;
                margin-bottom: 20px;
                color: #333;
            }
            
            /* Tabel Header (Info Target) */
            .table-header {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 20px; 
            }
            .table-header th, .table-header td {
                border: 1px solid #555;
                padding: 6px;
                text-align: left;
                vertical-align: top;
                font-size: 11px;
            }
            .table-header th {
                background-color: #f0f0f0;
                font-weight: bold;
                width: 20%;
            }
            
            /* Tabel Ringkasan Alur */
            .table-content {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 25px; /* Beri jarak ke tabel komponen pertama */
            }
            .table-content th, .table-content td {
                border: 1px solid #555;
                padding: 6px;
                text-align: left;
            }
            .table-content th {
                background-color: #004a99; /* Biru Tua */
                color: white;
                font-weight: bold;
                text-align: center;
                font-size: 11px;
            }
            .table-content td.text-center {
                text-align: center;
            }
            .table-content td.status-finished {
                font-weight: bold;
                color: #008000;
            }

            /* [STYLE BARU] Judul Alur untuk Rincian Komponen */
            .alur-title {
                font-size: 13px;
                font-weight: bold;
                color: #333;
                background-color: #f0f0f0;
                padding: 5px;
                margin-top: 15px; /* Jarak antar alur (tidak terlalu besar) */
                margin-bottom: 5px;
                border: 1px solid #ccc;
                border-bottom: 2px solid #004a99;
                page-break-after: avoid; /* Jangan pisah halaman setelah judul */
            }

            /* [STYLE BARU] Tabel Rincian Komponen (Sesuai image_a3d404.png) */
            .table-komponen {
                width: 100%;
                border-collapse: collapse;
                page-break-inside: auto; /* Biarkan tabel terpecah jika panjang */
            }
            .table-komponen th, .table-komponen td {
                border: 1px solid #888;
                padding: 5px;
                font-size: 10px;
            }
            .table-komponen th {
                background-color: #f9f9f9;
                font-weight: bold;
                text-align: center;
            }
            .table-komponen td.text-center {
                text-align: center;
            }
            
            tr {
                page-break-inside: avoid;
            }

        </style>
    </head>
    <body>
        <div class="header-title">LAPORAN PRODUKSI SELESAI</div>
        <div class="container">
    ';

    // 10. Render Bagian Header (Info Target)
    $html .= '
            <table class="table-header">
                <tr>
                    <th>Nama Barang</th>
                    <td>' . htmlspecialchars($header_info['nama_barang']) . ' (' . htmlspecialchars($header_info['kode_barang']) . ')</td>
                    <th>Nama Target</th>
                    <td>' . htmlspecialchars($header_info['nama_permintaan']) . '</td>
                </tr>
                <tr>
                    <th>Kategori</th>
                    <td>' . htmlspecialchars($header_info['nama_kategori'] ?? 'N/A') . '</td>
                    <th>No. SPK</th>
                    <td>' . htmlspecialchars($header_info['no_spk']) . '</td>
                </tr>
                <tr>
                    <th>Jumlah Unit</th>
                    <td>' . htmlspecialchars($header_info['jumlah_unit']) . ' Unit</td>
                    <th>Tanggal Mulai</th>
                    <td>' . format_tanggal_indonesia($header_info['created_at']) . '</td>
                </tr>
                <tr>
                    <th>Tanggal Selesai</th>
                    <td colspan="3">' . format_tanggal_indonesia($header_info['tanggal_selesai']) . '</td>
                </tr>
            </table>
    ';

    // 11. Render Tabel Ringkasan Alur (Tabel 1)
    $html .= '
            <table class="table-content">
                <thead>
                    <tr>
                        <th style="width:5%;">No</th>
                        <th>Nama Alur Produksi</th>
                        <th style="width:12%;">Status</th>
                        <th style="width:20%;">PIC</th>
                        <th style="width:20%;">Waktu Mulai</th>
                        <th style="width:20%;">Waktu Selesai</th>
                    </tr>
                </thead>
                <tbody>
    ';

    if (empty($alur_data)) {
        $html .= '<tr><td colspan="6" class="text-center">Tidak ada data alur produksi yang digunakan untuk target ini.</td></tr>';
    } else {
        $no = 1;
        foreach ($alur_data as $alur) {
            $html .= '
                <tr>
                    <td class="text-center">' . $no++ . '</td>
                    <td>' . htmlspecialchars($alur['nama_alur']) . '</td>
                    <td class="text-center status-finished">Selesai</td>
                    <td>' . htmlspecialchars($alur['pic'] ?? 'N/A') . '</td>
                    <td>' . format_tanggal_indonesia($alur['waktu_mulai']) . '</td>
                    <td>' . format_tanggal_indonesia($alur['waktu_selesai']) . '</td>
                </tr>
            ';
        }
    }
    $html .= '
                </tbody>
            </table>
    ';

    // 12. [BAGIAN BARU] Render Rincian Komponen per Alur
    if (!empty($alur_data)) {
        $html .= '<div style="page-break-before: auto;"></div>'; // Pindah halaman jika perlu, tapi tidak dipaksa
        
        foreach ($alur_data as $alur) {
            $id_alur = $alur['id_alur'];
            // Hanya tampilkan jika alur ini punya data komponen
            if (isset($komponen_by_alur[$id_alur]) && !empty($komponen_by_alur[$id_alur])) {
                
                $html .= '<div class="alur-title">Alur Produksi: ' . htmlspecialchars($alur['nama_alur']) . '</div>';
                
                $html .= '
                    <table class="table-komponen">
                        <thead>
                            <tr>
                                <th style="width:5%;">No</th>
                                <th>Nama Komponen</th>
                                <th style="width:12%;">Jml/Unit</th>
                                <th style="width:12%;">Kebutuhan</th>
                                <th style="width:12%;">Selesai</th>
                            </tr>
                        </thead>
                        <tbody>
                ';

                $no_komponen = 1;
                foreach ($komponen_by_alur[$id_alur] as $komponen) {
                    $html .= '
                        <tr>
                            <td class="text-center">' . $no_komponen++ . '</td>
                            <td>' . htmlspecialchars($komponen['nama_komponen']) . '</td>
                            <td class="text-center">' . number_format($komponen['jumlah_per_unit']) . '</td>
                            <td class="text-center">' . number_format($komponen['kebutuhan_total']) . '</td>
                            <td class="text-center">' . number_format($komponen['total_selesai']) . '</td>
                        </tr>
                    ';
                }
                
                $html .= '
                        </tbody>
                    </table>
                ';
            }
        }
    }

    $html .= '
        </div>
    </body>
    </html>
    ';

    // 13. Inisialisasi dan Render Dompdf
    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true); 
    $options->set('defaultFont', 'Helvetica');

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    $filename = "Laporan Selesai - " . preg_replace('/[^A-Za-z0-9\-]/', '', $header_info['nama_permintaan']) . ".pdf";
    $dompdf->stream($filename, ["Attachment" => true]);
    exit;

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
} catch (Exception $e) {
    die("General Error: " . $e->getMessage());
}
?>