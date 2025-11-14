<?php
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'superadmin') {
    header("location: ../../auth/login.php");
    exit;
}

$template_dir = '../../../templates/excel/';
$template_file = 'template_impor_kustom.xlsx';
$template_path = $template_dir . $template_file;
$redirect_url = 'pengaturan_impor.php';

// Proses Upload Template
if (isset($_POST['upload_template'])) {
    if (isset($_FILES['template_excel']) && $_FILES['template_excel']['error'] === UPLOAD_ERR_OK) {
        // Pastikan direktori ada
        if (!is_dir($template_dir)) {
            mkdir($template_dir, 0777, true);
        }

        // Hapus template lama jika ada
        if (file_exists($template_path)) {
            unlink($template_path);
        }

        // Pindahkan file baru
        if (move_uploaded_file($_FILES['template_excel']['tmp_name'], $template_path)) {
            header("Location: $redirect_url?status=success&message=" . urlencode("Template kustom berhasil diunggah dan diaktifkan."));
        } else {
            header("Location: $redirect_url?status=error&message=" . urlencode("Gagal menyimpan file template."));
        }
    } else {
        header("Location: $redirect_url?status=error&message=" . urlencode("Terjadi kesalahan saat mengunggah file."));
    }
    exit;
}

// Proses Hapus Template
if (isset($_GET['hapus_template'])) {
    if (file_exists($template_path)) {
        unlink($template_path);
        header("Location: $redirect_url?status=success&message=" . urlencode("Template kustom berhasil dihapus. Sistem kini menggunakan template standar."));
    } else {
        header("Location: $redirect_url?status=warning&message=" . urlencode("Tidak ada template kustom yang ditemukan untuk dihapus."));
    }
    exit;
}

// Redirect jika diakses tanpa aksi
header("Location: $redirect_url");
exit;
?>