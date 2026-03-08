<?php 
include("../../database/connection.php");
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'responder') {
    header("Location: ../../login.html");
    exit;
}

$responder_id = $_SESSION['user_id'];
$message = "";
$message_type = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pat_name = $_POST['pat_name'] ?? '';
    $pat_contact = $_POST['pat_contact'] ?? '';
    
    if (empty($pat_name)) {
        $message = "Patient name is required";
        $message_type = "error";
    } else {
        // Create patient - birthdate defaults to today if not provided
        $birthdate = date('Y-m-d');
        $stmt = $conn->prepare("INSERT INTO patient (pat_name, birthdate, contact_number) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $pat_name, $birthdate, $pat_contact);
        
        if ($stmt->execute()) {
            $pat_id = $conn->insert_id;
            
            // Check if responder has a device
            $dev_stmt = $conn->prepare("
                SELECT d.dev_id FROM device_log dl
                JOIN device d ON dl.dev_id = d.dev_id
                WHERE dl.resp_id = ? AND dl.date_returned IS NULL
                LIMIT 1
            ");
            $dev_stmt->bind_param("i", $responder_id);
            $dev_stmt->execute();
            $dev_result = $dev_stmt->get_result();
            
            $log_id = null;
            if ($dev_result->num_rows > 0) {
                $device = $dev_result->fetch_assoc();
                // Get the device log ID
                $log_stmt = $conn->prepare("SELECT log_id FROM device_log WHERE dev_id = ? AND resp_id = ? AND date_returned IS NULL ORDER BY date_assigned DESC LIMIT 1");
                $log_stmt->bind_param("ii", $device['dev_id'], $responder_id);
                $log_stmt->execute();
                $log_result = $log_stmt->get_result();
                if ($log_result->num_rows > 0) {
                    $log = $log_result->fetch_assoc();
                    $log_id = $log['log_id'];
                }
            }
            
            // Create incident with 'ongoing' status
            $inc_stmt = $conn->prepare("INSERT INTO incident (log_id, pat_id, resp_id, status) VALUES (?, ?, ?, 'ongoing')");
            $inc_stmt->bind_param("iii", $log_id, $pat_id, $responder_id);
            
            if ($inc_stmt->execute()) {
                $incident_id = $conn->insert_id;
                
                // Auto-insert initial vital stats with default values
                $vital_stmt = $conn->prepare("INSERT INTO vitalstat (incident_id, recorded_by, bp_systolic, bp_diastolic, heart_rate, oxygen_level) VALUES (?, 'responder', 0, 0, 0, 0)");
                $vital_stmt->bind_param("i", $incident_id);
                $vital_stmt->execute();
                
                header("Location: patient_vitals.php?incident_id=" . $incident_id);
                exit;
            } else {
                $message = "Failed to create incident";
                $message_type = "error";
            }
        } else {
            $message = "Failed to create patient";
            $message_type = "error";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Create Incident - VitalWear</title>
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

<h2 style="color:#dd4c56;margin-bottom:20px;">➕ Create Incident</h2>

<?php if($message): ?>
<div style="background:<?php echo $message_type === 'error' ? '#fee2e2' : '#dcfce7'; ?>;color:<?php echo $message_type === 'error' ? '#991b1b' : '#166534'; ?>;padding:15px;border-radius:10px;margin-bottom:20px;text-align:center;font-weight:600;">
    <?php echo $message; ?>
</div>
<?php endif; ?>

<div style="background:white;padding:25px;border-radius:15px;box-shadow:0 5px 15px rgba(0,0,0,0.1);width:100%;max-width:500px;">
    <form method="POST">
        <div style="margin-bottom:20px;">
            <label style="display:block;margin-bottom:8px;font-weight:600;color:#333;">Patient Name *</label>
            <input type="text" name="pat_name" required style="width:100%;padding:12px;border:1px solid #ddd;border-radius:8px;font-size:14px;">
        </div>
        
        <div style="margin-bottom:20px;">
            <label style="display:block;margin-bottom:8px;font-weight:600;color:#333;">Contact Number</label>
            <input type="text" name="pat_contact" style="width:100%;padding:12px;border:1px solid #ddd;border-radius:8px;font-size:14px;">
        </div>
        
        <button type="submit" style="width:100%;padding:14px;background:#dd4c56;color:white;border:none;border-radius:8px;font-weight:bold;font-size:16px;cursor:pointer;">
            Create Incident & Record Vitals
        </button>
    </form>
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

