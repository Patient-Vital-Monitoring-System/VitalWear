<?php
// Test database connection from API directory
echo "<h2>Database Connection Test from API Directory</h2>";

try {
    require_once '../../../../../database/connection.php';
    echo "<p style='color: green;'>✅ Database connection file found and loaded!</p>";
    
    $conn = getDBConnection();
    if ($conn) {
        echo "<p style='color: green;'>✅ Database connection successful!</p>";
        
        // Test admin table
        $result = $conn->query("SELECT COUNT(*) as count FROM admin");
        if ($result) {
            $row = $result->fetch_assoc();
            echo "<p>Admin table has {$row['count']} records</p>";
        } else {
            echo "<p style='color: orange;'>⚠️ Could not query admin table: " . $conn->error . "</p>";
        }
    } else {
        echo "<p style='color: red;'>❌ Database connection failed!</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><a href='../view_admins.php'>← Back to Admin Page</a></p>";
?>
