<?php
session_start();
require_once '../../../../database/connection.php';

// Debug: Log API access
error_log("API update_rescuer.php: Accessed - REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD']);

// Check if admin user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    error_log("API update_rescuer.php: Unauthorized access");
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

header('Content-Type: application/json');

$conn = getDBConnection();

try {
    // Debug: Log POST data
    error_log("API update_rescuer.php: POST data: " . json_encode($_POST));
    
    // Get POST data
    $id = (int)$_POST['id'] ?? 0;
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    $password = $_POST['password'] ?? '';
    
    error_log("API update_rescuer.php: Parsed - ID: $id, Name: $name, Email: $email");
    
    // Validation
    if (empty($id) || $id <= 0) {
        throw new Exception("Invalid rescuer ID");
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
    
    // Check if rescuer exists
    $check_stmt = $conn->prepare("SELECT resc_id FROM rescuer WHERE resc_id = ?");
    $check_stmt->bind_param("i", $id);
    $check_stmt->execute();
    if ($check_stmt->get_result()->num_rows === 0) {
        throw new Exception("Rescuer not found");
    }
    
    // Check if email already exists (excluding current user)
    $email_check = $conn->prepare("SELECT resc_id FROM rescuer WHERE resc_email = ? AND resc_id != ?");
    $email_check->bind_param("si", $email, $id);
    $email_check->execute();
    if ($email_check->get_result()->num_rows > 0) {
        throw new Exception("An account with this email already exists");
    }
    
    // Build update query dynamically
    $update_fields = ["resc_name = ?", "resc_email = ?"];
    $update_values = [$name, $email];
    $bind_types = "ss";
    
    // Add contact field if provided
    if (!empty($contact)) {
        $update_fields[] = "resc_contact = ?";
        $update_values[] = $contact;
        $bind_types .= "s";
    }
    
    // Add password field if provided
    if (!empty($password)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $update_fields[] = "resc_password = ?";
        $update_values[] = $hashed_password;
        $bind_types .= "s";
    }
    
    // Add WHERE clause
    $update_values[] = $id;
    $bind_types .= "i";
    
    // Execute update
    $update_query = "UPDATE rescuer SET " . implode(", ", $update_fields) . " WHERE resc_id = ?";
    error_log("API update_rescuer.php: Query: " . $update_query);
    
    $update_stmt = $conn->prepare($update_query);
    
    if (!$update_stmt) {
        throw new Exception("Failed to prepare update query: " . $conn->error);
    }
    
    $update_stmt->bind_param($bind_types, ...$update_values);
    
    if (!$update_stmt->execute()) {
        throw new Exception("Error updating rescuer account: " . $update_stmt->error);
    }
    
    error_log("API update_rescuer.php: Update successful");
    echo json_encode([
        'success' => true, 
        'message' => "Rescuer account updated successfully!"
    ]);
    
} catch (Exception $e) {
    error_log("API update_rescuer.php: Error - " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}
?>
