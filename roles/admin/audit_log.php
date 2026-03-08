<?php
session_start();
require_once '../../database/connection.php';

// Check if admin user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /VitalWear-1/login.html');
    exit();
}

$conn = getDBConnection();

// Get audit log entries with pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 50;
$offset = ($page - 1) * $limit;

// Get total count for pagination
$count_result = $conn->query("SELECT COUNT(*) as total FROM activity_log");
$total_records = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);

// Get audit log entries
$audit_query = $conn->prepare("
    SELECT 
        al.activity_id,
        al.user_name,
        al.user_role,
        al.action_type,
        al.module,
        al.description,
        al.created_at
    FROM activity_log al 
    ORDER BY al.created_at DESC 
    LIMIT ? OFFSET ?
");
$audit_query->bind_param("ii", $limit, $offset);
$audit_query->execute();
$audit_logs = $audit_query->get_result()->fetch_all(MYSQLI_ASSOC);

// Get statistics
$stats = [
    'total_activities' => $total_records,
    'today_activities' => 0,
    'admin_activities' => 0,
    'management_activities' => 0,
    'responder_activities' => 0,
    'rescuer_activities' => 0
];

// Get today's activities
$today_query = $conn->prepare("
    SELECT COUNT(*) as count, user_role 
    FROM activity_log 
    WHERE DATE(created_at) = CURDATE()
    GROUP BY user_role
");
$today_query->execute();
$today_results = $today_query->get_result()->fetch_all(MYSQLI_ASSOC);

// Get role-based statistics
$role_stats = $conn->query("
    SELECT user_role, COUNT(*) as count
    FROM activity_log
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY user_role
");

// Get activity trends (last 7 days)
$activity_trends = $conn->query("
    SELECT 
        DATE(created_at) as date,
        COUNT(*) as activities,
        COUNT(DISTINCT user_name) as active_users
    FROM activity_log
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(created_at)
    ORDER BY date
");

// Get recent activities
$recent_activities = $conn->query("
    SELECT user_name, user_role, action_type, module, description, created_at
    FROM activity_log
    ORDER BY created_at DESC
    LIMIT 10
");

// Calculate statistics
foreach ($today_results as $result) {
    $stats['today_activities'] += $result['count'];
    $stats[strtolower($result['user_role']) . '_activities'] = $result['count'];
}

if ($role_stats) {
    $role_data = $role_stats->fetch_all(MYSQLI_ASSOC);
    foreach ($role_data as $role) {
        $stats[strtolower($role['user_role']) . '_activities'] = $role['count'];
    }
}

if ($activity_trends) $trends_data = $activity_trends->fetch_all(MYSQLI_ASSOC);
if ($recent_activities) $recent_data = $recent_activities->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Log - Admin</title>
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
                        <a href="audit_log.php" class="nav-item active">
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
                    <h1 class="navbar-brand">Activity Log</h1>
                </div>
                <div class="navbar-actions">
                    <span class="text-muted">Welcome, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Admin'); ?></span>
                    <a href="/VitalWear-1/api/auth/logout.php" class="btn btn-secondary">Logout</a>
                </div>
            </header>

            <!-- Page Content -->
            <div class="content">
                <div class="content-header">
                    <h1 class="content-title">📋 System Activity Log</h1>
                    <p class="content-subtitle">Comprehensive audit trail of all system activities</p>
                </div>

                <!-- Activity Statistics -->
                <div class="metrics-grid">
                    <div class="metric-card">
                        <div class="metric-icon">📊</div>
                        <div class="metric-value"><?php echo number_format($stats['total_activities']); ?></div>
                        <div class="metric-label">Total Activities</div>
                    </div>
                    
                    <div class="metric-card">
                        <div class="metric-icon">📅</div>
                        <div class="metric-value"><?php echo number_format($stats['today_activities']); ?></div>
                        <div class="metric-label">Today's Activities</div>
                    </div>
                    
                    <div class="metric-card">
                        <div class="metric-icon">👥</div>
                        <div class="metric-value"><?php echo number_format($stats['admin_activities']); ?></div>
                        <div class="metric-label">Admin Activities</div>
                    </div>
                    
                    <div class="metric-card">
                        <div class="metric-icon">🔄</div>
                        <div class="metric-value"><?php echo number_format($total_pages); ?></div>
                        <div class="metric-label">Total Pages</div>
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
                                <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">User Role</label>
                                <select style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: var(--radius);">
                                    <option>All Roles</option>
                                    <option>Admin</option>
                                    <option>Management</option>
                                    <option>Responder</option>
                                    <option>Rescuer</option>
                                </select>
                            </div>
                            <div>
                                <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Action Type</label>
                                <select style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: var(--radius);">
                                    <option>All Actions</option>
                                    <option>Login</option>
                                    <option>Logout</option>
                                    <option>Create</option>
                                    <option>Update</option>
                                    <option>Delete</option>
                                </select>
                            </div>
                            <div>
                                <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Date Range</label>
                                <select style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: var(--radius);">
                                    <option>Last 7 Days</option>
                                    <option>Last 30 Days</option>
                                    <option>Last 3 Months</option>
                                    <option>All Time</option>
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

                <!-- Recent Activities -->
                <div class="card">
                    <div class="card-header">
                        Recent System Activities
                    </div>
                    <div class="card-body">
                        <div class="table" style="overflow-x: auto;">
                            <table style="min-width: 800px;">
                                <thead>
                                    <tr>
                                        <th>User</th>
                                        <th>Role</th>
                                        <th>Action</th>
                                        <th>Module</th>
                                        <th>Description</th>
                                        <th>Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($recent_data)): ?>
                                        <?php foreach ($recent_data as $activity): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($activity['user_name'] ?? 'System'); ?></strong>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?php echo $activity['user_role']; ?>">
                                                    <?php echo htmlspecialchars(ucfirst($activity['user_role'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?php 
                                                    echo $activity['action_type'] === 'login_success' ? 'success' : 
                                                         ($activity['action_type'] === 'login_failed' ? 'danger' : 'primary'); 
                                                ?>">
                                                    <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $activity['action_type']))); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span style="color: var(--text-secondary); font-size: 0.875rem;">
                                                    <?php echo htmlspecialchars(ucfirst($activity['module'] ?? 'N/A')); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div style="max-width: 200px; word-break: break-word;">
                                                    <?php echo htmlspecialchars($activity['description']); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="text-muted">
                                                    <?php echo date('M j, H:i:s', strtotime($activity['created_at'])); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" style="text-align: center; padding: 3rem; color: var(--muted);">
                                                No recent activities found
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Full Activity Log -->
                <div class="card">
                    <div class="card-header">
                        Full Activity Log
                        <span style="background: var(--accent); color: white; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; margin-left: 1rem;">
                            Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="table" style="overflow-x: auto;">
                            <table style="min-width: 900px;">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>User</th>
                                        <th>Role</th>
                                        <th>Action</th>
                                        <th>Module</th>
                                        <th>Description</th>
                                        <th>Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($audit_logs)): ?>
                                        <?php foreach ($audit_logs as $log): ?>
                                        <tr>
                                            <td>
                                                <span style="font-family: 'Inter', monospace; font-size: 0.75rem; color: var(--muted);">
                                                    #<?php echo $log['activity_id']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($log['user_name'] ?? 'System'); ?></strong>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?php echo $log['user_role']; ?>">
                                                    <?php echo htmlspecialchars(ucfirst($log['user_role'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?php 
                                                    echo $log['action_type'] === 'login_success' ? 'success' : 
                                                         ($log['action_type'] === 'login_failed' ? 'danger' : 'primary'); 
                                                ?>">
                                                    <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $log['action_type']))); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span style="color: var(--text-secondary); font-size: 0.875rem;">
                                                    <?php echo htmlspecialchars(ucfirst($log['module'] ?? 'N/A')); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div style="max-width: 250px; word-break: break-word;">
                                                    <?php echo htmlspecialchars($log['description']); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="text-muted">
                                                    <?php echo date('M j, Y H:i:s', strtotime($log['created_at'])); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" style="text-align: center; padding: 3rem; color: var(--muted);">
                                                No activities found in the log
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                        <div style="display: flex; justify-content: center; align-items: center; gap: 1rem; margin-top: 2rem; padding-top: 2rem; border-top: 1px solid var(--border);">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page - 1; ?>" class="btn btn-secondary">
                                    ← Previous
                                </a>
                            <?php endif; ?>
                            
                            <div style="display: flex; gap: 0.5rem;">
                                <?php 
                                $start_page = max(1, $page - 2);
                                $end_page = min($total_pages, $page + 2);
                                
                                for ($i = $start_page; $i <= $end_page; $i++): 
                                ?>
                                    <a href="?page=<?php echo $i; ?>" class="btn <?php echo $i === $page ? 'btn-primary' : 'btn-secondary'; ?>" style="padding: 0.5rem 0.75rem;">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endfor; ?>
                            </div>
                            
                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?php echo $page + 1; ?>" class="btn btn-secondary">
                                    Next →
                                </a>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
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
                            <button class="btn btn-secondary" onclick="clearLog()">
                                🗑️ Clear Log
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
                    label: 'Activities',
                    data: <?php echo json_encode(array_column($trends_data ?? [], 'activities')); ?>,
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.4
                }, {
                    label: 'Active Users',
                    data: <?php echo json_encode(array_column($trends_data ?? [], 'active_users')); ?>,
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

        function clearLog() {
            if (confirm('Are you sure you want to clear the activity log? This action cannot be undone.')) {
                alert('Clear log functionality would be implemented here');
            }
        }
    </script>
</body>
</html>
