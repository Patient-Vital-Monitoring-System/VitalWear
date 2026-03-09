<?php
session_start();
require_once '../../../database/connection.php';

// Check if admin user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /VitalWear-1/login.html');
    exit();
}

$conn = getDBConnection();

// Get security data
$security_data = [];
$error_message = '';

try {
    // Get login attempts (last 30 days)
    $login_attempts = $conn->query("
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as total_attempts,
            SUM(CASE WHEN action_type = 'login_success' THEN 1 ELSE 0 END) as successful_logins,
            SUM(CASE WHEN action_type = 'login_failed' THEN 1 ELSE 0 END) as failed_logins
        FROM activity_log 
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        AND (action_type = 'login_success' OR action_type = 'login_failed')
        GROUP BY DATE(created_at)
        ORDER BY date
    ");
    
    // Get user access patterns
    $access_patterns = $conn->query("
        SELECT 
            user_role,
            COUNT(DISTINCT user_name) as unique_users,
            COUNT(*) as total_activities,
            COUNT(DISTINCT module) as modules_accessed
        FROM activity_log 
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY user_role
        ORDER BY total_activities DESC
    ");
    
    // Get security events
    $security_events = $conn->query("
        SELECT user_name, user_role, action_type, module, description, created_at
        FROM activity_log 
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        AND (action_type LIKE '%failed%' OR action_type LIKE '%denied%' OR action_type LIKE '%blocked%')
        ORDER BY created_at DESC
        LIMIT 20
    ");
    
    // Get user account status
    $account_status = $conn->query("
        SELECT 'admin' as role, COUNT(*) as total, 
               SUM(CASE WHEN admin_email LIKE '%@%' THEN 1 ELSE 0 END) as active
        FROM admin
        UNION ALL
        SELECT 'management' as role, COUNT(*) as total,
               SUM(CASE WHEN mgmt_email LIKE '%@%' THEN 1 ELSE 0 END) as active
        FROM management
        UNION ALL
        SELECT 'responder' as role, COUNT(*) as total,
               SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active
        FROM responder
        UNION ALL
        SELECT 'rescuer' as role, COUNT(*) as total,
               SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active
        FROM rescuer
    ");
    
    if ($login_attempts) $security_data['login_attempts'] = $login_attempts->fetch_all(MYSQLI_ASSOC);
    if ($access_patterns) $security_data['access_patterns'] = $access_patterns->fetch_all(MYSQLI_ASSOC);
    if ($security_events) $security_data['security_events'] = $security_events->fetch_all(MYSQLI_ASSOC);
    if ($account_status) $security_data['account_status'] = $account_status->fetch_all(MYSQLI_ASSOC);
    
} catch (Exception $e) {
    $error_message = "Error fetching security data: " . $e->getMessage();
}

// Calculate summary statistics
$total_logins = 0;
$failed_logins = 0;
$suspicious_activities = 0;

if (!empty($security_data['login_attempts'])) {
    foreach ($security_data['login_attempts'] as $attempt) {
        $total_logins += $attempt['total_attempts'];
        $failed_logins += $attempt['failed_logins'];
    }
}

if (!empty($security_data['security_events'])) {
    $suspicious_activities = count($security_data['security_events']);
}

$security_score = $total_logins > 0 ? max(0, 100 - round(($failed_logins / $total_logins) * 100)) : 100;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Audit Report - Admin</title>
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
        <a href="user_activity_report.php"><i class="fa fa-user-chart"></i> User Activity</a>
        <a href="security_audit.php" class="active"><i class="fa fa-shield-alt"></i> Security Audit</a>
        <a href="../vitals_analytics.php"><i class="fa fa-heartbeat"></i> Vital Analytics</a>
        <a href="../audit_log.php"><i class="fa fa-clipboard-list"></i> Activity Log</a>
        <a href="../../../api/auth/logout.php" class="btn btn-secondary" style="margin: 12px;">Logout</a>
    </nav>
    <main class="container">
        <div class="page-header"><h1><i class="fa fa-shield-alt"></i> Security Audit</h1><p>System security and access control analysis</p></div>

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

                <!-- Security Score -->
                <div class="metrics-grid">
                    <div class="metric-card">
                        <div class="metric-icon">🔒</div>
                        <div class="metric-value" style="color: <?php echo $security_score >= 80 ? 'var(--success)' : ($security_score >= 60 ? 'var(--warning)' : 'var(--danger)'); ?>;">
                            <?php echo $security_score; ?>%
                        </div>
                        <div class="metric-label">Security Score</div>
                    </div>
                    
                    <div class="metric-card">
                        <div class="metric-icon">👥</div>
                        <div class="metric-value"><?php echo number_format($total_logins); ?></div>
                        <div class="metric-label">Total Logins (30 days)</div>
                    </div>
                    
                    <div class="metric-card">
                        <div class="metric-icon">⚠️</div>
                        <div class="metric-value"><?php echo number_format($failed_logins); ?></div>
                        <div class="metric-label">Failed Logins</div>
                    </div>
                    
                    <div class="metric-card">
                        <div class="metric-icon">🚨</div>
                        <div class="metric-value"><?php echo number_format($suspicious_activities); ?></div>
                        <div class="metric-label">Suspicious Activities</div>
                    </div>
                </div>

                <!-- Login Trends Chart -->
                <div class="card">
                    <div class="card-header">
                        Login Trends (Last 30 Days)
                    </div>
                    <div class="card-body">
                        <div style="height: 300px; position: relative;">
                            <canvas id="loginTrendsChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- User Access Patterns -->
                <div class="card">
                    <div class="card-header">
                        User Access Patterns by Role
                    </div>
                    <div class="card-body">
                        <div style="height: 300px; position: relative;">
                            <canvas id="accessPatternsChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Account Status Overview -->
                <div class="card">
                    <div class="card-header">
                        Account Status Overview
                    </div>
                    <div class="card-body">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem;">
                            <?php if (!empty($security_data['account_status'])): ?>
                                <?php foreach ($security_data['account_status'] as $account): ?>
                                <div style="text-align: center; padding: 1rem; border: 1px solid var(--border); border-radius: var(--radius);">
                                    <h4 style="margin: 0 0 0.5rem 0; color: var(--text);"><?php echo ucfirst($account['role']); ?></h4>
                                    <div style="font-size: 1.5rem; font-weight: 700; color: var(--accent); margin-bottom: 0.5rem;">
                                        <?php echo number_format($account['active']); ?>
                                    </div>
                                    <div style="font-size: 0.875rem; color: var(--text-secondary);">
                                        of <?php echo number_format($account['total']); ?> active
                                    </div>
                                    <div style="margin-top: 0.5rem; height: 4px; background: var(--surface2); border-radius: 2px; overflow: hidden;">
                                        <div style="height: 100%; background: var(--accent); width: <?php echo round(($account['active'] / $account['total']) * 100); ?>%;"></div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Security Events -->
                <div class="card">
                    <div class="card-header">
                        Recent Security Events
                    </div>
                    <div class="card-body">
                        <div class="table" style="overflow-x: auto;">
                            <table style="min-width: 800px;">
                                <thead>
                                    <tr>
                                        <th>User</th>
                                        <th>Role</th>
                                        <th>Event Type</th>
                                        <th>Module</th>
                                        <th>Description</th>
                                        <th>Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($security_data['security_events'])): ?>
                                        <?php foreach ($security_data['security_events'] as $event): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($event['user_name'] ?? 'System'); ?></strong>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?php echo $event['user_role']; ?>">
                                                    <?php echo htmlspecialchars(ucfirst($event['user_role'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge badge-danger">
                                                    <?php echo htmlspecialchars($event['action_type']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span style="color: var(--text-secondary); font-size: 0.875rem;">
                                                    <?php echo htmlspecialchars($event['module'] ?? 'N/A'); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div style="max-width: 200px; word-break: break-word;">
                                                    <?php echo htmlspecialchars($event['description']); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="text-muted">
                                                    <?php echo date('M j, H:i', strtotime($event['created_at'])); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" style="text-align: center; padding: 3rem; color: var(--muted);">
                                                No security events detected in the last 30 days
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
        // Login Trends Chart
        const loginCtx = document.getElementById('loginTrendsChart').getContext('2d');
        new Chart(loginCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_map(function($date) { 
                    return date('M j', strtotime($date)); 
                }, array_column($security_data['login_attempts'] ?? [], 'date'))); ?>,
                datasets: [{
                    label: 'Successful Logins',
                    data: <?php echo json_encode(array_column($security_data['login_attempts'] ?? [], 'successful_logins')); ?>,
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    tension: 0.4
                }, {
                    label: 'Failed Logins',
                    data: <?php echo json_encode(array_column($security_data['login_attempts'] ?? [], 'failed_logins')); ?>,
                    borderColor: '#ef4444',
                    backgroundColor: 'rgba(239, 68, 68, 0.1)',
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

        // Access Patterns Chart
        const accessCtx = document.getElementById('accessPatternsChart').getContext('2d');
        new Chart(accessCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_map('ucfirst', array_column($security_data['access_patterns'] ?? [], 'user_role'))); ?>,
                datasets: [{
                    label: 'Total Activities',
                    data: <?php echo json_encode(array_column($security_data['access_patterns'] ?? [], 'total_activities')); ?>,
                    backgroundColor: 'rgba(59, 130, 246, 0.8)',
                    borderColor: '#3b82f6',
                    borderWidth: 1
                }, {
                    label: 'Modules Accessed',
                    data: <?php echo json_encode(array_column($security_data['access_patterns'] ?? [], 'modules_accessed')); ?>,
                    backgroundColor: 'rgba(16, 185, 129, 0.8)',
                    borderColor: '#10b981',
                    borderWidth: 1
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
