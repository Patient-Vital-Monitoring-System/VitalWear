<?php
require_once 'session_check.php';
require_once '../../database/connection.php';

if (!isset($_GET['id'])) {
    header('Location: incident_records.php');
    exit();
}

$incident_id = $_GET['id'];
$rescuer_id = $_SESSION['user_id'];
$conn = getDBConnection();

// Get incident details
$incident_query = "SELECT i.incident_id, i.start_time, i.end_time, i.status, p.pat_name, p.birthdate, p.contact_number,
                  r.resp_name, r.resp_contact
                  FROM incident i 
                  JOIN patient p ON i.pat_id = p.pat_id 
                  JOIN responder r ON i.resp_id = r.resp_id 
                  WHERE i.incident_id = ? AND i.resc_id = ?";
$stmt = $conn->prepare($incident_query);
$stmt->bind_param("ii", $incident_id, $rescuer_id);
$stmt->execute();
$incident = $stmt->get_result()->fetch_assoc();

if (!$incident) {
    header('Location: incident_records.php');
    exit();
}

// Get all vital statistics
$vitals_query = "SELECT bp_systolic, bp_diastolic, heart_rate, oxygen_level, recorded_at, recorded_by 
                  FROM vitalstat 
                  WHERE incident_id = ? 
                  ORDER BY recorded_at DESC";
$stmt = $conn->prepare($vitals_query);
$stmt->bind_param("i", $incident_id);
$stmt->execute();
$vitals = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Incident Details - VitalWear</title>
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
        .back-btn:hover {
            background: rgba(255,255,255,0.3);
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
        .incident-info {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        .info-item {
            padding: 15px;
            background: #f7fafc;
            border-radius: 8px;
        }
        .info-label {
            font-weight: 600;
            color: #4a5568;
            margin-bottom: 5px;
        }
        .info-value {
            color: #2d3748;
        }
        .vitals-section {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .vitals-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .vitals-table th,
        .vitals-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        .vitals-table th {
            background: #f7fafc;
            font-weight: 600;
            color: #4a5568;
        }
        .vitals-table tr:hover {
            background: #f7fafc;
        }
        .recorded-by-responder {
            background: #e6fffa;
        }
        .recorded-by-rescuer {
            background: #f0fff4;
        }
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .status-transferred {
            background: #bee3f8;
            color: #2c5282;
        }
        .status-ongoing {
            background: #c6f6d5;
            color: #22543d;
        }
        .status-completed {
            background: #fed7d7;
            color: #742a2a;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="incident_records.php" class="back-btn">← Back to Records</a>
            <a href="#" onclick="logout()" class="logout-btn">Logout</a>
            <h1>📋 Incident #<?php echo $incident['incident_id']; ?> Details</h1>
        </div>

        <div class="incident-info">
            <h2>Patient Information</h2>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Name</div>
                    <div class="info-value"><?php echo htmlspecialchars($incident['pat_name']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Age</div>
                    <div class="info-value"><?php echo date('Y') - date('Y', strtotime($incident['birthdate'])); ?> years</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Contact</div>
                    <div class="info-value"><?php echo htmlspecialchars($incident['contact_number'] ?: 'N/A'); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Initial Responder</div>
                    <div class="info-value"><?php echo htmlspecialchars($incident['resp_name']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Responder Contact</div>
                    <div class="info-value"><?php echo htmlspecialchars($incident['resp_contact'] ?: 'N/A'); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Incident Status</div>
                    <div class="info-value">
                        <span class="status-badge status-<?php echo $incident['status']; ?>">
                            <?php echo $incident['status']; ?>
                        </span>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">Start Time</div>
                    <div class="info-value"><?php echo date('M j, Y H:i:s', strtotime($incident['start_time'])); ?></div>
                </div>
                <?php if ($incident['end_time']): ?>
                    <div class="info-item">
                        <div class="info-label">End Time</div>
                        <div class="info-value"><?php echo date('M j, Y H:i:s', strtotime($incident['end_time'])); ?></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="vitals-section">
            <h2>📈 Vital Signs History</h2>
            <?php if ($vitals->num_rows > 0): ?>
                <table class="vitals-table">
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Blood Pressure</th>
                            <th>Heart Rate</th>
                            <th>Oxygen Level</th>
                            <th>Recorded By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($vital = $vitals->fetch_assoc()): ?>
                            <tr class="<?php echo $vital['recorded_by'] === 'responder' ? 'recorded-by-responder' : 'recorded-by-rescuer'; ?>">
                                <td><?php echo date('M j, Y H:i:s', strtotime($vital['recorded_at'])); ?></td>
                                <td><?php echo $vital['bp_systolic']; ?>/<?php echo $vital['bp_diastolic']; ?></td>
                                <td><?php echo $vital['heart_rate']; ?> bpm</td>
                                <td><?php echo $vital['oxygen_level']; ?>%</td>
                                <td>
                                    <?php if ($vital['recorded_by'] === 'responder'): ?>
                                        🚑 Responder
                                    <?php else: ?>
                                        ❤️ Rescuer
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No vital signs recorded for this incident.</p>
            <?php endif; ?>
            
            <div style="margin-top: 20px;">
                <a href="case_vitals_history.php?id=<?php echo $incident_id; ?>" class="btn btn-primary">📊 Detailed Vitals Chart</a>
                <?php if ($incident['status'] === 'completed'): ?>
                    <a href="generate_case_report.php?id=<?php echo $incident_id; ?>" class="btn btn-warning" style="margin-left: 10px;">📄 Generate Report</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php require_once 'logout_script.php'; ?>
</body>
</html>
