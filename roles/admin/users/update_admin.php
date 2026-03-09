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
$admin_id = $_GET['id'] ?? '';

// Validate ID
if (empty($admin_id) || !is_numeric($admin_id)) {
    header('Location: view_admins.php');
    exit();
}

// Get current admin data
$admin = null;
try {
    // Check database connection
    if (!$conn) {
        $error_message = "Database connection failed. Please check your database configuration.";
    } else {
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
            
            // Build select query based on existing columns
            $select_columns = ['admin_id', 'admin_name', 'admin_email'];
            if (in_array('admin_contact', $existing_columns)) {
                $select_columns[] = 'admin_contact';
            }
            
            $select_query = "SELECT " . implode(', ', $select_columns) . " FROM admin WHERE admin_id = ?";
            $stmt = $conn->prepare($select_query);
            
            if (!$stmt) {
                $error_message = "Failed to prepare select query: " . $conn->error . "<br><br>Available columns: " . implode(', ', $existing_columns);
            } else {
                $stmt->bind_param("i", $admin_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows === 0) {
                    header('Location: view_admins.php');
                    exit();
                }
                
                $admin = $result->fetch_assoc();
            }
        }
    }
} catch (Exception $e) {
    $error_message = "Error fetching admin data: " . $e->getMessage();
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
                    
                    // Check if email already exists (excluding current user)
                    $check_query = "SELECT admin_id FROM admin WHERE admin_email = ? AND admin_id != ?";
                    $check_stmt = $conn->prepare($check_query);
                    
                    if (!$check_stmt) {
                        $error_message = "Failed to prepare email check query: " . $conn->error;
                    } else {
                        $check_stmt->bind_param("si", $email, $admin_id);
                        $check_stmt->execute();
                        $check_result = $check_stmt->get_result();
                        
                        if ($check_result->num_rows > 0) {
                            $error_message = "An account with this email already exists.";
                        } else {
                            // Update admin user
                            $update_columns = ['admin_name = ?', 'admin_email = ?'];
                            $update_values = [$name, $email];
                            $bind_types = "ss";
                            
                            // Add contact field if it exists
                            if (in_array('admin_contact', $existing_columns)) {
                                $update_columns[] = 'admin_contact = ?';
                                $update_values[] = $contact;
                                $bind_types .= "s";
                            }
                            
                            // Add password field if provided
                            if (!empty($password)) {
                                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                                $update_columns[] = 'admin_password = ?';
                                $update_values[] = $hashed_password;
                                $bind_types .= "s";
                            }
                            
                            // Add WHERE clause
                            $update_values[] = $admin_id;
                            $bind_types .= "i";
                            
                            // Build the final update query
                            $update_query = "UPDATE admin SET " . implode(', ', $update_columns) . " WHERE admin_id = ?";
                            $update_stmt = $conn->prepare($update_query);
                            
                            if (!$update_stmt) {
                                $error_message = "Failed to prepare update query: " . $conn->error . "<br><br>Available columns: " . implode(', ', $existing_columns);
                            } else {
                                // Bind parameters dynamically
                                $update_stmt->bind_param($bind_types, ...$update_values);
                                
                                if ($update_stmt->execute()) {
                                    $success_message = "Admin account updated successfully!";
                                    // Refresh data
                                    $select_columns = ['admin_id', 'admin_name', 'admin_email'];
                                    if (in_array('admin_contact', $existing_columns)) {
                                        $select_columns[] = 'admin_contact';
                                    }
                                    
                                    $refresh_query = "SELECT " . implode(', ', $select_columns) . " FROM admin WHERE admin_id = ?";
                                    $refresh_stmt = $conn->prepare($refresh_query);
                                    
                                    if ($refresh_stmt) {
                                        $refresh_stmt->bind_param("i", $admin_id);
                                        $refresh_stmt->execute();
                                        $result = $refresh_stmt->get_result();
                                        $admin = $result->fetch_assoc();
                                    }
                                } else {
                                    $error_message = "Error updating admin account: " . $update_stmt->error;
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
    <title>Update Admin Account - VitalWear Admin</title>
    <script src="https://kit.fontawesome.com/96e37b53f1.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* VitalWear Modern Soft UI Design System */
        :root {
            /* Primary Colors */
            --primary-50: #E8F4FD;
            --primary-100: #D1E9FB;
            --primary-600: #1E7AB8;
            --primary-700: #1A5F9A;
            
            /* Neutral Colors */
            --gray-50: #F9FAFB;
            --gray-100: #F3F4F6;
            --gray-200: #E5E7EB;
            --gray-300: #D1D5DB;
            --gray-500: #6B7280;
            --gray-600: #4B5563;
            --gray-700: #374151;
            --gray-900: #111827;
            
            /* Semantic Colors */
            --success: #10B981;
            --success-light: #D1FAE5;
            --error: #EF4444;
            --error-light: #FEE2E2;
            --info: #3B82F6;
            
            /* Design Tokens */
            --primary: var(--primary-600);
            --background: var(--gray-50);
            --surface: white;
            --text-primary: var(--gray-900);
            --text-secondary: var(--gray-600);
            --border: var(--gray-200);
            --radius: 8px;
            --radius-lg: 16px;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            --transition: 200ms cubic-bezier(0.4, 0, 0.2, 1);
        }

        body {
            background: var(--background);
            color: var(--text-primary);
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 0;
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem;
        }

        .form-card {
            background: var(--surface);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            padding: 2rem;
            margin-top: 2rem;
        }

        .form-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .form-header h1 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--error);
            margin-bottom: 0.5rem;
        }

        .form-header p {
            color: var(--text-secondary);
            margin-bottom: 0;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .form-input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid var(--border);
            border-radius: var(--radius);
            font-size: 1rem;
            transition: all var(--transition);
            background: var(--surface);
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(30, 122, 184, 0.1);
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: var(--radius);
            font-weight: 600;
            cursor: pointer;
            transition: all var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: var(--error);
            color: white;
        }

        .btn-primary:hover {
            background: #DC2626;
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        .btn-secondary {
            background: var(--gray-200);
            color: var(--text-primary);
        }

        .btn-secondary:hover {
            background: var(--gray-300);
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

        .password-hint {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-top: 0.25rem;
        }

        .admin-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            background: var(--error-light);
            color: var(--error);
            border-radius: var(--radius);
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
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
            <span><i class="fa fa-edit"></i> Update</span>
        </div>

        <?php if ($admin): ?>
            <div class="form-card">
                <div class="form-header">
                    <h1><i class="fa fa-user-cog"></i> Update Admin Account</h1>
                    <p>Edit administrator information <span class="admin-badge">High Privilege</span></p>
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
                        <label for="name" class="form-label">Full Name *</label>
                        <input type="text" id="name" name="name" class="form-input" 
                               value="<?php echo htmlspecialchars($admin['admin_name']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="email" class="form-label">Email Address *</label>
                        <input type="email" id="email" name="email" class="form-input" 
                               value="<?php echo htmlspecialchars($admin['admin_email']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="contact" class="form-label">Contact Number</label>
                        <input type="tel" id="contact" name="contact" class="form-input" 
                               value="<?php echo htmlspecialchars($admin['admin_contact'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label for="password" class="form-label">New Password</label>
                        <input type="password" id="password" name="password" class="form-input" 
                               placeholder="Leave blank to keep current password">
                        <div class="password-hint">Minimum 8 characters. Leave empty to keep current password.</div>
                    </div>

                    <div class="form-actions">
                        <a href="view_admins.php" class="btn btn-secondary">
                            <i class="fa fa-arrow-left"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fa fa-save"></i> Update Account
                        </button>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <div class="form-card">
                <div class="error-message">
                    <i class="fa fa-exclamation-circle"></i> Admin account not found.
                </div>
                <div style="text-align: center; margin-top: 1rem;">
                    <a href="view_admins.php" class="btn btn-secondary">
                        <i class="fa fa-arrow-left"></i> Back to Admins
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
