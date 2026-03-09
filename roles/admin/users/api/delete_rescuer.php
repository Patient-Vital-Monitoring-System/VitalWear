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
            echo json_encode(['success' => false, 'message' => 'Invalid rescuer ID']);
            exit();
        }
        
        // Check if rescuer exists
        $check_rescuer = $conn->prepare("SELECT resc_id FROM rescuer WHERE resc_id = ?");
        $check_rescuer->bind_param("i", $id);
        $check_rescuer->execute();
        if ($check_rescuer->get_result()->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Rescuer not found']);
            exit();
        }
        
        // Delete rescuer
        $delete_stmt = $conn->prepare("DELETE FROM rescuer WHERE resc_id = ?");
        $delete_stmt->bind_param("i", $id);
        
        if ($delete_stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Rescuer deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error deleting rescuer account']);
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
