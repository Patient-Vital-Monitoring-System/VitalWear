<?php
// Prevent any HTML output
error_reporting(0);
ini_set('display_errors', 0);

session_start();
require_once '../../../database/connection.php';

// Set JSON header first
header('Content-Type: application/json');

// Function to output JSON and exit
function jsonResponse($success, $message) {
    echo json_encode(['success' => $success, 'message' => $message]);
    exit();
}

// Check if admin user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    jsonResponse(false, 'Unauthorized access');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Invalid request method');
}

$conn = getDBConnection();

if (!$conn) {
    jsonResponse(false, 'Database connection failed');
}

try {
    $id = (int)$_POST['id'] ?? 0;
    
    // Validation
    if ($id <= 0) {
        jsonResponse(false, 'Invalid responder ID');
    }
    
    // Check if responder exists
    $check_responder = $conn->prepare("SELECT resp_id FROM responder WHERE resp_id = ?");
    if (!$check_responder) {
        jsonResponse(false, 'Database query failed');
    }
    
    $check_responder->bind_param("i", $id);
    $check_responder->execute();
    if ($check_responder->get_result()->num_rows === 0) {
        jsonResponse(false, 'Responder not found');
    }
    
    // Delete responder
    $delete_stmt = $conn->prepare("DELETE FROM responder WHERE resp_id = ?");
    if (!$delete_stmt) {
        jsonResponse(false, 'Failed to prepare delete statement');
    }
    
    $delete_stmt->bind_param("i", $id);
    
    if ($delete_stmt->execute()) {
        jsonResponse(true, 'Responder deleted successfully');
    } else {
        jsonResponse(false, 'Error deleting responder account');
    }
    
} catch (Exception $e) {
    jsonResponse(false, 'Database error: ' . $e->getMessage());
}
?>
