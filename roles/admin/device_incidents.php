<?php
require_once '../../database/connection.php';

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
        d.created_at,
        dl.resp_id,
        dl.date_assigned,
        dl.date_returned,
        r.resp_name as assigned_responder,
        rc.resc_name as assigned_rescuer,
        CASE 
            WHEN d.dev_status = 'assigned' AND dl.resp_id IS NOT NULL THEN 'responder'
            WHEN d.dev_status = 'assigned' AND dl.resc_id IS NOT NULL THEN 'rescuer'
            ELSE 'unassigned'
        END as assigned_to_type
    FROM device d
    LEFT JOIN device_log dl ON d.dev_id = dl.dev_id AND dl.date_returned IS NULL
    LEFT JOIN responder r ON dl.resp_id = r.resp_id
    LEFT JOIN rescuer rc ON dl.resc_id = rc.resc_id
    ORDER BY d.created_at DESC
");
$devices = $devices_query ? $devices_query->fetch_all(MYSQLI_ASSOC) : [];

// Get device assignment history
$assignment_history = $conn->query("
    SELECT 
        d.dev_serial,
        d.dev_status,
        CASE 
            WHEN dl.resp_id IS NOT NULL THEN r.resp_name
            WHEN dl.resc_id IS NOT NULL THEN rc.resc_name
            ELSE 'System'
        END as assigned_to,
        CASE 
            WHEN dl.resp_id IS NOT NULL THEN 'responder'
            WHEN dl.resc_id IS NOT NULL THEN 'rescuer'
            ELSE 'system'
        END as assigned_type,
        dl.date_assigned,
        dl.date_returned,
        DATEDIFF(IFNULL(dl.date_returned, CURDATE()), dl.date_assigned) as duration_days
    FROM device_log dl
    JOIN device d ON dl.dev_id = d.dev_id
    LEFT JOIN responder r ON dl.resp_id = r.resp_id
    LEFT JOIN rescuer rc ON dl.resc_id = rc.resc_id
    ORDER BY dl.date_assigned DESC
    LIMIT 20
");
$history = $assignment_history ? $assignment_history->fetch_all(MYSQLI_ASSOC) : [];

// Get device performance metrics
$performance_metrics = [
    'avg_assignment_duration' => 0,
    'most_used_device' => 'N/A',
    'devices_needing_maintenance' => 0,
    'new_devices_this_month' => 0
];

// Average assignment duration
$duration_query = $conn->query("
    SELECT AVG(DATEDIFF(date_returned, date_assigned)) as avg_duration 
    FROM device_log 
    WHERE date_returned IS NOT NULL
");
if ($duration_query) {
    $result = $duration_query->fetch_assoc();
    $performance_metrics['avg_assignment_duration'] = round($result['avg_duration'], 1);
}

// Most used device
$most_used_query = $conn->query("
    SELECT d.dev_serial, COUNT(*) as usage_count
    FROM device_log dl
    JOIN device d ON dl.dev_id = d.dev_id
    GROUP BY d.dev_id, d.dev_serial
    ORDER BY usage_count DESC
    LIMIT 1
");
if ($most_used_query) {
    $result = $most_used_query->fetch_assoc();
    $performance_metrics['most_used_device'] = $result['dev_serial'] . ' (' . $result['usage_count'] . ' uses)';
}

// Devices needing maintenance
$performance_metrics['devices_needing_maintenance'] = $device_stats['maintenance_devices'];

// New devices this month
$new_devices_query = $conn->query("
    SELECT COUNT(*) as count 
    FROM device 
    WHERE MONTH(created_at) = MONTH(CURDATE()) 
    AND YEAR(created_at) = YEAR(CURDATE())
");
if ($new_devices_query) {
    $result = $new_devices_query->fetch_assoc();
    $performance_metrics['new_devices_this_month'] = $result['count'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Device Overview - Admin</title>
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
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 30px;
        }

        .metric-card {
            background: var(--surface);
            border: 2px solid var(--border);
            border-radius: 12px;
            padding: 20px;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            min-height: 140px;
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
            font-size: 28px;
            margin-bottom: 8px;
            opacity: 0.8;
        }

        .metric-value {
            font-size: 2.2rem;
            font-weight: 800;
            color: var(--accent);
            letter-spacing: -1px;
            margin: 8px 0;
            font-family: 'Syne', sans-serif;
        }

        .metric-label {
            font-size: 11px;
            letter-spacing: 1px;
            color: var(--muted);
            font-family: 'Space Mono', monospace;
            font-weight: 600;
            text-transform: uppercase;
            margin-top: auto;
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

        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            font-family: 'Space Mono', monospace;
            border: 1px solid;
        }

        .status-badge.available {
            background: rgba(57, 255, 20, 0.15);
            color: #39ff14;
            border-color: rgba(57, 255, 20, 0.3);
        }

        .status-badge.assigned {
            background: rgba(0, 229, 255, 0.15);
            color: var(--accent);
            border-color: rgba(0, 229, 255, 0.3);
        }

        .status-badge.maintenance {
            background: rgba(245, 158, 11, 0.15);
            color: var(--warn);
            border-color: rgba(245, 158, 11, 0.3);
        }

        .status-badge.inactive {
            background: rgba(255, 77, 109, 0.15);
            color: #ff4d6d;
            border-color: rgba(255, 77, 109, 0.3);
        }

        .role-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            font-family: 'Space Mono', monospace;
        }

        .role-badge.responder {
            background: rgba(0, 229, 255, 0.15);
            color: var(--accent);
        }

        .role-badge.rescuer {
            background: rgba(255, 77, 109, 0.15);
            color: #ff4d6d;
        }

        .activity-time {
            color: var(--muted);
            font-size: 12px;
            font-family: 'Space Mono', monospace;
        }

        .performance-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
        }

        .performance-item {
            background: var(--surface2);
            padding: 16px;
            border-radius: 8px;
            text-align: center;
        }

        .performance-label {
            font-size: 11px;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 1px;
            font-family: 'Space Mono', monospace;
            margin-bottom: 8px;
        }

        .performance-value {
            font-size: 18px;
            font-weight: 700;
            color: var(--accent);
        }

        @import url('https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;600;700;800&display=swap');
    </style>
</head>
<body>
    <nav class="navbar-top">
        <h2 class="navbar-brand">Device Overview</h2>
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
                        <a class="nav-link active" href="device_incidents.php">Device Overview</a>
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
                <h1>📦 Device Oversight</h1>
                <p>Monitor all device assignments, status, and performance metrics across the VitalWear system.</p>

                <!-- Device Metrics -->
                <div class="metrics-grid">
                    <div class="metric-card">
                        <div class="metric-icon">📦</div>
                        <div class="metric-value"><?php echo number_format($device_stats['total_devices']); ?></div>
                        <div class="metric-label">Total Devices</div>
                    </div>
                    
                    <div class="metric-card">
                        <div class="metric-icon">✅</div>
                        <div class="metric-value"><?php echo number_format($device_stats['available_devices']); ?></div>
                        <div class="metric-label">Available</div>
                    </div>
                    
                    <div class="metric-card">
                        <div class="metric-icon">🔄</div>
                        <div class="metric-value"><?php echo number_format($device_stats['assigned_devices']); ?></div>
                        <div class="metric-label">Assigned</div>
                    </div>
                    
                    <div class="metric-card">
                        <div class="metric-icon">🔧</div>
                        <div class="metric-value"><?php echo number_format($device_stats['maintenance_devices']); ?></div>
                        <div class="metric-label">Maintenance</div>
                    </div>
                    
                    <div class="metric-card">
                        <div class="metric-icon">📊</div>
                        <div class="metric-value"><?php echo $device_stats['device_utilization']; ?>%</div>
                        <div class="metric-label">Utilization Rate</div>
                    </div>
                    
                    <div class="metric-card">
                        <div class="metric-icon">⚠️</div>
                        <div class="metric-value"><?php echo number_format($device_stats['inactive_devices']); ?></div>
                        <div class="metric-label">Inactive</div>
                    </div>
                </div>

                <!-- Performance Metrics -->
                <div class="card">
                    <div class="card-header">Device Performance Metrics</div>
                    <div class="card-body">
                        <div class="performance-grid">
                            <div class="performance-item">
                                <div class="performance-label">Avg Assignment Duration</div>
                                <div class="performance-value"><?php echo $performance_metrics['avg_assignment_duration']; ?> days</div>
                            </div>
                            <div class="performance-item">
                                <div class="performance-label">Most Used Device</div>
                                <div class="performance-value" style="font-size: 14px;"><?php echo $performance_metrics['most_used_device']; ?></div>
                            </div>
                            <div class="performance-item">
                                <div class="performance-label">Devices Needing Maintenance</div>
                                <div class="performance-value warning"><?php echo $performance_metrics['devices_needing_maintenance']; ?></div>
                            </div>
                            <div class="performance-item">
                                <div class="performance-label">New Devices This Month</div>
                                <div class="performance-value"><?php echo $performance_metrics['new_devices_this_month']; ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Controls Section -->
                <div class="controls-section">
                    <div class="controls-row">
                        <div class="filter-group">
                            <label for="statusFilter">Filter by Status:</label>
                            <select id="statusFilter" class="filter-select" onchange="filterByStatus()">
                                <option value="">All Status</option>
                                <option value="available">Available</option>
                                <option value="assigned">Assigned</option>
                                <option value="maintenance">Maintenance</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        
                        <form class="search-form" method="GET">
                            <input type="text" name="search" class="search-input" placeholder="Search devices..." value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                            <button type="submit" class="btn btn-primary">Search</button>
                        </form>
                    </div>
                </div>

                <!-- Device List -->
                <div class="card">
                    <div class="card-header">Device Inventory</div>
                    <div class="card-body">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Serial Number</th>
                                    <th>Status</th>
                                    <th>Assigned To</th>
                                    <th>Assignment Date</th>
                                    <th>Created</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($devices as $device): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($device['dev_serial']); ?></strong>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $device['dev_status']; ?>">
                                            <?php echo htmlspecialchars(ucfirst($device['dev_status'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($device['assigned_to_type'] !== 'unassigned'): ?>
                                            <div>
                                                <strong><?php echo htmlspecialchars($device['assigned_responder'] ?? $device['assigned_rescuer']); ?></strong>
                                                <span class="role-badge <?php echo $device['assigned_to_type']; ?>">
                                                    <?php echo htmlspecialchars($device['assigned_to_type']); ?>
                                                </span>
                                            </div>
                                        <?php else: ?>
                                            <span style="color: var(--muted);">Unassigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($device['date_assigned']): ?>
                                            <div class="activity-time">
                                                <?php echo date('M j, Y', strtotime($device['date_assigned'])); ?>
                                            </div>
                                        <?php else: ?>
                                            <span style="color: var(--muted);">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="activity-time">
                                            <?php echo date('M j, Y', strtotime($device['created_at'])); ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <?php if (empty($devices)): ?>
                        <div style="text-align: center; padding: 40px; color: var(--muted);">
                            No devices found matching your criteria.
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Assignment History -->
                <div class="card">
                    <div class="card-header">Recent Assignment History</div>
                    <div class="card-body">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Device</th>
                                    <th>Assigned To</th>
                                    <th>Type</th>
                                    <th>Assignment Date</th>
                                    <th>Return Date</th>
                                    <th>Duration</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($history as $entry): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($entry['dev_serial']); ?></strong>
                                        <div>
                                            <span class="status-badge <?php echo $entry['dev_status']; ?>">
                                                <?php echo htmlspecialchars(ucfirst($entry['dev_status'])); ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($entry['assigned_to']); ?></strong>
                                        <div>
                                            <span class="role-badge <?php echo $entry['assigned_type']; ?>">
                                                <?php echo htmlspecialchars($entry['assigned_type']); ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="role-badge <?php echo $entry['assigned_type']; ?>">
                                            <?php echo htmlspecialchars(ucfirst($entry['assigned_type'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="activity-time">
                                            <?php echo date('M j, Y H:i', strtotime($entry['date_assigned'])); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($entry['date_returned']): ?>
                                            <div class="activity-time">
                                                <?php echo date('M j, Y H:i', strtotime($entry['date_returned'])); ?>
                                            </div>
                                        <?php else: ?>
                                            <span style="color: var(--accent);">Still assigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="activity-time">
                                            <?php echo $entry['duration_days']; ?> days
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <?php if (empty($history)): ?>
                        <div style="text-align: center; padding: 40px; color: var(--muted);">
                            No assignment history found.
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

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

        // Filter by status
        function filterByStatus() {
            const status = document.getElementById('statusFilter').value;
            let url = 'device_incidents.php';
            if (status) {
                url += '?status=' + encodeURIComponent(status);
            }
            window.location.href = url;
        }
    </script>
</body>
</html>
