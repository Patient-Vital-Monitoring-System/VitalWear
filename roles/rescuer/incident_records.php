<?php
require_once 'session_check.php';
require_once '../../database/connection.php';

$rescuer_id = $_SESSION['user_id'];
$conn = getDBConnection();

// Get rescuer info
$rescuer_query = "SELECT resc_name FROM rescuer WHERE resc_id = ?";
$stmt = $conn->prepare($rescuer_query);
$stmt->bind_param("i", $rescuer_id);
$stmt->execute();
$rescuer = $stmt->get_result()->fetch_assoc();

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build base query
$base_query = "SELECT i.incident_id, i.start_time, i.end_time, i.status, p.pat_name, p.birthdate, p.contact_number,
               r.resp_name, r.resp_contact, COUNT(v.vital_id) as vital_count,
               MIN(v.recorded_at) as first_vital, MAX(v.recorded_at) as last_vital
               FROM incident i 
               JOIN patient p ON i.pat_id = p.pat_id 
               JOIN responder r ON i.resp_id = r.resp_id 
               LEFT JOIN vitalstat v ON i.incident_id = v.incident_id
               WHERE i.resc_id = ?";

$params = [$rescuer_id];
$types = "i";

// Add status filter
if ($status_filter !== 'all') {
    $base_query .= " AND i.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

// Add date filters
if (!empty($date_from)) {
    $base_query .= " AND i.start_time >= ?";
    $params[] = $date_from . ' 00:00:00';
    $types .= "s";
}

if (!empty($date_to)) {
    $base_query .= " AND i.start_time <= ?";
    $params[] = $date_to . ' 23:59:59';
    $types .= "s";
}

$base_query .= " GROUP BY i.incident_id, i.start_time, i.end_time, i.status, p.pat_name, p.birthdate, p.contact_number, r.resp_name, r.resp_contact
                ORDER BY i.start_time DESC";

$stmt = $conn->prepare($base_query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$incidents = $stmt->get_result();

// Get overall statistics
$stats_query = "SELECT 
                COUNT(CASE WHEN status = 'transferred' THEN 1 END) as transferred,
                COUNT(CASE WHEN status = 'ongoing' THEN 1 END) as ongoing,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed,
                COUNT(*) as total
                FROM incident WHERE resc_id = ?";
$stmt = $conn->prepare($stats_query);
$stmt->bind_param("i", $rescuer_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Incident Records - VitalWear</title>
    <link rel="stylesheet" href="../../../assets/css/styles.css">
    <style>
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            position: relative;
        }
        .back-btn {
            position: absolute;
            top: 20px;
            left: 20px;
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 10px 20px;
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: 5px;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        .back-btn:hover {
            background: rgba(255,255,255,0.3);
        }
        .stats-grid {
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
            font-size: 2em;
            font-weight: bold;
            color: #667eea;
        }
        .stat-label {
            color: #718096;
            margin-top: 5px;
        }
        .filters-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
        }
        .form-group {
            display: flex;
            flex-direction: column;
        }
        .form-group label {
            font-weight: 600;
            color: #4a5568;
            margin-bottom: 5px;
        }
        .form-group select,
        .form-group input {
            padding: 10px;
            border: 1px solid #cbd5e0;
            border-radius: 5px;
            font-size: 14px;
        }
        .form-group select:focus,
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        .filter-btn {
            background: #667eea;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        .filter-btn:hover {
            background: #5a67d8;
            transform: translateY(-2px);
        }
        .reset-btn {
            background: #718096;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        .reset-btn:hover {
            background: #4a5568;
            transform: translateY(-2px);
        }
        .records-table {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .table-wrapper {
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        th {
            background: #f7fafc;
            font-weight: 600;
            color: #4a5568;
            position: sticky;
            top: 0;
        }
        tr:hover {
            background: #f7fafc;
        }
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .status-transferred {
            background: #bee3f8;
            color: #2c5282;
        }
        .status-ongoing {
            background: #c6f6d5;
            color: #22543d;
        }
        .status-completed {
            background: #fed7d7;
            color: #742a2a;
        }
        .action-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            text-decoration: none;
            display: inline-block;
            margin-right: 5px;
            transition: all 0.3s ease;
        }
        .view-btn {
            background: #4299e1;
            color: white;
        }
        .vitals-btn {
            background: #48bb78;
            color: white;
        }
        .report-btn {
            background: #ed8936;
            color: white;
        }
        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }
        .no-records {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .no-records h3 {
            color: #718096;
            margin-bottom: 10px;
        }
        .duration {
            font-weight: 600;
            color: #38a169;
        }
        .vital-count {
            font-weight: 600;
            color: #667eea;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>
            <h1>📁 Incident Records</h1>
            <p>View full monitoring history of all handled incidents</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total']; ?></div>
                <div class="stat-label">📋 Total Incidents</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['transferred']; ?></div>
                <div class="stat-label">📥 Transferred</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['ongoing']; ?></div>
                <div class="stat-label">❤️ Ongoing</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['completed']; ?></div>
                <div class="stat-label">✅ Completed</div>
            </div>
        </div>

        <div class="filters-section">
            <h3>🔍 Filter Records</h3>
            <form method="GET" class="filter-form">
                <div class="form-group">
                    <label for="status">Status</label>
                    <select name="status" id="status">
                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="transferred" <?php echo $status_filter === 'transferred' ? 'selected' : ''; ?>>Transferred</option>
                        <option value="ongoing" <?php echo $status_filter === 'ongoing' ? 'selected' : ''; ?>>Ongoing</option>
                        <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="date_from">Date From</label>
                    <input type="date" name="date_from" id="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                </div>
                <div class="form-group">
                    <label for="date_to">Date To</label>
                    <input type="date" name="date_to" id="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                </div>
                <div class="form-group">
                    <button type="submit" class="filter-btn">Apply Filters</button>
                </div>
                <div class="form-group">
                    <a href="incident_records.php" class="reset-btn">Reset</a>
                </div>
            </form>
        </div>

        <?php if ($incidents->num_rows > 0): ?>
            <div class="records-table">
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Incident ID</th>
                                <th>Patient</th>
                                <th>Responder</th>
                                <th>Status</th>
                                <th>Start Time</th>
                                <th>End Time</th>
                                <th>Duration</th>
                                <th>Vital Records</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($incident = $incidents->fetch_assoc()): 
                                $duration = '';
                                if ($incident['start_time'] && $incident['end_time']) {
                                    $seconds = strtotime($incident['end_time']) - strtotime($incident['start_time']);
                                    $hours = floor($seconds / 3600);
                                    $minutes = floor(($seconds % 3600) / 60);
                                    $duration = $hours > 0 ? "{$hours}h {$minutes}m" : "{$minutes}m";
                                } elseif ($incident['status'] === 'ongoing') {
                                    $seconds = time() - strtotime($incident['start_time']);
                                    $hours = floor($seconds / 3600);
                                    $minutes = floor(($seconds % 3600) / 60);
                                    $duration = $hours > 0 ? "{$hours}h {$minutes}m" : "{$minutes}m";
                                }
                            ?>
                                <tr>
                                    <td><strong>#<?php echo $incident['incident_id']; ?></strong></td>
                                    <td>
                                        <?php echo htmlspecialchars($incident['pat_name']); ?><br>
                                        <small><?php echo date('Y') - date('Y', strtotime($incident['birthdate'])); ?> years</small>
                                    </td>
                                    <td><?php echo htmlspecialchars($incident['resp_name']); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $incident['status']; ?>">
                                            <?php echo $incident['status']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M j, Y H:i', strtotime($incident['start_time'])); ?></td>
                                    <td>
                                        <?php echo $incident['end_time'] ? date('M j, Y H:i', strtotime($incident['end_time'])) : '-'; ?>
                                    </td>
                                    <td class="duration"><?php echo $duration ?: '-'; ?></td>
                                    <td class="vital-count"><?php echo $incident['vital_count']; ?></td>
                                    <td>
                                        <a href="view_incident_details.php?id=<?php echo $incident['incident_id']; ?>" class="action-btn view-btn">View</a>
                                        <?php if ($incident['vital_count'] > 0): ?>
                                            <a href="case_vitals_history.php?id=<?php echo $incident['incident_id']; ?>" class="action-btn vitals-btn">Vitals</a>
                                        <?php endif; ?>
                                        <?php if ($incident['status'] === 'completed'): ?>
                                            <a href="generate_case_report.php?id=<?php echo $incident['incident_id']; ?>" class="action-btn report-btn">Report</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php else: ?>
            <div class="no-records">
                <h3>📁 No Incident Records Found</h3>
                <p>No incident records match your current filters.</p>
                <a href="incident_records.php" class="filter-btn" style="margin-top: 20px;">Clear Filters</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
