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

// Get completed incidents with summary data
$completed_query = "SELECT i.incident_id, i.start_time, i.end_time, p.pat_name, p.birthdate,
                   r.resp_name, COUNT(v.vital_id) as total_vitals,
                   MIN(v.recorded_at) as first_vital, MAX(v.recorded_at) as last_vital
                   FROM incident i 
                   JOIN patient p ON i.pat_id = p.pat_id 
                   JOIN responder r ON i.resp_id = r.resp_id 
                   LEFT JOIN vitalstat v ON i.incident_id = v.incident_id
                   WHERE i.resc_id = ? AND i.status = 'completed' 
                   GROUP BY i.incident_id, i.start_time, i.end_time, p.pat_name, p.birthdate, r.resp_name
                   ORDER BY i.end_time DESC";
$stmt = $conn->prepare($completed_query);
$stmt->bind_param("i", $rescuer_id);
$stmt->execute();
$completed_incidents = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Completed Cases - VitalWear</title>
    <link rel="stylesheet" href="../../../assets/css/styles.css">
    <style>
        .container {
            max-width: 1200px;
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
        .cases-grid {
            display: grid;
            gap: 20px;
        }
        .case-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: transform 0.3s ease;
        }
        .case-card:hover {
            transform: translateY(-3px);
        }
        .case-header {
            background: #f7fafc;
            padding: 20px;
            border-bottom: 1px solid #e2e8f0;
        }
        .case-title {
            font-size: 1.2em;
            font-weight: bold;
            color: #2d3748;
            margin-bottom: 10px;
        }
        .case-meta {
            display: flex;
            gap: 20px;
            color: #718096;
            font-size: 0.9em;
            flex-wrap: wrap;
        }
        .case-body {
            padding: 20px;
        }
        .case-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .summary-item {
            text-align: center;
            padding: 15px;
            background: #f7fafc;
            border-radius: 8px;
        }
        .summary-value {
            font-size: 1.3em;
            font-weight: bold;
            color: #ed8936;
        }
        .summary-label {
            font-size: 0.8em;
            color: #718096;
            margin-top: 5px;
        }
        .patient-info {
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
        .action-buttons {
            padding: 20px;
            background: #f7fafc;
            border-top: 1px solid #e2e8f0;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
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
        .btn-warning {
            background: #ed8936;
            color: white;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        .no-cases {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .no-cases h3 {
            color: #718096;
            margin-bottom: 10px;
        }
        .duration {
            color: #38a169;
            font-weight: 600;
        }
        .stats-bar {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-around;
            flex-wrap: wrap;
            gap: 20px;
        }
        .stat-item {
            text-align: center;
        }
        .stat-number {
            font-size: 2em;
            font-weight: bold;
            color: #ed8936;
        }
        .stat-label {
            color: #718096;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>
            <h1>✅ Completed Cases</h1>
            <p>View your completed incident cases and monitoring history</p>
        </div>

        <?php if ($completed_incidents->num_rows > 0): ?>
            <?php
            // Calculate statistics
            $total_cases = $completed_incidents->num_rows;
            $total_vitals = 0;
            $total_duration = 0;
            
            $completed_incidents->data_seek(0); // Reset pointer
            while ($case = $completed_incidents->fetch_assoc()) {
                $total_vitals += $case['total_vitals'];
                if ($case['start_time'] && $case['end_time']) {
                    $duration = strtotime($case['end_time']) - strtotime($case['start_time']);
                    $total_duration += $duration;
                }
            }
            
            $avg_duration = $total_cases > 0 ? $total_duration / $total_cases : 0;
            $avg_vitals = $total_cases > 0 ? $total_vitals / $total_cases : 0;
            ?>

            <div class="stats-bar">
                <div class="stat-item">
                    <div class="stat-number"><?php echo $total_cases; ?></div>
                    <div class="stat-label">Total Cases</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo number_format($avg_vitals, 1); ?></div>
                    <div class="stat-label">Avg Vital Records</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo gmdate('H:i', $avg_duration); ?></div>
                    <div class="stat-label">Avg Duration</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo $total_vitals; ?></div>
                    <div class="stat-label">Total Vital Records</div>
                </div>
            </div>

            <div class="cases-grid">
                <?php 
                $completed_incidents->data_seek(0); // Reset pointer again
                while ($case = $completed_incidents->fetch_assoc()): 
                    $duration = '';
                    if ($case['start_time'] && $case['end_time']) {
                        $seconds = strtotime($case['end_time']) - strtotime($case['start_time']);
                        $hours = floor($seconds / 3600);
                        $minutes = floor(($seconds % 3600) / 60);
                        $duration = $hours > 0 ? "{$hours}h {$minutes}m" : "{$minutes}m";
                    }
                ?>
                    <div class="case-card">
                        <div class="case-header">
                            <div class="case-title">Incident #<?php echo $case['incident_id']; ?></div>
                            <div class="case-meta">
                                <span>👤 <?php echo htmlspecialchars($case['pat_name']); ?></span>
                                <span>🚑 <?php echo htmlspecialchars($case['resp_name']); ?></span>
                                <span class="duration">⏱️ <?php echo $duration; ?></span>
                            </div>
                        </div>
                        
                        <div class="case-body">
                            <div class="patient-info">
                                <div class="info-label">Patient Information</div>
                                <div class="info-value">
                                    <strong>Name:</strong> <?php echo htmlspecialchars($case['pat_name']); ?><br>
                                    <strong>Age:</strong> <?php echo date('Y') - date('Y', strtotime($case['birthdate'])); ?> years
                                </div>
                            </div>
                            
                            <div class="case-summary">
                                <div class="summary-item">
                                    <div class="summary-value"><?php echo $case['total_vitals']; ?></div>
                                    <div class="summary-label">Vital Records</div>
                                </div>
                                <div class="summary-item">
                                    <div class="summary-value"><?php echo date('M j, Y', strtotime($case['start_time'])); ?></div>
                                    <div class="summary-label">Start Date</div>
                                </div>
                                <div class="summary-item">
                                    <div class="summary-value"><?php echo date('H:i', strtotime($case['start_time'])); ?></div>
                                    <div class="summary-label">Start Time</div>
                                </div>
                                <div class="summary-item">
                                    <div class="summary-value"><?php echo date('H:i', strtotime($case['end_time'])); ?></div>
                                    <div class="summary-label">End Time</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="action-buttons">
                            <a href="view_incident_details.php?id=<?php echo $case['incident_id']; ?>" class="btn btn-primary">📋 View Full Details</a>
                            <a href="case_vitals_history.php?id=<?php echo $case['incident_id']; ?>" class="btn btn-secondary">📈 Vital History</a>
                            <a href="generate_case_report.php?id=<?php echo $case['incident_id']; ?>" class="btn btn-warning">📄 Generate Report</a>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="no-cases">
                <h3>✅ No Completed Cases</h3>
                <p>You haven't completed any cases yet. Start monitoring transferred incidents to build your case history.</p>
                <a href="transferred_incidents.php" class="btn btn-primary" style="margin-top: 20px;">View Transferred Incidents</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
