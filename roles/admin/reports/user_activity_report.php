<?php
session_start();
require_once '../../../database/connection.php';

// Check if admin user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /VitalWear-1/login.html');
    exit();
}

$conn = getDBConnection();

// Get user activity data
$user_activity = [];
$error_message = '';

try {
    // Get activity by role
    $role_activity = $conn->query("
        SELECT user_role, COUNT(*) as activity_count,
               COUNT(DISTINCT user_name) as unique_users
        FROM activity_log 
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY user_role
        ORDER BY activity_count DESC
    ");
    
    // Get daily activity trends
    $daily_activity = $conn->query("
        SELECT DATE(created_at) as date, COUNT(*) as activities,
               COUNT(DISTINCT user_name) as active_users
        FROM activity_log 
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date
    ");
    
    // Get top users by activity
    $top_users = $conn->query("
        SELECT user_name, user_role, COUNT(*) as activity_count
        FROM activity_log 
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY user_name, user_role
        ORDER BY activity_count DESC
        LIMIT 10
    ");
    
    if ($role_activity) $user_activity['by_role'] = $role_activity->fetch_all(MYSQLI_ASSOC);
    if ($daily_activity) $user_activity['daily'] = $daily_activity->fetch_all(MYSQLI_ASSOC);
    if ($top_users) $user_activity['top_users'] = $top_users->fetch_all(MYSQLI_ASSOC);
    
} catch (Exception $e) {
    $error_message = "Error fetching user activity data: " . $e->getMessage();
}

// Calculate summary statistics
$total_activities = 0;
$total_users = 0;
if (!empty($user_activity['by_role'])) {
    foreach ($user_activity['by_role'] as $role) {
        $total_activities += $role['activity_count'];
        $total_users += $role['unique_users'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Activity Report - Admin</title>
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
                    <h1 class="content-title">📈 User Activity Report</h1>
                    <p class="content-subtitle">Comprehensive user engagement and activity metrics</p>
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
                        <div class="metric-value"><?php echo number_format($total_activities); ?></div>
                        <div class="metric-label">Total Activities (30 days)</div>
                    </div>
                    
                    <div class="metric-card">
                        <div class="metric-icon">👥</div>
                        <div class="metric-value"><?php echo number_format($total_users); ?></div>
                        <div class="metric-label">Active Users</div>
                    </div>
                    
                    <div class="metric-card">
                        <div class="metric-icon">📈</div>
                        <div class="metric-value"><?php echo number_format($total_activities / max($total_users, 1)); ?></div>
                        <div class="metric-label">Avg Activities/User</div>
                    </div>
                </div>

                <!-- Activity by Role Chart -->
                <div class="card">
                    <div class="card-header">
                        Activity Distribution by Role
                    </div>
                    <div class="card-body">
                        <div style="height: 300px; position: relative;">
                            <canvas id="roleActivityChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Daily Activity Trends -->
                <div class="card">
                    <div class="card-header">
                        Daily Activity Trends (Last 7 Days)
                    </div>
                    <div class="card-body">
                        <div style="height: 300px; position: relative;">
                            <canvas id="dailyActivityChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Top Active Users -->
                <div class="card">
                    <div class="card-header">
                        Top Active Users (Last 30 Days)
                    </div>
                    <div class="card-body">
                        <div class="table">
                            <table>
                                <thead>
                                    <tr>
                                        <th>User</th>
                                        <th>Role</th>
                                        <th>Activities</th>
                                        <th>Activity Level</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($user_activity['top_users'])): ?>
                                        <?php foreach ($user_activity['top_users'] as $user): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($user['user_name']); ?></strong>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?php echo $user['user_role']; ?>">
                                                    <?php echo htmlspecialchars(ucfirst($user['user_role'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <strong><?php echo number_format($user['activity_count']); ?></strong>
                                            </td>
                                            <td>
                                                <?php 
                                                $level = 'Low';
                                                if ($user['activity_count'] > 50) $level = 'High';
                                                elseif ($user['activity_count'] > 20) $level = 'Medium';
                                                ?>
                                                <span class="badge badge-<?php echo $level === 'High' ? 'danger' : ($level === 'Medium' ? 'warning' : 'success'); ?>">
                                                    <?php echo $level; ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" style="text-align: center; padding: 3rem; color: var(--muted);">
                                                No user activity data available
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
        // Activity by Role Chart
        const roleCtx = document.getElementById('roleActivityChart').getContext('2d');
        new Chart(roleCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_column($user_activity['by_role'] ?? [], 'user_role')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($user_activity['by_role'] ?? [], 'activity_count')); ?>,
                    backgroundColor: [
                        '#3b82f6',
                        '#8b5cf6',
                        '#10b981',
                        '#f59e0b'
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

        // Daily Activity Chart
        const dailyCtx = document.getElementById('dailyActivityChart').getContext('2d');
        new Chart(dailyCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_map(function($date) { 
                    return date('M j', strtotime($date)); 
                }, array_column($user_activity['daily'] ?? [], 'date'))); ?>,
                datasets: [{
                    label: 'Activities',
                    data: <?php echo json_encode(array_column($user_activity['daily'] ?? [], 'activities')); ?>,
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.4
                }, {
                    label: 'Active Users',
                    data: <?php echo json_encode(array_column($user_activity['daily'] ?? [], 'active_users')); ?>,
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
    </script>
</body>
</html>
