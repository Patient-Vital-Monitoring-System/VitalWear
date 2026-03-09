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

// Get responder info
$responder_query = "SELECT resp_name FROM responder WHERE resp_id = ?";
$stmt = $conn->prepare($responder_query);
$stmt->bind_param("i", $responder_id);
$stmt->execute();
$responder = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Responder Medical Dashboard</title>

<link rel="stylesheet" href="../../assets/css/styles.css">
<script src="https://kit.fontawesome.com/96e37b53f1.js"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
/* VitalWear Soft UI Design System */
:root {
    --deep-hospital-blue: #0A2A55;
    --medical-cyan: #00B6CC;
    --trust-blue: #0A85CC;
    --health-green: #2EDBB3;
    --clinical-white: #F0F4F8;
    --system-gray: #A9B7C6;
    --surface: #ffffff;
    --radius: 12px;
    --radius-lg: 16px;
    --shadow-sm: 0 2px 4px rgba(10, 42, 85, 0.06);
    --shadow: 0 4px 12px rgba(10, 42, 85, 0.08);
    --shadow-md: 0 8px 24px rgba(10, 42, 85, 0.12);
}

body {
    background-color: var(--clinical-white);
    color: var(--deep-hospital-blue);
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
}

/* Soft UI Sidebar */
#sidebar {
    background: var(--surface);
    border-right: 1px solid rgba(169, 183, 198, 0.3);
    box-shadow: var(--shadow);
}

.sidebar-logo {
    padding: 24px 20px;
    text-align: center;
    background: linear-gradient(135deg, var(--deep-hospital-blue) 0%, var(--trust-blue) 100%);
    margin: 12px;
    border-radius: var(--radius);
}

.sidebar-logo img {
    max-width: 140px;
    height: auto;
    filter: brightness(0) invert(1);
}

#sidebar a {
    color: var(--deep-hospital-blue);
    margin: 6px 12px;
    padding: 12px 16px;
    border-radius: var(--radius);
    transition: all 0.2s ease;
    border: none;
    font-weight: 500;
}

#sidebar a:hover {
    background: rgba(0, 182, 204, 0.1);
    color: var(--medical-cyan);
    transform: translateX(4px);
}

/* Soft UI Header */
.topbar {
    background: var(--surface);
    color: var(--deep-hospital-blue);
    border-bottom: 1px solid rgba(169, 183, 198, 0.2);
    box-shadow: var(--shadow-sm);
    padding: 16px 24px;
    font-weight: 600;
}

h2, h3, h4 {
    color: var(--deep-hospital-blue);
    font-weight: 700;
}

/* Modern Soft Edge Navigation */
.bottom-nav {
    background: var(--surface);
    border-top: 1px solid rgba(169, 183, 198, 0.3);
    box-shadow: 0 -4px 20px rgba(10, 42, 85, 0.08);
    padding: 12px 24px;
    display: flex;
    justify-content: space-around;
    align-items: center;
}

.bottom-nav .bottom-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    padding: 10px 20px;
    border-radius: var(--radius);
    color: var(--system-gray);
    text-decoration: none;
    transition: all 0.3s ease;
    font-weight: 500;
    font-size: 12px;
}

.bottom-nav .bottom-item i {
    font-size: 20px;
    transition: all 0.3s ease;
}

.bottom-nav .bottom-item:hover {
    color: var(--medical-cyan);
    background: rgba(0, 182, 204, 0.1);
    transform: translateY(-2px);
}

.bottom-nav .bottom-item.active {
    color: var(--medical-cyan);
    background: rgba(0, 182, 204, 0.15);
}

.bottom-nav .bottom-item.active i {
    transform: scale(1.1);
}

/* Modern Soft Edge Cards */
.dashboard-card {
    background: var(--surface);
    padding: 24px;
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow);
    border: 1px solid rgba(169, 183, 198, 0.2);
    margin-bottom: 20px;
    transition: all 0.3s ease;
}

.dashboard-card:hover {
    box-shadow: var(--shadow-md);
    transform: translateY(-2px);
}

.dashboard-card h3 {
    color: var(--deep-hospital-blue);
    margin-bottom: 16px;
    font-size: 1.1rem;
    display: flex;
    align-items: center;
    gap: 8px;
}

