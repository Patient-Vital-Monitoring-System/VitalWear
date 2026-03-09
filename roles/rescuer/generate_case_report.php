<?php
require_once '../../database/connection.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'rescuer') {
    header("Location: ../../login.html");
    exit;
}

if (!isset($_GET['id'])) {
    header('Location: incident_records.php');
    exit();
}

$incident_id = $_GET['id'];
$rescuer_id = $_SESSION['user_id'];
$conn = getDBConnection();

// Get complete incident data
$incident_query = "SELECT i.incident_id, i.start_time, i.end_time, p.pat_name, p.birthdate, p.contact_number,
                  r.resp_name, r.resp_contact
                  FROM incident i 
                  JOIN patient p ON i.pat_id = p.pat_id 
                  JOIN responder r ON i.resp_id = r.resp_id 
                  WHERE i.incident_id = ? AND i.resc_id = ? AND i.status = 'completed'";
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
                  ORDER BY recorded_at ASC";
$stmt = $conn->prepare($vitals_query);
$stmt->bind_param("i", $incident_id);
$stmt->execute();
$vitals = $stmt->get_result();

// Calculate statistics
$vitals_array = [];
$bp_systolic_avg = $bp_diastolic_avg = $heart_rate_avg = $oxygen_avg = 0;
$min_hr = $max_hr = $min_o2 = $max_o2 = null;

while ($vital = $vitals->fetch_assoc()) {
    $vitals_array[] = $vital;
    $bp_systolic_avg += $vital['bp_systolic'];
    $bp_diastolic_avg += $vital['bp_diastolic'];
    $heart_rate_avg += $vital['heart_rate'];
    $oxygen_avg += $vital['oxygen_level'];
    
    if ($min_hr === null || $vital['heart_rate'] < $min_hr) $min_hr = $vital['heart_rate'];
    if ($max_hr === null || $vital['heart_rate'] > $max_hr) $max_hr = $vital['heart_rate'];
    if ($min_o2 === null || $vital['oxygen_level'] < $min_o2) $min_o2 = $vital['oxygen_level'];
    if ($max_o2 === null || $vital['oxygen_level'] > $max_o2) $max_o2 = $vital['oxygen_level'];
}

$count = count($vitals_array);
if ($count > 0) {
    $bp_systolic_avg = round($bp_systolic_avg / $count);
    $bp_diastolic_avg = round($bp_diastolic_avg / $count);
    $heart_rate_avg = round($heart_rate_avg / $count);
    $oxygen_avg = round($oxygen_avg / $count);
}

// Calculate duration
$start_time = new DateTime($incident['start_time']);
$end_time = new DateTime($incident['end_time']);
$duration = $start_time->diff($end_time);
$duration_str = $duration->format('%h hours %i minutes');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Case Report - VitalWear</title>
    <link rel="stylesheet" href="../../../assets/css/styles.css">
    <style>
        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: linear-gradient(135deg, #ed8936 0%, #dd6b20 100%);
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            position: relative;
            text-align: center;
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
        .report-content {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .report-header {
            text-align: center;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .report-title {
            font-size: 24px;
            font-weight: bold;
            color: #2d3748;
            margin-bottom: 10px;
        }
        .report-subtitle {
            color: #718096;
            font-size: 16px;
            margin-bottom: 20px;
        }
        .section {
            margin-bottom: 30px;
        }
        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: #4a5568;
            margin-bottom: 15px;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 5px;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        .info-item {
            margin-bottom: 10px;
        }
        .info-label {
            font-weight: 600;
            color: #718096;
            margin-bottom: 5px;
        }
        .info-value {
            color: #2d3748;
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
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .stat-item {
            background: #f7fafc;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #ed8936;
        }
        .stat-label {
            color: #718096;
            margin-top: 5px;
        }
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            margin: 10px;
        }
        .btn-primary {
            background: #667eea;
            color: white;
        }
        .btn-warning {
            background: #ed8936;
            color: white;
        }
        .btn-success {
            background: #48bb78;
            color: white;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        .action-buttons {
            text-align: center;
            margin-top: 30px;
        }
        @media print {
            .back-btn, .logout-btn, .action-buttons {
                display: none;
            }
            .container {
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="view_incident_details.php?id=<?php echo $incident_id; ?>" class="back-btn">← Back to Details</a>
            <a href="#" onclick="logout()" class="logout-btn">Logout</a>
            <div class="report-header">
                <div class="report-title">VitalWear Case Report</div>
                <div class="report-subtitle">Incident #<?php echo $incident['incident_id']; ?> - Complete Medical Report</div>
            </div>
        </div>

        <div class="report-content">
            <div class="section">
                <div class="section-title">📋 Patient Information</div>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Name:</div>
                        <div class="info-value"><?php echo htmlspecialchars($incident['pat_name']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Age:</div>
                        <div class="info-value"><?php echo date('Y') - date('Y', strtotime($incident['birthdate'])); ?> years</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Contact:</div>
                        <div class="info-value"><?php echo htmlspecialchars($incident['contact_number'] ?: 'N/A'); ?></div>
                    </div>
                </div>
            </div>

            <div class="section">
                <div class="section-title">🚑 Incident Details</div>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Incident ID:</div>
                        <div class="info-value">#<?php echo $incident['incident_id']; ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Initial Responder:</div>
                        <div class="info-value"><?php echo htmlspecialchars($incident['resp_name']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Responder Contact:</div>
                        <div class="info-value"><?php echo htmlspecialchars($incident['resp_contact'] ?: 'N/A'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Start Time:</div>
                        <div class="info-value"><?php echo date('M j, Y H:i:s', strtotime($incident['start_time'])); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">End Time:</div>
                        <div class="info-value"><?php echo date('M j, Y H:i:s', strtotime($incident['end_time'])); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Duration:</div>
                        <div class="info-value"><?php echo $duration_str; ?></div>
                    </div>
                </div>
            </div>

            <div class="section">
                <div class="section-title">📊 Vital Statistics Summary</div>
                <div class="stats-grid">
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $count; ?></div>
                        <div class="stat-label">Total Readings</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $bp_systolic_avg; ?>/<?php echo $bp_diastolic_avg; ?></div>
                        <div class="stat-label">Avg Blood Pressure</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $heart_rate_avg; ?> bpm</div>
                        <div class="stat-label">Avg Heart Rate</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $oxygen_avg; ?>%</div>
                        <div class="stat-label">Avg Oxygen Level</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $min_hr; ?> - <?php echo $max_hr; ?></div>
                        <div class="stat-label">Heart Rate Range</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $min_o2; ?>% - <?php echo $max_o2; ?>%</div>
                        <div class="stat-label">Oxygen Range</div>
                    </div>
                </div>
            </div>

            <?php if ($count > 0): ?>
                <div class="section">
                    <div class="section-title">📈 Detailed Vital Readings</div>
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
                            <?php foreach ($vitals_array as $vital): ?>
                                <tr>
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
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="action-buttons">
            <a href="javascript:window.print()" class="btn btn-success">🖨 Print Report</a>
            <a href="case_vitals_history.php?id=<?php echo $incident_id; ?>" class="btn btn-primary">📊 View Charts</a>
            <a href="view_incident_details.php?id=<?php echo $incident_id; ?>" class="btn btn-warning">← Back to Details</a>
        </div>
    </div>
    
    <?php require_once 'logout_script.php'; ?>
</body>
</html>
