<?php
require_once 'session_check.php';
require_once '../../database/connection.php';

if (!isset($_GET['id'])) {
    header('Location: ongoing_monitoring.php');
    exit();
}

$incident_id = $_GET['id'];
$rescuer_id = $_SESSION['user_id'];
$conn = getDBConnection();

// Verify incident belongs to this rescuer and is ongoing
$verify_query = "SELECT incident_id FROM incident WHERE incident_id = ? AND resc_id = ? AND status = 'ongoing'";
$stmt = $conn->prepare($verify_query);
$stmt->bind_param("ii", $incident_id, $rescuer_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: ongoing_monitoring.php');
    exit();
}

// Update incident status to completed and set end_time
$update_query = "UPDATE incident SET status = 'completed', end_time = NOW() WHERE incident_id = ?";
$stmt = $conn->prepare($update_query);
$stmt->bind_param("i", $incident_id);

if ($stmt->execute()) {
    // Log activity
    $activity_query = "INSERT INTO activity_log (user_name, user_role, action_type, module, description) 
                       VALUES (?, 'rescuer', 'complete_incident', 'incident_monitoring', ?)";
    $rescuer_name = $_SESSION['user_name'];
    $description = "Completed incident #$incident_id";
    $stmt = $conn->prepare($activity_query);
    $stmt->bind_param("ss", $rescuer_name, $description);
    $stmt->execute();
    
    header('Location: completed_cases.php');
    exit();
} else {
    echo "Error completing incident. Please try again.";
}
?>
