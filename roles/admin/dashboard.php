<?php
session_start();
require_once '../../database/connection.php';

// Check if admin user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /VitalWear-1/login.html');
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - VitalWear</title>
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
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
                    <a href="dashboard.php" class="nav-item active">
                        🏠 Dashboard
                    </a>
                </div>
                
                <div class="nav-group">
                    <div class="nav-group-title">User Management</div>
                    <div class="nav-group-items">
                        <a href="users.php" class="nav-item">
                            👥 Staff Directory
                        </a>
                        <a href="users/view_management.php" class="nav-item">
                            👨‍💼 Management
                        </a>
                        <a href="users/view_responders.php" class="nav-item">
                            🚑 Responders
                        </a>
                        <a href="users/view_rescuers.php" class="nav-item">
                            🆘 Rescuers
                        </a>
                        <a href="users/view_admins.php" class="nav-item">
                            👨‍💻 Admins
                        </a>
                    </div>
                </div>
                
                <div class="nav-group">
                    <div class="nav-group-title">Reports</div>
                    <div class="nav-group-items">
                        <a href="system_reports.php" class="nav-item">
                            📊 System Reports
                        </a>
                        <a href="vitals_analytics.php" class="nav-item">
                            ❤️ Vital Analytics
                        </a>
                        <a href="audit_log.php" class="nav-item">
                            📋 Activity Log
                        </a>
                    </div>
                </div>
                
                <div class="nav-group">
                    <div class="nav-group-title">Monitoring</div>
                    <div class="nav-group-items">
                        <a href="device_incidents.php" class="nav-item">
                            📦 Device Overview
                        </a>
                        <a href="vitals.php" class="nav-item">
                            👤 User Activity
                        </a>
                    </div>
                </div>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="admin-main">
            <!-- Top Navigation -->
            <header class="navbar">
                <div>
                    <h1 class="navbar-brand">Admin Dashboard</h1>
                </div>
                <div class="navbar-actions">
                    <span class="text-muted">Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                    <a href="/VitalWear-1/api/auth/logout.php" class="btn btn-secondary">Logout</a>
                </div>
            </header>

            <!-- Page Content -->
            <div class="content">
                <div class="content-header">
                    <h1 class="content-title">System Overview</h1>
                    <p class="content-subtitle">Monitor and manage the VitalWear system</p>
                </div>

                <!-- Metrics Grid -->
                <div class="metrics-grid">
                    <div class="metric-card">
                        <div class="metric-icon">👥</div>
                        <div class="metric-value"><?php echo number_format($total_users); ?></div>
                        <div class="metric-label">Total Users</div>
                    </div>
                    
                    <div class="metric-card">
                        <div class="metric-icon">📦</div>
                        <div class="metric-value"><?php echo number_format($total_devices); ?></div>
                        <div class="metric-label">Devices</div>
                    </div>
                    
                    <div class="metric-card">
                        <div class="metric-icon">🚨</div>
                        <div class="metric-value"><?php echo number_format($total_incidents); ?></div>
                        <div class="metric-label">Incidents</div>
                    </div>
                    
                    <div class="metric-card">
                        <div class="metric-icon">📊</div>
                        <div class="metric-value"><?php echo number_format($total_activities); ?></div>
                        <div class="metric-label">Activities</div>
                    </div>
                </div>

                <!-- Recent Activities -->
                <div class="card">
                    <div class="card-header">
                        Recent System Activities
                    </div>
                    <div class="card-body">
                        <?php if (!empty($recent_activities)): ?>
                            <div class="table">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>User</th>
                                            <th>Role</th>
                                            <th>Action</th>
                                            <th>Time</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_activities as $activity): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($activity['user_name'] ?? 'System'); ?></td>
                                            <td>
                                                <span class="badge badge-<?php echo $activity['user_role']; ?>">
                                                    <?php echo htmlspecialchars(ucfirst($activity['user_role'])); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($activity['description']); ?></td>
                                            <td>
                                                <span class="text-muted">
                                                    <?php echo date('M j, H:i', strtotime($activity['created_at'])); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center text-muted">
                                <p>No recent activities found.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="card" style="margin-top: 2rem;">
                    <div class="card-header">
                        Quick Actions
                    </div>
                    <div class="card-body">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                            <a href="users.php" class="btn btn-primary">
                                👥 Manage Users
                            </a>
                            <a href="device_incidents.php" class="btn btn-secondary">
                                📦 View Devices
                            </a>
                            <a href="system_reports.php" class="btn btn-secondary">
                                📊 Generate Reports
                            </a>
                            <a href="audit_log.php" class="btn btn-secondary">
                                📋 View Activity Log
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
