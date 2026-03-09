<?php
require_once '../../database/connection.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'rescuer') {
    header("Location: ../../login.html");
    exit;
}

$rescuer_id = $_SESSION['user_id'];
$conn = getDBConnection();

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
    $base_query .= " AND DATE(i.start_time) >= ?";
    $params[] = $date_from;
    $types .= "s";
}
if (!empty($date_to)) {
    $base_query .= " AND DATE(i.start_time) <= ?";
    $params[] = $date_to;
    $types .= "s";
}

$base_query .= " GROUP BY i.incident_id, i.start_time, i.end_time, i.status, p.pat_name, p.birthdate, p.contact_number, r.resp_name, r.resp_contact
                ORDER BY i.start_time DESC";

$stmt = $conn->prepare($base_query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$incidents = $stmt->get_result();

// Get statistics
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
    <link rel="stylesheet" href="../../assets/css/styles.css">
    <script src="https://kit.fontawesome.com/96e37b53f1.js"></script>
</head>
<body>

<header class="topbar">
Rescuer: <?php echo isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Emergency Response'; ?>
</header>

<nav id="sidebar">
<a href="dashboard.php"><i class="fa fa-gauge"></i> Dashboard</a>
<a href="transferred_incidents.php"><i class="fa fa-exclamation-circle"></i> Transferred Incidents</a>
<a href="ongoing_monitoring.php"><i class="fa fa-heart-pulse"></i> Ongoing Monitoring</a>
<a href="completed_cases.php"><i class="fa fa-check-circle"></i> Completed Cases</a>
<a href="incident_records.php"><i class="fa fa-folder"></i> Incident Records</a>
<a href="return_device.php"><i class="fa fa-undo"></i> Return Device</a>
<a href="../../api/auth/logout.php"><i class="fa fa-sign-out"></i> Logout</a>
</nav>

<main class="container" style="display:block;overflow-y:auto;">

<h2 style="color:#dd4c56;margin-bottom:20px;">📁 Incident Records</h2>

<!-- Statistics Cards -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:15px;margin-bottom:20px;">
    <div style="background:white;padding:15px;border-radius:10px;box-shadow:0 3px 10px rgba(0,0,0,0.1);text-align:center;">
        <p style="font-size:24px;font-weight:bold;color:#3b82f6;"><?php echo $stats['total']; ?></p>
        <p style="color:#777;font-size:12px;">Total Cases</p>
    </div>
    <div style="background:white;padding:15px;border-radius:10px;box-shadow:0 3px 10px rgba(0,0,0,0.1);text-align:center;">
        <p style="font-size:24px;font-weight:bold;color:#3b82f6;"><?php echo $stats['transferred']; ?></p>
        <p style="color:#777;font-size:12px;">Transferred</p>
    </div>
    <div style="background:white;padding:15px;border-radius:10px;box-shadow:0 3px 10px rgba(0,0,0,0.1);text-align:center;">
        <p style="font-size:24px;font-weight:bold;color:#22c55e;"><?php echo $stats['ongoing']; ?></p>
        <p style="color:#777;font-size:12px;">Ongoing</p>
    </div>
    <div style="background:white;padding:15px;border-radius:10px;box-shadow:0 3px 10px rgba(0,0,0,0.1);text-align:center;">
        <p style="font-size:24px;font-weight:bold;color:#f59e0b;"><?php echo $stats['completed']; ?></p>
        <p style="color:#777;font-size:12px;">Completed</p>
    </div>
</div>

<!-- Filters -->
<div style="background:white;padding:20px;border-radius:15px;box-shadow:0 5px 15px rgba(0,0,0,0.1);margin-bottom:20px;">
    <form method="GET" style="display:flex;gap:15px;flex-wrap:wrap;align-items:end;">
        <div>
            <label style="display:block;margin-bottom:5px;font-weight:600;color:#333;">Status</label>
            <select name="status" style="padding:8px;border:1px solid #ddd;border-radius:6px;">
                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                <option value="transferred" <?php echo $status_filter === 'transferred' ? 'selected' : ''; ?>>Transferred</option>
                <option value="ongoing" <?php echo $status_filter === 'ongoing' ? 'selected' : ''; ?>>Ongoing</option>
                <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
            </select>
        </div>
        <div>
            <label style="display:block;margin-bottom:5px;font-weight:600;color:#333;">From Date</label>
            <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>" style="padding:8px;border:1px solid #ddd;border-radius:6px;">
        </div>
        <div>
            <label style="display:block;margin-bottom:5px;font-weight:600;color:#333;">To Date</label>
            <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>" style="padding:8px;border:1px solid #ddd;border-radius:6px;">
        </div>
        <button type="submit" style="padding:8px 16px;background:#dd4c56;color:white;border:none;border-radius:6px;font-weight:bold;cursor:pointer;">Filter</button>
        <a href="incident_records.php" style="padding:8px 16px;background:#64748b;color:white;text-decoration:none;border-radius:6px;font-weight:bold;">Clear</a>
    </form>
</div>

<!-- Incidents List -->
<?php if ($incidents->num_rows > 0): ?>
    <div style="display:flex;flex-direction:column;gap:15px;">
        <?php while ($incident = $incidents->fetch_assoc()): ?>
            <div style="background:white;padding:20px;border-radius:15px;box-shadow:0 5px 15px rgba(0,0,0,0.1);">
                <div style="display:flex;justify-content:space-between;align-items:start;">
                    <div style="flex:1;">
                        <h3 style="color:#dd4c56;margin:0 0 10px 0;">Incident #<?php echo $incident['incident_id']; ?></h3>
                        <p style="color:#777;margin:5px 0;">Patient: <?php echo htmlspecialchars($incident['pat_name']); ?></p>
                        <p style="color:#777;margin:5px 0;">From: <?php echo htmlspecialchars($incident['resp_name']); ?></p>
                        <p style="color:#777;font-size:14px;margin:5px 0;">Started: <?php echo date('M j, Y H:i', strtotime($incident['start_time'])); ?></p>
                        <?php if ($incident['end_time']): ?>
                            <p style="color:#777;font-size:14px;margin:5px 0;">Ended: <?php echo date('M j, Y H:i', strtotime($incident['end_time'])); ?></p>
                        <?php endif; ?>
                        <p style="color:#777;font-size:12px;margin:5px 0;"><?php echo $incident['vital_count']; ?> vitals recorded</p>
                    </div>
                    <div style="text-align:right;">
                        <?php
                        $status_color = '#6b7280';
                        switch($incident['status']) {
                            case 'transferred': $status_color = '#3b82f6'; break;
                            case 'ongoing': $status_color = '#22c55e'; break;
                            case 'completed': $status_color = '#f59e0b'; break;
                        }
                        ?>
                        <span style="display:inline-block;padding:6px 12px;background:<?php echo $status_color; ?>;color:white;border-radius:15px;font-size:12px;text-transform:uppercase;">
                            <?php echo $incident['status']; ?>
                        </span>
                    </div>
                </div>
                
                <div style="display:flex;gap:8px;margin-top:15px;">
                    <a href="case_vitals_history.php?id=<?php echo $incident['incident_id']; ?>" style="padding:6px 12px;background:#64748b;color:white;text-decoration:none;border-radius:6px;font-size:12px;">View History</a>
                    <?php if ($incident['status'] === 'completed'): ?>
                        <a href="generate_case_report.php?id=<?php echo $incident['incident_id']; ?>" style="padding:6px 12px;background:#3b82f6;color:white;text-decoration:none;border-radius:6px;font-size:12px;">Generate Report</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
<?php else: ?>
    <div style="background:white;padding:40px;border-radius:15px;box-shadow:0 5px 15px rgba(0,0,0,0.1);text-align:center;">
        <p style="color:#777;font-size:18px;">📁 No incident records found</p>
        <p style="color:#999;">No incidents match your current filters.</p>
    </div>
<?php endif; ?>

</main>

<nav class="bottom-nav">
<a href="dashboard.php" class="bottom-item">
<i class="fa fa-gauge"></i>
<span>Home</span>
</a>

<a href="transferred_incidents.php" class="bottom-item">
<i class="fa fa-exclamation-circle"></i>
<span>Transfer</span>
</a>

<a href="ongoing_monitoring.php" class="bottom-item">
<i class="fa fa-heart-pulse"></i>
<span>Monitor</span>
</a>

<a href="completed_cases.php" class="bottom-item">
<i class="fa fa-check-circle"></i>
<span>Complete</span>
</a>

<a href="../../api/auth/logout.php"><i class="fa fa-sign-out"></i></a>
</nav>

</body>
</html>
