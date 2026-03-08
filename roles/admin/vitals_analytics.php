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
    <title>Vital Analytics - Admin</title>
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
                        <a href="system_reports.php" class="nav-item">
                            📊 System Reports
                        </a>
                        <a href="vitals_analytics.php" class="nav-item active">
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
                    <h1 class="navbar-brand">Vital Analytics</h1>
                </div>
                <div class="navbar-actions">
                    <span class="text-muted">Welcome, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Admin'); ?></span>
                    <a href="/VitalWear-1/api/auth/logout.php" class="btn btn-secondary">Logout</a>
                </div>
            </header>

            <!-- Page Content -->
            <div class="content">
                <div class="content-header">
                    <h1 class="content-title">❤️ Vital Statistics Analytics</h1>
                    <p class="content-subtitle">Comprehensive vital signs monitoring and analysis</p>
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
                                <div style="font-size: 2rem; font-weight: 700; color: var(--accent); margin-bottom: 0.5rem;">
                                    <?php echo $peak_hour; ?>:00
                                </div>
                                <div style="color: var(--text-secondary);">Peak Hour</div>
                                <div style="font-size: 0.875rem; color: var(--muted); margin-top: 0.5rem;">
                                    Most vital readings recorded
                                </div>
                            </div>
                            
                            <div style="text-align: center;">
                                <div style="font-size: 2rem; font-weight: 700; color: var(--success); margin-bottom: 0.5rem;">
                                    <?php echo $vital_data['monitoring_freq']['days_monitored'] ?? 0; ?>
                                </div>
                                <div style="color: var(--text-secondary);">Days Monitored</div>
                                <div style="font-size: 0.875rem; color: var(--muted); margin-top: 0.5rem;">
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
                        <span style="background: var(--danger); color: white; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; margin-left: 1rem;">
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
                                                <div style="font-size: 0.75rem; color: var(--muted);">
                                                    ID: #<?php echo htmlspecialchars($vital['resp_id']); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span style="font-weight: 600; color: <?php 
                                                    echo (($vital['bp_systolic'] > 140 || $vital['bp_diastolic'] > 90) ? 'var(--danger)' : 
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
                                                    echo $vital['oxygen_sat'] < 95 ? 'var(--danger)' : 'var(--success)'; 
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
                                            <td colspan="8" style="text-align: center; padding: 3rem; color: var(--muted);">
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
