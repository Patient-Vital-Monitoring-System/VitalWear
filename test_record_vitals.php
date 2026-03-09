<?php
// Test script to check record_vitals API
include("database/connection.php");
session_start();

// Simulate logged in responder (use first responder from database)
$responder_query = "SELECT resp_id FROM responder LIMIT 1";
$responder_result = $conn->query($responder_query);
$responder = $responder_result->fetch_assoc();
$responder_id = $responder['resp_id'];

// Simulate session
$_SESSION['user_id'] = $responder_id;
$_SESSION['user_role'] = 'responder';

echo "<h2>Test Record Vitals API</h2>";
echo "<p><strong>Responder ID:</strong> " . $responder_id . "</p>";

// Get an active incident for this responder
$incident_query = "SELECT incident_id FROM incident WHERE resp_id = ? AND status = 'ongoing' LIMIT 1";
$incident_stmt = $conn->prepare($incident_query);
$incident_stmt->bind_param("i", $responder_id);
$incident_stmt->execute();
$incident_result = $incident_stmt->get_result();

if ($incident_result->num_rows === 0) {
    echo "<p style='color: red;'>No active incidents found. Creating test incident...</p>";
    
    // Create a test patient
    $patient_stmt = $conn->prepare("INSERT INTO patient (pat_name, birthdate, contact_number) VALUES (?, ?, ?)");
    $patient_name = "Test Patient " . date('His');
    $birthdate = date('Y-m-d');
    $contact = "09123456789";
    $patient_stmt->bind_param("sss", $patient_name, $birthdate, $contact);
    $patient_stmt->execute();
    $pat_id = $conn->insert_id;
    
    // Create test incident
    $incident_stmt = $conn->prepare("INSERT INTO incident (log_id, pat_id, resp_id, status) VALUES (?, ?, ?, 'ongoing')");
    $log_id = null;
    $incident_stmt->bind_param("iii", $log_id, $pat_id, $responder_id);
    $incident_stmt->execute();
    $incident_id = $conn->insert_id;
    
    echo "<p style='color: green;'>Created test incident ID: " . $incident_id . "</p>";
} else {
    $incident = $incident_result->fetch_assoc();
    $incident_id = $incident['incident_id'];
    echo "<p><strong>Using incident ID:</strong> " . $incident_id . "</p>";
}

// Test the API call
echo "<h3>Testing API Call:</h3>";

// Simulate POST data
$_POST = [
    'incident_id' => $incident_id,
    'bp_systolic' => 120,
    'bp_diastolic' => 80,
    'heart_rate' => 75,
    'oxygen_level' => 98
];

echo "<p><strong>POST Data:</strong> " . print_r($_POST, true) . "</p>";

// Include and test the API
ob_start();
include("api/vitals/record_vitals.php");
$output = ob_get_clean();

echo "<h3>API Response:</h3>";
echo "<pre>" . $output . "</pre>";

// Check if vital was actually inserted
$check_query = "SELECT * FROM vitalstat WHERE incident_id = ? ORDER BY vital_id DESC LIMIT 1";
$check_stmt = $conn->prepare($check_query);
$check_stmt->bind_param("i", $incident_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

echo "<h3>Database Check:</h3>";
if ($check_result->num_rows > 0) {
    $vital = $check_result->fetch_assoc();
    echo "<p style='color: green;'>✅ Vital successfully inserted:</p>";
    echo "<pre>" . print_r($vital, true) . "</pre>";
} else {
    echo "<p style='color: red;'>❌ No vital found in database</p>";
}

$conn->close();
?>
