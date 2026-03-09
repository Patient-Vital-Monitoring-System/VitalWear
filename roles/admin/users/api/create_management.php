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
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Validation
        if (empty($name) || empty($email) || empty($password)) {
            echo json_encode(['success' => false, 'message' => 'Name, email, and password are required']);
            exit();
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Invalid email format']);
            exit();
        }
        
        if (strlen($password) < 6) {
            echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters long']);
            exit();
        }
        
        if ($password !== $confirm_password) {
            echo json_encode(['success' => false, 'message' => 'Passwords do not match']);
            exit();
        }
        
        // Check if email already exists
        $check_email = $conn->prepare("SELECT mgmt_id FROM management WHERE mgmt_email = ?");
        $check_email->bind_param("s", $email);
        $check_email->execute();
        if ($check_email->get_result()->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Email already exists']);
            exit();
        }
        
        // Create management - only use columns that exist in database
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO management (mgmt_name, mgmt_email, mgmt_password) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $name, $email, $hashed_password);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Management created successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error creating management account']);
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
