<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'rescuer') {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}
?>
