<?php
// Minimal save_vitals with database
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

    $incident_id = $data['incident_id'];
    $heart_rate = $data['heart_rate'];
    $bp_systolic = $data['bp_systolic'];
    $bp_diastolic = $data['bp_diastolic'];
    $oxygen_level = $data['oxygen_level'];
    $rescuer_id = $_SESSION['user_id'];

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

    // Verify incident belongs to this rescuer and is ongoing
    $verify_query = "SELECT incident_id FROM incident WHERE incident_id = ? AND resc_id = ? AND status = 'ongoing'";
    $stmt = $conn->prepare($verify_query);
    
    if (!$stmt) {
        echo json_encode([
            'success' => false, 
            'message' => 'Prepare failed',
            'debug' => $conn->error
        ]);
        exit;
    }
    
    $stmt->bind_param("ii", $incident_id, $rescuer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode([
            'success' => false, 
            'message' => 'Incident not found or not ongoing'
        ]);
        exit;
    }

    // Insert vital signs
    $insert_query = "INSERT INTO vitalstat (incident_id, heart_rate, bp_systolic, bp_diastolic, oxygen_level, recorded_by, recorded_at) 
                     VALUES (?, ?, ?, ?, ?, 'rescuer', NOW())";
    $stmt = $conn->prepare($insert_query);
    
    if (!$stmt) {
        echo json_encode([
            'success' => false, 
            'message' => 'Insert prepare failed',
            'debug' => $conn->error
        ]);
        exit;
    }
    
    $stmt->bind_param("idiii", $incident_id, $heart_rate, $bp_systolic, $bp_diastolic, $oxygen_level);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true, 
            'message' => 'Vital signs saved successfully',
            'insert_id' => $conn->insert_id
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Execute failed',
            'debug' => $stmt->error
        ]);
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Exception occurred',
        'debug' => $e->getMessage()
    ]);
}
?>
