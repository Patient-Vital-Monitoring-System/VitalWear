<?php
require_once '../../../database/connection.php';

echo "Management Table Check\n";

$conn = getDBConnection();

// Check if table exists
$table_check = $conn->query("SHOW TABLES LIKE 'management'");
echo "Management table exists: " . ($table_check->num_rows > 0 ? "YES" : "NO") . "\n";

if ($table_check->num_rows > 0) {
    // Check data count
    $count = $conn->query("SELECT COUNT(*) as count FROM management");
    $row = $count->fetch_assoc();
    echo "Total records: " . $row['count'] . "\n";
    
    // Show sample data
    if ($row['count'] > 0) {
        echo "Sample data:\n";
        $data = $conn->query("SELECT mgmt_id, mgmt_name, mgmt_email FROM management LIMIT 3");
        while ($row = $data->fetch_assoc()) {
            echo "ID: {$row['mgmt_id']}, Name: {$row['mgmt_name']}, Email: {$row['mgmt_email']}\n";
        }
    }
} else {
    echo "Creating management table...\n";
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
        echo "Management table created successfully!\n";
        
        // Insert sample data
        $insert = $conn->prepare("INSERT INTO management (mgmt_name, mgmt_email, mgmt_contact, mgmt_password, status) VALUES (?, ?, ?, ?, 'active')");
        $hashed_password = password_hash('manager123', PASSWORD_DEFAULT);
        $insert->bind_param("ssss", 'John Manager', 'manager@vitalwear.com', '+1-555-0100', $hashed_password);
        $insert->execute();
        
        echo "Sample management account created!\n";
    } else {
        echo "Error creating table: " . $conn->error . "\n";
    }
}
?>
