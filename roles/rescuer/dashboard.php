<?php
require_once '../../database/connection.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'rescuer') {
    header("Location: ../../login.html");
    exit;
}

$rescuer_id = $_SESSION['user_id'];
$conn = getDBConnection();

// Get rescuer info
$rescuer_query = "SELECT resc_name, resc_email FROM rescuer WHERE resc_id = ?";
$stmt = $conn->prepare($rescuer_query);
$stmt->bind_param("i", $rescuer_id);
$stmt->execute();
$rescuer = $stmt->get_result()->fetch_assoc();

// Get incident counts
$transferred_query = "SELECT COUNT(*) as count FROM incident WHERE resc_id = ? AND status = 'transferred'";
$ongoing_query = "SELECT COUNT(*) as count FROM incident WHERE resc_id = ? AND status = 'ongoing'";
$completed_query = "SELECT COUNT(*) as count FROM incident WHERE resc_id = ? AND status = 'completed'";

$stmt = $conn->prepare($transferred_query);
$stmt->bind_param("i", $rescuer_id);
$stmt->execute();
$transferred_count = $stmt->get_result()->fetch_assoc()['count'];

$stmt = $conn->prepare($ongoing_query);
$stmt->bind_param("i", $rescuer_id);
$stmt->execute();
$ongoing_count = $stmt->get_result()->fetch_assoc()['count'];

$stmt = $conn->prepare($completed_query);
$stmt->bind_param("i", $rescuer_id);
$stmt->execute();
$completed_count = $stmt->get_result()->fetch_assoc()['count'];

// Get recent transferred incidents
$recent_transferred = "SELECT i.incident_id, i.start_time, p.pat_name, r.resp_name 
                      FROM incident i 
                      JOIN patient p ON i.pat_id = p.pat_id 
                      JOIN responder r ON i.resp_id = r.resp_id 
                      WHERE i.resc_id = ? AND i.status = 'transferred' 
                      ORDER BY i.start_time DESC LIMIT 5";
$stmt = $conn->prepare($recent_transferred);
$stmt->bind_param("i", $rescuer_id);
$stmt->execute();
$transferred_incidents = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Rescuer Medical Dashboard</title>

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

.status-transferred {
    background: rgba(0, 182, 204, 0.15);
    color: var(--medical-cyan);
}

/* Responsive Grid */
.vitals-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
    gap: 20px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 20px;
    margin-bottom: 24px;
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
            <span><?php echo htmlspecialchars($rescuer['resc_name'] ?? 'Rescuer'); ?></span>
        </div>
    </div>
</header>

<nav id="sidebar">
<div class="sidebar-logo">
    <img src="../../assets/logo.png" alt="VitalWear Logo">
</div>
<a href="dashboard.php"><i class="fa fa-gauge"></i> Dashboard</a>
<a href="transferred_incidents.php"><i class="fa fa-exclamation-circle"></i> Transferred Incidents</a>
<a href="ongoing_monitoring.php"><i class="fa fa-heart-pulse"></i> Ongoing Monitoring</a>
<a href="completed_cases.php"><i class="fa fa-check-circle"></i> Completed Cases</a>
<a href="incident_records.php"><i class="fa fa-folder"></i> Incident Records</a>
<a href="return_device.php"><i class="fa fa-undo"></i> Return Device</a>
<a href="../../api/auth/logout.php" class="btn btn-secondary">Logout</a>
</nav>

<main class="container" style="display:block;overflow-y:auto;">

<!-- Welcome Banner -->
<div style="background: linear-gradient(135deg, var(--deep-hospital-blue) 0%, var(--trust-blue) 100%); padding: 32px; border-radius: var(--radius-lg); margin-bottom: 24px; color: white; box-shadow: var(--shadow-md);">
    <div style="display: flex; align-items: center; gap: 16px;">
        <div style="width: 60px; height: 60px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 28px;">
            🚑
        </div>
        <div>
            <h1 style="color: white; margin: 0; font-size: 1.75rem; font-weight: 700;">Welcome, <?php echo htmlspecialchars($rescuer['resc_name'] ?? 'Rescuer'); ?>!</h1>
            <p style="color: rgba(255,255,255,0.9); margin: 4px 0 0 0; font-size: 1rem;">Ready to provide critical medical transport and care</p>
        </div>
    </div>
