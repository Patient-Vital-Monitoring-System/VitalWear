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
                    <h1 class="content-title">📱 Device Performance</h1>
                    <p class="content-subtitle">Device utilization and maintenance reports</p>
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
