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
    <link rel="stylesheet" href="../../../assets/css/admin.css">
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
                    <a href="../dashboard.php" class="nav-item">
                        🏠 Dashboard
                    </a>
                </div>
                
                <div class="nav-group">
                    <div class="nav-group-title">User Management</div>
                    <div class="nav-group-items">
                        <a href="../users.php" class="nav-item">
                            👥 Staff Directory
                        </a>
                        <a href="view_management.php" class="nav-item">
                            👨‍💼 Management
                        </a>
                        <a href="view_responders.php" class="nav-item">
                            🚑 Responders
                        </a>
                        <a href="view_rescuers.php" class="nav-item">
                            🆘 Rescuers
                        </a>
                        <a href="view_admins.php" class="nav-item">
                            👨‍💻 Admins
                        </a>
                    </div>
                </div>
                
                <div class="nav-group">
                    <div class="nav-group-title">Reports</div>
                    <div class="nav-group-items">
                        <a href="../system_reports.php" class="nav-item">
                            📊 System Reports
                        </a>
                        <a href="../vitals_analytics.php" class="nav-item">
                            ❤️ Vital Analytics
                        </a>
                        <a href="../audit_log.php" class="nav-item">
                            📋 Activity Log
                        </a>
                    </div>
                </div>
                
                <div class="nav-group">
                    <div class="nav-group-title">Monitoring</div>
                    <div class="nav-group-items">
                        <a href="../device_incidents.php" class="nav-item">
                            📦 Device Overview
                        </a>
                        <a href="../vitals.php" class="nav-item">
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
                    <h1 class="navbar-brand">← Back to System Reports</h1>
                </div>
                <div class="navbar-actions">
                    <span class="text-muted">Welcome, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Admin'); ?></span>
                    <a href="/VitalWear-1/api/auth/logout.php" class="btn btn-secondary">Logout</a>
                </div>
            </header>

            <!-- Page Content -->
            <div class="content">
                <div class="content-header">
                    <h1 class="content-title">🚨 Incident Analysis</h1>
                    <p class="content-subtitle">Detailed incident trends and response times</p>
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
