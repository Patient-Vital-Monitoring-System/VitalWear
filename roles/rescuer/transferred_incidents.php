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
$rescuer_query = "SELECT resc_name FROM rescuer WHERE resc_id = ?";
$stmt = $conn->prepare($rescuer_query);
$stmt->bind_param("i", $rescuer_id);
$stmt->execute();
$rescuer = $stmt->get_result()->fetch_assoc();

// Get transferred incidents with patient and vital details
$transferred_query = "SELECT i.incident_id, i.start_time, p.pat_name, p.birthdate, p.contact_number,
                      r.resp_name, r.resp_contact,
                      v.bp_systolic, v.bp_diastolic, v.heart_rate, v.oxygen_level, v.recorded_at as vital_time
                      FROM incident i 
                      JOIN patient p ON i.pat_id = p.pat_id 
                      JOIN responder r ON i.resp_id = r.resp_id 
                      LEFT JOIN vitalstat v ON i.incident_id = v.incident_id AND v.recorded_by = 'responder'
                      WHERE i.resc_id = ? AND i.status = 'transferred' 
                      ORDER BY i.start_time DESC";
$stmt = $conn->prepare($transferred_query);
$stmt->bind_param("i", $rescuer_id);
$stmt->execute();
$transferred_incidents = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transferred Incidents - VitalWear</title>
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

<h2 style="color:#dd4c56;margin-bottom:20px;">📥 Transferred Incidents</h2>

<?php if ($transferred_incidents->num_rows > 0): ?>
    <?php while ($incident = $transferred_incidents->fetch_assoc()): ?>
        <div style="background:white;padding:25px;border-radius:15px;box-shadow:0 5px 15px rgba(0,0,0,0.1);width:100%;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                <div>
                    <h3 style="color:#dd4c56;font-size:20px;margin:0;">Incident #<?php echo $incident['incident_id']; ?></h3>
                    <p style="color:#777;margin:5px 0;">Patient: <?php echo htmlspecialchars($incident['pat_name']); ?></p>
                    <p style="color:#777;font-size:14px;">From: <?php echo htmlspecialchars($incident['resp_name']); ?></p>
                    <p style="color:#777;font-size:14px;">Transfer Time: <?php echo date('M j, Y H:i', strtotime($incident['start_time'])); ?></p>
                </div>
                <div style="text-align:right;">
                    <span style="display:inline-block;padding:8px 16px;background:#3b82f6;color:white;border-radius:20px;font-size:14px;">Transferred</span>
                </div>
            </div>
            
            <?php if ($incident['bp_systolic']): ?>
            <div style="background:#f8fafc;padding:15px;border-radius:10px;margin-bottom:15px;">
                <h4 style="color:#333;margin:0 0 10px 0;">Latest Vitals</h4>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:10px;text-align:center;">
                    <div>
                        <p style="font-size:18px;font-weight:bold;color:#e74c3c;"><?php echo $incident['heart_rate']; ?> bpm</p>
                        <p style="color:#777;font-size:12px;">Heart Rate</p>
                    </div>
                    <div>
                        <p style="font-size:18px;font-weight:bold;color:#22c55e;"><?php echo $incident['bp_systolic']; ?>/<?php echo $incident['bp_diastolic']; ?></p>
                        <p style="color:#777;font-size:12px;">Blood Pressure</p>
                    </div>
                    <div>
                        <p style="font-size:18px;font-weight:bold;color:#3b82f6;"><?php echo $incident['oxygen_level']; ?>%</p>
                        <p style="color:#777;font-size:12px;">Oxygen Level</p>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <div style="display:flex;gap:10px;">
                <a href="accept_incident.php?id=<?php echo $incident['incident_id']; ?>" style="padding:10px 20px;background:#22c55e;color:white;text-decoration:none;border-radius:8px;font-weight:bold;">Accept & Monitor</a>
                <a href="view_incident_details.php?id=<?php echo $incident['incident_id']; ?>" style="padding:10px 20px;background:#64748b;color:white;text-decoration:none;border-radius:8px;font-weight:bold;">View Details</a>
            </div>
        </div>
    <?php endwhile; ?>
