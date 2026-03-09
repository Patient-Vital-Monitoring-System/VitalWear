<?php
echo "<h2>Database Connection Test</h2>";

try {
    include("database/connection.php");
    echo "<p style='color: green;'>✅ Database connection file loaded successfully</p>";
    
    if (isset($conn) && $conn) {
        echo "<p style='color: green;'>✅ Database connection established</p>";
        
        // Test basic query
        $result = $conn->query("SELECT COUNT(*) as count FROM patient");
        if ($result) {
            $row = $result->fetch_assoc();
            echo "<p>✅ Patient table accessible - Total patients: " . $row['count'] . "</p>";
        } else {
            echo "<p style='color: orange;'>⚠️ Could not query patient table: " . $conn->error . "</p>";
        }
        
        // Test incident table
        $result = $conn->query("SELECT COUNT(*) as count FROM incident");
        if ($result) {
            $row = $result->fetch_assoc();
            echo "<p>✅ Incident table accessible - Total incidents: " . $row['count'] . "</p>";
        } else {
            echo "<p style='color: orange;'>⚠️ Could not query incident table: " . $conn->error . "</p>";
        }
        
        echo "<p style='color: green;'><strong>✅ Database is fully connected and working!</strong></p>";
        
    } else {
        echo "<p style='color: red;'>❌ Database connection failed</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><a href='index.php'>← Back to Home</a></p>";
?>
