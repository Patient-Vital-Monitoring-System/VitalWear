<?php
// Debug script to check active incidents
include("database/connection.php");
session_start();

// Simulate logged in responder (use first responder from database)
$responder_query = "SELECT resp_id FROM responder LIMIT 1";
$responder_result = $conn->query($responder_query);
$responder = $responder_result->fetch_assoc();
$responder_id = $responder['resp_id'];

echo "<h2>Active Incidents Debug</h2>";
echo "<p><strong>Responder ID:</strong> " . $responder_id . "</p>";

// Check for active incidents
$stmt = $conn->prepare("
    SELECT i.incident_id, i.status, i.start_time, p.pat_name, p.pat_id,
           (SELECT COUNT(*) FROM vitalstat v WHERE v.incident_id = i.incident_id) as vital_count
    FROM incident i
    JOIN patient p ON i.pat_id = p.pat_id
    WHERE i.resp_id = ? AND i.status = 'ongoing'
    ORDER BY i.start_time DESC
");
$stmt->bind_param("i", $responder_id);
$stmt->execute();
$incidents = $stmt->get_result();

echo "<p><strong>Active Incidents Found:</strong> " . $incidents->num_rows . "</p>";

if ($incidents->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Incident ID</th><th>Patient</th><th>Status</th><th>Start Time</th><th>Vital Count</th></tr>";
    
    while($incident = $incidents->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $incident['incident_id'] . "</td>";
        echo "<td>" . htmlspecialchars($incident['pat_name']) . "</td>";
        echo "<td>" . $incident['status'] . "</td>";
        echo "<td>" . date('M d, Y h:i A', strtotime($incident['start_time'])) . "</td>";
        echo "<td>" . $incident['vital_count'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>No active incidents found for this responder.</p>";
    
    // Show all incidents for this responder
    $all_stmt = $conn->prepare("SELECT incident_id, status, start_time FROM incident WHERE resp_id = ?");
    $all_stmt->bind_param("i", $responder_id);
    $all_stmt->execute();
    $all_incidents = $all_stmt->get_result();
    
    echo "<h3>All Incidents for Responder:</h3>";
    if ($all_incidents->num_rows > 0) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>Incident ID</th><th>Status</th><th>Start Time</th></tr>";
        while($inc = $all_incidents->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $inc['incident_id'] . "</td>";
            echo "<td>" . $inc['status'] . "</td>";
            echo "<td>" . date('M d, Y h:i A', strtotime($inc['start_time'])) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No incidents found at all for this responder.</p>";
    }
}

$conn->close();
?>
