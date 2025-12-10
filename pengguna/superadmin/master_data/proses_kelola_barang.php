<?php
// LANGKAH 1: Aktifkan Error Reporting untuk Debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
include '../../../system/database_connection.php';

// Pastikan hanya superadmin yang bisa mengakses
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'superadmin') {
    header("location: ../../auth/login.php");
    exit;
}

// Fungsi untuk menangani upload gambar (milik Anda, tidak diubah)
function uploadGambar($file) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    $target_dir = "../../../uploads/";
    if (!is_dir($target_dir)) {
        if (!mkdir($target_dir, 0777, true)) {
            return ['error' => 'Gagal membuat direktori uploads. Periksa izin folder.'];
        }
    } elseif (!is_writable($target_dir)) {
        return ['error' => 'Direktori uploads tidak dapat ditulisi. Periksa izin folder.'];
    }
    $extension = pathinfo($file["name"], PATHINFO_EXTENSION);
    $nama_file_unik = "barang_" . uniqid() . "." . $extension;
    $target_file = $target_dir . $nama_file_unik;
    if (move_uploaded_file($file["tmp_name"], $target_file)) {
        return $nama_file_unik;
    } else {
        return ['error' => 'Gagal memindahkan file yang di-upload.'];
    }
}

// Proses Tambah Barang
if (isset($_POST['tambah_barang'])) {
    $nama_barang = $_POST['nama_barang'] ?? '';
    $kode_barang = trim($_POST['kode_barang'] ?? '');
    
    // Cek Duplikat ID Barang
    $stmt_cek = $pdo->prepare("SELECT id_barang FROM master_barang WHERE kode_barang = ?");
    $stmt_cek->execute([$kode_barang]);
    if ($stmt_cek->rowCount() > 0) {
        header("Location: kelola_master_barang.php?status=error&message=" . urlencode("Gagal: ID Barang '$kode_barang' sudah digunakan."));
        exit;
    }
    // ### PERUBAHAN 1: Mengambil id_kategori dari dropdown ###
    $id_kategori = (int)($_POST['id_kategori'] ?? 0);
    $alurs = isset($_POST['alurs']) ? (array)$_POST['alurs'] : [];
    
    $hasil_upload = uploadGambar($_FILES['gambar']);
    
    if (is_array($hasil_upload) && isset($hasil_upload['error'])) {
        header("Location: kelola_master_barang.php?status=error&message=" . urlencode($hasil_upload['error']));
        exit;
    }
    
    $gambar = $hasil_upload;

    // Validasi sekarang memeriksa id_kategori
    if (empty($nama_barang) || empty($id_kategori)) {
        header("Location: kelola_master_barang.php?status=error&message=" . urlencode("Nama barang dan kategori wajib diisi."));
        exit;
    }

    try {
        $pdo->beginTransaction();

        // ### PERUBAHAN 2: Menyesuaikan Query INSERT untuk menyimpan id_kategori ###
        // --- KODE BARU (METODE BIND PARAM) ---
$stmt = $pdo->prepare("INSERT INTO master_barang (nama_barang, kode_barang, id_kategori, gambar) VALUES (:nama_barang, :kode_barang, :id_kategori, :gambar)");

$stmt->bindParam(':nama_barang', $nama_barang, PDO::PARAM_STR);
$stmt->bindParam(':kode_barang', $kode_barang);
$stmt->bindParam(':id_kategori', $id_kategori, PDO::PARAM_INT); // Mengikat secara eksplisit sebagai Angka
$stmt->bindParam(':gambar', $gambar, PDO::PARAM_STR);

$stmt->execute(); // Menjalankan query

$id_barang_baru = $pdo->lastInsertId();
        if (!empty($alurs)) {
            $stmt_alur = $pdo->prepare("INSERT INTO alur_barang (id_barang, id_alur) VALUES (?, ?)");
            foreach ($alurs as $id_alur) {
                $stmt_alur->execute([$id_barang_baru, $id_alur]);
            }
        }

        $pdo->commit();
        header("Location: kelola_master_barang.php?status=success&message=Barang berhasil ditambahkan.");
        exit;

    } catch (PDOException $e) {
        $pdo->rollBack();
        // ### PERUBAHAN 3: Penanganan error yang lebih baik ###
        if ($e->getCode() == '23000') {
             header("Location: kelola_master_barang.php?status=error&message=" . urlencode("Gagal: Kategori yang dipilih tidak valid."));
        } else {
             header("Location: kelola_master_barang.php?status=error&message=" . urlencode("Database Error: " . $e->getMessage()));
        }
        exit;
    }
}

