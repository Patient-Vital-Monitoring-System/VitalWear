<?php
// Database migration script to fix incident creation
include("connection.php");

echo "<h2>VitalWear Database Migration</h2>";

try {
    // Make log_id nullable in incident table
    $sql = "ALTER TABLE incident MODIFY COLUMN log_id INT DEFAULT NULL";
    
    if ($conn->query($sql)) {
        echo "<p style='color:green;'>✅ Successfully updated incident table - log_id is now nullable</p>";
        
        // Verify the change
        $result = $conn->query("DESCRIBE incident");
        echo "<h3>Updated Incident Table Structure:</h3>";
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
        
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['Field'] . "</td>";
            echo "<td>" . $row['Type'] . "</td>";
            echo "<td>" . $row['Null'] . "</td>";
            echo "<td>" . $row['Key'] . "</td>";
            echo "<td>" . $row['Default'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        echo "<p style='color:blue;'><strong>Migration completed successfully!</strong></p>";
        echo "<p>Responders can now create incidents without requiring an assigned device.</p>";
        
    } else {
        echo "<p style='color:red;'>❌ Error updating table: " . $conn->error . "</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color:red;'>❌ Migration failed: " . $e->getMessage() . "</p>";
}

$conn->close();
?>
