<?php
session_start();
require_once '../../database/connection.php';

// Check if management user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'management') {
    header('Location: ../../login.html');
    exit();
}

$conn = getDBConnection();
$message = '';
$alert_type = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'verify_return') {
        $device_id = $_POST['device_id'];
        $log_id = $_POST['log_id'];
        $management_id = $_SESSION['user_id'];
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Update device_log with return date and verification
            $stmt = $conn->prepare("UPDATE device_log SET date_returned = NOW(), verified_return = 1 WHERE log_id = ?");
            $stmt->bind_param("i", $log_id);
            $stmt->execute();
            
            // Update device status to available
            $stmt = $conn->prepare("UPDATE device SET dev_status = 'available' WHERE dev_id = ?");
            $stmt->bind_param("i", $device_id);
            $stmt->execute();
            
            // Get device and responder info for logging
            $device_result = $conn->query("SELECT dev_serial FROM device WHERE dev_id = $device_id");
            $device_info = $device_result->fetch_assoc();
            
            $log_result = $conn->query("SELECT dl.resp_id, r.resp_name, r.resp_email FROM device_log dl JOIN responder r ON dl.resp_id = r.resp_id WHERE dl.log_id = $log_id");
            $log_info = $log_result->fetch_assoc();
            
            // Log activity
            $log_desc = "Verified return of device {$device_info['dev_serial']} from {$log_info['resp_name']} ({$log_info['resp_email']})";
            $stmt_log = $conn->prepare("INSERT INTO activity_log (user_name, user_role, action_type, module, description) VALUES (?, ?, 'verify_return', 'device', ?)");
            $stmt_log->bind_param("sss", $_SESSION['user_name'], $_SESSION['user_role'], $log_desc);
            $stmt_log->execute();
            
            $conn->commit();
            
            $message = "Device return verified successfully!";
            $alert_type = "success";
            
        } catch (Exception $e) {
            $conn->rollback();
            $message = "Error verifying return: " . $e->getMessage();
            $alert_type = "danger";
        }
    }
}

// Get devices pending return verification
$pending_returns = [];
$result = $conn->query("
    SELECT dl.*, d.dev_serial, r.resp_name, r.resp_email, m.mgmt_name
    FROM device_log dl
    JOIN device d ON dl.dev_id = d.dev_id
    JOIN responder r ON dl.resp_id = r.resp_id
    JOIN management m ON dl.mgmt_id = m.mgmt_id
    WHERE dl.date_returned IS NULL
    ORDER BY dl.date_assigned DESC
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $pending_returns[] = $row;
    }
}

// Get recently verified returns
$verified_returns = [];
$result = $conn->query("
    SELECT dl.*, d.dev_serial, r.resp_name, r.resp_email, m.mgmt_name
    FROM device_log dl
    JOIN device d ON dl.dev_id = d.dev_id
    JOIN responder r ON dl.resp_id = r.resp_id
    JOIN management m ON dl.mgmt_id = m.mgmt_id
    WHERE dl.date_returned IS NOT NULL AND dl.verified_return = 1
    ORDER BY dl.date_returned DESC
    LIMIT 10
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $verified_returns[] = $row;
    }
}

