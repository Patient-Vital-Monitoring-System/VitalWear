<?php 
include("database/connection.php");
session_start();

echo "<h2>Active Incidents - Simple Debug</h2>";

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'responder') {
    echo "<p style='color: red;'>❌ Not authenticated as responder</p>";
    echo "<p>Please <a href='../../login.html'>login here</a></p>";
    exit;
}

$responder_id = $_SESSION['user_id'];
echo "<p><strong>Logged in as Responder ID:</strong> " . $responder_id . "</p>";

// Get active incidents
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
    echo "<tr><th>Incident ID</th><th>Patient</th><th>Status</th><th>Vital Count</th><th>Action</th></tr>";
    
    while($incident = $incidents->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $incident['incident_id'] . "</td>";
        echo "<td>" . htmlspecialchars($incident['pat_name']) . "</td>";
        echo "<td>" . $incident['status'] . "</td>";
        echo "<td>" . $incident['vital_count'] . "</td>";
        echo "<td><button onclick='alert(\"Testing button for incident " . $incident['incident_id'] . "\")'>Test Button</button></td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: orange;'>⚠️ No active incidents found for this responder.</p>";
    
    // Show all incidents for this responder
    $all_stmt = $conn->prepare("SELECT incident_id, status FROM incident WHERE resp_id = ?");
    $all_stmt->bind_param("i", $responder_id);
    $all_stmt->execute();
    $all_incidents = $all_stmt->get_result();
    
    echo "<h3>All Incidents for This Responder:</h3>";
    if ($all_incidents->num_rows > 0) {
        echo "<ul>";
        while($inc = $all_incidents->fetch_assoc()) {
            echo "<li>Incident #" . $inc['incident_id'] . " - Status: " . $inc['status'] . "</li>";
        }
        echo "</ul>";
    } else {
        echo "<p>No incidents at all for this responder.</p>";
    }
}

$conn->close();
?>
