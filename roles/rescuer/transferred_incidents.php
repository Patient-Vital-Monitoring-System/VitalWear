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
$rescuer = ['resc_name' => 'Rescuer'];
if ($conn && !$conn->connect_error) {
    $rescuer_query = "SELECT resc_name, resc_email FROM rescuer WHERE resc_id = ?";
    $stmt = $conn->prepare($rescuer_query);
    if ($stmt) {
        $stmt->bind_param("i", $rescuer_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        if ($result) {
            $rescuer = $result;
        }
    }
}

// Get transferred incidents with patient and vital details
$transferred_incidents = false;
if ($conn && !$conn->connect_error) {
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
    if ($stmt) {
        $stmt->bind_param("i", $rescuer_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        // Group incidents by incident_id
        $incidents_data = [];
        while ($row = $result->fetch_assoc()) {
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
        $transferred_incidents = !empty($incidents_data);
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Transferred Incidents - VitalWear</title>

<link rel="stylesheet" href="../../assets/css/styles.css">
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
}

/* Soft UI Sidebar */
#sidebar {
    background: var(--surface);
    border-right: 1px solid rgba(169, 183, 198, 0.3);
    box-shadow: var(--shadow);
}

.sidebar-logo {
    padding: 24px 20px;
    text-align: center;
    background: linear-gradient(135deg, var(--deep-hospital-blue) 0%, var(--trust-blue) 100%);
    margin: 12px;
    border-radius: var(--radius);
}

.sidebar-logo img {
    max-width: 140px;
    height: auto;
    filter: brightness(0) invert(1);
}

#sidebar a {
    color: var(--deep-hospital-blue);
    margin: 6px 12px;
    padding: 12px 16px;
    border-radius: var(--radius);
    transition: all 0.2s ease;
    border: none;
    font-weight: 500;
}

#sidebar a:hover {
    background: rgba(0, 182, 204, 0.1);
    color: var(--medical-cyan);
    transform: translateX(4px);
}

/* Soft UI Header */
.topbar {
    background: var(--surface);
    color: var(--deep-hospital-blue);
    border-bottom: 1px solid rgba(169, 183, 198, 0.2);
    box-shadow: var(--shadow-sm);
    padding: 16px 24px;
    font-weight: 600;
}

h2, h3, h4 {
    color: var(--deep-hospital-blue);
    font-weight: 700;
}

/* Modern Soft Edge Navigation */
.bottom-nav {
    background: var(--surface);
    border-top: 1px solid rgba(169, 183, 198, 0.3);
    box-shadow: 0 -4px 20px rgba(10, 42, 85, 0.08);
    padding: 12px 24px;
    display: flex;
    justify-content: space-around;
    align-items: center;
}

.bottom-nav .bottom-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    padding: 10px 20px;
    border-radius: var(--radius);
    color: var(--system-gray);
    text-decoration: none;
    transition: all 0.3s ease;
    font-weight: 500;
    font-size: 12px;
}

.bottom-nav .bottom-item i {
    font-size: 20px;
    transition: all 0.3s ease;
}

.bottom-nav .bottom-item:hover {
    color: var(--medical-cyan);
    background: rgba(0, 182, 204, 0.1);
    transform: translateY(-2px);
}

.bottom-nav .bottom-item.active {
    color: var(--medical-cyan);
    background: rgba(0, 182, 204, 0.15);
}

.bottom-nav .bottom-item.active i {
    transform: scale(1.1);
}

/* Modern Soft Edge Cards */
.dashboard-card {
    background: var(--surface);
    padding: 24px;
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow);
    border: 1px solid rgba(169, 183, 198, 0.2);
    margin-bottom: 20px;
    transition: all 0.3s ease;
}

.dashboard-card:hover {
    box-shadow: var(--shadow-md);
    transform: translateY(-2px);
}

.dashboard-card h3 {
    color: var(--deep-hospital-blue);
    margin-bottom: 16px;
    font-size: 1.1rem;
    display: flex;
    align-items: center;
    gap: 8px;
}

