<?php
require_once 'session_check.php';
require_once '../../database/connection.php';

if (!isset($_GET['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Incident ID required']);
    exit();
}

$incident_id = $_GET['id'];
$rescuer_id = $_SESSION['user_id'];
$conn = getDBConnection();

// Verify incident belongs to this rescuer
$verify_query = "SELECT incident_id FROM incident WHERE incident_id = ? AND resc_id = ?";
$stmt = $conn->prepare($verify_query);
$stmt->bind_param("ii", $incident_id, $rescuer_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit();
}

// Get vital statistics (only latest reading like responder)
$stats_query = "SELECT 
                bp_systolic, 
                bp_diastolic, 
                heart_rate, 
                oxygen_level, 
                recorded_at,
                recorded_by
                FROM vitalstat 
                WHERE incident_id = ? 
                ORDER BY recorded_at DESC 
                LIMIT 1";
$stmt = $conn->prepare($stats_query);
$stmt->bind_param("i", $incident_id);
$stmt->execute();
$latest_vital = $stmt->get_result()->fetch_assoc();

// Get total count
$count_query = "SELECT COUNT(*) as total FROM vitalstat WHERE incident_id = ?";
$stmt = $conn->prepare($count_query);
$stmt->bind_param("i", $incident_id);
$stmt->execute();
$total_result = $stmt->get_result()->fetch_assoc();
$total_readings = $total_result['total'];

$latest_bp = $latest_hr = $latest_o2 = $last_vital_time = null;

if ($latest_vital) {
    $latest_bp = $latest_vital['bp_systolic'] . '/' . $latest_vital['bp_diastolic'];
    $latest_hr = $latest_vital['heart_rate'];
    $latest_o2 = $latest_vital['oxygen_level'];
    $last_vital_time = $latest_vital['recorded_at'];
}

echo json_encode([
    'status' => 'success',
    'latest_bp' => $latest_bp,
    'latest_hr' => $latest_hr,
    'latest_o2' => $latest_o2,
    'last_vital_time' => $last_vital_time,
    'total_readings' => $total_readings
]);
?>
