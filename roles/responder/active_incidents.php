<?php 
include("../../database/connection.php");
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'responder') {
    header("Location: ../../login.html");
    exit;
}

$responder_id = $_SESSION['user_id'];

// Get active incidents with latest vitals
$stmt = $conn->prepare("
    SELECT i.incident_id, i.status, i.start_time, p.pat_name, p.pat_id,
           (SELECT v.heart_rate FROM vitalstat v WHERE v.incident_id = i.incident_id ORDER BY v.recorded_at DESC LIMIT 1) as heart_rate,
           (SELECT v.bp_systolic FROM vitalstat v WHERE v.incident_id = i.incident_id ORDER BY v.recorded_at DESC LIMIT 1) as bp_systolic,
           (SELECT v.bp_diastolic FROM vitalstat v WHERE v.incident_id = i.incident_id ORDER BY v.recorded_at DESC LIMIT 1) as bp_diastolic,
           (SELECT v.oxygen_level FROM vitalstat v WHERE v.incident_id = i.incident_id ORDER BY v.recorded_at DESC LIMIT 1) as oxygen_level,
           (SELECT v.recorded_at FROM vitalstat v WHERE v.incident_id = i.incident_id ORDER BY v.recorded_at DESC LIMIT 1) as last_vital_time,
           (SELECT COUNT(*) FROM vitalstat v WHERE v.incident_id = i.incident_id) as vital_count
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
<a href="patient_vitals.php"><i class="fa fa-line-chart"></i> View Vitals</a>
<a href="transfer_incident.php"><i class="fa fa-exchange"></i> Transfer to Rescuer</a>
<a href="incident_history.php"><i class="fa fa-history"></i> Incident History</a>
<a href="../../api/auth/logout.php"><i class="fa fa-sign-out"></i> Logout</a>
</nav>

<main class="container" style="display:block;overflow-y:auto;width:100%;">

<h2 style="color:#dd4c56;margin-bottom:20px;">🚨 Active Incidents</h2>

<!-- AJAX Message Container -->
<div id="ajaxMessage" style="display:none;padding:15px;border-radius:10px;margin-bottom:20px;text-align:center;font-weight:600;"></div>

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
                <button id="autoBtn<?php echo $incident['incident_id']; ?>" onclick="toggleAutoInsert(<?php echo $incident['incident_id']; ?>)" style="display:inline-block;margin-top:10px;padding:8px 16px;background:#3b82f6;color:white;border:none;border-radius:6px;font-size:13px;cursor:pointer;"><i class="fa fa-play"></i> Auto Start</button>
            </div>
        </div>
        
<!-- Display Vitals -->
        <div id="vitalsDisplay<?php echo $incident['incident_id']; ?>">
        <?php if($incident['vital_count'] > 0): ?>
        <div style="margin-top:15px;padding-top:15px;border-top:1px solid #eee;">
            <p style="font-size:14px;font-weight:600;color:#333;margin-bottom:10px;">Latest Vitals:</p>
            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;text-align:center;">
                <?php if($incident['heart_rate'] !== null): ?>
                <div style="background:#fef2f2;padding:10px;border-radius:8px;">
                    <p style="color:#777;font-size:11px;">Heart Rate</p>
                    <p style="font-size:16px;font-weight:bold;color:#e74c3c;"><?php echo $incident['heart_rate'] > 0 ? $incident['heart_rate'] . ' bpm' : '-'; ?></p>
                </div>
                <?php endif; ?>
                <?php if($incident['bp_systolic'] !== null && $incident['bp_diastolic'] !== null): ?>
                <div style="background:#f0fdf4;padding:10px;border-radius:8px;">
                    <p style="color:#777;font-size:11px;">Blood Pressure</p>
                    <p style="font-size:16px;font-weight:bold;color:#22c55e;"><?php echo ($incident['bp_systolic'] > 0 ? $incident['bp_systolic'] : '-') . '/' . ($incident['bp_diastolic'] > 0 ? $incident['bp_diastolic'] : '-'); ?></p>
                </div>
                <?php endif; ?>
                <?php if($incident['oxygen_level'] !== null): ?>
                <div style="background:#eff6ff;padding:10px;border-radius:8px;">
                    <p style="color:#777;font-size:11px;">Oxygen</p>
                    <p style="font-size:16px;font-weight:bold;color:#0ea5e9;"><?php echo $incident['oxygen_level'] > 0 ? $incident['oxygen_level'] . '%' : '-'; ?></p>
                </div>
                <?php endif; ?>
                <?php if($incident['last_vital_time']): ?>
                <div style="background:#f5f3ff;padding:10px;border-radius:8px;">
                    <p style="color:#777;font-size:11px;">Time</p>
                    <p style="font-size:12px;font-weight:bold;color:#7c3aed;"><?php echo date('h:i A', strtotime($incident['last_vital_time'])); ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
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
<a href="incident_history.php" class="bottom-item"><i class="fa fa-history"></i><span>History</span></a>
<a href="../../api/auth/logout.php"><i class="fa fa-sign-out"></i></a>
</nav>

<script>
// Simulate vital stats and insert into database every 10 seconds
function simulateVitals(incidentId) {
    // Generate realistic random vital values
    // Heart rate: 60-100 bpm
    var heartRate = Math.floor(Math.random() * (100 - 60 + 1)) + 60;
    // Systolic BP: 100-140 mmHg
    var bpSystolic = Math.floor(Math.random() * (140 - 100 + 1)) + 100;
    // Diastolic BP: 60-90 mmHg
    var bpDiastolic = Math.floor(Math.random() * (90 - 60 + 1)) + 60;
    // Oxygen level: 95-100%
    var oxygenLevel = Math.floor(Math.random() * (100 - 95 + 1)) + 95;
    
    // Get current time
    var now = new Date();
    var timeString = now.toLocaleTimeString('en-US', {hour: 'numeric', minute: '2-digit'});
    
    // Insert vitals into database via API
    fetch('../../api/vitals/record_vitals.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `incident_id=${incidentId}&bp_systolic=${bpSystolic}&bp_diastolic=${bpDiastolic}&heart_rate=${heartRate}&oxygen_level=${oxygenLevel}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('Vitals recorded successfully:', data.vital);
        } else {
            console.error('Failed to record vitals:', data.message);
        }
    })
    .catch(error => {
        console.error('Error recording vitals:', error);
    });
    
    // Update vitals display
    var displayDiv = document.getElementById('vitalsDisplay' + incidentId);
    displayDiv.innerHTML = `
        <div style="margin-top:15px;padding-top:15px;border-top:1px solid #eee;">
            <p style="font-size:14px;font-weight:600;color:#333;margin-bottom:10px;">
                Latest Vitals: <span style="background:#dbeafe;color:#1e40af;padding:2px 6px;border-radius:4px;font-size:10px;">LIVE</span>
            </p>
            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;text-align:center;">
                <div style="background:#fef2f2;padding:10px;border-radius:8px;">
                    <p style="color:#777;font-size:11px;">Heart Rate</p>
                    <p style="font-size:16px;font-weight:bold;color:#e74c3c;">${heartRate} bpm</p>
                </div>
                <div style="background:#f0fdf4;padding:10px;border-radius:8px;">
                    <p style="color:#777;font-size:11px;">Blood Pressure</p>
                    <p style="font-size:16px;font-weight:bold;color:#22c55e;">${bpSystolic}/${bpDiastolic}</p>
                </div>
                <div style="background:#eff6ff;padding:10px;border-radius:8px;">
                    <p style="color:#777;font-size:11px;">Oxygen</p>
                    <p style="font-size:16px;font-weight:bold;color:#0ea5e9;">${oxygenLevel}%</p>
                </div>
                <div style="background:#f5f3ff;padding:10px;border-radius:8px;">
                    <p style="color:#777;font-size:11px;">Time</p>
                    <p style="font-size:12px;font-weight:bold;color:#7c3aed;">${timeString}</p>
                </div>
            </div>
        </div>
    `;
}

