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

// Get admin data for confirmation
$admin = null;
try {
    $stmt = $conn->prepare("SELECT admin_id, admin_name, admin_email FROM admin WHERE admin_id = ?");
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        header('Location: view_admins.php');
        exit();
    }
    
    $admin = $result->fetch_assoc();
} catch (Exception $e) {
    $error_message = "Error fetching admin data: " . $e->getMessage();
}

// Handle deletion confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    try {
        // Prevent deletion of current admin
        if ($admin_id == $_SESSION['user_id']) {
            $error_message = "You cannot delete your own account.";
        } else {
            // Check if this is the last admin
            $count_stmt = $conn->prepare("SELECT COUNT(*) as admin_count FROM admin");
            $count_stmt->execute();
            $count_result = $count_stmt->get_result();
            $admin_count = $count_result->fetch_assoc()['admin_count'];
            
            if ($admin_count <= 1) {
                $error_message = "Cannot delete the last administrator account. At least one admin must remain in the system.";
            } else {
                // Delete admin account
                $delete_stmt = $conn->prepare("DELETE FROM admin WHERE admin_id = ?");
                $delete_stmt->bind_param("i", $admin_id);
                
                if ($delete_stmt->execute()) {
                    $success_message = "Admin account deleted successfully!";
                    // Redirect after successful deletion
                    header("refresh:2;url=view_admins.php");
                } else {
                    $error_message = "Error deleting admin account: " . $conn->error;
                }
            }
        }
    } catch (Exception $e) {
        $error_message = "Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Admin Account - VitalWear Admin</title>
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
            --warning: #F59E0B;
            --warning-light: #FEF3C7;
            
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
            max-width: 600px;
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

        .warning-box {
            background: var(--warning-light);
            border: 2px solid var(--warning);
            border-radius: var(--radius);
            padding: 1.5rem;
            margin-bottom: 2rem;
            text-align: center;
        }

        .warning-box h3 {
            color: var(--warning);
            margin: 0 0 1rem 0;
            font-size: 1.25rem;
        }

        .user-details {
            background: var(--gray-50);
            border-radius: var(--radius);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .user-detail {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.75rem;
            font-size: 0.95rem;
        }

        .user-detail:last-child {
            margin-bottom: 0;
        }

        .user-detail-label {
            font-weight: 600;
            color: var(--text-secondary);
        }

        .user-detail-value {
            color: var(--text-primary);
            font-weight: 500;
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

        .btn-danger {
            background: var(--error);
            color: white;
        }

        .btn-danger:hover {
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
            justify-content: center;
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

        .warning-icon {
            font-size: 3rem;
            color: var(--warning);
            margin-bottom: 1rem;
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

        .critical-warning {
            background: var(--error-light);
            border: 2px solid var(--error);
            color: var(--error);
            padding: 1rem;
            border-radius: var(--radius);
            margin-bottom: 1rem;
            text-align: center;
            font-weight: 600;
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
            <span><i class="fa fa-trash"></i> Delete</span>
        </div>

        <?php if ($admin): ?>
            <div class="form-card">
                <div class="form-header">
                    <div class="warning-icon">
                        <i class="fa fa-exclamation-triangle"></i>
                    </div>
                    <h1>Delete Admin Account</h1>
                    <p>This action cannot be undone <span class="admin-badge">Critical Action</span></p>
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

                <?php if (!$success_message): ?>
                    <div class="warning-box">
                        <h3><i class="fa fa-exclamation-triangle"></i> Critical Warning</h3>
                        <p>You are about to permanently delete an administrator account. This action cannot be undone and will remove all administrative privileges from this user.</p>
                    </div>

                    <div class="user-details">
                        <div class="user-detail">
                            <span class="user-detail-label">Name:</span>
                            <span class="user-detail-value"><?php echo htmlspecialchars($admin['admin_name']); ?></span>
                        </div>
                        <div class="user-detail">
                            <span class="user-detail-label">Email:</span>
                            <span class="user-detail-value"><?php echo htmlspecialchars($admin['admin_email']); ?></span>
                        </div>
                        <div class="user-detail">
                            <span class="user-detail-label">ID:</span>
                            <span class="user-detail-value">#<?php echo htmlspecialchars($admin['admin_id']); ?></span>
                        </div>
                        <div class="user-detail">
                            <span class="user-detail-label">Privilege Level:</span>
                            <span class="user-detail-value"><span class="admin-badge">Administrator</span></span>
                        </div>
                    </div>

                    <?php if ($admin_id == $_SESSION['user_id']): ?>
                        <div class="critical-warning">
                            <i class="fa fa-user-shield"></i> You cannot delete your own account while logged in.
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="form-actions">
                            <a href="view_admins.php" class="btn btn-secondary">
                                <i class="fa fa-arrow-left"></i> Cancel
                            </a>
                            <?php if ($admin_id != $_SESSION['user_id']): ?>
                                <button type="submit" name="confirm_delete" value="yes" class="btn btn-danger">
                                    <i class="fa fa-trash"></i> Delete Admin Account
                                </button>
                            <?php endif; ?>
                        </div>
                    </form>
                <?php else: ?>
                    <div style="text-align: center; margin-top: 2rem;">
                        <a href="view_admins.php" class="btn btn-secondary">
                            <i class="fa fa-arrow-left"></i> Back to Admins
                        </a>
                    </div>
                <?php endif; ?>
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
