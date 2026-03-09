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
    <script src="https://kit.fontawesome.com/96e37b53f1.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* VitalWear Modern Soft UI Design System */
        :root {
            /* Primary Colors - Modern Blue Palette */
            --primary-50: #E8F4FD;
            --primary-100: #D1E9FB;
            --primary-200: #A9D9F5;
            --primary-300: #7BC4F0;
            --primary-400: #4DAEEA;
            --primary-500: #2E96D5;
            --primary-600: #1E7AB8;
            --primary-700: #1A5F9A;
            --primary-800: #1A4975;
            --primary-900: #1A3A5C;
            
            /* Neutral Colors */
            --gray-50: #F9FAFB;
            --gray-100: #F3F4F6;
            --gray-200: #E5E7EB;
            --gray-300: #D1D5DB;
            --gray-400: #9CA3AF;
            --gray-500: #6B7280;
            --gray-600: #4B5563;
            --gray-700: #374151;
            --gray-800: #1F2937;
            --gray-900: #111827;
            
            /* Semantic Colors */
            --success: #10B981;
            --success-light: #D1FAE5;
            --warning: #F59E0B;
            --warning-light: #FEF3C7;
            --error: #EF4444;
            --error-light: #FEE2E2;
            --info: #3B82F6;
            --info-light: #DBEAFE;
            
            /* Core Design Tokens */
            --primary: var(--primary-600);
            --primary-light: var(--primary-100);
            --background: var(--gray-50);
            --surface: var(--pure-white);
            --text-primary: var(--gray-900);
            --text-secondary: var(--gray-600);
            --text-tertiary: var(--gray-500);
            --border: var(--gray-200);
            --border-hover: var(--gray-300);
            
            /* Soft UI Radius System */
            --radius-xs: 4px;
            --radius-sm: 6px;
            --radius: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
            --radius-xl: 20px;
            --radius-2xl: 24px;
            --radius-full: 9999px;
            
            /* Modern Shadow System */
            --shadow-xs: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-sm: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-md: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            --shadow-xl: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            
            /* Transitions */
            --transition-fast: 150ms cubic-bezier(0.4, 0, 0.2, 1);
            --transition: 200ms cubic-bezier(0.4, 0, 0.2, 1);
            --transition-slow: 300ms cubic-bezier(0.4, 0, 0.2, 1);
        }

        body {
            background: var(--background);
            color: var(--text-primary);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            margin: 0;
            padding: 0;
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        /* Modern Soft UI Sidebar */
        .admin-sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 280px;
            height: 100vh;
            background: var(--surface);
            border-right: 1px solid var(--border);
            box-shadow: var(--shadow-lg);
            z-index: 1000;
            overflow-y: auto;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }

        .sidebar-header {
            padding: 32px 24px 24px;
            text-align: center;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-700) 100%);
            margin: 16px;
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-md);
            position: relative;
            overflow: hidden;
        }

        .sidebar-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.1) 0%, transparent 100%);
            pointer-events: none;
        }

        .sidebar-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: white;
            margin-bottom: 4px;
            position: relative;
            z-index: 1;
        }

        .sidebar-subtitle {
            font-size: 0.875rem;
            color: rgba(255, 255, 255, 0.8);
            position: relative;
            z-index: 1;
        }

        .nav-menu {
            padding: 16px;
        }

        .nav-group {
            margin-bottom: 24px;
        }

        .nav-group-title {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-tertiary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 0 16px 8px;
            padding: 8px 0;
        }

        .nav-group-items {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .nav-item {
            color: var(--text-primary);
            padding: 12px 16px;
            border-radius: var(--radius-lg);
            transition: all var(--transition);
            border: none;
            font-weight: 500;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 12px;
            position: relative;
            overflow: hidden;
        }

        .nav-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
            transition: left var(--transition-slow);
        }

        .nav-item:hover {
            background: var(--primary-light);
            color: var(--primary);
            transform: translateX(6px);
            box-shadow: var(--shadow);
        }

        .nav-item:hover::before {
            left: 100%;
        }

        .nav-item.active {
            background: var(--primary);
            color: white;
            box-shadow: var(--shadow-md);
            transform: translateX(4px);
        }

        .nav-item.active::before {
            display: none;
        }

        /* Modern Soft UI Header */
        .admin-header {
            position: fixed;
            top: 0;
            left: 280px;
            right: 0;
            background: var(--surface);
            color: var(--text-primary);
            border-bottom: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
            padding: 20px 32px;
            font-weight: 600;
            z-index: 999;
            display: flex;
            align-items: center;
            justify-content: space-between;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }

        /* Main Container */
        .admin-main {
            max-width: 1400px;
            margin: 0 auto;
            padding: 32px;
            margin-left: 280px;
            margin-top: 80px;
        }

        /* Header Styles */
        .page-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-700) 100%);
            color: white;
            padding: 40px;
            border-radius: var(--radius-xl);
            margin-bottom: 32px;
            box-shadow: var(--shadow-lg);
        }

        .page-header h1 {
            font-size: 2rem;
            font-weight: 700;
            margin: 0 0 8px 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .page-header p {
            margin: 0;
            opacity: 0.9;
            font-size: 1rem;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: var(--surface);
            padding: 32px;
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            text-align: center;
            transition: all var(--transition);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary) 0%, var(--primary-700) 100%);
        }

        .stat-card:hover {
            box-shadow: var(--shadow-lg);
            transform: translateY(-4px);
        }

        .stat-card h3 {
            margin: 0 0 16px 0;
            color: var(--text-secondary);
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary);
            margin: 0;
        }

        /* Activity Section */
        .activity-section {
            background: var(--surface);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            overflow: hidden;
        }

        .activity-header {
            padding: 24px 32px;
            border-bottom: 1px solid var(--border);
        }

        .activity-header h2 {
            margin: 0;
            color: var(--text-primary);
            font-size: 1.25rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .activity-list {
            max-height: 400px;
            overflow-y: auto;
        }

        .activity-item {
            padding: 16px 32px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 16px;
            transition: background-color var(--transition);
        }

        .activity-item:hover {
            background: var(--primary-light);
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: var(--radius-full);
            background: var(--primary-light);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            flex-shrink: 0;
        }

        .activity-content {
            flex: 1;
        }

        .activity-content strong {
            color: var(--text-primary);
            font-weight: 600;
        }

        .activity-content p {
            margin: 4px 0 0 0;
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        .activity-time {
            color: var(--text-tertiary);
            font-size: 0.75rem;
            white-space: nowrap;
        }

        /* Logout Button */
        .logout-btn {
            background: var(--error);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: var(--radius-lg);
            font-weight: 600;
            cursor: pointer;
            transition: all var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .logout-btn:hover {
            background: #DC2626;
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
    </style>
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
                        <i class="fa fa-gauge"></i> Dashboard
                    </a>
                </div>
                
                <div class="nav-group">
                    <div class="nav-group-title">User Management</div>
                    <div class="nav-group-items">
                        <a href="users.php" class="nav-item">
                            <i class="fa fa-users"></i> Staff Directory
                        </a>
                        <a href="users/view_management.php" class="nav-item">
                            <i class="fa fa-user-tie"></i> Management
                        </a>
                        <a href="users/view_responders.php" class="nav-item">
                            <i class="fa fa-user-md"></i> Responders
                        </a>
                        <a href="users/view_rescuers.php" class="nav-item">
                            <i class="fa fa-user-shield"></i> Rescuers
                        </a>
                        <a href="users/view_admins.php" class="nav-item">
                            <i class="fa fa-user-cog"></i> Admins
                        </a>
                    </div>
                </div>
                
                <div class="nav-group">
                    <div class="nav-group-title">Reports</div>
                    <div class="nav-group-items">
                        <a href="system_reports.php" class="nav-item">
                            <i class="fa fa-chart-line"></i> System Reports
                        </a>
                        <a href="vitals_analytics.php" class="nav-item">
                            <i class="fa fa-heartbeat"></i> Vital Analytics
                        </a>
                        <a href="audit_log.php" class="nav-item">
                            <i class="fa fa-clipboard-list"></i> Activity Log
                        </a>
                    </div>
                </div>
                
                <div class="nav-group">
                    <div class="nav-group-title">Monitoring</div>
                    <div class="nav-group-items">
                        <a href="device_incidents.php" class="nav-item">
                            <i class="fa fa-box"></i> Device Overview
                        </a>
                        <a href="vitals.php" class="nav-item">
                            <i class="fa fa-user-clock"></i> User Activity
                        </a>
                    </div>
                </div>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="admin-main">
            <!-- Modern Header -->
            <header class="admin-header">
                <div>
                    <h1><i class="fa fa-cogs"></i> Admin Dashboard</h1>
                </div>
                <div>
                    <span style="color: var(--text-secondary); margin-right: 16px;">Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                    <a href="/VitalWear-1/logout.php" class="logout-btn">
                        <i class="fa fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </header>

            <!-- Page Header -->
            <div class="page-header">
                <h1><i class="fa fa-chart-line"></i> System Overview</h1>
                <p>Monitor and manage the VitalWear system</p>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Users</h3>
                    <div class="stat-number"><?php echo number_format($total_users); ?></div>
                </div>
                
                <div class="stat-card">
                    <h3>Devices</h3>
                    <div class="stat-number"><?php echo number_format($total_devices); ?></div>
                </div>
                
                <div class="stat-card">
                    <h3>Incidents</h3>
                    <div class="stat-number"><?php echo number_format($total_incidents); ?></div>
                </div>
                
                <div class="stat-card">
                    <h3>Activities</h3>
                    <div class="stat-number"><?php echo number_format($total_activities); ?></div>
                </div>
            </div>

            <!-- Activity Section -->
            <div class="activity-section">
                <div class="activity-header">
                    <h2><i class="fa fa-clock"></i> Recent System Activities</h2>
                </div>
                <div class="activity-list">
                    <?php if (!empty($recent_activities)): ?>
                        <?php foreach ($recent_activities as $activity): ?>
                            <div class="activity-item">
                                <div class="activity-icon">
                                    <?php 
                                    $icon = 'fa-cog';
                                    if ($activity['user_role'] === 'responder') $icon = 'fa-user-md';
                                    elseif ($activity['user_role'] === 'rescuer') $icon = 'fa-user-shield';
                                    elseif ($activity['user_role'] === 'management') $icon = 'fa-user-tie';
                                    elseif ($activity['user_role'] === 'admin') $icon = 'fa-user-cog';
                                    ?>
                                    <i class="fa <?php echo $icon; ?>"></i>
                                </div>
                                <div class="activity-content">
                                    <strong><?php echo htmlspecialchars($activity['user_name']); ?></strong>
                                    <p><?php echo htmlspecialchars($activity['description'] ?? 'System activity'); ?></p>
                                </div>
                                <div class="activity-time">
                                    <?php echo date('M j, H:i', strtotime($activity['created_at'])); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="activity-item">
                            <div class="activity-content">
                                <strong>No recent activities</strong>
                                <p>System activities will appear here</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
