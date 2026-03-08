<?php 
include("../../database/connection.php");
$dbStatus = isset($conn) && !$conn->connect_error;
session_start();

// Check if logged in
if (!isset($_SESSION['responder_id'])) {
    header("Location: ../../login.html");
    exit;
}

$responder_id = $_SESSION['responder_id'];
$message = "";
$message_type = "";

// Handle device request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_device'])) {
    // Get an available device
    $stmt = $conn->prepare("SELECT dev_id FROM device WHERE dev_status = 'available' LIMIT 1");
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $device = $result->fetch_assoc();
        $dev_id = $device['dev_id'];
        
        // Assign device to responder
        $assign_stmt = $conn->prepare("INSERT INTO device_log (dev_id, resp_id, date_assigned) VALUES (?, ?, NOW())");
        $assign_stmt->bind_param("ii", $dev_id, $responder_id);
        
        if ($assign_stmt->execute()) {
            // Update device status
            $update_stmt = $conn->prepare("UPDATE device SET dev_status = 'assigned' WHERE dev_id = ?");
            $update_stmt->bind_param("i", $dev_id);
            $update_stmt->execute();
            
            $message = "Device assigned successfully!";
            $message_type = "success";
        } else {
            $message = "Failed to assign device.";
            $message_type = "error";
        }
    } else {
        $message = "No devices available. Please try again later.";
        $message_type = "error";
    }
}

// Handle device return
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['return_device'])) {
    $stmt = $conn->prepare("
        UPDATE device_log 
        SET date_returned = NOW() 
        WHERE resp_id = ? AND date_returned IS NULL
    ");
    $stmt->bind_param("i", $responder_id);
    
    if ($stmt->execute()) {
        // Get the device that was returned
        $dev_stmt = $conn->prepare("
            SELECT d.dev_id FROM device d
            JOIN device_log dl ON d.dev_id = dl.dev_id
            WHERE dl.resp_id = ? AND dl.date_returned IS NOT NULL
            ORDER BY dl.date_assigned DESC
            LIMIT 1
        ");
        $dev_stmt->bind_param("i", $responder_id);
        $dev_stmt->execute();
        $dev_result = $dev_stmt->get_result();
        
        if ($dev_result->num_rows > 0) {
            $dev = $dev_result->fetch_assoc();
            // Update device status to available
            $update_stmt = $conn->prepare("UPDATE device SET dev_status = 'available' WHERE dev_id = ?");
            $update_stmt->bind_param("i", $dev['dev_id']);
            $update_stmt->execute();
        }
        
        $message = "Device returned successfully!";
        $message_type = "success";
    } else {
        $message = "Failed to return device.";
        $message_type = "error";
    }
}

// Get current assigned device
$stmt = $conn->prepare("
    SELECT d.dev_id, d.dev_serial, d.dev_status, dl.date_assigned
    FROM device_log dl
    JOIN device d ON dl.dev_id = d.dev_id
    WHERE dl.resp_id = ? AND dl.date_returned IS NULL
    ORDER BY dl.date_assigned DESC
    LIMIT 1
");
$stmt->bind_param("i", $responder_id);
$stmt->execute();
$assigned_device = $stmt->get_result()->fetch_assoc();

// Get available devices count
$stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM device WHERE dev_status = 'available'");
$stmt->execute();
$available_count = $stmt->get_result()->fetch_assoc()['cnt'];
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Device - VitalWear</title>

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

<main class="container" style="display:block;overflow-y:auto;">

<?php if($message): ?>
<div style="background:<?php echo $message_type === 'success' ? '#dcfce7' : '#fee2e2'; ?>;color:<?php echo $message_type === 'success' ? '#166534' : '#991b1b'; ?>;padding:15px;border-radius:10px;margin-bottom:20px;text-align:center;font-weight:600;">
    <?php echo $message; ?>
</div>
<?php endif; ?>

<!-- Current Device Card -->
<div style="background:white;padding:25px;border-radius:15px;box-shadow:0 5px 15px rgba(0,0,0,0.1);width:100%;margin-bottom:20px;">
    <h3 style="color:#dd4c56;margin-bottom:20px;">📱 My Device</h3>
    
    <?php if($assigned_device): ?>
    <div style="text-align:center;padding:30px;">
        <div style="width:80px;height:80px;background:#fef2f2;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;">
            <i class="fa fa-tablet" style="font-size:36px;color:#dd4c56;"></i>
        </div>
        <p style="font-size:24px;font-weight:bold;"><?php echo htmlspecialchars($assigned_device['dev_serial']); ?></p>
        <p style="color:#22c55e;font-weight:600;margin:10px 0;">Device Assigned</p>
        <p style="color:#777;font-size:14px;">Assigned on: <?php echo date('M d, Y h:i A', strtotime($assigned_device['date_assigned'])); ?></p>
        
        <form method="POST" style="margin-top:25px;">
            <button type="submit" name="return_device" onclick="return confirm('Are you sure you want to return this device?');" style="padding:12px 30px;background:#ef4444;color:white;border:none;border-radius:8px;font-weight:bold;cursor:pointer;font-size:14px;">
                <i class="fa fa-reply"></i> Return Device
            </button>
        </form>
    </div>
    <?php else: ?>
    <div style="text-align:center;padding:30px;">
        <div style="width:80px;height:80px;background:#f1f5f9;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;">
            <i class="fa fa-tablet" style="font-size:36px;color:#94a3b8;"></i>
        </div>
        <p style="font-size:18px;color:#777;margin-bottom:10px;">No Device Assigned</p>
        <p style="color:#999;font-size:14px;margin-bottom:20px;">Available devices: <?php echo $available_count; ?></p>
        
        <?php if($available_count > 0): ?>
        <form method="POST">
            <button type="submit" name="request_device" style="padding:12px 30px;background:#22c55e;color:white;border:none;border-radius:8px;font-weight:bold;cursor:pointer;font-size:14px;">
                <i class="fa fa-plus"></i> Request Device
            </button>
        </form>
        <?php else: ?>
        <p style="color:#f59e0b;font-size:14px;">No devices available. Please try again later.</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Device Info Card -->
<div style="background:white;padding:25px;border-radius:15px;box-shadow:0 5px 15px rgba(0,0,0,0.1);width:100%;">
    <h3 style="color:#dd4c56;margin-bottom:15px;">ℹ️ Device Information</h3>
    <p style="color:#777;font-size:14px;line-height:1.6;">
        Your assigned device is used to record patient vital signs during incidents. 
        Make sure to keep the device charged and in good condition.
    </p>
    <ul style="color:#777;font-size:14px;margin-top:15px;padding-left:20px;line-height:1.8;">
        <li>Check battery level before each use</li>
        <li>Return device when not in use</li>
        <li>Report any issues immediately</li>
    </ul>
</div>

</main>

<nav class="bottom-nav">

<a href="dashboard.php" class="bottom-item">
<i class="fa fa-gauge"></i>
<span>Home</span>
</a>

<a href="device.php" class="bottom-item">
<i class="fa fa-tablet"></i>
<span>Device</span>
</a>

<a href="create_incident.php" class="bottom-item">
<i class="fa fa-plus-circle"></i>
<span>Incident</span>
</a>

<a href="record_vitals.php" class="bottom-item">
<i class="fa fa-heartbeat"></i>
<span>Vitals</span>
</a>

<a href="incident_history.php" class="bottom-item">
<i class="fa fa-history"></i>
<span>History</span>
</a>

<a href="../../api/auth/logout.php"><i class="fa fa-sign-out"></i></a>
</nav>

</body>
</html>

