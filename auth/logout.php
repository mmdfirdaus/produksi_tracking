<?php
// Selalu mulai session
session_start();

// Hapus semua data yang tersimpan di session
$_SESSION = array();

// Hancurkan session-nya
session_destroy();

// Arahkan pengguna kembali ke halaman login
header("location: login.php");
exit;
?>