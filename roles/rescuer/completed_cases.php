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
$rescuer = ['resc_name' => 'Rescuer'];
if ($conn && !$conn->connect_error) {
    $rescuer_query = "SELECT resc_name, resc_email FROM rescuer WHERE resc_id = ?";
    $stmt = $conn->prepare($rescuer_query);
    if ($stmt) {
        $stmt->bind_param("i", $rescuer_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        if ($result) {
            $rescuer = $result;
        }
    }
}

// Get completed incidents for this rescuer
$completed_incidents = [];
if ($conn && !$conn->connect_error) {
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
    if ($stmt) {
        $stmt->bind_param("i", $rescuer_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $completed_incidents[] = $row;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Completed Cases - VitalWear</title>

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

/* Completed Case Card */
.completed-case-card {
    background: var(--surface);
    padding: 28px;
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow);
    border: 1px solid rgba(169, 183, 198, 0.2);
    margin-bottom: 20px;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.completed-case-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--health-green) 0%, #20c997 100%);
}

.completed-case-card:hover {
    box-shadow: var(--shadow-md);
    transform: translateY(-2px);
}

.case-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 20px;
}

.case-info h3 {
    color: var(--deep-hospital-blue);
    font-size: 1.25rem;
    font-weight: 700;
    margin: 0 0 8px 0;
}

.case-info p {
    color: var(--system-gray);
    margin: 4px 0;
    font-size: 0.9rem;
}

.case-status {
    text-align: right;
}

.status-badge {
    display: inline-block;
    padding: 8px 16px;
    background: linear-gradient(135deg, var(--health-green) 0%, #20c997 100%);
    color: white;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    margin-bottom: 8px;
}

.vitals-count {
    color: var(--system-gray);
    font-size: 0.85rem;
}

.case-actions {
    display: flex;
    gap: 12px;
    margin-top: 20px;
}

.btn {
    padding: 14px 24px;
    border: none;
    border-radius: var(--radius);
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    position: relative;
    overflow: hidden;
    text-transform: none;
    letter-spacing: 0.3px;
}

.btn::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
    transition: left 0.5s;
}

.btn:hover::before {
    left: 100%;
}

.btn i {
    font-size: 16px;
    transition: transform 0.3s ease;
}

.btn:hover i {
    transform: scale(1.1);
}

.btn-primary {
    background: linear-gradient(135deg, var(--medical-cyan) 0%, var(--trust-blue) 100%);
    color: white;
    box-shadow: 0 4px 15px rgba(0, 182, 204, 0.3);
    border: none;
}

.btn-primary:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0, 182, 204, 0.4);
    background: linear-gradient(135deg, var(--trust-blue) 0%, var(--medical-cyan) 100%);
}

.btn-primary:active {
    transform: translateY(-1px);
    box-shadow: 0 4px 15px rgba(0, 182, 204, 0.3);
}

.btn-secondary {
    background: linear-gradient(135deg, var(--health-green) 0%, #20c997 100%);
    color: white;
    box-shadow: 0 4px 15px rgba(46, 219, 179, 0.3);
    border: none;
}

.btn-secondary:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(46, 219, 179, 0.4);
    background: linear-gradient(135deg, #20c997 0%, var(--health-green) 100%);
}

.btn-secondary:active {
    transform: translateY(-1px);
    box-shadow: 0 4px 15px rgba(46, 219, 179, 0.3);
}

/* Welcome Banner */
.welcome-banner {
    background: linear-gradient(135deg, var(--deep-hospital-blue) 0%, var(--trust-blue) 100%);
    padding: 32px;
    border-radius: var(--radius-lg);
    margin-bottom: 24px;
    color: white;
    box-shadow: var(--shadow-md);
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    background: var(--surface);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow);
    border: 1px solid rgba(169, 183, 198, 0.2);
}

.empty-state h3 {
    color: var(--deep-hospital-blue);
    margin-bottom: 16px;
}

.empty-state p {
    color: var(--system-gray);
    margin-bottom: 24px;
}

.case-duration {
    display: flex;
    align-items: center;
    gap: 6px;
    color: var(--system-gray);
    font-size: 0.85rem;
}

.patient-info {
    display: flex;
    align-items: center;
    gap: 6px;
    color: var(--system-gray);
    font-size: 0.9rem;
}

.responder-info {
    display: flex;
    align-items: center;
    gap: 6px;
    color: var(--system-gray);
    font-size: 0.85rem;
}
</style>

</head>

<body>

<header class="topbar">
    <div style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
        <div style="display: flex; align-items: center; gap: 12px;">
            <i class="fa fa-check-circle" style="font-size: 24px; color: var(--health-green);"></i>
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
<div class="welcome-banner">
    <div style="display: flex; align-items: center; gap: 16px;">
        <div style="width: 60px; height: 60px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 28px;">
            ✅
        </div>
        <div>
            <h1 style="color: white; margin: 0; font-size: 1.75rem; font-weight: 700;">Completed Cases</h1>
            <p style="color: rgba(255,255,255,0.9); margin: 4px 0 0 0; font-size: 1rem;">Successfully managed incidents</p>
        </div>
    </div>
</div>

<!-- Completed Cases List -->
<?php if (count($completed_incidents) > 0): ?>
    <div style="display: flex; flex-direction: column; gap: 20px;">
        <?php foreach ($completed_incidents as $incident): ?>
            <div class="completed-case-card">
                <div class="case-header">
                    <div class="case-info">
                        <h3>Incident #<?php echo $incident['incident_id']; ?></h3>
                        <div class="patient-info">
                            <i class="fa fa-user"></i>
                            Patient: <?php echo htmlspecialchars($incident['pat_name']); ?>
                        </div>
                        <div class="responder-info">
                            <i class="fa fa-ambulance"></i>
                            From: <?php echo htmlspecialchars($incident['resp_name']); ?>
                        </div>
                        <div class="case-duration">
                            <i class="fa fa-clock"></i>
                            <?php 
                            $start = new DateTime($incident['start_time']);
                            $end = new DateTime($incident['end_time']);
                            $duration = $start->diff($end);
                            echo $duration->format('%H:%I:%S');
                            ?>
                            (<?php echo date('M j, Y H:i', strtotime($incident['start_time'])); ?> - <?php echo date('H:i', strtotime($incident['end_time'])); ?>)
                        </div>
                    </div>
                    <div class="case-status">
                        <span class="status-badge">
                            <i class="fa fa-check-circle"></i> Completed
                        </span>
                        <div class="vitals-count">
                            📊 <?php echo $incident['vital_count']; ?> vitals recorded
                        </div>
                    </div>
                </div>
                
                <div class="case-actions">
                    <a href="generate_case_report.php?id=<?php echo $incident['incident_id']; ?>" class="btn btn-primary">
                        <i class="fa fa-file-medical"></i> Generate Report
                    </a>
                    <a href="case_vitals_history.php?id=<?php echo $incident['incident_id']; ?>" class="btn btn-secondary">
                        <i class="fa fa-chart-line"></i> View History
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="empty-state">
        <div style="font-size: 64px; margin-bottom: 20px; color: var(--system-gray);">
            ✅
        </div>
        <h3>No Completed Cases Yet</h3>
        <p>You haven't completed any cases yet. Complete ongoing incidents to see them here.</p>
        <a href="ongoing_monitoring.php" class="btn btn-primary">
            <i class="fa fa-heart-pulse"></i> Go to Ongoing Monitoring
        </a>
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

<a href="completed_cases.php" class="bottom-item active">
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
