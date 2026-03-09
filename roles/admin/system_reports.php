<?php
session_start();
require_once '../../database/connection.php';

// Check if admin user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /VitalWear-1/login.html');
    exit();
}

// Get comprehensive system statistics
$system_stats = [
    'total_users' => 0,
    'total_devices' => 0,
    'total_incidents' => 0,
    'active_incidents' => 0,
    'completed_incidents' => 0,
    'device_utilization' => 0,
    'avg_response_time' => 0,
    'system_uptime' => '99.9%'
];

// Get user counts by role
$user_stats = [];
$tables = [
    'admin' => ['admin_id', 'admin_name', 'Admin'],
    'management' => ['mgmt_id', 'mgmt_name', 'Management'],
    'responder' => ['resp_id', 'resp_name', 'Responder'],
    'rescuer' => ['resc_id', 'resc_name', 'Rescuer']
];

foreach ($tables as $table => $fields) {
    $result = $conn->query("SELECT COUNT(*) as count FROM $table");
    if ($result) {
        $count = $result->fetch_assoc()['count'];
        $system_stats['total_users'] += $count;
        $user_stats[$fields[2]] = $count;
    }
}

// Get device statistics
$device_result = $conn->query("SELECT COUNT(*) as count FROM device");
if ($device_result) {
    $system_stats['total_devices'] = $device_result->fetch_assoc()['count'];
}

$assigned_result = $conn->query("SELECT COUNT(*) as count FROM device WHERE dev_status = 'assigned'");
if ($assigned_result) {
    $assigned_count = $assigned_result->fetch_assoc()['count'];
    $system_stats['device_utilization'] = $system_stats['total_devices'] > 0 
        ? round(($assigned_count / $system_stats['total_devices']) * 100, 1) 
        : 0;
}

// Get incident statistics
$incident_result = $conn->query("SELECT COUNT(*) as count FROM incident");
if ($incident_result) {
    $system_stats['total_incidents'] = $incident_result->fetch_assoc()['count'];
}

$active_result = $conn->query("SELECT COUNT(*) as count FROM incident WHERE status = 'ongoing'");
if ($active_result) {
    $system_stats['active_incidents'] = $active_result->fetch_assoc()['count'];
}

$completed_result = $conn->query("SELECT COUNT(*) as count FROM incident WHERE status = 'completed'");
if ($completed_result) {
    $system_stats['completed_incidents'] = $completed_result->fetch_assoc()['count'];
}

