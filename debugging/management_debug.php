<?php
require_once '../../../../../database/connection.php';

echo "<h2>Management Role Debugging Tools</h2>";
echo "<style>
    .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
    .success { color: green; }
    .error { color: red; }
    .info { color: blue; }
    table { border-collapse: collapse; width: 100%; margin: 10px 0; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #f2f2f2; }
    .btn { padding: 8px 15px; margin: 5px; background: #28a745; color: white; text-decoration: none; border-radius: 3px; }
</style>";

$conn = getDBConnection();

// Section 1: Table Structure Check
echo "<div class='section'>";
echo "<h3>📊 Management Table Structure</h3>";

$table_check = $conn->query("SHOW TABLES LIKE 'management'");
echo "<p>Management table exists: " . ($table_check->num_rows > 0 ? "<span class='success'>YES</span>" : "<span class='error'>NO</span>") . "</p>";

if ($table_check->num_rows > 0) {
    echo "<h4>Table Structure:</h4>";
    $columns = $conn->query("SHOW COLUMNS FROM management");
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
    
    // Expected vs Actual comparison
    echo "<h4>Expected vs Actual Columns:</h4>";
    $expected_columns = ['mgmt_id', 'mgmt_name', 'mgmt_email', 'mgmt_password'];
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
    
    // Check for missing columns that were removed from code
    $removed_columns = ['mgmt_contact', 'status', 'created_at'];
    foreach ($removed_columns as $col) {
        if (in_array($col, $actual_columns)) {
            echo "<p class='info'>ℹ️ $col - Present (optional)</p>";
        } else {
            echo "<p class='info'>ℹ️ $col - Not present (handled in code)</p>";
        }
    }
}
echo "</div>";

// Section 2: Data Check
echo "<div class='section'>";
echo "<h3>📋 Management Data Check</h3>";

if ($table_check->num_rows > 0) {
    $count = $conn->query("SELECT COUNT(*) as count FROM management");
    $row = $count->fetch_assoc();
    echo "<p><strong>Total Management Accounts:</strong> " . $row['count'] . "</p>";
    
    if ($row['count'] > 0) {
        echo "<h4>Sample Management Accounts:</h4>";
        $data = $conn->query("SELECT mgmt_id, mgmt_name, mgmt_email FROM management LIMIT 5");
        echo "<table>";
        echo "<tr><th>ID</th><th>Name</th><th>Email</th></tr>";
        while ($row = $data->fetch_assoc()) {
            echo "<tr>";
            echo "<td>{$row['mgmt_id']}</td>";
            echo "<td>" . htmlspecialchars($row['mgmt_name']) . "</td>";
            echo "<td>" . htmlspecialchars($row['mgmt_email']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='info'>ℹ️ No management accounts found. You can create test accounts below.</p>";
    }
}
echo "</div>";

// Section 3: API Test
echo "<div class='section'>";
echo "<h3>🔧 Management API Test</h3>";

echo "<h4>Test Management Creation API:</h4>";
echo "<form method='post' style='margin: 10px 0;'>";
echo "<input type='text' name='test_name' placeholder='Test Manager' required style='padding: 5px; margin: 2px;'>";
echo "<input type='email' name='test_email' placeholder='manager@test.com' required style='padding: 5px; margin: 2px;'>";
echo "<input type='password' name='test_password' placeholder='password' required style='padding: 5px; margin: 2px;'>";
echo "<button type='submit' name='test_create_management' class='btn'>Test Create Management</button>";
echo "</form>";

if (isset($_POST['test_create_management'])) {
    $test_name = trim($_POST['test_name'] ?? '');
    $test_email = trim($_POST['test_email'] ?? '');
    $test_password = $_POST['test_password'] ?? '';
    
    if (!empty($test_name) && !empty($test_email) && !empty($test_password)) {
        try {
            // Check if email already exists
            $check_email = $conn->prepare("SELECT mgmt_id FROM management WHERE mgmt_email = ?");
            $check_email->bind_param("s", $test_email);
            $check_email->execute();
            
            if ($check_email->get_result()->num_rows > 0) {
                echo "<p class='error'>❌ Email already exists</p>";
            } else {
                // Create management
                $hashed_password = password_hash($test_password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO management (mgmt_name, mgmt_email, mgmt_password) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $test_name, $test_email, $hashed_password);
                
                if ($stmt->execute()) {
                    echo "<p class='success'>✅ Test management account created successfully!</p>";
                    echo "<p>Created account: " . htmlspecialchars($test_name) . " (" . htmlspecialchars($test_email) . ")</p>";
                } else {
                    echo "<p class='error'>❌ Failed to create test management: " . $stmt->error . "</p>";
                }
            }
        } catch (Exception $e) {
            echo "<p class='error'>❌ Error: " . $e->getMessage() . "</p>";
        }
    } else {
        echo "<p class='error'>❌ Please fill all fields</p>";
    }
}

echo "<h4>Test Management Update API:</h4>";
echo "<form method='post' style='margin: 10px 0;'>";
echo "<input type='number' name='update_id' placeholder='Management ID' required style='padding: 5px; margin: 2px;'>";
echo "<input type='text' name='update_name' placeholder='New Name' required style='padding: 5px; margin: 2px;'>";
echo "<input type='email' name='update_email' placeholder='New Email' required style='padding: 5px; margin: 2px;'>";
echo "<button type='submit' name='test_update_management' class='btn'>Test Update Management</button>";
echo "</form>";

if (isset($_POST['test_update_management'])) {
    $update_id = intval($_POST['update_id'] ?? 0);
    $update_name = trim($_POST['update_name'] ?? '');
    $update_email = trim($_POST['update_email'] ?? '');
    
    if ($update_id > 0 && !empty($update_name) && !empty($update_email)) {
        try {
            $stmt = $conn->prepare("UPDATE management SET mgmt_name = ?, mgmt_email = ? WHERE mgmt_id = ?");
            $stmt->bind_param("ssi", $update_name, $update_email, $update_id);
            
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    echo "<p class='success'>✅ Management account updated successfully!</p>";
                } else {
                    echo "<p class='error'>❌ No management found with ID $update_id or no changes made</p>";
                }
            } else {
                echo "<p class='error'>❌ Failed to update management: " . $stmt->error . "</p>";
            }
        } catch (Exception $e) {
            echo "<p class='error'>❌ Error: " . $e->getMessage() . "</p>";
        }
    } else {
        echo "<p class='error'>❌ Please provide valid management ID, name, and email</p>";
    }
}
echo "</div>";

// Section 4: Navigation
echo "<div class='section'>";
echo "<h3>🔗 Quick Links</h3>";
echo "<a href='../view_management.php' class='btn'>View Management Page</a>";
echo "<a href='admin_debug.php' class='btn'>Admin Debug</a>";
echo "<a href='responder_debug.php' class='btn'>Responder Debug</a>";
echo "<a href='rescuer_debug.php' class='btn'>Rescuer Debug</a>";
echo "</div>";

$conn->close();
?>
