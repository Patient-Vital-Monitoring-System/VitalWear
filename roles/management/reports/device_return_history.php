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
$device_filter = $_GET['device'] ?? 'all';
$verification_filter = $_GET['verification'] ?? 'all';

// Build query conditions
$where_conditions = ["dl.date_returned IS NOT NULL AND dl.date_returned BETWEEN ? AND ?"];
$params = [$date_from, $date_to . ' 23:59:59'];
$types = 'ss';

if ($responder_filter !== 'all') {
    $where_conditions[] = "dl.resp_id = ?";
    $params[] = $responder_filter;
    $types .= 'i';
}

if ($device_filter !== 'all') {
    $where_conditions[] = "dl.dev_id = ?";
    $params[] = $device_filter;
    $types .= 'i';
}

if ($verification_filter !== 'all') {
    $where_conditions[] = "dl.verified_return = ?";
    $params[] = $verification_filter === 'verified' ? 1 : 0;
    $types .= 'i';
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Get return history
$query = "
    SELECT dl.*, d.dev_serial, r.resp_name, r.resp_email, m.mgmt_name,
           CASE 
               WHEN dl.verified_return = 1 THEN 'Verified'
               ELSE 'Pending Verification'
           END as verification_status
    FROM device_log dl
    JOIN device d ON dl.dev_id = d.dev_id
    JOIN responder r ON dl.resp_id = r.resp_id
    JOIN management m ON dl.mgmt_id = m.mgmt_id
    $where_clause
    ORDER BY dl.date_returned DESC
";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$returns = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get filters data
$responders = [];
$result = $conn->query("SELECT resp_id, resp_name FROM responder ORDER BY resp_name");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $responders[] = $row;
    }
}

$devices = [];
$result = $conn->query("SELECT dev_id, dev_serial FROM device ORDER BY dev_serial");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $devices[] = $row;
    }
}

// Calculate statistics
$total_returns = count($returns);
$verified_returns = 0;
$pending_verification = 0;
$avg_duration = 0;

foreach ($returns as $return) {
    if ($return['verified_return']) {
        $verified_returns++;
    } else {
        $pending_verification++;
    }
}

// Calculate average duration
$durations = [];
foreach ($returns as $return) {
    $assigned = new DateTime($return['date_assigned']);
    $returned = new DateTime($return['date_returned']);
    $durations[] = $assigned->diff($returned)->days;
}

if (!empty($durations)) {
    $avg_duration = round(array_sum($durations) / count($durations), 1);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Device Return History - VitalWear</title>
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
        
        .status-verified { background: #d4edda; color: #155724; }
        .status-pending { background: #fff3cd; color: #856404; }
        
        .duration {
            background: #e3f2fd;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            color: #1565c0;
            font-weight: bold;
        }
        
        .verification-actions {
            display: flex;
            gap: 5px;
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
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="page-header">
            <div>
                <h1 style="margin: 0;">📊 Device Return History</h1>
                <p style="margin: 5px 0 0 0; opacity: 0.9;">View and analyze device return records</p>
            </div>
            <div>
                <a href="../dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
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
                    <label for="device">Device:</label>
                    <select id="device" name="device">
                        <option value="all" <?php echo $device_filter === 'all' ? 'selected' : ''; ?>>All Devices</option>
                        <?php foreach ($devices as $device): ?>
                            <option value="<?php echo $device['dev_id']; ?>" 
                                    <?php echo $device_filter == $device['dev_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($device['dev_serial']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="verification">Verification Status:</label>
                    <select id="verification" name="verification">
                        <option value="all" <?php echo $verification_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="verified" <?php echo $verification_filter === 'verified' ? 'selected' : ''; ?>>Verified</option>
                        <option value="pending" <?php echo $verification_filter === 'pending' ? 'selected' : ''; ?>>Pending Verification</option>
                    </select>
                </div>
                
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                    <a href="device_return_history.php" class="btn btn-secondary">Clear</a>
                </div>
            </form>
        </div>

        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_returns; ?></div>
                <div class="stat-label">Total Returns</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $verified_returns; ?></div>
                <div class="stat-label">Verified Returns</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $pending_verification; ?></div>
                <div class="stat-label">Pending Verification</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $avg_duration; ?></div>
                <div class="stat-label">Avg. Duration (days)</div>
            </div>
        </div>

        <div class="export-section">
            <button class="btn btn-success" onclick="exportToCSV()">📥 Export to CSV</button>
        </div>

        <div class="table-container">
            <table id="returnsTable">
                <thead>
                    <tr>
                        <th>Device Serial</th>
                        <th>Responder Name</th>
                        <th>Responder Email</th>
                        <th>Assigned By</th>
                        <th>Assignment Date</th>
                        <th>Return Date</th>
                        <th>Duration</th>
                        <th>Verification Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($returns)): ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 40px; color: #666;">
                                No return records found matching your criteria.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($returns as $return): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($return['dev_serial']); ?></td>
                                <td><?php echo htmlspecialchars($return['resp_name']); ?></td>
                                <td><?php echo htmlspecialchars($return['resp_email']); ?></td>
                                <td><?php echo htmlspecialchars($return['mgmt_name']); ?></td>
                                <td><?php echo date('M j, Y H:i', strtotime($return['date_assigned'])); ?></td>
                                <td><?php echo date('M j, Y H:i', strtotime($return['date_returned'])); ?></td>
                                <td>
                                    <?php 
                                    $assigned = new DateTime($return['date_assigned']);
                                    $returned = new DateTime($return['date_returned']);
                                    $duration = $assigned->diff($returned)->days;
                                    echo "<span class='duration'>$duration days</span>";
                                    ?>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $return['verification_status'])); ?>">
                                        <?php echo $return['verification_status']; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="verification-actions">
                                        <?php if (!$return['verified_return']): ?>
                                            <a href="../verify_return.php?device_id=<?php echo $return['dev_id']; ?>" class="btn btn-warning">Verify</a>
                                        <?php else: ?>
                                            <span class="status-badge status-verified">✓ Verified</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        function exportToCSV() {
            const table = document.getElementById('returnsTable');
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
                    // Skip actions column for export
                    if (j === 8) continue;
                    
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
            a.setAttribute('download', 'device_return_history_<?php echo date('Y-m-d'); ?>.csv');
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        }
    </script>
</body>
</html>