/* Incident Card Styles */
.incident-card {
    background: var(--surface);
    padding: 24px;
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow);
    border: 1px solid rgba(169, 183, 198, 0.2);
    margin-bottom: 20px;
    transition: all 0.3s ease;
}

.incident-card:hover {
    box-shadow: var(--shadow-md);
    transform: translateY(-2px);
}

.incident-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 20px;
}

.incident-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--deep-hospital-blue);
    margin-bottom: 8px;
}

.incident-meta {
    display: flex;
    flex-direction: column;
    gap: 4px;
    color: var(--system-gray);
    font-size: 14px;
}

.incident-body {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}

.info-section {
    background: var(--clinical-white);
    padding: 16px;
    border-radius: var(--radius);
    border-left: 4px solid var(--medical-cyan);
}

.info-label {
    font-weight: 600;
    color: var(--deep-hospital-blue);
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.info-value {
    color: var(--system-gray);
    line-height: 1.6;
}

.vitals-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 12px;
    margin-top: 12px;
}

.vital-item {
    text-align: center;
    padding: 12px;
    background: var(--clinical-white);
    border-radius: var(--radius);
    border: 1px solid rgba(169, 183, 198, 0.2);
}

.vital-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--deep-hospital-blue);
    margin-bottom: 4px;
}

