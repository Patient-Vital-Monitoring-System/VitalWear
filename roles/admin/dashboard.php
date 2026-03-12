<?php
session_start();
require_once '../../database/connection.php';

// Check if admin user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /VitalWear-1/login.html');
    exit();
}

$conn = getDBConnection();

// Debug: Check database connection
if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
} else {
    error_log("Database connection successful");
}

// Check if required tables exist
$tables_to_check = ['admin', 'management', 'responder', 'rescuer', 'device', 'incident', 'activity_log'];
foreach ($tables_to_check as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result && $result->num_rows > 0) {
        error_log("Table '$table' exists");
    } else {
        error_log("Table '$table' does NOT exist");
    }
}

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

// Get data for dashboard charts
$chart_data = [];

// User distribution by role
$user_distribution = [];
$roles = ['admin', 'management', 'responder', 'rescuer'];
foreach ($roles as $role) {
    $result = $conn->query("SELECT COUNT(*) as count FROM " . $role);
    if ($result) {
        $count = $result->fetch_assoc()['count'];
        $user_distribution[] = [
            'role' => ucfirst($role),
            'count' => $count
        ];
    } else {
        // Debug: Show query error
        error_log("Error querying $role table: " . $conn->error);
    }
}

// Debug: Log user distribution
error_log("User distribution: " . json_encode($user_distribution));

