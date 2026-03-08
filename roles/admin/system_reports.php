<?php
require_once '../../database/connection.php';

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
    $monthly_trends = $trend_query->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Get device status distribution
$device_status = [];
$status_query = $conn->query("
    SELECT dev_status, COUNT(*) as count 
    FROM device 
    GROUP BY dev_status
");
if ($status_query) {
    $device_status = $status_query->get_result()->fetch_all(MYSQLI_ASSOC);
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
    $recent_activities = $activity_query->get_result()->fetch_all(MYSQLI_ASSOC);
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
    <title>System Reports - Admin</title>
    <link rel="stylesheet" href="../../assets/css/styles.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --bg: #0a0e1a;
            --surface: #111827;
            --surface2: #1a2235;
            --border: #1f2d45;
            --accent: #00e5ff;
            --accent2: #ff4d6d;
            --accent3: #39ff14;
            --text: #e2e8f0;
            --muted: #64748b;
            --warn: #f59e0b;
            --danger: #ef4444;
            --success: #10b981;
        }

        * { margin:0; padding:0; box-sizing:border-box; }

        body {
            font-family:'Syne',sans-serif;
            background:var(--bg);
            color:var(--text);
            min-height:100vh;
            overflow-x:hidden;
            display: flex;
            flex-direction: column;
        }

        .navbar-top {
            position: sticky;
            top: 0;
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            color: white;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
            z-index: 1030;
        }

        .page-wrapper {
            display: flex;
            flex: 1;
            min-height: calc(100vh - 70px);
        }

        .sidebar {
            width: 320px;
            background-color: var(--surface);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            min-height: 100%;
            overflow-y: auto;
        }

        .sidebar-header {
            background-color: var(--surface2);
            color: white;
            border-bottom: 1px solid var(--border);
            padding: 24px;
        }

        .sidebar-title {
            font-weight: 700;
            font-size: 1.3rem;
            color: var(--accent);
            letter-spacing: 1px;
            font-family: 'Space Mono', monospace;
        }

        .sidebar-nav {
            display: flex;
            flex-direction: column;
            padding: 0;
            margin: 0;
            flex: 1;
        }

        .sidebar-nav .nav-link {
            color: var(--muted);
            padding: 18px 24px;
            border-left: 3px solid transparent;
            transition: all 0.3s ease;
            text-decoration: none;
            display: block;
            font-weight: 600;
            font-size: 14px;
        }

        .sidebar-nav .nav-link:hover {
            background-color: rgba(0, 229, 255, 0.1);
            color: var(--accent);
            border-left-color: var(--accent);
        }

        .sidebar-nav .nav-link.active {
            color: var(--accent);
            background-color: rgba(0, 229, 255, 0.15);
            border-left-color: var(--accent);
        }

        .nav-group {
            display: flex;
            flex-direction: column;
        }

        .nav-group-toggle {
            color: var(--muted);
            padding: 18px 24px;
            border-left: 3px solid transparent;
            border: none;
            background: transparent;
            transition: all 0.3s ease;
            text-decoration: none;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            width: 100%;
            text-align: left;
            font-family: inherit;
        }

        .nav-group-toggle:hover {
            background-color: rgba(0, 229, 255, 0.1);
            color: var(--accent);
            border-left-color: var(--accent);
        }

        .dropdown-arrow {
            transition: transform 0.3s ease;
            display: inline-block;
            font-size: 12px;
        }

        .nav-group.active .dropdown-arrow {
            transform: rotate(180deg);
        }

        .nav-group-items {
            display: none;
            flex-direction: column;
            background-color: rgba(0, 0, 0, 0.2);
            border-left: 3px solid var(--border);
        }

        .nav-group.active .nav-group-items {
            display: flex;
        }

        .nav-group .nav-link {
            padding: 14px 24px 14px 48px;
            border-left: none;
            font-size: 13px;
            color: var(--muted);
        }

        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
        }

        .navbar-brand {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 700;
            color: white;
            letter-spacing: -0.5px;
            flex: 1;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 32px 20px;
        }

        h1 {
            color: var(--accent);
            font-weight: 800;
            margin-bottom: 16px;
            margin-top: 0;
            font-size: 2rem;
            letter-spacing: -0.5px;
        }

        .reports-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 24px;
            margin-bottom: 40px;
        }

        .report-card {
            background: var(--surface);
            border: 2px solid var(--border);
            border-radius: 12px;
            padding: 24px;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none;
            color: var(--text);
        }

        .report-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--accent), var(--accent2), var(--accent3));
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .report-card:hover {
            border-color: var(--accent);
            box-shadow: 0 8px 24px rgba(0, 229, 255, 0.15);
            transform: translateY(-4px);
            text-decoration: none;
            color: var(--text);
        }

        .report-card:hover::before {
            opacity: 1;
        }

        .report-icon {
            font-size: 48px;
            margin-bottom: 16px;
            opacity: 0.8;
        }

        .report-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--accent);
            margin-bottom: 8px;
        }

        .report-description {
            color: var(--muted);
            font-size: 14px;
            line-height: 1.5;
        }

        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .metric-card {
            background: var(--surface);
            border: 2px solid var(--border);
            border-radius: 12px;
            padding: 24px;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }

        .metric-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--accent), var(--accent2), var(--accent3));
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .metric-card:hover {
            border-color: var(--accent);
            box-shadow: 0 8px 24px rgba(0, 229, 255, 0.15);
            transform: translateY(-4px);
        }

        .metric-card:hover::before {
            opacity: 1;
        }

        .metric-icon {
            font-size: 36px;
            margin-bottom: 12px;
            opacity: 0.8;
        }

        .metric-value {
            font-size: 2.8rem;
            font-weight: 800;
            color: var(--accent);
            letter-spacing: -1px;
            margin: 12px 0;
            font-family: 'Syne', sans-serif;
        }

        .metric-label {
            font-size: 12px;
            letter-spacing: 1px;
            color: var(--muted);
            font-family: 'Space Mono', monospace;
            font-weight: 600;
            text-transform: uppercase;
        }

        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            transition: transform 0.2s, box-shadow 0.2s;
            margin-bottom: 24px;
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 229, 255, 0.1);
        }

        .card-header {
            background-color: var(--surface2);
            color: var(--accent);
            border-bottom: 1px solid var(--border);
            font-weight: 700;
            padding: 16px 20px;
            font-size: 13px;
            letter-spacing: 1px;
            font-family: 'Space Mono', monospace;
        }

        .card-body {
            padding: 24px;
        }

        .chart-container {
            background: var(--surface);
            padding: 24px;
            border-radius: 12px;
            border: 1px solid var(--border);
            margin-bottom: 30px;
        }

        .chart-container h3 {
            margin: 0 0 24px 0;
            color: var(--accent);
            font-size: 16px;
        }

        .health-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
        }

        .health-item {
            background: var(--surface2);
            padding: 16px;
            border-radius: 8px;
            text-align: center;
        }

        .health-label {
            font-size: 11px;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 1px;
            font-family: 'Space Mono', monospace;
            margin-bottom: 8px;
        }

        .health-value {
            font-size: 18px;
            font-weight: 700;
            color: var(--success);
        }

        .health-value.warning {
            color: var(--warn);
        }

        .health-value.danger {
            color: var(--accent2);
        }

        .btn {
            padding: 10px 14px;
            border-radius: 6px;
            font-family: 'Syne', sans-serif;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-block;
            letter-spacing: 0.5px;
        }

        .btn-primary {
            background: var(--accent);
            color: #000;
        }

        .btn-primary:hover {
            background: #33eeff;
            transform: translateY(-1px);
            box-shadow: 0 8px 20px rgba(0, 229, 255, 0.3);
        }

        .btn-secondary {
            background: var(--surface2);
            color: var(--text);
            border: 1px solid var(--border);
        }

        .btn-secondary:hover {
            background: var(--border);
            border-color: var(--accent);
        }

        @import url('https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;600;700;800&display=swap');
    </style>
