<?php
session_start();
require_once '../../../database/connection.php';

// Check if management user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'management') {
    header('Location: ../../login.html');
    exit();
}

$conn = getDBConnection();

// Handle filtering
$date_from = $_GET['date_from'] ?? date('Y-m-01'); // First day of current month
$date_to = $_GET['date_to'] ?? date('Y-m-d'); // Today
$responder_filter = $_GET['responder'] ?? 'all';
$activity_filter = $_GET['activity'] ?? 'all';

// Build query conditions
$where_conditions = [];
$params = [];
$types = '';

if ($date_from && $date_to) {
    $where_conditions[] = "DATE(al.created_at) BETWEEN ? AND ?";
    $params[] = $date_from;
    $params[] = $date_to;
    $types .= 'ss';
}

if ($responder_filter !== 'all') {
    $where_conditions[] = "r.resp_id = ?";
    $params[] = $responder_filter;
    $types .= 'i';
}

if ($activity_filter !== 'all') {
    $where_conditions[] = "al.action_type = ?";
    $params[] = $activity_filter;
    $types .= 's';
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Get responder activities
$query = "
    SELECT al.*, r.resp_name, r.resp_email, r.status as responder_status
    FROM activity_log al
    LEFT JOIN responder r ON (al.user_role = 'responder' AND al.user_name = r.resp_name)
    $where_clause
    ORDER BY al.created_at DESC
";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$activities = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get filters data
$responders = [];
$result = $conn->query("SELECT resp_id, resp_name FROM responder ORDER BY resp_name");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $responders[] = $row;
    }
}

// Get activity types
$activity_types = [];
$result = $conn->query("SELECT DISTINCT action_type FROM activity_log WHERE user_role = 'responder' ORDER BY action_type");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $activity_types[] = $row['action_type'];
    }
}

// Calculate statistics
$total_activities = count($activities);
$active_responders = 0;
$unique_responders = [];
$activity_counts = [];

foreach ($activities as $activity) {
    if ($activity['resp_name'] && $activity['responder_status'] === 'active') {
        $active_responders++;
        $unique_responders[$activity['resp_name']] = true;
    }
    
    $action = $activity['action_type'];
    if (!isset($activity_counts[$action])) {
        $activity_counts[$action] = 0;
    }
    $activity_counts[$action]++;
}

