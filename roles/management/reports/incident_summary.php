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
$status_filter = $_GET['status'] ?? 'all';
$responder_filter = $_GET['responder'] ?? 'all';
$rescuer_filter = $_GET['rescuer'] ?? 'all';

// Build query conditions
$where_conditions = ["i.start_time BETWEEN ? AND ?"];
$params = [$date_from, $date_to . ' 23:59:59'];
$types = 'ss';

if ($status_filter !== 'all') {
    $where_conditions[] = "i.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if ($responder_filter !== 'all') {
    $where_conditions[] = "i.resp_id = ?";
    $params[] = $responder_filter;
    $types .= 'i';
}

if ($rescuer_filter !== 'all') {
    $where_conditions[] = "i.resc_id = ?";
    $params[] = $rescuer_filter;
    $types .= 'i';
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Get incident summary
$query = "
    SELECT i.*, p.pat_name, p.birthdate, p.contact_number,
           r.resp_name as responder_name, r.resp_email as responder_email,
           rc.resc_name as rescuer_name, rc.resc_email as rescuer_email,
           d.dev_serial,
           TIMESTAMPDIFF(HOUR, i.start_time, COALESCE(i.end_time, NOW())) as duration_hours,
           TIMESTAMPDIFF(DAY, i.start_time, COALESCE(i.end_time, NOW())) as duration_days
    FROM incident i
    JOIN patient p ON i.pat_id = p.pat_id
    JOIN device_log dl ON i.log_id = dl.log_id
    JOIN device d ON dl.dev_id = d.dev_id
    JOIN responder r ON i.resp_id = r.resp_id
    LEFT JOIN rescuer rc ON i.resc_id = rc.resc_id
    $where_clause
    ORDER BY i.start_time DESC
";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$incidents = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get filters data
$responders = [];
$result = $conn->query("SELECT resp_id, resp_name FROM responder ORDER BY resp_name");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $responders[] = $row;
    }
}

$rescuers = [];
$result = $conn->query("SELECT resc_id, resc_name FROM rescuer ORDER BY resc_name");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $rescuers[] = $row;
    }
}

// Calculate statistics
$total_incidents = count($incidents);
$ongoing_incidents = 0;
$completed_incidents = 0;
$transferred_incidents = 0;
$avg_duration = 0;
$total_vital_readings = 0;

foreach ($incidents as $incident) {
    switch ($incident['status']) {
        case 'ongoing': $ongoing_incidents++; break;
        case 'completed': $completed_incidents++; break;
        case 'transferred': $transferred_incidents++; break;
    }
}

// Calculate average duration for completed incidents
$durations = [];
foreach ($incidents as $incident) {
    if ($incident['end_time']) {
        $start = new DateTime($incident['start_time']);
        $end = new DateTime($incident['end_time']);
        $durations[] = $start->diff($end)->hours;
    }
}

if (!empty($durations)) {
    $avg_duration = round(array_sum($durations) / count($durations), 1);
}

