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

<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Return Device - VitalWear</title>

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

/* Modern Soft Edge Navigation */
.bottom-nav {
    background: var(--surface);
    border-top: 1px solid rgba(169, 183, 198, 0.3);
    box-shadow: 0 -4px 20px rgba(10, 42, 85, 0.08);
    padding: 12px 24px;
    display: flex;
    justify-content: space-around;
    align-items: center;
}

.bottom-nav .bottom-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    padding: 10px 20px;
    border-radius: var(--radius);
    color: var(--system-gray);
    text-decoration: none;
    transition: all 0.3s ease;
    font-weight: 500;
    font-size: 12px;
}

.bottom-nav .bottom-item i {
    font-size: 20px;
    transition: all 0.3s ease;
}

.bottom-nav .bottom-item:hover {
    color: var(--medical-cyan);
    background: rgba(0, 182, 204, 0.1);
    transform: translateY(-2px);
}

.bottom-nav .bottom-item.active {
    color: var(--medical-cyan);
    background: rgba(0, 182, 204, 0.15);
}

.bottom-nav .bottom-item.active i {
    transform: scale(1.1);
}

/* Welcome Banner */
.welcome-banner {
    background: linear-gradient(135deg, var(--deep-hospital-blue) 0%, var(--trust-blue) 100%);
    padding: 32px;
    border-radius: var(--radius-lg);
    margin-bottom: 30px;
    color: white;
    box-shadow: var(--shadow-md);
    text-align: center;
}

/* Alert Messages */
.alert {
    padding: 16px 24px;
    border-radius: var(--radius-lg);
    margin-bottom: 24px;
    text-align: center;
    font-weight: 600;
    box-shadow: var(--shadow);
    border: 1px solid rgba(169, 183, 198, 0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
}

.alert-success {
    background: linear-gradient(135deg, rgba(46, 219, 179, 0.2) 0%, rgba(32, 201, 151, 0.2) 100%);
    color: var(--health-green);
    border-color: rgba(46, 219, 179, 0.3);
}

.alert-error {
    background: linear-gradient(135deg, rgba(245, 158, 11, 0.2) 0%, rgba(217, 119, 6, 0.2) 100%);
    color: #f59e0b;
    border-color: rgba(245, 158, 11, 0.3);
}

/* Device Card */
.device-card {
    background: var(--surface);
    padding: 40px;
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow);
    border: 1px solid rgba(169, 183, 198, 0.2);
    text-align: center;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.device-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--medical-cyan) 0%, var(--trust-blue) 100%);
}

.device-icon {
    width: 100px;
    height: 100px;
    background: linear-gradient(135deg, rgba(0, 182, 204, 0.1) 0%, rgba(10, 133, 204, 0.1) 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 24px;
    border: 2px solid rgba(0, 182, 204, 0.2);
}

.device-icon i {
    font-size: 48px;
    color: var(--medical-cyan);
}

.device-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--deep-hospital-blue);
    margin-bottom: 16px;
}

.device-serial {
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--medical-cyan);
    margin: 16px 0;
    padding: 12px 20px;
    background: var(--clinical-white);
    border-radius: var(--radius);
    border: 1px solid rgba(0, 182, 204, 0.2);
    display: inline-block;
}

.device-info {
    color: var(--system-gray);
    margin: 8px 0;
    font-size: 0.95rem;
}

.device-status {
    display: inline-block;
    padding: 8px 16px;
    background: linear-gradient(135deg, var(--health-green) 0%, #20c997 100%);
    color: white;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin: 16px 0;
}

/* Buttons */
.btn {
    padding: 14px 32px;
    border: none;
    border-radius: var(--radius);
    cursor: pointer;
    font-size: 16px;
    font-weight: 600;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    position: relative;
    overflow: hidden;
    text-transform: none;
    letter-spacing: 0.3px;
}

.btn::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
    transition: left 0.5s;
}

.btn:hover::before {
    left: 100%;
}

.btn i {
    font-size: 18px;
    transition: transform 0.3s ease;
}

.btn:hover i {
    transform: scale(1.1);
}

.btn-danger {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: white;
    box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);
}

.btn-danger:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(239, 68, 68, 0.4);
    background: linear-gradient(135deg, #dc2626 0%, #ef4444 100%);
}

.btn-primary {
    background: linear-gradient(135deg, var(--medical-cyan) 0%, var(--trust-blue) 100%);
    color: white;
    box-shadow: 0 4px 15px rgba(0, 182, 204, 0.3);
}

.btn-primary:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0, 182, 204, 0.4);
    background: linear-gradient(135deg, var(--trust-blue) 0%, var(--medical-cyan) 100%);
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    background: var(--surface);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow);
    border: 1px solid rgba(169, 183, 198, 0.2);
}

.empty-icon {
    width: 100px;
    height: 100px;
    background: linear-gradient(135deg, rgba(46, 219, 179, 0.1) 0%, rgba(32, 201, 151, 0.1) 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 24px;
    border: 2px solid rgba(46, 219, 179, 0.2);
}

.empty-icon i {
    font-size: 48px;
    color: var(--health-green);
}

.empty-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--deep-hospital-blue);
    margin-bottom: 12px;
}

