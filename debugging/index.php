<?php
require_once '../../../../../database/connection.php';

echo "<h1>🔧 VitalWear Debugging Dashboard</h1>";
echo "<style>
    .dashboard { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin: 20px 0; }
    .card { background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    .card h3 { margin-top: 0; color: #495057; }
    .status { padding: 10px; margin: 10px 0; border-radius: 5px; }
    .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
    .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    .info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
    .btn { padding: 10px 15px; margin: 5px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; display: inline-block; }
    .btn-admin { background: #6f42c1; }
    .btn-management { background: #28a745; }
    .btn-responder { background: #17a2b8; }
    .btn-rescuer { background: #fd7e14; }
    .btn-incident { background: #dc3545; }
    .btn-vital { background: #20c997; }
    .stats { display: flex; justify-content: space-around; margin: 15px 0; }
    .stat { text-align: center; }
    .stat-number { font-size: 2em; font-weight: bold; color: #007bff; }
    .stat-label { font-size: 0.9em; color: #6c757d; }
    table { width: 100%; border-collapse: collapse; margin: 10px 0; }
    th, td { padding: 8px; text-align: left; border-bottom: 1px solid #dee2e6; }
    th { background-color: #e9ecef; }
</style>";

$conn = getDBConnection();

// Get overall stats
echo "<div class='card'>";
echo "<h3>📊 System Overview</h3>";
echo "<div class='stats'>";

// Admin count
$admin_count = $conn->query("SELECT COUNT(*) as count FROM admin")->fetch_assoc()['count'];
echo "<div class='stat'>";
echo "<div class='stat-number'>$admin_count</div>";
echo "<div class='stat-label'>Admins</div>";
echo "</div>";

// Management count
$mgmt_count = $conn->query("SELECT COUNT(*) as count FROM management")->fetch_assoc()['count'];
echo "<div class='stat'>";
echo "<div class='stat-number'>$mgmt_count</div>";
echo "<div class='stat-label'>Management</div>";
echo "</div>";

// Responder count
$resp_count = $conn->query("SELECT COUNT(*) as count FROM responder")->fetch_assoc()['count'];
echo "<div class='stat'>";
echo "<div class='stat-number'>$resp_count</div>";
echo "<div class='stat-label'>Responders</div>";
echo "</div>";

// Rescuer count
$resc_count = $conn->query("SELECT COUNT(*) as count FROM rescuer")->fetch_assoc()['count'];
echo "<div class='stat'>";
echo "<div class='stat-number'>$resc_count</div>";
echo "<div class='stat-label'>Rescuers</div>";
echo "</div>";

echo "</div>";

// Recent activity
echo "<h4>Recent Activity</h4>";
$recent_incidents = $conn->query("SELECT COUNT(*) as count FROM incident WHERE DATE(created_at) = CURDATE()")->fetch_assoc()['count'];
$recent_patients = $conn->query("SELECT COUNT(*) as count FROM patient WHERE DATE(created_at) = CURDATE()")->fetch_assoc()['count'];

echo "<p>📅 Today's Incidents: <strong>$recent_incidents</strong></p>";
echo "<p>👥 Today's Patients: <strong>$recent_patients</strong></p>";
echo "</div>";

// Quick access cards
echo "<div class='dashboard'>";

// Admin Debug Card
echo "<div class='card'>";
echo "<h3>👔 Admin Debugging</h3>";
echo "<p>Debug admin user management, table structure, and API functionality.</p>";
echo "<div class='status info'>";
$admin_table = $conn->query("SHOW TABLES LIKE 'admin'")->num_rows > 0;
echo "Admin Table: " . ($admin_table ? "✅ Exists" : "❌ Missing");
echo "</div>";
echo "<a href='admin_debug.php' class='btn btn-admin'>Open Admin Debug</a>";
echo "</div>";

// Management Debug Card
echo "<div class='card'>";
echo "<h3>💼 Management Debugging</h3>";
echo "<p>Debug management user accounts and related functionality.</p>";
echo "<div class='status info'>";
$mgmt_table = $conn->query("SHOW TABLES LIKE 'management'")->num_rows > 0;
echo "Management Table: " . ($mgmt_table ? "✅ Exists" : "❌ Missing");
echo "</div>";
echo "<a href='management_debug.php' class='btn btn-management'>Open Management Debug</a>";
echo "</div>";

// Responder Debug Card
echo "<div class='card'>";
echo "<h3>🚑 Responder Debugging</h3>";
echo "<p>Debug responder accounts, incidents, and vital statistics.</p>";
echo "<div class='status info'>";
$resp_table = $conn->query("SHOW TABLES LIKE 'responder'")->num_rows > 0;
$incident_table = $conn->query("SHOW TABLES LIKE 'incident'")->num_rows > 0;
echo "Responder Table: " . ($resp_table ? "✅" : "❌") . " | ";
echo "Incident Table: " . ($incident_table ? "✅" : "❌");
echo "</div>";
echo "<a href='responder_debug.php' class='btn btn-responder'>Open Responder Debug</a>";
echo "</div>";

// Rescuer Debug Card
echo "<div class='card'>";
echo "<h3>🆘 Rescuer Debugging</h3>";
echo "<p>Debug rescuer accounts, device assignments, and equipment.</p>";
echo "<div class='status info'>";
$resc_table = $conn->query("SHOW TABLES LIKE 'rescuer'")->num_rows > 0;
$device_table = $conn->query("SHOW TABLES LIKE 'device'")->num_rows > 0;
echo "Rescuer Table: " . ($resc_table ? "✅" : "❌") . " | ";
echo "Device Table: " . ($device_table ? "✅" : "❌");
echo "</div>";
echo "<a href='rescuer_debug.php' class='btn btn-rescuer'>Open Rescuer Debug</a>";
echo "</div>";
echo "</div>";

// System Health Check
echo "<div class='card'>";
echo "<h3>🏥 System Health Check</h3>";

$health_checks = [
    'patient' => 'Patient Records',
    'vitalstat' => 'Vital Statistics',
    'device' => 'Device Management',
    'device_assignment' => 'Device Assignments',
    'logs' => 'Device Logs'
];

echo "<table>";
echo "<tr><th>Component</th><th>Status</th><th>Records</th></tr>";

foreach ($health_checks as $table => $label) {
    $exists = $conn->query("SHOW TABLES LIKE '$table'")->num_rows > 0;
    $count = $exists ? $conn->query("SELECT COUNT(*) as count FROM $table")->fetch_assoc()['count'] : 0;
    
    $status = $exists ? "✅ OK" : "❌ Missing";
    $status_class = $exists ? "success" : "error";
    
    echo "<tr>";
    echo "<td>$label</td>";
    echo "<td><span class='status $status_class'>$status</span></td>";
    echo "<td>$count</td>";
    echo "</tr>";
}

echo "</table>";
echo "</div>";

// Quick Actions
echo "<div class='card'>";
echo "<h3>⚡ Quick Actions</h3>";
echo "<a href='create_incident_test.php' class='btn btn-incident'>Test Create Incident</a>";
echo "<a href='test_connection_simple.php' class='btn'>Test DB Connection</a>";
echo "<a href='test_record_vitals.php' class='btn btn-vital'>Test Vital Recording</a>";
echo "<a href='../database/run_migration.php' class='btn btn-incident'>Run Database Migration</a>";
echo "<a href='FIX_INSTRUCTIONS.md' class='btn'>View Fix Instructions</a>";
echo "</div>";

// Recent Errors (if any)
echo "<div class='card'>";
echo "<h3>🐛 Recent Debugging Files</h3>";

$debug_files = [
    'create_incident_test.php' => 'Incident Creation Test',
    'test_connection_simple.php' => 'Database Connection Test',
    'debug_active_incidents.php' => 'Active Incidents Debug',
    'quick_debug.php' => 'Quick System Debug',
    'simple_active_incidents.php' => 'Simple Incidents Debug'
];

echo "<table>";
echo "<tr><th>File</th><th>Purpose</th><th>Action</th></tr>";

foreach ($debug_files as $file => $description) {
    if (file_exists(__DIR__ . '/' . $file)) {
        echo "<tr>";
        echo "<td>$file</td>";
        echo "<td>$description</td>";
        echo "<td><a href='$file' class='btn'>Open</a></td>";
        echo "</tr>";
    }
}

echo "</table>";
echo "</div>";

$conn->close();

echo "<hr>";
echo "<p style='text-align: center; color: #6c757d;'>";
echo "<strong>🔧 VitalWear Debugging Dashboard</strong> | ";
echo "<a href='../index.php'>Back to Home</a> | ";
echo "<a href='README.md'>Debug Documentation</a>";
echo "</p>";
?>
