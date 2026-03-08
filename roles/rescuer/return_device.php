<?php
require_once 'session_check.php';
require_once '../../database/connection.php';

$rescuer_id = $_SESSION['user_id'];
$conn = getDBConnection();

// Get rescuer info
$rescuer_query = "SELECT resc_name FROM rescuer WHERE resc_id = ?";
$stmt = $conn->prepare($rescuer_query);
$stmt->bind_param("i", $rescuer_id);
$stmt->execute();
$rescuer = $stmt->get_result()->fetch_assoc();

// Get currently assigned device
$device_query = "SELECT dl.log_id, dl.dev_id, d.dev_serial, dl.date_assigned, m.mgmt_name, m.mgmt_email
                 FROM device_log dl
                 JOIN device d ON dl.dev_id = d.dev_id
                 JOIN management m ON dl.mgmt_id = m.mgmt_id
                 WHERE dl.resp_id = ? AND dl.date_returned IS NULL
                 ORDER BY dl.date_assigned DESC
                 LIMIT 1";
$stmt = $conn->prepare($device_query);
$stmt->bind_param("i", $rescuer_id);
$stmt->execute();
$device_result = $stmt->get_result();

$assigned_device = null;
if ($device_result->num_rows > 0) {
    $assigned_device = $device_result->fetch_assoc();
}

// Handle device return request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['return_device'])) {
    if ($assigned_device) {
        $log_id = $assigned_device['log_id'];
        
        // Update device log with return date
        $update_query = "UPDATE device_log SET date_returned = NOW() WHERE log_id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("i", $log_id);
        
        if ($stmt->execute()) {
            // Update device status to available
            $device_update = "UPDATE device SET dev_status = 'available' WHERE dev_id = ?";
            $stmt = $conn->prepare($device_update);
            $stmt->bind_param("i", $assigned_device['dev_id']);
            $stmt->execute();
            
            // Log activity
            $activity_query = "INSERT INTO activity_log (user_name, user_role, action_type, module, description) 
                               VALUES (?, 'rescuer', 'return_device', 'device_management', ?)";
            $rescuer_name = $_SESSION['user_name'];
            $description = "Returned device #{$assigned_device['dev_serial']} (pending verification)";
            $stmt = $conn->prepare($activity_query);
            $stmt->bind_param("ss", $rescuer_name, $description);
            $stmt->execute();
            
            // Refresh device data
            $assigned_device = null;
        } else {
            $error = "Error returning device. Please try again.";
        }
    }
}

// Get device return history
$history_query = "SELECT dl.log_id, dl.dev_id, d.dev_serial, dl.date_assigned, dl.date_returned, 
                  dl.verified_return, m.mgmt_name
                  FROM device_log dl
                  JOIN device d ON dl.dev_id = d.dev_id
                  JOIN management m ON dl.mgmt_id = m.mgmt_id
                  WHERE dl.resp_id = ?
                  ORDER BY dl.date_assigned DESC";
