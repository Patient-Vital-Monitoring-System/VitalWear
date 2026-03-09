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
            echo json_encode(['success' => false, 'message' => 'Invalid admin ID']);
            exit();
        }
        
        // Check if admin exists
        $check_admin = $conn->prepare("SELECT admin_id FROM admin WHERE admin_id = ?");
        $check_admin->bind_param("i", $id);
        $check_admin->execute();
        if ($check_admin->get_result()->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Admin not found']);
            exit();
        }
        
        // Delete admin
        $delete_stmt = $conn->prepare("DELETE FROM admin WHERE admin_id = ?");
        $delete_stmt->bind_param("i", $id);
        
        if ($delete_stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Admin deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error deleting admin account']);
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
