<?php 
include("../../database/connection.php");
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'responder') {
    header("Location: ../../login.html");
    exit;
}

$responder_id = $_SESSION['user_id'];

// Get responder info
$responder_query = "SELECT resp_name FROM responder WHERE resp_id = ?";
$resp_stmt = $conn->prepare($responder_query);
$resp_stmt->bind_param("i", $responder_id);
$resp_stmt->execute();
$responder_info = $resp_stmt->get_result()->fetch_assoc();

$stmt = $conn->prepare("
    SELECT i.incident_id, i.status, i.start_time, i.end_time, p.pat_name,
           (SELECT MAX(v.recorded_at) FROM vitalstat v WHERE v.incident_id = i.incident_id) as last_vital
    FROM incident i
    JOIN patient p ON i.pat_id = p.pat_id
    WHERE i.resp_id = ?
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
<title>Incident History - VitalWear</title>
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

/* Dashboard Cards */
.dashboard-card {
    background: var(--surface);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow);
    border: 1px solid rgba(169, 183, 198, 0.2);
    padding: 24px;
}

/* Quick Action Buttons */
.btn-quick-action {
    background: linear-gradient(135deg, var(--medical-cyan) 0%, var(--trust-blue) 100%);
    color: white;
    border: none;
    padding: 14px 28px;
    border-radius: var(--radius-lg);
    font-weight: 600;
    box-shadow: var(--shadow);
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-quick-action:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}

/* Status Badges */
.status-badge {
    display: inline-flex;
    align-items: center;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-ongoing {
    background: rgba(245, 158, 11, 0.15);
    color: #d97706;
}

.status-transferred {
    background: rgba(59, 130, 246, 0.15);
    color: #2563eb;
}

.status-completed {
    background: rgba(107, 114, 128, 0.15);
    color: #4b5563;
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
            <span><?php echo htmlspecialchars($responder_info['resp_name'] ?? 'Responder'); ?></span>
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
<a href="../../api/auth/logout.php"><i class="fa fa-sign-out"></i> Logout</a>
</nav>

<main class="container" style="display:block;overflow-y:auto;width:100%;padding:20px;">

<div style="max-width: 900px;">

<h2 style="color: var(--deep-hospital-blue); margin-bottom: 20px; font-weight: 700; font-size: 1.5rem;">📋 Incident History</h2>

<?php if($incidents->num_rows > 0): ?>
    <?php while($incident = $incidents->fetch_assoc()): 
        $status_class = '';
        switch($incident['status']) {
            case 'ongoing': $status_class = 'status-ongoing'; break;
            case 'transferred': $status_class = 'status-transferred'; break;
            case 'completed': $status_class = 'status-completed'; break;
            default: $status_class = '';
        }
    ?>
    <div class="dashboard-card" style="margin-bottom: 16px;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px;">
            <div style="flex: 1; min-width: 200px;">
                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
                    <div style="width: 40px; height: 40px; background: var(--clinical-white); border-radius: var(--radius); display: flex; align-items: center; justify-content: center;">
                        <i class="fa fa-hashtag" style="color: var(--medical-cyan); font-size: 18px;"></i>
                    </div>
                    <div>
                        <p style="font-size: 18px; font-weight: 700; color: var(--deep-hospital-blue); margin: 0;">Incident #<?php echo $incident['incident_id']; ?></p>
                        <span class="status-badge <?php echo $status_class; ?>"><?php echo ucfirst($incident['status']); ?></span>
                    </div>
                </div>
                <p style="color: var(--system-gray); font-size: 14px; margin: 6px 0;"><i class="fa fa-user" style="width: 20px; color: var(--medical-cyan);"></i> <strong>Patient:</strong> <?php echo htmlspecialchars($incident['pat_name']); ?></p>
                <p style="color: var(--system-gray); font-size: 14px; margin: 6px 0;"><i class="fa fa-calendar" style="width: 20px; color: var(--medical-cyan);"></i> <strong>Started:</strong> <?php echo date('M d, Y h:i A', strtotime($incident['start_time'])); ?></p>
                <?php if($incident['last_vital']): ?>
                <p style="color: var(--system-gray); font-size: 14px; margin: 6px 0;"><i class="fa fa-heartbeat" style="width: 20px; color: var(--health-green);"></i> <strong>Last Vitals:</strong> <?php echo date('M d, h:i A', strtotime($incident['last_vital'])); ?></p>
                <?php endif; ?>
            </div>
            <div style="text-align: right; min-width: 150px;">
                <?php if($incident['end_time']): ?>
                <p style="color: var(--system-gray); font-size: 13px; margin: 0;"><i class="fa fa-check-circle" style="color: var(--health-green);"></i> Ended</p>
                <p style="color: var(--deep-hospital-blue); font-size: 14px; font-weight: 600; margin: 4px 0 0 0;"><?php echo date('M d, h:i A', strtotime($incident['end_time'])); ?></p>
                <?php else: ?>
                <p style="color: var(--system-gray); font-size: 13px; margin: 0;"><i class="fa fa-spinner fa-spin" style="color: var(--medical-cyan);"></i> In Progress</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endwhile; ?>
<?php else: ?>
    <div class="dashboard-card" style="text-align: left; padding: 40px 20px; max-width: 400px; margin: 0 auto;">
        <div style="width: 60px; height: 60px; background: var(--clinical-white); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;">
            <i class="fa fa-clipboard-list" style="font-size: 28px; color: var(--system-gray);"></i>
        </div>
        <p style="color: var(--system-gray); font-size: 16px; margin-bottom: 16px;">No incident history found</p>
        <a href="create_incident.php" class="btn-quick-action" style="width: auto; display: inline-block; padding: 12px 24px; font-size: 14px;">
            <i class="fa fa-plus"></i> Create First Incident
        </a>
    </div>
<?php endif; ?>

</div>

</main>

<nav class="bottom-nav">
<a href="dashboard.php" class="bottom-item"><i class="fa fa-gauge"></i><span>Home</span></a>
<a href="device.php" class="bottom-item"><i class="fa fa-tablet"></i><span>Device</span></a>
<a href="create_incident.php" class="bottom-item"><i class="fa fa-plus-circle"></i><span>Incident</span></a>
<a href="incident_history.php" class="bottom-item"><i class="fa fa-history"></i><span>History</span></a>
<a href="../../api/auth/logout.php"><i class="fa fa-sign-out"></i></a>
</nav>

</body>
</html>

