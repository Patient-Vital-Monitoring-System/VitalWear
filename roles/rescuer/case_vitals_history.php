<?php
require_once '../../database/connection.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'rescuer') {
    header("Location: ../../login.html");
    exit;
}

if (!isset($_GET['id'])) {
    header('Location: ongoing_monitoring.php');
    exit();
}

$incident_id = $_GET['id'];
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

// Verify incident belongs to this rescuer
if ($conn && !$conn->connect_error) {
    $verify_query = "SELECT incident_id FROM incident WHERE incident_id = ? AND resc_id = ?";
    $stmt = $conn->prepare($verify_query);
    if ($stmt) {
        $stmt->bind_param("ii", $incident_id, $rescuer_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            header('Location: ongoing_monitoring.php');
            exit();
        }
    }
}

// Get vital statistics
$vitals_data = [];
if ($conn && !$conn->connect_error) {
    $vitals_query = "SELECT bp_systolic, bp_diastolic, heart_rate, oxygen_level, recorded_at, recorded_by 
                      FROM vitalstat 
                      WHERE incident_id = ? 
                      ORDER BY recorded_at ASC";
    $stmt = $conn->prepare($vitals_query);
    if ($stmt) {
        $stmt->bind_param("i", $incident_id);
        $stmt->execute();
        $vitals = $stmt->get_result();

        while ($vital = $vitals->fetch_assoc()) {
            $vitals_data[] = $vital;
        }
    }
}

// Get incident info for header
$incident = null;
if ($conn && !$conn->connect_error) {
    $incident_query = "SELECT i.incident_id, i.status, p.pat_name, i.start_time
                      FROM incident i 
                      JOIN patient p ON i.pat_id = p.pat_id 
                      WHERE i.incident_id = ?";
    $stmt = $conn->prepare($incident_query);
    if ($stmt) {
        $stmt->bind_param("i", $incident_id);
        $stmt->execute();
        $incident = $stmt->get_result()->fetch_assoc();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Vitals History - VitalWear</title>

<link rel="stylesheet" href="../../assets/css/styles.css">
<script src="https://kit.fontawesome.com/96e37b53f1.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

/* Chart Container */
.chart-container {
    background: var(--surface);
    padding: 24px;
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow);
    border: 1px solid rgba(169, 183, 198, 0.2);
    margin-bottom: 20px;
}

.chart-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.chart-box {
    background: var(--clinical-white);
    padding: 20px;
    border-radius: var(--radius);
    border: 1px solid rgba(169, 183, 198, 0.2);
    min-height: 350px;
    display: flex;
    flex-direction: column;
}

.chart-title {
    font-weight: 600;
    color: var(--deep-hospital-blue);
    margin-bottom: 15px;
    text-align: center;
}

.chart-box canvas {
    flex: 1;
    width: 100% !important;
    height: 280px !important;
    max-height: 280px;
}

/* Vitals Table */
.vitals-table {
    background: var(--surface);
    padding: 24px;
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow);
    border: 1px solid rgba(169, 183, 198, 0.2);
}

.vitals-table table {
    width: 100%;
    border-collapse: collapse;
}

.vitals-table th,
.vitals-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid rgba(169, 183, 198, 0.2);
}

.vitals-table th {
    background: var(--clinical-white);
    font-weight: 600;
    color: var(--deep-hospital-blue);
}

.vitals-table tr:hover {
    background: var(--clinical-white);
}

.recorded-by-responder {
    background: rgba(0, 182, 204, 0.1);
}

.recorded-by-rescuer {
    background: rgba(46, 219, 179, 0.1);
}

/* Buttons */
.btn {
    padding: 12px 20px;
    border: none;
    border-radius: var(--radius);
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-primary {
    background: linear-gradient(135deg, var(--medical-cyan) 0%, var(--trust-blue) 100%);
    color: white;
    box-shadow: var(--shadow);
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}

.btn-secondary {
    background: linear-gradient(135deg, var(--health-green) 0%, #20c997 100%);
    color: white;
    box-shadow: var(--shadow);
}

.btn-secondary:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}

.btn-warning {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    color: white;
    box-shadow: var(--shadow);
}

.btn-warning:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}

/* Welcome Banner */
.welcome-banner {
    background: linear-gradient(135deg, var(--deep-hospital-blue) 0%, var(--trust-blue) 100%);
    padding: 32px;
    border-radius: var(--radius-lg);
    margin-bottom: 24px;
    color: white;
    box-shadow: var(--shadow-md);
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    background: var(--surface);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow);
    border: 1px solid rgba(169, 183, 198, 0.2);
}

