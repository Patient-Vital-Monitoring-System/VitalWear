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
        // Check database connection
        if (!$conn) {
            $error_message = "Database connection failed. Please check your database configuration.";
        } else {
            try {
                // First, check if management table exists
                $table_check = $conn->query("SHOW TABLES LIKE 'management'");
                if ($table_check->num_rows == 0) {
                    $error_message = "Management table does not exist in the database.";
                } else {
                    // Check if email already exists
                    $check_query = "SELECT mgmt_id FROM management WHERE mgmt_email = ?";
                    $check_stmt = $conn->prepare($check_query);
                    
                    if (!$check_stmt) {
                        $error_message = "Failed to prepare email check query: " . $conn->error;
                    } else {
                        $check_stmt->bind_param("s", $email);
                        $check_stmt->execute();
                        $check_result = $check_stmt->get_result();
                        
                        if ($check_result->num_rows > 0) {
                            $error_message = "An account with this email already exists.";
                        } else {
                            // Hash password
                            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                            
                            // Check table structure to see which columns exist
                            $columns_result = $conn->query("SHOW COLUMNS FROM management");
                            $existing_columns = [];
                            while ($row = $columns_result->fetch_assoc()) {
                                $existing_columns[] = $row['Field'];
                            }
                            
                            // Build insert query based on existing columns
                            $insert_columns = ['mgmt_name', 'mgmt_email', 'mgmt_password'];
                            $insert_values = [$name, $email, $hashed_password];
                            $insert_placeholders = ['?', '?', '?'];
                            
                            // Add contact field if it exists
                            if (in_array('mgmt_contact', $existing_columns)) {
                                $insert_columns[] = 'mgmt_contact';
                                $insert_values[] = $contact;
                                $insert_placeholders[] = '?';
                                $bind_types = "ssss";
                            } else {
                                $bind_types = "sss";
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
                            
                            // Build the final query
                            $insert_query = "INSERT INTO management (" . implode(', ', $insert_columns) . ") VALUES (" . implode(', ', $insert_placeholders) . ")";
                            $insert_stmt = $conn->prepare($insert_query);
                            
                            if (!$insert_stmt) {
                                $error_message = "Failed to prepare insert query: " . $conn->error . "<br><br>Available columns: " . implode(', ', $existing_columns);
                            } else {
                                // Bind parameters dynamically
                                $insert_stmt->bind_param($bind_types, ...$insert_values);
                                
                                if ($insert_stmt->execute()) {
                                    $success_message = "Management account created successfully!";
                                    // Clear form
                                    $name = $email = $password = $contact = '';
                                } else {
                                    $error_message = "Error creating management account: " . $insert_stmt->error;
                                }
                            }
                        }
                    }
                }
            } catch (Exception $e) {
                $error_message = "Database error: " . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Management Account - VitalWear Admin</title>
    <script src="https://kit.fontawesome.com/96e37b53f1.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Admin & Management Minimal Design System */
        :root {
            /* Authority Color Palette */
            --authority-blue: #1B3F72;
            --dashboard-light: #F4F7FC;
            --pure-white: #FFFFFF;
            --secondary-text: #7E91B3;
            --system-success: #2CC990;
            --system-warning: #FFC107;
            --system-error: #DC3545;
            --interface-border: #D1E0F1;
            
            /* Extended Minimal Palette */
            --authority-blue-dark: #152E56;
            --authority-blue-light: #2A5288;
            --dashboard-light-alt: #EDF2F9;
            --secondary-text-light: #8FA1C3;
            --system-success-light: #E8F5F0;
            --system-warning-light: #FFF8E7;
            --system-error-light: #FDF2F4;
            --interface-border-light: #E1E8F0;
            
            /* Design Tokens */
            --primary: var(--authority-blue);
            --primary-dark: var(--authority-blue-dark);
            --primary-light: var(--authority-blue-light);
            --background: var(--dashboard-light);
            --surface: var(--pure-white);
            --surface-alt: var(--dashboard-light-alt);
            --text-primary: var(--authority-blue);
            --text-secondary: var(--secondary-text);
            --text-muted: var(--secondary-text-light);
            --text-inverse: var(--pure-white);
            --border: var(--interface-border);
            --border-light: var(--interface-border-light);
            --success: var(--system-success);
            --success-bg: var(--system-success-light);
            --warning: var(--system-warning);
            --warning-bg: var(--system-warning-light);
            --error: var(--system-error);
            --error-bg: var(--system-error-light);
            
            /* Minimal Radius System */
            --radius-xs: 2px;
            --radius-sm: 4px;
            --radius: 6px;
            --radius-md: 8px;
            --radius-lg: 12px;
            --radius-xl: 16px;
            --radius-2xl: 20px;
            --radius-full: 9999px;
            
            /* Minimal Shadow System */
            --shadow-xs: 0 1px 2px rgba(27, 63, 114, 0.04);
            --shadow-sm: 0 1px 3px rgba(27, 63, 114, 0.08), 0 1px 2px rgba(27, 63, 114, 0.04);
            --shadow: 0 2px 8px rgba(27, 63, 114, 0.08), 0 1px 2px rgba(27, 63, 114, 0.04);
            --shadow-md: 0 4px 12px rgba(27, 63, 114, 0.1), 0 2px 4px rgba(27, 63, 114, 0.06);
            --shadow-lg: 0 8px 24px rgba(27, 63, 114, 0.12), 0 4px 8px rgba(27, 63, 114, 0.08);
            
            /* Transitions */
            --transition-fast: 150ms ease;
            --transition: 200ms ease;
            --transition-slow: 300ms ease;
        }

        body {
            background: var(--background);
            color: var(--text-primary);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            margin: 0;
            padding: 0;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem;
        }

        .form-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            padding: 2.5rem;
            margin-top: 2rem;
        }

        .form-header {
            text-align: center;
            margin-bottom: 2.5rem;
            position: relative;
        }

        .form-header h1 {
            font-size: 1.875rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            letter-spacing: -0.01em;
        }

        .form-header p {
            color: var(--text-secondary);
            margin-bottom: 0;
            font-size: 1rem;
            margin-top: 0.5rem;
            font-weight: 400;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
        }

        .form-input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            font-size: 0.9375rem;
            transition: all var(--transition);
            background: var(--surface);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            font-weight: 400;
        }

        .form-input:hover {
            border-color: var(--border-light);
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(27, 63, 114, 0.1);
        }

        .form-input::placeholder {
            color: var(--text-muted);
        }

        .required-indicator {
            color: var(--error);
            font-weight: 600;
            margin-left: 2px;
        }

        .form-group small {
            display: block;
            margin-top: 0.25rem;
            color: var(--text-secondary);
            font-size: 0.75rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: var(--radius);
            font-weight: 500;
            cursor: pointer;
            transition: all var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        }

        .btn-primary {
            background: var(--primary);
            color: var(--text-inverse);
        }

        .btn-primary:hover {
            background: var(--primary-dark);
        }

        .btn-secondary {
            background: var(--surface);
            color: var(--text-primary);
            border: 1px solid var(--border);
        }

        .btn-secondary:hover {
            background: var(--surface-alt);
            border-color: var(--border-light);
        }

        .success-message {
            background: var(--success-bg);
            color: var(--success);
            padding: 1rem;
            border-radius: var(--radius);
            margin-bottom: 1.5rem;
            border: 1px solid var(--success);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.875rem;
        }

        .error-message {
            background: var(--error-bg);
            color: var(--error);
            padding: 1rem;
            border-radius: var(--radius);
            margin-bottom: 1.5rem;
            border: 1px solid var(--error);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.875rem;
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 2rem;
        }

        .form-actions::before {
            content: '';
            position: absolute;
            top: -1px;
            left: 50%;
            transform: translateX(-50%);
            width: 60px;
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--primary), transparent);
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

        .form-group small {
            display: block;
            margin-top: 0.5rem;
            color: var(--text-tertiary);
            font-size: 0.875rem;
            font-style: italic;
            padding-left: 0.5rem;
            border-left: 2px solid var(--border);
        }

        .required-indicator {
            color: var(--error);
            font-weight: 700;
            margin-left: 4px;
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

        .form-group {
            animation: slideInLeft 0.4s ease-out;
            animation-fill-mode: both;
        }

        .form-group:nth-child(1) { animation-delay: 0.1s; }
        .form-group:nth-child(2) { animation-delay: 0.2s; }
        .form-group:nth-child(3) { animation-delay: 0.3s; }
        .form-group:nth-child(4) { animation-delay: 0.4s; }

        @keyframes slideInLeft {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .form-input:focus + .input-icon {
            color: var(--primary);
            transform: scale(1.1);
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

        .form-group {
            position: relative;
        }

        .form-group.has-icon .form-input {
            padding-right: 3rem;
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
            <a href="view_management.php"><i class="fa fa-user-tie"></i> Management</a>
            <span>/</span>
            <span><i class="fa fa-plus"></i> Create</span>
        </div>

        <div class="form-card">
            <div class="form-header">
                <h1><i class="fa fa-user-tie"></i> Create Management Account</h1>
                <p>Add a new management user to the system</p>
            </div>

            <?php if ($success_message): ?>
                <div class="success-message">
                    <i class="fa fa-check-circle"></i> 
                    <div>
                        <strong>Success!</strong><br>
                        <?php echo htmlspecialchars($success_message); ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <?php if (strpos($error_message, 'table does not exist') !== false): ?>
                    <div class="fatal-error">
                        <i class="fa fa-table" style="font-size: 2rem;"></i>
                        <div>
                            <h3>Missing Database Table</h3>
                            <p><?php echo htmlspecialchars($error_message); ?></p>
                            <div class="debug-info">
                                <strong>Required Action:</strong><br>
                                1. Create the management table using this SQL:<br>
                                <code style="display: block; background: #000; color: #0f0; padding: 10px; margin: 10px 0; border-radius: 4px;">
                                CREATE TABLE management (<br>
                                &nbsp;&nbsp;mgmt_id INT AUTO_INCREMENT PRIMARY KEY,<br>
                                &nbsp;&nbsp;mgmt_name VARCHAR(255) NOT NULL,<br>
                                &nbsp;&nbsp;mgmt_email VARCHAR(255) UNIQUE NOT NULL,<br>
                                &nbsp;&nbsp;mgmt_password VARCHAR(255) NOT NULL,<br>
                                &nbsp;&nbsp;mgmt_contact VARCHAR(50),<br>
                                &nbsp;&nbsp;status ENUM('active', 'inactive') DEFAULT 'active',<br>
                                &nbsp;&nbsp;created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP<br>
                                );
                                </code>
                            </div>
                        </div>
                    </div>
                <?php elseif (strpos($error_message, 'Failed to prepare') !== false || strpos($error_message, 'Database error') !== false): ?>
                    <div class="fatal-error">
                        <i class="fa fa-database" style="font-size: 2rem;"></i>
                        <div>
                            <h3>Database Query Error</h3>
                            <p><?php echo htmlspecialchars($error_message); ?></p>
                            <div class="debug-info">
                                <strong>Troubleshooting:</strong><br>
                                1. Check if the database connection is working<br>
                                2. Verify the 'management' table structure<br>
                                3. The system will automatically adapt to your table structure<br>
                                4. Check database user permissions for INSERT operations<br>
                                5. Verify MySQL/MariaDB version compatibility<br><br>
                                <strong>Quick Fix:</strong> The system now detects your actual table structure and adapts the query accordingly. Try submitting the form again.
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="error-message">
                        <i class="fa fa-exclamation-triangle"></i> 
                        <div>
                            <strong>Validation Error:</strong><br>
                            <?php echo htmlspecialchars($error_message); ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if (!$conn): ?>
                <div class="fatal-error">
                    <i class="fa fa-server" style="font-size: 2rem;"></i>
                    <div>
                        <h3>Database Connection Failed</h3>
                        <p>The system could not connect to the database. Please check your database configuration.</p>
                        <div class="debug-info">
                            <strong>Connection Checklist:</strong><br>
                            • XAMPP/MAMP/WAMP server is running<br>
                            • MySQL service is active<br>
                            • Database credentials in connection.php are correct<br>
                            • Database 'vitalwear' exists<br>
                            • User has proper permissions
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="name" class="form-label">Full Name<span class="required-indicator">*</span></label>
                    <input type="text" id="name" name="name" class="form-input" 
                           value="<?php echo htmlspecialchars($name ?? ''); ?>" 
                           placeholder="Enter full name" required>
                </div>

                <div class="form-group">
                    <label for="email" class="form-label">Email Address<span class="required-indicator">*</span></label>
                    <input type="email" id="email" name="email" class="form-input" 
                           value="<?php echo htmlspecialchars($email ?? ''); ?>" 
                           placeholder="management@example.com" required>
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">Password<span class="required-indicator">*</span></label>
                    <input type="password" id="password" name="password" class="form-input" 
                           placeholder="Create a strong password" required>
                    <small>Minimum 8 characters</small>
                </div>

                <div class="form-group">
                    <label for="contact" class="form-label">Contact Number</label>
                    <input type="tel" id="contact" name="contact" class="form-input" 
                           value="<?php echo htmlspecialchars($contact ?? ''); ?>"
                           placeholder="+1 (555) 123-4567">
                    <small>Optional field</small>
                </div>

                <div class="form-actions">
                    <a href="view_management.php" class="btn btn-secondary">
                        Cancel
                    </a>
                    <button type="submit" class="btn btn-primary">
                        Create Account
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
