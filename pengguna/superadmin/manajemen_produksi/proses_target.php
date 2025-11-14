<?php
session_start();
include '../../../system/database_connection.php';

// Pastikan hanya superadmin yang bisa mengakses
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'superadmin') {
    header("location: ../../auth/login.php");
    exit;
}

// =================================================================
// 1. PROSES TAMBAH TARGET BARU (TELAH DIPERBARUI DENGAN LOGIKA BARU)
// =================================================================
if (isset($_POST['add_target'])) {
    // Ambil data dari form
    $id_barang = filter_input(INPUT_POST, 'id_barang', FILTER_VALIDATE_INT);
    $nama_permintaan = htmlspecialchars($_POST['nama_permintaan']);
    $jumlah_unit = filter_input(INPUT_POST, 'jumlah_unit', FILTER_VALIDATE_INT);
    $status_prioritas = $_POST['prioritas']; // "Prioritas" atau "Normal"
    
    // Validasi dasar
    if (!$id_barang || !$nama_permintaan || !$jumlah_unit) {
        $_SESSION['flash_message'] = [
            'status' => 'danger',
            'message' => 'Semua field wajib diisi dengan benar.'
        ];
        header("Location: detail_barang.php?id=" . $id_barang);
        exit;
    }

    // ========== LOGIKA BARU UNTUK PRIORITAS ==========
    $is_priority = 0;
    $priority_deadline = null;

    if ($status_prioritas === 'Prioritas') {
        $is_priority = 1;
        // Ambil dan validasi tanggal tenggat dari input 'priority_deadline'
        if (!empty($_POST['priority_deadline'])) {
            $priority_deadline = $_POST['priority_deadline'];
        } else {
            // Jika memilih prioritas tapi tidak mengisi tanggal, beri pesan error
            $_SESSION['flash_message'] = [
                'status' => 'danger',
                'message' => 'Tanggal tenggat wajib diisi untuk target prioritas.'
            ];
            header("Location: detail_barang.php?id=" . $id_barang);
            exit;
        }
    }
    // ===============================================

    try {
        // Menggunakan transaksi untuk memastikan integritas data
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("
            INSERT INTO production_targets 
            (id_barang, nama_permintaan, jumlah_unit, status, is_active, created_at, is_priority, priority_deadline, prioritas) 
            VALUES 
            (:id_barang, :nama_permintaan, :jumlah_unit, 'ongoing', 1, NOW(), :is_priority, :priority_deadline, :prioritas_text)
        ");

        $stmt->execute([
            ':id_barang' => $id_barang,
            ':nama_permintaan' => $nama_permintaan,
            ':jumlah_unit' => $jumlah_unit,
            ':is_priority' => $is_priority,              // Menyimpan 1 atau 0
            ':priority_deadline' => $priority_deadline,    // Menyimpan tanggal atau NULL
            ':prioritas_text' => $status_prioritas         // Tetap menyimpan teks "Prioritas" atau "Normal"
        ]);
        
        $pdo->commit();

        $_SESSION['flash_message'] = [
            'status' => 'success',
            'message' => 'Target produksi baru berhasil ditambahkan.'
        ];

    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['flash_message'] = [
            'status' => 'danger',
            'message' => 'Gagal menambahkan target: ' . $e->getMessage()
        ];
    }

    // Redirect kembali ke halaman detail barang
    header("Location: detail_barang.php?id=" . $id_barang);
    exit;
}

// =================================================================
// 2. PROSES BATALKAN TARGET (DIPERTAHANKAN DARI KODE LAMA)
// =================================================================
if (isset($_GET['cancel_target'])) {
    $id_target = filter_input(INPUT_GET, 'cancel_target', FILTER_SANITIZE_NUMBER_INT);
    $id_barang = filter_input(INPUT_GET, 'id_barang', FILTER_SANITIZE_NUMBER_INT);
    $redirect_url = "detail_barang.php?id=" . $id_barang;
    if ($id_target && $id_barang) {
        try {
            $stmt = $pdo->prepare("UPDATE production_targets SET status = 'cancelled' WHERE id_target = :id_target");
            $stmt->bindParam(':id_target', $id_target, PDO::PARAM_INT);
            if ($stmt->execute()) {
                $_SESSION['flash_message'] = ['status' => 'success', 'message' => 'Target berhasil dibatalkan.'];
            } else {
                $_SESSION['flash_message'] = ['status' => 'danger', 'message' => 'Gagal membatalkan target.'];
            }
        } catch (PDOException $e) {
            $_SESSION['flash_message'] = ['status' => 'danger', 'message' => 'Database error: ' . $e->getMessage()];
        }
        header("Location: " . $redirect_url);
    } else {
        header("Location: ../master_data/kelola_master_barang.php");
    }
    exit;
}

// =================================================================
// 3. PROSES SELESAIKAN TARGET (DIPERTAHANKAN DARI KODE LAMA)
// =================================================================
if (isset($_GET['complete_target'])) {
    $id_target = filter_input(INPUT_GET, 'complete_target', FILTER_SANITIZE_NUMBER_INT);
    $id_barang = filter_input(INPUT_GET, 'id_barang', FILTER_SANITIZE_NUMBER_INT); // Tambahkan ini agar tahu redirect kemana

    if ($id_target && $id_barang) {
        try {
            $stmt = $pdo->prepare("UPDATE production_targets SET status = 'completed', is_active = 0 WHERE id_target = :id_target");
            $stmt->bindParam(':id_target', $id_target, PDO::PARAM_INT);

            if ($stmt->execute()) {
                $_SESSION['flash_message'] = ['status' => 'success', 'message' => 'Target berhasil diselesaikan dan diarsipkan.'];
            } else {
                $_SESSION['flash_message'] = ['status' => 'danger', 'message' => 'Gagal menyelesaikan target.'];
            }
        } catch (PDOException $e) {
            $_SESSION['flash_message'] = ['status' => 'danger', 'message' => 'Database error: ' . $e->getMessage()];
        }
        // Kembali ke halaman detail barang agar user melihat perubahannya
        header("Location: detail_barang.php?id=" . $id_barang); 
    } else {
        header("Location: ../master_data/kelola_master_barang.php");
    }
    exit;
}

// =================================================================
// 4. PENGALIHAN DEFAULT (JIKA TIDAK ADA AKSI YANG COCOK)
// =================================================================
header("Location: ../master_data/kelola_master_barang.php");
exit;
?>