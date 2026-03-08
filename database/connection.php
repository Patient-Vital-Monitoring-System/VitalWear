<?php
/**
 * Database Connection for VitalWear
 * 
 * This file establishes the connection to the MySQL database.
 * Update the credentials below to match your local environment.
 * 
 * To test the connection, open this file in your browser:
 * http://localhost/VitalWear-1/database/connection.php
 */

// Database credentials
$db_host = 'localhost';
$db_name = 'vitalwear';
$db_user = 'root';
$db_pass = '';

// Check if accessed directly for testing
if (basename($_SERVER['PHP_SELF']) == 'connection.php') {
    echo "<h2>VitalWear Database Connection Test</h2>";
}

// Try to connect to MySQL server first to create database if not exists
$conn = @new mysqli($db_host, $db_user, $db_pass);

if ($conn->connect_error) {
    if (basename($_SERVER['PHP_SELF']) == 'connection.php') {
        die("<p style='color:red;'>❌ Connection failed: " . $conn->connect_error . "</p>");
    } else {
        die("Connection failed: " . $conn->connect_error);
    }
}

// Create database if not exists
$conn->query("CREATE DATABASE IF NOT EXISTS $db_name");
$conn->select_db($db_name);

if (basename($_SERVER['PHP_SELF']) == 'connection.php') {
    echo "<p style='color:green;'>✅ Connected successfully to MySQL server!</p>";
    
    // Check if database has tables
    $result = $conn->query("SHOW TABLES");
    $tableCount = $result->num_rows;
    
    echo "<p>Database: <strong>$db_name</strong></p>";
    echo "<p>Tables found: <strong>$tableCount</strong></p>";
    
    if ($tableCount > 0) {
        echo "<h3>Tables in Database:</h3>";
        echo "<ul>";
        while ($row = $result->fetch_array()) {
            echo "<li>" . $row[0] . "</li>";
        }
        echo "</ul>";
        
        // Check if there's data
        echo "<h3>Sample Data Check:</h3>";
        
        $tables = ['admin', 'management', 'responder', 'rescuer', 'device', 'patient'];
        foreach ($tables as $table) {
            $countResult = $conn->query("SELECT COUNT(*) as cnt FROM $table");
            $countRow = $countResult->fetch_assoc();
            echo "<p>$table: " . $countRow['cnt'] . " records</p>";
        }
    } else {
        echo "<p style='color:orange;'>⚠️ No tables found. Please import schema.sql and seed.sql in phpMyAdmin.</p>";
    }
    
    echo "<hr>";
    echo "<p><em>Connection file is working correctly. Include this file in other PHP files to use the database.</em></p>";
}

// Function to get database connection
function getDBConnection() {
    global $conn;
    return $conn;
}

// Function to close database connection
function closeDBConnection() {
    global $conn;
    if ($conn) {
        $conn->close();
    }
}
?>

