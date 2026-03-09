<?php
session_start();
require_once "../../../database/connection.php";

// Check if admin user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /VitalWear-1/login.html');
    exit();
}

$conn = getDBConnection();
$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $id = (int)$_POST['id'] ?? 0;
        
        // Validation
        if ($id <= 0) {
            throw new Exception("Invalid management ID");
        }

        // Check if management exists
        $check_management = $conn->prepare("SELECT mgmt_name FROM management WHERE mgmt_id = ?");
        if (!$check_management) {
            throw new Exception("Failed to prepare check query: " . $conn->error);
        }
        
        $check_management->bind_param("i", $id);
        $check_management->execute();
        $result = $check_management->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("Management not found");
        }

        $management_name = $result->fetch_assoc()["mgmt_name"];
        
        // Delete management
        $delete_stmt = $conn->prepare("DELETE FROM management WHERE mgmt_id = ?");
        if (!$delete_stmt) {
            throw new Exception("Failed to prepare delete query: " . $conn->error);
        }
        
        $delete_stmt->bind_param("i", $id);
        
        if ($delete_stmt->execute()) {
            $success_message = "Management \"$management_name\" deleted successfully!";
        } else {
            throw new Exception("Error deleting management account: " . $delete_stmt->error);
        }

    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }

    // Redirect back to view page with message
    $redirect_url = "/VitalWear-1/roles/admin/users/view_management.php";
    if ($error_message) {
        $redirect_url .= "?error=" . urlencode($error_message);
    } elseif ($success_message) {
        $redirect_url .= "?success=" . urlencode($success_message);
    }
    header("Location: $redirect_url");
    exit();
}

// If not POST request, redirect to view page
header("Location: view_management.php");
exit();
?>
