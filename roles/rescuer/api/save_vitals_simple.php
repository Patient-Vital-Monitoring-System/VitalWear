<?php
// Minimal save_vitals without database dependency
session_start();

// Disable all error display
ini_set('display_errors', 0);
error_reporting(0);

// Set JSON header first
header('Content-Type: application/json');

try {
    // Check if user is logged in as rescuer
    if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'rescuer') {
        echo json_encode([
            'success' => false, 
            'message' => 'Unauthorized',
            'debug' => 'Session check failed'
        ]);
        exit;
    }

    // Get POST data
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['incident_id']) || !isset($data['heart_rate']) || !isset($data['bp_systolic']) || 
        !isset($data['bp_diastolic']) || !isset($data['oxygen_level'])) {
        echo json_encode([
            'success' => false, 
            'message' => 'Missing required fields',
            'debug' => $data
        ]);
        exit;
    }

    // Simulate success (without database)
    echo json_encode([
        'success' => true, 
        'message' => 'Vital signs would be saved (test mode)',
        'data_received' => $data
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Exception occurred',
        'debug' => $e->getMessage()
    ]);
}
?>
