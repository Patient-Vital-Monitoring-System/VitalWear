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

// Get vital history
$vitals_query = "SELECT bp_systolic, bp_diastolic, heart_rate, oxygen_level, recorded_at, recorded_by 
                  FROM vitalstat 
                  WHERE incident_id = ? 
                  ORDER BY recorded_at DESC";
$stmt = $conn->prepare($vitals_query);
$stmt->bind_param("i", $incident_id);
$stmt->execute();
$vitals = $stmt->get_result();

$vitals_data = [];
while ($vital = $vitals->fetch_assoc()) {
    $vitals_data[] = $vital;
}

echo json_encode([
    'status' => 'success',
    'vitals' => $vitals_data
]);
?>
