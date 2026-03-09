<?php
require_once '../../../../../database/connection.php';

echo "<h2>Admin Table Structure Check</h2>";

$conn = getDBConnection();

// Check if table exists
$table_check = $conn->query("SHOW TABLES LIKE 'admin'");
echo "<p>Admin table exists: " . ($table_check->num_rows > 0 ? "YES" : "NO") . "</p>";

if ($table_check->num_rows > 0) {
    // Show actual table structure
    echo "<h3>Actual Table Structure:</h3>";
    $columns = $conn->query("SHOW COLUMNS FROM admin");
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
    $test_query = "INSERT INTO admin (admin_name, admin_email, admin_password) VALUES (?, ?, ?)";
    echo "<p>Query: " . htmlspecialchars($test_query) . "</p>";
    
    $test_stmt = $conn->prepare($test_query);
    if ($test_stmt) {
        echo "<p style='color: green;'>✅ Query prepares successfully!</p>";
    } else {
        echo "<p style='color: red;'>❌ Query preparation failed: " . $conn->error . "</p>";
    }
    
    // Show total count
    $count = $conn->query("SELECT COUNT(*) as count FROM admin");
    $row = $count->fetch_assoc();
    echo "<h3>Total Records:</h3>";
    echo "<p><strong>{$row['count']}</strong> admin accounts found</p>";
    
} else {
    echo "<p style='color: red;'>Admin table does not exist!</p>";
}

echo "<hr>";
echo "<p><a href='../view_admins.php'>← Back to Admin Page</a></p>";
?>
