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

$stmt = $conn->prepare("SELECT resc_id, resc_name FROM rescuer");
$stmt->execute();
$rescuers = $stmt->get_result();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $incident_id = $_POST['incident_id'] ?? 0;
    $resc_id = $_POST['resc_id'] ?? 0;
    
    if (empty($incident_id) || empty($resc_id)) {
        $message = "Please select incident and rescuer";
        $message_type = "error";
    } else {
        $stmt = $conn->prepare("UPDATE incident SET resc_id = ?, status = 'transferred' WHERE incident_id = ? AND resp_id = ?");
        $stmt->bind_param("iii", $resc_id, $incident_id, $responder_id);
        
        if ($stmt->execute()) {
            $message = "Incident transferred successfully!";
            $message_type = "success";
        } else {
            $message = "Failed to transfer incident";
            $message_type = "error";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Transfer Incident - VitalWear</title>
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

<h2 style="color:#dd4c56;margin-bottom:20px;">🔄 Transfer Incident</h2>

<?php if($message): ?>
<div style="background:<?php echo $message_type === 'success' ? '#dcfce7' : '#fee2e2'; ?>;color:<?php echo $message_type === 'success' ? '#166534' : '#991b1b'; ?>;padding:15px;border-radius:10px;margin-bottom:20px;text-align:center;font-weight:600;">
    <?php echo $message; ?>
</div>
<?php endif; ?>

<div style="background:white;padding:25px;border-radius:15px;box-shadow:0 5px 15px rgba(0,0,0,0.1);width:100%;max-width:500px;">
    <form method="POST">
        <div style="margin-bottom:20px;">
            <label style="display:block;margin-bottom:8px;font-weight:600;color:#333;">Select Incident *</label>
            <select name="incident_id" required style="width:100%;padding:12px;border:1px solid #ddd;border-radius:8px;font-size:14px;">
                <option value="">-- Select Incident --</option>
                <?php while($inc = $incidents->fetch_assoc()): ?>
                <option value="<?php echo $inc['incident_id']; ?>">
                    #<?php echo $inc['incident_id']; ?> - <?php echo htmlspecialchars($inc['pat_name']); ?>
                </option>
                <?php endwhile; ?>
            </select>
        </div>
        
        <div style="margin-bottom:20px;">
            <label style="display:block;margin-bottom:8px;font-weight:600;color:#333;">Transfer to Rescuer *</label>
            <select name="resc_id" required style="width:100%;padding:12px;border:1px solid #ddd;border-radius:8px;font-size:14px;">
                <option value="">-- Select Rescuer --</option>
                <?php while($rescuer = $rescuers->fetch_assoc()): ?>
                <option value="<?php echo $rescuer['resc_id']; ?>">
                    <?php echo htmlspecialchars($rescuer['resc_name']); ?>
                </option>
                <?php endwhile; ?>
            </select>
        </div>
        
        <button type="submit" style="width:100%;padding:14px;background:#dd4c56;color:white;border:none;border-radius:8px;font-weight:bold;font-size:16px;cursor:pointer;">
            Transfer Incident
        </button>
    </form>
</div>

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

