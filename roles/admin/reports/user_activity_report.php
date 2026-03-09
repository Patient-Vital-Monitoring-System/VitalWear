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
    <link rel="stylesheet" href="../../../assets/css/styles.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://kit.fontawesome.com/96e37b53f1.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --authority-blue: #1B3F72; --dashboard-light: #F4F7FC; --pure-white: #FFFFFF; --secondary-text: #7E91B3; --interface-border: #D1E0F1; --radius: 12px; --shadow: 0 4px 12px rgba(27, 63, 114, 0.08); }
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
    </style>
</head>
<body>
    <header class="topbar">
        <div style="display: flex; align-items: center; gap: 12px;"><i class="fa fa-shield-alt" style="font-size: 24px; color: var(--authority-blue);"></i><span style="font-size: 18px; font-weight: 700;">VitalWear Admin</span></div>
        <div style="display: flex; align-items: center; gap: 16px;"><span style="color: var(--secondary-text);">Welcome, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Admin'); ?></span><a href="/VitalWear-1/api/auth/logout.php" class="btn btn-secondary">Logout</a></div>
    </header>
    <nav id="sidebar">
        <div class="sidebar-logo"><img src="../../../assets/logo.png" alt="VitalWear Logo"></div>
        <a href="../dashboard.php"><i class="fa fa-gauge"></i> Dashboard</a>
        <a href="../users.php"><i class="fa fa-users"></i> Staff Directory</a>
        <a href="../system_reports.php"><i class="fa fa-chart-bar"></i> System Reports</a>
        <a href="incident_analysis.php"><i class="fa fa-chart-line"></i> Incident Analysis</a>
        <a href="device_performance.php"><i class="fa fa-mobile-alt"></i> Device Performance</a>
        <a href="user_activity_report.php" class="active"><i class="fa fa-user-chart"></i> User Activity</a>
        <a href="security_audit.php"><i class="fa fa-shield-alt"></i> Security Audit</a>
        <a href="../vitals_analytics.php"><i class="fa fa-heartbeat"></i> Vital Analytics</a>
        <a href="../audit_log.php"><i class="fa fa-clipboard-list"></i> Activity Log</a>
        <a href="../../../api/auth/logout.php" class="btn btn-secondary" style="margin: 12px;">Logout</a>
    </nav>
    <main class="container">
        <div class="page-header"><h1><i class="fa fa-user-chart"></i> User Activity Report</h1><p>Comprehensive user engagement and activity metrics</p></div>

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
