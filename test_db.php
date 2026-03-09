<?php
require_once 'database/connection.php';

echo "<h2>Database Structure Check</h2>";

// Check management table
echo "<h3>Management Table:</h3>";
try {
    $result = $conn->query("DESCRIBE management");
    if ($result) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['Field']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Key']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color:red;'>Management table does not exist</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Check admin table
echo "<h3>Admin Table:</h3>";
try {
    $result = $conn->query("DESCRIBE admin");
    if ($result) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['Field']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Key']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color:red;'>Admin table does not exist</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<h3>All Tables:</h3>";
try {
    $result = $conn->query("SHOW TABLES");
    if ($result) {
        echo "<ul>";
        while ($row = $result->fetch_array()) {
            echo "<li>" . htmlspecialchars($row[0]) . "</li>";
        }
        echo "</ul>";
    }
} catch (Exception $e) {
    echo "<p style='color:red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
