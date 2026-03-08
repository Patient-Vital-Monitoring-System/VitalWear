<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Test - VitalWear</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
        }
        
        .test-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #007bff;
        }
        
        .credentials {
            background: #fff3cd;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
        }
        
        .test-link {
            display: inline-block;
            padding: 10px 20px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 5px;
        }
        
        .test-link:hover {
            background: #0056b3;
        }
        
        .success {
            color: #28a745;
            font-weight: bold;
        }
        
        .info {
            color: #17a2b8;
        }
    </style>
</head>
<body>
    <h1>🔐 VitalWear Login Test</h1>
    
    <div class="test-section">
        <h2>📱 Responder Access Test</h2>
        <p>Test the following responder accounts to verify they can access the system:</p>
        
        <div class="credentials">
            <h3>Test Credentials:</h3>
            <ul>
                <li><strong>Email:</strong> juan@responder.com | <strong>Password:</strong> resp123</li>
                <li><strong>Email:</strong> mark@responder.com | <strong>Password:</strong> resp123</li>
                <li><strong>Email:</strong> leo@responder.com | <strong>Password:</strong> resp123</li>
                <li><strong>Email:</strong> pedro@responder.com | <strong>Password:</strong> resp123</li>
                <li><strong>Email:</strong> john@responder.com | <strong>Password:</strong> resp123</li>
            </ul>
        </div>
        
        <a href="../login.html" class="test-link">🚀 Go to Login Page</a>
        <a href="../roles/responder/dashboard.php" class="test-link">📊 Test Responder Dashboard (Direct)</a>
    </div>
    
    <div class="test-section">
        <h2>🆘 Rescuer Access Test</h2>
        <p>Test the following rescuer accounts:</p>
        
        <div class="credentials">
            <h3>Test Credentials:</h3>
            <ul>
                <li><strong>Email:</strong> maria@rescuer.com | <strong>Password:</strong> resc123</li>
                <li><strong>Email:</strong> ana@rescuer.com | <strong>Password:</strong> resc123</li>
                <li><strong>Email:</strong> david@rescuer.com | <strong>Password:</strong> resc123</li>
            </ul>
        </div>
        
        <a href="../roles/rescuer/dashboard.php" class="test-link">📊 Test Rescuer Dashboard (Direct)</a>
    </div>
    
    <div class="test-section">
        <h2>👨‍💼 Management Access Test</h2>
        <p>Test management accounts:</p>
        
        <div class="credentials">
            <h3>Test Credentials:</h3>
            <ul>
                <li><strong>Email:</strong> ops@vitalwear.com | <strong>Password:</strong> manager123</li>
                <li><strong>Email:</strong> field@vitalwear.com | <strong>Password:</strong> manager123</li>
            </ul>
        </div>
        
        <a href="../roles/management/dashboard.php" class="test-link">📊 Test Management Dashboard (Direct)</a>
    </div>
    
    <div class="test-section">
        <h2>🔧 What Was Fixed</h2>
        <div class="success">✅ Password Authentication:</div>
        <p>Updated login system to handle both MD5 (seed data) and modern password hashing (new users)</p>
        
        <div class="success">✅ Session Variables:</div>
        <p>Fixed all responder pages to use unified session variables ($_SESSION['user_id'], $_SESSION['user_role'])</p>
        
        <div class="success">✅ Role Verification:</div>
        <p>Added proper role checking to ensure users can only access their designated areas</p>
        
        <div class="info">ℹ️ Note:</div>
        <p>Rescuer pages were already properly configured with session management</p>
    </div>
    
    <div class="test-section">
        <h2>🧪 Testing Steps</h2>
        <ol>
            <li>Try to access responder dashboard directly - should redirect to login</li>
            <li>Login with responder credentials - should access dashboard successfully</li>
            <li>Verify dashboard shows responder name and assigned devices</li>
            <li>Test navigation between different responder pages</li>
            <li>Test logout and login again</li>
        </ol>
    </div>
</body>
</html>
