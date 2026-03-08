<?php
// API endpoint for creating a new user

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

$email = isset($input['email']) ? trim($input['email']) : '';
$role = isset($input['role']) ? trim($input['role']) : '';
$password = isset($input['password']) ? $input['password'] : '';
$name = isset($input['name']) ? trim($input['name']) : '';

// Validate input
if (empty($email)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Email is required']);
    exit;
}

if (empty($role)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Role is required']);
    exit;
}

if (empty($password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Password is required']);
    exit;
}

// Hash password using MD5 (to match database format)
$passwordHash = md5($password);

// Try to create the user in the appropriate table
try {
    $result = false;
    
    switch ($role) {
        case 'staff':
            $stmt = $conn->prepare("INSERT INTO management (mgmt_email, mgmt_password, mgmt_name) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $email, $passwordHash, $name);
            $stmt->execute();
            $result = $stmt->affected_rows > 0;
            break;
            
        case 'admin':
            $stmt = $conn->prepare("INSERT INTO admin (admin_email, admin_password, admin_name) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $email, $passwordHash, $name);
            $stmt->execute();
            $result = $stmt->affected_rows > 0;
            break;
            
        case 'responder':
            $stmt = $conn->prepare("INSERT INTO responder (resp_email, resp_password, resp_name) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $email, $passwordHash, $name);
            $stmt->execute();
            $result = $stmt->affected_rows > 0;
            break;
            
        case 'rescuer':
            $stmt = $conn->prepare("INSERT INTO rescuer (resc_email, resc_password, resc_name) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $email, $passwordHash, $name);
            $stmt->execute();
            $result = $stmt->affected_rows > 0;
            break;
    }
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'User created successfully']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to create user']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to create user: ' . $e->getMessage()]);
}
?>
