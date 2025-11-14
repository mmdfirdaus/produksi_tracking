<?php
session_start();
header('Content-Type: application/json');
include '../../system/database_connection.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'superadmin') {
    echo json_encode(['error' => 'Akses ditolak']);
    exit;
}

$period = isset($_GET['period']) ? $_GET['period'] : 'all';
$response = [];

try {
    $where_clause_created = '';
    $where_clause_completed = '';

    switch ($period) {
        case '7days':
            $where_clause_created = "WHERE created_at >= CURDATE() - INTERVAL 7 DAY";
            $where_clause_completed = "WHERE tanggal_selesai >= CURDATE() - INTERVAL 7 DAY";
            break;
        case '30days':
            $where_clause_created = "WHERE created_at >= CURDATE() - INTERVAL 30 DAY";
            $where_clause_completed = "WHERE tanggal_selesai >= CURDATE() - INTERVAL 30 DAY";
            break;
        case '3months':
            $where_clause_created = "WHERE created_at >= CURDATE() - INTERVAL 3 MONTH";
            $where_clause_completed = "WHERE tanggal_selesai >= CURDATE() - INTERVAL 3 MONTH";
            break;
        case 'all':
        default:
            // No WHERE clause for 'all'
            break;
    }

    // 1. Kartu Statistik Dinamis
    $stats = [];
    $stats['priority_targets'] = $pdo->query("SELECT COUNT(*) FROM production_targets WHERE is_priority = 1 AND status = 'ongoing' $where_clause_created")->fetchColumn();
    $stats['completed_reports'] = $pdo->query("SELECT COUNT(*) FROM production_targets WHERE status = 'Selesai' $where_clause_completed")->fetchColumn();
    $response['stats'] = $stats;
    
    // 2. Data Grafik Dinamis
    $chart_stmt = $pdo->query("
        SELECT status, COUNT(*) as count 
        FROM production_targets 
        $where_clause_created
        GROUP BY status
    ");
    $status_data = $chart_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $chart_labels = [];
    $chart_values = [];
    foreach ($status_data as $data) {
        $chart_labels[] = ucfirst($data['status']);
        $chart_values[] = $data['count'];
    }
    $response['chart'] = [
        'labels' => $chart_labels,
        'values' => $chart_values
    ];

    echo json_encode($response);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => "Database error: " . $e->getMessage()]);
}
exit;
?>