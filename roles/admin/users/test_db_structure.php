<?php
session_start();
require_once '../../../database/connection.php';

// Check if admin user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

header('Content-Type: application/json');

try {
    $conn = getDBConnection();
    
    // Test table structures
    $tables = ['responder', 'rescuer', 'management', 'admin'];
    $table_info = [];
    
    foreach ($tables as $table) {
        $columns_result = $conn->query("SHOW COLUMNS FROM $table");
        $columns = [];
        while ($row = $columns_result->fetch_assoc()) {
            $columns[] = $row['Field'];
        }
        $table_info[$table] = $columns;
    }
    
    echo json_encode([
        'success' => true, 
        'message' => 'Database structure retrieved successfully',
        'tables' => $table_info
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
