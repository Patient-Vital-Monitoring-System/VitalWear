<?php
session_start();
require_once '../../../database/connection.php';

// Check if management user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'management') {
    header('Location: ../../login.html');
    exit();
}

$conn = getDBConnection();

// Get quick statistics
$total_devices = 0;
$available_devices = 0;
$assigned_devices = 0;
$total_responders = 0;
$active_responders = 0;
$total_rescuers = 0;
$active_rescuers = 0;
$total_incidents = 0;
$ongoing_incidents = 0;
$completed_incidents = 0;

// Device statistics
$result = $conn->query("SELECT COUNT(*) as count FROM device");
if ($result) $total_devices = $result->fetch_assoc()['count'];

$result = $conn->query("SELECT COUNT(*) as count FROM device WHERE dev_status = 'available'");
if ($result) $available_devices = $result->fetch_assoc()['count'];

$result = $conn->query("SELECT COUNT(*) as count FROM device WHERE dev_status = 'assigned'");
if ($result) $assigned_devices = $result->fetch_assoc()['count'];

// Responder statistics
$result = $conn->query("SELECT COUNT(*) as count FROM responder");
if ($result) $total_responders = $result->fetch_assoc()['count'];

$result = $conn->query("SELECT COUNT(*) as count FROM responder WHERE status = 'active'");
if ($result) $active_responders = $result->fetch_assoc()['count'];

// Rescuer statistics
$result = $conn->query("SELECT COUNT(*) as count FROM rescuer");
if ($result) $total_rescuers = $result->fetch_assoc()['count'];

$result = $conn->query("SELECT COUNT(*) as count FROM rescuer WHERE status = 'active'");
if ($result) $active_rescuers = $result->fetch_assoc()['count'];

// Incident statistics
$result = $conn->query("SELECT COUNT(*) as count FROM incident");
if ($result) $total_incidents = $result->fetch_assoc()['count'];

$result = $conn->query("SELECT COUNT(*) as count FROM incident WHERE status = 'ongoing'");
if ($result) $ongoing_incidents = $result->fetch_assoc()['count'];

$result = $conn->query("SELECT COUNT(*) as count FROM incident WHERE status = 'completed'");
if ($result) $completed_incidents = $result->fetch_assoc()['count'];