// Store interval IDs for each incident
var simulateIntervals = {};

// Start simulating vitals for an incident
function startSimulation(incidentId) {
    // Clear existing interval if any
    if (simulateIntervals[incidentId]) {
        clearInterval(simulateIntervals[incidentId]);
    }
    
    // Simulate immediately
    simulateVitals(incidentId);
    
    // Then simulate every 10 seconds
    simulateIntervals[incidentId] = setInterval(function() {
        simulateVitals(incidentId);
    }, 10000);
}

// Stop simulating vitals for an incident
function stopSimulation(incidentId) {
    if (simulateIntervals[incidentId]) {
        clearInterval(simulateIntervals[incidentId]);
        delete simulateIntervals[incidentId];
    }
}

// Toggle simulation mode
function toggleAutoInsert(incidentId) {
    var btn = document.getElementById('autoBtn' + incidentId);
    
    if (simulateIntervals[incidentId]) {
        // Stop simulation
        stopSimulation(incidentId);
        btn.innerHTML = '<i class="fa fa-play"></i> Auto Start';
        btn.style.background = '#3b82f6';
    } else {
        // Start simulation
        startSimulation(incidentId);
        btn.innerHTML = '<i class="fa fa-stop"></i> Stop Live';
        btn.style.background = '#ef4444';
    }
}

// Automatically start simulation for all active incidents when page loads
document.addEventListener('DOMContentLoaded', function() {
    <?php if($incidents->num_rows > 0): ?>
        // Reset incidents pointer and loop through them again
        <?php 
        $incidents->data_seek(0);
        while($inc = $incidents->fetch_assoc()): 
        ?>
        startSimulation(<?php echo $inc['incident_id']; ?>);
        
        // Update button to show it's running
        var btn = document.getElementById('autoBtn<?php echo $inc['incident_id']; ?>');
        if (btn) {
            btn.innerHTML = '<i class="fa fa-stop"></i> Stop Live';
            btn.style.background = '#ef4444';
        }
        <?php endwhile; ?>
    <?php endif; ?>
});
</script>

</body>
</html>

