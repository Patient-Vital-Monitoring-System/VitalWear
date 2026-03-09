<?php 
include("../../database/connection.php");
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'responder') {
    header("Location: ../../login.html");
    exit;
}

$responder_id = $_SESSION['user_id'];
$incident_id = $_GET['incident_id'] ?? 0;
$patient_name = "";
$vitals = [];

if ($incident_id > 0) {
    // Get patient name for this incident
    $pat_stmt = $conn->prepare("
        SELECT p.pat_name FROM patient p
        JOIN incident i ON i.pat_id = p.pat_id
        WHERE i.incident_id = ?
    ");
    $pat_stmt->bind_param("i", $incident_id);
    $pat_stmt->execute();
    $pat_result = $pat_stmt->get_result();
    if ($pat_result->num_rows > 0) {
        $patient = $pat_result->fetch_assoc();
        $patient_name = $patient['pat_name'];
    }
    
    // Get all vitals for this incident
    $vitals_stmt = $conn->prepare("
        SELECT * FROM vitalstat 
        WHERE incident_id = ? 
        ORDER BY recorded_at DESC
    ");
    $vitals_stmt->bind_param("i", $incident_id);
    $vitals_stmt->execute();
    $vitals_result = $vitals_stmt->get_result();
    
    // Debug: Check if query returns anything
    echo "<!-- Debug: Query executed for incident_id: " . $incident_id . " -->";
    echo "<!-- Debug: Query result rows: " . $vitals_result->num_rows . " -->";
    
    while ($row = $vitals_result->fetch_assoc()) {
        $vitals[] = $row;
    }
    
    // Debug: Show first vital if exists
    if (count($vitals) > 0) {
        echo "<!-- Debug: First vital: " . print_r($vitals[0], true) . " -->";
    }
}

// Get all ongoing incidents for selector
$incidents_stmt = $conn->prepare("
    SELECT i.incident_id, p.pat_name, i.status
    FROM incident i
    JOIN patient p ON i.pat_id = p.pat_id
    WHERE i.resp_id = ? AND i.status = 'ongoing'
    ORDER BY i.start_time DESC
");
$incidents_stmt->bind_param("i", $responder_id);
$incidents_stmt->execute();
$incidents_result = $incidents_stmt->get_result();
$incidents = [];
while ($row = $incidents_result->fetch_assoc()) {
    $incidents[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Patient Vitals - VitalWear</title>
<link rel="stylesheet" href="../../assets/css/styles.css">
<script src="https://kit.fontawesome.com/96e37b53f1.js"></script>
<style>
    .vital-card {
        background: white;
        padding: 15px;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        margin-bottom: 10px;
    }
    .vital-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 10px;
        text-align: center;
    }
    .vital-item {
        padding: 10px;
        border-radius: 8px;
    }
    .heart-rate { background: #fef2f2; }
    .bp { background: #f0fdf4; }
    .oxygen { background: #eff6ff; }
    .time { background: #f5f3ff; }
    .vital-label {
        font-size: 11px;
        color: #777;
        margin-bottom: 4px;
    }
    .vital-value {
        font-size: 16px;
        font-weight: bold;
    }
    .no-vitals {
        text-align: center;
        padding: 40px;
        color: #777;
    }
    .auto-badge {
        display: inline-block;
        background: #dbeafe;
        color: #1e40af;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 10px;
        margin-left: 5px;
    }
</style>
</head>
<body>

<header class="topbar">
    <div style="display: flex; align-items: center; gap: 12px;">
        <i class="fa fa-heart-pulse" style="font-size: 24px; color: var(--medical-cyan);"></i>
        <span style="font-size: 18px; font-weight: 700;">VitalWear</span>
    </div>
</header>

<nav id="sidebar">
<a href="dashboard.php"><i class="fa fa-gauge"></i> Dashboard</a>
<a href="device.php"><i class="fa fa-tablet"></i> My Device</a>
<a href="active_incidents.php"><i class="fa fa-exclamation-circle"></i> Active Incidents</a>
<a href="create_incident.php"><i class="fa fa-plus-circle"></i> Create Incident</a>
<a href="patient_vitals.php"><i class="fa fa-line-chart"></i> View Vitals</a>
<a href="transfer_incident.php"><i class="fa fa-exchange"></i> Transfer to Rescuer</a>
<a href="incident_history.php"><i class="fa fa-history"></i> Incident History</a>
<a href="../../api/auth/logout.php"><i class="fa fa-sign-out"></i> Logout</a>
</nav>

<main class="container" style="display:block;overflow-y:auto;width:100%;">

<h2 style="color:#dd4c56;margin-bottom:20px;">📊 View Patient Vitals</h2>

<!-- Incident Selector -->
<div style="background:white;padding:20px;border-radius:15px;box-shadow:0 5px 15px rgba(0,0,0,0.1);width:100%;margin-bottom:20px;">
    <label style="display:block;margin-bottom:8px;font-weight:600;color:#333;">Select Incident</label>
    <select id="incidentSelector" onchange="window.location.href='patient_vitals.php?incident_id='+this.value" style="width:100%;padding:12px;border:1px solid #ddd;border-radius:8px;font-size:14px;">
        <option value="">-- Select Incident --</option>
        <?php foreach($incidents as $inc): ?>
        <option value="<?php echo $inc['incident_id']; ?>" <?php echo $incident_id == $inc['incident_id'] ? 'selected' : ''; ?>>
            #<?php echo $inc['incident_id']; ?> - <?php echo htmlspecialchars($inc['pat_name']); ?> (<?php echo $inc['status']; ?>)
        </option>
        <?php endforeach; ?>
    </select>
</div>

<?php 
// Debug information
echo "<!-- Debug: Incident ID: " . $incident_id . " -->";
echo "<!-- Debug: Patient name: '" . $patient_name . "' -->";
echo "<!-- Debug: Vitals count: " . count($vitals) . " -->";
?>
<?php if($incident_id > 0 && $patient_name): ?>
<!-- Patient Info -->
<div style="background:#fef2f2;padding:15px;border-radius:10px;margin-bottom:20px;text-align:center;">
    <p style="color:#333;font-size:16px;">Patient: <strong><?php echo htmlspecialchars($patient_name); ?></strong></p>
    <p style="color:#777;font-size:14px;">Incident #<?php echo $incident_id; ?></p>
</div>


<!-- Latest Vital Display -->
<?php if(count($vitals) > 0): ?>
<div style="background:white;padding:20px;border-radius:15px;box-shadow:0 5px 15px rgba(0,0,0,0.1);width:100%;margin-bottom:20px;">
    <h3 style="color:#22c55e;margin-bottom:15px;text-align:center;">📈 Latest Vital Reading</h3>
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:15px;text-align:center;">
        <div style="background:#fef2f2;padding:15px;border-radius:10px;">
            <div class="vital-label">Heart Rate</div>
            <div style="font-size:24px;font-weight:800;color:#e74c3c;">
                <?php echo $vitals[0]['heart_rate'] > 0 ? $vitals[0]['heart_rate'] . ' bpm' : '-'; ?>
            </div>
        </div>
        <div style="background:#f0fdf4;padding:15px;border-radius:10px;">
            <div class="vital-label">Blood Pressure</div>
            <div style="font-size:20px;font-weight:bold;color:#22c55e;">
                <?php echo ($vitals[0]['bp_systolic'] > 0 ? $vitals[0]['bp_systolic'] : '-') . '/' . ($vitals[0]['bp_diastolic'] > 0 ? $vitals[0]['bp_diastolic'] : '-'); ?>
            </div>
        </div>
        <div style="background:#eff6ff;padding:15px;border-radius:10px;">
            <div class="vital-label">Oxygen (SpO2)</div>
            <div style="font-size:24px;font-weight:800;color:#0ea5e9;">
                <?php echo $vitals[0]['oxygen_level'] > 0 ? $vitals[0]['oxygen_level'] . '%' : '-'; ?>
            </div>
        </div>
        <div style="background:#f5f3ff;padding:15px;border-radius:10px;">
            <div class="vital-label">Last Updated</div>
            <div style="font-size:14px;font-weight:bold;color:#7c3aed;">
                <?php echo date('h:i:s A', strtotime($vitals[0]['recorded_at'])); ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Vitals History -->
<?php 
// Debug: Show vital count
echo "<!-- Debug: Total vitals found: " . count($vitals) . " -->";
?>
<?php if(count($vitals) > 0): ?>
    <div style="margin-bottom:20px;">
        <p style="color:#777;font-size:14px;margin-bottom:10px;">Total Records: <?php echo count($vitals); ?></p>
    </div>
    
    <?php foreach($vitals as $vital): ?>
    <div class="vital-card">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
            <p style="color:#777;font-size:12px;">
                Recorded: <?php echo date('M d, Y h:i:s A', strtotime($vital['recorded_at'])); ?>
            </p>
            <p style="color:#777;font-size:11px;">By: <?php echo ucfirst($vital['recorded_by']); ?></p>
        </div>
        <div class="vital-grid">
            <div class="vital-item heart-rate">
                <div class="vital-label">Heart Rate</div>
                <div class="vital-value" style="color:#e74c3c;">
                    <?php echo $vital['heart_rate'] > 0 ? $vital['heart_rate'] . ' bpm' : '-'; ?>
                </div>
            </div>
            <div class="vital-item bp">
                <div class="vital-label">Blood Pressure</div>
                <div class="vital-value" style="color:#22c55e;">
                    <?php echo ($vital['bp_systolic'] > 0 ? $vital['bp_systolic'] : '-') . '/' . ($vital['bp_diastolic'] > 0 ? $vital['bp_diastolic'] : '-'); ?>
                </div>
            </div>
            <div class="vital-item oxygen">
                <div class="vital-label">Oxygen (SpO2)</div>
                <div class="vital-value" style="color:#0ea5e9;">
                    <?php echo $vital['oxygen_level'] > 0 ? $vital['oxygen_level'] . '%' : '-'; ?>
                </div>
            </div>
            <div class="vital-item time">
                <div class="vital-label">Time</div>
                <div class="vital-value" style="color:#7c3aed;">
                    <?php echo date('h:i A', strtotime($vital['recorded_at'])); ?>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    
<?php else: ?>
    <div class="no-vitals">
        <i class="fa fa-heartbeat" style="font-size:48px;color:#ddd;margin-bottom:15px;"></i>
        <p style="font-size:16px;margin-bottom:10px;">No vital records found</p>
        <p style="font-size:14px;color:#999;">Vitals will appear here when recorded automatically or manually.</p>
    </div>
<?php endif; ?>

<?php elseif($incident_id > 0): ?>
<div style="background:white;padding:40px;border-radius:15px;box-shadow:0 5px 15px rgba(0,0,0,0.1);width:100%;text-align:center;">
    <p style="color:#777;font-size:18px;">Invalid incident selected</p>
</div>
<?php else: ?>
<div style="background:white;padding:40px;border-radius:15px;box-shadow:0 5px 15px rgba(0,0,0,0.1);width:100%;text-align:center;">
    <i class="fa fa-line-chart" style="font-size:48px;color:#ddd;margin-bottom:15px;"></i>
    <p style="color:#777;font-size:18px;margin-bottom:10px;">Select an incident to view vital stats</p>
    <p style="font-size:14px;color:#999;">Choose an incident from the dropdown above to see vital history.</p>
</div>
<?php endif; ?>

</main>

<nav class="bottom-nav">
<a href="dashboard.php" class="bottom-item"><i class="fa fa-gauge"></i><span>Home</span></a>
<a href="device.php" class="bottom-item"><i class="fa fa-tablet"></i><span>Device</span></a>
<a href="create_incident.php" class="bottom-item"><i class="fa fa-plus-circle"></i><span>Incident</span></a>
<a href="patient_vitals.php" class="bottom-item"><i class="fa fa-line-chart"></i><span>Vitals</span></a>
<a href="incident_history.php" class="bottom-item"><i class="fa fa-history"></i><span>History</span></a>
<a href="../../api/auth/logout.php"><i class="fa fa-sign-out"></i></a>
</nav>

<script>
// Auto-refresh disabled - page must be manually refreshed
// <?php if($incident_id > 0): ?>
// setInterval(function() {
//     location.reload();
// }, 10000);
// <?php endif; ?>
</script>

</body>
</html>

