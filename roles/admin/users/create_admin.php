<?php
session_start();
require_once '../../../database/connection.php';

// Check if admin user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /VitalWear-1/login.html');
    exit();
}

$conn = getDBConnection();
$error_message = '';
$success_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $contact = trim($_POST['contact'] ?? '');
    
    // Validation
    if (empty($name) || empty($email) || empty($password)) {
        $error_message = "Name, email, and password are required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Please enter a valid email address.";
    } elseif (strlen($password) < 8) {
        $error_message = "Password must be at least 8 characters long.";
    } else {
        try {
            // Check if email already exists
            $check_stmt = $conn->prepare("SELECT admin_id FROM admin WHERE admin_email = ?");
            $check_stmt->bind_param("s", $email);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $error_message = "An account with this email already exists.";
            } else {
                // Check database connection
                if (!$conn) {
                    $error_message = "Database connection failed. Please check your database configuration.";
                } else {
                    try {
                        // Check if admin table exists
                        $table_check = $conn->query("SHOW TABLES LIKE 'admin'");
                        if ($table_check->num_rows == 0) {
                            $error_message = "Admin table does not exist in the database.";
                        } else {
                            // Check table structure to see which columns exist
                            $columns_result = $conn->query("SHOW COLUMNS FROM admin");
                            $existing_columns = [];
                            while ($row = $columns_result->fetch_assoc()) {
                                $existing_columns[] = $row['Field'];
                            }
                            
                            // Hash password
                            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                            
                            // Build INSERT query dynamically based on existing columns
                            $insert_columns = ['admin_name', 'admin_email', 'admin_password'];
                            $insert_values = [$name, $email, $hashed_password];
                            $insert_placeholders = ['?', '?', '?'];
                            $bind_types = "sss";
                            
                            // Add contact field if it exists
                            if (in_array('admin_contact', $existing_columns)) {
                                $insert_columns[] = 'admin_contact';
                                $insert_values[] = $contact;
                                $insert_placeholders[] = '?';
                                $bind_types .= "s";
                            }
                            
                            // Add status field if it exists
                            if (in_array('status', $existing_columns)) {
                                $insert_columns[] = 'status';
                                $insert_values[] = 'active';
                                $insert_placeholders[] = '?';
                                $bind_types .= "s";
                            }
                            
                            // Add created_at field if it exists
                            if (in_array('created_at', $existing_columns)) {
                                $insert_columns[] = 'created_at';
                                $insert_values[] = date('Y-m-d H:i:s');
                                $insert_placeholders[] = '?';
                                $bind_types .= "s";
                            }
                            
                            // Build the final INSERT query
                            $insert_query = "INSERT INTO admin (" . implode(', ', $insert_columns) . ") VALUES (" . implode(', ', $insert_placeholders) . ")";
                            $insert_stmt = $conn->prepare($insert_query);
                            
                            if (!$insert_stmt) {
                                $error_message = "Failed to prepare insert query: " . $conn->error . "<br><br>Available columns: " . implode(', ', $existing_columns);
                            } else {
                                // Bind parameters dynamically
                                $insert_stmt->bind_param($bind_types, ...$insert_values);
                                
                                if ($insert_stmt->execute()) {
                                    $success_message = "Admin account created successfully!";
                                    // Clear form
                                    $name = $email = $password = $contact = '';
                                } else {
                                    $error_message = "Error creating admin account: " . $insert_stmt->error;
                                }
                            }
                        }
                    } catch (Exception $e) {
                        $error_message = "Database error: " . $e->getMessage();
                    }
                }
            }
        } catch (Exception $e) {
            $error_message = "Error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Admin Account - VitalWear Admin</title>
    <script src="https://kit.fontawesome.com/96e37b53f1.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* VitalWear Brand Kit & Soft Edge Design System */
        :root {
            /* Brand Colors - VitalWear Identity */
            --brand-primary: #0066CC;
            --brand-secondary: #004499;
            --brand-accent: #00AAFF;
            --brand-success: #22C55E;
            --brand-warning: #F59E0B;
            --brand-danger: #EF4444;
            --brand-info: #3B82F6;
            
            /* Extended Color Palette */
            --primary-50: #F0F7FF;
            --primary-100: #E0EEFF;
            --primary-200: #C2DDFF;
            --primary-300: #A3C9FF;
            --primary-400: #85B5FF;
            --primary-500: #66A0FF;
            --primary-600: #0066CC;
            --primary-700: #004499;
            --primary-800: #003366;
            --primary-900: #002244;
            
            /* Neutral Palette - Soft & Modern */
            --neutral-50: #FAFBFC;
            --neutral-100: #F1F5F9;
            --neutral-200: #E2E8F0;
            --neutral-300: #CBD5E1;
            --neutral-400: #94A3B8;
            --neutral-500: #64748B;
            --neutral-600: #475569;
            --neutral-700: #334155;
            --neutral-800: #1E293B;
            --neutral-900: #0F172A;
            
            /* Semantic Colors */
            --success: #22C55E;
            --success-light: #F0FDF4;
            --success-bg: #DCFCE7;
            --warning: #F59E0B;
            --warning-light: #FFFBEB;
            --warning-bg: #FEF3C7;
            --error: #EF4444;
            --error-light: #FEF2F2;
            --error-bg: #FEE2E2;
            --info: #3B82F6;
            --info-light: #EFF6FF;
            --info-bg: #DBEAFE;
            
            /* Core Design Tokens */
            --primary: var(--brand-primary);
            --secondary: var(--brand-secondary);
            --accent: var(--brand-accent);
            --background: var(--neutral-50);
            --surface: #FFFFFF;
            --surface-elevated: #FAFBFC;
            --surface-overlay: rgba(255, 255, 255, 0.95);
            --text-primary: var(--neutral-900);
            --text-secondary: var(--neutral-600);
            --text-tertiary: var(--neutral-500);
            --text-inverse: #FFFFFF;
            --border: var(--neutral-200);
            --border-hover: var(--neutral-300);
            --border-focus: var(--brand-primary);
            
            /* Soft Edge Radius System */
            --radius-xs: 4px;
            --radius-sm: 6px;
            --radius: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
            --radius-xl: 20px;
            --radius-2xl: 24px;
            --radius-3xl: 32px;
            --radius-full: 9999px;
            
            /* Soft Shadow System */
            --shadow-xs: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-sm: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-md: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            --shadow-xl: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            --shadow-soft: 0 2px 20px rgba(0, 102, 204, 0.1);
            --shadow-soft-lg: 0 4px 30px rgba(0, 102, 204, 0.15);
            
            /* Transitions */
            --transition-fast: 150ms cubic-bezier(0.4, 0, 0.2, 1);
            --transition: 200ms cubic-bezier(0.4, 0, 0.2, 1);
            --transition-slow: 300ms cubic-bezier(0.4, 0, 0.2, 1);
            --transition-bounce: 400ms cubic-bezier(0.68, -0.55, 0.265, 1.55);
            
            /* Brand Gradients */
            --gradient-primary: linear-gradient(135deg, var(--brand-primary) 0%, var(--brand-secondary) 100%);
            --gradient-soft: linear-gradient(135deg, rgba(0, 102, 204, 0.1) 0%, rgba(0, 68, 153, 0.05) 100%);
            --gradient-success: linear-gradient(135deg, var(--success) 0%, #16A34A 100%);
            --gradient-danger: linear-gradient(135deg, var(--error) 0%, #DC2626 100%);
        }

        body {
            background: var(--background);
            color: var(--text-primary);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            margin: 0;
            padding: 0;
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            min-height: 100vh;
            position: relative;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(circle at 20% 80%, rgba(239, 68, 68, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(220, 38, 38, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 40% 40%, rgba(239, 68, 68, 0.05) 0%, transparent 50%);
            pointer-events: none;
            z-index: 0;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem;
            position: relative;
            z-index: 1;
        }

        .form-card {
            background: var(--surface);
            border-radius: var(--radius-2xl);
            box-shadow: var(--shadow-soft-lg);
            padding: 3rem;
            margin-top: 2rem;
            position: relative;
            overflow: hidden;
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all var(--transition-slow);
        }

        .form-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gradient-danger);
            opacity: 0.8;
        }

        .form-card::after {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: conic-gradient(from 0deg, transparent, rgba(239, 68, 68, 0.05), transparent);
            animation: rotate 20s linear infinite;
            pointer-events: none;
        }

        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .form-header {
            text-align: center;
            margin-bottom: 2.5rem;
            position: relative;
        }

        .form-header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 16px;
            position: relative;
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.1) 0%, rgba(220, 38, 38, 0.05) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .form-header h1::after {
            content: '';
            position: absolute;
            bottom: -12px;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 4px;
            background: var(--gradient-danger);
            border-radius: var(--radius-full);
            box-shadow: 0 2px 20px rgba(239, 68, 68, 0.1);
        }

        .form-header p {
            color: var(--text-secondary);
            margin-bottom: 0;
            font-size: 1.1rem;
            margin-top: 1.5rem;
            font-weight: 500;
        }

        .form-group {
            margin-bottom: 2rem;
            position: relative;
        }

        .form-group.has-icon .form-input {
            padding-right: 3rem;
        }

        .form-label {
            display: block;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.75rem;
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            position: relative;
        }

        .form-label::before {
            content: '';
            position: absolute;
            left: -12px;
            top: 50%;
            transform: translateY(-50%);
            width: 4px;
            height: 4px;
            background: var(--error);
            border-radius: var(--radius-full);
        }

        .form-input {
            width: 100%;
            padding: 1.25rem 1.5rem;
            border: 2px solid var(--border);
            border-radius: var(--radius-xl);
            font-size: 1rem;
            transition: all var(--transition);
            background: var(--surface-elevated);
            position: relative;
            font-family: 'Inter', sans-serif;
            font-weight: 500;
            box-shadow: var(--shadow-sm);
        }

        .form-input:hover {
            border-color: var(--border-focus);
            transform: translateY(-2px);
            box-shadow: 0 2px 20px rgba(239, 68, 68, 0.1);
        }

        .form-input:focus {
            outline: none;
            border-color: var(--border-focus);
            box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.15), 0 2px 20px rgba(239, 68, 68, 0.1);
            transform: translateY(-3px);
            background: var(--surface);
        }

        .form-input::placeholder {
            color: var(--text-tertiary);
            font-style: italic;
        }

        .form-input:required {
            background-image: radial-gradient(circle at 98% 8%, transparent 8px, var(--error) 8px, var(--error) 10px, transparent 10px);
        }

        .input-icon {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-tertiary);
            transition: all var(--transition);
            pointer-events: none;
        }

        .form-input:focus + .input-icon {
            color: var(--error);
            transform: translateY(-50%) scale(1.1);
        }

        .btn {
            padding: 1rem 2rem;
            border: none;
            border-radius: var(--radius-lg);
            font-weight: 600;
            cursor: pointer;
            transition: all var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 1rem;
            position: relative;
            overflow: hidden;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left var(--transition-slow);
        }

        .btn:hover::before {
            left: 100%;
        }

        .btn-primary {
            background: var(--gradient-danger);
            color: var(--text-inverse);
            box-shadow: 0 2px 20px rgba(239, 68, 68, 0.1);
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #DC2626 0%, #991B1B 100%);
            transform: translateY(-4px);
            box-shadow: 0 4px 30px rgba(239, 68, 68, 0.15);
        }

        .btn-primary:active {
            transform: translateY(-2px);
            box-shadow: 0 2px 20px rgba(239, 68, 68, 0.1);
        }

        .btn-secondary {
            background: var(--surface-elevated);
            color: var(--text-primary);
            border: 2px solid var(--border);
            font-weight: 600;
        }

        .btn-secondary:hover {
            background: var(--surface);
            border-color: var(--border-focus);
            transform: translateY(-3px);
            box-shadow: 0 2px 20px rgba(239, 68, 68, 0.1);
        }

        .btn-secondary:active {
            transform: translateY(-1px);
        }

        .success-message {
            background: var(--success-light);
            color: var(--success);
            padding: 1rem;
            border-radius: var(--radius);
            margin-bottom: 1rem;
            border: 1px solid var(--success);
        }

        .error-message {
            background: var(--error-light);
            color: var(--error);
            padding: 1rem;
            border-radius: var(--radius);
            margin-bottom: 1rem;
            border: 1px solid var(--error);
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 2rem;
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
            font-size: 0.875rem;
        }

        .breadcrumb a {
            color: var(--text-secondary);
            text-decoration: none;
        }

        .breadcrumb a:hover {
            color: var(--primary);
        }

        .admin-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            background: var(--error-bg);
            color: var(--error);
            border-radius: var(--radius);
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: var(--shadow-xs);
        }

        .required-indicator {
            color: var(--error);
            font-weight: 700;
            margin-left: 4px;
        }

        .form-group small {
            display: block;
            margin-top: 0.5rem;
            color: var(--text-tertiary);
            font-size: 0.875rem;
            font-style: italic;
            padding-left: 0.5rem;
            border-left: 2px solid var(--border);
        }

        .success-message {
            background: var(--success-bg);
            color: var(--success);
            padding: 1.25rem 1.5rem;
            border-radius: var(--radius-xl);
            margin-bottom: 1.5rem;
            border: 1px solid var(--success);
            display: flex;
            align-items: center;
            gap: 1rem;
            animation: slideIn 0.3s ease-out;
            box-shadow: var(--shadow-soft);
            font-weight: 600;
        }

        .error-message {
            background: var(--error-bg);
            color: var(--error);
            padding: 1.25rem 1.5rem;
            border-radius: var(--radius-xl);
            margin-bottom: 1.5rem;
            border: 1px solid var(--error);
            display: flex;
            align-items: center;
            gap: 1rem;
            animation: slideIn 0.3s ease-out;
            box-shadow: var(--shadow-soft);
            font-weight: 600;
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: space-between;
            margin-top: 3rem;
            padding-top: 2rem;
            border-top: 1px solid var(--border);
            position: relative;
        }

        .form-actions::before {
            content: '';
            position: absolute;
            top: -1px;
            left: 50%;
            transform: translateX(-50%);
            width: 60px;
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--error), transparent);
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .form-card {
            animation: fadeInUp 0.6s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Breadcrumb -->
        <div class="breadcrumb">
            <a href="../dashboard.php"><i class="fa fa-gauge"></i> Dashboard</a>
            <span>/</span>
            <a href="../users.php"><i class="fa fa-users"></i> Staff Directory</a>
            <span>/</span>
            <a href="view_admins.php"><i class="fa fa-user-cog"></i> Admins</a>
            <span>/</span>
            <span><i class="fa fa-plus"></i> Create</span>
        </div>

        <div class="form-card">
            <div class="form-header">
                <h1><i class="fa fa-user-cog"></i> Create Admin Account</h1>
                <p>Add a new administrator to the system <span class="admin-badge">High Privilege</span></p>
            </div>

            <?php if ($success_message): ?>
                <div class="success-message">
                    <i class="fa fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="error-message">
                    <i class="fa fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group has-icon">
                    <label for="name" class="form-label">Full Name<span class="required-indicator">*</span></label>
                    <input type="text" id="name" name="name" class="form-input" 
                           value="<?php echo htmlspecialchars($name ?? ''); ?>" 
                           placeholder="Enter full name" required>
                    <i class="fa fa-user input-icon"></i>
                </div>

                <div class="form-group has-icon">
                    <label for="email" class="form-label">Email Address<span class="required-indicator">*</span></label>
                    <input type="email" id="email" name="email" class="form-input" 
                           value="<?php echo htmlspecialchars($email ?? ''); ?>" 
                           placeholder="admin@example.com" required>
                    <i class="fa fa-envelope input-icon"></i>
                </div>

                <div class="form-group has-icon">
                    <label for="password" class="form-label">Password<span class="required-indicator">*</span></label>
                    <input type="password" id="password" name="password" class="form-input" 
                           placeholder="Create a strong password" required>
                    <i class="fa fa-lock input-icon"></i>
                    <small>Minimum 8 characters with mixed letters, numbers, and symbols</small>
                </div>

                <div class="form-group has-icon">
                    <label for="contact" class="form-label">Contact Number</label>
                    <input type="tel" id="contact" name="contact" class="form-input" 
                           value="<?php echo htmlspecialchars($contact ?? ''); ?>"
                           placeholder="+1 (555) 123-4567">
                    <i class="fa fa-phone input-icon"></i>
                    <small>Optional field - will be saved if your database supports it</small>
                </div>

                <div class="form-actions">
                    <a href="view_admins.php" class="btn btn-secondary">
                        <i class="fa fa-arrow-left"></i> Cancel
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa fa-save"></i> Create Admin Account
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
