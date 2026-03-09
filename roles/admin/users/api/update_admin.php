<?php
session_start();
require_once '../../../../database/connection.php';

// Debug: Log API access
error_log("API update_admin.php: Accessed - REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD']);

// Check if admin user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    error_log("API update_admin.php: Unauthorized access");
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

header('Content-Type: application/json');

$conn = getDBConnection();

try {
    // Debug: Log POST data
    error_log("API update_admin.php: POST data: " . json_encode($_POST));
    
    // Get POST data
    $id = (int)$_POST['id'] ?? 0;
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    $password = $_POST['password'] ?? '';
    
    error_log("API update_admin.php: Parsed - ID: $id, Name: $name, Email: $email");
    
    // Validation
    if (empty($id) || $id <= 0) {
        throw new Exception("Invalid admin ID");
    }
    
    if (empty($name) || empty($email)) {
        throw new Exception("Name and email are required fields");
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception("Please enter a valid email address");
    }
    
    if (!empty($password) && strlen($password) < 6) {
        throw new Exception("Password must be at least 6 characters long");
    }
    
    // Check if admin exists
    $check_stmt = $conn->prepare("SELECT admin_id FROM admin WHERE admin_id = ?");
    $check_stmt->bind_param("i", $id);
    $check_stmt->execute();
    if ($check_stmt->get_result()->num_rows === 0) {
        throw new Exception("Admin not found");
    }
    
    // Check if email already exists (excluding current user)
    $email_check = $conn->prepare("SELECT admin_id FROM admin WHERE admin_email = ? AND admin_id != ?");
    $email_check->bind_param("si", $email, $id);
    $email_check->execute();
    if ($email_check->get_result()->num_rows > 0) {
        throw new Exception("An account with this email already exists");
    }
    
    // Build update query dynamically
    $update_fields = ["admin_name = ?", "admin_email = ?"];
    $update_values = [$name, $email];
    $bind_types = "ss";
    
    // Note: admin_contact field doesn't exist in database, so we skip it
    // Add password field if provided
    if (!empty($password)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $update_fields[] = "admin_password = ?";
        $update_values[] = $hashed_password;
        $bind_types .= "s";
    }
    
    // Add WHERE clause
    $update_values[] = $id;
    $bind_types .= "i";
    
    // Execute update
    $update_query = "UPDATE admin SET " . implode(", ", $update_fields) . " WHERE admin_id = ?";
    error_log("API update_admin.php: Query: " . $update_query);
    
    $update_stmt = $conn->prepare($update_query);
    
    if (!$update_stmt) {
        throw new Exception("Failed to prepare update query: " . $conn->error);
    }
    
    $update_stmt->bind_param($bind_types, ...$update_values);
    
    if (!$update_stmt->execute()) {
        throw new Exception("Error updating admin account: " . $update_stmt->error);
    }
    
    error_log("API update_admin.php: Update successful");
    echo json_encode([
        'success' => true, 
        'message' => "Admin account updated successfully!"
    ]);
    
} catch (Exception $e) {
    error_log("API update_admin.php: Error - " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}
?>