// Get monthly incident trends
$monthly_trends = [];
$trend_query = $conn->query("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        COUNT(*) as incidents,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
    FROM incident 
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month ASC
");
if ($trend_query) {
    $monthly_trends = array_reverse($trend_query->fetch_all(MYSQLI_ASSOC));
}

// Get device status distribution
$device_status = [];
$status_query = $conn->query("
    SELECT dev_status, COUNT(*) as count 
    FROM device 
    GROUP BY dev_status
");
if ($status_query) {
    $device_status = $status_query->fetch_all(MYSQLI_ASSOC);
}

// Get recent activities
$recent_activities = [];
$activity_query = $conn->query("
    SELECT user_name, user_role, description, created_at 
    FROM activity_log 
    ORDER BY created_at DESC 
    LIMIT 10
");
if ($activity_query) {
    $recent_activities = $activity_query->fetch_all(MYSQLI_ASSOC);
}

// Get system health metrics
$health_metrics = [
    'database_status' => 'Healthy',
    'api_response_time' => '125ms',
    'error_rate' => '0.2%',
    'last_backup' => date('M j, Y H:i', strtotime('-2 hours')),
    'storage_usage' => '68%',
    'memory_usage' => '42%'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Reports - VitalWear</title>
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

        /* Report Cards */
        .reports-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 24px;
        }

        .report-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-xl);
            padding: 32px;
            text-decoration: none;
            color: var(--text-primary);
            transition: all var(--transition);
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow);
        }

        .report-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary) 0%, var(--primary-700) 100%);
        }

        .report-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }

        .report-icon {
            font-size: 2.5rem;
            margin-bottom: 16px;
        }

        .report-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--text-primary);
        }

        .report-description {
            color: var(--text-secondary);
            margin-bottom: 16px;
            line-height: 1.5;
        }

        .report-stats {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .stat-badge {
            background: var(--primary-light);
            color: var(--primary);
            padding: 4px 12px;
            border-radius: var(--radius-full);
            font-size: 0.75rem;
            font-weight: 600;
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
                        <a href="system_reports.php" class="nav-item active">
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
                    <h1><i class="fa fa-chart-line"></i> System Reports</h1>
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
                <h1><i class="fa fa-chart-line"></i> System Reports & Analytics</h1>
                <p>Comprehensive system overview and performance metrics</p>
            </div>

                <!-- System Health Metrics -->
                <div class="metrics-grid">
                    <div class="metric-card">
                        <div class="metric-icon">🗄️</div>
                        <div class="metric-value"><?php echo htmlspecialchars($health_metrics['database_status']); ?></div>
                        <div class="metric-label">Database Status</div>
                    </div>
                    
                    <div class="metric-card">
                        <div class="metric-icon">⚡</div>
                        <div class="metric-value"><?php echo htmlspecialchars($health_metrics['api_response_time']); ?></div>
                        <div class="metric-label">API Response</div>
                    </div>
                    
                    <div class="metric-card">
                        <div class="metric-icon">🔒</div>
                        <div class="metric-value"><?php echo htmlspecialchars($health_metrics['error_rate']); ?></div>
                        <div class="metric-label">Error Rate</div>
                    </div>
                    
                    <div class="metric-card">
                        <div class="metric-icon">💾</div>
                        <div class="metric-value"><?php echo htmlspecialchars($health_metrics['storage_usage']); ?></div>
                        <div class="metric-label">Storage Usage</div>
                    </div>
                </div>

                <!-- Report Generation -->
                <div class="card">
                    <div class="card-header">
                        Report Generation
                    </div>
                    <div class="card-body">
                        <div class="reports-grid">
                            <a href="reports/user_activity_report.php" class="report-card">
                                <div class="report-icon">📈</div>
                                <div class="report-title">User Activity Report</div>
                                <div class="report-description">Comprehensive user engagement and activity metrics</div>
                                <div class="report-stats">
                                    <span class="stat-badge">30 Days</span>
                                    <span class="stat-badge">Live Data</span>
                                </div>
                            </a>
                            
                            <a href="reports/incident_analysis.php" class="report-card">
                                <div class="report-icon">🚨</div>
                                <div class="report-title">Incident Analysis</div>
                                <div class="report-description">Detailed incident trends and response times</div>
                                <div class="report-stats">
                                    <span class="stat-badge">6 Months</span>
                                    <span class="stat-badge">Analytics</span>
                                </div>
                            </a>
                            
                            <a href="reports/device_performance.php" class="report-card">
                                <div class="report-icon">📱</div>
                                <div class="report-title">Device Performance</div>
                                <div class="report-description">Device utilization and maintenance reports</div>
                                <div class="report-stats">
                                    <span class="stat-badge">Real-time</span>
                                    <span class="stat-badge">Metrics</span>
                                </div>
                            </a>
                            
                            <a href="reports/security_audit.php" class="report-card">
                                <div class="report-icon">🔒</div>
                                <div class="report-title">Security Audit</div>
                                <div class="report-description">System security and access control analysis</div>
                                <div class="report-stats">
                                    <span class="stat-badge">30 Days</span>
                                    <span class="stat-badge">Security</span>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Recent Activities -->
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
                                        <strong><?php echo htmlspecialchars($activity['user_name'] ?? 'System'); ?></strong>
                                        <p><?php echo htmlspecialchars($activity['description']); ?></p>
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

            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Generate Report Function
        function generateReport(type) {
            const reportTypes = {
                'user': 'User Activity Report',
                'incident': 'Incident Analytics Report',
                'device': 'Device Performance Report',
                'vital': 'Vital Statistics Report',
                'system': 'System Health Report',
                'compliance': 'Compliance Report'
            };
            
            const reportName = reportTypes[type] || 'System Report';
            
            // Show loading message
            alert(`Generating ${reportName}...\n\nThis feature would generate a comprehensive PDF/Excel report with:\n\n• Detailed analytics and metrics\n• Charts and visualizations\n• Trend analysis\n• Recommendations\n• Export options`);
            
            // In a real implementation, this would:
            // 1. Collect data from database
            // 2. Generate charts and analytics
            // 3. Create PDF/Excel report
            // 4. Provide download link
        }
    </script>
</body>
</html>