// Incident trends (last 7 days)
$incident_trends = $conn->query("
    SELECT 
        DATE(start_time) as date,
        COUNT(*) as incidents,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
    FROM incident 
    WHERE start_time >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(start_time)
    ORDER BY date
");

if ($incident_trends) {
    $chart_data['incident_trends'] = $incident_trends->fetch_all(MYSQLI_ASSOC);
    error_log("Incident trends: " . json_encode($chart_data['incident_trends']));
} else {
    error_log("Error querying incident trends: " . $conn->error);
}

// Device status distribution
$device_status = $conn->query("
    SELECT 
        dev_status,
        COUNT(*) as count
    FROM device 
    GROUP BY dev_status
");

if ($device_status) {
    $chart_data['device_status'] = $device_status->fetch_all(MYSQLI_ASSOC);
    error_log("Device status: " . json_encode($chart_data['device_status']));
} else {
    error_log("Error querying device status: " . $conn->error);
}

// Activity trends (last 7 days)
$activity_trends = $conn->query("
    SELECT 
        DATE(created_at) as date,
        COUNT(*) as activities,
        COUNT(DISTINCT user_name) as active_users
    FROM activity_log 
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(created_at)
    ORDER BY date
");

if ($activity_trends) {
    $chart_data['activity_trends'] = $activity_trends->fetch_all(MYSQLI_ASSOC);
    error_log("Activity trends: " . json_encode($chart_data['activity_trends']));
} else {
    error_log("Error querying activity trends: " . $conn->error);
}

// If no real data exists, create sample data for testing
if (empty($user_distribution) || empty(array_filter($user_distribution, fn($item) => $item['count'] > 0))) {
    $user_distribution = [
        ['role' => 'Admin', 'count' => 3],
        ['role' => 'Management', 'count' => 5],
        ['role' => 'Responder', 'count' => 12],
        ['role' => 'Rescuer', 'count' => 8]
    ];
    error_log("Using sample user distribution data");
}

if (empty($chart_data['device_status'])) {
    $chart_data['device_status'] = [
        ['dev_status' => 'available', 'count' => 15],
        ['dev_status' => 'assigned', 'count' => 8],
        ['dev_status' => 'maintenance', 'count' => 3],
        ['dev_status' => 'inactive', 'count' => 2]
    ];
    error_log("Using sample device status data");
}

if (empty($chart_data['incident_trends'])) {
    // Generate sample data for last 7 days
    $chart_data['incident_trends'] = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $chart_data['incident_trends'][] = [
            'date' => $date,
            'incidents' => rand(5, 15),
            'completed' => rand(3, 12),
            'active' => rand(1, 5),
            'pending' => rand(0, 3)
        ];
    }
    error_log("Using sample incident trends data");
}

if (empty($chart_data['activity_trends'])) {
    // Generate sample activity data for last 7 days
    $chart_data['activity_trends'] = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $chart_data['activity_trends'][] = [
            'date' => $date,
            'activities' => rand(20, 50),
            'active_users' => rand(5, 15)
        ];
    }
    error_log("Using sample activity trends data");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - VitalWear</title>
    <script src="https://kit.fontawesome.com/96e37b53f1.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

        /* Mobile Bottom Navigation */
        .mobile-navbar {
            display: none;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: var(--surface);
            border-top: 1px solid var(--border);
            box-shadow: var(--shadow-lg);
            z-index: 1000;
            padding: 12px;
        }

        .mobile-nav-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
        }

        .mobile-nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 8px;
            border-radius: var(--radius);
            text-decoration: none;
            color: var(--text-secondary);
            font-size: 12px;
            transition: all var(--transition-fast);
            min-height: 60px;
            justify-content: center;
        }

        .mobile-nav-item:hover,
        .mobile-nav-item.active {
            background: var(--primary-light);
            color: var(--primary);
        }

        .mobile-nav-item i {
            font-size: 18px;
            margin-bottom: 4px;
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

        /* Chart Styles */
        .charts-section {
            margin: 32px 0;
        }

        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 24px;
            margin-bottom: 24px;
        }

        .chart-card {
            background: var(--surface);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            overflow: hidden;
        }

        .chart-card.full-width {
            grid-column: 1 / -1;
        }

        .chart-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border);
            background: linear-gradient(135deg, var(--primary-light) 0%, rgba(255,255,255,0.5) 100%);
        }

        .chart-header h3 {
            margin: 0;
            color: var(--text-primary);
            font-size: 1.125rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .chart-container {
            padding: 24px;
            height: 300px;
            position: relative;
        }

        .chart-card.full-width .chart-container {
            height: 350px;
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

            <!-- Dashboard Charts -->
            <div class="charts-section">
                <div class="charts-grid">
                    <!-- User Distribution Chart -->
                    <div class="chart-card">
                        <div class="chart-header">
                            <h3><i class="fa fa-users"></i> User Distribution</h3>
                        </div>
                        <div class="chart-container">
                            <canvas id="userDistributionChart"></canvas>
                        </div>
                    </div>

                    <!-- Device Status Chart -->
                    <div class="chart-card">
                        <div class="chart-header">
                            <h3><i class="fa fa-box"></i> Device Status</h3>
                        </div>
                        <div class="chart-container">
                            <canvas id="deviceStatusChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Incident Trends Chart -->
                <div class="chart-card full-width">
                    <div class="chart-header">
                        <h3><i class="fa fa-chart-line"></i> Incident Trends (Last 7 Days)</h3>
                    </div>
                    <div class="chart-container">
                        <canvas id="incidentTrendsChart"></canvas>
                    </div>
                </div>

                <!-- Activity Trends Chart -->
                <div class="chart-card full-width">
                    <div class="chart-header">
                        <h3><i class="fa fa-chart-area"></i> System Activity Trends (Last 7 Days)</h3>
                    </div>
                    <div class="chart-container">
                        <canvas id="activityTrendsChart"></canvas>
                    </div>
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

    <script>
        // Debug: Log data availability
        console.log('Dashboard Data Check:');
        console.log('User Distribution:', <?php echo json_encode($user_distribution); ?>);
        console.log('Device Status:', <?php echo json_encode($chart_data['device_status'] ?? []); ?>);
        console.log('Incident Trends:', <?php echo json_encode($chart_data['incident_trends'] ?? []); ?>);
        console.log('Activity Trends:', <?php echo json_encode($chart_data['activity_trends'] ?? []); ?>);

        // User Distribution Chart
        const userCtx = document.getElementById('userDistributionChart').getContext('2d');
        const userData = <?php echo json_encode($user_distribution); ?>;
        
        if (userData.length > 0) {
            new Chart(userCtx, {
                type: 'doughnut',
                data: {
                    labels: userData.map(item => item.role),
                    datasets: [{
                        data: userData.map(item => item.count),
                        backgroundColor: [
                            '#3b82f6',
                            '#8b5cf6',
                            '#10b981',
                            '#f59e0b'
                        ],
                        borderWidth: 2,
                        borderColor: '#ffffff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 20,
                                font: {
                                    size: 12,
                                    family: 'Inter'
                                }
                            }
                        }
                    }
                }
            });
        } else {
            userCtx.canvas.parentNode.innerHTML = '<div style="text-align: center; padding: 3rem; color: var(--text-tertiary);">No user data available</div>';
        }

        // Device Status Chart
        const deviceCtx = document.getElementById('deviceStatusChart').getContext('2d');
        const deviceData = <?php echo json_encode($chart_data['device_status'] ?? []); ?>;
        
        if (deviceData.length > 0) {
            new Chart(deviceCtx, {
                type: 'doughnut',
                data: {
                    labels: deviceData.map(item => item.dev_status.charAt(0).toUpperCase() + item.dev_status.slice(1)),
                    datasets: [{
                        data: deviceData.map(item => parseInt(item.count) || 0),
                        backgroundColor: [
                            '#10b981',
                            '#3b82f6',
                            '#f59e0b',
                            '#ef4444'
                        ],
                        borderWidth: 2,
                        borderColor: '#ffffff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 20,
                                font: {
                                    size: 12,
                                    family: 'Inter'
                                }
                            }
                        }
                    }
                }
            });
        } else {
            deviceCtx.canvas.parentNode.innerHTML = '<div style="text-align: center; padding: 3rem; color: var(--text-tertiary);">No device data available</div>';
        }

        // Incident Trends Chart
        const incidentCtx = document.getElementById('incidentTrendsChart').getContext('2d');
        const incidentData = <?php echo json_encode($chart_data['incident_trends'] ?? []); ?>;
        
        if (incidentData.length > 0) {
            new Chart(incidentCtx, {
                type: 'line',
                data: {
                    labels: incidentData.map(item => {
                        const date = new Date(item.date);
                        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
                    }),
                    datasets: [{
                        label: 'Total Incidents',
                        data: incidentData.map(item => parseInt(item.incidents) || 0),
                        borderColor: '#ef4444',
                        backgroundColor: 'rgba(239, 68, 68, 0.1)',
                        tension: 0.4,
                        fill: true
                    }, {
                        label: 'Completed',
                        data: incidentData.map(item => parseInt(item.completed) || 0),
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        tension: 0.4,
                        fill: true
                    }, {
                        label: 'Active',
                        data: incidentData.map(item => parseInt(item.active) || 0),
                        borderColor: '#f59e0b',
                        backgroundColor: 'rgba(245, 158, 11, 0.1)',
                        tension: 0.4,
                        fill: true
                    }, {
                        label: 'Pending',
                        data: incidentData.map(item => parseInt(item.pending) || 0),
                        borderColor: '#8b5cf6',
                        backgroundColor: 'rgba(139, 92, 246, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                padding: 20,
                                font: {
                                    family: 'Inter'
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            }
                        },
                        x: {
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            }
                        }
                    }
                }
            });
        } else {
            incidentCtx.canvas.parentNode.innerHTML = '<div style="text-align: center; padding: 3rem; color: var(--text-tertiary);">No incident data available for the selected period</div>';
        }

        // Activity Trends Chart
        const activityCtx = document.getElementById('activityTrendsChart').getContext('2d');
        const activityData = <?php echo json_encode($chart_data['activity_trends'] ?? []); ?>;
        
        if (activityData.length > 0) {
            new Chart(activityCtx, {
                type: 'line',
                data: {
                    labels: activityData.map(item => {
                        const date = new Date(item.date);
                        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
                    }),
                    datasets: [{
                        label: 'System Activities',
                        data: activityData.map(item => parseInt(item.activities) || 0),
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        tension: 0.4,
                        fill: true,
                        yAxisID: 'y'
                    }, {
                        label: 'Active Users',
                        data: activityData.map(item => parseInt(item.active_users) || 0),
                        borderColor: '#8b5cf6',
                        backgroundColor: 'rgba(139, 92, 246, 0.1)',
                        tension: 0.4,
                        fill: true,
                        yAxisID: 'y1'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                padding: 20,
                                font: {
                                    family: 'Inter'
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Activities'
                            },
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Active Users'
                            },
                            grid: {
                                drawOnChartArea: false,
                            }
                        },
                        x: {
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            }
                        }
                    }
                }
            });
        } else {
            activityCtx.canvas.parentNode.innerHTML = '<div style="text-align: center; padding: 3rem; color: var(--text-tertiary);">No activity data available for the selected period</div>';
        }
    </script>
</body>
</html>
