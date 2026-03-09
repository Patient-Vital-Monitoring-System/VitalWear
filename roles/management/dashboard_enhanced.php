<?php
session_start();
require_once '../../database/connection.php';

// Check if management user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'management') {
    header('Location: ../../login.html');
    exit();
}

$conn = getDBConnection();

// Get management-specific statistics
$total_devices = 0;
$available_devices = 0;
$assigned_devices = 0;
$devices_not_returned = 0;
$active_incidents = 0;
$completed_incidents = 0;
$pending_returns = 0;

// Device statistics
$result = $conn->query("SELECT COUNT(*) as count FROM device");
if ($result) $total_devices = $result->fetch_assoc()['count'];

$result = $conn->query("SELECT COUNT(*) as count FROM device WHERE dev_status = 'available'");
if ($result) $available_devices = $result->fetch_assoc()['count'];

$result = $conn->query("SELECT COUNT(*) as count FROM device WHERE dev_status = 'assigned'");
if ($result) $assigned_devices = $result->fetch_assoc()['count'];

$result = $conn->query("SELECT COUNT(*) as count FROM device_log WHERE date_returned IS NULL");
if ($result) $devices_not_returned = $result->fetch_assoc()['count'];

// Incident statistics
$result = $conn->query("SELECT COUNT(*) as count FROM incident WHERE status IN ('ongoing', 'transferred')");
if ($result) $active_incidents = $result->fetch_assoc()['count'];

$result = $conn->query("SELECT COUNT(*) as count FROM incident WHERE status = 'completed'");
if ($result) $completed_incidents = $result->fetch_assoc()['count'];

// Pending returns
$result = $conn->query("SELECT COUNT(*) as count FROM device_log WHERE date_returned IS NOT NULL AND verified_return = 0");
if ($result) $pending_returns = $result->fetch_assoc()['count'];

// Get recent activities
$recent_activities = [];
$result = $conn->query("SELECT * FROM activity_log WHERE user_role IN ('management', 'responder', 'rescuer') ORDER BY created_at DESC LIMIT 10");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $recent_activities[] = $row;
    }
}

// Get device utilization
$device_utilization = 0;
if ($total_devices > 0) {
    $device_utilization = round(($assigned_devices / $total_devices) * 100, 1);
}