</div>

<!-- Stats Overview -->
<div class="stats-grid">
    <div class="dashboard-card">
        <div class="vital-stat">
            <span class="vital-stat-label">Transferred Cases</span>
            <span class="vital-stat-value" style="color: var(--medical-cyan);">
                <?php echo $transferred_count; ?>
            </span>
        </div>
    </div>
    <div class="dashboard-card">
        <div class="vital-stat">
            <span class="vital-stat-label">Ongoing Monitoring</span>
            <span class="vital-stat-value" style="color: var(--health-green);">
                <?php echo $ongoing_count; ?>
            </span>
        </div>
    </div>
    <div class="dashboard-card">
        <div class="vital-stat">
            <span class="vital-stat-label">Completed Cases</span>
            <span class="vital-stat-value" style="color: var(--trust-blue);">
                <?php echo $completed_count; ?>
            </span>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div style="margin-bottom:24px;width:100%;">
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;">
        <a href="transferred_incidents.php" class="btn-quick-action">
            <i class="fa fa-exclamation-circle"></i> View Transferred Cases
        </a>
        <a href="ongoing_monitoring.php" class="btn-quick-action" style="background: linear-gradient(135deg, var(--health-green) 0%, #20c997 100%);">
            <i class="fa fa-heart-pulse"></i> Ongoing Monitoring
        </a>
        <a href="completed_cases.php" class="btn-quick-action" style="background: linear-gradient(135deg, var(--trust-blue) 0%, #0a7cc4 100%);">
            <i class="fa fa-check-circle"></i> Completed Cases
        </a>
    </div>
</div>

<!-- Recent Transferred Incidents -->
<div class="dashboard-card">
    <h3>📥 Recent Transferred Incidents</h3>
    <?php if ($transferred_incidents->num_rows > 0): ?>
        <div style="display:flex;flex-direction:column;gap:12px;">
            <?php while ($incident = $transferred_incidents->fetch_assoc()): ?>
                <div style="background:var(--clinical-white);padding:16px;border-radius:var(--radius);border-left:4px solid var(--medical-cyan);">
                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <div>
                            <p style="font-size:16px;font-weight:700;color:var(--deep-hospital-blue);margin:0;">#<?php echo $incident['incident_id']; ?> - <?php echo htmlspecialchars($incident['pat_name']); ?></p>
                            <p style="color:var(--system-gray);font-size:14px;margin:4px 0;">From: <?php echo htmlspecialchars($incident['resp_name']); ?></p>
                            <p style="color:var(--system-gray);font-size:12px;"><?php echo date('M j, Y H:i', strtotime($incident['start_time'])); ?></p>
                        </div>
                        <span class="status-badge status-transferred">Transferred</span>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
        <div style="margin-top:16px;text-align:center;">
            <a href="transferred_incidents.php" class="btn-link">View All Transferred Cases <i class="fa fa-arrow-right"></i></a>
        </div>
    <?php else: ?>
        <div style="text-align:center;padding:32px;color:var(--system-gray);">
            <div style="font-size:48px;margin-bottom:16px;">📥</div>
            <p style="font-size:16px;margin-bottom:12px;">No transferred incidents</p>
            <p style="font-size:14px;">Transferred incidents from responders will appear here</p>
        </div>
    <?php endif; ?>
</div>

<!-- Assigned Device Card -->
<div class="dashboard-card">
    <h3>📦 Assigned Device</h3>
    <?php
    $device_query = "SELECT d.dev_serial, d.dev_status 
                     FROM device_log dl
                     JOIN device d ON dl.dev_id = d.dev_id
                     WHERE dl.resc_id = ? AND dl.date_returned IS NULL
                     ORDER BY dl.date_assigned DESC
                     LIMIT 1";
    $stmt = $conn->prepare($device_query);
    $stmt->bind_param("i", $rescuer_id);
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
    <a href="return_device.php" class="btn-link">Request Device <i class="fa fa-arrow-right"></i></a>
    <?php endif; ?>
</div>

</main>

<nav class="bottom-nav">
<a href="dashboard.php" class="bottom-item active">
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

<a href="../../api/auth/logout.php" class="bottom-item">
    <i class="fa fa-sign-out"></i>
    <span>Logout</span>
</a>
</nav>

</body>
</html>
