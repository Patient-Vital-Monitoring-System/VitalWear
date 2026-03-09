<?php
// Quick debug to check what's happening
include("database/connection.php");
session_start();

echo "<h2>Quick Debug Check</h2>";

// Check if session is set
echo "<p><strong>Session Status:</strong></p>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

// Check if any responders exist
$responder_check = $conn->query("SELECT resp_id, resp_name FROM responder LIMIT 3");
echo "<p><strong>Available Responders:</strong></p>";
if ($responder_check->num_rows > 0) {
    echo "<ul>";
    while ($resp = $responder_check->fetch_assoc()) {
        echo "<li>ID: " . $resp['resp_id'] . " - " . $resp['resp_name'] . "</li>";
    }
    echo "</ul>";
} else {
    echo "<p style='color: red;'>No responders found in database</p>";
}

// Check if any incidents exist
$incident_check = $conn->query("SELECT incident_id, resp_id, status FROM incident LIMIT 5");
echo "<p><strong>Recent Incidents:</strong></p>";
if ($incident_check->num_rows > 0) {
    echo "<ul>";
    while ($inc = $incident_check->fetch_assoc()) {
        echo "<li>Incident #" . $inc['incident_id'] . " - Responder ID: " . $inc['resp_id'] . " - Status: " . $inc['status'] . "</li>";
    }
    echo "</ul>";
} else {
    echo "<p style='color: red;'>No incidents found in database</p>";
}

// Check if migration was applied
echo "<p><strong>Database Migration Status:</strong></p>";
$describe = $conn->query("DESCRIBE incident");
$found_log_id = false;
while ($col = $describe->fetch_assoc()) {
    if ($col['Field'] === 'log_id') {
        $found_log_id = true;
        echo "<p style='color: green;'>✅ log_id column found - " . $col['Type'] . " - Null: " . $col['Null'] . "</p>";
        break;
    }
}
if (!$found_log_id) {
    echo "<p style='color: red;'>❌ log_id column not found - migration not applied</p>";
}

$conn->close();
?>
