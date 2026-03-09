<?php 
include("../../database/connection.php");
$dbStatus = isset($conn) && !$conn->connect_error;
session_start();

// Check if logged in as responder
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'responder') {
    header("Location: ../../login.html");
    exit;
}

$responder_id = $_SESSION['user_id'];
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Responder Medical Dashboard</title>

<link rel="stylesheet" href="../../assets/css/styles.css">
<script src="https://kit.fontawesome.com/96e37b53f1.js"></script>

</head>

<body>

<header class="topbar">
Responder: <?php echo isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Medical Monitoring'; ?>
</header>

<nav id="sidebar">

<a href="dashboard.php"><i class="fa fa-gauge"></i> Dashboard</a>
<a href="device.php"><i class="fa fa-tablet"></i> My Device</a>
<a href="active_incidents.php"><i class="fa fa-exclamation-circle"></i> Active Incidents</a>
<a href="create_incident.php"><i class="fa fa-plus-circle"></i> Create Incident</a>
<a href="transfer_incident.php"><i class="fa fa-exchange"></i> Transfer to Rescuer</a>
<a href="incident_history.php"><i class="fa fa-history"></i> Incident History</a>
<a href="/VitalWear-1/api/auth/logout.php" class="btn btn-secondary">Logout</a>


</nav>

<main class="container" style="display:block;overflow-y:auto;">

<!-- Quick Create Incident Button -->
<div style="margin-bottom:20px;width:100%;">
    <a href="create_incident.php" style="display:inline-block;width:100%;padding:15px;background:#dd4c56;color:white;text-decoration:none;border-radius:15px;font-weight:bold;font-size:16px;text-align:center;box-shadow:0 5px 15px rgba(221,76,86,0.3);">
        <i class="fa fa-plus-circle"></i> Quick Create Incident
    </a>
</div>