.vital-label {
    font-size: 12px;
    color: var(--system-gray);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.action-buttons {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}

.btn-primary {
    background: linear-gradient(135deg, var(--medical-cyan) 0%, var(--trust-blue) 100%);
    color: white;
    border: none;
    padding: 12px 20px;
    border-radius: var(--radius);
    font-weight: 600;
    box-shadow: var(--shadow);
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}

.btn-secondary {
    background: linear-gradient(135deg, var(--health-green) 0%, #20c997 100%);
    color: white;
    border: none;
    padding: 12px 20px;
    border-radius: var(--radius);
    font-weight: 600;
    box-shadow: var(--shadow);
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-secondary:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}

.status-badge {
    display: inline-flex;
    align-items: center;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 14px;
    font-weight: 600;
}

.status-transferred {
    background: rgba(0, 182, 204, 0.15);
    color: var(--medical-cyan);
}

.no-incidents {
    text-align: center;
    padding: 60px 20px;
    background: var(--surface);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow);
    border: 1px solid rgba(169, 183, 198, 0.2);
}

.no-incidents h3 {
    font-size: 2rem;
    margin-bottom: 16px;
    color: var(--deep-hospital-blue);
}

.no-incidents p {
    color: var(--system-gray);
    margin-bottom: 24px;
}
</style>

</head>

<body>

<header class="topbar">
    <div style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
        <div style="display: flex; align-items: center; gap: 12px;">
            <i class="fa fa-heart-pulse" style="font-size: 24px; color: var(--medical-cyan);"></i>
            <span style="font-size: 18px; font-weight: 700;">VitalWear</span>
        </div>
        <div style="display: flex; align-items: center; gap: 8px; color: var(--deep-hospital-blue); font-weight: 500;">
            <i class="fa fa-user-circle" style="font-size: 20px; color: var(--medical-cyan);"></i>
            <span><?php echo htmlspecialchars($rescuer['resc_name'] ?? 'Rescuer'); ?></span>
        </div>
    </div>
</header>

<nav id="sidebar">
<div class="sidebar-logo">
    <img src="../../assets/logo.png" alt="VitalWear Logo">
</div>
<a href="dashboard.php"><i class="fa fa-gauge"></i> Dashboard</a>
<a href="transferred_incidents.php"><i class="fa fa-exclamation-circle"></i> Transferred Incidents</a>
<a href="ongoing_monitoring.php"><i class="fa fa-heart-pulse"></i> Ongoing Monitoring</a>
<a href="completed_cases.php"><i class="fa fa-check-circle"></i> Completed Cases</a>
<a href="incident_records.php"><i class="fa fa-folder"></i> Incident Records</a>
<a href="return_device.php"><i class="fa fa-undo"></i> Return Device</a>
<a href="../../api/auth/logout.php" class="btn btn-secondary">Logout</a>
</nav>

<main class="container" style="display:block;overflow-y:auto;">

<!-- Welcome Banner -->
<div style="background: linear-gradient(135deg, var(--deep-hospital-blue) 0%, var(--trust-blue) 100%); padding: 32px; border-radius: var(--radius-lg); margin-bottom: 24px; color: white; box-shadow: var(--shadow-md);">
    <div style="display: flex; align-items: center; gap: 16px;">
        <div style="width: 60px; height: 60px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 28px;">
            📥
        </div>
        <div>
            <h1 style="color: white; margin: 0; font-size: 1.75rem; font-weight: 700;">Transferred Incidents</h1>
            <p style="color: rgba(255,255,255,0.9); margin: 4px 0 0 0; font-size: 1rem;">Review and accept incidents from responders</p>
        </div>
    </div>
</div>

<?php if ($transferred_incidents && !empty($incidents_data)): ?>
    <div class="incidents-list">
        <?php foreach ($incidents_data as $incident): ?>
            <div class="incident-card">
                <div class="incident-header">
                    <div>
                        <div class="incident-title">Incident #<?php echo $incident['incident_id']; ?></div>
                        <div class="incident-meta">
                            <span>📅 <?php echo date('M j, Y H:i', strtotime($incident['start_time'])); ?></span>
                            <span>👤 <?php echo htmlspecialchars($incident['pat_name']); ?></span>
                        </div>
                    </div>
                    <span class="status-badge status-transferred">
                        <i class="fa fa-circle" style="font-size: 8px; margin-right: 6px;"></i>
                        Transferred
                    </span>
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
                        <div class="vitals-info" style="grid-column: 1 / -1;">
                            <div class="info-section">
                                <div class="info-label">❤️ Latest Vital Signs</div>
                                <div class="vitals-grid">
                                    <?php $latest_vital = end($incident['vitals']); ?>
                                    <div class="vital-item">
                                        <div class="vital-value" style="color: #e74c3c;"><?php echo $latest_vital['heart_rate']; ?></div>
                                        <div class="vital-label">Heart Rate</div>
                                    </div>
                                    <div class="vital-item">
                                        <div class="vital-value" style="color: #22c55e;"><?php echo $latest_vital['bp_systolic']; ?>/<?php echo $latest_vital['bp_diastolic']; ?></div>
                                        <div class="vital-label">Blood Pressure</div>
                                    </div>
                                    <div class="vital-item">
                                        <div class="vital-value" style="color: #3b82f6;"><?php echo $latest_vital['oxygen_level']; ?>%</div>
                                        <div class="vital-label">SpO2</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="action-buttons">
                    <a href="accept_incident.php?id=<?php echo $incident['incident_id']; ?>" class="btn-primary">
                        <i class="fa fa-check"></i> Accept & Start Monitoring
                    </a>
                    <a href="view_incident_details.php?id=<?php echo $incident['incident_id']; ?>" class="btn-secondary">
                        <i class="fa fa-eye"></i> View Full Details
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="no-incidents">
        <div style="font-size:64px;margin-bottom:20px;color:var(--system-gray);">
            📥
        </div>
        <h3>No Transferred Incidents</h3>
        <p>There are no transferred incidents at the moment. Check back later for new cases.</p>
        <a href="dashboard.php" class="btn-primary">Return to Dashboard</a>
    </div>
<?php endif; ?>

</main>

<nav class="bottom-nav">
<a href="dashboard.php" class="bottom-item">
    <i class="fa fa-gauge"></i>
    <span>Home</span>
</a>

<a href="transferred_incidents.php" class="bottom-item active">
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

<a href="../../api/auth/logout.php" class="bottom-item">
    <i class="fa fa-sign-out"></i>
    <span>Logout</span>
</a>
</nav>

</body>
</html>
