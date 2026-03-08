<?php
session_start();
require_once '../../../database/connection.php';

// Check if admin user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../../login.html');
    exit();
}

$conn = getDBConnection();

// Get system-wide statistics
$total_users = 0;
$total_devices = 0;
$total_incidents = 0;
$total_activities = 0;

// Count users from all tables
$result = $conn->query("SELECT COUNT(*) as count FROM admin");
if ($result) $total_users += $result->fetch_assoc()['count'];

$result = $conn->query("SELECT COUNT(*) as count FROM management");
if ($result) $total_users += $result->fetch_assoc()['count'];

$result = $conn->query("SELECT COUNT(*) as count FROM responder");
if ($result) $total_users += $result->fetch_assoc()['count'];

$result = $conn->query("SELECT COUNT(*) as count FROM rescuer");
if ($result) $total_users += $result->fetch_assoc()['count'];

// Count devices
$result = $conn->query("SELECT COUNT(*) as count FROM device");
if ($result) $total_devices = $result->fetch_assoc()['count'];

// Count incidents
$result = $conn->query("SELECT COUNT(*) as count FROM incident");
if ($result) $total_incidents = $result->fetch_assoc()['count'];

// Count activities
$result = $conn->query("SELECT COUNT(*) as count FROM activity_log");
if ($result) $total_activities = $result->fetch_assoc()['count'];

// Get recent system activities
$recent_activities = [];
$result = $conn->query("SELECT * FROM activity_log ORDER BY created_at DESC LIMIT 10");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $recent_activities[] = $row;
    }
}

