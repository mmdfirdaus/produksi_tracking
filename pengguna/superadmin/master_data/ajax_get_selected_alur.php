<?php
session_start();
include '../../../system/database_connection.php';

// Pastikan hanya superadmin yang bisa akses
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'superadmin') {
    http_response_code(403);
    echo json_encode(['error' => 'Akses ditolak']);
    exit;
}

$response = [];
if (isset($_GET['id_barang'])) {
    $id_barang = filter_input(INPUT_GET, 'id_barang', FILTER_VALIDATE_INT);
    if ($id_barang) {
        try {
            $stmt = $pdo->prepare("SELECT id_alur FROM alur_barang WHERE id_barang = ?");
            $stmt->execute([$id_barang]);
            // Ambil hanya kolom id_alur ke dalam array
            $response = $stmt->fetchAll(PDO::FETCH_COLUMN, 0); 
        } catch (PDOException $e) {
            http_response_code(500);
            $response = ['error' => 'Database error'];
        }
    }
}

header('Content-Type: application/json');
echo json_encode($response);
?>