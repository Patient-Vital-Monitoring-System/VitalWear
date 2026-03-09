<?php
session_start();
require_once '../../../database/connection.php';

// Check if admin user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /VitalWear-1/login.html');
    exit();
}

$conn = getDBConnection();
$success_message = '';
$error_message = '';

// Get rescuer ID from URL
$rescuer_id = $_GET['id'] ?? '';

// Validate ID
if (empty($rescuer_id) || !is_numeric($rescuer_id)) {
    header('Location: view_rescuers.php');
    exit();
}

// Get current rescuer data
$rescuer = null;
try {
    $stmt = $conn->prepare("SELECT resc_id, resc_name, resc_email, resc_contact FROM rescuer WHERE resc_id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $rescuer_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            header('Location: view_rescuers.php');
            exit();
        }
        
        $rescuer = $result->fetch_assoc();
    }
} catch (Exception $e) {
    $error_message = "Error fetching rescuer data: " . $e->getMessage();
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
    } elseif (!empty($password) && strlen($password) < 6) {
        $error_message = "Password must be at least 6 characters long.";
    } else {
        try {
            // Check if email already exists (excluding current user)
            $check_stmt = $conn->prepare("SELECT resc_id FROM rescuer WHERE resc_email = ? AND resc_id != ?");
            if ($check_stmt) {
                $check_stmt->bind_param("si", $email, $rescuer_id);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                
                if ($check_result->num_rows > 0) {
                    $error_message = "An account with this email already exists.";
                } else {
                    // Build update query dynamically
                    $update_fields = ["resc_name = ?", "resc_email = ?"];
                    $update_values = [$name, $email];
                    $bind_types = "ss";
                    
                    // Add contact field if provided
                    if (!empty($contact)) {
                        $update_fields[] = "resc_contact = ?";
                        $update_values[] = $contact;
                        $bind_types .= "s";
                    }
                    
                    // Add password field if provided
                    if (!empty($password)) {
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        $update_fields[] = "resc_password = ?";
                        $update_values[] = $hashed_password;
                        $bind_types .= "s";
                    }
                    
                    // Add WHERE clause
                    $update_values[] = $rescuer_id;
                    $bind_types .= "i";
                    
                    // Execute update
                    $update_query = "UPDATE rescuer SET " . implode(", ", $update_fields) . " WHERE resc_id = ?";
                    $update_stmt = $conn->prepare($update_query);
                    
                    if ($update_stmt) {
                        $update_stmt->bind_param($bind_types, ...$update_values);
                        
                        if ($update_stmt->execute()) {
                            $success_message = "Rescuer account updated successfully!";
                            
                            // Refresh data
                            $refresh_stmt = $conn->prepare("SELECT resc_id, resc_name, resc_email, resc_contact FROM rescuer WHERE resc_id = ?");
                            if ($refresh_stmt) {
                                $refresh_stmt->bind_param("i", $rescuer_id);
                                $refresh_stmt->execute();
                                $refresh_result = $refresh_stmt->get_result();
                                if ($refresh_result->num_rows > 0) {
                                    $rescuer = $refresh_result->fetch_assoc();
                                }
                            }
                        } else {
                            $error_message = "Error updating rescuer account: " . $update_stmt->error;
                        }
                    } else {
                        $error_message = "Failed to prepare update query: " . $conn->error;
                    }
                }
            } else {
                $error_message = "Database error: Failed to prepare email check";
            }
            
        } catch (Exception $e) {
            $error_message = "Database error: " . $e->getMessage();
        }
    }
    
    // Redirect back to view page with message
    $redirect_url = "/VitalWear-1/roles/admin/users/view_rescuers.php";
    if ($error_message) {
        $redirect_url .= "?error=" . urlencode($error_message);
    } elseif ($success_message) {
        $redirect_url .= "?success=" . urlencode($success_message);
    }
    header("Location: $redirect_url");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Rescuer Account - VitalWear Admin</title>
    <script src="https://kit.fontawesome.com/96e37b53f1.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* VitalWear Modern Soft UI Design System */
        :root {
            /* Primary Colors - Modern Blue Palette */
            --primary-50: #E8F4FD;
            --primary-100: #D1E9FB;
            --primary-200: #A9D9F5;
            --primary-300: #7BC4F0;
            --primary-400: #4DAEEA;
            --primary-500: #2E96D5;
            --primary-600: #1E7AB8;
            --primary-700: #1A5F9A;
            --primary-800: #1A4975;
            --primary-900: #1A3A5C;
            
            /* Neutral Colors */
            --gray-50: #F9FAFB;
            --gray-100: #F3F4F6;
            --gray-200: #E5E7EB;
            --gray-300: #D1D5DB;
            --gray-400: #9CA3AF;
            --gray-500: #6B7280;
            --gray-600: #4B5563;
            --gray-700: #374151;
            --gray-800: #1F2937;
            --gray-900: #111827;
            
            /* Semantic Colors */
            --success: #10B981;
            --success-light: #D1FAE5;
            --warning: #F59E0B;
            --warning-light: #FEF3C7;
            --error: #EF4444;
            --error-light: #FEE2E2;
            --info: #3B82F6;
            --info-light: #DBEAFE;
            
            /* Core Design Tokens */
            --primary: var(--primary-600);
            --primary-light: var(--primary-100);
            --background: var(--gray-50);
            --surface: #ffffff;
            --text-primary: var(--gray-900);
            --text-secondary: var(--gray-600);
            --text-tertiary: var(--gray-500);
            --border: var(--gray-200);
            --border-hover: var(--gray-300);
            
            /* Soft UI Radius System */
            --radius-xs: 4px;
            --radius-sm: 6px;
            --radius: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
            --radius-xl: 20px;
            --radius-2xl: 24px;
            --radius-full: 9999px;
            
            /* Modern Shadow System */
            --shadow-xs: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-sm: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-md: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            --shadow-xl: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            
            /* Transitions */
            --transition-fast: 150ms cubic-bezier(0.4, 0, 0.2, 1);
            --transition: 200ms cubic-bezier(0.4, 0, 0.2, 1);
            --transition-slow: 300ms cubic-bezier(0.4, 0, 0.2, 1);
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
        }

        /* Modern Soft UI Sidebar */
        .admin-sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 280px;
            height: 100vh;
            background: var(--surface);
            border-right: 1px solid var(--border);
            box-shadow: var(--shadow-lg);
            z-index: 1000;
            overflow-y: auto;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }

        .sidebar-header {
            padding: 32px 24px 24px;
            text-align: center;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-700) 100%);
            margin: 16px;
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-md);
            position: relative;
            overflow: hidden;
        }

        .sidebar-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.1) 0%, transparent 100%);
            pointer-events: none;
        }

        .sidebar-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: white;
            margin-bottom: 4px;
            position: relative;
            z-index: 1;
        }

        .sidebar-subtitle {
            font-size: 0.875rem;
            color: rgba(255, 255, 255, 0.8);
            position: relative;
            z-index: 1;
        }

        .nav-menu {
            padding: 16px;
        }

        .nav-group {
            margin-bottom: 24px;
        }

        .nav-group-title {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-tertiary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 0 16px 8px;
            padding: 8px 0;
        }

        .nav-group-items {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .nav-item {
            color: var(--text-primary);
            padding: 12px 16px;
            border-radius: var(--radius-lg);
            transition: all var(--transition);
            border: none;
            font-weight: 500;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 12px;
            position: relative;
            overflow: hidden;
        }

        .nav-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
            transition: left var(--transition-slow);
        }

        .nav-item:hover {
            background: var(--primary-light);
            color: var(--primary);
            transform: translateX(4px);
        }

        .nav-item:hover::before {
            left: 100%;
        }

        .nav-item.active {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-600) 100%);
            color: white;
            box-shadow: var(--shadow-md);
            transform: translateX(4px);
        }

        .nav-item.active::before {
            left: 100%;
        }

        /* Main Content */
        .admin-main {
            margin-left: 280px;
            min-height: 100vh;
            background: var(--background);
        }

        /* Modern Header */
        .admin-header {
            background: var(--surface);
            padding: 1.5rem 2rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }

        .admin-header h1 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        /* Page Header */
        .page-header {
            padding: 2rem 2rem 1rem;
        }

        .page-header h1 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0 0 0.5rem 0;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .page-header p {
            color: var(--text-secondary);
            margin: 0;
            font-size: 1.125rem;
        }

        /* Form Container */
        .form-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        .form-card {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08), 0 1px 3px rgba(0, 0, 0, 0.1);
            border: 1px solid #e5e7eb;
            overflow: hidden;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .form-card:hover {
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12), 0 2px 6px rgba(0, 0, 0, 0.15);
        }

        .form-header {
            padding: 2rem 2rem 1rem;
            border-bottom: 1px solid #e5e7eb;
            background: #ffffff;
        }

        .form-header h2 {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .form-header p {
            color: var(--text-secondary);
            margin: 0.5rem 0 0 0;
        }

        .form-body {
            padding: 2rem;
            background: #ffffff;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-label {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .required {
            color: var(--error);
        }

        .form-input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 0.875rem;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            background: #ffffff;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(30, 122, 184, 0.1), 0 2px 8px rgba(30, 122, 184, 0.15);
            transform: translateY(-1px);
        }

        .form-input:hover {
            border-color: #d1d5db;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
        }

        /* Buttons */
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: var(--radius-lg);
            font-weight: 600;
            cursor: pointer;
            transition: all var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-600) 100%);
            color: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(30, 122, 184, 0.2), inset 0 1px 0 rgba(255, 255, 255, 0.3);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            font-weight: 600;
            letter-spacing: 0.025em;
            position: relative;
            overflow: hidden;
        }

        .btn-primary::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
            transition: left 0.6s ease;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--primary-600) 0%, var(--primary-700) 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(30, 122, 184, 0.3), inset 0 1px 0 rgba(255, 255, 255, 0.2);
        }

        .btn-primary:hover::before {
            left: 100%;
        }

        .btn-primary:active {
            transform: translateY(0);
            box-shadow: 0 2px 6px rgba(30, 122, 184, 0.25);
        }

        .btn-secondary {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            color: var(--text-primary);
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1), inset 0 1px 0 rgba(255, 255, 255, 0.8);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            font-weight: 600;
            letter-spacing: 0.025em;
            position: relative;
            overflow: hidden;
        }

        .btn-secondary::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
            transition: left 0.6s ease;
        }

        .btn-secondary:hover {
            background: linear-gradient(135deg, #e2e8f0 0%, #cbd5e1 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15), inset 0 1px 0 rgba(255, 255, 255, 0.6);
        }

        .btn-secondary:hover::before {
            left: 100%;
        }

        .btn-secondary:active {
            transform: translateY(0);
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.12);
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            padding-top: 2rem;
            border-top: 1px solid var(--border);
        }

        /* Messages */
        .message {
            padding: 1rem 1.5rem;
            border-radius: var(--radius-md);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .success-message {
            background: var(--success-light);
            color: var(--success);
            border: 1px solid var(--success);
        }

        .error-message {
            background: var(--error-light);
            color: var(--error);
            border: 1px solid var(--error);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .admin-sidebar {
                transform: translateX(-100%);
            }
            
            .admin-main {
                margin-left: 0;
            }
            
            .form-container {
                padding: 0 1rem;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <!-- Sidebar -->
        <aside class="admin-sidebar">
            <div class="sidebar-header">
                <div class="sidebar-title">VitalWear Admin</div>
                <div class="sidebar-subtitle">System Management</div>
            </div>
            
            <nav class="nav-menu">
                <div class="nav-group">
                    <a href="../dashboard.php" class="nav-item">
                        <i class="fa fa-gauge"></i> Dashboard
                    </a>
                </div>
                
                <div class="nav-group">
                    <div class="nav-group-title">User Management</div>
                    <div class="nav-group-items">
                        <a href="../users.php" class="nav-item">
                            <i class="fa fa-users"></i> Staff Directory
                        </a>
                        <a href="view_management.php" class="nav-item">
                            <i class="fa fa-user-tie"></i> Management
                        </a>
                        <a href="view_responders.php" class="nav-item">
                            <i class="fa fa-user-md"></i> Responders
                        </a>
                        <a href="view_rescuers.php" class="nav-item active">
                            <i class="fa fa-user-shield"></i> Rescuers
                        </a>
                        <a href="view_admins.php" class="nav-item">
                            <i class="fa fa-user-cog"></i> Admins
                        </a>
                    </div>
                </div>
                
                <div class="nav-group">
                    <div class="nav-group-title">Reports</div>
                    <div class="nav-group-items">
                        <a href="../system_reports.php" class="nav-item">
                            <i class="fa fa-chart-line"></i> System Reports
                        </a>
                        <a href="../vitals_analytics.php" class="nav-item">
                            <i class="fa fa-heartbeat"></i> Vital Analytics
                        </a>
                        <a href="../audit_log.php" class="nav-item">
                            <i class="fa fa-clipboard-list"></i> Activity Log
                        </a>
                    </div>
                </div>
                
                <div class="nav-group">
                    <div class="nav-group-title">Monitoring</div>
                    <div class="nav-group-items">
                        <a href="../device_incidents.php" class="nav-item">
                            <i class="fa fa-box"></i> Device Overview
                        </a>
                        <a href="../vitals.php" class="nav-item">
                            <i class="fa fa-user-clock"></i> User Activity
                        </a>
                    </div>
                </div>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="admin-main">
            <!-- Modern Header -->
            <header class="admin-header">
                <div>
                    <h1><i class="fa fa-user-shield"></i> Update Rescuer Account</h1>
                </div>
                <div>
                    <span style="color: var(--text-secondary); margin-right: 16px;">Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                    <a href="/VitalWear-1/logout.php" class="btn btn-primary">
                        <i class="fa fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </header>

            <!-- Page Header -->
            <div class="page-header">
                <h1><i class="fa fa-user-shield"></i> Update Rescuer</h1>
                <p>Edit rescuer account information</p>
            </div>

            <!-- Form Container -->
            <div class="form-container">
                <?php if ($error_message): ?>
                    <div class="message error-message">
                        <i class="fa fa-exclamation-circle"></i>
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>

                <?php if ($success_message): ?>
                    <div class="message success-message">
                        <i class="fa fa-check-circle"></i>
                        <?php echo htmlspecialchars($success_message); ?>
                    </div>
                <?php endif; ?>

                <?php if ($rescuer): ?>
                    <div class="form-card">
                        <div class="form-header">
                            <h2><i class="fa fa-user-edit"></i> Rescuer Information</h2>
                            <p>Update the rescuer account details below</p>
                        </div>
                        
                        <form method="POST" class="form-body">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label">
                                        <i class="fa fa-user"></i> Full Name <span class="required">*</span>
                                    </label>
                                    <input type="text" name="name" class="form-input" 
                                           value="<?php echo htmlspecialchars($rescuer['resc_name']); ?>" 
                                           placeholder="Enter rescuer's full name" required>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">
                                        <i class="fa fa-envelope"></i> Email Address <span class="required">*</span>
                                    </label>
                                    <input type="email" name="email" class="form-input" 
                                           value="<?php echo htmlspecialchars($rescuer['resc_email']); ?>" 
                                           placeholder="rescuer@vitalwear.com" required>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">
                                        <i class="fa fa-phone"></i> Contact Number
                                    </label>
                                    <input type="tel" name="contact" class="form-input" 
                                           value="<?php echo htmlspecialchars($rescuer['resc_contact'] ?? ''); ?>" 
                                           placeholder="+1 (555) 123-4567">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">
                                        <i class="fa fa-lock"></i> New Password
                                    </label>
                                    <input type="password" name="password" class="form-input" 
                                           placeholder="Leave blank to keep current password">
                                </div>
                                
                                <div class="form-group full-width">
                                    <p style="color: var(--text-secondary); font-size: 0.875rem; margin-top: 0.5rem;">
                                        <i class="fa fa-info-circle"></i> 
                                        Password must be at least 6 characters long. Leave blank to keep current password.
                                    </p>
                                </div>
                            </div>
                            
                            <div class="form-actions">
                                <a href="view_rescuers.php" class="btn btn-secondary">
                                    <i class="fa fa-arrow-left"></i> Cancel
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fa fa-save"></i> Update Rescuer
                                </button>
                            </div>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="message error-message">
                        <i class="fa fa-exclamation-circle"></i>
                        Rescuer not found or invalid ID.
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>
