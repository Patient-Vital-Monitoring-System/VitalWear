<?php
require_once '../../database/connection.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'rescuer') {
    header("Location: ../../login.html");
    exit;
}

$rescuer_id = $_SESSION['user_id'];
$conn = getDBConnection();

// Get assigned device for this rescuer
$assigned_device = null;
if ($conn && !$conn->connect_error) {
    $device_query = "SELECT d.dev_serial, d.dev_status, dl.date_assigned
                    FROM device_log dl
                    JOIN device d ON dl.dev_id = d.dev_id
                    WHERE dl.resc_id = ? AND dl.date_returned IS NULL
                    ORDER BY dl.date_assigned DESC
                    LIMIT 1";
    $stmt = $conn->prepare($device_query);
    if ($stmt) {
        $stmt->bind_param("i", $rescuer_id);
        $stmt->execute();
        $assigned_device = $stmt->get_result()->fetch_assoc();
    }
}

// Handle device return
$message = "";
$message_type = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['return_device'])) {
    if ($assigned_device && $conn && !$conn->connect_error) {
        // Update device log
        $update_query = "UPDATE device_log SET date_returned = NOW() WHERE resc_id = ? AND date_returned IS NULL";
        $stmt = $conn->prepare($update_query);
        if ($stmt) {
            $stmt->bind_param("i", $rescuer_id);
            
            if ($stmt->execute()) {
                // Update device status to available
                $device_update = "UPDATE device SET dev_status = 'available' WHERE dev_serial = ?";
                $stmt = $conn->prepare($device_update);
                if ($stmt) {
                    $stmt->bind_param("s", $assigned_device['dev_serial']);
                    $stmt->execute();
                }
                
                $message = "Device returned successfully!";
                $message_type = "success";
                $assigned_device = null; // Clear the assigned device
            } else {
                $message = "Error returning device. Please try again.";
                $message_type = "error";
            }
        } else {
            $message = "Error preparing device return. Please try again.";
            $message_type = "error";
        }
    } else {
        $message = "No device assigned or database connection error.";
        $message_type = "error";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Return Device - VitalWear</title>
    <link rel="stylesheet" href="../../assets/css/styles.css">
    <script src="https://kit.fontawesome.com/96e37b53f1.js"></script>
</head>
<body>

<header class="topbar">
Rescuer: <?php echo isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Emergency Response'; ?>
</header>

<nav id="sidebar">
<a href="dashboard.php"><i class="fa fa-gauge"></i> Dashboard</a>
<a href="transferred_incidents.php"><i class="fa fa-exclamation-circle"></i> Transferred Incidents</a>
<a href="ongoing_monitoring.php"><i class="fa fa-heart-pulse"></i> Ongoing Monitoring</a>
<a href="completed_cases.php"><i class="fa fa-check-circle"></i> Completed Cases</a>
<a href="incident_records.php"><i class="fa fa-folder"></i> Incident Records</a>
<a href="return_device.php"><i class="fa fa-undo"></i> Return Device</a>
<a href="../../api/auth/logout.php"><i class="fa fa-sign-out"></i> Logout</a>
</nav>

<main class="container" style="display:block;overflow-y:auto;">

<h2 style="color:#dd4c56;margin-bottom:20px;">🔁 Return Device</h2>

<?php if ($message): ?>
    <div style="background:<?php echo $message_type === 'success' ? '#dcfce7' : '#fee2e2'; ?>;color:<?php echo $message_type === 'success' ? '#166534' : '#991b1b'; ?>;padding:15px;border-radius:10px;margin-bottom:20px;text-align:center;font-weight:600;">
        <?php echo $message; ?>
    </div>
<?php endif; ?>

<div style="background:white;padding:30px;border-radius:15px;box-shadow:0 5px 15px rgba(0,0,0,0.1);width:100%;">
    <?php if ($assigned_device): ?>
        <div style="text-align:center;">
            <div style="width:80px;height:80px;background:#fef2f2;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;">
                <i class="fa fa-tablet" style="font-size:36px;color:#dd4c56;"></i>
            </div>
            <h3 style="color:#333;margin-bottom:10px;">Assigned Device</h3>
            <p style="font-size:20px;font-weight:bold;color:#dd4c56;margin:10px 0;"><?php echo htmlspecialchars($assigned_device['dev_serial']); ?></p>
            <p style="color:#777;">Assigned on: <?php echo date('M j, Y H:i', strtotime($assigned_device['date_assigned'])); ?></p>
            <p style="color:#22c55e;font-weight:600;margin:10px 0;">Status: <?php echo ucfirst($assigned_device['dev_status']); ?></p>
            
            <form method="POST" style="margin-top:30px;">
                <button type="submit" name="return_device" style="padding:12px 30px;background:#ef4444;color:white;border:none;border-radius:8px;font-weight:bold;cursor:pointer;font-size:16px;">
                    <i class="fa fa-undo"></i> Return Device
                </button>
            </form>
            
            <p style="color:#666;font-size:14px;margin-top:20px;">Returning this device will make it available for other rescuers.</p>
        </div>
    <?php else: ?>
        <div style="text-align:center;">
            <div style="width:80px;height:80px;background:#f0fdf4;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;">
                <i class="fa fa-check-circle" style="font-size:36px;color:#22c55e;"></i>
            </div>
            <h3 style="color:#333;margin-bottom:10px;">No Device Assigned</h3>
            <p style="color:#777;">You currently don't have any device assigned to you.</p>
            <p style="color:#999;font-size:14px;margin-top:10px;">Devices are automatically assigned when you accept transferred incidents.</p>
            
            <div style="margin-top:30px;">
                <a href="dashboard.php" style="display:inline-block;padding:12px 24px;background:#dd4c56;color:white;text-decoration:none;border-radius:8px;font-weight:bold;">
                    <i class="fa fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>

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

<a href="completed_cases.php" class="bottom-item">
<i class="fa fa-check-circle"></i>
<span>Complete</span>
</a>

<a href="../../api/auth/logout.php"><i class="fa fa-sign-out"></i></a>
</nav>

</body>
</html>
