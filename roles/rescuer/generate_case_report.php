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

<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Case Report - VitalWear</title>

<link rel="stylesheet" href="../../../assets/css/styles.css">
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
    margin: 0;
    padding: 0;
}

/* Report Container */
.report-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

/* Report Header */
.report-header {
    background: linear-gradient(135deg, var(--deep-hospital-blue) 0%, var(--trust-blue) 100%);
    color: white;
    padding: 40px;
    border-radius: var(--radius-lg);
    margin-bottom: 30px;
    position: relative;
    box-shadow: var(--shadow-md);
    text-align: center;
}

.report-header-content {
    max-width: 600px;
    margin: 0 auto;
}

.report-title {
    font-size: 2rem;
    font-weight: 700;
    margin: 0 0 12px 0;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
}

.report-subtitle {
    font-size: 1.1rem;
    margin: 0 0 8px 0;
    opacity: 0.9;
}

.report-meta {
    font-size: 0.9rem;
    opacity: 0.8;
    margin: 0;
}

.back-btn, .logout-btn {
    position: absolute;
    top: 20px;
    background: rgba(255,255,255,0.2);
    color: white;
    padding: 10px 20px;
    border: 1px solid rgba(255,255,255,0.3);
    border-radius: var(--radius);
    text-decoration: none;
    transition: all 0.3s ease;
    font-weight: 500;
}

.back-btn {
    left: 20px;
}

.logout-btn {
    right: 20px;
}

.back-btn:hover, .logout-btn:hover {
    background: rgba(255,255,255,0.3);
    transform: translateY(-2px);
}

/* Report Content */
.report-content {
    background: var(--surface);
    padding: 40px;
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow);
    margin-bottom: 30px;
    border: 1px solid rgba(169, 183, 198, 0.2);
}

/* Sections */
.section {
    margin-bottom: 40px;
}

.section:last-child {
    margin-bottom: 0;
}

.section-title {
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--deep-hospital-blue);
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    padding-bottom: 10px;
    border-bottom: 2px solid var(--clinical-white);
}

/* Info Grid */
.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
}

.info-item {
    background: var(--clinical-white);
    padding: 20px;
    border-radius: var(--radius);
    border: 1px solid rgba(169, 183, 198, 0.2);
    transition: all 0.3s ease;
}

.info-item:hover {
    box-shadow: var(--shadow);
    transform: translateY(-2px);
}

.info-label {
    font-weight: 600;
    color: var(--system-gray);
    margin-bottom: 8px;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.info-value {
    color: var(--deep-hospital-blue);
    font-size: 1.1rem;
    font-weight: 500;
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
}

.stat-item {
    background: var(--clinical-white);
    padding: 24px;
    border-radius: var(--radius);
    border: 1px solid rgba(169, 183, 198, 0.2);
    text-align: center;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.stat-item::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, var(--medical-cyan) 0%, var(--trust-blue) 100%);
}

.stat-item:hover {
    box-shadow: var(--shadow);
    transform: translateY(-2px);
}

.stat-value {
    font-size: 2rem;
    font-weight: 700;
    color: var(--medical-cyan);
    margin-bottom: 8px;
}

.stat-label {
    color: var(--system-gray);
    font-size: 0.9rem;
    font-weight: 500;
}

/* Vitals Table */
.vitals-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
    background: var(--surface);
    border-radius: var(--radius);
    overflow: hidden;
    box-shadow: var(--shadow);
    border: 1px solid rgba(169, 183, 198, 0.2);
}

.vitals-table th,
.vitals-table td {
    padding: 16px;
    text-align: left;
    border-bottom: 1px solid rgba(169, 183, 198, 0.2);
}

.vitals-table th {
    background: var(--clinical-white);
    font-weight: 600;
    color: var(--deep-hospital-blue);
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.vitals-table tr:hover {
    background: var(--clinical-white);
}

.vitals-table tr:last-child td {
    border-bottom: none;
}

.recorded-by-responder {
    background: rgba(0, 182, 204, 0.1);
}

.recorded-by-rescuer {
    background: rgba(46, 219, 179, 0.1);
}

/* Action Buttons */
.action-buttons {
    text-align: center;
    margin-top: 40px;
    display: flex;
    justify-content: center;
    gap: 16px;
    flex-wrap: wrap;
}

.btn {
    padding: 14px 28px;
    border: none;
    border-radius: var(--radius);
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    position: relative;
    overflow: hidden;
    text-transform: none;
    letter-spacing: 0.3px;
}

.btn::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
    transition: left 0.5s;
}

.btn:hover::before {
    left: 100%;
}

.btn i {
    font-size: 16px;
    transition: transform 0.3s ease;
}

.btn:hover i {
    transform: scale(1.1);
}

.btn-primary {
    background: linear-gradient(135deg, var(--medical-cyan) 0%, var(--trust-blue) 100%);
    color: white;
    box-shadow: 0 4px 15px rgba(0, 182, 204, 0.3);
}

.btn-primary:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0, 182, 204, 0.4);
    background: linear-gradient(135deg, var(--trust-blue) 0%, var(--medical-cyan) 100%);
}

