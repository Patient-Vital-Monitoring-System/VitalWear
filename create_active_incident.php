<?php
include("database/connection.php");
session_start();

echo "<h2>Create Active Incident</h2>";

// Use logged in responder ID
$responder_id = $_SESSION['user_id'] ?? 1;
echo "<p><strong>Creating incident for Responder ID:</strong> " . $responder_id . "</p>";

// Create a test patient
$patient_name = "Test Patient " . date('His');
$birthdate = date('Y-m-d');
$contact = "09123456789";

$patient_stmt = $conn->prepare("INSERT INTO patient (pat_name, birthdate, contact_number) VALUES (?, ?, ?)");
$patient_stmt->bind_param("sss", $patient_name, $birthdate, $contact);

if ($patient_stmt->execute()) {
    $pat_id = $conn->insert_id;
    echo "<p style='color: green;'>✅ Created patient: " . $patient_name . " (ID: " . $pat_id . ")</p>";
    
    // Create incident with 'ongoing' status
    $log_id = null; // No device required
    $inc_stmt = $conn->prepare("INSERT INTO incident (log_id, pat_id, resp_id, status) VALUES (?, ?, ?, 'ongoing')");
    $inc_stmt->bind_param("iii", $log_id, $pat_id, $responder_id);
    
    if ($inc_stmt->execute()) {
        $incident_id = $conn->insert_id;
        echo "<p style='color: green;'>✅ Created active incident: #" . $incident_id . "</p>";
        
        // Auto-insert initial vital stats with default values
        $vital_stmt = $conn->prepare("INSERT INTO vitalstat (incident_id, recorded_by, bp_systolic, bp_diastolic, heart_rate, oxygen_level) VALUES (?, 'responder', 0, 0, 0, 0)");
        $vital_stmt->bind_param("i", $incident_id);
        $vital_stmt->execute();
        
        echo "<p style='color: green;'>✅ Added initial vital stats</p>";
        
        echo "<p><a href='roles/responder/active_incidents.php' style='display:inline-block;padding:10px 20px;background:#dd4c56;color:white;text-decoration:none;border-radius:5px;'>View Active Incidents</a></p>";
        
    } else {
        echo "<p style='color: red;'>❌ Failed to create incident: " . $conn->error . "</p>";
    }
    
} else {
    echo "<p style='color: red;'>❌ Failed to create patient: " . $conn->error . "</p>";
}

$conn->close();
?>
