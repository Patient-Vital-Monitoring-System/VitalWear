<?php
session_start();
require_once '../../database/connection.php';

// Check if management user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'management') {
    header('Location: ../../login.html');
    exit();
}

$conn = getDBConnection();

// Get dashboard statistics
$total_devices = 0;
$available_devices = 0;
$assigned_devices = 0;
$devices_not_returned = 0;
$active_incidents = 0;
$completed_incidents = 0;

// Total devices
$result = $conn->query("SELECT COUNT(*) as count FROM device");
if ($result) $total_devices = $result->fetch_assoc()['count'];

// Available devices
$result = $conn->query("SELECT COUNT(*) as count FROM device WHERE dev_status = 'available'");
if ($result) $available_devices = $result->fetch_assoc()['count'];

// Assigned devices
$result = $conn->query("SELECT COUNT(*) as count FROM device WHERE dev_status = 'assigned'");
if ($result) $assigned_devices = $result->fetch_assoc()['count'];

// Devices not yet returned (assigned but not verified)
$result = $conn->query("SELECT COUNT(*) as count FROM device_log WHERE date_returned IS NULL");
if ($result) $devices_not_returned = $result->fetch_assoc()['count'];

// Active incidents
$result = $conn->query("SELECT COUNT(*) as count FROM incident WHERE status IN ('ongoing', 'transferred')");
if ($result) $active_incidents = $result->fetch_assoc()['count'];

// Completed incidents
$result = $conn->query("SELECT COUNT(*) as count FROM incident WHERE status = 'completed'");
if ($result) $completed_incidents = $result->fetch_assoc()['count'];

// Get recent activities
$recent_activities = [];
$result = $conn->query("SELECT * FROM activity_log WHERE user_role = 'management' OR user_role = 'responder' OR user_role = 'rescuer' ORDER BY created_at DESC LIMIT 10");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $recent_activities[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Management Dashboard - VitalWear</title>
    <link rel="stylesheet" href="../../../assets/css/styles.css">
    <style>
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
        }
        
        .stat-card h3 {
            margin: 0 0 10px 0;
            color: #333;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .stat-number {
            font-size: 36px;
            font-weight: bold;
            color: #007bff;
            margin: 0;
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
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-time {
            color: #666;
            font-size: 12px;
        }
        
        .header-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .user-info {
            color: white;
            font-size: 14px;
        }
        
        .logout-btn {
            background: #dc3545;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            transition: background-color 0.2s;
        }
        
        .logout-btn:hover {
            background: #c82333;
        }
    </style>
</head>
<body>
    <div class="container">
        <header style="background: #007bff; color: white; padding: 20px; border-radius: 10px; margin-bottom: 30px;">
            <div class="header-actions">
                <div>
                    <h1 style="margin: 0;">🏠 Management Dashboard</h1>
                    <p style="margin: 5px 0 0 0; opacity: 0.9;">VitalWear Device Management System</p>
                </div>
                <div style="text-align: right;">
                    <div class="user-info">Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></div>
                    <a href="../../../api/auth/logout.php" class="logout-btn">Logout</a>
                </div>
            </div>
        </header>

        <section class="dashboard-grid">
            <div class="stat-card">
                <h3>Total Devices</h3>
                <p class="stat-number"><?php echo $total_devices; ?></p>
            </div>
            
            <div class="stat-card">
                <h3>Available Devices</h3>
                <p class="stat-number"><?php echo $available_devices; ?></p>
            </div>
            
            <div class="stat-card">
                <h3>Assigned Devices</h3>
                <p class="stat-number"><?php echo $assigned_devices; ?></p>
            </div>
            
            <div class="stat-card">
                <h3>Devices Not Yet Returned</h3>
                <p class="stat-number"><?php echo $devices_not_returned; ?></p>
            </div>
            
            <div class="stat-card">
                <h3>Active Incidents</h3>
                <p class="stat-number"><?php echo $active_incidents; ?></p>
            </div>
            
            <div class="stat-card">
                <h3>Completed Incidents</h3>
                <p class="stat-number"><?php echo $completed_incidents; ?></p>
            </div>
        </section>

        <section class="nav-grid">
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
        </section>

        <?php if (!empty($recent_activities)): ?>
        <section class="recent-activities">
            <h3>Recent Activities</h3>
            <?php foreach ($recent_activities as $activity): ?>
                <div class="activity-item">
                    <strong><?php echo htmlspecialchars($activity['user_name'] ?? 'Unknown'); ?></strong>
                    (<?php echo htmlspecialchars($activity['user_role']); ?>) - 
                    <?php echo htmlspecialchars($activity['description']); ?>
                    <div class="activity-time"><?php echo date('M j, Y H:i', strtotime($activity['created_at'])); ?></div>
                </div>
            <?php endforeach; ?>
        </section>
        <?php endif; ?>
    </div>
</body>
</html>