<?php
require_once '../../database/connection.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'rescuer') {
    header("Location: ../../login.html");
    exit;
}

if (!isset($_GET['id'])) {
    header('Location: transferred_incidents.php');
    exit();
}

$incident_id = $_GET['id'];
$rescuer_id = $_SESSION['user_id'];
$conn = getDBConnection();

// Verify incident belongs to this rescuer and is transferred
if ($conn && !$conn->connect_error) {
    $verify_query = "SELECT incident_id FROM incident WHERE incident_id = ? AND resc_id = ? AND status = 'transferred'";
    $stmt = $conn->prepare($verify_query);
    if ($stmt) {
        $stmt->bind_param("ii", $incident_id, $rescuer_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            header('Location: transferred_incidents.php');
            exit();
        }

        // Update incident status to ongoing
        $update_query = "UPDATE incident SET status = 'ongoing' WHERE incident_id = ?";
        $stmt = $conn->prepare($update_query);
        if ($stmt) {
            $stmt->bind_param("i", $incident_id);

            if ($stmt->execute()) {
                // Log activity
                $activity_query = "INSERT INTO activity_log (user_name, user_role, action_type, module, description) 
                                   VALUES (?, 'rescuer', 'accept_incident', 'incident_monitoring', ?)";
                $rescuer_name = $_SESSION['user_name'] ?? 'Unknown';
                $description = "Accepted incident #$incident_id for monitoring";
                $stmt = $conn->prepare($activity_query);
                if ($stmt) {
                    $stmt->bind_param("ss", $rescuer_name, $description);
                    $stmt->execute();
                }
                
                header('Location: ongoing_monitoring.php?id=' . $incident_id);
                exit();
            } else {
                header('Location: transferred_incidents.php?error=update_failed');
                exit();
            }
        } else {
            header('Location: transferred_incidents.php?error=prepare_failed');
            exit();
        }
    } else {
        header('Location: transferred_incidents.php?error=verify_failed');
        exit();
    }
} else {
    header('Location: transferred_incidents.php?error=db_connection');
    exit();
}
?>
