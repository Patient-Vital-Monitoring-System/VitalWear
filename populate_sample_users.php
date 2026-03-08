<?php
require_once '../../database/connection.php';

// Sample data insertion for testing user management page
$conn = getDBConnection();

echo "<h3>Populating Sample User Data</h3>";

// Insert sample admin if not exists
$admin_check = $conn->query("SELECT COUNT(*) as count FROM admin WHERE admin_email = 'admin1@vitalwear.com'");
$admin_count = $admin_check->fetch_assoc()['count'];

if ($admin_count == 0) {
    $stmt = $conn->prepare("INSERT INTO admin (admin_name, admin_email, admin_password) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", "System Admin", "admin1@vitalwear.com", md5('admin123'));
    if ($stmt->execute()) {
        echo "✅ Admin user created: admin1@vitalwear.com<br>";
    }
} else {
    echo "ℹ️ Admin user already exists<br>";
}

// Insert sample management if not exists
$mgmt_check = $conn->query("SELECT COUNT(*) as count FROM management WHERE mgmt_email = 'manager@vitalwear.com'");
$mgmt_count = $mgmt_check->fetch_assoc()['count'];

if ($mgmt_count == 0) {
    $stmt = $conn->prepare("INSERT INTO management (mgmt_name, mgmt_email, mgmt_password) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", "Operations Manager", "manager@vitalwear.com", md5('manager123'));
    if ($stmt->execute()) {
        echo "✅ Management user created: manager@vitalwear.com<br>";
    }
} else {
    echo "ℹ️ Management user already exists<br>";
}

// Insert sample responder if not exists
$resp_check = $conn->query("SELECT COUNT(*) as count FROM responder WHERE resp_email = 'responder@vitalwear.com'");
$resp_count = $resp_check->fetch_assoc()['count'];

if ($resp_count == 0) {
    $stmt = $conn->prepare("INSERT INTO responder (resp_name, resp_email, resp_password, resp_contact, status) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", "Juan Dela Cruz", "responder@vitalwear.com", md5('resp123'), '09123456789', 'active');
    if ($stmt->execute()) {
        echo "✅ Responder user created: responder@vitalwear.com<br>";
    }
} else {
    echo "ℹ️ Responder user already exists<br>";
}

// Insert sample rescuer if not exists
$resc_check = $conn->query("SELECT COUNT(*) as count FROM rescuer WHERE resc_email = 'rescuer@vitalwear.com'");
$resc_count = $resc_check->fetch_assoc()['count'];

if ($resc_count == 0) {
    $stmt = $conn->prepare("INSERT INTO rescuer (resc_name, resc_email, resc_password, resc_contact, status) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", "Maria Santos", "rescuer@vitalwear.com", md5('resc123'), '09987654321', 'active');
    if ($stmt->execute()) {
        echo "✅ Rescuer user created: rescuer@vitalwear.com<br>";
    }
} else {
    echo "ℹ️ Rescuer user already exists<br>";
}

echo "<br><h3>Database Status Check</h3>";

// Check table counts
$tables = ['admin', 'management', 'responder', 'rescuer'];
foreach ($tables as $table) {
    $result = $conn->query("SELECT COUNT(*) as count FROM $table");
    $count = $result->fetch_assoc()['count'];
    echo "📊 $table table: $count users<br>";
}

echo "<br><strong>✅ Sample data population complete!</strong><br>";
echo "<small>You can now visit the user management page to see the data.</small>";
?>
