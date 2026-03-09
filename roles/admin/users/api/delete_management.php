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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = getDBConnection();
    
    try {
        $id = (int)$_POST['id'] ?? 0;
        
        // Validation
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid management ID']);
            exit();
        }
        
        // Check if management exists
        $check_management = $conn->prepare("SELECT mgmt_id FROM management WHERE mgmt_id = ?");
        $check_management->bind_param("i", $id);
        $check_management->execute();
        if ($check_management->get_result()->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Management not found']);
            exit();
        }
        
        // Delete management
        $delete_stmt = $conn->prepare("DELETE FROM management WHERE mgmt_id = ?");
        $delete_stmt->bind_param("i", $id);
        
        if ($delete_stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Management deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error deleting management account']);
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
