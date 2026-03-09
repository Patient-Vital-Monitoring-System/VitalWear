<?php 
include("../../database/connection.php");
$dbStatus = isset($conn) && !$conn->connect_error;
session_start();

// Check if logged in
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
        
        // Get mgmt_id (default to 1 if not available)
        $mgmt_id = 1;
        
        // Assign device to responder
        $assign_stmt = $conn->prepare("INSERT INTO device_log (dev_id, resp_id, mgmt_id, date_assigned) VALUES (?, ?, ?, NOW())");
        $assign_stmt->bind_param("iii", $dev_id, $responder_id, $mgmt_id);
        
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

<main class="container" style="display:block;overflow-y:auto;">

<?php if($message): ?>
<div style="background: <?php echo $message_type === 'success' ? 'rgba(46, 219, 179, 0.15)' : 'rgba(239, 68, 68, 0.15)'; ?>; color: <?php echo $message_type === 'success' ? 'var(--health-green)' : '#dc2626'; ?>; padding: 16px 24px; border-radius: var(--radius); margin-bottom: 24px; text-align: center; font-weight: 600; border: 1px solid <?php echo $message_type === 'success' ? 'rgba(46, 219, 179, 0.3)' : 'rgba(239, 68, 68, 0.3)'; ?>; display: flex; align-items: center; justify-content: center; gap: 10px;">
    <i class="fa <?php echo $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
    <?php echo $message; ?>
</div>
<?php endif; ?>

<h2 style="color: var(--deep-hospital-blue); margin-bottom: 24px; font-weight: 700; font-size: 1.75rem;">📱 My Device</h2>

<!-- Current Device Card -->
<div class="dashboard-card" style="text-align: center;">
    
    <?php if($assigned_device): ?>
    <div style="padding: 20px;">
        <div style="width: 100px; height: 100px; background: linear-gradient(135deg, rgba(0, 182, 204, 0.15) 0%, rgba(10, 133, 204, 0.15) 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px; box-shadow: var(--shadow-sm);">
            <i class="fa fa-tablet" style="font-size: 48px; color: var(--medical-cyan);"></i>
        </div>
        <p style="font-size: 28px; font-weight: 800; color: var(--deep-hospital-blue); margin-bottom: 12px;"><?php echo htmlspecialchars($assigned_device['dev_serial']); ?></p>
        <span class="status-badge status-available" style="margin: 12px 0;">Device Assigned</span>
        <p style="color: var(--system-gray); font-size: 14px; margin-top: 16px;">
            <i class="fa fa-calendar"></i> Assigned on: <?php echo date('M d, Y h:i A', strtotime($assigned_device['date_assigned'])); ?>
        </p>
        
        <form method="POST" style="margin-top: 24px;">
            <button type="submit" name="return_device" class="btn-primary" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);">
                <i class="fa fa-undo"></i> Return Device
            </button>
        </form>
    </div>
    <?php else: ?>
    <div style="padding: 20px;">
        <div style="width: 100px; height: 100px; background: var(--clinical-white); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px; border: 2px dashed var(--system-gray);">
            <i class="fa fa-tablet" style="font-size: 48px; color: var(--system-gray);"></i>
        </div>
        <p style="font-size: 20px; color: var(--deep-hospital-blue); font-weight: 600; margin-bottom: 8px;">No Device Assigned</p>
        <p style="color: var(--system-gray); font-size: 14px; margin-bottom: 24px;">Available devices: <strong style="color: var(--medical-cyan);"><?php echo $available_count; ?></strong></p>
        
        <?php if($available_count > 0): ?>
        <form method="POST">
            <button type="submit" name="request_device" class="btn-quick-action" style="width: auto; padding: 14px 32px;">
                <i class="fa fa-plus"></i> Request Device
            </button>
        </form>
        <?php else: ?>
        <div style="background: rgba(245, 158, 11, 0.1); padding: 16px; border-radius: var(--radius); color: #d97706; font-size: 14px;">
            <i class="fa fa-exclamation-triangle"></i> No devices available. Please try again later.
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Device Info Card -->
<div class="dashboard-card">
    <h3>ℹ️ Device Information</h3>
    <p style="color: var(--system-gray); font-size: 15px; line-height: 1.7; margin-bottom: 20px;">
        Your assigned device is used to record patient vital signs during incidents. 
        Make sure to keep the device charged and in good condition.
    </p>
    <div style="background: var(--clinical-white); padding: 20px; border-radius: var(--radius); border-left: 4px solid var(--medical-cyan);">
        <ul style="color: var(--deep-hospital-blue); font-size: 14px; list-style: none; line-height: 2;">
            <li style="display: flex; align-items: center; gap: 10px;">
                <i class="fa fa-check-circle" style="color: var(--health-green);"></i>
                Check battery level before each use
            </li>
            <li style="display: flex; align-items: center; gap: 10px;">
                <i class="fa fa-check-circle" style="color: var(--health-green);"></i>
                Return device when not in use
            </li>
            <li style="display: flex; align-items: center; gap: 10px;">
                <i class="fa fa-check-circle" style="color: var(--health-green);"></i>
                Report any issues immediately
            </li>
        </ul>
    </div>
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

<a href="incident_history.php" class="bottom-item">
<i class="fa fa-history"></i>
<span>History</span>
</a>

<a href="../../api/auth/logout.php"><i class="fa fa-sign-out"></i></a>
</nav>

</body>
</html>

