<?php
session_start();
require_once '../../../../../../database/connection.php';

// Debug: Log API access
error_log("API create_admin.php: Accessed - REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD']);

// Check if admin user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    error_log("API create_admin.php: Unauthorized access");
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = getDBConnection();
    
    try {
        // Debug: Log POST data
        error_log("API create_admin.php: POST data: " . json_encode($_POST));
        
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        error_log("API create_admin.php: Parsed - Name: $name, Email: $email");
        
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
        $check_email = $conn->prepare("SELECT admin_id FROM admin WHERE admin_email = ?");
        $check_email->bind_param("s", $email);
        $check_email->execute();
        if ($check_email->get_result()->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Email already exists']);
            exit();
        }
        
        // Create admin - only use columns that exist in database
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $query = "INSERT INTO admin (admin_name, admin_email, admin_password) VALUES (?, ?, ?)";
        error_log("API create_admin.php: Query: " . $query);
        
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Failed to prepare statement: " . $conn->error);
        }
        
        $stmt->bind_param("sss", $name, $email, $hashed_password);
        
        if ($stmt->execute()) {
            error_log("API create_admin.php: Admin created successfully");
            echo json_encode(['success' => true, 'message' => 'Admin created successfully']);
        } else {
            throw new Exception("Error creating admin account: " . $stmt->error);
        }
        
    } catch (Exception $e) {
        error_log("API create_admin.php: Error - " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
