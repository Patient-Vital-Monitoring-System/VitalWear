<?php
require_once '../../database/connection.php';

// Get responder and rescuer activity data
$activity_rows = [];
try {
    // Responder Activity
    $stmt = $conn->query("SELECT 
                            r.resp_id,
                            r.resp_name,
                            r.resp_email,
                            COUNT(DISTINCT i.incident_id) as incidents_handled,
                            COUNT(DISTINCT CASE WHEN i.status IN ('active', 'pending') THEN i.incident_id END) as active_incidents,
                            MAX(i.start_time) as last_activity,
                            'responder' as role
                        FROM responder r
                        LEFT JOIN incident i ON i.resp_id = r.resp_id
                        GROUP BY r.resp_id, r.resp_name, r.resp_email
                        ORDER BY incidents_handled DESC");
    $responders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Rescuer Activity
    $stmt = $conn->query("SELECT 
                            rc.resc_id,
                            rc.resc_name,
                            rc.resc_email,
                            COUNT(DISTINCT i.incident_id) as incidents_handled,
                            COUNT(DISTINCT CASE WHEN i.status IN ('active', 'pending') THEN i.incident_id END) as active_incidents,
                            MAX(i.start_time) as last_activity,
                            'rescuer' as role
                        FROM rescuer rc
                        LEFT JOIN incident i ON i.resc_id = rc.resc_id
                        GROUP BY rc.resc_id, rc.resc_name, rc.resc_email
                        ORDER BY incidents_handled DESC");
    $rescuers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    $activity_rows = array_merge($responders, $rescuers);
} catch (Exception $e) {
    error_log('Activity query failed: ' . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Activity Monitoring - Admin</title>
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

        .alert {
            padding: 14px 16px;
            border-radius: 10px;
            border: 1px solid var(--border);
            margin-bottom: 16px;
            font-size: 13px;
        }

        .alert-info {
            background: rgba(0, 229, 255, 0.1);
            border-color: rgba(0, 229, 255, 0.3);
            color: var(--accent);
        }

        .role-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            background: rgba(0, 229, 255, 0.2);
            color: var(--accent);
            font-weight: 600;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-family: 'Space Mono', monospace;
        }

        .role-badge.responder {
            background: rgba(0, 229, 255, 0.2);
            color: #00e5ff;
        }

        .role-badge.rescuer {
            background: rgba(255, 77, 109, 0.2);
            color: #ff4d6d;
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

        .btn-secondary {
            background: var(--surface2);
            color: var(--text);
            border: 1px solid var(--border);
        }

        .btn-secondary:hover {
            background: var(--border);
            border-color: var(--accent);
        }

        @import url('https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;600;700;800&display=swap');
    </style>
</head>
<body>
    <nav class="navbar-top">
        <h2 class="navbar-brand">User Activity Monitor</h2>
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
                <div class="nav-group">
                    <button class="nav-group-toggle">Reports <span class="dropdown-arrow">▼</span></button>
                    <div class="nav-group-items">
                        <a class="nav-link" href="vitals_analytics.php">Vital Statistics</a>
                        <a class="nav-link" href="audit_log.php">System Activity Log</a>
                    </div>
                </div>

                <!-- Monitoring -->
                <div class="nav-group active">
                    <button class="nav-group-toggle">Monitoring <span class="dropdown-arrow">▼</span></button>
                    <div class="nav-group-items">
                        <a class="nav-link" href="incidents.php">Incident Monitoring</a>
                        <a class="nav-link" href="device_incidents.php">Device Overview</a>
                        <a class="nav-link active" href="vitals.php">User Activity</a>
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
                <h1>👤 Responder & Rescuer Activity</h1>
                <p>Track incident involvement and activity levels for all field staff members.</p>
                
                <?php if (!empty($activity_rows)): ?>
                <div class="card">
                    <div class="card-header">Field Staff Activity Overview</div>
                    <div class="card-body">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Incidents Handled</th>
                                    <th>Active</th>
                                    <th>Last Activity</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($activity_rows as $person): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($person['resp_name'] ?? $person['resc_name']); ?></td>
                                    <td><?php echo htmlspecialchars($person['resp_email'] ?? $person['resc_email']); ?></td>
                                    <td>
                                        <span class="role-badge <?php echo $person['role']; ?>">
                                            <?php echo htmlspecialchars($person['role']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $person['incidents_handled']; ?></td>
                                    <td><?php echo $person['active_incidents']; ?></td>
                                    <td><?php echo $person['last_activity'] ? htmlspecialchars($person['last_activity']) : '<span style="color: var(--muted);">Never</span>'; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php else: ?>
                <div class="alert alert-info">
                    No activity records found. This could mean no incidents have been recorded yet, or the database is empty.
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
    </script>
</body>
</html>
