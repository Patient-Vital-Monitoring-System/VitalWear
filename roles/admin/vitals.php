<?php
session_start();
require_once '../../database/connection.php';

// Check if admin user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /VitalWear-1/login.html');
    exit();
}

$conn = getDBConnection();

// Get responder and rescuer activity data
$activity_rows = [];
$error_message = '';

try {
    // Responder Activity
    $stmt = $conn->query("SELECT 
                        r.resp_id,
                        r.resp_name,
                        r.resp_email,
                        r.resp_contact,
                        r.status,
                        COUNT(DISTINCT i.incident_id) as incidents_handled,
                        COUNT(DISTINCT CASE WHEN i.status IN ('active', 'pending') THEN i.incident_id END) as active_incidents,
                        MAX(i.start_time) as last_activity,
                        'responder' as role
                    FROM responder r
                    LEFT JOIN incident i ON i.resp_id = r.resp_id
                    GROUP BY r.resp_id, r.resp_name, r.resp_email, r.resp_contact, r.status
                    ORDER BY incidents_handled DESC");
    $responders = $stmt->fetch_all(MYSQLI_ASSOC);
    
    // Rescuer Activity
    $stmt = $conn->query("SELECT 
                        rc.resc_id,
                        rc.resc_name,
                        rc.resc_email,
                        rc.resc_contact,
                        rc.status,
                        COUNT(DISTINCT i.incident_id) as incidents_handled,
                        COUNT(DISTINCT CASE WHEN i.status IN ('active', 'pending') THEN i.incident_id END) as active_incidents,
                        MAX(i.start_time) as last_activity,
                        'rescuer' as role
                    FROM rescuer rc
                    LEFT JOIN incident i ON i.resc_id = rc.resc_id
                    GROUP BY rc.resc_id, rc.resc_name, rc.resc_email, rc.resc_contact, rc.status
                    ORDER BY incidents_handled DESC");
    $rescuers = $stmt->fetch_all(MYSQLI_ASSOC);
    
    $activity_rows = array_merge($responders, $rescuers);
    
    // Get activity statistics
    $total_responders = count($responders);
    $total_rescuers = count($rescuers);
    $total_users = count($activity_rows);
    
    $active_responders = 0;
    $active_rescuers = 0;
    $total_incidents = 0;
    
    foreach ($responders as $responder) {
        if ($responder['active_incidents'] > 0) $active_responders++;
        $total_incidents += $responder['incidents_handled'];
    }
    
    foreach ($rescuers as $rescuer) {
        if ($rescuer['active_incidents'] > 0) $active_rescuers++;
        $total_incidents += $rescuer['incidents_handled'];
    }
    
    // Get activity trends (last 7 days)
    $activity_trends = $conn->query("
        SELECT 
            DATE(i.start_time) as date,
            COUNT(*) as total_incidents,
            COUNT(DISTINCT CASE WHEN r.resp_id IS NOT NULL THEN r.resp_id END) as active_responders,
            COUNT(DISTINCT CASE WHEN rc.resc_id IS NOT NULL THEN rc.resc_id END) as active_rescuers
        FROM incident i
        LEFT JOIN responder r ON i.resp_id = r.resp_id
        LEFT JOIN rescuer rc ON i.resc_id = rc.resc_id
        WHERE i.start_time >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY DATE(i.start_time)
        ORDER BY date
    ");
    
    // Get recent activities
    $recent_activities = $conn->query("
        SELECT 
            'Responder' as user_type,
            r.resp_name as user_name,
            r.resp_email as user_email,
            i.incident_id,
            i.incident_type,
            i.status,
            i.start_time as activity_time,
            i.location
        FROM incident i
        JOIN responder r ON i.resp_id = r.resp_id
        WHERE i.start_time >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        
        UNION ALL
        
        SELECT 
            'Rescuer' as user_type,
            rc.resc_name as user_name,
            rc.resc_email as user_email,
            i.incident_id,
            i.incident_type,
            i.status,
            i.start_time as activity_time,
            i.location
        FROM incident i
        JOIN rescuer rc ON i.resc_id = rc.resc_id
        WHERE i.start_time >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        
        ORDER BY activity_time DESC
        LIMIT 20
    ");
    
    if ($activity_trends) $trends_data = $activity_trends->fetch_all(MYSQLI_ASSOC);
    if ($recent_activities) $recent_data = $recent_activities->fetch_all(MYSQLI_ASSOC);
    
} catch (Exception $e) {
    $error_message = "Error fetching user activity data: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Activity - Admin</title>
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
                        <a href="device_incidents.php" class="nav-item">
                            📦 Device Overview
                        </a>
                        <a href="vitals.php" class="nav-item active">
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
                    <h1 class="navbar-brand">User Activity</h1>
                </div>
                <div class="navbar-actions">
                    <span class="text-muted">Welcome, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Admin'); ?></span>
                    <a href="/VitalWear-1/api/auth/logout.php" class="btn btn-secondary">Logout</a>
                </div>
            </header>

            <!-- Page Content -->
            <div class="content">
                <div class="content-header">
                    <h1 class="content-title">👤 User Activity Monitoring</h1>
                    <p class="content-subtitle">Real-time tracking of responder and rescuer activities</p>
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

                <!-- User Statistics -->
                <div class="metrics-grid">
                    <div class="metric-card">
                        <div class="metric-icon">👥</div>
                        <div class="metric-value"><?php echo number_format($total_users); ?></div>
                        <div class="metric-label">Total Field Staff</div>
                    </div>
                    
                    <div class="metric-card">
                        <div class="metric-icon">🚑</div>
                        <div class="metric-value"><?php echo number_format($total_responders); ?></div>
                        <div class="metric-label">Responders</div>
                    </div>
                    
                    <div class="metric-card">
                        <div class="metric-icon">🆘</div>
                        <div class="metric-value"><?php echo number_format($total_rescuers); ?></div>
                        <div class="metric-label">Rescuers</div>
                    </div>
                    
                    <div class="metric-card">
                        <div class="metric-icon">📊</div>
                        <div class="metric-value"><?php echo number_format($total_incidents); ?></div>
                        <div class="metric-label">Total Incidents</div>
                    </div>
                </div>

                <!-- Active Staff Statistics -->
                <div class="metrics-grid">
                    <div class="metric-card">
                        <div class="metric-icon">✅</div>
                        <div class="metric-value"><?php echo number_format($active_responders); ?></div>
                        <div class="metric-label">Active Responders</div>
                    </div>
                    
                    <div class="metric-card">
                        <div class="metric-icon">✅</div>
                        <div class="metric-value"><?php echo number_format($active_rescuers); ?></div>
                        <div class="metric-label">Active Rescuers</div>
                    </div>
                </div>

                <!-- Activity Trends Chart -->
                <div class="card">
                    <div class="card-header">
                        Activity Trends (Last 7 Days)
                    </div>
                    <div class="card-body">
                        <div style="height: 300px; position: relative;">
                            <canvas id="activityTrendsChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Filter Options -->
                <div class="card">
                    <div class="card-header">
                        Filter Options
                    </div>
                    <div class="card-body">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                            <div>
                                <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">User Type</label>
                                <select style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: var(--radius);">
                                    <option>All Users</option>
                                    <option>Responders</option>
                                    <option>Rescuers</option>
                                </select>
                            </div>
                            <div>
                                <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Status</label>
                                <select style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: var(--radius);">
                                    <option>All Status</option>
                                    <option>Active</option>
                                    <option>Inactive</option>
                                </select>
                            </div>
                            <div>
                                <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Activity Level</label>
                                <select style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: var(--radius);">
                                    <option>All Levels</option>
                                    <option>High Activity</option>
                                    <option>Medium Activity</option>
                                    <option>Low Activity</option>
                                </select>
                            </div>
                            <div style="display: flex; align-items: end;">
                                <button class="btn btn-primary" style="width: 100%;">
                                    🔍 Apply Filters
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- User Activity List -->
                <div class="card">
                    <div class="card-header">
                        Field Staff Activity Overview
                        <span style="background: var(--accent); color: white; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; margin-left: 1rem;">
                            <?php echo count($activity_rows); ?> Staff Members
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="table" style="overflow-x: auto;">
                            <table style="min-width: 900px;">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Contact</th>
                                        <th>Role</th>
                                        <th>Status</th>
                                        <th>Incidents Handled</th>
                                        <th>Active Cases</th>
                                        <th>Last Activity</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($activity_rows)): ?>
                                        <?php foreach ($activity_rows as $activity): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($activity['resp_name'] ?? $activity['resc_name']); ?></strong>
                                            </td>
                                            <td>
                                                <div style="color: var(--text-secondary); word-break: break-word;">
                                                    <?php echo htmlspecialchars($activity['resp_email'] ?? $activity['resc_email']); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div style="color: var(--text-secondary);">
                                                    <?php 
                                                    $contact = $activity['resp_contact'] ?? $activity['resc_contact'];
                                                    echo !empty($contact) ? htmlspecialchars($contact) : '<span style="color: var(--muted);">Not provided</span>';
                                                    ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?php echo $activity['role']; ?>">
                                                    <?php echo htmlspecialchars(ucfirst($activity['role'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?php echo ($activity['status'] ?? 'active') === 'active' ? 'success' : 'warning'; ?>">
                                                    <?php echo htmlspecialchars(ucfirst($activity['status'] ?? 'active')); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span style="font-weight: 600; color: var(--accent);">
                                                    <?php echo number_format($activity['incidents_handled']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span style="font-weight: 600; color: <?php echo $activity['active_incidents'] > 0 ? 'var(--warning)' : 'var(--success)'; ?>;">
                                                    <?php echo number_format($activity['active_incidents']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="text-muted">
                                                    <?php 
                                                    if (!empty($activity['last_activity'])) {
                                                        echo date('M j, H:i', strtotime($activity['last_activity']));
                                                    } else {
                                                        echo '<span style="color: var(--muted);">No activity</span>';
                                                    }
                                                    ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="9" style="text-align: center; padding: 3rem; color: var(--muted);">
                                                No user activity data available
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Recent Activities -->
                <div class="card">
                    <div class="card-header">
                        Recent Field Activities
                    </div>
                    <div class="card-body">
                        <div class="table" style="overflow-x: auto;">
                            <table style="min-width: 800px;">
                                <thead>
                                    <tr>
                                        <th>User Type</th>
                                        <th>Name</th>
                                        <th>Incident ID</th>
                                        <th>Type</th>
                                        <th>Status</th>
                                        <th>Location</th>
                                        <th>Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($recent_data)): ?>
                                        <?php foreach ($recent_data as $activity): ?>
                                        <tr>
                                            <td>
                                                <span class="badge badge-<?php echo $activity['user_type'] === 'Responder' ? 'primary' : 'secondary'; ?>">
                                                    <?php echo htmlspecialchars($activity['user_type']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($activity['user_name']); ?></strong>
                                            </td>
                                            <td>
                                                <span style="font-family: 'Inter', monospace; font-weight: 600; color: var(--accent);">
                                                    #<?php echo htmlspecialchars($activity['incident_id']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span style="color: var(--text-secondary);">
                                                    <?php echo htmlspecialchars($activity['incident_type']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?php 
                                                    echo $activity['status'] === 'active' ? 'danger' : 
                                                         ($activity['status'] === 'pending' ? 'warning' : 'success'); 
                                                ?>">
                                                    <?php echo htmlspecialchars(ucfirst($activity['status'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span style="color: var(--text-secondary);">
                                                    <?php echo htmlspecialchars($activity['location'] ?? 'N/A'); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="text-muted">
                                                    <?php echo date('M j, H:i:s', strtotime($activity['activity_time'])); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" style="text-align: center; padding: 3rem; color: var(--muted);">
                                                No recent activities found
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
                            <button class="btn btn-secondary" onclick="refreshData()">
                                🔄 Refresh Data
                            </button>
                        </div>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <script>
        // Activity Trends Chart
        const trendsCtx = document.getElementById('activityTrendsChart').getContext('2d');
        new Chart(trendsCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_map(function($date) { 
                    return date('M j', strtotime($date)); 
                }, array_column($trends_data ?? [], 'date'))); ?>,
                datasets: [{
                    label: 'Total Incidents',
                    data: <?php echo json_encode(array_column($trends_data ?? [], 'total_incidents')); ?>,
                    borderColor: '#ef4444',
                    backgroundColor: 'rgba(239, 68, 68, 0.1)',
                    tension: 0.4
                }, {
                    label: 'Active Responders',
                    data: <?php echo json_encode(array_column($trends_data ?? [], 'active_responders')); ?>,
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.4
                }, {
                    label: 'Active Rescuers',
                    data: <?php echo json_encode(array_column($trends_data ?? [], 'active_rescuers')); ?>,
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

        // Export Functions
        function exportToCSV() {
            alert('CSV export functionality would be implemented here');
        }

        function exportToPDF() {
            alert('PDF export functionality would be implemented here');
        }

        function refreshData() {
            alert('Data refresh functionality would be implemented here');
        }
    </script>
</body>
</html>
