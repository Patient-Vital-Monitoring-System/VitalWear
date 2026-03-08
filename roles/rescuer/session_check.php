<?php
// Session synchronization for rescuer pages
session_start();

// Check if sessionStorage has data but PHP session doesn't
if (!isset($_SESSION['user_id'])) {
    ?>
    <script>
    // Check sessionStorage and sync with PHP session
    const userId = sessionStorage.getItem('user_id');
    const userName = sessionStorage.getItem('user_name');
    const userRole = sessionStorage.getItem('user_role');
    
    if (userId && userName && userRole) {
        // Sync with PHP session via AJAX
        const formData = new FormData();
        formData.append('user_id', userId);
        formData.append('user_name', userName);
        formData.append('user_role', userRole);
        
        fetch('../../api/auth/session_bridge.php', {
            method: 'POST',
            body: formData
        }).then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                // Reload the page to apply session changes
                window.location.reload();
            } else {
                // Redirect to login if session sync fails
                window.location.href = '../../login.html';
            }
        })
        .catch(error => {
            console.error('Session sync error:', error);
            window.location.href = '../../login.html';
        });
    } else {
        // No session data, redirect to login
        window.location.href = '../../login.html';
    }
    </script>
    <?php
    exit();
}

// Verify user role is rescuer
if ($_SESSION['user_role'] !== 'rescuer') {
    ?>
    <script>
    window.location.href = '../../login.html';
    </script>
    <?php
    exit();
}
?>
