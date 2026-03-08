<?php
require_once '../../database/connection.php';

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

foreach ($today_results as $result) {
    $stats['today_activities'] += $result['count'];
    $role_key = $result['user_role'] . '_activities';
    if (isset($stats[$role_key])) {
        $stats[$role_key] = $result['count'];
    }
}

// Get role distribution overall
$role_query = $conn->prepare("
    SELECT user_role, COUNT(*) as count 
    FROM activity_log 
    GROUP BY user_role
");
$role_query->execute();
$role_results = $role_query->get_result()->fetch_all(MYSQLI_ASSOC);

foreach ($role_results as $result) {
    $role_key = $result['user_role'] . '_activities';
    if (isset($stats[$role_key])) {
        $stats[$role_key] = $result['count'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Activity Log - Admin</title>
    <link rel="stylesheet" href="../../assets/css/styles.css">
    <style>
        :root {
            --bg: #0a0e1a;
            --surface: #111827;
            --surface2: #1a2235;
            --border: #1f2d45;
            --accent: #00e5ff;
            --accent2: #ff4d6d;
            --accent3: #39ff14;
            --text: #e2e8f0;
            --muted: #64748b;
            --warn: #f59e0b;
            --danger: #ef4444;
            --success: #10b981;
        }

        * { margin:0; padding:0; box-sizing:border-box; }

        body {
            font-family:'Syne',sans-serif;
            background:var(--bg);
            color:var(--text);
            min-height:100vh;
            overflow-x:hidden;
            display: flex;
            flex-direction: column;
        }

        .navbar-top {
            position: sticky;
            top: 0;
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            color: white;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
            z-index: 1030;
        }

        .page-wrapper {
            display: flex;
            flex: 1;
            min-height: calc(100vh - 70px);
        }

        .sidebar {
            width: 320px;
            background-color: var(--surface);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            min-height: 100%;
            overflow-y: auto;
        }

        .sidebar-header {
            background-color: var(--surface2);
            color: white;
            border-bottom: 1px solid var(--border);
            padding: 24px;
        }

        .sidebar-title {
            font-weight: 700;
            font-size: 1.3rem;
            color: var(--accent);
            letter-spacing: 1px;
            font-family: 'Space Mono', monospace;
        }

        .sidebar-nav {
            display: flex;
            flex-direction: column;
            padding: 0;
            margin: 0;
            flex: 1;
        }

        .sidebar-nav .nav-link {
            color: var(--muted);
            padding: 18px 24px;
            border-left: 3px solid transparent;
            transition: all 0.3s ease;
            text-decoration: none;
            display: block;
            font-weight: 600;
            font-size: 14px;
        }

        .sidebar-nav .nav-link:hover {
            background-color: rgba(0, 229, 255, 0.1);
            color: var(--accent);
            border-left-color: var(--accent);
        }

        .sidebar-nav .nav-link.active {
            color: var(--accent);
            background-color: rgba(0, 229, 255, 0.15);
            border-left-color: var(--accent);
        }

        .nav-group {
            display: flex;
            flex-direction: column;
        }

        .nav-group-toggle {
            color: var(--muted);
            padding: 18px 24px;
            border-left: 3px solid transparent;
            border: none;
            background: transparent;
            transition: all 0.3s ease;
            text-decoration: none;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            width: 100%;
            text-align: left;
            font-family: inherit;
        }

        .nav-group-toggle:hover {
            background-color: rgba(0, 229, 255, 0.1);
            color: var(--accent);
            border-left-color: var(--accent);
        }

        .dropdown-arrow {
            transition: transform 0.3s ease;
            display: inline-block;
            font-size: 12px;
        }

        .nav-group.active .dropdown-arrow {
            transform: rotate(180deg);
        }

        .nav-group-items {
            display: none;
            flex-direction: column;
            background-color: rgba(0, 0, 0, 0.2);
            border-left: 3px solid var(--border);
        }

        .nav-group.active .nav-group-items {
            display: flex;
        }

        .nav-group .nav-link {
            padding: 14px 24px 14px 48px;
            border-left: none;
            font-size: 13px;
            color: var(--muted);
        }

        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
        }

        .navbar-brand {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 700;
            color: white;
            letter-spacing: -0.5px;
            flex: 1;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 32px 20px;
        }

        h1 {
            color: var(--accent);
            font-weight: 800;
            margin-bottom: 16px;
            margin-top: 0;
            font-size: 2rem;
            letter-spacing: -0.5px;
        }

        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .metric-card {
            background: var(--surface);
            border: 2px solid var(--border);
            border-radius: 12px;
            padding: 24px;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }

        .metric-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--accent), var(--accent2), var(--accent3));
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .metric-card:hover {
            border-color: var(--accent);
            box-shadow: 0 8px 24px rgba(0, 229, 255, 0.15);
            transform: translateY(-4px);
        }

        .metric-card:hover::before {
            opacity: 1;
        }

        .metric-icon {
            font-size: 36px;
            margin-bottom: 12px;
            opacity: 0.8;
        }

        .metric-value {
            font-size: 2.8rem;
            font-weight: 800;
            color: var(--accent);
            letter-spacing: -1px;
            margin: 12px 0;
            font-family: 'Syne', sans-serif;
        }

        .metric-label {
            font-size: 12px;
            letter-spacing: 1px;
            color: var(--muted);
            font-family: 'Space Mono', monospace;
            font-weight: 600;
            text-transform: uppercase;
        }

        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            transition: transform 0.2s, box-shadow 0.2s;
            margin-bottom: 24px;
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 229, 255, 0.1);
        }

        .card-header {
            background-color: var(--surface2);
            color: var(--accent);
            border-bottom: 1px solid var(--border);
            font-weight: 700;
            padding: 16px 20px;
            font-size: 13px;
            letter-spacing: 1px;
            font-family: 'Space Mono', monospace;
        }

        .card-body {
            padding: 24px;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
        }

        .table thead {
            background-color: var(--surface2);
        }

        .table thead th {
            padding: 14px 16px;
            font-size: 10px;
            letter-spacing: 1.5px;
            color: var(--muted);
            text-align: left;
            font-family: 'Space Mono', monospace;
            border-bottom: 2px solid var(--border);
            font-weight: 700;
        }

        .table tbody td {
            padding: 14px 16px;
            font-size: 13px;
            border-bottom: 1px solid rgba(31, 45, 69, 0.5);
            color: var(--text);
        }

        .table tbody tr:hover td {
            background-color: rgba(0, 229, 255, 0.05);
        }

        .table tbody tr:last-child td {
            border-bottom: none;
        }

        .controls-section {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .controls-row {
            display: flex;
            gap: 20px;
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .filter-group label {
            color: var(--muted);
            font-weight: 600;
            white-space: nowrap;
        }

        .filter-select {
            padding: 10px 14px;
            border: 1px solid var(--border);
            border-radius: 6px;
            background: var(--surface2);
            color: var(--text);
            cursor: pointer;
            font-size: 13px;
            font-family: 'Syne', sans-serif;
        }

        .search-form {
            display: flex;
            gap: 10px;
        }

        .search-input {
            padding: 10px 14px;
            border: 1px solid var(--border);
            border-radius: 6px;
            background: var(--surface2);
            color: var(--text);
            font-size: 13px;
            font-family: 'Syne', sans-serif;
            min-width: 250px;
        }

        .search-input::placeholder {
            color: var(--muted);
        }

        .search-input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(0, 229, 255, 0.1);
        }

        .btn {
            padding: 10px 14px;
            border-radius: 6px;
            font-family: 'Syne', sans-serif;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-block;
            letter-spacing: 0.5px;
        }

        .btn-primary {
            background: var(--accent);
            color: #000;
        }

        .btn-primary:hover {
            background: #33eeff;
            transform: translateY(-1px);
            box-shadow: 0 8px 20px rgba(0, 229, 255, 0.3);
        }

        .btn-secondary {
            background: var(--surface2);
            color: var(--text);
            border: 1px solid var(--border);
        }

        .btn-secondary:hover {
            background: var(--border);
            border-color: var(--accent);
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 20px;
        }

        .pagination a {
            padding: 8px 12px;
            background: var(--surface2);
            border: 1px solid var(--border);
            color: var(--text);
            text-decoration: none;
            border-radius: 4px;
            transition: all 0.2s;
        }

        .pagination a:hover {
            background: var(--accent);
            color: #000;
            border-color: var(--accent);
        }

        .pagination .active {
            background: var(--accent);
            color: #000;
            border-color: var(--accent);
        }

        .role-badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.5px;
            font-family: 'Space Mono', monospace;
            text-transform: uppercase;
            border: 1px solid;
        }

        .role-badge.admin {
            background: rgba(255, 77, 109, 0.15);
            color: #ff4d6d;
            border-color: rgba(255, 77, 109, 0.3);
        }

        .role-badge.staff {
            background: rgba(0, 229, 255, 0.15);
            color: var(--accent);
            border-color: rgba(0, 229, 255, 0.3);
        }

        .role-badge.responder {
            background: rgba(245, 158, 11, 0.15);
            color: var(--warn);
            border-color: rgba(245, 158, 11, 0.3);
        }

        .role-badge.rescuer {
            background: rgba(57, 255, 20, 0.15);
            color: #39ff14;
            border-color: rgba(57, 255, 20, 0.3);
        }

        .activity-time {
            color: var(--muted);
            font-size: 12px;
            font-family: 'Space Mono', monospace;
        }

        .activity-description {
            max-width: 400px;
            word-wrap: break-word;
        }

        @import url('https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;600;700;800&display=swap');
    </style>
