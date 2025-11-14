<?php
session_start();
include '../../../system/database_connection.php'; // Sesuaikan path jika perlu

// Validasi sesi superadmin
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'superadmin') {
    header("location: ../../auth/login.php");
    exit;
}

// Ambil aksi dari POST atau GET untuk fleksibilitas
$action = $_REQUEST['action'] ?? '';
$id_target = isset($_REQUEST['id_target']) ? (int)$_REQUEST['id_target'] : 0;
$id_barang = isset($_REQUEST['id_barang']) ? (int)$_REQUEST['id_barang'] : 0;

// === AKSI MENONAKTIFKAN TARGET (dari kode lama Anda) ===
if ($action == 'nonaktifkan' && $_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Ambil alasan dari POST, pastikan tidak kosong
    $alasan = !empty(trim($_POST['alasan_nonaktif'])) ? trim($_POST['alasan_nonaktif']) : null;

    if ($id_target > 0 && $alasan !== null) {
        try {
            // Query UPDATE untuk menonaktifkan dan menyimpan alasan
            $stmt = $pdo->prepare("
                UPDATE production_targets 
                SET is_active = 0, alasan_nonaktif = :alasan 
                WHERE id_target = :id_target
            ");
            
            $stmt->execute([
                ':alasan' => $alasan,
                ':id_target' => $id_target
            ]);
            
            $_SESSION['flash_message'] = [
                'status' => 'success',
                'message' => 'Target berhasil dinonaktifkan dan diarsipkan.'
            ];

        } catch (PDOException $e) {
            $_SESSION['flash_message'] = [
                'status' => 'danger',
                'message' => 'Gagal menonaktifkan target: ' . $e->getMessage()
            ];
        }
    } else {
        $_SESSION['flash_message'] = [
            'status' => 'danger',
            'message' => 'Data tidak valid atau alasan wajib diisi.'
        ];
    }

    // Redirect kembali ke halaman detail barang setelah menonaktifkan
    header("Location: detail_barang.php?id=" . $id_barang);
    exit;

// === AKSI MENGAKTIFKAN KEMBALI TARGET (dari kode baru) ===
} elseif ($action == 'aktifkan' && $id_target > 0 && $id_barang > 0) {
    
    try {
        // Query UPDATE untuk mengaktifkan kembali (set is_active = 1)
        // Kita juga bisa membersihkan alasan nonaktif saat diaktifkan kembali
        $stmt = $pdo->prepare("
            UPDATE production_targets 
            SET is_active = 1, alasan_nonaktif = NULL 
            WHERE id_target = ?
        ");
        $stmt->execute([$id_target]);
        
        $_SESSION['flash_message'] = [
            'status' => 'success',
            'message' => 'Target berhasil diaktifkan kembali.'
        ];

    } catch (PDOException $e) {
        $_SESSION['flash_message'] = [
            'status' => 'danger',
            'message' => 'Gagal mengaktifkan target: ' . $e->getMessage()
        ];
    }
    
    // Redirect kembali ke halaman arsip, dengan menyertakan id_barang
    header("Location: arsip_target.php?id_barang=" . $id_barang);
    exit;

// === AKSI MENGHAPUS PERMANEN TARGET ===
} elseif ($action == 'hapus_permanen' && $id_target > 0 && $id_barang > 0) {
    
    try {
        // Query DELETE untuk menghapus target secara permanen
        $stmt = $pdo->prepare("DELETE FROM production_targets WHERE id_target = ?");
        $stmt->execute([$id_target]);
        
        $_SESSION['flash_message'] = [
            'status' => 'success',
            'message' => 'Target berhasil dihapus secara permanen.'
        ];

    } catch (PDOException $e) {
        $_SESSION['flash_message'] = [
            'status' => 'danger',
            'message' => 'Gagal menghapus target: ' . $e->getMessage()
        ];
    }
    
    // Redirect kembali ke halaman arsip setelah menghapus
    header("Location: arsip_target.php?id_barang=" . $id_barang);
    exit;

// === JIKA AKSI TIDAK DIKENALI ===
} else {
    // Jika aksi tidak valid atau parameter tidak lengkap, redirect ke halaman utama
    $_SESSION['flash_message'] = [
        'status' => 'danger',
        'message' => 'Aksi tidak diketahui atau parameter tidak lengkap.'
    ];
    header("Location: ../master_data/kelola_master_barang.php");
    exit;
}
?>