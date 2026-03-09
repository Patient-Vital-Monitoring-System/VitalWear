<?php
// Test database connection
session_start();

// Disable all error display
ini_set('display_errors', 0);
error_reporting(0);

// Set JSON header first
header('Content-Type: application/json');

// Use the same connection method as connection.php
$db_host = 'localhost';
$db_name = 'vitalwear';
$db_user = 'root';
$db_pass = '';

// Connect to MySQL server first
$conn = @new mysqli($db_host, $db_user, $db_pass);

if ($conn->connect_error) {
    echo json_encode([
        'success' => false, 
        'message' => 'Database connection failed',
        'debug' => $conn->connect_error
    ]);
    exit;
}

// Create database if not exists and select it
$conn->query("CREATE DATABASE IF NOT EXISTS $db_name");
$conn->select_db($db_name);

// Test if vitalstat table exists
$result = $conn->query("SHOW TABLES LIKE 'vitalstat'");
$table_exists = $result->num_rows > 0;

echo json_encode([
    'success' => true, 
    'message' => 'Database connection successful',
    'database' => $db_name,
    'vitalstat_table_exists' => $table_exists,
    'session_user_id' => $_SESSION['user_id'] ?? 'not set',
    'session_user_role' => $_SESSION['user_role'] ?? 'not set'
]);
?>
