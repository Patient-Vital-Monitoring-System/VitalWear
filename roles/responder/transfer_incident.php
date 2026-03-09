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

/* Custom Select Dropdown Styling */
select {
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%2300B6CC' viewBox='0 0 16 16'%3E%3Cpath d='M8 11L3 6h10l-5 5z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 14px center;
    background-size: 16px;
    padding-right: 40px !important;
    cursor: pointer;
}

select:hover {
    border-color: var(--medical-cyan) !important;
}

select:focus {
    outline: none;
    border-color: var(--medical-cyan) !important;
    box-shadow: 0 0 0 3px rgba(0, 182, 204, 0.15) !important;
}

select option {
    padding: 12px;
    font-size: 15px;
    background: white;
    color: var(--deep-hospital-blue);
}

select option:hover,
select option:focus,
select option:checked {
    background: rgba(0, 182, 204, 0.1) !important;
    color: var(--deep-hospital-blue) !important;
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

<h2 style="color: var(--deep-hospital-blue); margin-bottom: 24px; font-weight: 700; font-size: 1.75rem; text-align: center;">🔄 Transfer Incident</h2>

<?php if($message): ?>
<div style="background: <?php echo $message_type === 'success' ? 'rgba(46, 219, 179, 0.15)' : 'rgba(239, 68, 68, 0.15)'; ?>; color: <?php echo $message_type === 'success' ? 'var(--health-green)' : '#dc2626'; ?>; padding: 16px 24px; border-radius: var(--radius); margin-bottom: 24px; text-align: center; font-weight: 600; border: 1px solid <?php echo $message_type === 'success' ? 'rgba(46, 219, 179, 0.3)' : 'rgba(239, 68, 68, 0.3)'; ?>; display: flex; align-items: center; justify-content: center; gap: 10px;">
    <i class="fa <?php echo $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
    <?php echo $message; ?>
</div>
<?php endif; ?>

<div class="dashboard-card" style="width: 100%;">
    <form method="POST">
        <div style="margin-bottom: 24px;">
            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--deep-hospital-blue); font-size: 14px;">
                <i class="fa fa-exclamation-circle" style="color: var(--medical-cyan); margin-right: 8px;"></i>Select Incident *
            </label>
            <select name="incident_id" required style="width: 100%; padding: 14px 40px 14px 14px; border: 2px solid rgba(169, 183, 198, 0.3); border-radius: var(--radius); font-size: 15px; background: white;">
                <option value="">-- Select Incident --</option>
                <?php while($inc = $incidents->fetch_assoc()): ?>
                <option value="<?php echo $inc['incident_id']; ?>">
                    #<?php echo $inc['incident_id']; ?> - <?php echo htmlspecialchars($inc['pat_name']); ?>
                </option>
                <?php endwhile; ?>
            </select>
        </div>
        
        <div style="margin-bottom: 24px;">
            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--deep-hospital-blue); font-size: 14px;">
                <i class="fa fa-user-md" style="color: var(--medical-cyan); margin-right: 8px;"></i>Transfer to Rescuer *
            </label>
            <select name="resc_id" required style="width: 100%; padding: 14px 40px 14px 14px; border: 2px solid rgba(169, 183, 198, 0.3); border-radius: var(--radius); font-size: 15px; background: white;">
                <option value="">-- Select Rescuer --</option>
                <?php while($rescuer = $rescuers->fetch_assoc()): ?>
                <option value="<?php echo $rescuer['resc_id']; ?>">
                    <?php echo htmlspecialchars($rescuer['resc_name']); ?>
                </option>
                <?php endwhile; ?>
            </select>
        </div>
        
        <button type="submit" style="width: 100%; padding: 16px 24px; background: linear-gradient(135deg, var(--medical-cyan) 0%, var(--trust-blue) 100%); color: white; border: none; border-radius: var(--radius-lg); font-weight: 700; font-size: 16px; cursor: pointer; box-shadow: var(--shadow); transition: all 0.3s ease; display: flex; align-items: center; justify-content: center; gap: 10px; text-transform: uppercase; letter-spacing: 0.5px;" onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='var(--shadow-md)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='var(--shadow)'">
            <i class="fa fa-exchange" style="font-size: 20px;"></i> Transfer Incident
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