<!-- Assigned Device Card -->
<div style="background:white;padding:20px;border-radius:15px;box-shadow:0 5px 15px rgba(0,0,0,0.1);width:100%;margin-bottom:20px;">
    <h3 style="color:#dd4c56;margin-bottom:10px;">📦 Assigned Device</h3>
    <?php
    $stmt = $conn->prepare("
        SELECT d.dev_serial, d.dev_status 
        FROM device_log dl
        JOIN device d ON dl.dev_id = d.dev_id
        WHERE dl.resp_id = ? AND dl.date_returned IS NULL
        ORDER BY dl.date_assigned DESC
        LIMIT 1
    ");
    $stmt->bind_param("i", $responder_id);
    $stmt->execute();
    $device = $stmt->get_result()->fetch_assoc();
    
    if($device):
    ?>
    <p style="font-size:18px;font-weight:bold;"><?php echo $device['dev_serial']; ?></p>
    <p style="color:<?php echo $device['dev_status']=='available'?'#22c55e':'#f59e0b'; ?>;"><?php echo ucfirst($device['dev_status']); ?></p>
    <?php else: ?>
    <p style="color:#777;">No device assigned</p>
    <a href="device.php" style="color:#dd4c56;">Request Device →</a>
    <?php endif; ?>
</div>

<!-- Active Incident Card -->
<div style="background:white;padding:20px;border-radius:15px;box-shadow:0 5px 15px rgba(0,0,0,0.1);width:100%;margin-bottom:20px;">
    <h3 style="color:#dd4c56;margin-bottom:10px;">🚨 Active Incident</h3>
    <?php
$stmt = $conn->prepare("
        SELECT incident_id, pat_id, status, start_time FROM incident 
        WHERE resp_id = ? AND status = 'ongoing'
        ORDER BY start_time DESC
        LIMIT 1
    ");
    $stmt->bind_param("i", $responder_id);
    $stmt->execute();
    $incident = $stmt->get_result()->fetch_assoc();
    
    if($incident):
        // Get patient name
        $pat_stmt = $conn->prepare("SELECT pat_name FROM patient WHERE pat_id = ?");
        $pat_stmt->bind_param("i", $incident['pat_id']);
        $pat_stmt->execute();
        $patient = $pat_stmt->get_result()->fetch_assoc();
    ?>
    <p style="font-size:18px;font-weight:bold;">Incident #<?php echo $incident['incident_id']; ?></p>
    <p style="color:#777;">Patient: <?php echo htmlspecialchars($patient['pat_name'] ?? 'Unknown'); ?></p>
    <p style="color:#f59e0b;font-weight:600;"><?php echo ucfirst($incident['status']); ?></p>
    <?php else: ?>
    <p style="color:#777;">No active incidents</p>
    <a href="create_incident.php" style="color:#dd4c56;">Create Incident →</a>
    <?php endif; ?>
</div>

<!-- Latest Vital Readings Card -->
<div style="background:white;padding:20px;border-radius:15px;box-shadow:0 5px 15px rgba(0,0,0,0.1);width:100%;">
    <h3 style="color:#dd4c56;margin-bottom:15px;">❤️ Latest Vital Readings</h3>
    <?php
    // Query to get latest vital readings for responder's active incidents
    $stmt = $conn->prepare("
        SELECT 
            v.heart_rate,
            v.bp_systolic,
            v.bp_diastolic,
            v.oxygen_level,
            v.recorded_at,
            p.pat_name,
            i.incident_id
        FROM vitalstat v
        JOIN incident i ON v.incident_id = i.incident_id
        JOIN patient p ON i.pat_id = p.pat_id
        WHERE i.resp_id = ? AND i.status = 'ongoing'
        ORDER BY v.recorded_at DESC
        LIMIT 1
    ");
    $stmt->bind_param("i", $responder_id);
    $stmt->execute();
    $vitals = $stmt->get_result()->fetch_assoc();
    
    if($vitals):
    ?>
    <div style="display:flex;gap:20px;flex-wrap:wrap;">
        <div style="flex:1 1 100px;min-width:80px;">
            <p style="color:#777;font-size:12px;">Heart Rate</p>
            <p style="font-size:24px;font-weight:800;color:#e74c3c;">
                <?php echo $vitals['heart_rate']; ?><span style="font-size:12px;color:#777;"> bpm</span>
            </p>
        </div>
        
        <div style="flex:1 1 100px;min-width:80px;">
            <p style="color:#777;font-size:12px;">Blood Pressure</p>
            <p style="font-size:20px;font-weight:bold;color:#22c55e;">
                <?php echo $vitals['bp_systolic']; ?>/<?php echo $vitals['bp_diastolic']; ?>
                <span style="font-size:10px;color:#777;">mmHg</span>
            </p>
        </div>
        
        <div style="flex:1 1 100px;min-width:80px;">
            <p style="color:#777;font-size:12px;">SpO2</p>
            <p style="font-size:24px;font-weight:800;color:#0ea5e9;">
                <?php echo $vitals['oxygen_level']; ?><span style="font-size:12px;color:#777;">%</span>
            </p>
        </div>
        
        <div style="flex:1 1 100px;min-width:80px;text-align:right;">
            <p style="color:#777;font-size:12px;">Recorded</p>
            <p style="font-size:14px;"><?php echo date('M d, h:i A', strtotime($vitals['recorded_at'])); ?></p>
        </div>
    </div>
    <div style="margin-top:15px;">
    </div>
    <?php else: ?>
    <div style="text-align:center;padding:20px;color:#777;">
        <p style="font-size:14px;margin-bottom:10px;">No vital readings available</p>
        <?php if($incident): ?>
        <?php else: ?>
        <p style="font-size:12px;color:#999;">Create an incident to record vitals.</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

</main>

<nav class="bottom-nav">

<a href="dashboard.php" class="bottom-item">
<i class="fa fa-gauge"></i>
<span>Home</span>
</a>

<a href="patient_vitals.php" class="bottom-item">
<i class="fa fa-line-chart"></i>
<span>Vitals</span>
</a>

<a href="create_incident.php" class="bottom-item">
<i class="fa fa-plus-circle"></i>
<span>Incident</span>
</a>

<a href="incident_history.php" class="bottom-item">
<i class="fa fa-history"></i>
<span>History</span>
</a>

<a href="/VitalWear-1/api/auth/logout.php"><i class="fa fa-sign-out"></i></a>
</nav>

</body>
</html>
