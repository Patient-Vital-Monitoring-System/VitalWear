<?php
session_start();
session_destroy();

// Handle both GET and POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo json_encode(['status' => 'success']);
    exit();
}

header("Location: ../../login.html");
exit();
?>