// Get system health metrics
$database_status = $conn->ping() ? 'Connected' : 'Disconnected';
$system_uptime = 'Running'; // Could be enhanced with actual uptime tracking
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - VitalWear</title>
    <link rel="stylesheet" href="../../../assets/css/styles.css">
    <style>
        .admin-header {
            background: #dc3545;
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
        .btn-danger { background: #dc3545; color: white; }
        .btn-warning { background: #ffc107; color: black; }
        .btn-success { background: #28a745; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        
        .btn:hover { opacity: 0.9; }
        
        .admin-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .admin-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid #dc3545;
            text-align: center;
        }
        
        .admin-number {
            font-size: 36px;
            font-weight: bold;
            color: #dc3545;
            margin-bottom: 10px;
        }
        
        .admin-label {
            color: #666;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .functions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }
        
        .function-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        
        .function-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        
        .function-icon {
            font-size: 48px;
            margin-bottom: 15px;
            text-align: center;
        }
        
        .function-title {
            font-size: 18px;
            font-weight: bold;
            color: #333;
            margin-bottom: 10px;
            text-align: center;
        }
        
        .function-description {
            color: #666;
            margin-bottom: 15px;
            text-align: center;
            font-size: 14px;
        }
        
        .system-health {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .health-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        
        .health-item:last-child {
            border-bottom: none;
        }
        
        .status-good { color: #28a745; font-weight: bold; }
        .status-warning { color: #ffc107; font-weight: bold; }
        .status-danger { color: #dc3545; font-weight: bold; }
        
        .recent-activities {
            background: white;
            padding: 20px;
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
        
        .role-badge {
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .role-admin { background: #dc3545; color: white; }
        .role-management { background: #007bff; color: white; }
        .role-responder { background: #28a745; color: white; }
        .role-rescuer { background: #ffc107; color: black; }
    </style>
</head>
<body>
    <div class="container">
        <header class="admin-header">
            <div>
                <h1 style="margin: 0;">🔐 Admin Dashboard</h1>
                <p style="margin: 5px 0 0 0; opacity: 0.9;">System Administration & Oversight</p>
            </div>
            <div>
                <div style="color: white; font-size: 14px; margin-bottom: 10px;">
                    Admin: <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                </div>
                <a href="../../../api/auth/logout.php" class="btn btn-secondary">Logout</a>
            </div>
        </header>

        <div class="admin-grid">
            <div class="admin-card">
                <div class="admin-number"><?php echo $total_users; ?></div>
                <div class="admin-label">Total Users</div>
            </div>
            
            <div class="admin-card">
                <div class="admin-number"><?php echo $total_devices; ?></div>
                <div class="admin-label">Total Devices</div>
            </div>
            
            <div class="admin-card">
                <div class="admin-number"><?php echo $total_incidents; ?></div>
                <div class="admin-label">Total Incidents</div>
            </div>
            
            <div class="admin-card">
                <div class="admin-number"><?php echo $total_activities; ?></div>
                <div class="admin-label">System Activities</div>
            </div>
        </div>

        <div class="functions-grid">
            <div class="function-card">
                <div class="function-icon">👥</div>
                <h3 class="function-title">User Management</h3>
                <p class="function-description">Manage all system users across all roles</p>
                <div style="text-align: center;">
                    <a href="users.php" class="btn btn-danger">Manage Users</a>
                </div>
            </div>
            
            <div class="function-card">
                <div class="function-icon">📋</div>
                <h3 class="function-title">Audit Logs</h3>
                <p class="function-description">View complete system audit trail</p>
                <div style="text-align: center;">
                    <a href="audit_logs.php" class="btn btn-danger">View Logs</a>
                </div>
            </div>
            
            <div class="function-card">
                <div class="function-icon">📊</div>
                <h3 class="function-title">System Reports</h3>
                <p class="function-description">Generate comprehensive system reports</p>
                <div style="text-align: center;">
                    <a href="reports.php" class="btn btn-danger">Generate Reports</a>
                </div>
            </div>
            
            <div class="function-card">
                <div class="function-icon">📦</div>
                <h3 class="function-title">Device Oversight</h3>
                <p class="function-description">Monitor all device assignments and status</p>
                <div style="text-align: center;">
                    <a href="devices.php" class="btn btn-danger">View Devices</a>
                </div>
            </div>
            
            <div class="function-card">
                <div class="function-icon">⚙️</div>
                <h3 class="function-title">System Settings</h3>
                <p class="function-description">Configure system-wide settings</p>
                <div style="text-align: center;">
                    <a href="#" class="btn btn-secondary">Coming Soon</a>
                </div>
            </div>
            
            <div class="function-card">
                <div class="function-icon">🔧</div>
                <h3 class="function-title">System Maintenance</h3>
                <p class="function-description">Perform system maintenance tasks</p>
                <div style="text-align: center;">
                    <a href="#" class="btn btn-warning">Maintenance</a>
                </div>
            </div>
        </div>

        <div class="system-health">
            <h3 style="margin: 0 0 20px 0;">🏥 System Health</h3>
            <div class="health-item">
                <span>Database Connection</span>
                <span class="status-good"><?php echo $database_status; ?></span>
            </div>
            <div class="health-item">
                <span>System Status</span>
                <span class="status-good"><?php echo $system_uptime; ?></span>
            </div>
            <div class="health-item">
                <span>Last Backup</span>
                <span class="status-warning">Not Available</span>
            </div>
            <div class="health-item">
                <span>Active Sessions</span>
                <span class="status-good">Normal</span>
            </div>
        </div>

        <?php if (!empty($recent_activities)): ?>
        <div class="recent-activities">
            <h3 style="margin: 0 0 20px 0;">📝 Recent System Activities</h3>
            <?php foreach ($recent_activities as $activity): ?>
                <div class="activity-item">
                    <div>
                        <strong><?php echo htmlspecialchars($activity['user_name'] ?? 'System'); ?></strong>
                        <span class="role-badge role-<?php echo $activity['user_role']; ?>">
                            <?php echo $activity['user_role']; ?>
                        </span>
                        - <?php echo htmlspecialchars($activity['description'] ?? 'No description'); ?>
                    </div>
                    <div style="color: #999; font-size: 12px;">
                        <?php echo date('M j, H:i', strtotime($activity['created_at'])); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>