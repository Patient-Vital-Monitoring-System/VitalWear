<?php
// API endpoint for deleting a user

header('Content-Type: application/json');
require_once '../../database/connection.php';

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Get input data - try both JSON and form data
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    $input = $_POST;
}

$userId = isset($input['id']) ? intval($input['id']) : 0;
$role = isset($input['role']) ? trim($input['role']) : '';

// Debug log
error_log("Delete request - ID: $userId, Role: $role");

// Validate input
if ($userId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid user ID', 'received' => $input]);
    exit;
}

// Try to delete the user
try {
    $result = false;
    
    // If role is provided, delete from specific table
    if (!empty($role)) {
        switch ($role) {
            case 'staff':
                $stmt = $conn->prepare("DELETE FROM management WHERE mgmt_id = ?");
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $result = $stmt->affected_rows > 0;
                break;
                
            case 'admin':
                $stmt = $conn->prepare("DELETE FROM admin WHERE admin_id = ?");
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $result = $stmt->affected_rows > 0;
                break;
                
            case 'responder':
                $stmt = $conn->prepare("DELETE FROM responder WHERE resp_id = ?");
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $result = $stmt->affected_rows > 0;
                break;
                
            case 'rescuer':
                $stmt = $conn->prepare("DELETE FROM rescuer WHERE resc_id = ?");
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $result = $stmt->affected_rows > 0;
                break;
        }
    } else {
        // Try each table
        $tables = [
            ['table' => 'management', 'id' => 'mgmt_id'],
            ['table' => 'admin', 'id' => 'admin_id'],
            ['table' => 'responder', 'id' => 'resp_id'],
            ['table' => 'rescuer', 'id' => 'resc_id']
        ];
        
        foreach ($tables as $t) {
            $stmt = $conn->prepare("DELETE FROM {$t['table']} WHERE {$t['id']} = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            if ($stmt->affected_rows > 0) {
                $result = true;
                break;
            }
        }
    }
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'error' => 'User not found or already deleted']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to delete user: ' . $e->getMessage()]);
}
?>
