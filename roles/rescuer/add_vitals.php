<?php
require_once '../../database/connection.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'rescuer') {
    header("Location: ../../login.html");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ongoing_monitoring.php');
    exit();
}

$incident_id = $_POST['incident_id'];
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

// Get form data
$bp_systolic = $_POST['bp_systolic'];
$bp_diastolic = $_POST['bp_diastolic'];
$heart_rate = $_POST['heart_rate'];
$oxygen_level = $_POST['oxygen_level'];

// Insert vital statistics
$insert_query = "INSERT INTO vitalstat (incident_id, recorded_by, bp_systolic, bp_diastolic, heart_rate, oxygen_level) 
                 VALUES (?, 'rescuer', ?, ?, ?, ?)";
$stmt = $conn->prepare($insert_query);
$stmt->bind_param("iiiii", $incident_id, $bp_systolic, $bp_diastolic, $heart_rate, $oxygen_level);

if ($stmt->execute()) {
    // Log activity
    $activity_query = "INSERT INTO activity_log (user_name, user_role, action_type, module, description) 
                       VALUES (?, 'rescuer', 'add_vitals', 'incident_monitoring', ?)";
    $rescuer_name = $_SESSION['user_name'];
    $description = "Added vital signs for incident #$incident_id";
    $stmt = $conn->prepare($activity_query);
    $stmt->bind_param("ss", $rescuer_name, $description);
    $stmt->execute();
    
    // Return JSON response for AJAX requests
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
        echo json_encode(['status' => 'success']);
        exit();
    }
    
    header('Location: ongoing_monitoring.php?id=' . $incident_id);
    exit();
} else {
    // Return JSON error response for AJAX requests
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
        echo json_encode(['status' => 'error', 'message' => 'Failed to record vital signs']);
        exit();
    }
    
    echo "Error recording vital signs. Please try again.";
}
?>
