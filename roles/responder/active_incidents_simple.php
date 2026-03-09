<?php 
include("../../database/connection.php");
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'responder') {
    header("Location: ../../login.html");
    exit;
}

$responder_id = $_SESSION['user_id'];

// Get active incidents
$stmt = $conn->prepare("
    SELECT i.incident_id, i.status, i.start_time, p.pat_name, p.pat_id,
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
<style>
.vital-card {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    text-align: center;
    margin: 5px;
}
.vital-value {
    font-size: 18px;
    font-weight: bold;
    margin: 5px 0;
}
.btn-start {
    background: #28a745;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 5px;
    cursor: pointer;
}
.btn-stop {
    background: #dc3545;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 5px;
    cursor: pointer;
}
.incident-card {
    background: white;
    padding: 20px;
    border-radius: 10px;
    margin: 10px 0;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}
</style>
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
<a href="../../api/auth/logout.php"><i class="fa fa-sign-out"></i> Logout</a>
</nav>

<main class="container">
<h2 style="color:#dd4c56;">🚨 Active Incidents</h2>

<div id="message" style="display:none;padding:10px;border-radius:5px;margin:10px 0;"></div>

<?php if($incidents->num_rows > 0): ?>
    <?php while($incident = $incidents->fetch_assoc()): ?>
    <div class="incident-card">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;">
            <div>
                <h3>Incident #<?php echo $incident['incident_id']; ?></h3>
                <p><strong>Patient:</strong> <?php echo htmlspecialchars($incident['pat_name']); ?></p>
                <p><strong>Started:</strong> <?php echo date('M d, Y h:i A', strtotime($incident['start_time'])); ?></p>
                <p><strong>Status:</strong> <span style="color:#f59e0b;"><?php echo ucfirst($incident['status']); ?></span></p>
            </div>
            <div>
                <button id="btn-<?php echo $incident['incident_id']; ?>" class="btn-start" onclick="toggleMonitoring(<?php echo $incident['incident_id']; ?>)">
                    <i class="fa fa-play"></i> Start Live Monitoring
                </button>
            </div>
        </div>
        
        <div id="vitals-<?php echo $incident['incident_id']; ?>">
            <h4>Vitals Monitor</h4>
            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;">
                <div class="vital-card">
                    <div>Heart Rate</div>
                    <div class="vital-value" id="hr-<?php echo $incident['incident_id']; ?>" style="color:#e74c3c;">-- bpm</div>
                </div>
                <div class="vital-card">
                    <div>Blood Pressure</div>
                    <div class="vital-value" id="bp-<?php echo $incident['incident_id']; ?>" style="color:#22c55e;">--/--</div>
                </div>
                <div class="vital-card">
                    <div>Oxygen Level</div>
                    <div class="vital-value" id="o2-<?php echo $incident['incident_id']; ?>" style="color:#0ea5e9;">--%</div>
                </div>
                <div class="vital-card">
                    <div>Last Update</div>
                    <div class="vital-value" id="time-<?php echo $incident['incident_id']; ?>" style="color:#7c3aed;">--:--</div>
                </div>
            </div>
        </div>
    </div>
    <?php endwhile; ?>
<?php else: ?>
    <div style="background:white;padding:40px;border-radius:15px;box-shadow:0 5px 15px rgba(0,0,0,0.1);text-align:center;">
        <p style="color:#777;font-size:18px;margin-bottom:15px;">No active incidents</p>
        <a href="create_incident.php" style="display:inline-block;padding:12px 24px;background:#dd4c56;color:white;text-decoration:none;border-radius:8px;font-weight:bold;">Create Incident</a>
    </div>
<?php endif; ?>

</main>

<script>
let monitoringIntervals = {};

function showMessage(text, type) {
    const msgDiv = document.getElementById('message');
    msgDiv.style.display = 'block';
    msgDiv.innerHTML = text;
    msgDiv.style.background = type === 'success' ? '#d4edda' : '#f8d7da';
    msgDiv.style.color = type === 'success' ? '#155724' : '#721c24';
    setTimeout(() => msgDiv.style.display = 'none', 3000);
}

function toggleMonitoring(incidentId) {
    const btn = document.getElementById('btn-' + incidentId);
    
    if (monitoringIntervals[incidentId]) {
        // Stop monitoring
        clearInterval(monitoringIntervals[incidentId]);
        delete monitoringIntervals[incidentId];
        btn.innerHTML = '<i class="fa fa-play"></i> Start Live Monitoring';
        btn.className = 'btn-start';
        showMessage('Live monitoring stopped', 'success');
    } else {
        // Start monitoring
        btn.innerHTML = '<i class="fa fa-stop"></i> Stop Monitoring';
        btn.className = 'btn-stop';
        showMessage('Live monitoring started', 'success');
        
        // Record immediately
        recordVitals(incidentId);
        
        // Then record every 5 seconds
        monitoringIntervals[incidentId] = setInterval(() => recordVitals(incidentId), 5000);
    }
}

function recordVitals(incidentId) {
    // Generate realistic vital values
    const heartRate = Math.floor(Math.random() * (100 - 60 + 1)) + 60;
    const bpSystolic = Math.floor(Math.random() * (140 - 100 + 1)) + 100;
    const bpDiastolic = Math.floor(Math.random() * (90 - 60 + 1)) + 60;
    const oxygenLevel = Math.floor(Math.random() * (100 - 95 + 1)) + 95;
    
    // Send to database
    fetch('../../api/vitals/record_vitals.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `incident_id=${incidentId}&bp_systolic=${bpSystolic}&bp_diastolic=${bpDiastolic}&heart_rate=${heartRate}&oxygen_level=${oxygenLevel}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update display
            document.getElementById('hr-' + incidentId).textContent = heartRate + ' bpm';
            document.getElementById('bp-' + incidentId).textContent = bpSystolic + '/' + bpDiastolic;
            document.getElementById('o2-' + incidentId).textContent = oxygenLevel + '%';
            document.getElementById('time-' + incidentId).textContent = new Date().toLocaleTimeString('en-US', {hour: '2-digit', minute: '2-digit'});
        } else {
            showMessage('Failed to record vitals: ' + data.message, 'error');
        }
    })
    .catch(error => {
        showMessage('Error: ' + error, 'error');
    });
}
</script>

</body>
</html>
