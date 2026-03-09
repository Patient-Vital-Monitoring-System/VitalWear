<?php
session_start();
require_once '../../../database/connection.php';

// Check if admin user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /VitalWear-1/login.html');
    exit();
}

$conn = getDBConnection();

// Get device data
$device_data = [];
$error_message = '';

try {
    // Get device status distribution
    $device_status = $conn->query("
        SELECT dev_status, COUNT(*) as count
        FROM device
        GROUP BY dev_status
    ");
    
    // Get device utilization
    $device_utilization = $conn->query("
        SELECT 
            d.dev_id,
            d.dev_serial,
            d.dev_status,
            COUNT(dl.dev_id) as assignment_count,
            AVG(TIMESTAMPDIFF(HOUR, dl.date_assigned, COALESCE(dl.date_returned, NOW()))) as avg_usage_hours
        FROM device d
        LEFT JOIN device_log dl ON d.dev_id = dl.dev_id
        WHERE dl.date_assigned >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY d.dev_id, d.dev_serial, d.dev_status
        ORDER BY assignment_count DESC
    ");
    
    // Get device maintenance needs
    $maintenance_needs = $conn->query("
        SELECT 
            d.dev_id,
            d.dev_serial,
            d.dev_status,
            d.last_maintenance,
            DATEDIFF(NOW(), COALESCE(d.last_maintenance, d.created_at)) as days_since_maintenance
        FROM device d
        WHERE d.dev_status = 'maintenance' OR 
              DATEDIFF(NOW(), COALESCE(d.last_maintenance, d.created_at)) > 90
        ORDER BY days_since_maintenance DESC
    ");
    
    if ($device_status) $device_data['status'] = $device_status->fetch_all(MYSQLI_ASSOC);
    if ($device_utilization) $device_data['utilization'] = $device_utilization->fetch_all(MYSQLI_ASSOC);
    if ($maintenance_needs) $device_data['maintenance'] = $maintenance_needs->fetch_all(MYSQLI_ASSOC);
    
} catch (Exception $e) {
    $error_message = "Error fetching device data: " . $e->getMessage();
}

// Calculate summary statistics
$total_devices = 0;
$avg_utilization = 0;
$maintenance_needed = 0;

if (!empty($device_data['status'])) {
    foreach ($device_data['status'] as $status) {
        $total_devices += $status['count'];
        if ($status['dev_status'] === 'maintenance') {
            $maintenance_needed += $status['count'];
        }
    }
}

if (!empty($device_data['utilization'])) {
    $total_usage = array_sum(array_column($device_data['utilization'], 'avg_usage_hours'));
    $avg_utilization = round($total_usage / count($device_data['utilization']), 1);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Device Performance Report - Admin</title>
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
        body { background-color: var(--dashboard-light); color: var(--authority-blue); font-family: 'Inter', sans-serif; margin: 0; padding: 0; }
        #sidebar { position: fixed; left: 0; top: 0; width: 260px; height: 100vh; background: var(--pure-white); border-right: 1px solid var(--interface-border); box-shadow: var(--shadow); z-index: 1000; overflow-y: auto; }
        .sidebar-logo { padding: 24px 20px; text-align: center; background: linear-gradient(135deg, var(--authority-blue) 0%, #2a5298 100%); margin: 12px; border-radius: var(--radius); }
        .sidebar-logo img { max-width: 140px; height: auto; filter: brightness(0) invert(1); }
        #sidebar a { color: var(--authority-blue); margin: 6px 12px; padding: 12px 16px; border-radius: var(--radius); transition: all 0.2s ease; border: none; font-weight: 500; text-decoration: none; display: flex; align-items: center; gap: 12px; }
        #sidebar a:hover { background: rgba(27, 63, 114, 0.1); transform: translateX(4px); }
        #sidebar a.active { background: rgba(27, 63, 114, 0.15); }
        .topbar { position: fixed; top: 0; left: 260px; right: 0; background: var(--pure-white); border-bottom: 1px solid var(--interface-border); padding: 16px 24px; font-weight: 600; z-index: 999; display: flex; align-items: center; justify-content: space-between; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; margin-left: 260px; margin-top: 80px; }
        .page-header { background: linear-gradient(135deg, var(--authority-blue) 0%, #2a5298 100%); color: white; padding: 30px 40px; border-radius: var(--radius); margin-bottom: 30px; box-shadow: var(--shadow); }
        .page-header h1 { color: white; margin: 0 0 8px 0; font-size: 1.8rem; display: flex; align-items: center; gap: 12px; }
        .page-header p { margin: 0; opacity: 0.9; color: white; }
        .card { background: var(--pure-white); border-radius: var(--radius); box-shadow: var(--shadow); border: 1px solid var(--interface-border); margin-bottom: 24px; }
        .card-header { padding: 16px 20px; border-bottom: 1px solid var(--interface-border); font-weight: 600; color: var(--authority-blue); }
        .card-body { padding: 20px; }
        .btn { padding: 10px 20px; border: none; border-radius: var(--radius); cursor: pointer; font-size: 14px; font-weight: 600; transition: all 0.3s ease; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; }
        .btn-primary { background: linear-gradient(135deg, var(--authority-blue) 0%, #2a5298 100%); color: white; }
        .btn-secondary { background: var(--pure-white); color: var(--authority-blue); border: 1px solid var(--interface-border); }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 16px; text-align: left; border-bottom: 1px solid var(--interface-border); }
        th { background: var(--dashboard-light); font-weight: 600; }
        tr:hover { background: var(--dashboard-light); }
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .badge-success { background: rgba(44, 201, 144, 0.15); color: #2CC990; }
        .badge-warning { background: rgba(255, 193, 7, 0.15); color: #d39e00; }
        .badge-danger { background: rgba(220, 53, 69, 0.15); color: #DC3545; }
    </style>
</head>
<body>
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
    <nav id="sidebar">
        <div class="sidebar-logo"><img src="../../../assets/logo.png" alt="VitalWear Logo"></div>
        <a href="../dashboard.php"><i class="fa fa-gauge"></i> Dashboard</a>
        <a href="../users.php"><i class="fa fa-users"></i> Staff Directory</a>
        <a href="../system_reports.php"><i class="fa fa-chart-bar"></i> System Reports</a>
        <a href="incident_analysis.php"><i class="fa fa-chart-line"></i> Incident Analysis</a>
        <a href="device_performance.php" class="active"><i class="fa fa-mobile-alt"></i> Device Performance</a>
        <a href="user_activity_report.php"><i class="fa fa-user-chart"></i> User Activity</a>
        <a href="security_audit.php"><i class="fa fa-shield-alt"></i> Security Audit</a>
        <a href="../vitals_analytics.php"><i class="fa fa-heartbeat"></i> Vital Analytics</a>
        <a href="../audit_log.php"><i class="fa fa-clipboard-list"></i> Activity Log</a>
        <a href="../../../api/auth/logout.php" class="btn btn-secondary" style="margin: 12px;">Logout</a>
    </nav>
    <main class="container">
        <div class="page-header">
            <h1><i class="fa fa-mobile-alt"></i> Device Performance</h1>
            <p>Device utilization and maintenance reports</p>
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
                        <div class="metric-icon">📱</div>
                        <div class="metric-value"><?php echo number_format($total_devices); ?></div>
                        <div class="metric-label">Total Devices</div>
                    </div>
                    
                    <div class="metric-card">
                        <div class="metric-icon">⏱️</div>
                        <div class="metric-value"><?php echo $avg_utilization; ?>h</div>
                        <div class="metric-label">Avg Usage Time</div>
                    </div>
                    
                    <div class="metric-card">
                        <div class="metric-icon">🔧</div>
                        <div class="metric-value"><?php echo number_format($maintenance_needed); ?></div>
                        <div class="metric-label">Need Maintenance</div>
                    </div>
                </div>

                <!-- Device Status Distribution -->
                <div class="card">
                    <div class="card-header">
                        Device Status Distribution
                    </div>
                    <div class="card-body">
                        <div style="height: 300px; position: relative;">
                            <canvas id="deviceStatusChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Device Utilization -->
                <div class="card">
                    <div class="card-header">
                        Device Utilization (Last 30 Days)
                    </div>
                    <div class="card-body">
                        <div style="height: 300px; position: relative;">
                            <canvas id="utilizationChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Maintenance Needed -->
                <div class="card">
                    <div class="card-header">
                        Devices Requiring Maintenance
                    </div>
                    <div class="card-body">
                        <div class="table" style="overflow-x: auto;">
                            <table style="min-width: 800px;">
                                <thead>
                                    <tr>
                                        <th>Device ID</th>
                                        <th>Serial Number</th>
                                        <th>Status</th>
                                        <th>Days Since Maintenance</th>
                                        <th>Priority</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($device_data['maintenance'])): ?>
                                        <?php foreach ($device_data['maintenance'] as $device): ?>
                                        <tr>
                                            <td>
                                                <strong>#<?php echo htmlspecialchars($device['dev_id']); ?></strong>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($device['dev_serial']); ?>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?php 
                                                    echo $device['dev_status'] === 'maintenance' ? 'danger' : 'warning'; 
                                                ?>">
                                                    <?php echo htmlspecialchars(ucfirst($device['dev_status'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span style="font-weight: 600; color: <?php 
                                                    echo $device['days_since_maintenance'] > 180 ? 'var(--danger)' : 
                                                         ($device['days_since_maintenance'] > 90 ? 'var(--warning)' : 'var(--success)'); 
                                                ?>;">
                                                    <?php echo $device['days_since_maintenance']; ?> days
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?php 
                                                    echo $device['days_since_maintenance'] > 180 ? 'danger' : 'warning'; 
                                                ?>">
                                                    <?php echo $device['days_since_maintenance'] > 180 ? 'Urgent' : 'Scheduled'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button class="btn btn-primary" style="padding: 0.5rem 1rem; font-size: 0.75rem;">
                                                    Schedule Maintenance
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" style="text-align: center; padding: 3rem; color: var(--muted);">
                                                All devices are properly maintained
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
        // Device Status Chart
        const statusCtx = document.getElementById('deviceStatusChart').getContext('2d');
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_map('ucfirst', array_column($device_data['status'] ?? [], 'dev_status'))); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($device_data['status'] ?? [], 'count')); ?>,
                    backgroundColor: [
                        '#10b981',
                        '#3b82f6',
                        '#f59e0b',
                        '#ef4444'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                    }
                }
            }
        });

        // Device Utilization Chart
        const utilizationCtx = document.getElementById('utilizationChart').getContext('2d');
        new Chart(utilizationCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_slice(array_column($device_data['utilization'] ?? [], 'dev_serial'), 0, 10)); ?>,
                datasets: [{
                    label: 'Usage Hours',
                    data: <?php echo json_encode(array_slice(array_column($device_data['utilization'] ?? [], 'avg_usage_hours'), 0, 10)); ?>,
                    backgroundColor: 'rgba(59, 130, 246, 0.8)',
                    borderColor: '#3b82f6',
                    borderWidth: 1
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
