<?php
require_once 'session_check.php';
require_once '../../database/connection.php';

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
    <link rel="stylesheet" href="../../../assets/css/styles.css">
    <style>
        .container {
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
            position: relative;
        }
        .back-btn {
            position: absolute;
            top: 20px;
            left: 20px;
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 10px 20px;
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: 5px;
            text-decoration: none;
            transition: all 0.3s ease;
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
        .incidents-grid {
            display: grid;
            gap: 20px;
        }
        .incident-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: transform 0.3s ease;
        }
        .incident-card:hover {
            transform: translateY(-3px);
        }
        .incident-header {
            background: #f7fafc;
            padding: 20px;
            border-bottom: 1px solid #e2e8f0;
        }
        .incident-title {
            font-size: 1.2em;
            font-weight: bold;
            color: #2d3748;
            margin-bottom: 10px;
        }
        .incident-meta {
            display: flex;
            gap: 20px;
            color: #718096;
            font-size: 0.9em;
        }
        .incident-body {
            padding: 20px;
        }
        .patient-info, .responder-info, .vitals-info {
            margin-bottom: 20px;
        }
        .info-section {
            margin-bottom: 15px;
        }
        .info-label {
            font-weight: 600;
            color: #4a5568;
            margin-bottom: 5px;
        }
        .info-value {
            color: #2d3748;
        }
        .vitals-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 15px;
            margin-top: 10px;
        }
        .vital-item {
            text-align: center;
            padding: 15px;
            background: #f7fafc;
            border-radius: 8px;
        }
        .vital-value {
            font-size: 1.5em;
            font-weight: bold;
            color: #667eea;
        }
        .vital-label {
            font-size: 0.8em;
            color: #718096;
            margin-top: 5px;
        }
        .action-buttons {
            padding: 20px;
            background: #f7fafc;
            border-top: 1px solid #e2e8f0;
            display: flex;
            gap: 10px;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary {
            background: #667eea;
            color: white;
        }
        .btn-secondary {
            background: #48bb78;
            color: white;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        .no-incidents {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .no-incidents h3 {
            color: #718096;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>
            <a href="#" onclick="logout()" class="logout-btn">Logout</a>
            <h1>📥 Transferred Incidents</h1>
            <p>View transferred cases and patient details</p>
        </div>

        <?php if ($transferred_incidents->num_rows > 0): ?>
            <div class="incidents-grid">
                <?php 
                $current_incident = null;
                $incidents_data = [];
                
                // Group data by incident
                while ($row = $transferred_incidents->fetch_assoc()) {
                    $incident_id = $row['incident_id'];
                    if (!isset($incidents_data[$incident_id])) {
                        $incidents_data[$incident_id] = [
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
