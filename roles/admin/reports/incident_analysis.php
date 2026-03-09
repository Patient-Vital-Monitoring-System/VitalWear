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
    <title>Incident Analysis Report - Admin</title>
    <link rel="stylesheet" href="../../../assets/css/styles.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://kit.fontawesome.com/96e37b53f1.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --authority-blue: #1B3F72;
            --dashboard-light: #F4F7FC;
            --pure-white: #FFFFFF;
            --secondary-text: #7E91B3;
            --interface-border: #D1E0F1;
            --radius: 12px;
            --shadow: 0 4px 12px rgba(27, 63, 114, 0.08);
        }
        
        body {
            background-color: var(--dashboard-light);
            color: var(--authority-blue);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            margin: 0;
            padding: 0;
        }
        
        #sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 260px;
            height: 100vh;
            background: var(--pure-white);
            border-right: 1px solid var(--interface-border);
            box-shadow: var(--shadow);
            z-index: 1000;
            overflow-y: auto;
        }
        
        .sidebar-logo {
            padding: 24px 20px;
            text-align: center;
            background: linear-gradient(135deg, var(--authority-blue) 0%, #2a5298 100%);
            margin: 12px;
            border-radius: var(--radius);
        }
        
        .sidebar-logo img {
            max-width: 140px;
            height: auto;
            filter: brightness(0) invert(1);
        }
        
        #sidebar a {
            color: var(--authority-blue);
            margin: 6px 12px;
            padding: 12px 16px;
            border-radius: var(--radius);
            transition: all 0.2s ease;
            border: none;
            font-weight: 500;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        #sidebar a:hover {
            background: rgba(27, 63, 114, 0.1);
            color: var(--authority-blue);
            transform: translateX(4px);
        }
        
        #sidebar a.active {
            background: rgba(27, 63, 114, 0.15);
            color: var(--authority-blue);
        }
        
        .topbar {
            position: fixed;
            top: 0;
            left: 260px;
            right: 0;
            background: var(--pure-white);
            color: var(--authority-blue);
            border-bottom: 1px solid var(--interface-border);
            padding: 16px 24px;
            font-weight: 600;
            z-index: 999;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            margin-left: 260px;
            margin-top: 80px;
        }
        
        .page-header {
            background: linear-gradient(135deg, var(--authority-blue) 0%, #2a5298 100%);
            color: white;
            padding: 30px 40px;
            border-radius: var(--radius);
            margin-bottom: 30px;
            box-shadow: var(--shadow);
        }
        
        .page-header h1 {
            color: white;
            margin: 0 0 8px 0;
            font-size: 1.8rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .page-header p {
            margin: 0;
            opacity: 0.9;
            color: white;
        }
        
        .stat-card {
            background: var(--pure-white);
            padding: 24px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--interface-border);
            text-align: center;
        }
        
        .stat-card h3 {
            margin: 0 0 12px 0;
            color: var(--secondary-text);
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--authority-blue);
            margin: 0;
        }
        
        .card {
            background: var(--pure-white);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--interface-border);
            margin-bottom: 24px;
        }
        
        .card-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--interface-border);
            font-weight: 600;
            color: var(--authority-blue);
        }
        
        .card-body {
            padding: 20px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: var(--radius);
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--authority-blue) 0%, #2a5298 100%);
            color: white;
        }
        
        .btn-secondary {
            background: var(--pure-white);
            color: var(--authority-blue);
            border: 1px solid var(--interface-border);
        }
        
        .btn-secondary:hover {
            background: var(--dashboard-light);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 12px 16px;
            text-align: left;
            border-bottom: 1px solid var(--interface-border);
        }
        
        th {
            background: var(--dashboard-light);
            font-weight: 600;
            color: var(--authority-blue);
        }
        
        tr:hover {
            background: var(--dashboard-light);
        }
        
        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-success {
            background: rgba(44, 201, 144, 0.15);
            color: #2CC990;
        }
        
        .badge-warning {
            background: rgba(255, 193, 7, 0.15);
            color: #d39e00;
        }
        
        .badge-danger {
            background: rgba(220, 53, 69, 0.15);
            color: #DC3545;
        }
    </style>
</head>
<body>
    <!-- Topbar -->
    <header class="topbar">
        <div style="display: flex; align-items: center; gap: 12px;">
            <i class="fa fa-shield-alt" style="font-size: 24px; color: var(--authority-blue);"></i>
            <span style="font-size: 18px; font-weight: 700;">VitalWear Admin</span>
        </div>
        <div style="display: flex; align-items: center; gap: 16px;">
            <span style="color: var(--secondary-text);">Welcome, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Admin'); ?></span>
            <a href="/VitalWear-1/api/auth/logout.php" class="btn btn-secondary">Logout</a>
        </div>
    </header>

    <!-- Sidebar -->
    <nav id="sidebar">
        <div class="sidebar-logo">
            <img src="../../../assets/logo.png" alt="VitalWear Logo">
        </div>
        <a href="../dashboard.php"><i class="fa fa-gauge"></i> Dashboard</a>
        <a href="../users.php"><i class="fa fa-users"></i> Staff Directory</a>
        <a href="../system_reports.php"><i class="fa fa-chart-bar"></i> System Reports</a>
        <a href="incident_analysis.php" class="active"><i class="fa fa-chart-line"></i> Incident Analysis</a>
        <a href="device_performance.php"><i class="fa fa-mobile-alt"></i> Device Performance</a>
        <a href="user_activity_report.php"><i class="fa fa-user-chart"></i> User Activity</a>
        <a href="security_audit.php"><i class="fa fa-shield-alt"></i> Security Audit</a>
        <a href="../vitals_analytics.php"><i class="fa fa-heartbeat"></i> Vital Analytics</a>
        <a href="../audit_log.php"><i class="fa fa-clipboard-list"></i> Activity Log</a>
        <a href="../../../api/auth/logout.php" class="btn btn-secondary" style="margin: 12px;">Logout</a>
    </nav>

    <!-- Main Content -->
    <main class="container">
        <div class="page-header">
            <h1><i class="fa fa-chart-line"></i> Incident Analysis</h1>
            <p>Detailed incident trends and response times</p>
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
                                                    echo '<span style="color: var(--muted);">Pending</span>';
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <span style="font-weight: 600; color: <?php 
                                                    echo $incident['response_hours'] > 24 ? 'var(--danger)' : 
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
                                            <td colspan="6" style="text-align: center; padding: 3rem; color: var(--muted);">
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
                <div class="card" style="margin-top: 2rem;">
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
