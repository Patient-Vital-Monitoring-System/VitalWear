<?php
header('Content-Type: application/json');
include("../../database/connection.php");
session_start();

if (!isset($_SESSION['responder_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$responder_id = $_SESSION['responder_id'];
$incident_id = $_POST['incident_id'] ?? 0;
$bp_systolic = $_POST['bp_systolic'] ?? 0;
$bp_diastolic = $_POST['bp_diastolic'] ?? 0;
$heart_rate = $_POST['heart_rate'] ?? 0;
$oxygen_level = $_POST['oxygen_level'] ?? 0;

if (empty($incident_id)) {
    echo json_encode(['success' => false, 'message' => 'Incident ID is required']);
    exit;
}

// Verify the incident belongs to this responder
$check_stmt = $conn->prepare("SELECT incident_id FROM incident WHERE incident_id = ? AND resp_id = ? AND status = 'ongoing'");
$check_stmt->bind_param("ii", $incident_id, $responder_id);
$check_stmt->execute();
if ($check_stmt->get_result()->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid incident']);
    exit;
}

// Insert vital stats
$stmt = $conn->prepare("INSERT INTO vitalstat (incident_id, recorded_by, bp_systolic, bp_diastolic, heart_rate, oxygen_level) VALUES (?, 'responder', ?, ?, ?, ?)");
$stmt->bind_param("iiiii", $incident_id, $bp_systolic, $bp_diastolic, $heart_rate, $oxygen_level);

if ($stmt->execute()) {
    // Get the newly inserted vital record
    $vital_id = $conn->insert_id;
    $select_stmt = $conn->prepare("SELECT * FROM vitalstat WHERE vital_id = ?");
    $select_stmt->bind_param("i", $vital_id);
    $select_stmt->execute();
    $vital = $select_stmt->get_result()->fetch_assoc();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Vitals recorded successfully!',
        'vital' => $vital
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to record vitals']);
}

