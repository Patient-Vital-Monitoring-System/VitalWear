<script>
function logout() {
    if (confirm('Are you sure you want to logout?')) {
        // Clear sessionStorage
        sessionStorage.clear();
        
        // Call PHP logout
        fetch('../../api/auth/logout.php', {
            method: 'POST'
        }).then(() => {
            // Redirect to login
            window.location.href = '../../login.html';
        }).catch(error => {
            console.error('Logout error:', error);
            // Still redirect even if fetch fails
            window.location.href = '../../login.html';
        });
    }
}
</script>