// Get recent assignments
$recent_assignments = [];
$result = $conn->query("
    SELECT dl.*, d.dev_serial, r.resp_name, m.mgmt_name
    FROM device_log dl
    JOIN device d ON dl.dev_id = d.dev_id
    JOIN responder r ON dl.resp_id = r.resp_id
    JOIN management m ON dl.mgmt_id = m.mgmt_id
    ORDER BY dl.date_assigned DESC
    LIMIT 5
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $recent_assignments[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Management Dashboard - VitalWear</title>
        <style>
        .management-header {
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
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid #007bff;
            text-align: center;
        }
        
        .stat-card.warning {
            border-left-color: #ffc107;
        }
        
        .stat-card.danger {
            border-left-color: #dc3545;
        }
        
        .stat-card.success {
            border-left-color: #28a745;
        }
        
        .stat-number {
            font-size: 36px;
            font-weight: bold;
            color: #007bff;
            margin-bottom: 10px;
        }
        
        .stat-number.warning { color: #ffc107; }
        .stat-number.danger { color: #dc3545; }
        .stat-number.success { color: #28a745; }
        
        .stat-label {
            color: #666;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .nav-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .nav-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        
        .nav-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }
        
        .nav-card h4 {
            margin: 0 0 15px 0;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .nav-card ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .nav-card li {
            margin: 8px 0;
        }
        
        .nav-card a {
            color: #007bff;
            text-decoration: none;
            padding: 8px 12px;
            border-radius: 5px;
            display: block;
            transition: background-color 0.2s;
        }
        
        .nav-card a:hover {
            background-color: #f8f9fa;
        }
        
        .recent-activities {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .activity-item {
            padding: 10px 0;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-time {
            color: #666;
            font-size: 12px;
        }
        
        .recent-assignments {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .assignment-item {
            padding: 15px;
            border: 1px solid #eee;
            border-radius: 5px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .assignment-info h5 {
            margin: 0 0 5px 0;
            color: #333;
        }
        
        .assignment-info p {
            margin: 0;
            font-size: 12px;
            color: #666;
        }
        
        .utilization-bar {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .progress-bar {
            width: 100%;
            height: 20px;
            background: #e9ecef;
            border-radius: 10px;
            overflow: hidden;
            margin: 10px 0;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #28a745, #ffc107, #dc3545);
            transition: width 0.3s ease;
        }
        
        .role-badge {
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .role-management { background: #007bff; color: white; }
        .role-responder { background: #28a745; color: white; }
        .role-rescuer { background: #ffc107; color: black; }
        
        .priority-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .priority-high { background: #dc3545; color: white; }
        .priority-medium { background: #ffc107; color: black; }
        .priority-low { background: #28a745; color: white; }
    </style>
</head>
<body>
    <div class="container">
        <header class="management-header">
            <div>
                <h1 style="margin: 0;">🏠 Management Dashboard</h1>
                <p style="margin: 5px 0 0 0; opacity: 0.9;">Device & Resource Management</p>
            </div>
            <div>
                <div style="color: white; font-size: 14px; margin-bottom: 10px;">
                    Manager: <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                </div>
                <a href="../../../api/auth/logout.php" class="btn btn-secondary">Logout</a>
            </div>
        </header>

        <div class="utilization-bar">
            <h3 style="margin: 0 0 10px 0;">📊 Device Utilization</h3>
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?php echo $device_utilization; ?>%;"></div>
            </div>
            <p style="margin: 5px 0 0 0; color: #666;">
                <?php echo $assigned_devices; ?> of <?php echo $total_devices; ?> devices assigned (<?php echo $device_utilization; ?>%)
            </p>
        </div>

        <div class="dashboard-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_devices; ?></div>
                <div class="stat-label">Total Devices</div>
            </div>
            
            <div class="stat-card success">
                <div class="stat-number success"><?php echo $available_devices; ?></div>
                <div class="stat-label">Available Devices</div>
            </div>
            
            <div class="stat-card warning">
                <div class="stat-number warning"><?php echo $assigned_devices; ?></div>
                <div class="stat-label">Assigned Devices</div>
            </div>
            
            <div class="stat-card danger">
                <div class="stat-number danger"><?php echo $devices_not_returned; ?></div>
                <div class="stat-label">Devices Not Returned</div>
            </div>
            
            <div class="stat-card warning">
                <div class="stat-number warning"><?php echo $active_incidents; ?></div>
                <div class="stat-label">Active Incidents</div>
            </div>
            
            <div class="stat-card success">
                <div class="stat-number success"><?php echo $completed_incidents; ?></div>
                <div class="stat-label">Completed Incidents</div>
            </div>
            
            <?php if ($pending_returns > 0): ?>
            <div class="stat-card danger">
                <div class="stat-number danger"><?php echo $pending_returns; ?></div>
                <div class="stat-label">Pending Returns</div>
            </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($recent_assignments)): ?>
        <div class="recent-assignments">
            <h3 style="margin: 0 0 20px 0;">🔄 Recent Device Assignments</h3>
            <?php foreach ($recent_assignments as $assignment): ?>
                <div class="assignment-item">
                    <div class="assignment-info">
                        <h5><?php echo htmlspecialchars($assignment['dev_serial']); ?></h5>
                        <p>
                            Assigned to: <?php echo htmlspecialchars($assignment['resp_name']); ?> 
                            by <?php echo htmlspecialchars($assignment['mgmt_name']); ?>
                        </p>
                        <p><?php echo date('M j, Y H:i', strtotime($assignment['date_assigned'])); ?></p>
                    </div>
                    <div>
                        <?php if ($assignment['date_returned']): ?>
                            <span class="priority-badge priority-low">Returned</span>
                        <?php else: ?>
                            <span class="priority-badge priority-high">Active</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="nav-grid">
            <div class="nav-card">
                <h4>👥 User Management</h4>
                <ul>
                    <li><a href="manage_responders.php">➤ Manage Responders</a></li>
                    <li><a href="manage_rescuers.php">➤ Manage Rescuers</a></li>
                </ul>
            </div>
            
            <div class="nav-card">
                <h4>📦 Device Management</h4>
                <ul>
                    <li><a href="register_device.php">➤ Register Device</a></li>
                    <li><a href="device_list.php">➤ Device List</a></li>
                    <li><a href="assign_device.php">🔁 Assign Device</a></li>
                    <li><a href="verify_return.php">✅ Verify Device Return</a></li>
                </ul>
            </div>
            
            <div class="nav-card">
                <h4>📊 Reports</h4>
                <ul>
                    <li><a href="reports/device_assignment_history.php">Device Assignment History</a></li>
                    <li><a href="reports/device_return_history.php">Device Return History</a></li>
                    <li><a href="reports/responder_activity.php">Responder Activity Report</a></li>
                    <li><a href="reports/incident_summary.php">Incident Summary Report</a></li>
                </ul>
            </div>
        </div>

        <?php if (!empty($recent_activities)): ?>
        <div class="recent-activities">
            <h3>Recent Activities</h3>
            <?php foreach ($recent_activities as $activity): ?>
                <div class="activity-item">
                    <div>
                        <strong><?php echo htmlspecialchars($activity['user_name'] ?? 'Unknown'); ?></strong>
                        <span class="role-badge role-<?php echo $activity['user_role']; ?>">
                            <?php echo $activity['user_role']; ?>
                        </span>
                        - <?php echo htmlspecialchars($activity['description']); ?>
                    </div>
                    <div class="activity-time"><?php echo date('M j, Y H:i', strtotime($activity['created_at'])); ?></div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
