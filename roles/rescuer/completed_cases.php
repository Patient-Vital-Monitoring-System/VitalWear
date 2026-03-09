<?php
require_once '../../database/connection.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'rescuer') {
    header("Location: ../../login.html");
    exit;
}

$rescuer_id = $_SESSION['user_id'];
$conn = getDBConnection();

// Get completed incidents for this rescuer
$completed_query = "SELECT i.incident_id, i.start_time, i.end_time, p.pat_name,
                   r.resp_name, COUNT(v.vital_id) as vital_count
                   FROM incident i 
                   JOIN patient p ON i.pat_id = p.pat_id 
                   JOIN responder r ON i.resp_id = r.resp_id 
                   LEFT JOIN vitalstat v ON i.incident_id = v.incident_id
                   WHERE i.resc_id = ? AND i.status = 'completed' 
                   GROUP BY i.incident_id, i.start_time, i.end_time, p.pat_name, r.resp_name
                   ORDER BY i.end_time DESC";
$stmt = $conn->prepare($completed_query);
$stmt->bind_param("i", $rescuer_id);
$stmt->execute();
$completed_incidents = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Completed Cases - VitalWear</title>
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

<h2 style="color:#dd4c56;margin-bottom:20px;">✅ Completed Cases</h2>

<?php if ($completed_incidents->num_rows > 0): ?>
    <div style="display:flex;flex-direction:column;gap:20px;">
        <?php while ($incident = $completed_incidents->fetch_assoc()): ?>
            <div style="background:white;padding:25px;border-radius:15px;box-shadow:0 5px 15px rgba(0,0,0,0.1);width:100%;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                    <div>
                        <h3 style="color:#dd4c56;font-size:20px;margin:0;">Incident #<?php echo $incident['incident_id']; ?></h3>
                        <p style="color:#777;margin:5px 0;">Patient: <?php echo htmlspecialchars($incident['pat_name']); ?></p>
                        <p style="color:#777;font-size:14px;">From: <?php echo htmlspecialchars($incident['resp_name']); ?></p>
                        <p style="color:#777;font-size:14px;">Duration: <?php echo date('M j, Y H:i', strtotime($incident['start_time'])); ?> - <?php echo date('M j, Y H:i', strtotime($incident['end_time'])); ?></p>
                    </div>
                    <div style="text-align:right;">
                        <span style="display:inline-block;padding:8px 16px;background:#6b7280;color:white;border-radius:20px;font-size:14px;">Completed</span>
                        <p style="color:#777;font-size:12px;margin-top:5px;"><?php echo $incident['vital_count']; ?> vitals recorded</p>
                    </div>
                </div>
                
                <div style="display:flex;gap:10px;">
                    <a href="generate_case_report.php?id=<?php echo $incident['incident_id']; ?>" style="padding:10px 20px;background:#3b82f6;color:white;text-decoration:none;border-radius:8px;font-weight:bold;">
                        <i class="fa fa-file-pdf"></i> Generate Report
                    </a>
                    <a href="case_vitals_history.php?id=<?php echo $incident['incident_id']; ?>" style="padding:10px 20px;background:#64748b;color:white;text-decoration:none;border-radius:8px;font-weight:bold;">
                        <i class="fa fa-chart-line"></i> View History
                    </a>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
<?php else: ?>
    <div style="background:white;padding:40px;border-radius:15px;box-shadow:0 5px 15px rgba(0,0,0,0.1);width:100%;text-align:center;">
        <p style="color:#777;font-size:18px;margin-bottom:20px;">✅ No completed cases</p>
        <p style="color:#999;">You haven't completed any cases yet.</p>
        <a href="transferred_incidents.php" style="display:inline-block;padding:12px 24px;background:#dd4c56;color:white;text-decoration:none;border-radius:8px;font-weight:bold;margin-top:20px;">View Transferred Cases</a>
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
