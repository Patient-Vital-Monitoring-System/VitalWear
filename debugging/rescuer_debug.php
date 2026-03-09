<?php
require_once '../../../../../database/connection.php';

echo "<h2>Rescuer Role Debugging Tools</h2>";
echo "<style>
    .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
    .success { color: green; }
    .error { color: red; }
    .info { color: blue; }
    table { border-collapse: collapse; width: 100%; margin: 10px 0; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #f2f2f2; }
    .btn { padding: 8px 15px; margin: 5px; background: #fd7e14; color: white; text-decoration: none; border-radius: 3px; }
</style>";

$conn = getDBConnection();

// Section 1: Table Structure Check
echo "<div class='section'>";
echo "<h3>📊 Rescuer Table Structure</h3>";

$table_check = $conn->query("SHOW TABLES LIKE 'rescuer'");
echo "<p>Rescuer table exists: " . ($table_check->num_rows > 0 ? "<span class='success'>YES</span>" : "<span class='error'>NO</span>") . "</p>";

if ($table_check->num_rows > 0) {
    echo "<h4>Table Structure:</h4>";
    $columns = $conn->query("SHOW COLUMNS FROM rescuer");
    echo "<table>";
    echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    while ($row = $columns->fetch_assoc()) {
        echo "<tr>";
        echo "<td><strong>{$row['Field']}</strong></td>";
        echo "<td>{$row['Type']}</td>";
        echo "<td>{$row['Null']}</td>";
        echo "<td>{$row['Key']}</td>";
        echo "<td>{$row['Default']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Expected columns
    echo "<h4>Expected Columns:</h4>";
    $expected_columns = ['rescuer_id', 'rescuer_name', 'rescuer_email', 'rescuer_contact', 'rescuer_password', 'status', 'created_at'];
    $actual_columns = [];
    $columns->data_seek(0);
    while ($row = $columns->fetch_assoc()) {
        $actual_columns[] = $row['Field'];
    }
    
    foreach ($expected_columns as $col) {
        if (in_array($col, $actual_columns)) {
            echo "<p class='success'>✅ $col - Present</p>";
        } else {
            echo "<p class='error'>❌ $col - Missing</p>";
        }
    }
}
echo "</div>";

// Section 2: Data Check
echo "<div class='section'>";
echo "<h3>📋 Rescuer Data Check</h3>";

if ($table_check->num_rows > 0) {
    $count = $conn->query("SELECT COUNT(*) as count FROM rescuer");
    $row = $count->fetch_assoc();
    echo "<p><strong>Total Rescuer Accounts:</strong> " . $row['count'] . "</p>";
    
    if ($row['count'] > 0) {
        echo "<h4>Sample Rescuer Accounts:</h4>";
        $data = $conn->query("SELECT rescuer_id, rescuer_name, rescuer_email, rescuer_contact, status FROM rescuer LIMIT 5");
        echo "<table>";
        echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Contact</th><th>Status</th></tr>";
        while ($row = $data->fetch_assoc()) {
            echo "<tr>";
            echo "<td>{$row['rescuer_id']}</td>";
            echo "<td>" . htmlspecialchars($row['rescuer_name']) . "</td>";
            echo "<td>" . htmlspecialchars($row['rescuer_email']) . "</td>";
            echo "<td>" . htmlspecialchars($row['rescuer_contact']) . "</td>";
            echo "<td>" . htmlspecialchars($row['status']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
}
echo "</div>";

// Section 3: Device Assignment Check
echo "<div class='section'>";
echo "<h3>🔗 Device Assignment Check</h3>";

// Check device table
$device_check = $conn->query("SHOW TABLES LIKE 'device'");
echo "<p>Device table exists: " . ($device_check->num_rows > 0 ? "<span class='success'>YES</span>" : "<span class='error'>NO</span>") . "</p>";

if ($device_check->num_rows > 0) {
    $device_count = $conn->query("SELECT COUNT(*) as count FROM device");
    $device_row = $device_count->fetch_assoc();
    echo "<p><strong>Total Devices:</strong> " . $device_row['count'] . "</p>";
    
    if ($device_row['count'] > 0) {
        $device_data = $conn->query("SELECT device_id, device_name, device_type, status FROM device LIMIT 5");
        echo "<h4>Sample Devices:</h4>";
        echo "<table>";
        echo "<tr><th>Device ID</th><th>Name</th><th>Type</th><th>Status</th></tr>";
        while ($row = $device_data->fetch_assoc()) {
            echo "<tr>";
            echo "<td>{$row['device_id']}</td>";
            echo "<td>" . htmlspecialchars($row['device_name']) . "</td>";
            echo "<td>" . htmlspecialchars($row['device_type']) . "</td>";
            echo "<td>" . htmlspecialchars($row['status']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
}

// Check device assignment
if ($device_check->num_rows > 0 && $table_check->num_rows > 0) {
    $assignment_check = $conn->query("SHOW TABLES LIKE 'device_assignment'");
    echo "<p>Device Assignment table exists: " . ($assignment_check->num_rows > 0 ? "<span class='success'>YES</span>" : "<span class='error'>NO</span>") . "</p>";
    
    if ($assignment_check->num_rows > 0) {
        $assignment_count = $conn->query("SELECT COUNT(*) as count FROM device_assignment");
        $assignment_row = $assignment_count->fetch_assoc();
        echo "<p><strong>Total Device Assignments:</strong> " . $assignment_row['count'] . "</p>";
    }
}
echo "</div>";

// Section 4: API Test
echo "<div class='section'>";
echo "<h3>🔧 Rescuer API Test</h3>";

echo "<h4>Test Rescuer Creation API:</h4>";
echo "<form method='post' style='margin: 10px 0;'>";
echo "<input type='text' name='test_name' placeholder='Test Rescuer' required style='padding: 5px; margin: 2px;'>";
echo "<input type='email' name='test_email' placeholder='rescuer@test.com' required style='padding: 5px; margin: 2px;'>";
echo "<input type='tel' name='test_contact' placeholder='09123456789' required style='padding: 5px; margin: 2px;'>";
echo "<input type='password' name='test_password' placeholder='password' required style='padding: 5px; margin: 2px;'>";
echo "<button type='submit' name='test_create_rescuer' class='btn'>Test Create Rescuer</button>";
echo "</form>";

if (isset($_POST['test_create_rescuer'])) {
    $test_name = trim($_POST['test_name'] ?? '');
    $test_email = trim($_POST['test_email'] ?? '');
    $test_contact = trim($_POST['test_contact'] ?? '');
    $test_password = $_POST['test_password'] ?? '';
    
    if (!empty($test_name) && !empty($test_email) && !empty($test_contact) && !empty($test_password)) {
        try {
            // Check if email already exists
            $check_email = $conn->prepare("SELECT rescuer_id FROM rescuer WHERE rescuer_email = ?");
            $check_email->bind_param("s", $test_email);
            $check_email->execute();
            
            if ($check_email->get_result()->num_rows > 0) {
                echo "<p class='error'>❌ Email already exists</p>";
            } else {
                // Create rescuer
                $hashed_password = password_hash($test_password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO rescuer (rescuer_name, rescuer_email, rescuer_contact, rescuer_password, status) VALUES (?, ?, ?, ?, 'active')");
                $stmt->bind_param("ssss", $test_name, $test_email, $test_contact, $hashed_password);
                
                if ($stmt->execute()) {
                    echo "<p class='success'>✅ Test rescuer created successfully!</p>";
                    echo "<p>Created rescuer: " . htmlspecialchars($test_name) . " (" . htmlspecialchars($test_email) . ")</p>";
                } else {
                    echo "<p class='error'>❌ Failed to create test rescuer: " . $stmt->error . "</p>";
                }
            }
        } catch (Exception $e) {
            echo "<p class='error'>❌ Error: " . $e->getMessage() . "</p>";
        }
    } else {
        echo "<p class='error'>❌ Please fill all fields</p>";
    }
}

echo "<h4>Test Device Assignment:</h4>";
echo "<form method='post' style='margin: 10px 0;'>";
echo "<input type='number' name='rescuer_id' placeholder='Rescuer ID' required style='padding: 5px; margin: 2px;'>";
echo "<input type='number' name='device_id' placeholder='Device ID' required style='padding: 5px; margin: 2px;'>";
echo "<button type='submit' name='test_assign_device' class='btn'>Test Assign Device</button>";
echo "</form>";

if (isset($_POST['test_assign_device'])) {
    $rescuer_id = intval($_POST['rescuer_id'] ?? 0);
    $device_id = intval($_POST['device_id'] ?? 0);
    
    if ($rescuer_id > 0 && $device_id > 0) {
        try {
            $stmt = $conn->prepare("INSERT INTO device_assignment (rescuer_id, device_id, assigned_at) VALUES (?, ?, NOW())");
            $stmt->bind_param("ii", $rescuer_id, $device_id);
            
            if ($stmt->execute()) {
                echo "<p class='success'>✅ Device assigned successfully!</p>";
                echo "<p>Rescuer ID: $rescuer_id, Device ID: $device_id</p>";
            } else {
                echo "<p class='error'>❌ Failed to assign device: " . $stmt->error . "</p>";
            }
        } catch (Exception $e) {
            echo "<p class='error'>❌ Error: " . $e->getMessage() . "</p>";
        }
    } else {
        echo "<p class='error'>❌ Please provide valid rescuer ID and device ID</p>";
    }
}
echo "</div>";

// Section 5: Navigation
echo "<div class='section'>";
echo "<h3>🔗 Quick Links</h3>";
echo "<a href='../roles/management/manage_rescuers.php' class='btn'>Manage Rescuers</a>";
echo "<a href='admin_debug.php' class='btn'>Admin Debug</a>";
echo "<a href='management_debug.php' class='btn'>Management Debug</a>";
echo "<a href='responder_debug.php' class='btn'>Responder Debug</a>";
echo "</div>";

$conn->close();
?>
