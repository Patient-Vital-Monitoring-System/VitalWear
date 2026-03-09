<?php
// Simple test to check active incidents and vitals
include("database/connection.php");

echo "<h2>Active Incidents & Vitals Check</h2>";

// Check if there are any incidents at all
$all_incidents = $conn->query("SELECT COUNT(*) as count FROM incident");
$incident_count = $all_incidents->fetch_assoc()['count'];
echo "<p><strong>Total incidents in database:</strong> " . $incident_count . "</p>";

// Check ongoing incidents
$ongoing_incidents = $conn->query("SELECT COUNT(*) as count FROM incident WHERE status = 'ongoing'");
$ongoing_count = $ongoing_incidents->fetch_assoc()['count'];
echo "<p><strong>Ongoing incidents:</strong> " . $ongoing_count . "</p>";

// Check vitals
$vitals_count = $conn->query("SELECT COUNT(*) as count FROM vitalstat");
$vital_count = $vitals_count->fetch_assoc()['count'];
echo "<p><strong>Total vitals recorded:</strong> " . $vital_count . "</p>";

// Show recent vitals
echo "<h3>Recent Vitals:</h3>";
$recent_vitals = $conn->query("SELECT * FROM vitalstat ORDER BY recorded_at DESC LIMIT 5");
if ($recent_vitals->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Vital ID</th><th>Incident ID</th><th>Heart Rate</th><th>BP</th><th>Oxygen</th><th>Recorded At</th></tr>";
    while ($vital = $recent_vitals->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $vital['vital_id'] . "</td>";
        echo "<td>" . $vital['incident_id'] . "</td>";
        echo "<td>" . $vital['heart_rate'] . "</td>";
        echo "<td>" . $vital['bp_systolic'] . "/" . $vital['bp_diastolic'] . "</td>";
        echo "<td>" . $vital['oxygen_level'] . "%</td>";
        echo "<td>" . $vital['recorded_at'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>No vitals found in database</p>";
}

// Show active incidents with vitals
echo "<h3>Active Incidents with Vitals:</h3>";
$active_with_vitals = $conn->query("
    SELECT i.incident_id, i.status, p.pat_name, 
           COUNT(v.vital_id) as vital_count,
           MAX(v.recorded_at) as last_vital
    FROM incident i
    JOIN patient p ON i.pat_id = p.pat_id
    LEFT JOIN vitalstat v ON i.incident_id = v.incident_id
    WHERE i.status = 'ongoing'
    GROUP BY i.incident_id
");
if ($active_with_vitals->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Incident ID</th><th>Patient</th><th>Status</th><th>Vital Count</th><th>Last Vital</th></tr>";
    while ($row = $active_with_vitals->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['incident_id'] . "</td>";
        echo "<td>" . $row['pat_name'] . "</td>";
        echo "<td>" . $row['status'] . "</td>";
        echo "<td>" . $row['vital_count'] . "</td>";
        echo "<td>" . ($row['last_vital'] ?? 'Never') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>No active incidents found</p>";
}

$conn->close();
?>
