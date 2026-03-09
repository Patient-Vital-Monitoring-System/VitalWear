<?php
session_start();
require_once '../../database/connection.php';

// Check if management user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'management') {
    header('Location: ../../login.html');
    exit();
}

$conn = getDBConnection();

// Handle filtering
$status_filter = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';

// Build query
$where_conditions = [];
$params = [];
$types = '';

if ($status_filter !== 'all') {
    $where_conditions[] = "d.dev_status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if (!empty($search)) {
    $where_conditions[] = "d.dev_serial LIKE ?";
    $params[] = "%$search%";
    $types .= 's';
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Get devices with assignment info
$query = "
    SELECT d.*, 
           dl.resp_id,
           dl.date_assigned,
           dl.date_returned,
           r.resp_name,
           r.resp_email
    FROM device d
    LEFT JOIN device_log dl ON d.dev_id = dl.dev_id AND dl.date_returned IS NULL
    LEFT JOIN responder r ON dl.resp_id = r.resp_id
    $where_clause
    ORDER BY d.created_at DESC
";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$devices = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get statistics
$total_devices = 0;
$available_devices = 0;
$assigned_devices = 0;
$maintenance_devices = 0;

$stats_query = "SELECT dev_status, COUNT(*) as count FROM device GROUP BY dev_status";
$stats_result = $conn->query($stats_query);
while ($row = $stats_result->fetch_assoc()) {
    $total_devices += $row['count'];
    switch ($row['dev_status']) {
        case 'available': $available_devices = $row['count']; break;
        case 'assigned': $assigned_devices = $row['count']; break;
        case 'maintenance': $maintenance_devices = $row['count']; break;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Device List - VitalWear</title>
        <script src="https://kit.fontawesome.com/96e37b53f1.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* VitalWear Management Color Palette */
        :root {
            --authority-blue: #1B3F72;
            --dashboard-light: #F4F7FC;
            --pure-white: #FFFFFF;
            --secondary-text: #7E91B3;
            --system-success: #2CC990;
            --system-warning: #FFC107;
            --system-error: #DC3545;
            --interface-border: #D1E0F1;
            --radius: 12px;
            --radius-lg: 16px;
            --shadow-sm: 0 2px 4px rgba(27, 63, 114, 0.06);
            --shadow: 0 4px 12px rgba(27, 63, 114, 0.08);
            --shadow-md: 0 8px 24px rgba(27, 63, 114, 0.12);
        }

        body {
            background-color: var(--dashboard-light);
            color: var(--authority-blue);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            margin: 0;
            padding: 0;
        }

        /* Soft UI Sidebar */
        #sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 260px;
            height: 100vh;
            background: var(--pure-white);
            border-right: 1px solid var(--interface-border);
            box-shadow: var(--shadow);
            z-index: 1000;
            overflow-y: auto;
            transition: transform 0.3s ease;
        }

        .sidebar-logo {
            padding: 24px 20px;
            text-align: center;
            background: linear-gradient(135deg, var(--authority-blue) 0%, #2a5298 100%);
            margin: 12px;
            border-radius: var(--radius);
        }

        .sidebar-logo img {
            max-width: 140px;
            height: auto;
            filter: brightness(0) invert(1);
        }

        #sidebar a {
            color: var(--authority-blue);
            margin: 6px 12px;
            padding: 12px 16px;
            border-radius: var(--radius);
            transition: all 0.2s ease;
            border: none;
            font-weight: 500;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        #sidebar a:hover {
            background: rgba(27, 63, 114, 0.1);
            color: var(--authority-blue);
            transform: translateX(4px);
        }

        #sidebar a.active {
            background: rgba(27, 63, 114, 0.15);
            color: var(--authority-blue);
        }

        /* Soft UI Header */
        .topbar {
            position: fixed;
            top: 0;
            left: 260px;
            right: 0;
            background: var(--pure-white);
            color: var(--authority-blue);
            border-bottom: 1px solid var(--interface-border);
            box-shadow: var(--shadow-sm);
            padding: 16px 24px;
            font-weight: 600;
            z-index: 999;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        /* Main Container */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            margin-left: 260px;
            margin-top: 80px;
        }

        /* Page Header */
        .page-header {
            background: linear-gradient(135deg, var(--authority-blue) 0%, #2a5298 100%);
            color: white;
            padding: 40px;
            border-radius: var(--radius-lg);
            margin-bottom: 30px;
            box-shadow: var(--shadow-md);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .page-header h1 {
            font-size: 2rem;
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .page-header p {
            margin: 8px 0 0 0;
            opacity: 0.9;
            font-size: 1rem;
        }

        .header-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        /* Buttons */
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: var(--radius);
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .btn:hover::before {
            left: 100%;
        }

        .btn i {
            font-size: 16px;
            transition: transform 0.3s ease;
        }

        .btn:hover i {
            transform: scale(1.1);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--authority-blue) 0%, #2a5298 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(27, 63, 114, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(27, 63, 114, 0.4);
        }

        .btn-success {
            background: linear-gradient(135deg, var(--system-success) 0%, #20c997 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(44, 201, 144, 0.3);
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(44, 201, 144, 0.4);
        }

        .btn-warning {
            background: linear-gradient(135deg, var(--system-warning) 0%, #d97706 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(255, 193, 7, 0.3);
        }

        .btn-warning:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(255, 193, 7, 0.4);
        }

        .btn-secondary {
            background: linear-gradient(135deg, var(--secondary-text) 0%, #6b7280 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(126, 145, 179, 0.3);
        }

        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(126, 145, 179, 0.4);
        }

        .btn-sm {
            padding: 8px 16px;
            font-size: 12px;
        }

        /* Statistics Cards */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--pure-white);
            padding: 24px;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            border: 1px solid var(--interface-border);
            text-align: center;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--authority-blue) 0%, #2a5298 100%);
        }

        .stat-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--authority-blue);
            margin-bottom: 8px;
        }

        .stat-label {
            color: var(--secondary-text);
            font-size: 0.9rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Filters */
        .filters-container {
            background: var(--pure-white);
            padding: 30px;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            border: 1px solid var(--interface-border);
            margin-bottom: 30px;
        }

        .filters-row {
            display: flex;
            gap: 20px;
            align-items: flex-end;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
            flex: 1;
            min-width: 150px;
        }

        .filter-group label {
            font-weight: 600;
            color: var(--authority-blue);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .filter-group input, .filter-group select {
            padding: 12px 16px;
            border: 1px solid var(--interface-border);
            border-radius: var(--radius);
            font-size: 14px;
            transition: all 0.3s ease;
            background: var(--dashboard-light);
            color: var(--authority-blue);
        }

        .filter-group input:focus, .filter-group select:focus {
            outline: none;
            border-color: var(--authority-blue);
            box-shadow: 0 0 0 3px rgba(27, 63, 114, 0.1);
        }

        .filter-buttons {
            display: flex;
            gap: 12px;
            align-items: flex-end;
        }

        /* Table */
        .table-container {
            background: var(--pure-white);
            padding: 30px;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            border: 1px solid var(--interface-border);
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 16px 12px;
            text-align: left;
            border-bottom: 1px solid var(--interface-border);
        }

        th {
            background: var(--dashboard-light);
            font-weight: 700;
            color: var(--authority-blue);
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
            position: sticky;
            top: 0;
        }

        tr:hover {
            background: rgba(27, 63, 114, 0.05);
        }

        /* Status Badges */
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-available {
            background: linear-gradient(135deg, rgba(44, 201, 144, 0.2) 0%, rgba(32, 201, 151, 0.2) 100%);
            color: var(--system-success);
            border: 1px solid rgba(44, 201, 144, 0.3);
        }

        .status-assigned {
            background: linear-gradient(135deg, rgba(255, 193, 7, 0.2) 0%, rgba(217, 119, 6, 0.2) 100%);
            color: var(--system-warning);
            border: 1px solid rgba(255, 193, 7, 0.3);
        }

        .status-maintenance {
            background: linear-gradient(135deg, rgba(220, 53, 69, 0.2) 0%, rgba(214, 51, 108, 0.2) 100%);
            color: var(--system-error);
            border: 1px solid rgba(220, 53, 69, 0.3);
        }

        /* Device Info */
        .device-info {
            max-width: 200px;
        }

        .device-serial {
            font-weight: 700;
            color: var(--authority-blue);
            font-size: 0.95rem;
        }

        .device-date {
            font-size: 0.8rem;
            color: var(--secondary-text);
            margin-top: 4px;
        }

        /* Assigned Info */
        .assigned-info {
            font-size: 0.9rem;
        }

        .assigned-name {
            font-weight: 600;
            color: var(--authority-blue);
        }

        .assigned-date {
            font-size: 0.8rem;
            color: var(--secondary-text);
            margin-top: 4px;
        }

        .no-assignments {
            color: var(--secondary-text);
            font-style: italic;
            opacity: 0.7;
        }

        /* Actions */
        .actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }

            .page-header {
                padding: 30px 20px;
                flex-direction: column;
                text-align: center;
            }

            .header-actions {
                justify-content: center;
                width: 100%;
            }

            .filters-row {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-group {
                min-width: auto;
            }

            .filter-buttons {
                justify-content: center;
                margin-top: 20px;
            }

            .table-container {
                padding: 20px 10px;
            }

            th, td {
                padding: 12px 8px;
                font-size: 0.85rem;
            }

            .actions {
                flex-direction: column;
                gap: 4px;
            }

            .btn-sm {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <header class="topbar">
        <div style="display: flex; align-items: center; gap: 12px;">
            <i class="fa fa-box" style="font-size: 24px; color: var(--authority-blue);"></i>
            <span style="font-size: 18px; font-weight: 700;">VitalWear</span>
        </div>
        <div style="display: flex; align-items: center; gap: 8px; color: var(--authority-blue); font-weight: 500;">
            <i class="fa fa-user-circle" style="font-size: 20px; color: var(--authority-blue);"></i>
            <span><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
        </div>
    </header>

    <nav id="sidebar">
        <div class="sidebar-logo">
            <img src="../../../assets/logo.png" alt="VitalWear Logo">
        </div>
        <a href="dashboard.php"><i class="fa fa-gauge"></i> Dashboard</a>
        <a href="manage_responders.php"><i class="fa fa-user-md"></i> Manage Responders</a>
        <a href="manage_rescuers.php"><i class="fa fa-user-shield"></i> Manage Rescuers</a>
        <a href="register_device.php"><i class="fa fa-plus-circle"></i> Register Device</a>
        <a href="device_list.php" class="active"><i class="fa fa-box"></i> Device List</a>
        <a href="assign_device.php"><i class="fa fa-exchange-alt"></i> Assign Device</a>
        <a href="verify_return.php"><i class="fa fa-check-double"></i> Verify Return</a>
        <a href="reports/index.php"><i class="fa fa-chart-bar"></i> Reports</a>
        <a href="../../../api/auth/logout.php" class="btn btn-secondary">Logout</a>
    </nav>

    <main class="container">
        <header class="page-header">
            <div>
                <h1>
                    <i class="fa fa-box"></i>
                    Device List
                </h1>
                <p>View and manage all monitoring devices</p>
            </div>
            <div class="header-actions">
                <a href="register_device.php" class="btn btn-primary">
                    <i class="fa fa-plus-circle"></i> Register Device
                </a>
                <a href="assign_device.php" class="btn btn-success">
                    <i class="fa fa-exchange-alt"></i> Assign Device
                </a>
            </div>
        </header>

        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_devices; ?></div>
                <div class="stat-label">Total Devices</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $available_devices; ?></div>
                <div class="stat-label">Available</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $assigned_devices; ?></div>
                <div class="stat-label">Assigned</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $maintenance_devices; ?></div>
                <div class="stat-label">Maintenance</div>
            </div>
        </div>

        <div class="filters-container">
            <form method="GET" class="filters-row">
                <div class="filter-group">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Devices</option>
                        <option value="available" <?php echo $status_filter === 'available' ? 'selected' : ''; ?>>Available</option>
                        <option value="assigned" <?php echo $status_filter === 'assigned' ? 'selected' : ''; ?>>Assigned</option>
                        <option value="maintenance" <?php echo $status_filter === 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="search">Search</label>
                    <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="Serial number...">
                </div>
                
                <div class="filter-buttons">
                    <button type="submit" class="btn btn-primary">
                        <i class="fa fa-filter"></i> Filter
                    </button>
                </div>
            </form>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Device Info</th>
                        <th>Status</th>
                        <th>Assigned To</th>
                        <th>Assignment Date</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($devices)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 40px; color: var(--secondary-text);">
                                <div style="font-size: 48px; margin-bottom: 16px;">📦</div>
                                <div style="font-size: 1.1rem; font-weight: 500;">No devices found</div>
                                <div style="font-size: 0.9rem; opacity: 0.8;">No devices match your current criteria.</div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($devices as $device): ?>
                            <tr>
                                <td><?php echo $device['dev_id']; ?></td>
                                <td>
                                    <div class="device-info">
                                        <div class="device-serial"><?php echo htmlspecialchars($device['dev_serial']); ?></div>
                                        <div class="device-date">Created: <?php echo date('M j, Y', strtotime($device['created_at'])); ?></div>
                                    </div>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $device['dev_status']; ?>">
                                        <?php echo ucfirst($device['dev_status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($device['resp_id']): ?>
                                        <div class="assigned-info">
                                            <div class="assigned-name"><?php echo htmlspecialchars($device['resp_name']); ?></div>
                                            <div class="assigned-date"><?php echo htmlspecialchars($device['resp_email']); ?></div>
                                        </div>
                                    <?php else: ?>
                                        <span class="no-assignments">Not assigned</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($device['date_assigned']): ?>
                                        <?php echo date('M j, Y H:i', strtotime($device['date_assigned'])); ?>
                                    <?php else: ?>
                                        <span class="no-assignments">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('M j, Y', strtotime($device['created_at'])); ?></td>
                                <td>
                                    <div class="actions">
                                        <?php if ($device['dev_status'] === 'available'): ?>
                                            <a href="assign_device.php?device_id=<?php echo $device['dev_id']; ?>" class="btn btn-success btn-sm">
                                                <i class="fa fa-user-plus"></i> Assign
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if ($device['dev_status'] === 'assigned' && $device['resp_id']): ?>
                                            <a href="verify_return.php?device_id=<?php echo $device['dev_id']; ?>" class="btn btn-warning btn-sm">
                                                <i class="fa fa-undo"></i> Return
                                            </a>
                                        <?php endif; ?>
                                        
                                        <a href="register_device.php?edit=<?php echo $device['dev_id']; ?>" class="btn btn-secondary btn-sm">
                                            <i class="fa fa-edit"></i> Edit
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</body>
</html>