// Get selected device for verification
$selected_device = null;
if (isset($_GET['device_id'])) {
    $device_id = $_GET['device_id'];
    $stmt = $conn->prepare("
        SELECT dl.*, d.dev_serial, r.resp_name, r.resp_email, m.mgmt_name
        FROM device_log dl
        JOIN device d ON dl.dev_id = d.dev_id
        JOIN responder r ON dl.resp_id = r.resp_id
        JOIN management m ON dl.mgmt_id = m.mgmt_id
        WHERE dl.dev_id = ? AND dl.date_returned IS NULL
    ");
    $stmt->bind_param("i", $device_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 1) {
        $selected_device = $result->fetch_assoc();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Device Return - VitalWear</title>
        <script src="https://kit.fontawesome.com/96e37b53f1.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* VitalWear Soft UI Design System */
        :root {
            --authority-blue: #1B3F72;
            --dashboard-light: #F4F7FC;
            --pure-white: #FFFFFF;
            --secondary-text: #7E91B3;
            --system-success: #2CC990;
            --system-warning: #FFC107;
            --system-error: #DC3545;
            --interface-border: #D1E0F1;
            --radius: 12px;
            --radius-lg: 16px;
            --shadow-sm: 0 2px 4px rgba(27, 63, 114, 0.06);
            --shadow: 0 4px 12px rgba(27, 63, 114, 0.08);
            --shadow-md: 0 8px 24px rgba(27, 63, 114, 0.12);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background-color: var(--dashboard-light);
            color: var(--authority-blue);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            line-height: 1.6;
            overflow-x: hidden;
        }

        /* Soft UI Sidebar */
        #sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 260px;
            height: 100vh;
            background: var(--pure-white);
            border-right: 1px solid var(--interface-border);
            box-shadow: var(--shadow);
            z-index: 1000;
            overflow-y: auto;
            transition: transform 0.3s ease;
        }

        .sidebar-logo {
            padding: 24px 20px;
            text-align: center;
            background: linear-gradient(135deg, var(--authority-blue) 0%, #2a5298 100%);
            margin: 12px;
            border-radius: var(--radius);
        }

        .sidebar-logo img {
            max-width: 140px;
            height: auto;
            filter: brightness(0) invert(1);
        }

        #sidebar a {
            color: var(--authority-blue);
            margin: 6px 12px;
            padding: 12px 16px;
            border-radius: var(--radius);
            transition: all 0.2s ease;
            border: none;
            font-weight: 500;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        #sidebar a:hover {
            background: rgba(27, 63, 114, 0.1);
            color: var(--authority-blue);
            transform: translateX(4px);
        }

        #sidebar a.active {
            background: rgba(27, 63, 114, 0.15);
            color: var(--authority-blue);
        }

        /* Soft UI Header */
        .topbar {
            position: fixed;
            top: 0;
            left: 260px;
            right: 0;
            background: var(--pure-white);
            color: var(--authority-blue);
            border-bottom: 1px solid var(--interface-border);
            box-shadow: var(--shadow-sm);
            padding: 16px 24px;
            font-weight: 600;
            z-index: 999;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        /* Main Container */
        .container {
            margin-left: 260px;
            margin-top: 80px;
            padding: 24px;
            min-height: calc(100vh - 80px);
            transition: margin-left 0.3s ease;
        }

        h1, h2, h3, h4, h5, h6 {
            color: var(--authority-blue);
            font-weight: 700;
            line-height: 1.3;
        }

        /* Page Header */
        .page-header {
            background: linear-gradient(135deg, var(--authority-blue) 0%, #2a5298 100%);
            color: var(--pure-white);
            padding: 32px;
            border-radius: var(--radius-lg);
            margin-bottom: 32px;
            box-shadow: var(--shadow-md);
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            border-radius: 50%;
        }

        .page-header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            z-index: 1;
        }

        .page-header h1 {
            color: var(--pure-white);
            margin: 0 0 8px 0;
            font-size: 1.75rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .page-header p {
            color: rgba(255,255,255,0.9);
            margin: 0;
            font-size: 1rem;
        }

        .page-header-actions {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        /* Buttons */
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: var(--radius);
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s ease;
        }

        .btn:hover::before {
            left: 100%;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--system-success) 0%, #20c997 100%);
            color: var(--pure-white);
            box-shadow: var(--shadow);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .btn-success {
            background: linear-gradient(135deg, var(--system-success) 0%, #20c997 100%);
            color: var(--pure-white);
            box-shadow: var(--shadow);
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .btn-warning {
            background: linear-gradient(135deg, var(--system-warning) 0%, #e0a800 100%);
            color: var(--pure-white);
            box-shadow: var(--shadow);
        }

        .btn-warning:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .btn-secondary {
            background: linear-gradient(135deg, var(--secondary-text) 0%, #6b7280 100%);
            color: var(--pure-white);
            box-shadow: var(--shadow);
        }

        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 32px;
        }
        
        /* Verification Container */
        .verification-container, .history-container {
            background: var(--pure-white);
            padding: 32px;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            border: 1px solid var(--interface-border);
            transition: all 0.3s ease;
        }

        .verification-container:hover, .history-container:hover {
            box-shadow: var(--shadow-md);
        }

        .verification-container h2, .history-container h2 {
            margin: 0 0 24px 0;
            color: var(--authority-blue);
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        /* Alerts */
        .alert {
            padding: 16px 20px;
            border-radius: var(--radius);
            margin-bottom: 24px;
            border: 1px solid;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .alert-success {
            background: rgba(44, 201, 144, 0.15);
            color: var(--system-success);
            border-color: rgba(44, 201, 144, 0.3);
        }

        .alert-danger {
            background: rgba(220, 53, 69, 0.15);
            color: var(--system-error);
            border-color: rgba(220, 53, 69, 0.3);
        }

        /* Device Preview */
        .device-preview {
            background: var(--dashboard-light);
            padding: 20px;
            border-radius: var(--radius);
            margin-bottom: 24px;
            border-left: 4px solid var(--system-warning);
            border: 1px solid var(--interface-border);
        }

        .device-preview h4 {
            margin: 0 0 16px 0;
            color: var(--authority-blue);
            font-weight: 600;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .device-preview p {
            margin: 8px 0;
            font-size: 14px;
            color: var(--authority-blue);
            font-weight: 500;
        }

        .device-preview strong {
            color: var(--authority-blue);
            font-weight: 600;
        }

        /* Verification Form */
        .verification-form {
            background: rgba(255, 193, 7, 0.1);
            padding: 24px;
            border-radius: var(--radius);
            border: 1px solid rgba(255, 193, 7, 0.3);
            margin-bottom: 24px;
        }

        .verification-form h4 {
            margin: 0 0 16px 0;
            color: var(--system-warning);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .warning-text {
            color: var(--system-warning);
            font-size: 14px;
            margin-bottom: 20px;
            font-weight: 500;
            line-height: 1.6;
        }

        /* Duration Badge */
        .duration {
            background: rgba(27, 63, 114, 0.1);
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            color: var(--authority-blue);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: var(--dashboard-light);
            padding: 20px;
            border-radius: var(--radius);
            text-align: center;
            border: 1px solid var(--interface-border);
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--authority-blue);
            margin-bottom: 8px;
        }

        .stat-label {
            font-size: 12px;
            color: var(--secondary-text);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        /* Return Lists */
        .pending-list, .verified-list {
            max-height: 400px;
            overflow-y: auto;
            padding-right: 8px;
        }

        .return-item {
            padding: 16px;
            border: 1px solid var(--interface-border);
            border-radius: var(--radius);
            margin-bottom: 12px;
            transition: all 0.3s ease;
            background: var(--pure-white);
        }

        .return-item:hover {
            background: var(--dashboard-light);
            transform: translateX(4px);
            box-shadow: var(--shadow-sm);
        }

        .return-info h4 {
            margin: 0 0 12px 0;
            color: var(--authority-blue);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .return-info p {
            margin: 6px 0;
            font-size: 12px;
            color: var(--secondary-text);
            font-weight: 500;
        }

        .return-info strong {
            color: var(--authority-blue);
            font-weight: 600;
        }

        /* Verified Badge */
        .verified-badge {
            background: rgba(44, 201, 144, 0.15);
            color: var(--system-success);
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: 1px solid rgba(44, 201, 144, 0.3);
        }

        /* Actions */
        .actions {
            display: flex;
            gap: 12px;
            margin-top: 20px;
        }

        /* Section Headers */
        h3 {
            margin: 24px 0 16px 0;
            color: var(--authority-blue);
            font-weight: 600;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            color: var(--secondary-text);
            padding: 32px;
            font-size: 14px;
            font-weight: 500;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            #sidebar {
                transform: translateX(-100%);
            }
            
            #sidebar.open {
                transform: translateX(0);
            }
            
            .topbar {
                left: 0;
            }
            
            .container {
                margin-left: 0;
                padding: 16px;
            }
            
            .content-grid {
                grid-template-columns: 1fr;
                gap: 24px;
            }
            
            .page-header-content {
                flex-direction: column;
                gap: 20px;
                text-align: center;
            }
            
            .page-header-actions {
                width: 100%;
            }
            
            .page-header-actions .btn {
                width: 100%;
                justify-content: center;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Scrollbar Styling */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--dashboard-light);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--secondary-text);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--authority-blue);
        }
    </style>
</head>
<body>
    <header class="topbar">
        <div style="display: flex; align-items: center; gap: 12px;">
            <i class="fa fa-cogs" style="font-size: 24px; color: var(--authority-blue);"></i>
            <span style="font-size: 18px; font-weight: 700;">VitalWear</span>
        </div>
        <div style="display: flex; align-items: center; gap: 8px; color: var(--authority-blue); font-weight: 500;">
            <i class="fa fa-user-circle" style="font-size: 20px; color: var(--authority-blue);"></i>
            <span><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
        </div>
    </header>

    <nav id="sidebar">
        <div class="sidebar-logo">
            <img src="../../../assets/logo.png" alt="VitalWear Logo">
        </div>
        <a href="dashboard.php"><i class="fa fa-gauge"></i> Dashboard</a>
        <a href="manage_responders.php"><i class="fa fa-user-md"></i> Manage Responders</a>
        <a href="manage_rescuers.php"><i class="fa fa-user-shield"></i> Manage Rescuers</a>
        <a href="register_device.php"><i class="fa fa-plus-circle"></i> Register Device</a>
        <a href="device_list.php"><i class="fa fa-box"></i> Device List</a>
        <a href="assign_device.php"><i class="fa fa-exchange-alt"></i> Assign Device</a>
        <a href="verify_return.php" class="active"><i class="fa fa-check-double"></i> Verify Return</a>
        <a href="reports/reportdashboard.php"><i class="fa fa-chart-bar"></i> Reports</a>
        <a href="/VitalWear-1/logout.php" class="btn btn-secondary">Logout</a>
    </nav>

    <main class="container">
        <header class="page-header">
            <div class="page-header-content">
                <div>
                    <h1><i class="fa fa-check-double"></i> Verify Device Return</h1>
                    <p>Check and verify returned devices from responders</p>
                </div>
                <div class="page-header-actions">
                    <a href="device_list.php" class="btn btn-primary">
                        <i class="fa fa-box"></i> View All Devices
                    </a>
                </div>
            </div>
        </header>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $alert_type; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="content-grid">
            <div class="verification-container">
                <h2><i class="fa fa-check-double"></i> Device Return Verification</h2>
                
                <?php if ($selected_device): ?>
                    <div class="device-preview">
                        <h4><i class="fa fa-box"></i> Device to Verify</h4>
                        <p><strong><i class="fa fa-barcode"></i> Serial Number:</strong> <?php echo htmlspecialchars($selected_device['dev_serial']); ?></p>
                        <p><strong><i class="fa fa-user-md"></i> Assigned to:</strong> <?php echo htmlspecialchars($selected_device['resp_name']); ?></p>
                        <p><strong><i class="fa fa-envelope"></i> Email:</strong> <?php echo htmlspecialchars($selected_device['resp_email']); ?></p>
                        <p><strong><i class="fa fa-user"></i> Assigned by:</strong> <?php echo htmlspecialchars($selected_device['mgmt_name']); ?></p>
                        <p><strong><i class="fa fa-calendar"></i> Assignment Date:</strong> <?php echo date('M j, Y H:i', strtotime($selected_device['date_assigned'])); ?></p>
                        <p><strong><i class="fa fa-clock"></i> Duration:</strong> 
                            <span class="duration">
                                <?php 
                                $assigned = new DateTime($selected_device['date_assigned']);
                                $now = new DateTime();
                                $interval = $assigned->diff($now);
                                echo $interval->days . ' days';
                                ?>
                            </span>
                        </p>
                    </div>
                    
                    <div class="verification-form">
                        <h4><i class="fa fa-exclamation-triangle"></i> Verification Required</h4>
                        <p class="warning-text">
                            Please confirm that the device has been physically returned and is in good working condition before verifying.
                        </p>
                        
                        <form method="POST">
                            <input type="hidden" name="action" value="verify_return">
                            <input type="hidden" name="device_id" value="<?php echo $selected_device['dev_id']; ?>">
                            <input type="hidden" name="log_id" value="<?php echo $selected_device['log_id']; ?>">
                            
                            <div class="actions">
                                <button type="submit" class="btn btn-success" onclick="return confirm('Are you sure you want to verify this device return?')">
                                    <i class="fa fa-check-circle"></i> Confirm Return Verification
                                </button>
                                <a href="verify_return.php" class="btn btn-secondary">
                                    <i class="fa fa-times"></i> Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fa fa-hand-pointer" style="font-size: 48px; margin-bottom: 16px; opacity: 0.5;"></i>
                        <p>Select a device from the list to verify its return.</p>
                    </div>
                <?php endif; ?>
                
                <?php if (empty($pending_returns)): ?>
                    <div class="empty-state">
                        <i class="fa fa-check-circle" style="font-size: 48px; margin-bottom: 16px; opacity: 0.5;"></i>
                        <p>No devices pending return verification.</p>
                        <a href="assign_device.php" class="btn btn-primary">
                            <i class="fa fa-exchange-alt"></i> Assign Devices
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <div class="history-container">
                <h2><i class="fa fa-history"></i> Return History</h2>
                
                <?php
                // Calculate statistics
                $pending_count = count($pending_returns);
                $verified_count = count($verified_returns);
                $total_returns = $pending_count + $verified_count;
                ?>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $pending_count; ?></div>
                        <div class="stat-label">Pending</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $verified_count; ?></div>
                        <div class="stat-label">Verified</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $total_returns; ?></div>
                        <div class="stat-label">Total Returns</div>
                    </div>
                </div>
                
                <h3><i class="fa fa-hourglass-half"></i> Pending Verification</h3>
                <div class="pending-list">
                    <?php if (empty($pending_returns)): ?>
                        <div class="empty-state">
                            <i class="fa fa-check-circle" style="font-size: 48px; margin-bottom: 16px; opacity: 0.5;"></i>
                            <p>No pending returns.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($pending_returns as $return): ?>
                            <div class="return-item">
                                <div class="return-info">
                                    <h4><i class="fa fa-box"></i> <?php echo htmlspecialchars($return['dev_serial']); ?></h4>
                                    <p><strong><i class="fa fa-user-md"></i> From:</strong> <?php echo htmlspecialchars($return['resp_name']); ?></p>
                                    <p><strong><i class="fa fa-envelope"></i> Email:</strong> <?php echo htmlspecialchars($return['resp_email']); ?></p>
                                    <p><strong><i class="fa fa-calendar"></i> Assigned:</strong> <?php echo date('M j, Y H:i', strtotime($return['date_assigned'])); ?></p>
                                    <p><strong><i class="fa fa-clock"></i> Duration:</strong> 
                                        <?php 
                                        $assigned = new DateTime($return['date_assigned']);
                                        $now = new DateTime();
                                        $interval = $assigned->diff($now);
                                        echo $interval->days . ' days';
                                        ?>
                                    </p>
                                </div>
                                <div class="actions">
                                    <a href="?device_id=<?php echo $return['dev_id']; ?>" class="btn btn-warning">
                                        <i class="fa fa-check"></i> Verify Return
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <h3><i class="fa fa-check-circle"></i> Recently Verified</h3>
                <div class="verified-list">
                    <?php if (empty($verified_returns)): ?>
                        <div class="empty-state">
                            <i class="fa fa-history" style="font-size: 48px; margin-bottom: 16px; opacity: 0.5;"></i>
                            <p>No verified returns yet.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($verified_returns as $return): ?>
                            <div class="return-item">
                                <div class="return-info">
                                    <h4>
                                        <i class="fa fa-box"></i> <?php echo htmlspecialchars($return['dev_serial']); ?> 
                                        <span class="verified-badge">VERIFIED</span>
                                    </h4>
                                    <p><strong><i class="fa fa-user-md"></i> From:</strong> <?php echo htmlspecialchars($return['resp_name']); ?></p>
                                    <p><strong><i class="fa fa-calendar-check"></i> Returned:</strong> <?php echo date('M j, Y H:i', strtotime($return['date_returned'])); ?></p>
                                    <p><strong><i class="fa fa-clock"></i> Duration:</strong> 
                                        <?php 
                                        $assigned = new DateTime($return['date_assigned']);
                                        $returned = new DateTime($return['date_returned']);
                                        $interval = $assigned->diff($returned);
                                        echo $interval->days . ' days';
                                        ?>
                                    </p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