$unique_responder_count = count($unique_responders);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Responder Activity Report - VitalWear</title>
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
        
        .filters-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .filters-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
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
        
        .filter-actions {
            display: flex;
            gap: 10px;
            align-items: end;
        }
        
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
        
        .activity-breakdown {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .activity-chart {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
        }
        
        .activity-item {
            text-align: center;
            padding: 15px;
            border: 1px solid #eee;
            border-radius: 8px;
        }
        
        .activity-count {
            font-size: 24px;
            font-weight: bold;
            color: #007bff;
        }
        
        .activity-type {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
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
        }
        
        .status-active { background: #d4edda; color: #155724; }
        .status-inactive { background: #f8d7da; color: #721c24; }
        
        .action-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
            background: #e3f2fd;
            color: #1565c0;
        }
        
        .export-section {
            text-align: right;
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .filters-row {
                grid-template-columns: 1fr;
            }
            
            .filter-actions {
                flex-direction: column;
            }
            
            .activity-chart {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="page-header">
            <div>
                <h1 style="margin: 0;">📊 Responder Activity Report</h1>
                <p style="margin: 5px 0 0 0; opacity: 0.9;">Monitor and analyze responder activities</p>
            </div>
            <div>
                <a href="index.php" class="btn btn-primary">All Reports</a>
            </div>
        </header>

        <div class="filters-container">
            <form method="GET" class="filters-row">
                <div class="filter-group">
                    <label for="date_from">From Date:</label>
                    <input type="date" id="date_from" name="date_from" value="<?php echo $date_from; ?>" required>
                </div>
                
                <div class="filter-group">
                    <label for="date_to">To Date:</label>
                    <input type="date" id="date_to" name="date_to" value="<?php echo $date_to; ?>" required>
                </div>
                
                <div class="filter-group">
                    <label for="responder">Responder:</label>
                    <select id="responder" name="responder">
                        <option value="all" <?php echo $responder_filter === 'all' ? 'selected' : ''; ?>>All Responders</option>
                        <?php foreach ($responders as $responder): ?>
                            <option value="<?php echo $responder['resp_id']; ?>" 
                                    <?php echo $responder_filter == $responder['resp_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($responder['resp_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="activity">Activity Type:</label>
                    <select id="activity" name="activity">
                        <option value="all" <?php echo $activity_filter === 'all' ? 'selected' : ''; ?>>All Activities</option>
                        <?php foreach ($activity_types as $type): ?>
                            <option value="<?php echo $type; ?>" 
                                    <?php echo $activity_filter == $type ? 'selected' : ''; ?>>
                                <?php echo ucfirst($type); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                    <a href="responder_activity.php" class="btn btn-secondary">Clear</a>
                </div>
            </form>
        </div>

        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_activities; ?></div>
                <div class="stat-label">Total Activities</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $unique_responder_count; ?></div>
                <div class="stat-label">Active Responders</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count($activity_counts); ?></div>
                <div class="stat-label">Activity Types</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_activities > 0 ? round($total_activities / max($unique_responder_count, 1), 1) : 0; ?></div>
                <div class="stat-label">Avg. Activities/Responder</div>
            </div>
        </div>

        <?php if (!empty($activity_counts)): ?>
        <div class="activity-breakdown">
            <h3>Activity Breakdown</h3>
            <div class="activity-chart">
                <?php foreach ($activity_counts as $action => $count): ?>
                    <div class="activity-item">
                        <div class="activity-count"><?php echo $count; ?></div>
                        <div class="activity-type"><?php echo ucfirst($action); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="export-section">
            <button class="btn btn-success" onclick="exportToCSV()">📥 Export to CSV</button>
        </div>

        <div class="table-container">
            <table id="activitiesTable">
                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <th>Responder Name</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Action Type</th>
                        <th>Module</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($activities)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 40px; color: #666;">
                                No activity records found matching your criteria.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($activities as $activity): ?>
                            <tr>
                                <td><?php echo date('M j, Y H:i:s', strtotime($activity['created_at'])); ?></td>
                                <td><?php echo htmlspecialchars($activity['user_name'] ?? 'Unknown'); ?></td>
                                <td><?php echo htmlspecialchars($activity['resp_email'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php if ($activity['responder_status']): ?>
                                        <span class="status-badge status-<?php echo $activity['responder_status']; ?>">
                                            <?php echo ucfirst($activity['responder_status']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="status-badge status-inactive">Unknown</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="action-badge">
                                        <?php echo ucfirst($activity['action_type'] ?? 'Unknown'); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($activity['module'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($activity['description'] ?? 'N/A'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        function exportToCSV() {
            const table = document.getElementById('activitiesTable');
            let csv = [];
            
            // Get headers
            const headers = [];
            for (let i = 0; i < table.rows[0].cells.length; i++) {
                headers.push(table.rows[0].cells[i].textContent);
            }
            csv.push(headers.join(','));
            
            // Get data rows
            for (let i = 1; i < table.rows.length; i++) {
                const row = [];
                for (let j = 0; j < table.rows[i].cells.length; j++) {
                    // Clean up text content and remove commas
                    let cellText = table.rows[i].cells[j].textContent.trim();
                    cellText = cellText.replace(/,/g, '');
                    row.push(cellText);
                }
                csv.push(row.join(','));
            }
            
            // Create download link
            const csvContent = csv.join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.setAttribute('hidden', '');
            a.setAttribute('href', url);
            a.setAttribute('download', 'responder_activity_report_<?php echo date('Y-m-d'); ?>.csv');
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        }
    </script>
</body>
</html>
