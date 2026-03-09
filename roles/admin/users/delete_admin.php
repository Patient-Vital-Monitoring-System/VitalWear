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
            throw new Exception("Invalid admin ID");
        }

        // Prevent self-deletion
        if ($id == $_SESSION['user_id']) {
            throw new Exception("You cannot delete your own account");
        }

        // Check if admin exists
        $check_admin = $conn->prepare("SELECT admin_name FROM admin WHERE admin_id = ?");
        $check_admin->bind_param("i", $id);
        $check_admin->execute();
        $result = $check_admin->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("Admin not found");
        }

        $admin_name = $result->fetch_assoc()["admin_name"];
        
        // Delete admin
        $delete_stmt = $conn->prepare("DELETE FROM admin WHERE admin_id = ?");
        $delete_stmt->bind_param("i", $id);
        
        if ($delete_stmt->execute()) {
            $success_message = "Admin \"$admin_name\" deleted successfully!";
        } else {
            throw new Exception("Error deleting admin account");
        }

    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }

    // Redirect back to view page with message
    $redirect_url = "/VitalWear-1/roles/admin/users/view_admins.php";
    if ($error_message) {
        $redirect_url .= "?error=" . urlencode($error_message);
    } elseif ($success_message) {
        $redirect_url .= "?success=" . urlencode($success_message);
    }
    header("Location: $redirect_url");
    exit();
}

// If not POST request, redirect to view page
header("Location: view_admins.php");
exit();
?>