.btn-success {
    background: linear-gradient(135deg, var(--health-green) 0%, #20c997 100%);
    color: white;
    box-shadow: 0 4px 15px rgba(46, 219, 179, 0.3);
}

.btn-success:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(46, 219, 179, 0.4);
    background: linear-gradient(135deg, #20c997 0%, var(--health-green) 100%);
}

.btn-warning {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    color: white;
    box-shadow: 0 4px 15px rgba(245, 158, 11, 0.3);
}

.btn-warning:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(245, 158, 11, 0.4);
    background: linear-gradient(135deg, #d97706 0%, #f59e0b 100%);
}

/* Print Styles */
@media print {
    .back-btn, .logout-btn, .action-buttons {
        display: none;
    }
    
    .report-container {
        padding: 0;
        max-width: none;
    }
    
    .report-header {
        background: white !important;
        color: black !important;
        box-shadow: none;
        border: 1px solid #ccc;
    }
    
    .report-content {
        box-shadow: none;
        border: 1px solid #ccc;
    }
    
    .section-title {
        color: black !important;
        border-bottom-color: #ccc !important;
    }
    
    .stat-item::before {
        background: #ccc !important;
    }
    
    .stat-value {
        color: black !important;
    }
    
    body {
        background: white;
    }
}

/* Responsive Design */
@media (max-width: 768px) {
    .report-container {
        padding: 10px;
    }
    
    .report-header {
        padding: 30px 20px;
    }
    
    .report-title {
        font-size: 1.5rem;
    }
    
    .report-content {
        padding: 20px;
    }
    
    .info-grid {
        grid-template-columns: 1fr;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .action-buttons {
        flex-direction: column;
        align-items: center;
    }
    
    .btn {
        width: 100%;
        max-width: 300px;
        justify-content: center;
    }
    
    .back-btn, .logout-btn {
        position: static;
        display: block;
        margin: 10px auto;
        width: 200px;
        text-align: center;
    }
}
</style>

</head>

<body>

<div class="report-container">
    <div class="report-header">
        <a href="view_incident_details.php?id=<?php echo $incident_id; ?>" class="back-btn">
            <i class="fa fa-arrow-left"></i> Back to Details
        </a>
        <a href="#" onclick="logout()" class="logout-btn">
            <i class="fa fa-sign-out"></i> Logout
        </a>
        
        <div class="report-header-content">
            <div class="report-title">
                <i class="fa fa-file-medical"></i>
                VitalWear Case Report
            </div>
            <div class="report-subtitle">Incident #<?php echo $incident['incident_id']; ?> - Complete Medical Report</div>
            <div class="report-meta">Generated on <?php echo date('F j, Y H:i:s'); ?></div>
        </div>
    </div>

    <div class="report-content">
        <div class="section">
            <div class="section-title">
                <i class="fa fa-user"></i>
                Patient Information
            </div>
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
            </div>
        </div>

        <div class="section">
            <div class="section-title">
                <i class="fa fa-ambulance"></i>
                Incident Details
            </div>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Incident ID</div>
                    <div class="info-value">#<?php echo $incident['incident_id']; ?></div>
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
                    <div class="info-label">Start Time</div>
                    <div class="info-value"><?php echo date('M j, Y H:i:s', strtotime($incident['start_time'])); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">End Time</div>
                    <div class="info-value"><?php echo date('M j, Y H:i:s', strtotime($incident['end_time'])); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Duration</div>
                    <div class="info-value"><?php echo $duration_str; ?></div>
                </div>
            </div>
        </div>

        <div class="section">
            <div class="section-title">
                <i class="fa fa-chart-bar"></i>
                Vital Statistics Summary
            </div>
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
                <div class="section-title">
                    <i class="fa fa-chart-line"></i>
                    Detailed Vital Readings
                </div>
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
                            <tr class="<?php echo $vital['recorded_by'] === 'responder' ? 'recorded-by-responder' : 'recorded-by-rescuer'; ?>">
                                <td><?php echo date('M j, Y H:i:s', strtotime($vital['recorded_at'])); ?></td>
                                <td><?php echo $vital['bp_systolic']; ?>/<?php echo $vital['bp_diastolic']; ?></td>
                                <td><?php echo $vital['heart_rate']; ?> bpm</td>
                                <td><?php echo $vital['oxygen_level']; ?>%</td>
                                <td>
                                    <?php if ($vital['recorded_by'] === 'responder'): ?>
                                        <span style="background: rgba(0, 182, 204, 0.2); color: var(--medical-cyan); padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: 600;">
                                            🚑 Responder
                                        </span>
                                    <?php else: ?>
                                        <span style="background: rgba(46, 219, 179, 0.2); color: var(--health-green); padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: 600;">
                                            ❤️ Rescuer
                                        </span>
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
        <a href="javascript:window.print()" class="btn btn-success">
            <i class="fa fa-print"></i> Print Report
        </a>
        <a href="case_vitals_history.php?id=<?php echo $incident_id; ?>" class="btn btn-primary">
            <i class="fa fa-chart-line"></i> View Charts
        </a>
        <a href="view_incident_details.php?id=<?php echo $incident_id; ?>" class="btn btn-warning">
            <i class="fa fa-arrow-left"></i> Back to Details
        </a>
    </div>
</div>

<?php require_once 'logout_script.php'; ?>

</body>
</html>
