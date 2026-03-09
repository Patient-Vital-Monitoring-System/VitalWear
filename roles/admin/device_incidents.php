<?php
session_start();
require_once '../../database/connection.php';

// Check if admin user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /VitalWear-1/login.html');
    exit();
}

$conn = getDBConnection();

// Get device statistics and information
$device_stats = [
    'total_devices' => 0,
    'available_devices' => 0,
    'assigned_devices' => 0,
    'maintenance_devices' => 0,
    'inactive_devices' => 0,
    'device_utilization' => 0
];

// Get device counts by status
$status_result = $conn->query("
    SELECT dev_status, COUNT(*) as count 
    FROM device 
    GROUP BY dev_status
");
if ($status_result) {
    $status_counts = $status_result->fetch_all(MYSQLI_ASSOC);
    foreach ($status_counts as $status) {
        $device_stats['total_devices'] += $status['count'];
        switch ($status['dev_status']) {
            case 'available':
                $device_stats['available_devices'] = $status['count'];
                break;
            case 'assigned':
                $device_stats['assigned_devices'] = $status['count'];
                break;
            case 'maintenance':
                $device_stats['maintenance_devices'] = $status['count'];
                break;
            case 'inactive':
                $device_stats['inactive_devices'] = $status['count'];
                break;
        }
    }
    
    // Calculate utilization
    $device_stats['device_utilization'] = $device_stats['total_devices'] > 0 
        ? round(($device_stats['assigned_devices'] / $device_stats['total_devices']) * 100, 1) 
        : 0;
}

// Get detailed device information with assignments
$devices_query = $conn->query("
    SELECT 
        d.dev_id,
        d.dev_serial,
        d.dev_status,
        d.last_maintenance,
        d.created_at,
        dl.resp_name as assigned_to,
        dl.date_assigned,
        dl.date_returned,
        TIMESTAMPDIFF(HOUR, dl.date_assigned, COALESCE(dl.date_returned, NOW())) as usage_hours
    FROM device d
    LEFT JOIN device_log dl ON d.dev_id = dl.dev_id 
        AND dl.date_returned IS NULL
    ORDER BY d.dev_id
");
$devices = [];
if ($devices_query) {
    $devices = $devices_query->fetch_all(MYSQLI_ASSOC);
}

// Get device assignment history
$assignment_history = $conn->query("
    SELECT 
        d.dev_serial,
        d.dev_status,
        dl.resp_name,
        dl.date_assigned,
        dl.date_returned,
        TIMESTAMPDIFF(HOUR, dl.date_assigned, COALESCE(dl.date_returned, NOW())) as usage_hours
    FROM device_log dl
    JOIN device d ON dl.dev_id = d.dev_id
    WHERE dl.date_assigned >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ORDER BY dl.date_assigned DESC
    LIMIT 20
");

// Get device maintenance schedule
$maintenance_schedule = $conn->query("
    SELECT 
        dev_id,
        dev_serial,
        dev_status,
        last_maintenance,
        DATEDIFF(NOW(), COALESCE(last_maintenance, created_at)) as days_since_maintenance
    FROM device
    WHERE dev_status = 'maintenance' OR 
          DATEDIFF(NOW(), COALESCE(last_maintenance, created_at)) > 90
    ORDER BY days_since_maintenance DESC
");

$maintenance_data = [];
if ($maintenance_schedule) {
    $maintenance_data = $maintenance_schedule->fetch_all(MYSQLI_ASSOC);
}

// Get device utilization trends (last 7 days)
$utilization_trends = $conn->query("
    SELECT 
        DATE(date_assigned) as date,
        COUNT(*) as assignments,
        COUNT(DISTINCT dev_id) as unique_devices
    FROM device_log
    WHERE date_assigned >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(date_assigned)
    ORDER BY date
");

// Get assignment history
$assignment_history = $conn->query("
    SELECT 
        d.dev_serial,
        d.dev_status,
        dl.resp_name,
        dl.date_assigned,
        dl.date_returned,
        TIMESTAMPDIFF(HOUR, dl.date_assigned, COALESCE(dl.date_returned, NOW())) as usage_hours
    FROM device_log dl
    JOIN device d ON dl.dev_id = d.dev_id
    WHERE dl.date_assigned >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ORDER BY dl.date_assigned DESC
    LIMIT 20
");

// Initialize data arrays
$history_data = [];
$trends_data = [];

if ($assignment_history) {
    $history_data = $assignment_history->fetch_all(MYSQLI_ASSOC);
}
if ($utilization_trends) {
    $trends_data = $utilization_trends->fetch_all(MYSQLI_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Device Management Overview - VitalWear</title>
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
            --surface: #FFFFFF;
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
        .navbar {
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

        .navbar-brand {
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .navbar-actions {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        /* Main Container */
        .admin-main {
            max-width: 1400px;
            margin: 0 auto;
            padding: 32px;
            margin-left: 280px;
            margin-top: 80px;
        }

        /* Content Header */
        .content-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-700) 100%);
            color: white;
            padding: 40px;
            border-radius: var(--radius-xl);
            margin-bottom: 32px;
            box-shadow: var(--shadow-lg);
        }

        .content-title {
            font-size: 2rem;
            font-weight: 700;
            margin: 0 0 8px 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .content-subtitle {
            margin: 0;
            opacity: 0.9;
            font-size: 1rem;
        }

        /* Card Styles */
        .card {
            background: var(--surface);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            margin-bottom: 32px;
            overflow: hidden;
        }

        .card-header {
            padding: 24px 32px;
            border-bottom: 1px solid var(--border);
            font-weight: 600;
            color: var(--text-primary);
            font-size: 1.25rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .card-body {
            padding: 32px;
        }

        /* Metrics Grid */
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }

        .metric-card {
            background: var(--surface);
            padding: 24px;
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            text-align: center;
            transition: all var(--transition);
            position: relative;
            overflow: hidden;
        }

        .metric-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary) 0%, var(--primary-700) 100%);
        }

        .metric-card:hover {
            box-shadow: var(--shadow-lg);
            transform: translateY(-2px);
        }

        .metric-icon {
            font-size: 2rem;
            margin-bottom: 8px;
        }

        .metric-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 4px;
        }

        .metric-label {
            color: var(--text-secondary);
            font-size: 0.875rem;
            font-weight: 500;
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

        /* Buttons */
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: var(--radius-lg);
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-700);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .btn-secondary {
            background: var(--surface);
            color: var(--text-primary);
            border: 1px solid var(--border);
        }

        .btn-secondary:hover {
            background: var(--gray-100);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        /* Table Styles */
        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 12px 16px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }

        th {
            background: var(--gray-50);
            font-weight: 600;
            color: var(--text-primary);
        }

        tr:hover {
            background: var(--primary-light);
        }

        /* Badge Styles */
        .badge {
            padding: 4px 12px;
            border-radius: var(--radius-full);
            font-size: 0.75rem;
            font-weight: 600;
        }

        .badge-success {
            background: var(--success-light);
            color: var(--success);
        }

        .badge-warning {
            background: var(--warning-light);
            color: var(--warning);
        }

        .badge-danger {
            background: var(--error-light);
            color: var(--error);
        }

        .badge-primary {
            background: var(--info-light);
            color: var(--info);
        }

        /* Text muted style */
        .text-muted {
            color: var(--text-tertiary);
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
                    <a href="dashboard.php" class="nav-item">
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
                        <a href="device_incidents.php" class="nav-item active">
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
            <!-- Top Navigation -->
            <header class="navbar">
                <div>
                    <h1 class="navbar-brand"><i class="fa fa-box"></i> Device Overview</h1>
                </div>
                <div class="navbar-actions">
                    <span class="text-muted">Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                    <a href="/VitalWear-1/logout.php" class="logout-btn">
                        <i class="fa fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </header>

            <!-- Page Content -->
            <div class="content">
                <div class="content-header">
                    <h1 class="content-title"><i class="fa fa-box"></i> Device Management Overview</h1>
                    <p class="content-subtitle">Comprehensive device tracking and utilization monitoring</p>
                </div>

                <!-- Device Statistics -->
                <div class="metrics-grid">
                    <div class="metric-card">
                        <div class="metric-icon">📱</div>
                        <div class="metric-value"><?php echo number_format($device_stats['total_devices']); ?></div>
                        <div class="metric-label">Total Devices</div>
                    </div>
                    
                    <div class="metric-card">
                        <div class="metric-icon">✅</div>
                        <div class="metric-value"><?php echo number_format($device_stats['available_devices']); ?></div>
                        <div class="metric-label">Available</div>
                    </div>
                    
                    <div class="metric-card">
                        <div class="metric-icon">👤</div>
                        <div class="metric-value"><?php echo number_format($device_stats['assigned_devices']); ?></div>
                        <div class="metric-label">Assigned</div>
                    </div>
                    
                    <div class="metric-card">
                        <div class="metric-icon">📊</div>
                        <div class="metric-value"><?php echo $device_stats['device_utilization']; ?>%</div>
                        <div class="metric-label">Utilization Rate</div>
                    </div>
                </div>

                <!-- Additional Device Stats -->
                <div class="metrics-grid">
                    <div class="metric-card">
                        <div class="metric-icon">🔧</div>
                        <div class="metric-value"><?php echo number_format($device_stats['maintenance_devices']); ?></div>
                        <div class="metric-label">In Maintenance</div>
                    </div>
                    
                    <div class="metric-card">
                        <div class="metric-icon">⚠️</div>
                        <div class="metric-value"><?php echo number_format($device_stats['inactive_devices']); ?></div>
                        <div class="metric-label">Inactive</div>
                    </div>
                </div>

                <!-- Device Utilization Trends -->
                <div class="card">
                    <div class="card-header">
                        Device Assignment Trends (Last 7 Days)
                    </div>
                    <div class="card-body">
                        <div style="height: 300px; position: relative;">
                            <canvas id="utilizationTrendsChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Device Status Distribution -->
                <div class="card">
                    <div class="card-header">
                        Device Status Distribution
                    </div>
                    <div class="card-body">
                        <div style="height: 300px; position: relative;">
                            <canvas id="deviceStatusChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Device List -->
                <div class="card">
                    <div class="card-header">
                        Device Inventory
                        <span style="background: var(--primary); color: white; padding: 0.25rem 0.75rem; border-radius: var(--radius-full); font-size: 0.75rem; font-weight: 600; margin-left: 1rem;">
                            <?php echo count($devices); ?> Devices
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="table" style="overflow-x: auto;">
                            <table style="min-width: 900px;">
                                <thead>
                                    <tr>
                                        <th>Device ID</th>
                                        <th>Serial Number</th>
                                        <th>Status</th>
                                        <th>Assigned To</th>
                                        <th>Usage Hours</th>
                                        <th>Last Maintenance</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($devices)): ?>
                                        <?php foreach ($devices as $device): ?>
                                        <tr>
                                            <td>
                                                <span style="font-family: 'Inter', monospace; font-weight: 600; color: var(--accent);">
                                                    #<?php echo htmlspecialchars($device['dev_id']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($device['dev_serial']); ?></strong>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?php 
                                                    echo $device['dev_status'] === 'available' ? 'success' : 
                                                         ($device['dev_status'] === 'assigned' ? 'primary' : 
                                                         ($device['dev_status'] === 'maintenance' ? 'warning' : 'danger')); 
                                                ?>">
                                                    <?php echo htmlspecialchars(ucfirst($device['dev_status'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php 
                                                if (!empty($device['assigned_to'])) {
                                                    echo '<strong>' . htmlspecialchars($device['assigned_to']) . '</strong>';
                                                    echo '<div style="font-size: 0.75rem; color: var(--muted); margin-top: 0.25rem;">';
                                                    echo 'Since: ' . date('M j, H:i', strtotime($device['date_assigned']));
                                                    echo '</div>';
                                                } else {
                                                    echo '<span style="color: var(--text-tertiary);">Not assigned</span>';
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <?php 
                                                if ($device['usage_hours'] > 0) {
                                                    echo '<span style="font-weight: 600; color: var(--primary);">';
                                                    echo number_format($device['usage_hours']) . 'h';
                                                    echo '</span>';
                                                } else {
                                                    echo '<span style="color: var(--text-tertiary);">N/A</span>';
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <?php 
                                                if (!empty($device['last_maintenance'])) {
                                                    $days_ago = (strtotime(date('Y-m-d')) - strtotime($device['last_maintenance'])) / (60 * 60 * 24);
                                                    echo '<span style="font-weight: 600; color: ';
                                                    echo $days_ago > 90 ? 'var(--danger)' : 'var(--success)';
                                                    echo ';">';
                                                    echo date('M j, Y', strtotime($device['last_maintenance']));
                                                    echo '</span>';
                                                    echo '<div style="font-size: 0.75rem; color: var(--muted); margin-top: 0.25rem;">';
                                                    echo '(' . round($days_ago) . ' days ago)';
                                                    echo '</div>';
                                                } else {
                                                    echo '<span style="color: var(--warning);">Never maintained</span>';
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <div style="display: flex; gap: 0.5rem;">
                                                    <?php if ($device['dev_status'] === 'available'): ?>
                                                        <button class="btn btn-primary" style="padding: 0.25rem 0.75rem; font-size: 0.75rem;">
                                                            Assign
                                                        </button>
                                                    <?php elseif ($device['dev_status'] === 'assigned'): ?>
                                                        <button class="btn btn-secondary" style="padding: 0.25rem 0.75rem; font-size: 0.75rem;">
                                                            Return
                                                        </button>
                                                    <?php endif; ?>
                                                    <button class="btn btn-secondary" style="padding: 0.25rem 0.75rem; font-size: 0.75rem;">
                                                        Details
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" style="text-align: center; padding: 3rem; color: var(--text-tertiary);">
                                                No devices found in the system
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Maintenance Schedule -->
                <div class="card">
                    <div class="card-header">
                        Maintenance Schedule
                        <span style="background: var(--warning); color: white; padding: 0.25rem 0.75rem; border-radius: var(--radius-full); font-size: 0.75rem; font-weight: 600; margin-left: 1rem;">
                            <?php echo count($maintenance_data); ?> Devices Need Attention
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="table" style="overflow-x: auto;">
                            <table style="min-width: 800px;">
                                <thead>
                                    <tr>
                                        <th>Device ID</th>
                                        <th>Serial Number</th>
                                        <th>Status</th>
                                        <th>Days Since Maintenance</th>
                                        <th>Priority</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($maintenance_data)): ?>
                                        <?php foreach ($maintenance_data as $device): ?>
                                        <tr>
                                            <td>
                                                <span style="font-family: 'Inter', monospace; font-weight: 600; color: var(--accent);">
                                                    #<?php echo htmlspecialchars($device['dev_id']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($device['dev_serial']); ?></strong>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?php 
                                                    echo $device['dev_status'] === 'maintenance' ? 'danger' : 'warning'; 
                                                ?>">
                                                    <?php echo htmlspecialchars(ucfirst($device['dev_status'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span style="font-weight: 600; color: <?php 
                                                    echo $device['days_since_maintenance'] > 180 ? 'var(--danger)' : 'var(--warning)'; 
                                                ?>;">
                                                    <?php echo $device['days_since_maintenance']; ?> days
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?php 
                                                    echo $device['days_since_maintenance'] > 180 ? 'danger' : 'warning'; 
                                                ?>">
                                                    <?php echo $device['days_since_maintenance'] > 180 ? 'Urgent' : 'Scheduled'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button class="btn btn-primary" style="padding: 0.25rem 0.75rem; font-size: 0.75rem;">
                                                    Schedule Maintenance
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" style="text-align: center; padding: 3rem; color: var(--text-tertiary);">
                                                All devices are properly maintained
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Recent Assignment History -->
                <div class="card">
                    <div class="card-header">
                        Recent Assignment History
                    </div>
                    <div class="card-body">
                        <div class="table" style="overflow-x: auto;">
                            <table style="min-width: 800px;">
                                <thead>
                                    <tr>
                                        <th>Device Serial</th>
                                        <th>Assigned To</th>
                                        <th>Date Assigned</th>
                                        <th>Date Returned</th>
                                        <th>Usage Duration</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($history_data)): ?>
                                        <?php foreach ($history_data as $history): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($history['dev_serial']); ?></strong>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($history['resp_name']); ?>
                                            </td>
                                            <td>
                                                <?php echo date('M j, Y H:i', strtotime($history['date_assigned'])); ?>
                                            </td>
                                            <td>
                                                <?php 
                                                if ($history['date_returned']) {
                                                    echo date('M j, Y H:i', strtotime($history['date_returned']));
                                                } else {
                                                    echo '<span style="color: var(--warning);">Still assigned</span>';
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <span style="font-weight: 600; color: var(--primary);">
                                                    <?php echo number_format($history['usage_hours']); ?>h
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?php echo $history['date_returned'] ? 'success' : 'primary'; ?>">
                                                    <?php echo $history['date_returned'] ? 'Returned' : 'Active'; ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" style="text-align: center; padding: 3rem; color: var(--text-tertiary);">
                                                No assignment history available
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Export Options -->
                <div class="card">
                    <div class="card-header">
                        Export Options
                    </div>
                    <div class="card-body">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                            <button class="btn btn-primary" onclick="exportToCSV()">
                                📊 Export to CSV
                            </button>
                            <button class="btn btn-secondary" onclick="window.print()">
                                🖨️ Print Report
                            </button>
                            <button class="btn btn-secondary" onclick="exportToPDF()">
                                📄 Export to PDF
                            </button>
                            <button class="btn btn-secondary" onclick="addDevice()">
                                ➕ Add Device
                            </button>
                        </div>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <script>
        // Device Utilization Trends Chart
        const utilizationCtx = document.getElementById('utilizationTrendsChart').getContext('2d');
        new Chart(utilizationCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_map(function($date) { 
                    return date('M j', strtotime($date)); 
                }, array_column($trends_data ?? [], 'date'))); ?>,
                datasets: [{
                    label: 'Device Assignments',
                    data: <?php echo json_encode(array_column($trends_data ?? [], 'assignments')); ?>,
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.4
                }, {
                    label: 'Unique Devices Used',
                    data: <?php echo json_encode(array_column($trends_data ?? [], 'unique_devices')); ?>,
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Device Status Distribution Chart
        const statusCtx = document.getElementById('deviceStatusChart').getContext('2d');
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: ['Available', 'Assigned', 'Maintenance', 'Inactive'],
                datasets: [{
                    data: [
                        <?php echo $device_stats['total_devices']; ?>,
                        <?php echo $device_stats['assigned_devices']; ?>,
                        <?php echo $device_stats['maintenance_devices']; ?>,
                        <?php echo $device_stats['inactive_devices']; ?>
                    ],
                    backgroundColor: [
                        '#10b981',
                        '#3b82f6',
                        '#f59e0b',
                        '#ef4444'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                    }
                }
            }
        });

        // Export Functions
        function exportToCSV() {
            alert('CSV export functionality would be implemented here');
        }

        function exportToPDF() {
            alert('PDF export functionality would be implemented here');
        }

        function addDevice() {
            alert('Add device functionality would be implemented here');
        }
    </script>
</body>
</html>