// Recent activity
$recent_activities = [];
$result = $conn->query("SELECT * FROM activity_log ORDER BY created_at DESC LIMIT 10");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $recent_activities[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - VitalWear</title>
    <link rel="stylesheet" href="../../../assets/css/styles.css">
    <style>
        .page-header {
            background: #007bff;
            color: white;
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
        
        .overview-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .overview-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            border-left: 4px solid #007bff;
        }
        
        .overview-number {
            font-size: 36px;
            font-weight: bold;
            color: #007bff;
            margin-bottom: 10px;
        }
        
        .overview-label {
            color: #666;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .reports-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }
        
        .report-card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .report-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        
        .report-icon {
            font-size: 48px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .report-title {
            font-size: 20px;
            font-weight: bold;
            color: #333;
            margin-bottom: 15px;
            text-align: center;
        }
        
        .report-description {
            color: #666;
            margin-bottom: 20px;
            text-align: center;
            line-height: 1.5;
        }
        
        .report-features {
            list-style: none;
            padding: 0;
            margin-bottom: 20px;
        }
        
        .report-features li {
            padding: 5px 0;
            font-size: 14px;
            color: #555;
            display: flex;
            align-items: center;
        }
        
        .report-features li::before {
            content: "✓";
            color: #28a745;
            font-weight: bold;
            margin-right: 10px;
        }
        
        .report-actions {
            text-align: center;
        }
        
        .recent-activities {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .activity-item {
            padding: 15px 0;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-info {
            flex: 1;
        }
        
        .activity-user {
            font-weight: bold;
            color: #333;
        }
        
        .activity-description {
            color: #666;
            font-size: 14px;
        }
        
        .activity-time {
            color: #999;
            font-size: 12px;
            white-space: nowrap;
            margin-left: 15px;
        }
        
        @media (max-width: 768px) {
            .reports-grid {
                grid-template-columns: 1fr;
            }
            
            .overview-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .activity-item {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .activity-time {
                margin-left: 0;
                margin-top: 5px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="page-header">
            <div>
                <h1 style="margin: 0;">📊 Reports Dashboard</h1>
                <p style="margin: 5px 0 0 0; opacity: 0.9;">System reports and analytics</p>
            </div>
            <div>
                <a href="../dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
            </div>
        </header>

        <div class="overview-grid">
            <div class="overview-card">
                <div class="overview-number"><?php echo $total_devices; ?></div>
                <div class="overview-label">Total Devices</div>
            </div>
            
            <div class="overview-card">
                <div class="overview-number"><?php echo $available_devices; ?></div>
                <div class="overview-label">Available Devices</div>
            </div>
            
            <div class="overview-card">
                <div class="overview-number"><?php echo $active_responders; ?></div>
                <div class="overview-label">Active Responders</div>
            </div>
            
            <div class="overview-card">
                <div class="overview-number"><?php echo $ongoing_incidents; ?></div>
                <div class="overview-label">Ongoing Incidents</div>
            </div>
        </div>

        <div class="reports-grid">
            <div class="report-card">
                <div class="report-icon">📦</div>
                <h3 class="report-title">Device Assignment History</h3>
                <p class="report-description">
                    Comprehensive view of all device assignments and returns
                </p>
                <ul class="report-features">
                    <li>Filter by date range and responder</li>
                    <li>Track assignment duration</li>
                    <li>Monitor device utilization</li>
                    <li>Export to CSV functionality</li>
                </ul>
                <div class="report-actions">
                    <a href="device_assignment_history.php" class="btn btn-primary">View Report</a>
                </div>
            </div>
            
            <div class="report-card">
                <div class="report-icon">✅</div>
                <h3 class="report-title">Device Return History</h3>
                <p class="report-description">
                    Track device returns and verification status
                </p>
                <ul class="report-features">
                    <li>Monitor return verification status</li>
                    <li>Calculate average usage duration</li>
                    <li>Filter by device and responder</li>
                    <li>Export detailed return logs</li>
                </ul>
                <div class="report-actions">
                    <a href="device_return_history.php" class="btn btn-primary">View Report</a>
                </div>
            </div>
            
            <div class="report-card">
                <div class="report-icon">👥</div>
                <h3 class="report-title">Responder Activity Report</h3>
                <p class="report-description">
                    Monitor responder activities and engagement
                </p>
                <ul class="report-features">
                    <li>Track all responder actions</li>
                    <li>Activity type breakdown</li>
                    <li>Filter by responder and date</li>
                    <li>Monitor system usage patterns</li>
                </ul>
                <div class="report-actions">
                    <a href="responder_activity.php" class="btn btn-primary">View Report</a>
                </div>
            </div>
            
            <div class="report-card">
                <div class="report-icon">🚑</div>
                <h3 class="report-title">Incident Summary Report</h3>
                <p class="report-description">
                    Comprehensive incident analysis and statistics
                </p>
                <ul class="report-features">
                    <li>Track incident status and duration</li>
                    <li>Patient and responder information</li>
                    <li>Vital statistics summary</li>
                    <li>Filter by multiple criteria</li>
                </ul>
                <div class="report-actions">
                    <a href="incident_summary.php" class="btn btn-primary">View Report</a>
                </div>
            </div>
        </div>

        <?php if (!empty($recent_activities)): ?>
        <div class="recent-activities">
            <h3 style="margin: 0 0 20px 0;">Recent System Activities</h3>
            <?php foreach ($recent_activities as $activity): ?>
                <div class="activity-item">
                    <div class="activity-info">
                        <div class="activity-user"><?php echo htmlspecialchars($activity['user_name'] ?? 'Unknown'); ?></div>
                        <div class="activity-description">
                            <?php echo htmlspecialchars($activity['description'] ?? 'No description'); ?>
                        </div>
                    </div>
                    <div class="activity-time">
                        <?php echo date('M j, H:i', strtotime($activity['created_at'])); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
