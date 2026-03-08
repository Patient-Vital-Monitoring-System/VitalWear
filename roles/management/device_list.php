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
    <link rel="stylesheet" href="../../../assets/css/styles.css">
    <style>
        .page-header {
            background: #007bff;
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: background-color 0.2s;
        }
        
        .btn-primary { background: #007bff; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-warning { background: #ffc107; color: black; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        
        .btn:hover { opacity: 0.9; }
        
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-number {
            font-size: 32px;
            font-weight: bold;
            color: #007bff;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #666;
            font-size: 14px;
        }
        
        .filters-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .filters-row {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .filter-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .filter-group label {
            font-weight: bold;
            color: #333;
        }
        
        .filter-group input, .filter-group select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        
        .table-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        th {
            background: #f8f9fa;
            font-weight: bold;
            position: sticky;
            top: 0;
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status-available { background: #d4edda; color: #155724; }
        .status-assigned { background: #fff3cd; color: #856404; }
        .status-maintenance { background: #f8d7da; color: #721c24; }
        
        .device-info {
            max-width: 200px;
        }
        
        .device-serial {
            font-weight: bold;
            color: #333;
        }
        
        .device-date {
            font-size: 12px;
            color: #666;
        }
        
        .assigned-info {
            font-size: 14px;
        }
        
        .assigned-name {
            font-weight: bold;
            color: #333;
        }
        
        .assigned-date {
            font-size: 12px;
            color: #666;
        }
        
        .actions {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        
        .no-assignments {
            color: #999;
            font-style: italic;
        }
        
        .search-box {
            min-width: 200px;
        }
        
        @media (max-width: 768px) {
            .filters-row {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filter-group {
                flex-direction: column;
                align-items: stretch;
            }
            
            .search-box {
                min-width: auto;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="page-header">
            <div>
                <h1 style="margin: 0;">📦 Device List</h1>
                <p style="margin: 5px 0 0 0; opacity: 0.9;">View and manage all monitoring devices</p>
            </div>
            <div>
                <a href="dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
                <a href="register_device.php" class="btn btn-primary">+ Register Device</a>
                <a href="assign_device.php" class="btn btn-success">🔁 Assign Device</a>
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
                    <label for="status">Status:</label>
                    <select id="status" name="status">
                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Devices</option>
                        <option value="available" <?php echo $status_filter === 'available' ? 'selected' : ''; ?>>Available</option>
                        <option value="assigned" <?php echo $status_filter === 'assigned' ? 'selected' : ''; ?>>Assigned</option>
                        <option value="maintenance" <?php echo $status_filter === 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                    </select>
                </div>
                
                <div class="filter-group search-box">
                    <label for="search">Search:</label>
                    <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="Serial number...">
                </div>
                
                <div class="filter-group">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="device_list.php" class="btn btn-secondary">Clear</a>
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
                            <td colspan="7" style="text-align: center; padding: 40px; color: #666;">
                                No devices found matching your criteria.
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
                                            <a href="assign_device.php?device_id=<?php echo $device['dev_id']; ?>" class="btn btn-success">Assign</a>
                                        <?php endif; ?>
                                        
                                        <?php if ($device['dev_status'] === 'assigned' && $device['resp_id']): ?>
                                            <a href="verify_return.php?device_id=<?php echo $device['dev_id']; ?>" class="btn btn-warning">Return</a>
                                        <?php endif; ?>
                                        
                                        <a href="register_device.php?edit=<?php echo $device['dev_id']; ?>" class="btn btn-secondary">Edit</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
