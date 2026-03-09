<?php
require_once '../../../database/connection.php';

echo "<h2>Management Table Structure Check</h2>";

$conn = getDBConnection();

// Check if table exists
$table_check = $conn->query("SHOW TABLES LIKE 'management'");
echo "<p>Management table exists: " . ($table_check->num_rows > 0 ? "YES" : "NO") . "</p>";

if ($table_check->num_rows > 0) {
    // Show actual table structure
    echo "<h3>Actual Table Structure:</h3>";
    $columns = $conn->query("SHOW COLUMNS FROM management");
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr style='background: #f0f0f0;'><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    while ($row = $columns->fetch_assoc()) {
        echo "<tr>";
        echo "<td style='padding: 5px;'><strong>{$row['Field']}</strong></td>";
        echo "<td style='padding: 5px;'>{$row['Type']}</td>";
        echo "<td style='padding: 5px;'>{$row['Null']}</td>";
        echo "<td style='padding: 5px;'>{$row['Key']}</td>";
        echo "<td style='padding: 5px;'>{$row['Default']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Test the corrected query
    echo "<h3>Testing Corrected Query:</h3>";
    $test_query = "
        SELECT mgmt_id as id, mgmt_name as name, mgmt_email as email, 
               NULL as contact, 'management' as role, 'active' as status, 
               CURRENT_TIMESTAMP as created_at
        FROM management 
        ORDER BY mgmt_name
        LIMIT 3
    ";
    
    $result = $conn->query($test_query);
    if ($result) {
        echo "<p style='color: green;'>✅ Query executed successfully!</p>";
        echo "<h4>Sample Data:</h4>";
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr style='background: #f0f0f0;'><th>id</th><th>name</th><th>email</th><th>contact</th><th>role</th><th>status</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td style='padding: 5px;'>{$row['id']}</td>";
            echo "<td style='padding: 5px;'>{$row['name']}</td>";
            echo "<td style='padding: 5px;'>{$row['email']}</td>";
            echo "<td style='padding: 5px;'>{$row['contact']}</td>";
            echo "<td style='padding: 5px;'>{$row['role']}</td>";
            echo "<td style='padding: 5px;'>{$row['status']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: red;'>❌ Query failed: " . $conn->error . "</p>";
    }
    
    // Show total count
    $count = $conn->query("SELECT COUNT(*) as count FROM management");
    $row = $count->fetch_assoc();
    echo "<h3>Total Records:</h3>";
    echo "<p><strong>{$row['count']}</strong> management accounts found</p>";
    
} else {
    echo "<p style='color: red;'>Management table does not exist!</p>";
}

echo "<hr>";
echo "<p><a href='view_management.php'>← Back to Management Page</a></p>";
?>
