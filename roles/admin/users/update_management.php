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
$management_id = $_GET['id'] ?? '';

// Validate ID
if (empty($management_id) || !is_numeric($management_id)) {
    header('Location: view_management.php');
    exit();
}

// Get current management data
$management = null;
try {
    // Check database connection
    if (!$conn) {
        $error_message = "Database connection failed. Please check your database configuration.";
    } else {
        // Check if management table exists
        $table_check = $conn->query("SHOW TABLES LIKE 'management'");
        if ($table_check->num_rows == 0) {
            $error_message = "Management table does not exist in the database.";
        } else {
            // Check table structure to see which columns exist
            $columns_result = $conn->query("SHOW COLUMNS FROM management");
            $existing_columns = [];
            while ($row = $columns_result->fetch_assoc()) {
                $existing_columns[] = $row['Field'];
            }
            
            // Build select query based on existing columns
            $select_columns = ['mgmt_id', 'mgmt_name', 'mgmt_email'];
            if (in_array('mgmt_contact', $existing_columns)) {
                $select_columns[] = 'mgmt_contact';
            }
            
            $select_query = "SELECT " . implode(', ', $select_columns) . " FROM management WHERE mgmt_id = ?";
            $stmt = $conn->prepare($select_query);
            
            if (!$stmt) {
                $error_message = "Failed to prepare select query: " . $conn->error . "<br><br>Available columns: " . implode(', ', $existing_columns);
            } else {
                $stmt->bind_param("i", $management_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows === 0) {
                    header('Location: view_management.php');
                    exit();
                }
                
                $management = $result->fetch_assoc();
            }
        }
    }
} catch (Exception $e) {
    $error_message = "Error fetching management data: " . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Validation
    if (empty($name) || empty($email)) {
        $error_message = "Name and email are required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Please enter a valid email address.";
    } elseif (!empty($password) && strlen($password) < 8) {
        $error_message = "Password must be at least 8 characters long.";
    } else {
        // Check database connection
        if (!$conn) {
            $error_message = "Database connection failed. Please check your database configuration.";
        } else {
            try {
                // Check if management table exists
                $table_check = $conn->query("SHOW TABLES LIKE 'management'");
                if ($table_check->num_rows == 0) {
                    $error_message = "Management table does not exist in the database.";
                } else {
                    // Check table structure to see which columns exist
                    $columns_result = $conn->query("SHOW COLUMNS FROM management");
                    $existing_columns = [];
                    while ($row = $columns_result->fetch_assoc()) {
                        $existing_columns[] = $row['Field'];
                    }
                    
                    // Check if email already exists (excluding current user)
                    $check_query = "SELECT mgmt_id FROM management WHERE mgmt_email = ? AND mgmt_id != ?";
                    $check_stmt = $conn->prepare($check_query);
                    
                    if (!$check_stmt) {
                        $error_message = "Failed to prepare email check query: " . $conn->error;
                    } else {
                        $check_stmt->bind_param("si", $email, $management_id);
                        $check_stmt->execute();
                        $check_result = $check_stmt->get_result();
                        
                        if ($check_result->num_rows > 0) {
                            $error_message = "An account with this email already exists.";
                        } else {
                            // Update management user
                            $update_columns = ['mgmt_name = ?', 'mgmt_email = ?'];
                            $update_values = [$name, $email];
                            $bind_types = "ss";
                            
                            // Add contact field if it exists
                            if (in_array('mgmt_contact', $existing_columns)) {
                                $update_columns[] = 'mgmt_contact = ?';
                                $update_values[] = $contact;
                                $bind_types .= "s";
                            }
                            
                            // Add password field if provided
                            if (!empty($password)) {
                                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                                $update_columns[] = 'mgmt_password = ?';
                                $update_values[] = $hashed_password;
                                $bind_types .= "s";
                            }
                            
                            // Add WHERE clause
                            $update_values[] = $management_id;
                            $bind_types .= "i";
                            
                            // Build the final update query
                            $update_query = "UPDATE management SET " . implode(', ', $update_columns) . " WHERE mgmt_id = ?";
                            $update_stmt = $conn->prepare($update_query);
                            
                            if (!$update_stmt) {
                                $error_message = "Failed to prepare update query: " . $conn->error . "<br><br>Available columns: " . implode(', ', $existing_columns);
                            } else {
                                // Bind parameters dynamically
                                $update_stmt->bind_param($bind_types, ...$update_values);
                                
                                if ($update_stmt->execute()) {
                                    $success_message = "Management account updated successfully!";
                                    // Refresh data
                                    $select_columns = ['mgmt_id', 'mgmt_name', 'mgmt_email'];
                                    if (in_array('mgmt_contact', $existing_columns)) {
                                        $select_columns[] = 'mgmt_contact';
                                    }
                                    
                                    $refresh_query = "SELECT " . implode(', ', $select_columns) . " FROM management WHERE mgmt_id = ?";
                                    $refresh_stmt = $conn->prepare($refresh_query);
                                    
                                    if ($refresh_stmt) {
                                        $refresh_stmt->bind_param("i", $management_id);
                                        $refresh_stmt->execute();
                                        $result = $refresh_stmt->get_result();
                                        $management = $result->fetch_assoc();
                                    }
                                } else {
                                    $error_message = "Error updating management account: " . $update_stmt->error;
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
    <title>Update Management Account - VitalWear Admin</title>
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
            position: relative;
        }

        .form-group.has-icon .form-input {
            padding-right: 3rem;
        }

        .form-label {
            display: block;
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
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
            background: var(--primary);
            border-radius: var(--radius-full);
        }

        .form-input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            font-size: 0.9375rem;
            transition: all var(--transition);
            background: var(--surface);
            font-family: 'Inter', sans-serif;
            font-weight: 400;
            box-shadow: var(--shadow-sm);
        }

        .form-input:hover {
            border-color: var(--border-light);
            transform: translateY(-2px);
            box-shadow: var(--shadow-soft);
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(27, 63, 114, 0.1);
            transform: translateY(-3px);
            background: var(--surface);
        }

        .form-input::placeholder {
            color: var(--text-muted);
            font-style: italic;
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
            color: var(--primary);
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
            background: var(--gradient-primary);
            color: var(--text-inverse);
            box-shadow: var(--shadow-soft);
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--brand-secondary) 0%, var(--primary-800) 100%);
            transform: translateY(-4px);
            box-shadow: var(--shadow-soft-lg);
        }

        .btn-primary:active {
            transform: translateY(-2px);
            box-shadow: var(--shadow-soft);
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
            box-shadow: var(--shadow-soft);
        }

        .btn-secondary:active {
            transform: translateY(-1px);
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
            background: linear-gradient(90deg, transparent, var(--primary), transparent);
        }

        .required-indicator {
            color: var(--primary);
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

        .password-hint {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-top: 0.25rem;
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
            <span><i class="fa fa-edit"></i> Update</span>
        </div>

        <?php if ($management): ?>
            <div class="form-card">
                <div class="form-header">
                    <h1><i class="fa fa-user-tie"></i> Update Management Account</h1>
                    <p>Edit management user information</p>
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
                    <div class="form-group">
                        <label for="name" class="form-label">Full Name<span class="required-indicator">*</span></label>
                        <input type="text" id="name" name="name" class="form-input" 
                               value="<?php echo htmlspecialchars($management['mgmt_name']); ?>" 
                               placeholder="Enter full name" required>
                    </div>

                    <div class="form-group">
                        <label for="email" class="form-label">Email Address<span class="required-indicator">*</span></label>
                        <input type="email" id="email" name="email" class="form-input" 
                               value="<?php echo htmlspecialchars($management['mgmt_email']); ?>" 
                               placeholder="management@example.com" required>
                    </div>

                    <div class="form-group">
                        <label for="contact" class="form-label">Contact Number</label>
                        <input type="tel" id="contact" name="contact" class="form-input" 
                               value="<?php echo htmlspecialchars($management['mgmt_contact'] ?? ''); ?>"
                               placeholder="+1 (555) 123-4567">
                        <small>Optional field</small>
                    </div>

                    <div class="form-group">
                        <label for="password" class="form-label">New Password</label>
                        <input type="password" id="password" name="password" class="form-input" 
                               placeholder="Leave blank to keep current password">
                        <small>Minimum 8 characters. Leave empty to keep current password.</small>
                    </div>

                    <div class="form-actions">
                        <a href="view_management.php" class="btn btn-secondary">
                            Cancel
                        </a>
                        <button type="submit" class="btn btn-primary">
                            Update Account
                        </button>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <div class="form-card">
                <div class="error-message">
                    <i class="fa fa-exclamation-circle"></i> Management account not found.
                </div>
                <div style="text-align: center; margin-top: 1rem;">
                    <a href="view_management.php" class="btn btn-secondary">
                        <i class="fa fa-arrow-left"></i> Back to Management
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
