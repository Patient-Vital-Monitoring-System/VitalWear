<?php
session_start();
require_once '../../database/connection.php';

// Check if admin user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /VitalWear-1/login.html');
    exit();
}

$conn = getDBConnection();

// Get device statistics and information
$device_stats = [
    'total_devices' => 0,
    'available_devices' => 0,
    'assigned_devices' => 0,
    'maintenance_devices' => 0,
    'inactive_devices' => 0,
    'device_utilization' => 0
];

// Get device counts by status
$status_result = $conn->query("
    SELECT dev_status, COUNT(*) as count 
    FROM device 
    GROUP BY dev_status
");
if ($status_result) {
    $status_counts = $status_result->fetch_all(MYSQLI_ASSOC);
    foreach ($status_counts as $status) {
        $device_stats['total_devices'] += $status['count'];
        switch ($status['dev_status']) {
            case 'available':
                $device_stats['available_devices'] = $status['count'];
                break;
            case 'assigned':
                $device_stats['assigned_devices'] = $status['count'];
                break;
            case 'maintenance':
                $device_stats['maintenance_devices'] = $status['count'];
                break;
            case 'inactive':
                $device_stats['inactive_devices'] = $status['count'];
                break;
        }
    }
    
    // Calculate utilization
    $device_stats['device_utilization'] = $device_stats['total_devices'] > 0 
        ? round(($device_stats['assigned_devices'] / $device_stats['total_devices']) * 100, 1) 
        : 0;
}

// Get detailed device information with assignments
$devices_query = $conn->query("
    SELECT 
        d.dev_id,
        d.dev_serial,
        d.dev_status,
        d.last_maintenance,
        d.created_at,
        dl.resp_name as assigned_to,
        dl.date_assigned,
        dl.date_returned,
        TIMESTAMPDIFF(HOUR, dl.date_assigned, COALESCE(dl.date_returned, NOW())) as usage_hours
    FROM device d
    LEFT JOIN device_log dl ON d.dev_id = dl.dev_id 
        AND dl.date_returned IS NULL
    ORDER BY d.dev_id
");
$devices = [];
if ($devices_query) {
    $devices = $devices_query->fetch_all(MYSQLI_ASSOC);
}

// Get device assignment history
$assignment_history = $conn->query("
    SELECT 
        d.dev_serial,
        d.dev_status,
        dl.resp_name,
        dl.date_assigned,
        dl.date_returned,
        TIMESTAMPDIFF(HOUR, dl.date_assigned, COALESCE(dl.date_returned, NOW())) as usage_hours
    FROM device_log dl
    JOIN device d ON dl.dev_id = d.dev_id
    WHERE dl.date_assigned >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ORDER BY dl.date_assigned DESC
    LIMIT 20
");

// Get device maintenance schedule
$maintenance_schedule = $conn->query("
    SELECT 
        dev_id,
        dev_serial,
        dev_status,
        last_maintenance,
        DATEDIFF(NOW(), COALESCE(last_maintenance, created_at)) as days_since_maintenance
    FROM device
    WHERE dev_status = 'maintenance' OR 
          DATEDIFF(NOW(), COALESCE(last_maintenance, created_at)) > 90
    ORDER BY days_since_maintenance DESC
");

$maintenance_data = [];
if ($maintenance_schedule) {
    $maintenance_data = $maintenance_schedule->fetch_all(MYSQLI_ASSOC);
}

// Get device utilization trends (last 7 days)
$utilization_trends = $conn->query("
    SELECT 
        DATE(date_assigned) as date,
        COUNT(*) as assignments,
        COUNT(DISTINCT dev_id) as unique_devices
    FROM device_log
    WHERE date_assigned >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(date_assigned)
    ORDER BY date
");

// Get assignment history
$assignment_history = $conn->query("
    SELECT 
        d.dev_serial,
        d.dev_status,
        dl.resp_name,
        dl.date_assigned,
        dl.date_returned,
        TIMESTAMPDIFF(HOUR, dl.date_assigned, COALESCE(dl.date_returned, NOW())) as usage_hours
    FROM device_log dl
    JOIN device d ON dl.dev_id = d.dev_id
    WHERE dl.date_assigned >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ORDER BY dl.date_assigned DESC
    LIMIT 20
");

// Initialize data arrays
$history_data = [];
$trends_data = [];

if ($assignment_history) {
    $history_data = $assignment_history->fetch_all(MYSQLI_ASSOC);
}
if ($utilization_trends) {
    $trends_data = $utilization_trends->fetch_all(MYSQLI_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Device Overview - Admin</title>
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
                        <a href="device_incidents.php" class="nav-item active">
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
                    <h1 class="navbar-brand">Device Overview</h1>
                </div>
                <div class="navbar-actions">
                    <span class="text-muted">Welcome, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Admin'); ?></span>
                    <a href="/VitalWear-1/api/auth/logout.php" class="btn btn-secondary">Logout</a>
                </div>
            </header>

            <!-- Page Content -->
            <div class="content">
                <div class="content-header">
                    <h1 class="content-title">📦 Device Management Overview</h1>
                    <p class="content-subtitle">Comprehensive device tracking and utilization monitoring</p>
                </div>

                <!-- Device Statistics -->
                <div class="metrics-grid">
                    <div class="metric-card">
                        <div class="metric-icon">📱</div>
                        <div class="metric-value"><?php echo number_format($device_stats['total_devices']); ?></div>
                        <div class="metric-label">Total Devices</div>
                    </div>
                    
                    <div class="metric-card">
                        <div class="metric-icon">✅</div>
                        <div class="metric-value"><?php echo number_format($device_stats['available_devices']); ?></div>
                        <div class="metric-label">Available</div>
                    </div>
                    
                    <div class="metric-card">
                        <div class="metric-icon">👤</div>
                        <div class="metric-value"><?php echo number_format($device_stats['assigned_devices']); ?></div>
                        <div class="metric-label">Assigned</div>
                    </div>
                    
                    <div class="metric-card">
                        <div class="metric-icon">📊</div>
                        <div class="metric-value"><?php echo $device_stats['device_utilization']; ?>%</div>
                        <div class="metric-label">Utilization Rate</div>
                    </div>
                </div>

                <!-- Additional Device Stats -->
                <div class="metrics-grid">
                    <div class="metric-card">
                        <div class="metric-icon">🔧</div>
                        <div class="metric-value"><?php echo number_format($device_stats['maintenance_devices']); ?></div>
                        <div class="metric-label">In Maintenance</div>
                    </div>
                    
                    <div class="metric-card">
                        <div class="metric-icon">⚠️</div>
                        <div class="metric-value"><?php echo number_format($device_stats['inactive_devices']); ?></div>
                        <div class="metric-label">Inactive</div>
                    </div>
                </div>

                <!-- Device Utilization Trends -->
                <div class="card">
                    <div class="card-header">
                        Device Assignment Trends (Last 7 Days)
                    </div>
                    <div class="card-body">
                        <div style="height: 300px; position: relative;">
                            <canvas id="utilizationTrendsChart"></canvas>
                        </div>
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

                <!-- Device List -->
                <div class="card">
                    <div class="card-header">
                        Device Inventory
                        <span style="background: var(--accent); color: white; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; margin-left: 1rem;">
                            <?php echo count($devices); ?> Devices
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="table" style="overflow-x: auto;">
                            <table style="min-width: 900px;">
                                <thead>
                                    <tr>
                                        <th>Device ID</th>
                                        <th>Serial Number</th>
                                        <th>Status</th>
                                        <th>Assigned To</th>
                                        <th>Usage Hours</th>
                                        <th>Last Maintenance</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($devices)): ?>
                                        <?php foreach ($devices as $device): ?>
                                        <tr>
                                            <td>
                                                <span style="font-family: 'Inter', monospace; font-weight: 600; color: var(--accent);">
                                                    #<?php echo htmlspecialchars($device['dev_id']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($device['dev_serial']); ?></strong>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?php 
                                                    echo $device['dev_status'] === 'available' ? 'success' : 
                                                         ($device['dev_status'] === 'assigned' ? 'primary' : 
                                                         ($device['dev_status'] === 'maintenance' ? 'warning' : 'danger')); 
                                                ?>">
                                                    <?php echo htmlspecialchars(ucfirst($device['dev_status'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php 
                                                if (!empty($device['assigned_to'])) {
                                                    echo '<strong>' . htmlspecialchars($device['assigned_to']) . '</strong>';
                                                    echo '<div style="font-size: 0.75rem; color: var(--muted); margin-top: 0.25rem;">';
                                                    echo 'Since: ' . date('M j, H:i', strtotime($device['date_assigned']));
                                                    echo '</div>';
                                                } else {
                                                    echo '<span style="color: var(--muted);">Not assigned</span>';
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <?php 
                                                if ($device['usage_hours'] > 0) {
                                                    echo '<span style="font-weight: 600; color: var(--accent);">';
                                                    echo number_format($device['usage_hours']) . 'h';
                                                    echo '</span>';
                                                } else {
                                                    echo '<span style="color: var(--muted);">N/A</span>';
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <?php 
                                                if (!empty($device['last_maintenance'])) {
                                                    $days_ago = (strtotime(date('Y-m-d')) - strtotime($device['last_maintenance'])) / (60 * 60 * 24);
                                                    echo '<span style="font-weight: 600; color: ';
                                                    echo $days_ago > 90 ? 'var(--danger)' : 'var(--success)';
                                                    echo ';">';
                                                    echo date('M j, Y', strtotime($device['last_maintenance']));
                                                    echo '</span>';
                                                    echo '<div style="font-size: 0.75rem; color: var(--muted); margin-top: 0.25rem;">';
                                                    echo '(' . round($days_ago) . ' days ago)';
                                                    echo '</div>';
                                                } else {
                                                    echo '<span style="color: var(--warning);">Never maintained</span>';
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <div style="display: flex; gap: 0.5rem;">
                                                    <?php if ($device['dev_status'] === 'available'): ?>
                                                        <button class="btn btn-primary" style="padding: 0.25rem 0.75rem; font-size: 0.75rem;">
                                                            Assign
                                                        </button>
                                                    <?php elseif ($device['dev_status'] === 'assigned'): ?>
                                                        <button class="btn btn-secondary" style="padding: 0.25rem 0.75rem; font-size: 0.75rem;">
                                                            Return
                                                        </button>
                                                    <?php endif; ?>
                                                    <button class="btn btn-secondary" style="padding: 0.25rem 0.75rem; font-size: 0.75rem;">
                                                        Details
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" style="text-align: center; padding: 3rem; color: var(--muted);">
                                                No devices found in the system
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Maintenance Schedule -->
                <div class="card">
                    <div class="card-header">
                        Maintenance Schedule
                        <span style="background: var(--warning); color: white; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; margin-left: 1rem;">
                            <?php echo count($maintenance_data); ?> Devices Need Attention
                        </span>
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
                                    <?php if (!empty($maintenance_data)): ?>
                                        <?php foreach ($maintenance_data as $device): ?>
                                        <tr>
                                            <td>
                                                <span style="font-family: 'Inter', monospace; font-weight: 600; color: var(--accent);">
                                                    #<?php echo htmlspecialchars($device['dev_id']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($device['dev_serial']); ?></strong>
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
                                                    echo $device['days_since_maintenance'] > 180 ? 'var(--danger)' : 'var(--warning)'; 
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
                                                <button class="btn btn-primary" style="padding: 0.25rem 0.75rem; font-size: 0.75rem;">
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

                <!-- Recent Assignment History -->
                <div class="card">
                    <div class="card-header">
                        Recent Assignment History
                    </div>
                    <div class="card-body">
                        <div class="table" style="overflow-x: auto;">
                            <table style="min-width: 800px;">
                                <thead>
                                    <tr>
                                        <th>Device Serial</th>
                                        <th>Assigned To</th>
                                        <th>Date Assigned</th>
                                        <th>Date Returned</th>
                                        <th>Usage Duration</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($history_data)): ?>
                                        <?php foreach ($history_data as $history): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($history['dev_serial']); ?></strong>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($history['resp_name']); ?>
                                            </td>
                                            <td>
                                                <?php echo date('M j, Y H:i', strtotime($history['date_assigned'])); ?>
                                            </td>
                                            <td>
                                                <?php 
                                                if ($history['date_returned']) {
                                                    echo date('M j, Y H:i', strtotime($history['date_returned']));
                                                } else {
                                                    echo '<span style="color: var(--warning);">Still assigned</span>';
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <span style="font-weight: 600; color: var(--accent);">
                                                    <?php echo number_format($history['usage_hours']); ?>h
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?php echo $history['date_returned'] ? 'success' : 'primary'; ?>">
                                                    <?php echo $history['date_returned'] ? 'Returned' : 'Active'; ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" style="text-align: center; padding: 3rem; color: var(--muted);">
                                                No assignment history available
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
                            <button class="btn btn-secondary" onclick="addDevice()">
                                ➕ Add Device
                            </button>
                        </div>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <script>
        // Device Utilization Trends Chart
        const utilizationCtx = document.getElementById('utilizationTrendsChart').getContext('2d');
        new Chart(utilizationCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_map(function($date) { 
                    return date('M j', strtotime($date)); 
                }, array_column($trends_data ?? [], 'date'))); ?>,
                datasets: [{
                    label: 'Device Assignments',
                    data: <?php echo json_encode(array_column($trends_data ?? [], 'assignments')); ?>,
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.4
                }, {
                    label: 'Unique Devices Used',
                    data: <?php echo json_encode(array_column($trends_data ?? [], 'unique_devices')); ?>,
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

        // Device Status Distribution Chart
        const statusCtx = document.getElementById('deviceStatusChart').getContext('2d');
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: ['Available', 'Assigned', 'Maintenance', 'Inactive'],
                datasets: [{
                    data: [
                        <?php echo $device_stats['total_devices']; ?>,
                        <?php echo $device_stats['assigned_devices']; ?>,
                        <?php echo $device_stats['maintenance_devices']; ?>,
                        <?php echo $device_stats['inactive_devices']; ?>
                    ],
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

        // Export Functions
        function exportToCSV() {
            alert('CSV export functionality would be implemented here');
        }

        function exportToPDF() {
            alert('PDF export functionality would be implemented here');
        }

        function addDevice() {
            alert('Add device functionality would be implemented here');
        }
    </script>
</body>
</html>
