<?php 
include("../../database/connection.php");
session_start();

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
                
                header("Location: active_incidents.php?incident_id=" . $incident_id);
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

<main class="container" style="display: flex; flex-direction: column; align-items: center; overflow-y: auto; width: 100%; padding: 20px;">

<div style="width: 100%; max-width: 500px;">

<h2 style="color: var(--deep-hospital-blue); margin-bottom: 24px; font-weight: 700; font-size: 1.75rem; text-align: center;">➕ Create Incident</h2>

<?php if($message): ?>
<div style="background: <?php echo $message_type === 'error' ? 'rgba(239, 68, 68, 0.15)' : 'rgba(46, 219, 179, 0.15)'; ?>; color: <?php echo $message_type === 'error' ? '#dc2626' : 'var(--health-green)'; ?>; padding: 16px 24px; border-radius: var(--radius); margin-bottom: 24px; text-align: center; font-weight: 600; border: 1px solid <?php echo $message_type === 'error' ? 'rgba(239, 68, 68, 0.3)' : 'rgba(46, 219, 179, 0.3)'; ?>; display: flex; align-items: center; justify-content: center; gap: 10px;">
    <i class="fa <?php echo $message_type === 'error' ? 'fa-exclamation-circle' : 'fa-check-circle'; ?>"></i>
    <?php echo $message; ?>
</div>
<?php endif; ?>

<div class="dashboard-card" style="width: 100%;">
    <form method="POST">
        <div style="margin-bottom: 24px;">
            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--deep-hospital-blue); font-size: 14px;">
                <i class="fa fa-user" style="color: var(--medical-cyan); margin-right: 8px;"></i>Patient Name *
            </label>
            <input type="text" name="pat_name" required style="width: 100%; padding: 14px; border: 2px solid rgba(169, 183, 198, 0.3); border-radius: var(--radius); font-size: 15px; transition: all 0.2s ease;" onfocus="this.style.borderColor='var(--medical-cyan)'; this.style.boxShadow='0 0 0 3px rgba(0, 182, 204, 0.1)';" onblur="this.style.borderColor='rgba(169, 183, 198, 0.3)'; this.style.boxShadow='none';">
        </div>
        
        <div style="margin-bottom: 24px;">
            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--deep-hospital-blue); font-size: 14px;">
                <i class="fa fa-phone" style="color: var(--medical-cyan); margin-right: 8px;"></i>Contact Number
            </label>
            <input type="text" name="pat_contact" style="width: 100%; padding: 14px; border: 2px solid rgba(169, 183, 198, 0.3); border-radius: var(--radius); font-size: 15px; transition: all 0.2s ease;" onfocus="this.style.borderColor='var(--medical-cyan)'; this.style.boxShadow='0 0 0 3px rgba(0, 182, 204, 0.1)';" onblur="this.style.borderColor='rgba(169, 183, 198, 0.3)'; this.style.boxShadow='none';">
        </div>
        
        <button type="submit" style="width: 100%; padding: 16px 24px; background: linear-gradient(135deg, var(--medical-cyan) 0%, var(--trust-blue) 100%); color: white; border: none; border-radius: var(--radius-lg); font-weight: 700; font-size: 16px; cursor: pointer; box-shadow: var(--shadow); transition: all 0.3s ease; display: flex; align-items: center; justify-content: center; gap: 10px; text-transform: uppercase; letter-spacing: 0.5px;" onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='var(--shadow-md)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='var(--shadow)'">
            <i class="fa fa-plus-circle" style="font-size: 20px;"></i> Create Incident & Record Vitals
        </button>
    </form>
</div>

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

