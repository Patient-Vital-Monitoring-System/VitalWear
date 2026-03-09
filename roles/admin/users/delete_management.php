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

// Get management data for confirmation
$management = null;
try {
    $stmt = $conn->prepare("SELECT mgmt_id, mgmt_name, mgmt_email FROM management WHERE mgmt_id = ?");
    $stmt->bind_param("i", $management_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        header('Location: view_management.php');
        exit();
    }
    
    $management = $result->fetch_assoc();
} catch (Exception $e) {
    $error_message = "Error fetching management data: " . $e->getMessage();
}

// Handle deletion confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    try {
        // Prevent deletion of current admin
        if ($management_id == $_SESSION['user_id']) {
            $error_message = "You cannot delete your own account.";
        } else {
            // Delete management account
            $delete_stmt = $conn->prepare("DELETE FROM management WHERE mgmt_id = ?");
            $delete_stmt->bind_param("i", $management_id);
            
            if ($delete_stmt->execute()) {
                $success_message = "Management account deleted successfully!";
                // Redirect after successful deletion
                header("refresh:2;url=view_management.php");
            } else {
                $error_message = "Error deleting management account: " . $conn->error;
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
    <title>Delete Management Account - VitalWear Admin</title>
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
            <span><i class="fa fa-trash"></i> Delete</span>
        </div>

        <?php if ($management): ?>
            <div class="form-card">
                <div class="form-header">
                    <div class="warning-icon">
                        <i class="fa fa-exclamation-triangle"></i>
                    </div>
                    <h1>Delete Management Account</h1>
                    <p>This action cannot be undone</p>
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
                        <h3><i class="fa fa-exclamation-triangle"></i> Warning</h3>
                        <p>You are about to permanently delete this management account. This action cannot be undone and all associated data will be lost.</p>
                    </div>

                    <div class="user-details">
                        <div class="user-detail">
                            <span class="user-detail-label">Name:</span>
                            <span class="user-detail-value"><?php echo htmlspecialchars($management['mgmt_name']); ?></span>
                        </div>
                        <div class="user-detail">
                            <span class="user-detail-label">Email:</span>
                            <span class="user-detail-value"><?php echo htmlspecialchars($management['mgmt_email']); ?></span>
                        </div>
                        <div class="user-detail">
                            <span class="user-detail-label">ID:</span>
                            <span class="user-detail-value">#<?php echo htmlspecialchars($management['mgmt_id']); ?></span>
                        </div>
                    </div>

                    <form method="POST" action="">
                        <div class="form-actions">
                            <a href="view_management.php" class="btn btn-secondary">
                                <i class="fa fa-arrow-left"></i> Cancel
                            </a>
                            <button type="submit" name="confirm_delete" value="yes" class="btn btn-danger">
                                <i class="fa fa-trash"></i> Delete Account
                            </button>
                        </div>
                    </form>
                <?php else: ?>
                    <div style="text-align: center; margin-top: 2rem;">
                        <a href="view_management.php" class="btn btn-secondary">
                            <i class="fa fa-arrow-left"></i> Back to Management
                        </a>
                    </div>
                <?php endif; ?>
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
