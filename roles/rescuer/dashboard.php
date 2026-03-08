<?php
require_once 'session_check.php';
require_once '../../database/connection.php';

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
    <link rel="stylesheet" href="../../../assets/css/styles.css">
    <style>
        .dashboard-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-number {
            font-size: 2.5em;
            font-weight: bold;
            color: #667eea;
        }
        .stat-label {
            color: #666;
            margin-top: 10px;
        }
        .nav-section {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .nav-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        .nav-btn {
            padding: 15px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.3s ease;
            text-decoration: none;
            display: block;
            text-align: center;
        }
        .nav-btn.primary {
            background: #667eea;
            color: white;
        }
        .nav-btn.secondary {
            background: #48bb78;
            color: white;
        }
        .nav-btn.danger {
            background: #f56565;
            color: white;
        }
        .nav-btn.warning {
            background: #ed8936;
            color: white;
        }
        .nav-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        .recent-incidents {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .incident-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .incident-table th,
        .incident-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .incident-table th {
            background: #f7fafc;
            font-weight: 600;
        }
        .action-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        .view-btn {
            background: #4299e1;
            color: white;
        }
        .logout-btn {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 10px 20px;
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: 5px;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        .logout-btn:hover {
            background: rgba(255,255,255,0.3);
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="header">
            <h1>🏠 Rescuer Dashboard</h1>
            <p>Welcome back, <?php echo htmlspecialchars($rescuer['resc_name']); ?>!</p>
            <a href="/VitalWear-1/api/auth/logout.php" class="btn btn-secondary">Logout</a>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $transferred_count; ?></div>
                <div class="stat-label">📥 Transferred Incidents</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $ongoing_count; ?></div>
                <div class="stat-label">❤️ Ongoing Monitoring</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $completed_count; ?></div>
                <div class="stat-label">✅ Completed Cases</div>
            </div>
        </div>

        <div class="nav-section">
            <h2>Quick Actions</h2>
            <div class="nav-grid">
                <a href="transferred_incidents.php" class="nav-btn primary">📥 View Transferred Cases</a>
                <a href="ongoing_monitoring.php" class="nav-btn secondary">❤️ Continue Monitoring</a>
                <a href="completed_cases.php" class="nav-btn warning">✅ Completed Cases</a>
                <a href="incident_records.php" class="nav-btn primary">📁 Incident Records</a>
                <a href="return_device.php" class="nav-btn danger">🔁 Return Device</a>
            </div>
        </div>

        <div class="recent-incidents">
            <h2>📥 Recent Transferred Incidents</h2>
            <?php if ($transferred_incidents->num_rows > 0): ?>
                <table class="incident-table">
                    <thead>
                        <tr>
                            <th>Incident ID</th>
                            <th>Patient Name</th>
                            <th>Responder</th>
                            <th>Transfer Time</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($incident = $transferred_incidents->fetch_assoc()): ?>
                            <tr>
                                <td>#<?php echo $incident['incident_id']; ?></td>
                                <td><?php echo htmlspecialchars($incident['pat_name']); ?></td>
                                <td><?php echo htmlspecialchars($incident['resp_name']); ?></td>
                                <td><?php echo date('M j, Y H:i', strtotime($incident['start_time'])); ?></td>
                                <td>
                                    <a href="view_incident.php?id=<?php echo $incident['incident_id']; ?>" class="action-btn view-btn">View</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No transferred incidents at the moment.</p>
            <?php endif; ?>
        </div>
    </div>
    
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