// Get vital readings count
foreach ($incidents as $incident) {
    $vital_result = $conn->query("SELECT COUNT(*) as count FROM vitalstat WHERE incident_id = {$incident['incident_id']}");
    if ($vital_result) {
        $total_vital_readings += $vital_result->fetch_assoc()['count'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Incident Summary Report - VitalWear</title>
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
        
        .status-breakdown {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .status-chart {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
        }
        
        .status-item {
            text-align: center;
            padding: 15px;
            border: 1px solid #eee;
            border-radius: 8px;
        }
        
        .status-count {
            font-size: 24px;
            font-weight: bold;
            color: #007bff;
        }
        
        .status-type {
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
        
        .status-ongoing { background: #fff3cd; color: #856404; }
        .status-completed { background: #d4edda; color: #155724; }
        .status-transferred { background: #d1ecf1; color: #0c5460; }
        
        .duration {
            background: #e3f2fd;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            color: #1565c0;
            font-weight: bold;
        }
        
        .patient-info {
            font-size: 14px;
        }
        
        .patient-name {
            font-weight: bold;
            color: #333;
        }
        
        .patient-details {
            font-size: 12px;
            color: #666;
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
            
            .status-chart {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="page-header">
            <div>
                <h1 style="margin: 0;">📊 Incident Summary Report</h1>
                <p style="margin: 5px 0 0 0; opacity: 0.9;">Comprehensive incident analysis and statistics</p>
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
                    <label for="status">Status:</label>
                    <select id="status" name="status">
                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="ongoing" <?php echo $status_filter === 'ongoing' ? 'selected' : ''; ?>>Ongoing</option>
                        <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="transferred" <?php echo $status_filter === 'transferred' ? 'selected' : ''; ?>>Transferred</option>
                    </select>
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
                    <label for="rescuer">Rescuer:</label>
                    <select id="rescuer" name="rescuer">
                        <option value="all" <?php echo $rescuer_filter === 'all' ? 'selected' : ''; ?>>All Rescuers</option>
                        <?php foreach ($rescuers as $rescuer): ?>
                            <option value="<?php echo $rescuer['resc_id']; ?>" 
                                    <?php echo $rescuer_filter == $rescuer['resc_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($rescuer['resc_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                    <a href="incident_summary.php" class="btn btn-secondary">Clear</a>
                </div>
            </form>
        </div>

        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_incidents; ?></div>
                <div class="stat-label">Total Incidents</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $ongoing_incidents; ?></div>
                <div class="stat-label">Ongoing</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $completed_incidents; ?></div>
                <div class="stat-label">Completed</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $avg_duration; ?></div>
                <div class="stat-label">Avg. Duration (hours)</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_vital_readings; ?></div>
                <div class="stat-label">Total Vital Readings</div>
            </div>
        </div>

        <div class="status-breakdown">
            <h3>Incident Status Breakdown</h3>
            <div class="status-chart">
                <div class="status-item">
                    <div class="status-count"><?php echo $ongoing_incidents; ?></div>
                    <div class="status-type">Ongoing</div>
                </div>
                <div class="status-item">
                    <div class="status-count"><?php echo $completed_incidents; ?></div>
                    <div class="status-type">Completed</div>
                </div>
                <div class="status-item">
                    <div class="status-count"><?php echo $transferred_incidents; ?></div>
                    <div class="status-type">Transferred</div>
                </div>
                <div class="status-item">
                    <div class="status-count"><?php echo $total_incidents; ?></div>
                    <div class="status-type">Total</div>
                </div>
            </div>
        </div>

        <div class="export-section">
            <button class="btn btn-success" onclick="exportToCSV()">📥 Export to CSV</button>
        </div>

        <div class="table-container">
            <table id="incidentsTable">
                <thead>
                    <tr>
                        <th>Incident ID</th>
                        <th>Patient Info</th>
                        <th>Responder</th>
                        <th>Rescuer</th>
                        <th>Device Serial</th>
                        <th>Start Time</th>
                        <th>End Time</th>
                        <th>Duration</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($incidents)): ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 40px; color: #666;">
                                No incident records found matching your criteria.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($incidents as $incident): ?>
                            <tr>
                                <td>#<?php echo $incident['incident_id']; ?></td>
                                <td>
                                    <div class="patient-info">
                                        <div class="patient-name"><?php echo htmlspecialchars($incident['pat_name']); ?></div>
                                        <div class="patient-details">
                                            DOB: <?php echo date('M j, Y', strtotime($incident['birthdate'])); ?><br>
                                            Contact: <?php echo htmlspecialchars($incident['contact_number'] ?: 'N/A'); ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="patient-info">
                                        <div class="patient-name"><?php echo htmlspecialchars($incident['responder_name']); ?></div>
                                        <div class="patient-details"><?php echo htmlspecialchars($incident['responder_email']); ?></div>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($incident['rescuer_name']): ?>
                                        <div class="patient-info">
                                            <div class="patient-name"><?php echo htmlspecialchars($incident['rescuer_name']); ?></div>
                                            <div class="patient-details"><?php echo htmlspecialchars($incident['rescuer_email']); ?></div>
                                        </div>
                                    <?php else: ?>
                                        <span style="color: #999;">Not assigned</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($incident['dev_serial']); ?></td>
                                <td><?php echo date('M j, Y H:i', strtotime($incident['start_time'])); ?></td>
                                <td>
                                    <?php 
                                    echo $incident['end_time'] 
                                        ? date('M j, Y H:i', strtotime($incident['end_time'])) 
                                        : '-'; 
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                    if ($incident['duration_hours']) {
                                        echo "<span class='duration'>{$incident['duration_hours']}h</span>";
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $incident['status']; ?>">
                                        <?php echo ucfirst($incident['status']); ?>
                                    </span>
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
            const table = document.getElementById('incidentsTable');
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
            a.setAttribute('download', 'incident_summary_report_<?php echo date('Y-m-d'); ?>.csv');
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        }
    </script>
</body>
</html>
