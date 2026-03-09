<?php
session_start();

// Destroy all session data
session_unset();
session_destroy();

// Clear session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Handle both GET and POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo json_encode(['status' => 'success']);
    exit();
}

// Redirect to login page with session clearing
?>
<!DOCTYPE html>
<html>
<head>
    <title>Logging out...</title>
    <script>
        // Clear all session storage
        sessionStorage.clear();
        // Clear local storage too
        localStorage.clear();
        // Redirect to login
        window.location.href = '/VitalWear-1/login.html';
    </script>
</head>
<body>
    <p>Logging out...</p>
</body>
</html>
