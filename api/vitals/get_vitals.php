<?php
include("../../database/connection.php");
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'responder') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$incident_id = $_GET['incident_id'] ?? 0;
$limit = $_GET['limit'] ?? 20;

if (empty($incident_id)) {
    echo json_encode(['success' => false, 'message' => 'Incident ID required']);
    exit;
}

// Verify incident belongs to responder
$responder_id = $_SESSION['user_id'];
$check_stmt = $conn->prepare("SELECT incident_id FROM incident WHERE incident_id = ? AND resp_id = ? AND status = 'ongoing'");
$check_stmt->bind_param("ii", $incident_id, $responder_id);
$check_stmt->execute();
$result = $check_stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid incident']);
    exit;
}

// Get vitals history
$stmt = $conn->prepare("
    SELECT heart_rate, bp_systolic, bp_diastolic, oxygen_level, recorded_at 
    FROM vitalstat 
    WHERE incident_id = ? 
    ORDER BY recorded_at DESC 
    LIMIT ?
");
$stmt->bind_param("ii", $incident_id, $limit);
$stmt->execute();
$vitals = $stmt->get_result();

$vitals_data = [];
while($row = $vitals->fetch_assoc()) {
    $vitals_data[] = $row;
}

echo json_encode([
    'success' => true,
    'vitals' => array_reverse($vitals_data) // Reverse to show oldest first
]);

$conn->close();
?>