// Proses Edit Barang
if (isset($_POST['edit_barang'])) {
    $id_barang = $_POST['id_barang'] ?? 0;
    $nama_barang = $_POST['nama_barang'] ?? '';
    $kode_barang = trim($_POST['kode_barang'] ?? '');

    // Cek Duplikat saat Edit (kecuali punya sendiri)
    $stmt_cek = $pdo->prepare("SELECT id_barang FROM master_barang WHERE kode_barang = ? AND id_barang != ?");
    $stmt_cek->execute([$kode_barang, $id_barang]);
    if ($stmt_cek->rowCount() > 0) {
        header("Location: kelola_master_barang.php?status=error&message=" . urlencode("Gagal: ID Barang '$kode_barang' sudah digunakan barang lain."));
        exit;
    }
    // ### PERUBAHAN 4: Mengambil id_kategori untuk proses update ###
    $id_kategori = (int)($_POST['id_kategori'] ?? 0);
    $alurs = isset($_POST['alurs']) ? (array)$_POST['alurs'] : [];

    // Validasi dasar
    if (empty($id_barang) || empty($nama_barang) || empty($id_kategori)) {
        header("Location: kelola_master_barang.php?status=error&message=" . urlencode("Data tidak lengkap."));
        exit;
    }

    try {
        $pdo->beginTransaction();

        if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] === UPLOAD_ERR_OK) {
            $hasil_upload = uploadGambar($_FILES['gambar']);

            if (is_array($hasil_upload) && isset($hasil_upload['error'])) {
                $pdo->rollBack();
                header("Location: kelola_master_barang.php?status=error&message=" . urlencode($hasil_upload['error']));
                exit;
            }

            // ### PERUBAHAN 5: Menyesuaikan Query UPDATE dengan gambar ###
            $stmt = $pdo->prepare("UPDATE master_barang SET nama_barang = ?, id_kategori = ?, gambar = ? WHERE id_barang = ?");
            $stmt->execute([$nama_barang, $id_kategori, $hasil_upload, $id_barang]);
        } else {
            // ### PERUBAHAN 6: Menyesuaikan Query UPDATE tanpa gambar ###
            $stmt = $pdo->prepare("UPDATE master_barang SET nama_barang = ?, kode_barang = ?, id_kategori = ? WHERE id_barang = ?");
$stmt->execute([$nama_barang, $kode_barang, $id_kategori, $id_barang]);
        }

        // Hapus alur lama dan masukkan yang baru (logika Anda sudah benar)
        $stmt_delete_alur = $pdo->prepare("DELETE FROM alur_barang WHERE id_barang = ?");
        $stmt_delete_alur->execute([$id_barang]);

        if (!empty($alurs)) {
            $stmt_insert_alur = $pdo->prepare("INSERT INTO alur_barang (id_barang, id_alur) VALUES (?, ?)");
            foreach ($alurs as $id_alur) {
                $stmt_insert_alur->execute([$id_barang, $id_alur]);
            }
        }
        
        $pdo->commit();
        header("Location: kelola_master_barang.php?status=success&message=Barang berhasil diperbarui.");
        exit;

    } catch (PDOException $e) {
        $pdo->rollBack();
        header("Location: kelola_master_barang.php?status=error&message=" . urlencode("Database Error: " . $e->getMessage()));
        exit;
    }
}

// Logika Hapus Barang (milik Anda, tidak perlu diubah)
if (isset($_POST['hapus_barang'])) {
    $id_barang = $_POST['id_barang'] ?? 0;

    if (empty($id_barang)) {
        header("Location: kelola_master_barang.php?status=error&message=ID Barang tidak valid.");
        exit;
    }

    try {
        $pdo->beginTransaction();
        
        $stmt_delete_alur = $pdo->prepare("DELETE FROM alur_barang WHERE id_barang = ?");
        $stmt_delete_alur->execute([$id_barang]);
        
        $stmt_delete_barang = $pdo->prepare("DELETE FROM master_barang WHERE id_barang = ?");
        $stmt_delete_barang->execute([$id_barang]);

        $pdo->commit();
        header("Location: kelola_master_barang.php?status=success&message=Barang berhasil dihapus.");
        exit;

    } catch (PDOException $e) {
        $pdo->rollBack();
        header("Location: kelola_master_barang.php?status=error&message=" . urlencode("Database Error: " . $e->getMessage()));
        exit;
    }
}

// Fallback jika tidak ada aksi yang cocok
header("Location: kelola_master_barang.php");
exit;