<?php else: ?>
    <div style="background:white;padding:40px;border-radius:15px;box-shadow:0 5px 15px rgba(0,0,0,0.1);width:100%;text-align:center;">
        <p style="color:#777;font-size:18px;margin-bottom:20px;">📥 No transferred incidents</p>
        <p style="color:#999;">There are no transferred incidents at the moment.</p>
        <a href="dashboard.php" style="display:inline-block;padding:12px 24px;background:#dd4c56;color:white;text-decoration:none;border-radius:8px;font-weight:bold;margin-top:20px;">Return to Dashboard</a>
    </div>
<?php endif; ?>

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
                            'incident_id' => $row['incident_id'],
                            'start_time' => $row['start_time'],
                            'pat_name' => $row['pat_name'],
                            'birthdate' => $row['birthdate'],
                            'contact_number' => $row['contact_number'],
                            'resp_name' => $row['resp_name'],
                            'resp_contact' => $row['resp_contact'],
                            'vitals' => []
                        ];
                    }
                    
                    if ($row['bp_systolic'] !== null) {
                        $incidents_data[$incident_id]['vitals'][] = [
                            'bp_systolic' => $row['bp_systolic'],
                            'bp_diastolic' => $row['bp_diastolic'],
                            'heart_rate' => $row['heart_rate'],
                            'oxygen_level' => $row['oxygen_level'],
                            'vital_time' => $row['vital_time']
                        ];
                    }
                }
                
                foreach ($incidents_data as $incident): 
                ?>
                    <div class="incident-card">
                        <div class="incident-header">
                            <div class="incident-title">Incident #<?php echo $incident['incident_id']; ?></div>
                            <div class="incident-meta">
                                <span>📅 <?php echo date('M j, Y H:i', strtotime($incident['start_time'])); ?></span>
                                <span>👤 <?php echo htmlspecialchars($incident['pat_name']); ?></span>
                            </div>
                        </div>
                        
                        <div class="incident-body">
                            <div class="patient-info">
                                <div class="info-section">
                                    <div class="info-label">👤 Patient Information</div>
                                    <div class="info-value">
                                        <strong>Name:</strong> <?php echo htmlspecialchars($incident['pat_name']); ?><br>
                                        <strong>Age:</strong> <?php echo date('Y') - date('Y', strtotime($incident['birthdate'])); ?> years<br>
                                        <strong>Contact:</strong> <?php echo htmlspecialchars($incident['contact_number'] ?: 'N/A'); ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="responder-info">
                                <div class="info-section">
                                    <div class="info-label">🚑 Initial Responder</div>
                                    <div class="info-value">
                                        <strong>Name:</strong> <?php echo htmlspecialchars($incident['resp_name']); ?><br>
                                        <strong>Contact:</strong> <?php echo htmlspecialchars($incident['resp_contact'] ?: 'N/A'); ?>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if (!empty($incident['vitals'])): ?>
                                <div class="vitals-info">
                                    <div class="info-section">
                                        <div class="info-label">❤️ Initial Vitals from Responder</div>
                                        <div class="vitals-grid">
                                            <div class="vital-item">
                                                <div class="vital-value"><?php echo $incident['vitals'][0]['bp_systolic']; ?>/<?php echo $incident['vitals'][0]['bp_diastolic']; ?></div>
                                                <div class="vital-label">Blood Pressure</div>
                                            </div>
                                            <div class="vital-item">
                                                <div class="vital-value"><?php echo $incident['vitals'][0]['heart_rate']; ?></div>
                                                <div class="vital-label">Heart Rate</div>
                                            </div>
                                            <div class="vital-item">
                                                <div class="vital-value"><?php echo $incident['vitals'][0]['oxygen_level']; ?>%</div>
                                                <div class="vital-label">Oxygen Level</div>
                                            </div>
                                            <div class="vital-item">
                                                <div class="vital-value"><?php echo date('H:i', strtotime($incident['vitals'][0]['vital_time'])); ?></div>
                                                <div class="vital-label">Recorded At</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="action-buttons">
                            <a href="accept_incident.php?id=<?php echo $incident['incident_id']; ?>" class="btn btn-primary">Accept & Start Monitoring</a>
                            <a href="view_incident_details.php?id=<?php echo $incident['incident_id']; ?>" class="btn btn-secondary">View Full Details</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-incidents">
                <h3>📥 No Transferred Incidents</h3>
                <p>There are no transferred incidents at the moment. Check back later for new cases.</p>
                <a href="dashboard.php" class="btn btn-primary" style="margin-top: 20px;">Return to Dashboard</a>
            </div>
        <?php endif; ?>
    </div>
    
    <?php require_once 'logout_script.php'; ?>
</body>
</html>