</head>
<body>
    <nav class="navbar-top">
        <h2 class="navbar-brand">System Activity Log</h2>
        <a href="/VitalWear-1/api/auth/logout.php" class="btn btn-secondary">Logout</a>
    </nav>

    <div class="page-wrapper">
        <aside class="sidebar">
            <div class="sidebar-header">
                <h5 class="sidebar-title">Menu</h5>
            </div>
            <nav class="sidebar-nav">
                <a class="nav-link" href="dashboard.php">Dashboard</a>
                
                <!-- User Management -->
                <div class="nav-group">
                    <button class="nav-group-toggle">User Management <span class="dropdown-arrow">▼</span></button>
                    <div class="nav-group-items">
                        <a class="nav-link" href="users.php">Staff Directory</a>
                        <a class="nav-link" href="user_status.php">User Status</a>
                    </div>
                </div>

                <!-- Reports -->
                <div class="nav-group active">
                    <button class="nav-group-toggle">Reports <span class="dropdown-arrow">▼</span></button>
                    <div class="nav-group-items">
                        <a class="nav-link" href="vitals_analytics.php">Vital Statistics</a>
                        <a class="nav-link active" href="audit_log.php">System Activity Log</a>
                    </div>
                </div>

                <!-- Monitoring -->
                <div class="nav-group">
                    <button class="nav-group-toggle">Monitoring <span class="dropdown-arrow">▼</span></button>
                    <div class="nav-group-items">
                        <a class="nav-link" href="incidents.php">Incident Monitoring</a>
                        <a class="nav-link" href="device_incidents.php">Device Overview</a>
                        <a class="nav-link" href="vitals.php">User Activity</a>
                    </div>
                </div>

                <!-- Accounts -->
                <div class="nav-group">
                    <button class="nav-group-toggle">Accounts <span class="dropdown-arrow">▼</span></button>
                    <div class="nav-group-items">
                        <a class="nav-link" href="profile.php">Profile</a>
                        <a class="nav-link" href="/VitalWear-1/api/auth/logout.php" style="color: #ff4d6d;">Logout</a>
                    </div>
                </div>
            </nav>
        </aside>

        <main class="main-content">
            <div class="container">
                <h1>📋 System Activity Log</h1>
                <p>Comprehensive audit trail of all system activities and user actions.</p>

                <!-- Metrics Grid -->
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
                        <div class="metric-icon">👨‍💼</div>
                        <div class="metric-value"><?php echo number_format($stats['admin_activities']); ?></div>
                        <div class="metric-label">Admin Activities</div>
                    </div>
                    
                    <div class="metric-card">
                        <div class="metric-icon">📋</div>
                        <div class="metric-value"><?php echo number_format($stats['management_activities']); ?></div>
                        <div class="metric-label">Management Activities</div>
                    </div>
                    
                    <div class="metric-card">
                        <div class="metric-icon">🚑</div>
                        <div class="metric-value"><?php echo number_format($stats['responder_activities']); ?></div>
                        <div class="metric-label">Responder Activities</div>
                    </div>
                    
                    <div class="metric-card">
                        <div class="metric-icon">🆘</div>
                        <div class="metric-value"><?php echo number_format($stats['rescuer_activities']); ?></div>
                        <div class="metric-label">Rescuer Activities</div>
                    </div>
                </div>

                <!-- Controls Section -->
                <div class="controls-section">
                    <div class="controls-row">
                        <div class="filter-group">
                            <label for="roleFilter">Filter by Role:</label>
                            <select id="roleFilter" class="filter-select" onchange="filterByRole()">
                                <option value="">All Roles</option>
                                <option value="admin">Admin</option>
                                <option value="management">Management</option>
                                <option value="responder">Responder</option>
                                <option value="rescuer">Rescuer</option>
                            </select>
                        </div>
                        
                        <form class="search-form" method="GET">
                            <input type="text" name="search" class="search-input" placeholder="Search activities..." value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                            <button type="submit" class="btn btn-primary">Search</button>
                        </form>
                    </div>
                </div>

                <!-- Audit Log Table -->
                <div class="card">
                    <div class="card-header">Activity Log Entries</div>
                    <div class="card-body">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Timestamp</th>
                                    <th>User</th>
                                    <th>Role</th>
                                    <th>Action</th>
                                    <th>Module</th>
                                    <th>Activity</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($audit_logs as $log): ?>
                                <tr>
                                    <td>
                                        <div class="activity-time">
                                            <?php echo date('M j, Y H:i:s', strtotime($log['created_at'])); ?>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($log['user_name'] ?? 'System'); ?></td>
                                    <td>
                                        <span class="role-badge <?php echo $log['user_role']; ?>">
                                            <?php echo htmlspecialchars($log['user_role']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span style="color: var(--accent); font-size: 12px; font-family: 'Space Mono', monospace;">
                                            <?php echo htmlspecialchars($log['action_type'] ?? 'N/A'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span style="color: var(--muted); font-size: 12px; font-family: 'Space Mono', monospace;">
                                            <?php echo htmlspecialchars($log['module'] ?? 'N/A'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="activity-description">
                                            <?php echo htmlspecialchars($log['description']); ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <?php if (empty($audit_logs)): ?>
                        <div style="text-align: center; padding: 40px; color: var(--muted);">
                            No activity logs found matching your criteria.
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>">« Previous</a>
                    <?php endif; ?>
                    
                    <?php
                    $start = max(1, $page - 2);
                    $end = min($total_pages, $page + 2);
                    
                    for ($i = $start; $i <= $end; $i++):
                    ?>
                        <a href="?page=<?php echo $i; ?>" class="<?php echo $i == $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>">Next »</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

            </div>
        </main>
    </div>

    <script>
        // Toggle navigation groups
        document.querySelectorAll('.nav-group-toggle').forEach(toggle => {
            toggle.addEventListener('click', function() {
                this.parentElement.classList.toggle('active');
            });
        });

        // Filter by role
        function filterByRole() {
            const role = document.getElementById('roleFilter').value;
            let url = 'audit_log.php';
            if (role) {
                url += '?role=' + encodeURIComponent(role);
            }
            window.location.href = url;
        }
    </script>
</body>
</html>
