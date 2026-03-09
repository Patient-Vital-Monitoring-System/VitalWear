<?php
session_start();
require_once '../../../database/connection.php';

// Check if admin user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /VitalWear-1/login.html');
    exit();
}

$conn = getDBConnection();

// Get incident data
$incident_data = [];
$error_message = '';

try {
    // Get incident trends (last 6 months)
    $incident_trends = $conn->query("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as total_incidents,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
        FROM incident 
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month
    ");
    
    // Get incident response times
    $response_times = $conn->query("
        SELECT 
            i.incident_id,
            i.created_at,
            i.updated_at as resolved_at,
            TIMESTAMPDIFF(HOUR, i.created_at, COALESCE(i.updated_at, NOW())) as response_hours,
            i.status,
            i.priority
        FROM incident i
        WHERE i.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ORDER BY i.created_at DESC
        LIMIT 50
    ");
    
    // Get incident by priority
    $priority_stats = $conn->query("
        SELECT priority, COUNT(*) as count,
               AVG(TIMESTAMPDIFF(HOUR, created_at, COALESCE(updated_at, NOW()))) as avg_response_time
        FROM incident 
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY priority
        ORDER BY FIELD(priority, 'critical', 'high', 'medium', 'low')
    ");
    
    if ($incident_trends) $incident_data['trends'] = $incident_trends->fetch_all(MYSQLI_ASSOC);
    if ($response_times) $incident_data['response_times'] = $response_times->fetch_all(MYSQLI_ASSOC);
    if ($priority_stats) $incident_data['priority_stats'] = $priority_stats->fetch_all(MYSQLI_ASSOC);
    
} catch (Exception $e) {
    $error_message = "Error fetching incident data: " . $e->getMessage();
}

// Calculate summary statistics
$total_incidents = 0;
$avg_response_time = 0;
if (!empty($incident_data['trends'])) {
    foreach ($incident_data['trends'] as $trend) {
        $total_incidents += $trend['total_incidents'];
    }
}
if (!empty($incident_data['priority_stats'])) {
    $total_time = 0;
    $count = 0;
    foreach ($incident_data['priority_stats'] as $stat) {
        if ($stat['avg_response_time']) {
            $total_time += $stat['avg_response_time'];
            $count++;
        }
    }
    $avg_response_time = $count > 0 ? round($total_time / $count, 1) : 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Incident Analysis - VitalWear</title>
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
                    <h1><i class="fa fa-chart-line"></i> Incident Analysis</h1>
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
                <h1><i class="fa fa-chart-line"></i> Incident Analysis</h1>
                <p>Detailed incident trends and response times</p>
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

                <!-- Summary Statistics -->
                <div class="metrics-grid">
                    <div class="metric-card">
                        <div class="metric-icon">📊</div>
                        <div class="metric-value"><?php echo number_format($total_incidents); ?></div>
                        <div class="metric-label">Total Incidents (6 months)</div>
                    </div>
                    
                    <div class="metric-card">
                        <div class="metric-icon">⏱️</div>
                        <div class="metric-value"><?php echo $avg_response_time; ?>h</div>
                        <div class="metric-label">Avg Response Time</div>
                    </div>
                    
                    <div class="metric-card">
                        <div class="metric-icon">✅</div>
                        <div class="metric-value"><?php 
                            $completed = array_sum(array_column($incident_data['trends'] ?? [], 'completed'));
                            echo number_format($completed); 
                        ?></div>
                        <div class="metric-label">Completed Incidents</div>
                    </div>
                </div>

                <!-- Incident Trends Chart -->
                <div class="card">
                    <div class="card-header">
                        Incident Trends (Last 6 Months)
                    </div>
                    <div class="card-body">
                        <div style="height: 300px; position: relative;">
                            <canvas id="incidentTrendsChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Response Time by Priority -->
                <div class="card">
                    <div class="card-header">
                        Response Time by Priority
                    </div>
                    <div class="card-body">
                        <div style="height: 300px; position: relative;">
                            <canvas id="priorityChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Recent Incidents -->
                <div class="card">
                    <div class="card-header">
                        Recent Incidents & Response Times
                    </div>
                    <div class="card-body">
                        <div class="table" style="overflow-x: auto;">
                            <table style="min-width: 800px;">
                                <thead>
                                    <tr>
                                        <th>Incident ID</th>
                                        <th>Created</th>
                                        <th>Resolved</th>
                                        <th>Response Time</th>
                                        <th>Status</th>
                                        <th>Priority</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($incident_data['response_times'])): ?>
                                        <?php foreach ($incident_data['response_times'] as $incident): ?>
                                        <tr>
                                            <td>
                                                <strong>#<?php echo htmlspecialchars($incident['incident_id']); ?></strong>
                                            </td>
                                            <td>
                                                <?php echo date('M j, H:i', strtotime($incident['created_at'])); ?>
                                            </td>
                                            <td>
                                                <?php 
                                                if ($incident['resolved_at'] && $incident['resolved_at'] !== $incident['created_at']) {
                                                    echo date('M j, H:i', strtotime($incident['resolved_at']));
                                                } else {
                                                    echo '<span style="color: var(--text-tertiary);">Pending</span>';
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <span style="font-weight: 600; color: <?php 
                                                    echo $incident['response_hours'] > 24 ? 'var(--error)' : 
                                                         ($incident['response_hours'] > 12 ? 'var(--warning)' : 'var(--success)'); 
                                                ?>;">
                                                    <?php echo $incident['response_hours']; ?>h
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?php echo $incident['status'] === 'completed' ? 'success' : 'warning'; ?>">
                                                    <?php echo htmlspecialchars(ucfirst($incident['status'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?php 
                                                    echo $incident['priority'] === 'critical' ? 'danger' : 
                                                         ($incident['priority'] === 'high' ? 'warning' : 'success'); 
                                                ?>">
                                                    <?php echo htmlspecialchars(ucfirst($incident['priority'])); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" style="text-align: center; padding: 3rem; color: var(--text-tertiary);">
                                                No incident data available
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
        // Incident Trends Chart
        const trendsCtx = document.getElementById('incidentTrendsChart').getContext('2d');
        new Chart(trendsCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_map(function($month) { 
                    return date('M Y', strtotime($month . '-01')); 
                }, array_column($incident_data['trends'] ?? [], 'month'))); ?>,
                datasets: [{
                    label: 'Total Incidents',
                    data: <?php echo json_encode(array_column($incident_data['trends'] ?? [], 'total_incidents')); ?>,
                    borderColor: '#ef4444',
                    backgroundColor: 'rgba(239, 68, 68, 0.1)',
                    tension: 0.4
                }, {
                    label: 'Completed',
                    data: <?php echo json_encode(array_column($incident_data['trends'] ?? [], 'completed')); ?>,
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    tension: 0.4
                }, {
                    label: 'Pending',
                    data: <?php echo json_encode(array_column($incident_data['trends'] ?? [], 'pending')); ?>,
                    borderColor: '#f59e0b',
                    backgroundColor: 'rgba(245, 158, 11, 0.1)',
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

        // Priority Response Time Chart
        const priorityCtx = document.getElementById('priorityChart').getContext('2d');
        new Chart(priorityCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_map('ucfirst', array_column($incident_data['priority_stats'] ?? [], 'priority'))); ?>,
                datasets: [{
                    label: 'Average Response Time (Hours)',
                    data: <?php echo json_encode(array_column($incident_data['priority_stats'] ?? [], 'avg_response_time')); ?>,
                    backgroundColor: [
                        '#ef4444',
                        '#f59e0b',
                        '#10b981',
                        '#3b82f6'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Hours'
                        }
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
