<?php 
include("../../database/connection.php");
session_start();

if (!isset($_SESSION['responder_id'])) {
    header("Location: ../../login.html");
    exit;
}

$responder_id = $_SESSION['responder_id'];
$message = "";
$message_type = "";

$incident_id = $_GET['incident_id'] ?? 0;

$stmt = $conn->prepare("
    SELECT i.incident_id, p.pat_name, i.status
    FROM incident i
    JOIN patient p ON i.pat_id = p.pat_id
    WHERE i.resp_id = ? AND i.status = 'ongoing'
    ORDER BY i.start_time DESC
");
$stmt->bind_param("i", $responder_id);
$stmt->execute();
$incidents = $stmt->get_result();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $incident_id = $_POST['incident_id'] ?? 0;
    $bp_systolic = $_POST['bp_systolic'] ?? 0;
    $bp_diastolic = $_POST['bp_diastolic'] ?? 0;
    $heart_rate = $_POST['heart_rate'] ?? 0;
    $oxygen_level = $_POST['oxygen_level'] ?? 0;
    
    if (empty($incident_id)) {
        $message = "Please select an incident";
        $message_type = "error";
    } else {
        $stmt = $conn->prepare("INSERT INTO vitalstat (incident_id, recorded_by, bp_systolic, bp_diastolic, heart_rate, oxygen_level) VALUES (?, 'responder', ?, ?, ?, ?)");
        $stmt->bind_param("iiii", $incident_id, $bp_systolic, $bp_diastolic, $heart_rate, $oxygen_level);
        
        if ($stmt->execute()) {
            $message = "Vitals recorded successfully!";
            $message_type = "success";
        } else {
            $message = "Failed to record vitals";
            $message_type = "error";
        }
    }
}

$latest_vitals = null;
if ($incident_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM vitalstat WHERE incident_id = ? ORDER BY recorded_at DESC LIMIT 1");
    $stmt->bind_param("i", $incident_id);
    $stmt->execute();
    $latest_vitals = $stmt->get_result()->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Record Vitals - VitalWear</title>
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

<h2 style="color:#dd4c56;margin-bottom:20px;">❤️ Record Vitals</h2>

<?php if($message): ?>
<div style="background:<?php echo $message_type === 'success' ? '#dcfce7' : '#fee2e2'; ?>;color:<?php echo $message_type === 'success' ? '#166534' : '#991b1b'; ?>;padding:15px;border-radius:10px;margin-bottom:20px;text-align:center;font-weight:600;">
    <?php echo $message; ?>
</div>
<?php endif; ?>

<div style="background:white;padding:25px;border-radius:15px;box-shadow:0 5px 15px rgba(0,0,0,0.1);width:100%;max-width:500px;margin-bottom:20px;">
    <form method="POST">
        <div style="margin-bottom:20px;">
            <label style="display:block;margin-bottom:8px;font-weight:600;color:#333;">Select Incident *</label>
            <select name="incident_id" required style="width:100%;padding:12px;border:1px solid #ddd;border-radius:8px;font-size:14px;">
                <option value="">-- Select Incident --</option>
                <?php while($inc = $incidents->fetch_assoc()): ?>
                <option value="<?php echo $inc['incident_id']; ?>" <?php echo $incident_id == $inc['incident_id'] ? 'selected' : ''; ?>>
                    #<?php echo $inc['incident_id']; ?> - <?php echo htmlspecialchars($inc['pat_name']); ?> (<?php echo $inc['status']; ?>)
                </option>
                <?php endwhile; ?>
            </select>
        </div>
        
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;margin-bottom:20px;">
            <div>
                <label style="display:block;margin-bottom:8px;font-weight:600;color:#333;">Systolic BP</label>
                <input type="number" name="bp_systolic" placeholder="120" style="width:100%;padding:12px;border:1px solid #ddd;border-radius:8px;font-size:14px;">
            </div>
            <div>
                <label style="display:block;margin-bottom:8px;font-weight:600;color:#333;">Diastolic BP</label>
                <input type="number" name="bp_diastolic" placeholder="80" style="width:100%;padding:12px;border:1px solid #ddd;border-radius:8px;font-size:14px;">
            </div>
        </div>
        
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;margin-bottom:20px;">
            <div>
                <label style="display:block;margin-bottom:8px;font-weight:600;color:#333;">Heart Rate (bpm)</label>
                <input type="number" name="heart_rate" placeholder="72" style="width:100%;padding:12px;border:1px solid #ddd;border-radius:8px;font-size:14px;">
            </div>
            <div>
                <label style="display:block;margin-bottom:8px;font-weight:600;color:#333;">Oxygen Level (%)</label>
                <input type="number" name="oxygen_level" placeholder="98" style="width:100%;padding:12px;border:1px solid #ddd;border-radius:8px;font-size:14px;">
            </div>
        </div>
        
        <button type="submit" style="width:100%;padding:14px;background:#dd4c56;color:white;border:none;border-radius:8px;font-weight:bold;font-size:16px;cursor:pointer;">
            Save Vitals
        </button>
    </form>
</div>

<?php if($latest_vitals): ?>
<div style="background:white;padding:20px;border-radius:15px;box-shadow:0 5px 15px rgba(0,0,0,0.1);width:100%;max-width:500px;">
    <h3 style="color:#22c55e;margin-bottom:15px;">Latest Reading</h3>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
        <div><p style="color:#777;font-size:12px;">Heart Rate</p><p style="font-size:20px;font-weight:bold;color:#e74c3c;"><?php echo $latest_vitals['heart_rate']; ?> bpm</p></div>
        <div><p style="color:#777;font-size:12px;">Blood Pressure</p><p style="font-size:20px;font-weight:bold;color:#22c55e;"><?php echo $latest_vitals['bp_systolic']; ?>/<?php echo $latest_vitals['bp_diastolic']; ?></p></div>
        <div><p style="color:#777;font-size:12px;">Oxygen</p><p style="font-size:20px;font-weight:bold;color:#0ea5e9;"><?php echo $latest_vitals['oxygen_level']; ?>%</p></div>
        <div><p style="color:#777;font-size:12px;">Time</p><p style="font-size:14px;"><?php echo date('h:i A', strtotime($latest_vitals['recorded_at'])); ?></p></div>
    </div>
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

