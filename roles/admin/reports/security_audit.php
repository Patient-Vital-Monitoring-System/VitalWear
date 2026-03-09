<?php
session_start();
require_once '../../../database/connection.php';

// Check if admin user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /VitalWear-1/login.html');
    exit();
}

$conn = getDBConnection();

// Get security data
$security_data = [];
$error_message = '';

try {
    // Get login attempts (last 30 days)
    $login_attempts = $conn->query("
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as total_attempts,
            SUM(CASE WHEN action_type = 'login_success' THEN 1 ELSE 0 END) as successful_logins,
            SUM(CASE WHEN action_type = 'login_failed' THEN 1 ELSE 0 END) as failed_logins
        FROM activity_log 
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        AND (action_type = 'login_success' OR action_type = 'login_failed')
        GROUP BY DATE(created_at)
        ORDER BY date
    ");
    
    // Get user access patterns
    $access_patterns = $conn->query("
        SELECT 
            user_role,
            COUNT(DISTINCT user_name) as unique_users,
            COUNT(*) as total_activities,
            COUNT(DISTINCT module) as modules_accessed
        FROM activity_log 
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY user_role
        ORDER BY total_activities DESC
    ");
    
    // Get security events
    $security_events = $conn->query("
        SELECT user_name, user_role, action_type, module, description, created_at
        FROM activity_log 
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        AND (action_type LIKE '%failed%' OR action_type LIKE '%denied%' OR action_type LIKE '%blocked%')
        ORDER BY created_at DESC
        LIMIT 20
    ");
    
    // Get user account status
    $account_status = $conn->query("
        SELECT 'admin' as role, COUNT(*) as total, 
               SUM(CASE WHEN admin_email LIKE '%@%' THEN 1 ELSE 0 END) as active
        FROM admin
        UNION ALL
        SELECT 'management' as role, COUNT(*) as total,
               SUM(CASE WHEN mgmt_email LIKE '%@%' THEN 1 ELSE 0 END) as active
        FROM management
        UNION ALL
        SELECT 'responder' as role, COUNT(*) as total,
               SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active
        FROM responder
        UNION ALL
        SELECT 'rescuer' as role, COUNT(*) as total,
               SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active
        FROM rescuer
    ");
    
    if ($login_attempts) $security_data['login_attempts'] = $login_attempts->fetch_all(MYSQLI_ASSOC);
    if ($access_patterns) $security_data['access_patterns'] = $access_patterns->fetch_all(MYSQLI_ASSOC);
    if ($security_events) $security_data['security_events'] = $security_events->fetch_all(MYSQLI_ASSOC);
    if ($account_status) $security_data['account_status'] = $account_status->fetch_all(MYSQLI_ASSOC);
    
} catch (Exception $e) {
    $error_message = "Error fetching security data: " . $e->getMessage();
}

// Calculate summary statistics
$total_logins = 0;
$failed_logins = 0;
$suspicious_activities = 0;

if (!empty($security_data['login_attempts'])) {
    foreach ($security_data['login_attempts'] as $attempt) {
        $total_logins += $attempt['total_attempts'];
        $failed_logins += $attempt['failed_logins'];
    }
}

if (!empty($security_data['security_events'])) {
    $suspicious_activities = count($security_data['security_events']);
}

$security_score = $total_logins > 0 ? max(0, 100 - round(($failed_logins / $total_logins) * 100)) : 100;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Audit - VitalWear</title>
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

        .badge-admin {
            background: var(--info-light);
            color: var(--info);
        }

        .badge-responder {
            background: var(--success-light);
            color: var(--success);
        }

        .badge-rescuer {
            background: var(--warning-light);
            color: var(--warning);
        }

        .badge-management {
            background: var(--primary-light);
            color: var(--primary);
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
                    <a href="../dashboard.php" class="nav-item">
                        <i class="fa fa-gauge"></i> Dashboard
                    </a>
                </div>
                
                <div class="nav-group">
                    <div class="nav-group-title">User Management</div>
                    <div class="nav-group-items">
                        <a href="../users.php" class="nav-item">
                            <i class="fa fa-users"></i> Staff Directory
                        </a>
                        <a href="../users/view_management.php" class="nav-item">
                            <i class="fa fa-user-tie"></i> Management
                        </a>
                        <a href="../users/view_responders.php" class="nav-item">
                            <i class="fa fa-user-md"></i> Responders
                        </a>
                        <a href="../users/view_rescuers.php" class="nav-item">
                            <i class="fa fa-user-shield"></i> Rescuers
                        </a>
                        <a href="../users/view_admins.php" class="nav-item">
                            <i class="fa fa-user-cog"></i> Admins
                        </a>
                    </div>
                </div>
                
                <div class="nav-group">
                    <div class="nav-group-title">Reports</div>
                    <div class="nav-group-items">
                        <a href="../system_reports.php" class="nav-item">
                            <i class="fa fa-chart-line"></i> System Reports
                        </a>
                        <a href="../vitals_analytics.php" class="nav-item">
                            <i class="fa fa-heartbeat"></i> Vital Analytics
                        </a>
                        <a href="../audit_log.php" class="nav-item">
                            <i class="fa fa-clipboard-list"></i> Activity Log
                        </a>
                    </div>
                </div>
                
                <div class="nav-group">
                    <div class="nav-group-title">Monitoring</div>
                    <div class="nav-group-items">
                        <a href="../device_incidents.php" class="nav-item">
                            <i class="fa fa-box"></i> Device Overview
                        </a>
                        <a href="../vitals.php" class="nav-item">
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
                    <h1><i class="fa fa-shield-alt"></i> Security Audit</h1>
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
                <h1><i class="fa fa-shield-alt"></i> Security Audit</h1>
                <p>System security and access control analysis</p>
            </div>

                <!-- Error Display -->
                <?php if (!empty($error_message)): ?>
                <div class="card">
                    <div class="card-header" style="background: linear-gradient(135deg, var(--error-light) 0%, rgba(220, 38, 38, 0.1) 100%); color: var(--error);">
                        Database Connection Issues
                    </div>
                    <div class="card-body">
                        <div style="color: var(--error); padding: 1rem; background: var(--error-light); border-radius: var(--radius-md); border: 1px solid rgba(239, 68, 68, 0.2);">
                            <?php echo $error_message; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Security Score -->
                <div class="metrics-grid">
                    <div class="metric-card">
                        <div class="metric-icon">🔒</div>
                        <div class="metric-value" style="color: <?php echo $security_score >= 80 ? 'var(--success)' : ($security_score >= 60 ? 'var(--warning)' : 'var(--error)'); ?>;">
                            <?php echo $security_score; ?>%
                        </div>
                        <div class="metric-label">Security Score</div>
                    </div>
                    
                    <div class="metric-card">
                        <div class="metric-icon">👥</div>
                        <div class="metric-value"><?php echo number_format($total_logins); ?></div>
                        <div class="metric-label">Total Logins (30 days)</div>
                    </div>
                    
                    <div class="metric-card">
                        <div class="metric-icon">⚠️</div>
                        <div class="metric-value"><?php echo number_format($failed_logins); ?></div>
                        <div class="metric-label">Failed Logins</div>
                    </div>
                    
                    <div class="metric-card">
                        <div class="metric-icon">🚨</div>
                        <div class="metric-value"><?php echo number_format($suspicious_activities); ?></div>
                        <div class="metric-label">Suspicious Activities</div>
                    </div>
                </div>

                <!-- Login Trends Chart -->
                <div class="card">
                    <div class="card-header">
                        Login Trends (Last 30 Days)
                    </div>
                    <div class="card-body">
                        <div style="height: 300px; position: relative;">
                            <canvas id="loginTrendsChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- User Access Patterns -->
                <div class="card">
                    <div class="card-header">
                        User Access Patterns by Role
                    </div>
                    <div class="card-body">
                        <div style="height: 300px; position: relative;">
                            <canvas id="accessPatternsChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Account Status Overview -->
                <div class="card">
                    <div class="card-header">
                        Account Status Overview
                    </div>
                    <div class="card-body">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem;">
                            <?php if (!empty($security_data['account_status'])): ?>
                                <?php foreach ($security_data['account_status'] as $account): ?>
                                <div style="text-align: center; padding: 1rem; border: 1px solid var(--border); border-radius: var(--radius-lg);">
                                    <h4 style="margin: 0 0 0.5rem 0; color: var(--text-primary);"><?php echo ucfirst($account['role']); ?></h4>
                                    <div style="font-size: 1.5rem; font-weight: 700; color: var(--primary); margin-bottom: 0.5rem;">
                                        <?php echo number_format($account['active']); ?>
                                    </div>
                                    <div style="font-size: 0.875rem; color: var(--text-secondary);">
                                        of <?php echo number_format($account['total']); ?> active
                                    </div>
                                    <div style="margin-top: 0.5rem; height: 4px; background: var(--gray-200); border-radius: 2px; overflow: hidden;">
                                        <div style="height: 100%; background: var(--primary); width: <?php echo round(($account['active'] / $account['total']) * 100); ?>%;"></div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Security Events -->
                <div class="card">
                    <div class="card-header">
                        Recent Security Events
                    </div>
                    <div class="card-body">
                        <div class="table" style="overflow-x: auto;">
                            <table style="min-width: 800px;">
                                <thead>
                                    <tr>
                                        <th>User</th>
                                        <th>Role</th>
                                        <th>Event Type</th>
                                        <th>Module</th>
                                        <th>Description</th>
                                        <th>Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($security_data['security_events'])): ?>
                                        <?php foreach ($security_data['security_events'] as $event): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($event['user_name'] ?? 'System'); ?></strong>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?php echo $event['user_role']; ?>">
                                                    <?php echo htmlspecialchars(ucfirst($event['user_role'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge badge-danger">
                                                    <?php echo htmlspecialchars($event['action_type']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span style="color: var(--text-secondary); font-size: 0.875rem;">
                                                    <?php echo htmlspecialchars($event['module'] ?? 'N/A'); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div style="max-width: 200px; word-break: break-word;">
                                                    <?php echo htmlspecialchars($event['description']); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="text-muted">
                                                    <?php echo date('M j, H:i', strtotime($event['created_at'])); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" style="text-align: center; padding: 3rem; color: var(--text-tertiary);">
                                                No security events detected in the last 30 days
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
                        </div>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
        // Login Trends Chart
        const loginCtx = document.getElementById('loginTrendsChart').getContext('2d');
        const loginData = <?php echo json_encode($security_data['login_attempts'] ?? []); ?>;
        
        if (loginData.length > 0) {
            new Chart(loginCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_map(function($date) { 
                    return date('M j', strtotime($date)); 
                }, array_column($security_data['login_attempts'] ?? [], 'date'))); ?>,
                datasets: [{
                    label: 'Successful Logins',
                    data: <?php echo json_encode(array_column($security_data['login_attempts'] ?? [], 'successful_logins')); ?>,
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    tension: 0.4
                }, {
                    label: 'Failed Logins',
                    data: <?php echo json_encode(array_column($security_data['login_attempts'] ?? [], 'failed_logins')); ?>,
                    borderColor: '#ef4444',
                    backgroundColor: 'rgba(239, 68, 68, 0.1)',
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
        } else {
            loginCtx.canvas.parentNode.innerHTML = '<div style="text-align: center; padding: 3rem; color: var(--text-tertiary);">No login trend data available for the selected period</div>';
        }

        // Access Patterns Chart
        const accessCtx = document.getElementById('accessPatternsChart').getContext('2d');
        const accessData = <?php echo json_encode($security_data['access_patterns'] ?? []); ?>;
        
        if (accessData.length > 0) {
            new Chart(accessCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_map('ucfirst', array_column($security_data['access_patterns'] ?? [], 'user_role'))); ?>,
                datasets: [{
                    label: 'Total Activities',
                    data: <?php echo json_encode(array_column($security_data['access_patterns'] ?? [], 'total_activities')); ?>,
                    backgroundColor: 'rgba(59, 130, 246, 0.8)',
                    borderColor: '#3b82f6',
                    borderWidth: 1
                }, {
                    label: 'Modules Accessed',
                    data: <?php echo json_encode(array_column($security_data['access_patterns'] ?? [], 'modules_accessed')); ?>,
                    backgroundColor: 'rgba(16, 185, 129, 0.8)',
                    borderColor: '#10b981',
                    borderWidth: 1
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
        } else {
            accessCtx.canvas.parentNode.innerHTML = '<div style="text-align: center; padding: 3rem; color: var(--text-tertiary);">No access pattern data available</div>';
        }

        // Export Functions
        function exportToCSV() {
            alert('CSV export functionality would be implemented here');
        }

        function exportToPDF() {
            alert('PDF export functionality would be implemented here');
        }
    </script>
</body>
</html>
