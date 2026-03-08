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
    <link rel="stylesheet" href="../../../assets/css/styles.css">
    <style>
        .page-header {
            background: #007bff;
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: background-color 0.2s;
        }
        
        .btn-primary { background: #007bff; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-warning { background: #ffc107; color: black; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        
        .btn:hover { opacity: 0.9; }
        
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }
        
        .verification-container, .history-container {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
        .device-preview {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #ffc107;
        }
        
        .device-preview h4 {
            margin: 0 0 15px 0;
            color: #333;
            font-size: 18px;
        }
        
        .device-preview p {
            margin: 8px 0;
            font-size: 14px;
            color: #666;
        }
        
        .device-preview strong {
            color: #333;
        }
        
        .pending-list, .verified-list {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .return-item {
            padding: 15px;
            border: 1px solid #eee;
            border-radius: 5px;
            margin-bottom: 10px;
            transition: background-color 0.2s;
        }
        
        .return-item:hover {
            background-color: #f8f9fa;
        }
        
        .return-info h4 {
            margin: 0 0 8px 0;
            color: #333;
        }
        
        .return-info p {
            margin: 4px 0;
            font-size: 12px;
            color: #666;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            text-align: center;
            padding: 15px;
            border-radius: 5px;
            background: #f8f9fa;
        }
        
        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #007bff;
        }
        
        .stat-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
        }
        
        .verification-form {
            background: #fff3cd;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #ffeaa7;
        }
        
        .verification-form h4 {
            margin: 0 0 15px 0;
            color: #856404;
        }
        
        .warning-text {
            color: #856404;
            font-size: 14px;
            margin-bottom: 15px;
        }
        
        .actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .duration {
            background: #e3f2fd;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            color: #1565c0;
            font-weight: bold;
        }
        
        .verified-badge {
            background: #d4edda;
            color: #155724;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
        }
        
        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="page-header">
            <div>
                <h1 style="margin: 0;">✅ Verify Device Return</h1>
                <p style="margin: 5px 0 0 0; opacity: 0.9;">Check and verify returned devices from responders</p>
            </div>
            <div>
                <a href="dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
                <a href="device_list.php" class="btn btn-primary">View All Devices</a>
            </div>
        </header>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $alert_type; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="content-grid">
            <div class="verification-container">
                <h2>Device Return Verification</h2>
                
                <?php if ($selected_device): ?>
                    <div class="device-preview">
                        <h4>Device to Verify</h4>
                        <p><strong>Serial Number:</strong> <?php echo htmlspecialchars($selected_device['dev_serial']); ?></p>
                        <p><strong>Assigned to:</strong> <?php echo htmlspecialchars($selected_device['resp_name']); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($selected_device['resp_email']); ?></p>
                        <p><strong>Assigned by:</strong> <?php echo htmlspecialchars($selected_device['mgmt_name']); ?></p>
                        <p><strong>Assignment Date:</strong> <?php echo date('M j, Y H:i', strtotime($selected_device['date_assigned'])); ?></p>
                        <p><strong>Duration:</strong> 
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
                        <h4>Verification Required</h4>
                        <p class="warning-text">
                            Please confirm that the device has been physically returned and is in good working condition before verifying.
                        </p>
                        
                        <form method="POST">
                            <input type="hidden" name="action" value="verify_return">
                            <input type="hidden" name="device_id" value="<?php echo $selected_device['dev_id']; ?>">
                            <input type="hidden" name="log_id" value="<?php echo $selected_device['log_id']; ?>">
                            
                            <div class="actions">
                                <button type="submit" class="btn btn-success" onclick="return confirm('Are you sure you want to verify this device return?')">
                                    ✅ Confirm Return Verification
                                </button>
                                <a href="verify_return.php" class="btn btn-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px; color: #666;">
                        <p>Select a device from the list to verify its return.</p>
                    </div>
                <?php endif; ?>
                
                <?php if (empty($pending_returns)): ?>
                    <div style="text-align: center; padding: 40px; color: #666; margin-top: 20px;">
                        <p>No devices pending return verification.</p>
                        <a href="assign_device.php" class="btn btn-primary">Assign Devices</a>
                    </div>
                <?php endif; ?>
            </div>

            <div class="history-container">
                <h2>Return History</h2>
                
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
                
                <h3 style="margin: 20px 0 10px 0; color: #333;">Pending Verification</h3>
                <div class="pending-list">
                    <?php if (empty($pending_returns)): ?>
                        <p style="text-align: center; color: #666; padding: 20px;">No pending returns.</p>
                    <?php else: ?>
                        <?php foreach ($pending_returns as $return): ?>
                            <div class="return-item">
                                <div class="return-info">
                                    <h4><?php echo htmlspecialchars($return['dev_serial']); ?></h4>
                                    <p><strong>From:</strong> <?php echo htmlspecialchars($return['resp_name']); ?></p>
                                    <p><strong>Email:</strong> <?php echo htmlspecialchars($return['resp_email']); ?></p>
                                    <p><strong>Assigned:</strong> <?php echo date('M j, Y H:i', strtotime($return['date_assigned'])); ?></p>
                                    <p><strong>Duration:</strong> 
                                        <?php 
                                        $assigned = new DateTime($return['date_assigned']);
                                        $now = new DateTime();
                                        $interval = $assigned->diff($now);
                                        echo $interval->days . ' days';
                                        ?>
                                    </p>
                                </div>
                                <div class="actions">
                                    <a href="?device_id=<?php echo $return['dev_id']; ?>" class="btn btn-warning">Verify Return</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <h3 style="margin: 20px 0 10px 0; color: #333;">Recently Verified</h3>
                <div class="verified-list">
                    <?php if (empty($verified_returns)): ?>
                        <p style="text-align: center; color: #666; padding: 20px;">No verified returns yet.</p>
                    <?php else: ?>
                        <?php foreach ($verified_returns as $return): ?>
                            <div class="return-item">
                                <div class="return-info">
                                    <h4><?php echo htmlspecialchars($return['dev_serial']); ?> <span class="verified-badge">VERIFIED</span></h4>
                                    <p><strong>From:</strong> <?php echo htmlspecialchars($return['resp_name']); ?></p>
                                    <p><strong>Returned:</strong> <?php echo date('M j, Y H:i', strtotime($return['date_returned'])); ?></p>
                                    <p><strong>Duration:</strong> 
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
    </div>
</body>
</html>
