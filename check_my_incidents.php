<?php
include("database/connection.php");
session_start();

echo "<h2>Check Your Active Incidents</h2>";

$responder_id = $_SESSION['user_id'] ?? 1;
echo "<p><strong>Your Responder ID:</strong> " . $responder_id . "</p>";

// Check your active incidents specifically
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

echo "<p><strong>Your Active Incidents:</strong> " . $incidents->num_rows . "</p>";

if ($incidents->num_rows === 0) {
    echo "<p style='color: red;'>❌ No active incidents found!</p>";
    echo "<p><a href='create_active_incident.php'>Create an Active Incident Now</a></p>";
} else {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Incident ID</th><th>Patient</th><th>Status</th><th>Vitals</th></tr>";
    while($incident = $incidents->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $incident['incident_id'] . "</td>";
        echo "<td>" . $incident['pat_name'] . "</td>";
        echo "<td>" . $incident['status'] . "</td>";
        echo "<td>" . $incident['vital_count'] . " records</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "<p><a href='roles/responder/active_incidents.php'>View Active Incidents Page</a></p>";
}

$conn->close();
?>