.empty-description {
    color: var(--system-gray);
    margin-bottom: 8px;
    font-size: 0.95rem;
}

.empty-note {
    color: var(--system-gray);
    font-size: 0.85rem;
    opacity: 0.8;
    margin-top: 16px;
}

/* Form Styles */
.return-form {
    margin-top: 32px;
}

.return-form .btn {
    min-width: 200px;
}

.device-note {
    color: var(--system-gray);
    font-size: 0.9rem;
    margin-top: 24px;
    padding: 16px;
    background: var(--clinical-white);
    border-radius: var(--radius);
    border: 1px solid rgba(169, 183, 198, 0.2);
}

/* Responsive Design */
@media (max-width: 768px) {
    .device-card, .empty-state {
        padding: 30px 20px;
    }
    
    .device-icon, .empty-icon {
        width: 80px;
        height: 80px;
    }
    
    .device-icon i, .empty-icon i {
        font-size: 36px;
    }
    
    .device-serial {
        font-size: 1.4rem;
        padding: 10px 16px;
    }
    
    .btn {
        width: 100%;
        justify-content: center;
        min-width: auto;
    }
}
</style>

</head>

<body>

<header class="topbar">
    <div style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
        <div style="display: flex; align-items: center; gap: 12px;">
            <i class="fa fa-undo" style="font-size: 24px; color: var(--medical-cyan);"></i>
            <span style="font-size: 18px; font-weight: 700;">VitalWear</span>
        </div>
        <div style="display: flex; align-items: center; gap: 8px; color: var(--deep-hospital-blue); font-weight: 500;">
            <i class="fa fa-user-circle" style="font-size: 20px; color: var(--medical-cyan);"></i>
            <span><?php echo isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Rescuer'; ?></span>
        </div>
    </div>
</header>

<nav id="sidebar">
<div class="sidebar-logo">
    <img src="../../assets/logo.png" alt="VitalWear Logo">
</div>
<a href="dashboard.php"><i class="fa fa-gauge"></i> Dashboard</a>
<a href="transferred_incidents.php"><i class="fa fa-exclamation-circle"></i> Transferred Incidents</a>
<a href="ongoing_monitoring.php"><i class="fa fa-heart-pulse"></i> Ongoing Monitoring</a>
<a href="completed_cases.php"><i class="fa fa-check-circle"></i> Completed Cases</a>
<a href="incident_records.php"><i class="fa fa-folder"></i> Incident Records</a>
<a href="return_device.php"><i class="fa fa-undo"></i> Return Device</a>
<a href="../../api/auth/logout.php" class="btn btn-secondary">Logout</a>
</nav>

<main class="container" style="display:block;overflow-y:auto;">

<!-- Welcome Banner -->
<div class="welcome-banner">
    <div style="display: flex; align-items: center; justify-content: center; gap: 16px;">
        <div style="width: 60px; height: 60px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 28px;">
            🔁
        </div>
        <div>
            <h1 style="color: white; margin: 0; font-size: 1.75rem; font-weight: 700;">Return Device</h1>
            <p style="color: rgba(255,255,255,0.9); margin: 4px 0 0 0; font-size: 1rem;">Manage your assigned VitalWear device</p>
        </div>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type === 'success' ? 'success' : 'error'; ?>">
        <i class="fa fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
        <?php echo $message; ?>
    </div>
<?php endif; ?>

<div class="device-card">
    <?php if ($assigned_device): ?>
        <div class="device-icon">
            <i class="fa fa-tablet"></i>
        </div>
        
        <h3 class="device-title">Assigned Device</h3>
        
        <div class="device-serial">
            <?php echo htmlspecialchars($assigned_device['dev_serial']); ?>
        </div>
        
        <div class="device-info">
            <i class="fa fa-calendar"></i> Assigned on: <?php echo date('M j, Y H:i', strtotime($assigned_device['date_assigned'])); ?>
        </div>
        
        <div class="device-status">
            <i class="fa fa-check-circle"></i> <?php echo ucfirst($assigned_device['dev_status']); ?>
        </div>
        
        <form method="POST" class="return-form">
            <button type="submit" name="return_device" class="btn btn-danger">
                <i class="fa fa-undo"></i> Return Device
            </button>
        </form>
        
        <div class="device-note">
            <i class="fa fa-info-circle"></i> Returning this device will make it available for other rescuers to use.
        </div>
    <?php else: ?>
        <div class="empty-icon">
            <i class="fa fa-check-circle"></i>
        </div>
        
        <h3 class="empty-title">No Device Assigned</h3>
        
        <p class="empty-description">
            You currently don't have any device assigned to you.
        </p>
        
        <p class="empty-note">
            Devices are automatically assigned when you accept transferred incidents.
        </p>
        
        <div style="margin-top: 32px;">
            <a href="dashboard.php" class="btn btn-primary">
                <i class="fa fa-arrow-left"></i> Back to Dashboard
            </a>
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

<a href="return_device.php" class="bottom-item active">
    <i class="fa fa-undo"></i>
    <span>Device</span>
</a>

<a href="../../api/auth/logout.php" class="bottom-item">
    <i class="fa fa-sign-out"></i>
    <span>Logout</span>
</a>
</nav>

</body>
</html>
