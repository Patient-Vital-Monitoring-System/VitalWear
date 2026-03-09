<?php
require_once 'database/connection.php';

echo "<h2>Management Table Structure</h2>";

try {
    $result = $conn->query("DESCRIBE management");
    if ($result) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td><strong>" . htmlspecialchars($row['Field']) . "</strong></td>";
            echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Key']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Default'] ?? 'NULL') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        echo "<h3>Sample Data:</h3>";
        $sample = $conn->query("SELECT * FROM management LIMIT 1");
        if ($sample && $sample->num_rows > 0) {
            echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
            echo "<tr>";
            foreach (array_keys($sample->fetch_assoc()) as $field) {
                echo "<th>" . htmlspecialchars($field) . "</th>";
            }
            echo "</tr>";
            $sample->data_seek(0);
            while ($row = $sample->fetch_assoc()) {
                echo "<tr>";
                foreach ($row as $value) {
                    echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
                }
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p>No data in management table</p>";
        }
    } else {
        echo "<p style='color:red;'>Management table does not exist</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