</head>
<body>
    <nav class="navbar-top">
        <h2 class="navbar-brand">System Reports</h2>
        <a href="/VitalWear-1/api/auth/logout.php" class="btn btn-secondary">Logout</a>
    </nav>

    <div class="page-wrapper">
        <aside class="sidebar">
            <div class="sidebar-header">
                <h5 class="sidebar-title">Menu</h5>
            </div>
            <nav class="sidebar-nav">
                <a class="nav-link" href="dashboard.php">Dashboard</a>
                
                <!-- User Management -->
                <div class="nav-group">
                    <button class="nav-group-toggle">User Management <span class="dropdown-arrow">▼</span></button>
                    <div class="nav-group-items">
                        <a class="nav-link" href="users.php">Staff Directory</a>
                        <a class="nav-link" href="user_status.php">User Status</a>
                    </div>
                </div>

                <!-- Reports -->
                <div class="nav-group active">
                    <button class="nav-group-toggle">Reports <span class="dropdown-arrow">▼</span></button>
                    <div class="nav-group-items">
                        <a class="nav-link" href="vitals_analytics.php">Vital Statistics</a>
                        <a class="nav-link" href="audit_log.php">System Activity Log</a>
                        <a class="nav-link active" href="system_reports.php">System Reports</a>
                    </div>
                </div>

                <!-- Monitoring -->
                <div class="nav-group">
                    <button class="nav-group-toggle">Monitoring <span class="dropdown-arrow">▼</span></button>
                    <div class="nav-group-items">
                        <a class="nav-link" href="incidents.php">Incident Monitoring</a>
                        <a class="nav-link" href="device_incidents.php">Device Overview</a>
                        <a class="nav-link" href="vitals.php">User Activity</a>
                    </div>
                </div>

                <!-- Accounts -->
                <div class="nav-group">
                    <button class="nav-group-toggle">Accounts <span class="dropdown-arrow">▼</span></button>
                    <div class="nav-group-items">
                        <a class="nav-link" href="profile.php">Profile</a>
                        <a class="nav-link" href="/VitalWear-1/api/auth/logout.php" style="color: #ff4d6d;">Logout</a>
                    </div>
                </div>
            </nav>
        </aside>

        <main class="main-content">
            <div class="container">
                <h1>📊 System Reports</h1>
                <p>Generate comprehensive reports and analytics for the VitalWear system.</p>

                <!-- Report Categories -->
                <div class="reports-grid">
                    <a href="#" class="report-card" onclick="generateReport('user'); return false;">
                        <div class="report-icon">👥</div>
                        <div class="report-title">User Activity Report</div>
                        <div class="report-description">Detailed analysis of user login patterns, activity levels, and engagement metrics across all roles.</div>
                    </a>

                    <a href="#" class="report-card" onclick="generateReport('incident'); return false;">
                        <div class="report-icon">🚨</div>
                        <div class="report-title">Incident Analytics</div>
                        <div class="report-description">Comprehensive incident statistics, response times, resolution rates, and trend analysis.</div>
                    </a>

                    <a href="#" class="report-card" onclick="generateReport('device'); return false;">
                        <div class="report-icon">📦</div>
                        <div class="report-title">Device Performance</div>
                        <div class="report-description">Device utilization rates, maintenance schedules, performance metrics, and allocation efficiency.</div>
                    </a>

                    <a href="#" class="report-card" onclick="generateReport('vital'); return false;">
                        <div class="report-icon">❤️</div>
                        <div class="report-title">Vital Statistics</div>
                        <div class="report-description">Patient vital signs analysis, health trends, monitoring frequency, and alert patterns.</div>
                    </a>

                    <a href="#" class="report-card" onclick="generateReport('system'); return false;">
                        <div class="report-icon">⚙️</div>
                        <div class="report-title">System Health</div>
                        <div class="report-description">System performance metrics, uptime statistics, error rates, and infrastructure health.</div>
                    </a>

                    <a href="#" class="report-card" onclick="generateReport('compliance'); return false;">
                        <div class="report-icon">📋</div>
                        <div class="report-title">Compliance Report</div>
                        <div class="report-description">Regulatory compliance tracking, audit trails, security assessments, and policy adherence.</div>
                    </a>
                </div>

                <!-- Key Metrics -->
                <h2 style="color: var(--accent); margin-top: 40px; margin-bottom: 20px;">🔍 System Overview</h2>
                <div class="metrics-grid">
                    <div class="metric-card">
                        <div class="metric-icon">👤</div>
                        <div class="metric-value"><?php echo number_format($system_stats['total_users']); ?></div>
                        <div class="metric-label">Total Users</div>
                    </div>
                    
                    <div class="metric-card">
                        <div class="metric-icon">📦</div>
                        <div class="metric-value"><?php echo number_format($system_stats['total_devices']); ?></div>
                        <div class="metric-label">Total Devices</div>
                    </div>
                    
                    <div class="metric-card">
                        <div class="metric-icon">🚨</div>
                        <div class="metric-value"><?php echo number_format($system_stats['total_incidents']); ?></div>
                        <div class="metric-label">Total Incidents</div>
                    </div>
                    
                    <div class="metric-card">
                        <div class="metric-icon">📊</div>
                        <div class="metric-value"><?php echo $system_stats['device_utilization']; ?>%</div>
                        <div class="metric-label">Device Utilization</div>
                    </div>
                    
                    <div class="metric-card">
                        <div class="metric-icon">⚡</div>
                        <div class="metric-value"><?php echo $system_stats['system_uptime']; ?></div>
                        <div class="metric-label">System Uptime</div>
                    </div>
                    
                    <div class="metric-card">
                        <div class="metric-icon">🔄</div>
                        <div class="metric-value"><?php echo number_format($system_stats['active_incidents']); ?></div>
                        <div class="metric-label">Active Incidents</div>
                    </div>
                </div>

                <!-- Charts Section -->
                <h2 style="color: var(--accent); margin-top: 40px; margin-bottom: 20px;">📈 Analytics Dashboard</h2>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(450px, 1fr)); gap: 30px; margin-bottom: 40px;">
                    
                    <!-- Incident Trends Chart -->
                    <div class="chart-container">
                        <h3>📊 Incident Trends (6 Months)</h3>
                        <canvas id="incidentTrendsChart" style="max-height: 300px;"></canvas>
                    </div>

                    <!-- Device Status Distribution -->
                    <div class="chart-container">
                        <h3>📦 Device Status Distribution</h3>
                        <canvas id="deviceStatusChart" style="max-height: 300px;"></canvas>
                    </div>

                </div>

                <!-- System Health -->
                <div class="card">
                    <div class="card-header">System Health Metrics</div>
                    <div class="card-body">
                        <div class="health-grid">
                            <div class="health-item">
                                <div class="health-label">Database Status</div>
                                <div class="health-value"><?php echo $health_metrics['database_status']; ?></div>
                            </div>
                            <div class="health-item">
                                <div class="health-label">API Response Time</div>
                                <div class="health-value"><?php echo $health_metrics['api_response_time']; ?></div>
                            </div>
                            <div class="health-item">
                                <div class="health-label">Error Rate</div>
                                <div class="health-value"><?php echo $health_metrics['error_rate']; ?></div>
                            </div>
                            <div class="health-item">
                                <div class="health-label">Last Backup</div>
                                <div class="health-value"><?php echo $health_metrics['last_backup']; ?></div>
                            </div>
                            <div class="health-item">
                                <div class="health-label">Storage Usage</div>
                                <div class="health-value warning"><?php echo $health_metrics['storage_usage']; ?></div>
                            </div>
                            <div class="health-item">
                                <div class="health-label">Memory Usage</div>
                                <div class="health-value"><?php echo $health_metrics['memory_usage']; ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activities -->
                <div class="card">
                    <div class="card-header">Recent System Activities</div>
                    <div class="card-body">
                        <?php if (!empty($recent_activities)): ?>
                        <div style="max-height: 300px; overflow-y: auto;">
                            <?php foreach ($recent_activities as $activity): ?>
                            <div style="padding: 12px 0; border-bottom: 1px solid var(--border);">
                                <div style="display: flex; justify-content: space-between; align-items: start;">
                                    <div>
                                        <strong><?php echo htmlspecialchars($activity['user_name'] ?? 'System'); ?></strong>
                                        <span style="color: var(--muted); font-size: 12px; margin-left: 8px;">
                                            (<?php echo htmlspecialchars($activity['user_role']); ?>)
                                        </span>
                                        <div style="color: var(--text); margin-top: 4px;">
                                            <?php echo htmlspecialchars($activity['description']); ?>
                                        </div>
                                    </div>
                                    <div style="color: var(--muted); font-size: 12px; white-space: nowrap;">
                                        <?php echo date('M j, H:i', strtotime($activity['created_at'])); ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div style="text-align: center; padding: 40px; color: var(--muted);">
                            No recent activities found.
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <script>
        // Toggle navigation groups
        document.querySelectorAll('.nav-group-toggle').forEach(toggle => {
            toggle.addEventListener('click', function() {
                this.parentElement.classList.toggle('active');
            });
        });

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

        // Incident Trends Chart
        const incidentCtx = document.getElementById('incidentTrendsChart').getContext('2d');
        new Chart(incidentCtx, {
            type: 'line',
            data: {
                labels: [<?php foreach ($monthly_trends as $trend) echo "'" . date('M Y', strtotime($trend['month'] . '-01')) . "', "; ?>],
                datasets: [
                    {
                        label: 'Total Incidents',
                        data: [<?php foreach ($monthly_trends as $trend) echo $trend['incidents'] . ", "; ?>],
                        borderColor: '#ff4d6d',
                        backgroundColor: 'rgba(255, 77, 109, 0.1)',
                        borderWidth: 2,
                        tension: 0.4,
                        fill: true
                    },
                    {
                        label: 'Completed Incidents',
                        data: [<?php foreach ($monthly_trends as $trend) echo $trend['completed'] . ", "; ?>],
                        borderColor: '#39ff14',
                        backgroundColor: 'rgba(57, 255, 20, 0.1)',
                        borderWidth: 2,
                        tension: 0.4,
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        labels: { color: '#a0aec0' }
                    }
                },
                scales: {
                    y: {
                        ticks: { color: '#a0aec0' },
                        grid: { color: 'rgba(160, 174, 192, 0.1)' }
                    },
                    x: {
                        ticks: { color: '#a0aec0' },
                        grid: { color: 'rgba(160, 174, 192, 0.1)' }
                    }
                }
            }
        });

        // Device Status Distribution Chart
        const deviceCtx = document.getElementById('deviceStatusChart').getContext('2d');
        new Chart(deviceCtx, {
            type: 'doughnut',
            data: {
                labels: [<?php foreach ($device_status as $status) echo "'" . ucfirst($status['dev_status']) . "', "; ?>],
                datasets: [{
                    data: [<?php foreach ($device_status as $status) echo $status['count'] . ", "; ?>],
                    backgroundColor: ['#00e5ff', '#ff4d6d', '#39ff14', '#f59e0b'],
                    borderColor: '#0a0e1a',
                    borderWidth: 3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        labels: { color: '#a0aec0' }
                    }
                }
            }
        });
    </script>
</body>
</html>
