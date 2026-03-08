<?php 
include("../../database/connection.php");
session_start();

if (!isset($_SESSION['responder_id'])) {
    header("Location: ../../login.html");
    exit;
}

$responder_id = $_SESSION['responder_id'];

// Get active incidents
$stmt = $conn->prepare("
    SELECT i.incident_id, i.status, i.start_time, p.pat_name, p.pat_id
    FROM incident i
    JOIN patient p ON i.pat_id = p.pat_id
    WHERE i.resp_id = ? AND i.status = 'ongoing'
    ORDER BY i.start_time DESC
");
$stmt->bind_param("i", $responder_id);
$stmt->execute();
$incidents = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Active Incidents - VitalWear</title>
<link rel="stylesheet" href="../../assets/css/styles.css">
<script src="https://kit.fontawesome.com/96e37b53f1.js"></script>
</head>
<body>

<header class="topbar">
Responder: <?php echo isset($_SESSION['responder_name']) ? $_SESSION['responder_name'] : 'Medical Monitoring'; ?>
</header>

<nav id="sidebar">
<a href="dashboard.php"><i class="fa fa-gauge"></i> Dashboard</a>
<a href="device.php"><i class="fa fa-tablet"></i> My Device</a>
<a href="active_incidents.php"><i class="fa fa-exclamation-circle"></i> Active Incidents</a>
<a href="create_incident.php"><i class="fa fa-plus-circle"></i> Create Incident</a>
<a href="record_vitals.php"><i class="fa fa-heartbeat"></i> Record Vitals</a>
<a href="transfer_incident.php"><i class="fa fa-exchange"></i> Transfer to Rescuer</a>
<a href="incident_history.php"><i class="fa fa-history"></i> Incident History</a>
<a href="../../api/auth/logout.php"><i class="fa fa-sign-out"></i> Logout</a>
</nav>

<main class="container" style="display:block;overflow-y:auto;width:100%;">

<h2 style="color:#dd4c56;margin-bottom:20px;">🚨 Active Incidents</h2>

<?php if($incidents->num_rows > 0): ?>
    <?php while($incident = $incidents->fetch_assoc()): ?>
    <div style="background:white;padding:20px;border-radius:15px;box-shadow:0 5px 15px rgba(0,0,0,0.1);width:100%;margin-bottom:15px;">
        <div style="display:flex;justify-content:space-between;align-items:center;">
            <div>
                <p style="font-size:18px;font-weight:bold;">Incident #<?php echo $incident['incident_id']; ?></p>
                <p style="color:#777;">Patient: <?php echo htmlspecialchars($incident['pat_name']); ?></p>
                <p style="color:#777;font-size:14px;">Started: <?php echo date('M d, Y h:i A', strtotime($incident['start_time'])); ?></p>
            </div>
            <div style="text-align:right;">
                <p style="color:#f59e0b;font-weight:bold;font-size:16px;"><?php echo ucfirst($incident['status']); ?></p>
                <a href="record_vitals.php?incident_id=<?php echo $incident['incident_id']; ?>" style="display:inline-block;margin-top:10px;padding:8px 16px;background:#22c55e;color:white;text-decoration:none;border-radius:6px;font-size:13px;">Record Vitals</a>
            </div>
        </div>
    </div>
    <?php endwhile; ?>
<?php else: ?>
    <div style="background:white;padding:40px;border-radius:15px;box-shadow:0 5px 15px rgba(0,0,0,0.1);width:100%;text-align:center;">
        <p style="color:#777;font-size:18px;margin-bottom:15px;">No active incidents</p>
        <a href="create_incident.php" style="display:inline-block;padding:12px 24px;background:#dd4c56;color:white;text-decoration:none;border-radius:8px;font-weight:bold;">Create Incident</a>
    </div>
<?php endif; ?>

</main>

<nav class="bottom-nav">
<a href="dashboard.php" class="bottom-item"><i class="fa fa-gauge"></i><span>Home</span></a>
<a href="device.php" class="bottom-item"><i class="fa fa-tablet"></i><span>Device</span></a>
<a href="create_incident.php" class="bottom-item"><i class="fa fa-plus-circle"></i><span>Incident</span></a>
<a href="record_vitals.php" class="bottom-item"><i class="fa fa-heartbeat"></i><span>Vitals</span></a>
<a href="incident_history.php" class="bottom-item"><i class="fa fa-history"></i><span>History</span></a>
<a href="../../api/auth/logout.php"><i class="fa fa-sign-out"></i></a>
</nav>

</body>
</html>

