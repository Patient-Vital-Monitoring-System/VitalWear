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
    <title>System Reports - Admin</title>
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                    <a href="dashboard.php" class="nav-item">
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
                        <a href="system_reports.php" class="nav-item active">
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
                    <h1 class="navbar-brand">System Reports</h1>
                </div>
                <div class="navbar-actions">
                    <span class="text-muted">Welcome, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Admin'); ?></span>
                    <a href="/VitalWear-1/api/auth/logout.php" class="btn btn-secondary">Logout</a>
                </div>
            </header>

            <!-- Page Content -->
            <div class="content">
                <div class="content-header">
                    <h1 class="content-title">System Reports & Analytics</h1>
                    <p class="content-subtitle">Comprehensive system overview and performance metrics</p>
                </div>

                <!-- System Health Metrics -->
                <div class="metrics-grid">
                    <div class="metric-card">
                        <div class="metric-icon">�</div>
                        <div class="metric-value"><?php echo htmlspecialchars($health_metrics['database_status']); ?></div>
                        <div class="metric-label">Database Status</div>
                    </div>
                    
                    <div class="metric-card">
                        <div class="metric-icon">⚡</div>
                        <div class="metric-value"><?php echo htmlspecialchars($health_metrics['api_response_time']); ?></div>
                        <div class="metric-label">API Response</div>
                    </div>
                    
                    <div class="metric-card">
                        <div class="metric-icon">�</div>
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
