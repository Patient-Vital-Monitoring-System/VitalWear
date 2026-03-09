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

<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Incident Details - VitalWear</title>

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

/* Details Container */
.details-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

/* Header */
.header {
    background: linear-gradient(135deg, var(--deep-hospital-blue) 0%, var(--trust-blue) 100%);
    color: white;
    padding: 40px;
    border-radius: var(--radius-lg);
    margin-bottom: 30px;
    position: relative;
    box-shadow: var(--shadow-md);
    text-align: center;
}

.header-content {
    max-width: 600px;
    margin: 0 auto;
}

.header h1 {
    font-size: 2rem;
    font-weight: 700;
    margin: 0 0 12px 0;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
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

/* Content Sections */
.incident-info, .vitals-section {
    background: var(--surface);
    padding: 40px;
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow);
    margin-bottom: 30px;
    border: 1px solid rgba(169, 183, 198, 0.2);
}

.section-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--deep-hospital-blue);
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    gap: 12px;
    padding-bottom: 12px;
    border-bottom: 2px solid var(--clinical-white);
}

/* Info Grid */
.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
}

.info-item {
    background: var(--clinical-white);
    padding: 24px;
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

/* Status Badges */
.status-badge {
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    display: inline-block;
}

.status-transferred {
    background: linear-gradient(135deg, var(--medical-cyan) 0%, var(--trust-blue) 100%);
    color: white;
}

.status-ongoing {
    background: linear-gradient(135deg, var(--health-green) 0%, #20c997 100%);
    color: white;
}

.status-completed {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    color: white;
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

.recorded-by-badge {
    background: rgba(0, 182, 204, 0.2);
    color: var(--medical-cyan);
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
}

.recorded-by-badge.rescuer {
    background: rgba(46, 219, 179, 0.2);
    color: var(--health-green);
}

/* Action Buttons */
.action-buttons {
    margin-top: 30px;
    display: flex;
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

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    background: var(--clinical-white);
    border-radius: var(--radius);
    border: 1px solid rgba(169, 183, 198, 0.2);
}

.empty-state h3 {
    color: var(--deep-hospital-blue);
    margin-bottom: 16px;
    font-size: 1.3rem;
}

.empty-state p {
    color: var(--system-gray);
    margin-bottom: 24px;
}

/* Responsive Design */
@media (max-width: 768px) {
    .details-container {
        padding: 10px;
    }
    
    .header {
        padding: 30px 20px;
    }
    
    .header h1 {
        font-size: 1.5rem;
    }
    
    .incident-info, .vitals-section {
        padding: 20px;
    }
    
    .info-grid {
        grid-template-columns: 1fr;
    }
    
    .action-buttons {
        flex-direction: column;
    }
    
    .btn {
        width: 100%;
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

<div class="details-container">
    <div class="header">
        <a href="incident_records.php" class="back-btn">
            <i class="fa fa-arrow-left"></i> Back to Records
        </a>
        <a href="#" onclick="logout()" class="logout-btn">
            <i class="fa fa-sign-out"></i> Logout
        </a>
        
        <div class="header-content">
            <h1>
                <i class="fa fa-clipboard-list"></i>
                Incident #<?php echo $incident['incident_id']; ?> Details
            </h1>
        </div>
    </div>

    <div class="incident-info">
        <h2 class="section-title">
            <i class="fa fa-user"></i>
            Patient Information
        </h2>
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
        
        <h2 class="section-title" style="margin-top: 30px;">
            <i class="fa fa-ambulance"></i>
            Incident Information
        </h2>
        <div class="info-grid">
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
                        <?php 
                        switch($incident['status']) {
                            case 'transferred': echo 'Transferred'; break;
                            case 'ongoing': echo 'Ongoing'; break;
                            case 'completed': echo 'Completed'; break;
                            default: echo $incident['status'];
                        }
                        ?>
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
        <h2 class="section-title">
            <i class="fa fa-heart-pulse"></i>
            Vital Signs History
        </h2>
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
                                    <span class="recorded-by-badge">
                                        🚑 Responder
                                    </span>
                                <?php else: ?>
                                    <span class="recorded-by-badge rescuer">
                                        ❤️ Rescuer
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            
            <div class="action-buttons">
                <a href="case_vitals_history.php?id=<?php echo $incident_id; ?>" class="btn btn-primary">
                    <i class="fa fa-chart-line"></i> Detailed Vitals Chart
                </a>
                <?php if ($incident['status'] === 'completed'): ?>
                    <a href="generate_case_report.php?id=<?php echo $incident_id; ?>" class="btn btn-warning">
                        <i class="fa fa-file-medical"></i> Generate Report
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div style="font-size: 64px; margin-bottom: 20px; color: var(--system-gray);">
                    📊
                </div>
                <h3>No Vital Signs Recorded</h3>
                <p>No vital signs have been recorded for this incident yet.</p>
                <p style="color: var(--system-gray); font-size: 14px;">
                    Start monitoring this incident to begin recording vital signs.
                </p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'logout_script.php'; ?>

</body>
</html>
