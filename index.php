<?php
// Selalu mulai session di awal
session_start();

// 1. Periksa apakah pengguna sudah login dan sesinya valid.
if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    
    // 2. Jika sudah login, periksa perannya (role).
    $role = $_SESSION["role"];
    
    // 3. Arahkan pengguna berdasarkan perannya.
    switch ($role) {
        case 'superadmin':
            // DIUBAH: Mengarahkan ke master_barang.php yang sudah ada
            header("location: pengguna/superadmin/dashboard.php");
            break;
        case 'admin':
            // Pastikan file dashboard.php untuk admin sudah ada
            header("location: pengguna/admin/dashboard.php");
            break;
        case 'user':
             // Pastikan file dashboard.php untuk user sudah ada
            header("location: pengguna/user/dashboard.php");
            break;
        default:
            // Jika peran tidak dikenali, arahkan ke halaman login untuk keamanan.
            header("location: auth/login.php");
            break;
    }
    exit();

} else {
    // 4. Jika pengguna belum login, paksa mereka ke halaman login.
    header("location: auth/login.php?error=belum_login");
    exit();
}
?>