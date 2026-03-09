<?php
require_once '../../database/connection.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'rescuer') {
    header("Location: ../../login.html");
    exit;
}

$rescuer_id = $_SESSION['user_id'];
$conn = getDBConnection();

// Get rescuer info
$rescuer_query = "SELECT resc_name, resc_email FROM rescuer WHERE resc_id = ?";
$stmt = $conn->prepare($rescuer_query);
$stmt->bind_param("i", $rescuer_id);
$stmt->execute();
$rescuer = $stmt->get_result()->fetch_assoc();

// Get incident counts
$transferred_query = "SELECT COUNT(*) as count FROM incident WHERE resc_id = ? AND status = 'transferred'";
$ongoing_query = "SELECT COUNT(*) as count FROM incident WHERE resc_id = ? AND status = 'ongoing'";
$completed_query = "SELECT COUNT(*) as count FROM incident WHERE resc_id = ? AND status = 'completed'";

$stmt = $conn->prepare($transferred_query);
$stmt->bind_param("i", $rescuer_id);
$stmt->execute();
$transferred_count = $stmt->get_result()->fetch_assoc()['count'];

$stmt = $conn->prepare($ongoing_query);
$stmt->bind_param("i", $rescuer_id);
$stmt->execute();
$ongoing_count = $stmt->get_result()->fetch_assoc()['count'];

$stmt = $conn->prepare($completed_query);
$stmt->bind_param("i", $rescuer_id);
$stmt->execute();
$completed_count = $stmt->get_result()->fetch_assoc()['count'];

// Get recent transferred incidents
$recent_transferred = "SELECT i.incident_id, i.start_time, p.pat_name, r.resp_name 
                      FROM incident i 
                      JOIN patient p ON i.pat_id = p.pat_id 
                      JOIN responder r ON i.resp_id = r.resp_id 
                      WHERE i.resc_id = ? AND i.status = 'transferred' 
                      ORDER BY i.start_time DESC LIMIT 5";
$stmt = $conn->prepare($recent_transferred);
$stmt->bind_param("i", $rescuer_id);
$stmt->execute();
$transferred_incidents = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rescuer Dashboard - VitalWear</title>
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

<h2 style="color:#dd4c56;margin-bottom:20px;">📊 Rescuer Dashboard</h2>

<!-- Stats Cards -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:20px;margin-bottom:20px;">
    <div style="background:white;padding:20px;border-radius:15px;box-shadow:0 5px 15px rgba(0,0,0,0.1);text-align:center;">
        <p style="font-size:28px;font-weight:bold;color:#3b82f6;"><?php echo $transferred_count; ?></p>
        <p style="color:#777;font-size:14px;">📥 Transferred Cases</p>
    </div>
    <div style="background:white;padding:20px;border-radius:15px;box-shadow:0 5px 15px rgba(0,0,0,0.1);text-align:center;">
        <p style="font-size:28px;font-weight:bold;color:#22c55e;"><?php echo $ongoing_count; ?></p>
        <p style="color:#777;font-size:14px;">❤️ Ongoing Monitoring</p>
    </div>
    <div style="background:white;padding:20px;border-radius:15px;box-shadow:0 5px 15px rgba(0,0,0,0.1);text-align:center;">
        <p style="font-size:28px;font-weight:bold;color:#f59e0b;"><?php echo $completed_count; ?></p>
        <p style="color:#777;font-size:14px;">✅ Completed Cases</p>
    </div>
</div>

<!-- Quick Actions -->
<div style="background:white;padding:25px;border-radius:15px;box-shadow:0 5px 15px rgba(0,0,0,0.1);width:100%;margin-bottom:20px;">
    <h3 style="color:#dd4c56;margin-bottom:20px;">🚀 Quick Actions</h3>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:15px;">
        <a href="transferred_incidents.php" style="display:block;padding:15px;background:#3b82f6;color:white;text-decoration:none;border-radius:10px;text-align:center;font-weight:bold;">
            <i class="fa fa-exclamation-circle"></i> View Transferred
        </a>
        <a href="ongoing_monitoring.php" style="display:block;padding:15px;background:#22c55e;color:white;text-decoration:none;border-radius:10px;text-align:center;font-weight:bold;">
            <i class="fa fa-heart-pulse"></i> Ongoing Cases
        </a>
        <a href="completed_cases.php" style="display:block;padding:15px;background:#f59e0b;color:white;text-decoration:none;border-radius:10px;text-align:center;font-weight:bold;">
            <i class="fa fa-check-circle"></i> Completed
        </a>
    </div>
</div>

<!-- Recent Transferred Incidents -->
<div style="background:white;padding:25px;border-radius:15px;box-shadow:0 5px 15px rgba(0,0,0,0.1);width:100%;">
    <h3 style="color:#dd4c56;margin-bottom:20px;">📥 Recent Transferred Incidents</h3>
    <?php if ($transferred_incidents->num_rows > 0): ?>
        <div style="display:flex;flex-direction:column;gap:15px;">
            <?php while ($incident = $transferred_incidents->fetch_assoc()): ?>
                <div style="background:#f8fafc;padding:15px;border-radius:10px;border-left:4px solid #3b82f6;">
                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <div>
                            <p style="font-size:16px;font-weight:bold;color:#1f2937;">#<?php echo $incident['incident_id']; ?> - <?php echo htmlspecialchars($incident['pat_name']); ?></p>
                            <p style="color:#6b7280;font-size:14px;">From: <?php echo htmlspecialchars($incident['resp_name']); ?></p>
                            <p style="color:#9ca3af;font-size:12px;"><?php echo date('M j, Y H:i', strtotime($incident['start_time'])); ?></p>
                        </div>
                        <a href="view_incident.php?id=<?php echo $incident['incident_id']; ?>" style="padding:8px 16px;background:#3b82f6;color:white;text-decoration:none;border-radius:6px;font-size:14px;">View</a>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <p style="color:#777;text-align:center;padding:20px;">No transferred incidents at the moment.</p>
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

    <script>
    function logout() {
        if (confirm('Are you sure you want to logout?')) {
            // Clear sessionStorage
            sessionStorage.clear();
            
            // Call PHP logout
            fetch('/VitalWear-1/api/auth/logout.php', {
                method: 'POST'
            }).then(() => {
                // Redirect to login
                window.location.href = '/VitalWear-1/login.html';
            }).catch(error => {
                console.error('Logout error:', error);
                // Still redirect even if fetch fails
                window.location.href = '/VitalWear-1/login.html';
            });
        }
    }
    </script>
</body>
</html>