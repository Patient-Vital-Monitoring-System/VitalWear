<?php
require_once '../../../database/connection.php';

$conn = getDBConnection();

echo "<h2>Management Table Debug</h2>";

// Check if table exists
$table_check = $conn->query("SHOW TABLES LIKE 'management'");
echo "<p>Management table exists: " . ($table_check->num_rows > 0 ? "YES" : "NO") . "</p>";

if ($table_check->num_rows > 0) {
    // Check table structure
    $columns = $conn->query("SHOW COLUMNS FROM management");
    echo "<h3>Table Structure:</h3>";
    echo "<table border='1'>";
    echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th></tr>";
    while ($row = $columns->fetch_assoc()) {
        echo "<tr><td>{$row['Field']}</td><td>{$row['Type']}</td><td>{$row['Null']}</td><td>{$row['Key']}</td></tr>";
    }
    echo "</table>";
    
    // Check data count
    $count = $conn->query("SELECT COUNT(*) as count FROM management");
    $row = $count->fetch_assoc();
    echo "<h3>Data Count:</h3>";
    echo "<p>Total records: " . $row['count'] . "</p>";
    
    // Show sample data
    if ($row['count'] > 0) {
        echo "<h3>Sample Data:</h3>";
        $data = $conn->query("SELECT * FROM management LIMIT 5");
        echo "<table border='1'>";
        echo "<tr>";
        $columns = $data->fetch_fields();
        foreach ($columns as $column) {
            echo "<th>{$column->name}</th>";
        }
        echo "</tr>";
        while ($row = $data->fetch_assoc()) {
            echo "<tr>";
            foreach ($row as $value) {
                echo "<td>" . htmlspecialchars($value) . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    }
} else {
    echo "<p>Creating management table...</p>";
    $create_table = $conn->query("
        CREATE TABLE IF NOT EXISTS management (
            mgmt_id INT AUTO_INCREMENT PRIMARY KEY,
            mgmt_name VARCHAR(255) NOT NULL,
            mgmt_email VARCHAR(255) NOT NULL UNIQUE,
            mgmt_contact VARCHAR(255),
            mgmt_password VARCHAR(255),
            status ENUM('active', 'inactive') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    if ($create_table) {
        echo "<p>Management table created successfully!</p>";
        
        // Insert sample data
        $insert = $conn->prepare("INSERT INTO management (mgmt_name, mgmt_email, mgmt_contact, mgmt_password, status) VALUES (?, ?, ?, ?, 'active')");
        $hashed_password = password_hash('admin123', PASSWORD_DEFAULT);
        $insert->bind_param("ssss", 'John Manager', 'manager@vitalwear.com', '+1-555-0100', $hashed_password);
        $insert->execute();
        
        echo "<p>Sample management account created!</p>";
    } else {
        echo "<p>Error creating table: " . $conn->error . "</p>";
    }
}

echo "<p><a href='view_management.php'>Back to Management Page</a></p>";
?>
