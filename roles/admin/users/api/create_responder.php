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
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($name) || empty($email) || empty($password)) {
        jsonResponse(false, 'Name, email, and password are required');
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(false, 'Invalid email format');
    }
    
    if (strlen($password) < 6) {
        jsonResponse(false, 'Password must be at least 6 characters long');
    }
    
    if ($password !== $confirm_password) {
        jsonResponse(false, 'Passwords do not match');
    }
    
    // Check if email already exists
    $check_email = $conn->prepare("SELECT resp_id FROM responder WHERE resp_email = ?");
    if (!$check_email) {
        jsonResponse(false, 'Database query failed');
    }
    
    $check_email->bind_param("s", $email);
    $check_email->execute();
    if ($check_email->get_result()->num_rows > 0) {
        jsonResponse(false, 'Email already exists');
    }
    
    // Create responder
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO responder (resp_name, resp_email, resp_contact, resp_password, status, created_at) VALUES (?, ?, ?, ?, 'active', NOW())");
    if (!$stmt) {
        jsonResponse(false, 'Failed to prepare insert statement');
    }
    
    $stmt->bind_param("ssss", $name, $email, $contact, $hashed_password);
    
    if ($stmt->execute()) {
        jsonResponse(true, 'Responder created successfully');
    } else {
        jsonResponse(false, 'Error creating responder account');
    }
    
} catch (Exception $e) {
    jsonResponse(false, 'Database error: ' . $e->getMessage());
}
?>