.empty-state h3 {
    color: var(--deep-hospital-blue);
    margin-bottom: 16px;
}

.empty-state p {
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
<div class="welcome-banner">
    <div style="display: flex; align-items: center; gap: 16px;">
        <div style="width: 60px; height: 60px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 28px;">
            📊
        </div>
        <div>
            <h1 style="color: white; margin: 0; font-size: 1.75rem; font-weight: 700;">Vitals History</h1>
            <p style="color: rgba(255,255,255,0.9); margin: 4px 0 0 0; font-size: 1rem;">Incident #<?php echo $incident['incident_id']; ?> - <?php echo htmlspecialchars($incident['pat_name']); ?></p>
        </div>
    </div>
</div>

<!-- Summary Statistics -->
<?php if (count($vitals_data) > 0): ?>
<div class="vitals-table" style="margin-bottom: 20px;">
    <h3 style="margin-bottom: 20px; display: flex; align-items: center; gap: 8px;">
        <i class="fa fa-chart-bar"></i> Summary Statistics
    </h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
        <div style="text-align: center; padding: 20px; background: var(--clinical-white); border-radius: var(--radius); border: 1px solid rgba(169, 183, 198, 0.2);">
            <div style="font-size: 24px; font-weight: 700; color: var(--medical-cyan);">
                <?php 
                $avg_systolic = array_sum(array_column($vitals_data, 'bp_systolic')) / count($vitals_data);
                echo round($avg_systolic, 1); 
                ?>
            </div>
            <div style="font-size: 12px; color: var(--system-gray);">Avg Systolic BP</div>
        </div>
        <div style="text-align: center; padding: 20px; background: var(--clinical-white); border-radius: var(--radius); border: 1px solid rgba(169, 183, 198, 0.2);">
            <div style="font-size: 24px; font-weight: 700; color: var(--medical-cyan);">
                <?php 
                $avg_diastolic = array_sum(array_column($vitals_data, 'bp_diastolic')) / count($vitals_data);
                echo round($avg_diastolic, 1); 
                ?>
            </div>
            <div style="font-size: 12px; color: var(--system-gray);">Avg Diastolic BP</div>
        </div>
        <div style="text-align: center; padding: 20px; background: var(--clinical-white); border-radius: var(--radius); border: 1px solid rgba(169, 183, 198, 0.2);">
            <div style="font-size: 24px; font-weight: 700; color: var(--health-green);">
                <?php 
                $avg_hr = array_sum(array_column($vitals_data, 'heart_rate')) / count($vitals_data);
                echo round($avg_hr, 1); 
                ?>
            </div>
            <div style="font-size: 12px; color: var(--system-gray);">Avg Heart Rate</div>
        </div>
        <div style="text-align: center; padding: 20px; background: var(--clinical-white); border-radius: var(--radius); border: 1px solid rgba(169, 183, 198, 0.2);">
            <div style="font-size: 24px; font-weight: 700; color: var(--trust-blue);">
                <?php 
                $avg_oxygen = array_sum(array_column($vitals_data, 'oxygen_level')) / count($vitals_data);
                echo round($avg_oxygen, 1); 
                ?>
            </div>
            <div style="font-size: 12px; color: var(--system-gray);">Avg Oxygen Level</div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Charts Section -->
<?php if (count($vitals_data) > 0): ?>
<div class="chart-container">
    <h3 style="margin-bottom: 20px; display: flex; align-items: center; gap: 8px;">
        <i class="fa fa-chart-line"></i> Vital Signs Trends
    </h3>
    <div class="chart-grid">
        <div class="chart-box">
            <div class="chart-title">Blood Pressure Trend</div>
            <canvas id="bpChart"></canvas>
        </div>
        <div class="chart-box">
            <div class="chart-title">Heart Rate Trend</div>
            <canvas id="hrChart"></canvas>
        </div>
        <div class="chart-box">
            <div class="chart-title">Oxygen Level Trend</div>
            <canvas id="o2Chart"></canvas>
        </div>
    </div>
</div>
<?php else: ?>
<div class="chart-container">
    <h3 style="margin-bottom: 20px; display: flex; align-items: center; gap: 8px;">
        <i class="fa fa-chart-line"></i> Vital Signs Trends
    </h3>
    <div style="text-align: center; padding: 40px; background: var(--clinical-white); border-radius: var(--radius); border: 1px solid rgba(169, 183, 198, 0.2);">
        <div style="font-size: 48px; margin-bottom: 16px; color: var(--system-gray);">📈</div>
        <h4 style="color: var(--deep-hospital-blue); margin-bottom: 8px;">No Data for Charts</h4>
        <p style="color: var(--system-gray);">Start monitoring to see vital signs trends here.</p>
    </div>
</div>
<?php endif; ?>

<!-- Detailed Vitals Data -->
<div class="vitals-table">
    <h3 style="margin-bottom: 20px; display: flex; align-items: center; gap: 8px;">
        <i class="fa fa-list"></i> Vitals History Table
    </h3>
    
    <p style="background: rgba(0, 182, 204, 0.1); padding: 12px; border-radius: var(--radius); margin-bottom: 20px; color: var(--deep-hospital-blue);">
        <strong>📊 Total Records:</strong> <?php echo count($vitals_data); ?> vital measurements recorded
        <?php if (count($vitals_data) > 0): ?>
            | <strong>Latest:</strong> <?php echo date('M j, Y H:i:s', strtotime(end($vitals_data)['recorded_at'])); ?>
        <?php endif; ?>
    </p>
    
    <?php if (count($vitals_data) > 0): ?>
    <div style="overflow-x: auto;">
        <table style="width: 100%; min-width: 600px;">
            <thead>
                <tr style="background: var(--deep-hospital-blue); color: white;">
                    <th style="padding: 15px; text-align: left;">#</th>
                    <th style="padding: 15px; text-align: left;">Date & Time</th>
                    <th style="padding: 15px; text-align: center;">Blood Pressure</th>
                    <th style="padding: 15px; text-align: center;">Heart Rate</th>
                    <th style="padding: 15px; text-align: center;">Oxygen Level</th>
                    <th style="padding: 15px; text-align: center;">Recorded By</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (array_reverse($vitals_data) as $index => $vital): ?>
                <tr class="<?php echo $vital['recorded_by'] === 'responder' ? 'recorded-by-responder' : 'recorded-by-rescuer'; ?>" 
                    style="border-bottom: 1px solid rgba(169, 183, 198, 0.2);">
                    <td style="padding: 12px; font-weight: 600; color: var(--system-gray);">
                        <?php echo count($vitals_data) - $index; ?>
                    </td>
                    <td style="padding: 12px;">
                        <div style="font-weight: 500;"><?php echo date('M j, Y', strtotime($vital['recorded_at'])); ?></div>
                        <div style="font-size: 12px; color: var(--system-gray);"><?php echo date('H:i:s', strtotime($vital['recorded_at'])); ?></div>
                    </td>
                    <td style="padding: 12px; text-align: center; font-weight: 600; color: var(--medical-cyan);">
                        <?php echo $vital['bp_systolic']; ?>/<?php echo $vital['bp_diastolic']; ?>
                        <div style="font-size: 11px; color: var(--system-gray);">mmHg</div>
                    </td>
                    <td style="padding: 12px; text-align: center; font-weight: 600; color: var(--health-green);">
                        <?php echo $vital['heart_rate']; ?>
                        <div style="font-size: 11px; color: var(--system-gray);">bpm</div>
                    </td>
                    <td style="padding: 12px; text-align: center; font-weight: 600; color: var(--trust-blue);">
                        <?php echo $vital['oxygen_level']; ?>%
                        <div style="font-size: 11px; color: var(--system-gray);">SpO2</div>
                    </td>
                    <td style="padding: 12px; text-align: center;">
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
    
    <?php else: ?>
    <div style="text-align: center; padding: 40px; background: var(--clinical-white); border-radius: var(--radius);">
        <div style="font-size: 48px; margin-bottom: 16px; color: var(--system-gray);">📊</div>
        <h4 style="color: var(--deep-hospital-blue); margin-bottom: 8px;">No Vitals Recorded Yet</h4>
        <p style="color: var(--system-gray);">No vital signs have been recorded for this incident yet.</p>
        <p style="color: var(--system-gray); font-size: 14px; margin-top: 16px;">
            <strong>To add vitals:</strong><br>
            1. Go to Ongoing Monitoring<br>
            2. Click "Start Monitoring" for this incident<br>
            3. Vital signs will be automatically recorded every 2 seconds
        </p>
    </div>
    <?php endif; ?>
</div>

<!-- Action Buttons -->
<div style="text-align: center; margin-top: 30px;">
    <a href="ongoing_monitoring.php" class="btn btn-primary">
        <i class="fa fa-arrow-left"></i> Back to Monitoring
    </a>
    <?php if ($incident && $incident['status'] === 'completed'): ?>
        <a href="generate_case_report.php?id=<?php echo $incident_id; ?>" class="btn btn-warning" style="margin-left: 12px;">
            <i class="fa fa-file-medical"></i> Generate Report
        </a>
    <?php endif; ?>
</div>

<nav class="bottom-nav">
<a href="dashboard.php" class="bottom-item">
    <i class="fa fa-gauge"></i>
    <span>Home</span>
</a>

<a href="transferred_incidents.php" class="bottom-item">
    <i class="fa fa-exclamation-circle"></i>
    <span>Transfer</span>
</a>

<a href="ongoing_monitoring.php" class="bottom-item active">
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

<script>
// Prepare data for charts
const vitalsData = <?php echo json_encode($vitals_data); ?>;
console.log('Vitals data loaded:', vitalsData.length, 'records');

<?php if (count($vitals_data) > 0): ?>
// Extract data for charts (in reverse order to show timeline correctly)
const labels = vitalsData.slice().reverse().map(v => {
    const date = new Date(v.recorded_at);
    return date.toLocaleString('en-US', { 
        month: 'short', 
        day: 'numeric', 
        hour: '2-digit', 
        minute: '2-digit' 
    });
});
const systolicData = vitalsData.slice().reverse().map(v => v.bp_systolic);
const diastolicData = vitalsData.slice().reverse().map(v => v.bp_diastolic);
const heartRateData = vitalsData.slice().reverse().map(v => v.heart_rate);
const oxygenData = vitalsData.slice().reverse().map(v => v.oxygen_level);

console.log('Chart data prepared:', {
    labels: labels.length,
    systolic: systolicData.length,
    heartRate: heartRateData.length,
    oxygen: oxygenData.length
});

// Common chart options
const commonOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            display: true,
            position: 'top'
        }
    },
    scales: {
        x: {
            display: true,
            grid: {
                display: false
            }
        },
        y: {
            display: true,
            grid: {
                color: 'rgba(169, 183, 198, 0.1)'
            }
        }
    }
};

