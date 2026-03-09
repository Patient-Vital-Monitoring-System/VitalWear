<?php
// Test API endpoint to isolate the issue
session_start();

// Disable all error display
ini_set('display_errors', 0);
error_reporting(0);

// Set JSON header first - no output before this
header('Content-Type: application/json');

// Simple test response
echo json_encode([
    'success' => true,
    'message' => 'Test API working',
    'session_data' => [
        'user_id' => $_SESSION['user_id'] ?? 'not set',
        'user_role' => $_SESSION['user_role'] ?? 'not set'
    ]
]);
?>
