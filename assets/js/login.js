document.addEventListener('DOMContentLoaded', function() {
  const loginForm = document.getElementById('loginForm');
  const loginBtn = document.getElementById('loginBtn');
  const errorMessage = document.getElementById('errorMessage');
  const successMessage = document.getElementById('successMessage');

  // Check if already logged in
  if (sessionStorage.getItem('responder_id')) {
    window.location.href = 'roles/responder/dashboard.php';
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

      const response = await fetch('api/auth/login.php', {
        method: 'POST',
        body: formData
      });

      const data = await response.json();

      if (data.status === 'success') {
        // Store session data
        sessionStorage.setItem('responder_id', data.responder_id);
        sessionStorage.setItem('responder_name', data.responder_name);
        sessionStorage.setItem('user_role', 'responder');

        successMessage.style.display = 'block';
        
        // Redirect to responder dashboard
        setTimeout(() => {
          window.location.href = 'roles/responder/dashboard.php';
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