// Blood Pressure Chart
if (document.getElementById('bpChart')) {
    const bpCtx = document.getElementById('bpChart').getContext('2d');
    new Chart(bpCtx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Systolic',
                data: systolicData,
                borderColor: '#0A85CC',
                backgroundColor: 'rgba(10, 133, 204, 0.1)',
                tension: 0.4,
                fill: true,
                borderWidth: 2
            }, {
                label: 'Diastolic',
                data: diastolicData,
                borderColor: '#00B6CC',
                backgroundColor: 'rgba(0, 182, 204, 0.1)',
                tension: 0.4,
                fill: true,
                borderWidth: 2
            }]
        },
        options: {
            ...commonOptions,
            scales: {
                ...commonOptions.scales,
                y: {
                    beginAtZero: false,
                    min: 60,
                    max: 160,
                    title: {
                        display: true,
                        text: 'Blood Pressure (mmHg)'
                    }
                }
            }
        }
    });
    console.log('Blood pressure chart created');
}

// Heart Rate Chart
if (document.getElementById('hrChart')) {
    const hrCtx = document.getElementById('hrChart').getContext('2d');
    new Chart(hrCtx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Heart Rate',
                data: heartRateData,
                borderColor: '#2EDBB3',
                backgroundColor: 'rgba(46, 219, 179, 0.1)',
                tension: 0.4,
                fill: true,
                borderWidth: 2
            }]
        },
        options: {
            ...commonOptions,
            scales: {
                ...commonOptions.scales,
                y: {
                    beginAtZero: false,
                    min: 50,
                    max: 120,
                    title: {
                        display: true,
                        text: 'Heart Rate (bpm)'
                    }
                }
            }
        }
    });
    console.log('Heart rate chart created');
}

// Oxygen Level Chart
if (document.getElementById('o2Chart')) {
    const o2Ctx = document.getElementById('o2Chart').getContext('2d');
    new Chart(o2Ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Oxygen Level',
                data: oxygenData,
                borderColor: '#0A2A55',
                backgroundColor: 'rgba(10, 42, 85, 0.1)',
                tension: 0.4,
                fill: true,
                borderWidth: 2
            }]
        },
        options: {
            ...commonOptions,
            scales: {
                ...commonOptions.scales,
                y: {
                    beginAtZero: false,
                    min: 85,
                    max: 100,
                    title: {
                        display: true,
                        text: 'Oxygen Level (%)'
                    }
                }
            }
        }
    });
    console.log('Oxygen level chart created');
}

<?php else: ?>
console.log('No vitals data available for charts');
<?php endif; ?>

// Add chart resize handler
window.addEventListener('resize', function() {
    setTimeout(function() {
        Chart.helpers.each(Chart.instances, function(instance) {
            instance.resize();
        });
    }, 100);
});
</script>

</body>
</html>
