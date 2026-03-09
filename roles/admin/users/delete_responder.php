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
            throw new Exception("Invalid responder ID");
        }

        // Check if responder exists
        $check_responder = $conn->prepare("SELECT resp_name FROM responder WHERE resp_id = ?");
        if (!$check_responder) {
            throw new Exception("Failed to prepare check query: " . $conn->error);
        }
        
        $check_responder->bind_param("i", $id);
        $check_responder->execute();
        $result = $check_responder->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("Responder not found");
        }

        $responder_name = $result->fetch_assoc()["resp_name"];
        
        // Check for related records in device_log
        $check_device_log = $conn->prepare("SELECT COUNT(*) as count FROM device_log WHERE resp_id = ?");
        if (!$check_device_log) {
            throw new Exception("Failed to check device log: " . $conn->error);
        }
        
        $check_device_log->bind_param("i", $id);
        $check_device_log->execute();
        $device_log_result = $check_device_log->get_result();
        $device_log_count = $device_log_result->fetch_assoc()['count'];
        
        // Check for related records in other potential tables
        $tables_to_check = [
            'active_incidents' => 'resp_id',
            'vitals' => 'recorded_by',
            // Add other tables that might reference responder
        ];
        
        $related_records = [];
        foreach ($tables_to_check as $table => $column) {
            $check_table = $conn->prepare("SELECT COUNT(*) as count FROM $table WHERE $column = ?");
            if ($check_table) {
                $check_table->bind_param("i", $id);
                $check_table->execute();
                $table_result = $check_table->get_result();
                $count = $table_result->fetch_assoc()['count'];
                if ($count > 0) {
                    $related_records[] = "$table ($count records)";
                }
            }
        }
        
        // If there are related records, handle them
        if ($device_log_count > 0 || !empty($related_records)) {
            // Option 1: Delete related records (cascade delete)
            $conn->begin_transaction();
            
            try {
                // Delete from device_log first
                if ($device_log_count > 0) {
                    $delete_device_log = $conn->prepare("DELETE FROM device_log WHERE resp_id = ?");
                    if (!$delete_device_log) {
                        throw new Exception("Failed to prepare device log deletion: " . $conn->error);
                    }
                    $delete_device_log->bind_param("i", $id);
                    if (!$delete_device_log->execute()) {
                        throw new Exception("Failed to delete device log records: " . $delete_device_log->error);
                    }
                }
                
                // Delete from other related tables
                foreach ($tables_to_check as $table => $column) {
                    $delete_related = $conn->prepare("DELETE FROM $table WHERE $column = ?");
                    if ($delete_related) {
                        $delete_related->bind_param("i", $id);
                        $delete_related->execute();
                    }
                }
                
                // Now delete the responder
                $delete_stmt = $conn->prepare("DELETE FROM responder WHERE resp_id = ?");
                if (!$delete_stmt) {
                    throw new Exception("Failed to prepare delete query: " . $conn->error);
                }
                
                $delete_stmt->bind_param("i", $id);
                
                if ($delete_stmt->execute()) {
                    $conn->commit();
                    $success_message = "Responder \"$responder_name\" deleted successfully!";
                } else {
                    throw new Exception("Error deleting responder account: " . $delete_stmt->error);
                }
                
            } catch (Exception $e) {
                $conn->rollback();
                throw new Exception("Error during deletion: " . $e->getMessage());
            }
            
        } else {
            // No related records, simple deletion
            $delete_stmt = $conn->prepare("DELETE FROM responder WHERE resp_id = ?");
            if (!$delete_stmt) {
                throw new Exception("Failed to prepare delete query: " . $conn->error);
            }
            
            $delete_stmt->bind_param("i", $id);
            
            if ($delete_stmt->execute()) {
                $success_message = "Responder \"$responder_name\" deleted successfully!";
            } else {
                throw new Exception("Error deleting responder account: " . $delete_stmt->error);
            }
        }

    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }

    // Redirect back to view page with message
    $redirect_url = "/VitalWear-1/roles/admin/users/view_responders.php";
    if ($error_message) {
        $redirect_url .= "?error=" . urlencode($error_message);
    } elseif ($success_message) {
        $redirect_url .= "?success=" . urlencode($success_message);
    }
    header("Location: $redirect_url");
    exit();
}

// If not POST request, redirect to view page
header("Location: view_responders.php");
exit();
?>
