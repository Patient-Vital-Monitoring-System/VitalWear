<?php
session_start();
require_once '../../database/connection.php';

// Check if rescuer user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'rescuer') {
    header('Location: ../../login.html');
    exit();
}

$conn = getDBConnection();

// Get rescuer-specific statistics
$transferred_incidents = 0;
$accepted_incidents = 0;
$completed_incidents = 0;
$active_cases = 0;
$total_vitals_recorded = 0;
$avg_response_time = 0;

// Get incident statistics for this rescuer
$rescuer_id = $_SESSION['user_id'];

// Transferred incidents (available to accept)
$result = $conn->query("
    SELECT COUNT(*) as count 
    FROM incident i 
    WHERE i.status = 'transferred' 
    AND i.resc_id IS NULL
");
if ($result) $transferred_incidents = $result->fetch_assoc()['count'];

// Accepted incidents (assigned to this rescuer)
$result = $conn->query("
    SELECT COUNT(*) as count 
    FROM incident i 
    WHERE i.resc_id = $rescuer_id
");
if ($result) $accepted_incidents = $result->fetch_assoc()['count'];

// Active cases
$result = $conn->query("
    SELECT COUNT(*) as count 
    FROM incident i 
    WHERE i.resc_id = $rescuer_id AND i.status = 'transferred'
");
if ($result) $active_cases = $result->fetch_assoc()['count'];

// Completed incidents
$result = $conn->query("
    SELECT COUNT(*) as count 
    FROM incident i 
    WHERE i.resc_id = $rescuer_id AND i.status = 'completed'
");
if ($result) $completed_incidents = $result->fetch_assoc()['count'];

// Total vitals recorded by this rescuer
$result = $conn->query("
    SELECT COUNT(*) as count 
    FROM vitalstat v 
    JOIN incident i ON v.incident_id = i.incident_id 
    WHERE i.resc_id = $rescuer_id AND v.recorded_by = 'rescuer'
");
if ($result) $total_vitals_recorded = $result->fetch_assoc()['count'];

// Get active cases with patient info
$active_cases_data = [];
$result = $conn->query("
    SELECT i.incident_id, i.start_time, p.pat_name, p.pat_id, p.birthdate, p.contact_number,
           (SELECT MAX(v.recorded_at) FROM vitalstat v WHERE v.incident_id = i.incident_id) as last_vital
    FROM incident i
    JOIN patient p ON i.pat_id = p.pat_id
    WHERE i.resc_id = $rescuer_id AND i.status = 'transferred'
    ORDER BY i.start_time DESC
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $active_cases_data[] = $row;
    }
}

// Get recent vital readings for all active cases
$recent_vitals = [];
if (!empty($active_cases_data)) {
    $incident_ids = array_column($active_cases_data, 'incident_id');
    $incident_list = implode(',', $incident_ids);
    
    $result = $conn->query("
        SELECT v.*, i.incident_id, p.pat_name
        FROM vitalstat v
        JOIN incident i ON v.incident_id = i.incident_id
        JOIN patient p ON i.pat_id = p.pat_id
        WHERE v.incident_id IN ($incident_list) AND v.recorded_by = 'rescuer'
        ORDER BY v.recorded_at DESC
        LIMIT 10
    ");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $recent_vitals[] = $row;
        }
    }
}

// Get transferred incidents available for acceptance
$available_incidents = [];
$result = $conn->query("
    SELECT i.incident_id, i.start_time, p.pat_name, p.pat_id, r.resp_name,
           (SELECT COUNT(*) FROM vitalstat v WHERE v.incident_id = i.incident_id) as vital_count
    FROM incident i
    JOIN patient p ON i.pat_id = p.pat_id
    JOIN responder r ON i.resp_id = r.resp_id
    WHERE i.status = 'transferred' AND i.resc_id IS NULL
    ORDER BY i.start_time DESC
    LIMIT 10
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $available_incidents[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rescuer Dashboard - VitalWear</title>
    <link rel="stylesheet" href="../../../assets/css/styles.css">
    <style>
        .rescuer-header {
            background: #ffc107;
            color: #000;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: background-color 0.2s;
        }
        
        .btn-primary { background: #007bff; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-warning { background: #ffc107; color: black; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        
        .btn:hover { opacity: 0.9; }
        
        .dashboard-grid {
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
            border-left: 4px solid #ffc107;
            text-align: center;
        }
        
        .stat-card.urgent {
            border-left-color: #dc3545;
        }
        
        .stat-card.success {
            border-left-color: #28a745;
        }
        
        .stat-card.info {
            border-left-color: #17a2b8;
        }
        
        .stat-number {
            font-size: 36px;
            font-weight: bold;
            color: #ffc107;
            margin-bottom: 10px;
        }
        
        .stat-number.urgent { color: #dc3545; }
        .stat-number.success { color: #28a745; }
        .stat-number.info { color: #17a2b8; }
        
        .stat-label {
            color: #666;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .cases-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin-bottom: 30px;
        }
        
        .case-panel {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .case-item {
            padding: 15px;
            border: 1px solid #eee;
            border-radius: 8px;
            margin-bottom: 15px;
            transition: background-color 0.2s;
        }
        
        .case-item:hover {
            background-color: #f8f9fa;
        }
        
        .case-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .case-title {
            font-weight: bold;
            color: #333;
            font-size: 16px;
        }
        
        .case-time {
            color: #666;
            font-size: 12px;
        }
        
        .case-details {
            color: #666;
            font-size: 14px;
            margin-bottom: 10px;
        }
        
        .vital-reading {
            display: flex;
            gap: 15px;
            margin-top: 10px;
            flex-wrap: wrap;
        }
        
        .vital-item {
            background: #f8f9fa;
            padding: 8px 12px;
            border-radius: 5px;
            font-size: 12px;
        }
        
        .vital-value {
            font-weight: bold;
            color: #333;
        }
        
        .vital-label {
            color: #666;
            text-transform: uppercase;
        }
        
        .accept-btn {
            background: #28a745;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .accept-btn:hover {
            background: #218838;
        }
        
        .priority-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .priority-high { background: #dc3545; color: white; }
        .priority-medium { background: #ffc107; color: black; }
        .priority-low { background: #28a745; color: white; }
        
        .vital-alert {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-critical {
            background: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
        }
        
        @media (max-width: 768px) {
            .cases-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="rescuer-header">
            <div>
                <h1 style="margin: 0;">🆘 Rescuer Dashboard</h1>
                <p style="margin: 5px 0 0 0; opacity: 0.9;">Advanced Medical Response & Patient Care</p>
            </div>
            <div>
                <div style="color: black; font-size: 14px; margin-bottom: 10px;">
                    Rescuer: <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                </div>
                <a href="../../../api/auth/logout.php" class="btn btn-secondary">Logout</a>
            </div>
        </header>

        <?php if ($active_cases > 0): ?>
        <div class="vital-alert alert-critical">
            <strong>🚨 Active Cases Require Attention!</strong><br>
            You have <?php echo $active_cases; ?> active patient case(s) requiring immediate attention.
        </div>
        <?php endif; ?>

        <div class="dashboard-grid">
            <div class="stat-card urgent">
                <div class="stat-number urgent"><?php echo $active_cases; ?></div>
                <div class="stat-label">Active Cases</div>
            </div>
            
            <div class="stat-card info">
                <div class="stat-number info"><?php echo $transferred_incidents; ?></div>
                <div class="stat-label">Available to Accept</div>
            </div>
            
            <div class="stat-card success">
                <div class="stat-number success"><?php echo $completed_incidents; ?></div>
                <div class="stat-label">Completed Cases</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_vitals_recorded; ?></div>
                <div class="stat-label">Vitals Recorded</div>
            </div>
        </div>

        <div class="cases-grid">
            <div class="case-panel">
                <h3 style="margin: 0 0 20px 0;">🏥 Active Cases</h3>
                <?php if (empty($active_cases_data)): ?>
                    <p style="text-align: center; color: #666; padding: 20px;">No active cases</p>
                <?php else: ?>
                    <?php foreach ($active_cases_data as $case): ?>
                        <div class="case-item">
                            <div class="case-header">
                                <div class="case-title">Case #<?php echo $case['incident_id']; ?></div>
                                <div class="case-time"><?php echo date('M j, H:i', strtotime($case['start_time'])); ?></div>
                            </div>
                            <div class="case-details">
                                <strong>Patient:</strong> <?php echo htmlspecialchars($case['pat_name']); ?><br>
                                <strong>Age:</strong> <?php echo (new DateTime())->diff(new DateTime($case['birthdate']))->y; ?> years<br>
                                <strong>Contact:</strong> <?php echo htmlspecialchars($case['contact_number']); ?>
                            </div>
                            <?php if ($case['last_vital']): ?>
                                <div style="color: #666; font-size: 12px;">
                                    Last vital: <?php echo date('M j, H:i', strtotime($case['last_vital'])); ?>
                                </div>
                            <?php endif; ?>
                            <div style="margin-top: 10px;">
                                <a href="ongoing_monitoring.php?incident_id=<?php echo $case['incident_id']; ?>" class="btn btn-primary">
                                    Monitor Patient
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="case-panel">
                <h3 style="margin: 0 0 20px 0;">📋 Cases to Accept</h3>
                <?php if (empty($available_incidents)): ?>
                    <p style="text-align: center; color: #666; padding: 20px;">No cases waiting for acceptance</p>
                <?php else: ?>
                    <?php foreach ($available_incidents as $incident): ?>
                        <div class="case-item">
                            <div class="case-header">
                                <div class="case-title">Incident #<?php echo $incident['incident_id']; ?></div>
                                <div class="case-time"><?php echo date('M j, H:i', strtotime($incident['start_time'])); ?></div>
                            </div>
                            <div class="case-details">
                                <strong>Patient:</strong> <?php echo htmlspecialchars($incident['pat_name']); ?><br>
                                <strong>Transferred by:</strong> <?php echo htmlspecialchars($incident['resp_name']); ?><br>
                                <strong>Vitals recorded:</strong> <?php echo $incident['vital_count']; ?>
                            </div>
                            <div style="margin-top: 10px;">
                                <form method="POST" action="accept_incident.php" style="display: inline;">
                                    <input type="hidden" name="incident_id" value="<?php echo $incident['incident_id']; ?>">
                                    <button type="submit" class="accept-btn">Accept Case</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($recent_vitals)): ?>
        <div class="case-panel">
            <h3 style="margin: 0 0 20px 0;">📈 Recent Vital Readings</h3>
            <?php foreach ($recent_vitals as $vital): ?>
                <div class="case-item">
                    <div class="case-header">
                        <div class="case-title"><?php echo htmlspecialchars($vital['pat_name']); ?></div>
                        <div class="case-time"><?php echo date('M j, H:i:s', strtotime($vital['recorded_at'])); ?></div>
                    </div>
                    <div class="vital-reading">
                        <div class="vital-item">
                            <div class="vital-value"><?php echo $vital['heart_rate']; ?></div>
                            <div class="vital-label">HR</div>
                        </div>
                        <div class="vital-item">
                            <div class="vital-value"><?php echo $vital['bp_systolic']; ?>/<?php echo $vital['bp_diastolic']; ?></div>
                            <div class="vital-label">BP</div>
                        </div>
                        <div class="vital-item">
                            <div class="vital-value"><?php echo $vital['oxygen_level']; ?>%</div>
                            <div class="vital-label">SpO2</div>
                        </div>
                    </div>
                    <div style="margin-top: 10px;">
                        <a href="add_vitals.php?incident_id=<?php echo $vital['incident_id']; ?>" class="btn btn-primary">
                            Update Vitals
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