$stmt = $conn->prepare($history_query);
$stmt->bind_param("i", $rescuer_id);
$stmt->execute();
$device_history = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Device Return - VitalWear</title>
    <link rel="stylesheet" href="../../../assets/css/styles.css">
    <style>
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: linear-gradient(135deg, #f56565 0%, #e53e3e 100%);
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            position: relative;
        }
        .back-btn {
            position: absolute;
            top: 20px;
            left: 20px;
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 10px 20px;
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: 5px;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        .back-btn:hover {
            background: rgba(255,255,255,0.3);
        }
        .device-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 30px;
            margin-bottom: 30px;
        }
        .device-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        .info-item {
            padding: 15px;
            background: #f7fafc;
            border-radius: 8px;
        }
        .info-label {
            font-weight: 600;
            color: #4a5568;
            margin-bottom: 5px;
        }
        .info-value {
            color: #2d3748;
            font-size: 1.1em;
        }
        .return-form {
            background: #fff5f5;
            border: 2px solid #feb2b2;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
        }
        .warning-text {
            color: #c53030;
            font-weight: 600;
            margin-bottom: 15px;
        }
        .btn-danger {
            background: #f56565;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        .btn-danger:hover {
            background: #e53e3e;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(245, 101, 101, 0.3);
        }
        .no-device {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .no-device h3 {
            color: #48bb78;
            margin-bottom: 10px;
        }
        .history-section {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .history-header {
            background: #f7fafc;
            padding: 20px;
            border-bottom: 1px solid #e2e8f0;
        }
        .history-body {
            padding: 20px;
        }
        .history-table {
            width: 100%;
            border-collapse: collapse;
        }
        .history-table th,
        .history-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        .history-table th {
            background: #f7fafc;
            font-weight: 600;
            color: #4a5568;
        }
        .history-table tr:hover {
            background: #f7fafc;
        }
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-returned {
            background: #c6f6d5;
            color: #22543d;
        }
        .status-pending {
            background: #feebc8;
            color: #7c2d12;
        }
        .status-verified {
            background: #bee3f8;
            color: #2c5282;
        }
        .error-message {
            background: #fed7d7;
            color: #742a2a;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .success-message {
            background: #c6f6d5;
            color: #22543d;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .device-serial {
            font-family: monospace;
            background: #edf2f7;
            padding: 2px 6px;
            border-radius: 3px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>
            <h1>🔁 Device Return</h1>
            <p>Return assigned device and complete the handoff process</p>
        </div>

        <?php if (isset($error)): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_POST['return_device']) && !isset($error)): ?>
            <div class="success-message">
                ✅ Device return request submitted successfully! The device is now pending verification by management.
            </div>
        <?php endif; ?>

        <?php if ($assigned_device): ?>
            <div class="device-card">
                <h2>📱 Currently Assigned Device</h2>
                <div class="device-info">
                    <div class="info-item">
                        <div class="info-label">Device Serial</div>
                        <div class="info-value device-serial"><?php echo htmlspecialchars($assigned_device['dev_serial']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Assigned By</div>
                        <div class="info-value"><?php echo htmlspecialchars($assigned_device['mgmt_name']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Assigned Date</div>
                        <div class="info-value"><?php echo date('M j, Y H:i', strtotime($assigned_device['date_assigned'])); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Duration</div>
                        <div class="info-value">
                            <?php 
                            $duration = time() - strtotime($assigned_device['date_assigned']);
                            $days = floor($duration / 86400);
                            $hours = floor(($duration % 86400) / 3600);
                            echo $days > 0 ? "{$days}d {$hours}h" : "{$hours}h";
                            ?>
                        </div>
                    </div>
                </div>

                <div class="return-form">
                    <div class="warning-text">
                        ⚠️ You are about to return this device. After submission, management will need to verify the device return.
                    </div>
                    <form method="POST" onsubmit="return confirm('Are you sure you want to return this device? You will need management verification to complete the process.');">
                        <input type="hidden" name="return_device" value="1">
                        <button type="submit" class="btn-danger">🔁 Return Device</button>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <div class="no-device">
                <h3>✅ No Device Currently Assigned</h3>
                <p>You don't have any devices assigned to you at the moment.</p>
                <p>Contact management if you need to be assigned a device for incident response.</p>
            </div>
        <?php endif; ?>

        <div class="history-section">
            <div class="history-header">
                <h2>📋 Device Assignment History</h2>
            </div>
            <div class="history-body">
                <?php if ($device_history->num_rows > 0): ?>
                    <table class="history-table">
                        <thead>
                            <tr>
                                <th>Device Serial</th>
                                <th>Assigned By</th>
                                <th>Assigned Date</th>
                                <th>Returned Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($device = $device_history->fetch_assoc()): ?>
                                <tr>
                                    <td class="device-serial"><?php echo htmlspecialchars($device['dev_serial']); ?></td>
                                    <td><?php echo htmlspecialchars($device['mgmt_name']); ?></td>
                                    <td><?php echo date('M j, Y H:i', strtotime($device['date_assigned'])); ?></td>
                                    <td>
                                        <?php echo $device['date_returned'] ? date('M j, Y H:i', strtotime($device['date_returned'])) : '-'; ?>
                                    </td>
                                    <td>
                                        <?php if ($device['date_returned'] === null): ?>
                                            <span class="status-badge status-returned">Currently Assigned</span>
                                        <?php elseif ($device['verified_return'] == 0): ?>
                                            <span class="status-badge status-pending">Pending Verification</span>
                                        <?php else: ?>
                                            <span class="status-badge status-verified">Verified & Complete</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No device assignment history found.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
