document.addEventListener('DOMContentLoaded', function() {
  const loginForm = document.getElementById('loginForm');
  const loginBtn = document.getElementById('loginBtn');
  const errorMessage = document.getElementById('errorMessage');
  const successMessage = document.getElementById('successMessage');

  // Check if already logged in
  const userRole = sessionStorage.getItem('user_role');
  if (sessionStorage.getItem('user_id')) {
    // Redirect based on user role
    switch(userRole) {
      case 'admin':
        window.location.href = '/VitalWear-1/roles/admin/dashboard.php';
        break;
      case 'management':
        window.location.href = '/VitalWear-1/roles/management/dashboard.php';
        break;
      case 'responder':
        window.location.href = '/VitalWear-1/roles/responder/dashboard.php';
        break;
      case 'rescuer':
        window.location.href = '/VitalWear-1/roles/rescuer/dashboard.php';
        break;
      default:
        window.location.href = '/VitalWear-1/roles/responder/dashboard.php';
    }
    return;
  }

  loginForm.addEventListener('submit', async function(e) {
    e.preventDefault();

    // Clear previous messages
    errorMessage.style.display = 'none';
    successMessage.style.display = 'none';

    const email = document.getElementById('email').value.trim();
    const password = document.getElementById('password').value;

    // Validate inputs
    if (!email || !password) {
      showError('Please enter both email and password');
      return; 
    }

    // Disable button during request
    loginBtn.disabled = true;
    loginBtn.textContent = 'Signing in...';

    try {
      const formData = new FormData();
      formData.append('email', email);
      formData.append('password', password);

      const response = await fetch('/VitalWear-1/api/auth/login.php', {
        method: 'POST',
        body: formData
      });

      const data = await response.json();

      if (data.status === 'success') {
        // Store session data
        sessionStorage.setItem('user_id', data.user_id);
        sessionStorage.setItem('user_name', data.user_name);
        sessionStorage.setItem('user_role', data.user_role);

        // Sync with PHP session
        try {
          const formData = new FormData();
          formData.append('user_id', data.user_id);
          formData.append('user_name', data.user_name);
          formData.append('user_role', data.user_role);
          
          await fetch('/VitalWear-1/api/auth/session_bridge.php', {
            method: 'POST',
            body: formData
          });
        } catch (error) {
          console.warn('Session sync failed:', error);
        }

        successMessage.style.display = 'block';
        
        // Redirect based on user role
        setTimeout(() => {
          switch(data.user_role) {
            case 'admin':
              window.location.href = '/VitalWear-1/roles/admin/dashboard.php';
              break;
            case 'management':
              window.location.href = '/VitalWear-1/roles/management/dashboard.php';
              break;
            case 'responder':
              window.location.href = '/VitalWear-1/roles/responder/dashboard.php';
              break;
            case 'rescuer':
              window.location.href = '/VitalWear-1/roles/rescuer/dashboard.php';
              break;
            default:
              window.location.href = '/VitalWear-1/roles/responder/dashboard.php';
          }
        }, 1000);
      } else if (data.status === 'empty') {
        showError('Please enter both email and password');
      } else if (data.status === 'invalid') {
        showError('Invalid email or password');
      } else {
        showError('Login failed. Please try again.');
      }
    } catch (error) {
      console.error('Login error:', error);
      showError('Connection error. Please check if the server is running.');
    } finally {
      loginBtn.disabled = false;
      loginBtn.textContent = 'Sign In';
    }
  });

  function showError(message) {
    errorMessage.textContent = message;
    errorMessage.style.display = 'block';
  }
});

