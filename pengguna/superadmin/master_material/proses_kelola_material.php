<?php
session_start();
include '../../../system/database_connection.php';

// Pastikan koneksi PDO selalu melaporkan error dalam bentuk exception
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Cek hak akses
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'superadmin') {
    header("location: ../../auth/login.php");
    exit;
}

// ==========================================================
// PROSES TAMBAH KOMPONEN (DENGAN LOGIKA RE-AKTIVASI)
// ==========================================================
if (isset($_POST['tambah_komponen'])) {
    $kode_komponen = trim($_POST['kode_komponen']) ?: NULL;
    $nama_komponen = trim($_POST['nama_komponen']);

    if (empty($nama_komponen)) {
        header("Location: kelola_material.php?status=error&message=" . urlencode("Nama komponen tidak boleh kosong."));
        exit;
    }

    try {
        // Langkah 1: Cek apakah komponen dengan nama yang sama sudah ada di database
        $check_stmt = $pdo->prepare("SELECT id_komponen, is_active FROM master_komponen WHERE nama_komponen = ?");
        $check_stmt->execute([$nama_komponen]);
        $existing_component = $check_stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing_component) {
            // JIKA NAMA KOMPONEN SUDAH ADA
            if ($existing_component['is_active'] == 0) {
                // Jika tidak aktif (is_active = 0), maka UPDATE statusnya menjadi 1 (aktifkan kembali)
                $reactivate_stmt = $pdo->prepare("UPDATE master_komponen SET is_active = 1, kode_komponen = ? WHERE id_komponen = ?");
                $reactivate_stmt->execute([$kode_komponen, $existing_component['id_komponen']]);
                header("Location: kelola_material.php?status=success&message=" . urlencode("Komponen '" . htmlspecialchars($nama_komponen) . "' berhasil diaktifkan kembali."));
            } else {
                // Jika sudah ada dan aktif, berikan pesan error
                header("Location: kelola_material.php?status=error&message=" . urlencode("Nama komponen '" . htmlspecialchars($nama_komponen) . "' sudah ada dan aktif."));
            }
        } else {
            // JIKA NAMA KOMPONEN BENAR-BENAR BARU, lakukan INSERT
            $stmt = $pdo->prepare("INSERT INTO master_komponen (kode_komponen, nama_komponen) VALUES (?, ?)");
            $stmt->execute([$kode_komponen, $nama_komponen]);
            header("Location: kelola_material.php?status=success&message=" . urlencode("Komponen baru '" . htmlspecialchars($nama_komponen) . "' berhasil ditambahkan."));
        }
    } catch (PDOException $e) {
        // Tangkap jika ada error lain dari database
        header("Location: kelola_material.php?status=error&message=" . urlencode("Database Error: " . $e->getMessage()));
    }
    exit;
}


// ==========================================================
// PROSES EDIT KOMPONEN (VERSI FINAL DENGAN TRANSAKSI EKSPLISIT)
// ==========================================================
if (isset($_POST['edit_komponen'])) {
    // Ambil data dari form
    $id_komponen = $_POST['id_komponen'] ?? null;
    $kode_komponen = trim($_POST['kode_komponen']) ?: NULL;
    $nama_komponen = trim($_POST['nama_komponen']);

    // Validasi dasar
    if (empty($id_komponen) || empty($nama_komponen)) {
        header("Location: kelola_material.php?status=error&message=" . urlencode("Data tidak lengkap. Gagal memproses permintaan."));
        exit;
    }

    try {
        // Cek duplikasi nama komponen
        $check_stmt = $pdo->prepare(
            "SELECT id_komponen FROM master_komponen WHERE nama_komponen = :nama AND id_komponen != :id AND is_active = 1"
        );
        $check_stmt->execute([':nama' => $nama_komponen, ':id' => $id_komponen]);
        
        if ($check_stmt->fetch()) {
            header("Location: kelola_material.php?status=error&message=" . urlencode("Nama komponen '" . htmlspecialchars($nama_komponen) . "' sudah digunakan."));
            exit;
        }

        // ==> PERBAIKAN DIMULAI DI SINI <==

        // 1. Mulai transaksi secara manual
        $pdo->beginTransaction();

        // 2. Siapkan dan jalankan perintah UPDATE
        $stmt = $pdo->prepare(
            "UPDATE master_komponen SET kode_komponen = :kode, nama_komponen = :nama WHERE id_komponen = :id"
        );
        
        $stmt->execute([
            ':kode' => $kode_komponen,
            ':nama' => $nama_komponen,
            ':id'   => $id_komponen
        ]);
        
        // 3. Simpan perubahan secara permanen ke database
        $pdo->commit();

        // 4. Redirect dengan pesan sukses
        header("Location: kelola_material.php?status=success&message=Data komponen berhasil diperbarui.");
        
    } catch (PDOException $e) {
        // Jika terjadi error di langkah manapun, batalkan semua perubahan
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        // Kirim pesan error
        header("Location: kelola_material.php?status=error&message=" . urlencode("Database Error: " . $e->getMessage()));
    }
    exit;
}


// ==========================================================
// PROSES HAPUS KOMPONEN (SOFT DELETE)
// ==========================================================
if (isset($_POST['hapus_komponen'])) {
    $id_komponen = $_POST['id_komponen'];

    if (!empty($id_komponen)) {
        try {
            // Cek keterkaitan dengan tabel lain
            $cek_stmt = $pdo->prepare("SELECT COUNT(*) FROM target_material WHERE id_komponen = ?");
            $cek_stmt->execute([$id_komponen]);
            if ($cek_stmt->fetchColumn() > 0) {
                header("Location: kelola_material.php?status=error&message=Gagal! Komponen ini sedang digunakan dalam target produksi.");
            } else {
                // Lakukan soft delete
                $stmt = $pdo->prepare("UPDATE master_komponen SET is_active = 0 WHERE id_komponen = ?");
                $stmt->execute([$id_komponen]);
                header("Location: kelola_material.php?status=success&message=Komponen berhasil dihapus (dinonaktifkan).");
            }
        } catch (PDOException $e) {
            header("Location: kelola_material.php?status=error&message=" . urlencode("Database Error saat menghapus: " . $e->getMessage()));
        }
    }
    exit;
}

// Redirect default jika tidak ada aksi yang cocok
header("Location: kelola_material.php");
exit;
?>