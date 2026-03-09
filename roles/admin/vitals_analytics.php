<?php
session_start();
require_once '../../database/connection.php';

// Check if admin user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /VitalWear-1/login.html');
    exit();
}

$conn = getDBConnection();

// Get vital statistics data
$vital_data = [];
$error_message = '';

try {
    // Get average BP this month
    $avgBpQuery = $conn->query("
        SELECT 
            AVG(bp_systolic) as avg_systolic,
            AVG(bp_diastolic) as avg_diastolic,
            COUNT(*) as total_readings
        FROM vitalstat 
        WHERE MONTH(recorded_at) = MONTH(CURDATE()) 
        AND YEAR(recorded_at) = YEAR(CURDATE())
    ");
    
    // Get high BP incidents this month
    $highBpQuery = $conn->query("
        SELECT COUNT(*) as high_bp_count
        FROM vitalstat
        WHERE (bp_systolic > 140 OR bp_diastolic > 90)
        AND MONTH(recorded_at) = MONTH(CURDATE())
        AND YEAR(recorded_at) = YEAR(CURDATE())
    ");
    
    // Get peak hour based on vital readings
    $peakHourQuery = $conn->query("
        SELECT 
            HOUR(recorded_at) AS peak_hour,
            COUNT(*) AS total
        FROM vitalstat
        WHERE MONTH(recorded_at) = MONTH(CURDATE())
          AND YEAR(recorded_at) = YEAR(CURDATE())
        GROUP BY HOUR(recorded_at)
        ORDER BY total DESC
        LIMIT 1
    ");
    
    // Get monitoring frequency
    $monitoringFreqQuery = $conn->query("
        SELECT 
            COUNT(*) as total_readings,
            COUNT(DISTINCT DATE(recorded_at)) as days_monitored,
            ROUND(COUNT(*) / COUNT(DISTINCT DATE(recorded_at)), 1) as readings_per_day
        FROM vitalstat
        WHERE recorded_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ");
    
    // Get vital trends (last 7 days)
    $vitalTrends = $conn->query("
        SELECT 
            DATE(recorded_at) as date,
            AVG(bp_systolic) as avg_systolic,
            AVG(bp_diastolic) as avg_diastolic,
            AVG(heart_rate) as avg_heart_rate,
            AVG(body_temp) as avg_temp,
            AVG(oxygen_sat) as avg_oxygen,
            COUNT(*) as readings_count
        FROM vitalstat
        WHERE recorded_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY DATE(recorded_at)
        ORDER BY date
    ");
    
    // Get critical vitals (high/low readings)
    $criticalVitals = $conn->query("
        SELECT 
            resp_name,
            resp_id,
            bp_systolic,
            bp_diastolic,
            heart_rate,
            body_temp,
            oxygen_sat,
            recorded_at,
            CASE 
                WHEN bp_systolic > 140 OR bp_diastolic > 90 THEN 'High BP'
                WHEN bp_systolic < 90 OR bp_diastolic < 60 THEN 'Low BP'
                WHEN heart_rate > 100 OR heart_rate < 60 THEN 'Irregular HR'
                WHEN body_temp > 38 OR body_temp < 36 THEN 'Abnormal Temp'
                WHEN oxygen_sat < 95 THEN 'Low O2'
                ELSE 'Normal'
            END as alert_type
        FROM vitalstat
        WHERE recorded_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        AND (bp_systolic > 140 OR bp_diastolic > 90 OR 
             bp_systolic < 90 OR bp_diastolic < 60 OR
             heart_rate > 100 OR heart_rate < 60 OR
             body_temp > 38 OR body_temp < 36 OR
             oxygen_sat < 95)
        ORDER BY recorded_at DESC
        LIMIT 20
    ");
    
    if ($avgBpQuery) $vital_data['avg_bp'] = $avgBpQuery->fetch_assoc();
    if ($highBpQuery) $vital_data['high_bp'] = $highBpQuery->fetch_assoc();
    if ($peakHourQuery) $vital_data['peak_hour'] = $peakHourQuery->fetch_assoc();
    if ($monitoringFreqQuery) $vital_data['monitoring_freq'] = $monitoringFreqQuery->fetch_assoc();
    if ($vitalTrends) $vital_data['trends'] = $vitalTrends->fetch_all(MYSQLI_ASSOC);
    if ($criticalVitals) $vital_data['critical_vitals'] = $criticalVitals->fetch_all(MYSQLI_ASSOC);
    
} catch (Exception $e) {
    $error_message = "Error fetching vital statistics data: " . $e->getMessage();
}

// Calculate summary statistics
$total_readings = $vital_data['avg_bp']['total_readings'] ?? 0;
$high_bp_count = $vital_data['high_bp']['high_bp_count'] ?? 0;
$avg_systolic = round($vital_data['avg_bp']['avg_systolic'] ?? 0);
$avg_diastolic = round($vital_data['avg_bp']['avg_diastolic'] ?? 0);
$peak_hour = $vital_data['peak_hour']['peak_hour'] ?? 0;
$readings_per_day = $vital_data['monitoring_freq']['readings_per_day'] ?? 0;
$critical_count = count($vital_data['critical_vitals'] ?? []);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vital Analytics - VitalWear</title>
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
                        <a href="vitals_analytics.php" class="nav-item active">
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
            <!-- Top Navigation -->
            <header class="navbar">
                <div>
                    <h1 class="navbar-brand"><i class="fa fa-heartbeat"></i> Vital Analytics</h1>
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
                    <h1 class="content-title"><i class="fa fa-heartbeat"></i> Vital Statistics Analytics</h1>
                    <p class="content-subtitle">Comprehensive vital signs monitoring and analysis</p>
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

                <!-- Vital Statistics Overview -->
                <div class="metrics-grid">
                    <div class="metric-card">
                        <div class="metric-icon">❤️</div>
                        <div class="metric-value"><?php echo $avg_systolic . '/' . $avg_diastolic; ?></div>
                        <div class="metric-label">Avg BP (mmHg)</div>
                    </div>
                    
                    <div class="metric-card">
                        <div class="metric-icon">📊</div>
                        <div class="metric-value"><?php echo number_format($total_readings); ?></div>
                        <div class="metric-label">Total Readings</div>
                    </div>
                    
                    <div class="metric-card">
                        <div class="metric-icon">⚠️</div>
                        <div class="metric-value"><?php echo number_format($high_bp_count); ?></div>
                        <div class="metric-label">High BP Incidents</div>
                    </div>
                    
                    <div class="metric-card">
                        <div class="metric-icon">📈</div>
                        <div class="metric-value"><?php echo $readings_per_day; ?></div>
                        <div class="metric-label">Readings/Day</div>
                    </div>
                </div>

                <!-- Peak Activity -->
                <div class="card">
                    <div class="card-header">
                        Peak Monitoring Activity
                    </div>
                    <div class="card-body">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 2rem;">
                            <div style="text-align: center;">
                                <div style="font-size: 2rem; font-weight: 700; color: var(--primary); margin-bottom: 0.5rem;">
                                    <?php echo $peak_hour; ?>:00
                                </div>
                                <div style="color: var(--text-secondary);">Peak Hour</div>
                                <div style="font-size: 0.875rem; color: var(--text-tertiary); margin-top: 0.5rem;">
                                    Most vital readings recorded
                                </div>
                            </div>
                            
                            <div style="text-align: center;">
                                <div style="font-size: 2rem; font-weight: 700; color: var(--success); margin-bottom: 0.5rem;">
                                    <?php echo $vital_data['monitoring_freq']['days_monitored'] ?? 0; ?>
                                </div>
                                <div style="color: var(--text-secondary);">Days Monitored</div>
                                <div style="font-size: 0.875rem; color: var(--text-tertiary); margin-top: 0.5rem;">
                                    Last 30 days
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Vital Trends Chart -->
                <div class="card">
                    <div class="card-header">
                        Vital Trends (Last 7 Days)
                    </div>
                    <div class="card-body">
                        <div style="height: 400px; position: relative;">
                            <canvas id="vitalTrendsChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Critical Vital Alerts -->
                <div class="card">
                    <div class="card-header">
                        Critical Vital Alerts
                        <span style="background: var(--error); color: white; padding: 0.25rem 0.75rem; border-radius: var(--radius-full); font-size: 0.75rem; font-weight: 600; margin-left: 1rem;">
                            <?php echo number_format($critical_count); ?> Alerts
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="table" style="overflow-x: auto;">
                            <table style="min-width: 800px;">
                                <thead>
                                    <tr>
                                        <th>Patient</th>
                                        <th>Blood Pressure</th>
                                        <th>Heart Rate</th>
                                        <th>Temperature</th>
                                        <th>Oxygen</th>
                                        <th>Alert Type</th>
                                        <th>Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($vital_data['critical_vitals'])): ?>
                                        <?php foreach ($vital_data['critical_vitals'] as $vital): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($vital['resp_name']); ?></strong>
                                                <div style="font-size: 0.75rem; color: var(--text-tertiary);">
                                                    ID: #<?php echo htmlspecialchars($vital['resp_id']); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span style="font-weight: 600; color: <?php 
                                                    echo (($vital['bp_systolic'] > 140 || $vital['bp_diastolic'] > 90) ? 'var(--error)' : 
                                                         (($vital['bp_systolic'] < 90 || $vital['bp_diastolic'] < 60) ? 'var(--warning)' : 'var(--success)')); 
                                                ?>;">
                                                    <?php echo $vital['bp_systolic']; ?>/<?php echo $vital['bp_diastolic']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span style="font-weight: 600; color: <?php 
                                                    echo ($vital['heart_rate'] > 100 || $vital['heart_rate'] < 60) ? 'var(--warning)' : 'var(--success)'; 
                                                ?>;">
                                                    <?php echo $vital['heart_rate']; ?> bpm
                                                </span>
                                            </td>
                                            <td>
                                                <span style="font-weight: 600; color: <?php 
                                                    echo ($vital['body_temp'] > 38 || $vital['body_temp'] < 36) ? 'var(--warning)' : 'var(--success)'; 
                                                ?>;">
                                                    <?php echo $vital['body_temp']; ?>°C
                                                </span>
                                            </td>
                                            <td>
                                                <span style="font-weight: 600; color: <?php 
                                                    echo ($vital['oxygen_sat'] < 95) ? 'var(--error)' : 'var(--success)'; 
                                                ?>;">
                                                    <?php echo $vital['oxygen_sat']; ?>%
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?php 
                                                    echo ($vital['alert_type'] === 'High BP' ? 'danger' : 
                                                         ($vital['alert_type'] === 'Low BP' ? 'warning' : 'warning')); 
                                                ?>">
                                                    <?php echo htmlspecialchars($vital['alert_type']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="text-muted">
                                                    <?php echo date('M j, H:i', strtotime($vital['recorded_at'])); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" style="text-align: center; padding: 3rem; color: var(--text-tertiary);">
                                                No critical vital alerts detected
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

    <script>
        // Vital Trends Chart
        const vitalCtx = document.getElementById('vitalTrendsChart').getContext('2d');
        new Chart(vitalCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_map(function($date) { 
                    return date('M j', strtotime($date)); 
                }, array_column($vital_data['trends'] ?? [], 'date'))); ?>,
                datasets: [{
                    label: 'Systolic BP',
                    data: <?php echo json_encode(array_map('round', array_column($vital_data['trends'] ?? [], 'avg_systolic'))); ?>,
                    borderColor: '#ef4444',
                    backgroundColor: 'rgba(239, 68, 68, 0.1)',
                    tension: 0.4
                }, {
                    label: 'Diastolic BP',
                    data: <?php echo json_encode(array_map('round', array_column($vital_data['trends'] ?? [], 'avg_diastolic'))); ?>,
                    borderColor: '#f59e0b',
                    backgroundColor: 'rgba(245, 158, 11, 0.1)',
                    tension: 0.4
                }, {
                    label: 'Heart Rate',
                    data: <?php echo json_encode(array_map('round', array_column($vital_data['trends'] ?? [], 'avg_heart_rate'))); ?>,
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    tension: 0.4
                }, {
                    label: 'Temperature',
                    data: <?php echo json_encode(array_map('round', array_column($vital_data['trends'] ?? [], 'avg_temp'))); ?>,
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.4
                }, {
                    label: 'Oxygen Saturation',
                    data: <?php echo json_encode(array_map('round', array_column($vital_data['trends'] ?? [], 'avg_oxygen'))); ?>,
                    borderColor: '#8b5cf6',
                    backgroundColor: 'rgba(139, 92, 246, 0.1)',
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