/* Modern Soft Edge Buttons */
.btn-quick-action {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    width: 100%;
    padding: 16px 24px;
    background: linear-gradient(135deg, var(--medical-cyan) 0%, var(--trust-blue) 100%);
    color: white;
    text-decoration: none;
    border-radius: var(--radius-lg);
    font-weight: 600;
    font-size: 16px;
    box-shadow: var(--shadow);
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-quick-action:hover {
    transform: translateY(-3px);
    box-shadow: var(--shadow-md);
}

.btn-link {
    color: var(--medical-cyan);
    text-decoration: none;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s ease;
}

.btn-link:hover {
    color: var(--trust-blue);
    transform: translateX(4px);
}

/* Vital Stats Display */
.vital-stat {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.vital-stat-label {
    color: var(--system-gray);
    font-size: 12px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.vital-stat-value {
    font-size: 28px;
    font-weight: 800;
    color: var(--deep-hospital-blue);
}

.vital-stat-unit {
    font-size: 14px;
    color: var(--system-gray);
    font-weight: 500;
}

/* Status Badges */
.status-badge {
    display: inline-flex;
    align-items: center;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 14px;
    font-weight: 600;
}

.status-available {
    background: rgba(46, 219, 179, 0.15);
    color: var(--health-green);
}

.status-ongoing {
    background: rgba(245, 158, 11, 0.15);
    color: #f59e0b;
}

/* Responsive Grid */
.vitals-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
    gap: 20px;
}


/* Soft UI Cards */
.card {
    background: var(--surface);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow);
    border: 1px solid rgba(169, 183, 198, 0.2);
}

/* Soft UI Buttons */
.btn-primary {
    background: linear-gradient(135deg, var(--medical-cyan) 0%, var(--trust-blue) 100%);
    color: white;
    border: none;
    padding: 12px 24px;
    border-radius: var(--radius);
    font-weight: 600;
    box-shadow: var(--shadow);
    transition: all 0.3s ease;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}
</style>

</head>

<body>

<header class="topbar">
    <div style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
        <div style="display: flex; align-items: center; gap: 12px;">
            <i class="fa fa-heart-pulse" style="font-size: 24px; color: var(--medical-cyan);"></i>
            <span style="font-size: 18px; font-weight: 700;">VitalWear</span>
        </div>
        <div style="display: flex; align-items: center; gap: 8px; color: var(--deep-hospital-blue); font-weight: 500;">
            <i class="fa fa-user-circle" style="font-size: 20px; color: var(--medical-cyan);"></i>
            <span><?php echo htmlspecialchars($responder['resp_name'] ?? 'Responder'); ?></span>
        </div>
    </div>
</header>

<nav id="sidebar">
<div class="sidebar-logo">
    <img src="../../assets/logo.png" alt="VitalWear Logo">
</div>
<a href="dashboard.php"><i class="fa fa-gauge"></i> Dashboard</a>
<a href="device.php"><i class="fa fa-tablet"></i> My Device</a>
<a href="active_incidents.php"><i class="fa fa-exclamation-circle"></i> Active Incidents</a>
<a href="create_incident.php"><i class="fa fa-plus-circle"></i> Create Incident</a>
<a href="transfer_incident.php"><i class="fa fa-exchange"></i> Transfer to Rescuer</a>
<a href="incident_history.php"><i class="fa fa-history"></i> Incident History</a>
<a href="/VitalWear-1/api/auth/logout.php" class="btn btn-secondary">Logout</a>


</nav>

<main class="container" style="display:block;overflow-y:auto;">

<!-- Welcome Banner -->
<div style="background: linear-gradient(135deg, var(--deep-hospital-blue) 0%, var(--trust-blue) 100%); padding: 32px; border-radius: var(--radius-lg); margin-bottom: 24px; color: white; box-shadow: var(--shadow-md);">
    <div style="display: flex; align-items: center; gap: 16px;">
        <div style="width: 60px; height: 60px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 28px;">
            👋
        </div>
        <div>
            <h1 style="color: white; margin: 0; font-size: 1.75rem; font-weight: 700;">Welcome, <?php echo htmlspecialchars($responder['resp_name'] ?? 'Responder'); ?>!</h1>
            <p style="color: rgba(255,255,255,0.9); margin: 4px 0 0 0; font-size: 1rem;">Ready to provide critical medical response</p>
        </div>
    </div>
</div>

<!-- Quick Create Incident Button -->
<div style="margin-bottom:24px;width:100%;">
    <a href="create_incident.php" class="btn-quick-action">
        <i class="fa fa-plus-circle"></i> Quick Create Incident
    </a>
</div>

<!-- Assigned Device Card -->
<div class="dashboard-card">
    <h3>📦 Assigned Device</h3>
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
    <p style="font-size:18px;font-weight:700;color:var(--deep-hospital-blue);"><?php echo $device['dev_serial']; ?></p>
    <span class="status-badge <?php echo $device['dev_status']=='available'?'status-available':'status-ongoing'; ?>">
        <?php echo ucfirst($device['dev_status']); ?>
    </span>
    <?php else: ?>
    <p style="color:var(--system-gray);">No device assigned</p>
    <a href="device.php" class="btn-link">Request Device <i class="fa fa-arrow-right"></i></a>
    <?php endif; ?>
</div>

<!-- Active Incident Card -->
<div class="dashboard-card">
    <h3>🚨 Active Incident</h3>
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
    <p style="font-size:18px;font-weight:700;color:var(--deep-hospital-blue);">Incident #<?php echo $incident['incident_id']; ?></p>
    <p style="color:var(--system-gray);margin-bottom:8px;">Patient: <?php echo htmlspecialchars($patient['pat_name'] ?? 'Unknown'); ?></p>
    <span class="status-badge status-ongoing"><?php echo ucfirst($incident['status']); ?></span>
    <?php else: ?>
    <p style="color:var(--system-gray);">No active incidents</p>
    <a href="create_incident.php" class="btn-link">Create Incident <i class="fa fa-arrow-right"></i></a>
    <?php endif; ?>
</div>

<!-- Latest Vital Readings Card -->
<div class="dashboard-card">
    <h3>❤️ Latest Vital Readings</h3>
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
    <div class="vitals-grid">
        <div class="vital-stat">
            <span class="vital-stat-label">Heart Rate</span>
            <span class="vital-stat-value" style="color:#0A85CC;">
                <?php echo $vitals['heart_rate']; ?><span class="vital-stat-unit">bpm</span>
            </span>
        </div>
        
        <div class="vital-stat">
            <span class="vital-stat-label">Blood Pressure</span>
            <span class="vital-stat-value" style="color:#00B6CC;font-size:22px;">
                <?php echo $vitals['bp_systolic']; ?>/<?php echo $vitals['bp_diastolic']; ?>
                <span class="vital-stat-unit">mmHg</span>
            </span>
        </div>
        
        <div class="vital-stat">
            <span class="vital-stat-label">SpO2</span>
            <span class="vital-stat-value" style="color:#0A2A55;">
                <?php echo $vitals['oxygen_level']; ?><span class="vital-stat-unit">%</span>
            </span>
        </div>
        
        <div class="vital-stat" style="text-align:right;">
            <span class="vital-stat-label">Recorded</span>
            <span style="font-size:14px;color:var(--deep-hospital-blue);"><?php echo date('M d, h:i A', strtotime($vitals['recorded_at'])); ?></span>
        </div>
    </div>
    <?php else: ?>
    <div style="text-align:center;padding:24px;color:var(--system-gray);">
        <p style="font-size:14px;margin-bottom:12px;">No vital readings available</p>
        <?php if($incident): ?>
        <a href="active_incidents.php" class="btn-link">Go to Active Incidents <i class="fa fa-arrow-right"></i></a>
        <?php else: ?>
        <p style="font-size:12px;">Create an incident to record vitals.</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

</main>

<nav class="bottom-nav">
<a href="dashboard.php" class="bottom-item active">
    <i class="fa fa-gauge"></i>
    <span>Home</span>
</a>

<a href="device.php" class="bottom-item">
    <i class="fa fa-tablet"></i>
    <span>Device</span>
</a>

<a href="active_incidents.php" class="bottom-item">
    <i class="fa fa-exclamation-circle"></i>
    <span>Incidents</span>
</a>

<a href="create_incident.php" class="bottom-item">
    <i class="fa fa-plus-circle"></i>
    <span>Create</span>
</a>

<a href="../../api/auth/logout.php" class="bottom-item">
    <i class="fa fa-sign-out"></i>
    <span>Logout</span>
</a>
</nav>

</body>
</html>
