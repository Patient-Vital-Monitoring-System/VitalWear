<?php
session_start();
require_once '../../../database/connection.php';

// Check if admin user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /VitalWear-1/login.html');
    exit();
}

$conn = getDBConnection();

// Initialize variables
$error_message = '';

// Get rescuer ID from URL
$rescuer_id = $_GET['id'] ?? '';

// Validate ID
if (empty($rescuer_id) || !is_numeric($rescuer_id)) {
    header('Location: view_rescuers.php');
    exit();
}

// Get current rescuer data for confirmation
$rescuer = null;
try {
    // Check database connection
    if (!$conn) {
        $error_message = "Database connection failed. Please check your database configuration.";
    } else {
        // Check if rescuer table exists
        $table_check = $conn->query("SHOW TABLES LIKE 'rescuer'");
        if ($table_check->num_rows == 0) {
            $error_message = "Rescuer table does not exist in the database.";
        } else {
            // Check table structure to see which columns exist
            $columns_result = $conn->query("SHOW COLUMNS FROM rescuer");
            $existing_columns = [];
            while ($row = $columns_result->fetch_assoc()) {
                $existing_columns[] = $row['Field'];
            }
            
            // Build select query based on existing columns
            $select_columns = ['resc_id', 'resc_name', 'resc_email'];
            if (in_array('resc_contact', $existing_columns)) {
                $select_columns[] = 'resc_contact';
            }
            
            $select_query = "SELECT " . implode(', ', $select_columns) . " FROM rescuer WHERE resc_id = ?";
            $stmt = $conn->prepare($select_query);
            
            if (!$stmt) {
                $error_message = "Failed to prepare select query: " . $conn->error . "<br><br>Available columns: " . implode(', ', $existing_columns);
            } else {
                $stmt->bind_param("i", $rescuer_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows === 0) {
                    header('Location: view_rescuers.php');
                    exit();
                }
                
                $rescuer = $result->fetch_assoc();
            }
        }
    }
} catch (Exception $e) {
    $error_message = "Error fetching rescuer data: " . $e->getMessage();
}

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    // Check database connection
    if (!$conn) {
        $error_message = "Database connection failed. Please check your database configuration.";
    } else {
        try {
            // Check if rescuer table exists
            $table_check = $conn->query("SHOW TABLES LIKE 'rescuer'");
            if ($table_check->num_rows == 0) {
                $error_message = "Rescuer table does not exist in the database.";
            } else {
                // Delete the rescuer
                $delete_query = "DELETE FROM rescuer WHERE resc_id = ?";
                $delete_stmt = $conn->prepare($delete_query);
                
                if (!$delete_stmt) {
                    $error_message = "Failed to prepare delete query: " . $conn->error;
                } else {
                    $delete_stmt->bind_param("i", $rescuer_id);
                    
                    if ($delete_stmt->execute()) {
                        $success_message = "Rescuer account deleted successfully!";
                        // Redirect after successful deletion
                        header('Location: view_rescuers.php?message=' . urlencode($success_message));
                        exit();
                    } else {
                        $error_message = "Error deleting rescuer account: " . $delete_stmt->error;
                    }
                }
            }
        } catch (Exception $e) {
            $error_message = "Database error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Rescuer Account - VitalWear Admin</title>
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

        .warning-box {
            background: var(--warning-bg);
            border: 1px solid var(--warning);
            border-radius: var(--radius);
            padding: 1.5rem;
            margin-bottom: 2rem;
            display: flex;
            align-items: flex-start;
            gap: 1rem;
        }

        .warning-box i {
            color: var(--warning);
            font-size: 1.5rem;
            margin-top: 0.25rem;
        }

        .warning-box h3 {
            color: var(--warning);
            margin: 0 0 0.5rem 0;
            font-size: 1.125rem;
            font-weight: 600;
        }

        .warning-box p {
            margin: 0;
            color: var(--text-secondary);
            line-height: 1.6;
        }

        .rescuer-info {
            background: var(--surface-alt);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .rescuer-info h4 {
            margin: 0 0 1rem 0;
            color: var(--text-primary);
            font-size: 1rem;
            font-weight: 600;
        }

        .info-row {
            display: flex;
            margin-bottom: 0.75rem;
        }

        .info-row:last-child {
            margin-bottom: 0;
        }

        .info-label {
            font-weight: 500;
            color: var(--text-secondary);
            min-width: 120px;
        }

        .info-value {
            color: var(--text-primary);
            font-weight: 400;
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

        .btn-danger {
            background: var(--error);
            color: var(--text-inverse);
        }

        .btn-danger:hover {
            background: #c82333;
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
    </style>
</head>
<body>
    <div class="container">
        <div class="breadcrumb">
            <a href="/VitalWear-1/roles/admin/dashboard.php">Dashboard</a>
            <span>/</span>
            <a href="view_rescuers.php">Rescuers</a>
            <span>/</span>
            <span>Delete Rescuer</span>
        </div>

        <?php if ($rescuer): ?>
            <div class="form-card">
                <div class="form-header">
                    <h1>Delete Rescuer Account</h1>
                    <p>This action cannot be undone</p>
                </div>

                <?php if (!$conn): ?>
                    <div class="error-message">
                        <i class="fa fa-exclamation-circle"></i> 
                        Database connection failed. Please check your database configuration.
                    </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                    <div class="error-message">
                        <i class="fa fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>

                <div class="warning-box">
                    <i class="fa fa-exclamation-triangle"></i>
                    <div>
                        <h3>Warning: Permanent Action</h3>
                        <p>You are about to permanently delete this rescuer account. This action cannot be undone and all associated data will be lost.</p>
                    </div>
                </div>

                <div class="rescuer-info">
                    <h4>Rescuer Information</h4>
                    <div class="info-row">
                        <span class="info-label">Name:</span>
                        <span class="info-value"><?php echo htmlspecialchars($rescuer['resc_name']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Email:</span>
                        <span class="info-value"><?php echo htmlspecialchars($rescuer['resc_email']); ?></span>
                    </div>
                    <?php if (isset($rescuer['resc_contact'])): ?>
                    <div class="info-row">
                        <span class="info-label">Contact:</span>
                        <span class="info-value"><?php echo htmlspecialchars($rescuer['resc_contact']); ?></span>
                    </div>
                    <?php endif; ?>
                </div>

                <form method="POST" action="">
                    <div class="form-actions">
                        <a href="view_rescuers.php" class="btn btn-secondary">
                            Cancel
                        </a>
                        <button type="submit" name="confirm_delete" class="btn btn-danger">
                            <i class="fa fa-trash"></i>
                            Delete Account
                        </button>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <div class="form-card">
                <div class="error-message">
                    <i class="fa fa-exclamation-circle"></i> Rescuer account not found.
                </div>
                <div style="text-align: center; margin-top: 1rem;">
                    <a href="view_rescuers.php" class="btn btn-secondary">Back to Rescuers</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
