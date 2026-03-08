<?php
// API endpoint for updating a user

header('Content-Type: application/json');
require_once '../../database/connection.php';

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Get input data
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    $input = $_POST;
}

$userId = isset($input['id']) ? intval($input['id']) : 0;
$email = isset($input['email']) ? trim($input['email']) : '';
$role = isset($input['role']) ? trim($input['role']) : '';
$name = isset($input['name']) ? trim($input['name']) : '';
$password = isset($input['password']) ? $input['password'] : null;

// Validate input
if ($userId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid user ID']);
    exit;
}

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

// Hash password if provided using MD5 (to match database format)
$passwordHash = null;
if (!empty($password)) {
    $passwordHash = md5($password);
}

// Try to update the user
try {
    $result = false;
    
    switch ($role) {
        case 'staff':
            if ($name !== null) {
                $sql = $passwordHash 
                    ? "UPDATE management SET mgmt_email = ?, mgmt_password = ?, mgmt_name = ? WHERE mgmt_id = ?"
                    : "UPDATE management SET mgmt_email = ?, mgmt_name = ? WHERE mgmt_id = ?";
                $stmt = $conn->prepare($sql);
                if ($passwordHash) {
                    $stmt->bind_param("sssi", $email, $passwordHash, $name, $userId);
                } else {
                    $stmt->bind_param("ssi", $email, $name, $userId);
                }
            } else {
                $sql = $passwordHash 
                    ? "UPDATE management SET mgmt_email = ?, mgmt_password = ? WHERE mgmt_id = ?"
                    : "UPDATE management SET mgmt_email = ? WHERE mgmt_id = ?";
                $stmt = $conn->prepare($sql);
                if ($passwordHash) {
                    $stmt->bind_param("ssi", $email, $passwordHash, $userId);
                } else {
                    $stmt->bind_param("si", $email, $userId);
                }
            }
            $stmt->execute();
            $result = $stmt->affected_rows > 0;
            break;
            
        case 'admin':
            if ($name !== null) {
                $sql = $passwordHash 
                    ? "UPDATE admin SET admin_email = ?, admin_password = ?, admin_name = ? WHERE admin_id = ?"
                    : "UPDATE admin SET admin_email = ?, admin_name = ? WHERE admin_id = ?";
                $stmt = $conn->prepare($sql);
                if ($passwordHash) {
                    $stmt->bind_param("sssi", $email, $passwordHash, $name, $userId);
                } else {
                    $stmt->bind_param("ssi", $email, $name, $userId);
                }
            } else {
                $sql = $passwordHash 
                    ? "UPDATE admin SET admin_email = ?, admin_password = ? WHERE admin_id = ?"
                    : "UPDATE admin SET admin_email = ? WHERE admin_id = ?";
                $stmt = $conn->prepare($sql);
                if ($passwordHash) {
                    $stmt->bind_param("ssi", $email, $passwordHash, $userId);
                } else {
                    $stmt->bind_param("si", $email, $userId);
                }
            }
            $stmt->execute();
            $result = $stmt->affected_rows > 0;
            break;
            
        case 'responder':
            if ($name !== null) {
                $sql = $passwordHash 
                    ? "UPDATE responder SET resp_email = ?, resp_password = ?, resp_name = ? WHERE resp_id = ?"
                    : "UPDATE responder SET resp_email = ?, resp_name = ? WHERE resp_id = ?";
                $stmt = $conn->prepare($sql);
                if ($passwordHash) {
                    $stmt->bind_param("sssi", $email, $passwordHash, $name, $userId);
                } else {
                    $stmt->bind_param("ssi", $email, $name, $userId);
                }
            } else {
                $sql = $passwordHash 
                    ? "UPDATE responder SET resp_email = ?, resp_password = ? WHERE resp_id = ?"
                    : "UPDATE responder SET resp_email = ? WHERE resp_id = ?";
                $stmt = $conn->prepare($sql);
                if ($passwordHash) {
                    $stmt->bind_param("ssi", $email, $passwordHash, $userId);
                } else {
                    $stmt->bind_param("si", $email, $userId);
                }
            }
            $stmt->execute();
            $result = $stmt->affected_rows > 0;
            break;
            
        case 'rescuer':
            if ($name !== null) {
                $sql = $passwordHash 
                    ? "UPDATE rescuer SET resc_email = ?, resc_password = ?, resc_name = ? WHERE resc_id = ?"
                    : "UPDATE rescuer SET resc_email = ?, resc_name = ? WHERE resc_id = ?";
                $stmt = $conn->prepare($sql);
                if ($passwordHash) {
                    $stmt->bind_param("sssi", $email, $passwordHash, $name, $userId);
                } else {
                    $stmt->bind_param("ssi", $email, $name, $userId);
                }
            } else {
                $sql = $passwordHash 
                    ? "UPDATE rescuer SET resc_email = ?, resc_password = ? WHERE resc_id = ?"
                    : "UPDATE rescuer SET resc_email = ? WHERE resc_id = ?";
                $stmt = $conn->prepare($sql);
                if ($passwordHash) {
                    $stmt->bind_param("ssi", $email, $passwordHash, $userId);
                } else {
                    $stmt->bind_param("si", $email, $userId);
                }
            }
            $stmt->execute();
            $result = $stmt->affected_rows > 0;
            break;
    }

    echo json_encode([
        'success' => true,
        'message' => $result ? 'User updated successfully' : 'No changes were necessary'
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to update user: ' . $e->getMessage()]);
}
?>
