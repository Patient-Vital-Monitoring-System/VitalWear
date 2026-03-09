<?php
session_start();
require_once '../../database/connection.php';

// Check if admin user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /VitalWear-1/login.html');
    exit();
}

$conn = getDBConnection();

// Get responder and rescuer activity data
$activity_rows = [];
$error_message = '';

try {
    // Responder Activity
    $stmt = $conn->query("SELECT 
                        r.resp_id,
                        r.resp_name,
                        r.resp_email,
                        r.resp_contact,
                        r.status,
                        COUNT(DISTINCT i.incident_id) as incidents_handled,
                        COUNT(DISTINCT CASE WHEN i.status IN ('active', 'pending') THEN i.incident_id END) as active_incidents,
                        MAX(i.start_time) as last_activity,
                        'responder' as role
                    FROM responder r
                    LEFT JOIN incident i ON i.resp_id = r.resp_id
                    GROUP BY r.resp_id, r.resp_name, r.resp_email, r.resp_contact, r.status
                    ORDER BY incidents_handled DESC");
    $responders = $stmt->fetch_all(MYSQLI_ASSOC);
    
    // Rescuer Activity
    $stmt = $conn->query("SELECT 
                        rc.resc_id,
                        rc.resc_name,
                        rc.resc_email,
                        rc.resc_contact,
                        rc.status,
                        COUNT(DISTINCT i.incident_id) as incidents_handled,
                        COUNT(DISTINCT CASE WHEN i.status IN ('active', 'pending') THEN i.incident_id END) as active_incidents,
                        MAX(i.start_time) as last_activity,
                        'rescuer' as role
                    FROM rescuer rc
                    LEFT JOIN incident i ON i.resc_id = rc.resc_id
                    GROUP BY rc.resc_id, rc.resc_name, rc.resc_email, rc.resc_contact, rc.status
                    ORDER BY incidents_handled DESC");
    $rescuers = $stmt->fetch_all(MYSQLI_ASSOC);
    
    $activity_rows = array_merge($responders, $rescuers);
    
    // Get activity statistics
    $total_responders = count($responders);
    $total_rescuers = count($rescuers);
    $total_users = count($activity_rows);
    
    $active_responders = 0;
    $active_rescuers = 0;
    $total_incidents = 0;
    
    foreach ($responders as $responder) {
        if ($responder['active_incidents'] > 0) $active_responders++;
        $total_incidents += $responder['incidents_handled'];
    }
    
    foreach ($rescuers as $rescuer) {
        if ($rescuer['active_incidents'] > 0) $active_rescuers++;
        $total_incidents += $rescuer['incidents_handled'];
    }
    
    // Get activity trends (last 7 days)
    $activity_trends = $conn->query("
        SELECT 
            DATE(i.start_time) as date,
            COUNT(*) as total_incidents,
            COUNT(DISTINCT CASE WHEN r.resp_id IS NOT NULL THEN r.resp_id END) as active_responders,
            COUNT(DISTINCT CASE WHEN rc.resc_id IS NOT NULL THEN rc.resc_id END) as active_rescuers
        FROM incident i
        LEFT JOIN responder r ON i.resp_id = r.resp_id
        LEFT JOIN rescuer rc ON i.resc_id = rc.resc_id
        WHERE i.start_time >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY DATE(i.start_time)
        ORDER BY date
    ");
    
    // Get recent activities
    $recent_activities = $conn->query("
        SELECT 
            'Responder' as user_type,
            r.resp_name as user_name,
            r.resp_email as user_email,
            i.incident_id,
            i.incident_type,
            i.status,
            i.start_time as activity_time,
            i.location
        FROM incident i
        JOIN responder r ON i.resp_id = r.resp_id
        WHERE i.start_time >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        
        UNION ALL
        
        SELECT 
            'Rescuer' as user_type,
            rc.resc_name as user_name,
            rc.resc_email as user_email,
            i.incident_id,
            i.incident_type,
            i.status,
            i.start_time as activity_time,
            i.location
        FROM incident i
        JOIN rescuer rc ON i.resc_id = rc.resc_id
        WHERE i.start_time >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        
        ORDER BY activity_time DESC
        LIMIT 20
    ");
    
    if ($activity_trends) $trends_data = $activity_trends->fetch_all(MYSQLI_ASSOC);
    if ($recent_activities) $recent_data = $recent_activities->fetch_all(MYSQLI_ASSOC);
    
} catch (Exception $e) {
    $error_message = "Error fetching user activity data: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Activity Monitoring - VitalWear</title>
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

        .badge-secondary {
            background: var(--gray-100);
            color: var(--gray-700);
        }

        .badge-responder {
            background: var(--info-light);
            color: var(--info);
        }

        .badge-rescuer {
            background: var(--success-light);
            color: var(--success);
        }

        /* Text muted style */
        .text-muted {
            color: var(--text-tertiary);
        }

        /* Select and Input Styles */
        select {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            background: var(--surface);
            color: var(--text-primary);
            font-size: 0.875rem;
            transition: all var(--transition);
        }

        select:hover {
            border-color: var(--border-hover);
        }

        select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(30, 122, 184, 0.1);
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--text-primary);
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
                        <a href="device_incidents.php" class="nav-item">
                            <i class="fa fa-box"></i> Device Overview
                        </a>
                        <a href="vitals.php" class="nav-item active">
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
                    <h1 class="navbar-brand"><i class="fa fa-user-clock"></i> User Activity</h1>
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
                    <h1 class="content-title"><i class="fa fa-user-clock"></i> User Activity Monitoring</h1>
                    <p class="content-subtitle">Real-time tracking of responder and rescuer activities</p>
                </div>

                <!-- Error Display -->
                <?php if (!empty($error_message)): ?>
                <div class="card" style="margin-bottom: 2rem;">
                    <div class="card-header" style="background: linear-gradient(135deg, rgba(239, 68, 68, 0.1) 0%, rgba(220, 38, 38, 0.1) 100%); color: var(--danger);">
                        Database Connection Issues
                    </div>
                    <div class="card-body">
                        <div style="color: var(--danger); padding: 1rem; background: rgba(239, 68, 68, 0.05); border-radius: var(--radius); border: 1px solid rgba(239, 68, 68, 0.2);">
                            <?php echo $error_message; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- User Statistics -->
                <div class="metrics-grid">
                    <div class="metric-card">
                        <div class="metric-icon">👥</div>
                        <div class="metric-value"><?php echo number_format($total_users); ?></div>
                        <div class="metric-label">Total Field Staff</div>
                    </div>
                    
                    <div class="metric-card">
                        <div class="metric-icon">🚑</div>
                        <div class="metric-value"><?php echo number_format($total_responders); ?></div>
                        <div class="metric-label">Responders</div>
                    </div>
                    
                    <div class="metric-card">
                        <div class="metric-icon">🆘</div>
                        <div class="metric-value"><?php echo number_format($total_rescuers); ?></div>
                        <div class="metric-label">Rescuers</div>
                    </div>
                    
                    <div class="metric-card">
                        <div class="metric-icon">📊</div>
                        <div class="metric-value"><?php echo number_format($total_incidents); ?></div>
                        <div class="metric-label">Total Incidents</div>
                    </div>
                </div>

                <!-- Active Staff Statistics -->
                <div class="metrics-grid">
                    <div class="metric-card">
                        <div class="metric-icon">✅</div>
                        <div class="metric-value"><?php echo number_format($active_responders); ?></div>
                        <div class="metric-label">Active Responders</div>
                    </div>
                    
                    <div class="metric-card">
                        <div class="metric-icon">✅</div>
                        <div class="metric-value"><?php echo number_format($active_rescuers); ?></div>
                        <div class="metric-label">Active Rescuers</div>
                    </div>
                </div>

                <!-- Activity Trends Chart -->
                <div class="card">
                    <div class="card-header">
                        Activity Trends (Last 7 Days)
                    </div>
                    <div class="card-body">
                        <div style="height: 300px; position: relative;">
                            <canvas id="activityTrendsChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Filter Options -->
                <div class="card">
                    <div class="card-header">
                        Filter Options
                    </div>
                    <div class="card-body">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                            <div>
                                <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">User Type</label>
                                <select style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: var(--radius);">
                                    <option>All Users</option>
                                    <option>Responders</option>
                                    <option>Rescuers</option>
                                </select>
                            </div>
                            <div>
                                <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Status</label>
                                <select style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: var(--radius);">
                                    <option>All Status</option>
                                    <option>Active</option>
                                    <option>Inactive</option>
                                </select>
                            </div>
                            <div>
                                <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Activity Level</label>
                                <select style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: var(--radius);">
                                    <option>All Levels</option>
                                    <option>High Activity</option>
                                    <option>Medium Activity</option>
                                    <option>Low Activity</option>
                                </select>
                            </div>
                            <div style="display: flex; align-items: end;">
                                <button class="btn btn-primary" style="width: 100%;">
                                    🔍 Apply Filters
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- User Activity List -->
                <div class="card">
                    <div class="card-header">
                        Field Staff Activity Overview
                        <span style="background: var(--primary); color: white; padding: 0.25rem 0.75rem; border-radius: var(--radius-full); font-size: 0.75rem; font-weight: 600; margin-left: 1rem;">
                            <?php echo count($activity_rows); ?> Staff Members
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="table" style="overflow-x: auto;">
                            <table style="min-width: 900px;">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Contact</th>
                                        <th>Role</th>
                                        <th>Status</th>
                                        <th>Incidents Handled</th>
                                        <th>Active Cases</th>
                                        <th>Last Activity</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($activity_rows)): ?>
                                        <?php foreach ($activity_rows as $activity): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($activity['resp_name'] ?? $activity['resc_name']); ?></strong>
                                            </td>
                                            <td>
                                                <div style="color: var(--text-secondary); word-break: break-word;">
                                                    <?php echo htmlspecialchars($activity['resp_email'] ?? $activity['resc_email']); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div style="color: var(--text-secondary);">
                                                    <?php 
                                                    $contact = $activity['resp_contact'] ?? $activity['resc_contact'];
                                                    echo !empty($contact) ? htmlspecialchars($contact) : '<span style="color: var(--text-tertiary);">Not provided</span>';
                                                    ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?php echo $activity['role']; ?>">
                                                    <?php echo htmlspecialchars(ucfirst($activity['role'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?php echo ($activity['status'] ?? 'active') === 'active' ? 'success' : 'warning'; ?>">
                                                    <?php echo htmlspecialchars(ucfirst($activity['status'] ?? 'active')); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span style="font-weight: 600; color: var(--primary);">
                                                    <?php echo number_format($activity['incidents_handled']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span style="font-weight: 600; color: <?php echo $activity['active_incidents'] > 0 ? 'var(--warning)' : 'var(--success)'; ?>;">
                                                    <?php echo number_format($activity['active_incidents']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="text-muted">
                                                    <?php 
                                                    if (!empty($activity['last_activity'])) {
                                                        echo date('M j, H:i', strtotime($activity['last_activity']));
                                                    } else {
                                                        echo '<span style="color: var(--text-tertiary);">No activity</span>';
                                                    }
                                                    ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="9" style="text-align: center; padding: 3rem; color: var(--text-tertiary);">
                                                No user activity data available
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Recent Activities -->
                <div class="card">
                    <div class="card-header">
                        Recent Field Activities
                    </div>
                    <div class="card-body">
                        <div class="table" style="overflow-x: auto;">
                            <table style="min-width: 800px;">
                                <thead>
                                    <tr>
                                        <th>User Type</th>
                                        <th>Name</th>
                                        <th>Incident ID</th>
                                        <th>Type</th>
                                        <th>Status</th>
                                        <th>Location</th>
                                        <th>Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($recent_data)): ?>
                                        <?php foreach ($recent_data as $activity): ?>
                                        <tr>
                                            <td>
                                                <span class="badge badge-<?php echo $activity['user_type'] === 'Responder' ? 'primary' : 'secondary'; ?>">
                                                    <?php echo htmlspecialchars($activity['user_type']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($activity['user_name']); ?></strong>
                                            </td>
                                            <td>
                                                <span style="font-family: 'Inter', monospace; font-weight: 600; color: var(--primary);">
                                                    #<?php echo htmlspecialchars($activity['incident_id']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span style="color: var(--text-secondary);">
                                                    <?php echo htmlspecialchars($activity['incident_type']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?php 
                                                    echo $activity['status'] === 'active' ? 'danger' : 
                                                         ($activity['status'] === 'pending' ? 'warning' : 'success'); 
                                                ?>">
                                                    <?php echo htmlspecialchars(ucfirst($activity['status'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span style="color: var(--text-secondary);">
                                                    <?php echo htmlspecialchars($activity['location'] ?? 'N/A'); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="text-muted">
                                                    <?php echo date('M j, H:i:s', strtotime($activity['activity_time'])); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" style="text-align: center; padding: 3rem; color: var(--text-tertiary);">
                                                No recent activities found
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
                            <button class="btn btn-secondary" onclick="refreshData()">
                                🔄 Refresh Data
                            </button>
                        </div>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Activity Trends Chart
        const trendsCtx = document.getElementById('activityTrendsChart').getContext('2d');
        const activityLabels = <?php echo json_encode(array_map(function($date) { 
            return date('M j', strtotime($date)); 
        }, array_column($trends_data ?? [], 'date'))); ?>;
        const activityData = <?php echo json_encode($trends_data ?? []); ?>;
        
        if (activityLabels.length > 0 && activityData.length > 0) {
            new Chart(trendsCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_map(function($date) { 
                    return date('M j', strtotime($date)); 
                }, array_column($trends_data ?? [], 'date'))); ?>,
                datasets: [{
                    label: 'Total Incidents',
                    data: <?php echo json_encode(array_column($trends_data ?? [], 'total_incidents')); ?>,
                    borderColor: '#ef4444',
                    backgroundColor: 'rgba(239, 68, 68, 0.1)',
                    tension: 0.4
                }, {
                    label: 'Active Responders',
                    data: <?php echo json_encode(array_column($trends_data ?? [], 'active_responders')); ?>,
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.4
                }, {
                    label: 'Active Rescuers',
                    data: <?php echo json_encode(array_column($trends_data ?? [], 'active_rescuers')); ?>,
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
        } else {
            trendsCtx.canvas.parentNode.innerHTML = '<div style="text-align: center; padding: 3rem; color: var(--text-tertiary);">No activity trends data available for the selected period</div>';
        }

        // Export Functions
        function exportToCSV() {
            alert('CSV export functionality would be implemented here');
        }

        function exportToPDF() {
            alert('PDF export functionality would be implemented here');
        }

        function refreshData() {
            alert('Data refresh functionality would be implemented here');
        }
    </script>
</body>
</html>
