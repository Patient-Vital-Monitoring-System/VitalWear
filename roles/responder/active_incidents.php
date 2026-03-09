<?php 
include("../../database/connection.php");
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'responder') {
    header("Location: ../../login.html");
    exit;
}

$responder_id = $_SESSION['user_id'];

// Get responder info
$responder_query = "SELECT resp_name FROM responder WHERE resp_id = ?";
$resp_stmt = $conn->prepare($responder_query);
$resp_stmt->bind_param("i", $responder_id);
$resp_stmt->execute();
$responder_info = $resp_stmt->get_result()->fetch_assoc();

// Get active incidents with latest vitals
$stmt = $conn->prepare("
    SELECT i.incident_id, i.status, i.start_time, p.pat_name, p.pat_id,
           (SELECT v.heart_rate FROM vitalstat v WHERE v.incident_id = i.incident_id ORDER BY v.recorded_at DESC LIMIT 1) as heart_rate,
           (SELECT v.bp_systolic FROM vitalstat v WHERE v.incident_id = i.incident_id ORDER BY v.recorded_at DESC LIMIT 1) as bp_systolic,
           (SELECT v.bp_diastolic FROM vitalstat v WHERE v.incident_id = i.incident_id ORDER BY v.recorded_at DESC LIMIT 1) as bp_diastolic,
           (SELECT v.oxygen_level FROM vitalstat v WHERE v.incident_id = i.incident_id ORDER BY v.recorded_at DESC LIMIT 1) as oxygen_level,
           (SELECT v.recorded_at FROM vitalstat v WHERE v.incident_id = i.incident_id ORDER BY v.recorded_at DESC LIMIT 1) as last_vital_time,
           (SELECT COUNT(*) FROM vitalstat v WHERE v.incident_id = i.incident_id) as vital_count
    FROM incident i
    JOIN patient p ON i.pat_id = p.pat_id
    WHERE i.resp_id = ? AND i.status = 'ongoing'
    ORDER BY i.start_time DESC
");
$stmt->bind_param("i", $responder_id);
$stmt->execute();
$incidents = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Active Incidents - VitalWear</title>
<link rel="stylesheet" href="../../assets/css/styles.css">
<script src="https://kit.fontawesome.com/96e37b53f1.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
/* VitalWear Branding Color Palette with Soft UI */
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
    border-bottom: 1px solid rgba(169, 183, 198, 0.2);
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

#sidebar a.active {
    background: linear-gradient(135deg, var(--medical-cyan) 0%, var(--trust-blue) 100%);
    color: white;
    box-shadow: var(--shadow-sm);
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

/* Soft UI Cards */
.incident-card {
    background: var(--surface);
    padding: 24px;
    border-radius: var(--radius-lg);
    margin: 16px 0;
    box-shadow: var(--shadow);
    border: 1px solid rgba(169, 183, 198, 0.2);
    overflow: visible;
}

.vital-card {
    background: var(--clinical-white);
    padding: 20px;
    border-radius: var(--radius);
    text-align: center;
    border: 1px solid rgba(169, 183, 198, 0.2);
    box-shadow: var(--shadow-sm);
    transition: all 0.2s ease;
}

.vital-card:hover {
    box-shadow: var(--shadow);
    transform: translateY(-2px);
}

.vital-value {
    font-size: 20px;
    font-weight: 700;
    margin: 8px 0;
    color: var(--deep-hospital-blue);
}

/* Soft UI Buttons */
.btn-start {
    background: linear-gradient(135deg, var(--medical-cyan) 0%, var(--trust-blue) 100%);
    color: white;
    border: none;
    padding: 14px 28px;
    border-radius: var(--radius);
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    box-shadow: var(--shadow);
    transition: all 0.3s ease;
    display: inline-block;
    white-space: nowrap;
}

.btn-start:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}

.btn-stop {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: white;
    border: none;
    padding: 14px 28px;
    border-radius: var(--radius);
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    box-shadow: var(--shadow);
    transition: all 0.3s ease;
    display: inline-block;
    white-space: nowrap;
}

.btn-stop:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}

/* Soft UI Chart */
.chart-container {
    background: var(--surface);
    padding: 24px;
    border-radius: var(--radius-lg);
    margin: 24px 0;
    box-shadow: var(--shadow);
    min-height: 380px;
    border: 1px solid rgba(169, 183, 198, 0.2);
}

.chart-container canvas {
    width: 100% !important;
    height: 280px !important;
}

.chart-tabs {
    display: flex;
    gap: 12px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.chart-tab {
    padding: 10px 20px;
    background: var(--clinical-white);
    border: 1px solid rgba(169, 183, 198, 0.3);
    border-radius: var(--radius);
    cursor: pointer;
    transition: all 0.2s ease;
    color: var(--deep-hospital-blue);
    font-weight: 500;
}

.chart-tab:hover {
    background: rgba(0, 182, 204, 0.1);
    border-color: var(--medical-cyan);
}

.chart-tab.active {
    background: linear-gradient(135deg, var(--medical-cyan) 0%, var(--trust-blue) 100%);
    color: white;
    border-color: transparent;
    box-shadow: var(--shadow-sm);
}

h2, h3, h4 {
    color: var(--deep-hospital-blue);
    font-weight: 700;
}

h2 {
    font-size: 1.75rem;
    margin-bottom: 1.5rem;
}

h3 {
    font-size: 1.25rem;
    margin-bottom: 1rem;
}

/* Message styling */
#message {
    border-radius: var(--radius);
    font-weight: 500;
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
            <span><?php echo htmlspecialchars($responder_info['resp_name'] ?? 'Responder'); ?></span>
        </div>
    </div>
</header>

<nav id="sidebar">
<div class="sidebar-logo">
    <img src="../../assets/logo.png" alt="VitalWear Logo">
</div>
<a href="dashboard.php"><i class="fa fa-gauge"></i> Dashboard</a>
<a href="device.php"><i class="fa fa-tablet"></i> My Device</a>
<a href="active_incidents.php"><i class="fa fa-exclamation-circle"></i> Active Incidents</a>
<a href="create_incident.php"><i class="fa fa-plus-circle"></i> Create Incident</a>
<a href="transfer_incident.php"><i class="fa fa-exchange"></i> Transfer to Rescuer</a>
<a href="incident_history.php"><i class="fa fa-history"></i> Incident History</a>
<a href="../../api/auth/logout.php"><i class="fa fa-sign-out"></i> Logout</a>
</nav>

<main class="container" style="display:block;overflow-y:auto;width:100%;">

<h2 style="color: var(--deep-hospital-blue); margin-bottom: 24px; font-weight: 700; font-size: 1.75rem;">🚨 Active Incidents</h2>

<!-- AJAX Message Container -->
<div id="message" style="display:none;padding:10px;border-radius:5px;margin:10px 0;"></div>

<?php if($incidents->num_rows > 0): ?>
    <?php while($incident = $incidents->fetch_assoc()): ?>
    <div class="incident-card">
        <div style="margin-bottom: 20px; padding-bottom: 16px; border-bottom: 1px solid rgba(169, 183, 198, 0.2);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                <h3 style="margin: 0; font-size: 1.25rem;">Incident #<?php echo $incident['incident_id']; ?></h3>
                <span class="status-badge status-ongoing"><?php echo ucfirst($incident['status']); ?></span>
            </div>
            <p style="color: var(--system-gray); font-size: 14px; margin: 4px 0;"><i class="fa fa-user" style="width: 20px; color: var(--medical-cyan);"></i> <strong>Patient:</strong> <?php echo htmlspecialchars($incident['pat_name']); ?></p>
            <p style="color: var(--system-gray); font-size: 14px; margin: 4px 0;"><i class="fa fa-clock" style="width: 20px; color: var(--medical-cyan);"></i> <strong>Started:</strong> <?php echo date('M d, Y h:i A', strtotime($incident['start_time'])); ?></p>
        </div>
        
        <div style="text-align:center;margin-bottom:20px;">
            <button id="btn-<?php echo $incident['incident_id']; ?>" class="btn-start" onclick="toggleMonitoring(<?php echo $incident['incident_id']; ?>)" style="white-space:nowrap;overflow:visible;">
                <i class="fa fa-play"></i> Start Live Monitoring
            </button>
        </div>
        
        <div id="vitals-<?php echo $incident['incident_id']; ?>">
            <h4 style="color: var(--deep-hospital-blue); margin-bottom: 16px; font-size: 1.1rem;"><i class="fa fa-heartbeat" style="color: var(--medical-cyan);"></i> Vitals Monitor</h4>
            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px;">
                <div class="vital-card">
                    <div style="color: var(--system-gray); font-size: 12px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px;">Heart Rate</div>
                    <div class="vital-value" id="hr-<?php echo $incident['incident_id']; ?>" style="color: #0A85CC;">
                        <?php echo $incident['heart_rate'] > 0 ? $incident['heart_rate'] : '--'; ?><span style="font-size: 14px; color: var(--system-gray);"> bpm</span>
                    </div>
                </div>
                <div class="vital-card">
                    <div style="color: var(--system-gray); font-size: 12px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px;">Blood Pressure</div>
                    <div class="vital-value" id="bp-<?php echo $incident['incident_id']; ?>" style="color: #00B6CC; font-size: 18px;">
                        <?php echo ($incident['bp_systolic'] > 0 ? $incident['bp_systolic'] : '--') . '/' . ($incident['bp_diastolic'] > 0 ? $incident['bp_diastolic'] : '--'); ?>
                    </div>
                </div>
                <div class="vital-card">
                    <div style="color: var(--system-gray); font-size: 12px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px;">Oxygen Level</div>
                    <div class="vital-value" id="o2-<?php echo $incident['incident_id']; ?>" style="color: #0A2A55;">
                        <?php echo $incident['oxygen_level'] > 0 ? $incident['oxygen_level'] : '--'; ?><span style="font-size: 14px; color: var(--system-gray);">%</span>
                    </div>
                </div>
                <div class="vital-card">
                    <div style="color: var(--system-gray); font-size: 12px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px;">Last Update</div>
                    <div class="vital-value" id="time-<?php echo $incident['incident_id']; ?>" style="color: #2EDBB3; font-size: 16px;">
                        <?php echo $incident['last_vital_time'] ? date('h:i A', strtotime($incident['last_vital_time'])) : '--:--'; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Vitals Chart -->
        <div class="chart-container">
            <h4 style="margin-bottom: 15px;">📈 Vitals Trend</h4>
            <div class="chart-tabs">
                <div class="chart-tab active" onclick="switchChart(<?php echo $incident['incident_id']; ?>, 'all', this)">All Vitals</div>
                <div class="chart-tab" onclick="switchChart(<?php echo $incident['incident_id']; ?>, 'heartRate', this)">Heart Rate</div>
                <div class="chart-tab" onclick="switchChart(<?php echo $incident['incident_id']; ?>, 'bloodPressure', this)">Blood Pressure</div>
                <div class="chart-tab" onclick="switchChart(<?php echo $incident['incident_id']; ?>, 'oxygen', this)">Oxygen Level</div>
            </div>
            <div style="border: 2px solid #e9ecef; border-radius: 8px; padding: 10px; background: #f8f9fa;">
                <canvas id="chart-<?php echo $incident['incident_id']; ?>" style="background: white;"></canvas>
            </div>
        </div>
    </div>
    <?php endwhile; ?>
<?php else: ?>
    <div class="dashboard-card" style="text-align: center; padding: 48px 24px;">
        <div style="width: 80px; height: 80px; background: var(--clinical-white); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px;">
            <i class="fa fa-clipboard-check" style="font-size: 36px; color: var(--system-gray);"></i>
        </div>
        <p style="color: var(--system-gray); font-size: 18px; margin-bottom: 20px;">No active incidents</p>
        <a href="create_incident.php" class="btn-quick-action" style="width: auto; display: inline-block;">
            <i class="fa fa-plus"></i> Create Incident
        </a>
    </div>
<?php endif; ?>

</main>

<nav class="bottom-nav">
<a href="dashboard.php" class="bottom-item"><i class="fa fa-gauge"></i><span>Home</span></a>
<a href="device.php" class="bottom-item"><i class="fa fa-tablet"></i><span>Device</span></a>
<a href="create_incident.php" class="bottom-item"><i class="fa fa-plus-circle"></i><span>Incident</span></a>
<a href="incident_history.php" class="bottom-item"><i class="fa fa-history"></i><span>History</span></a>
<a href="../../api/auth/logout.php"><i class="fa fa-sign-out"></i></a>
</nav>

<script>
let monitoringIntervals = {};
let charts = {};
let chartData = {};

function showMessage(text, type) {
    const msgDiv = document.getElementById('message');
    msgDiv.style.display = 'block';
    msgDiv.innerHTML = text;
    msgDiv.style.background = type === 'success' ? '#d4edda' : '#f8d7da';
    msgDiv.style.color = type === 'success' ? '#155724' : '#721c24';
    setTimeout(() => msgDiv.style.display = 'none', 3000);
}

function toggleMonitoring(incidentId) {
    const btn = document.getElementById('btn-' + incidentId);
    
    if (monitoringIntervals[incidentId]) {
        // Stop monitoring
        clearInterval(monitoringIntervals[incidentId]);
        delete monitoringIntervals[incidentId];
        btn.innerHTML = '<i class="fa fa-play"></i> Start Live Monitoring';
        btn.className = 'btn-start';
        showMessage('Live monitoring stopped', 'success');
    } else {
        // Start monitoring
        btn.innerHTML = '<i class="fa fa-stop"></i> Stop Monitoring';
        btn.className = 'btn-stop';
        showMessage('Live monitoring started', 'success');
        
        // Record immediately
        recordVitals(incidentId);
        
        // Then record every 5 seconds
        monitoringIntervals[incidentId] = setInterval(() => recordVitals(incidentId), 5000);
    }
}

function initializeChart(incidentId) {
    console.log('Initializing chart for incident:', incidentId);
    
    const canvas = document.getElementById('chart-' + incidentId);
    if (!canvas) {
        console.error('Canvas not found for incident:', incidentId);
        return;
    }
    
    const ctx = canvas.getContext('2d');
    if (!ctx) {
        console.error('Could not get context for incident:', incidentId);
        return;
    }
    
    // Initialize data structure with sample data for testing
    if (!chartData[incidentId]) {
        chartData[incidentId] = {
            labels: ['12:00:00', '12:00:05', '12:00:10'],
            heartRate: [72, 75, 73],
            bpSystolic: [120, 122, 118],
            bpDiastolic: [80, 82, 78],
            oxygenLevel: [98, 97, 98]
        };
    }
    
    try {
        charts[incidentId] = new Chart(ctx, {
            type: 'line',
            data: {
                labels: chartData[incidentId].labels,
                datasets: [
                    {
                        label: 'Heart Rate',
                        data: chartData[incidentId].heartRate,
                        borderColor: '#0A85CC',
                        backgroundColor: 'rgba(10, 133, 204, 0.1)',
                        tension: 0.4,
                        yAxisID: 'y',
                        borderWidth: 2
                    },
                    {
                        label: 'Systolic BP',
                        data: chartData[incidentId].bpSystolic,
                        borderColor: '#00B6CC',
                        backgroundColor: 'rgba(0, 182, 204, 0.1)',
                        tension: 0.4,
                        yAxisID: 'y1',
                        borderWidth: 2
                    },
                    {
                        label: 'Diastolic BP',
                        data: chartData[incidentId].bpDiastolic,
                        borderColor: '#2EDBB3',
                        backgroundColor: 'rgba(46, 219, 179, 0.1)',
                        tension: 0.4,
                        yAxisID: 'y1',
                        borderWidth: 2
                    },
                    {
                        label: 'Oxygen Level',
                        data: chartData[incidentId].oxygenLevel,
                        borderColor: '#0A2A55',
                        backgroundColor: 'rgba(10, 42, 85, 0.1)',
                        tension: 0.4,
                        yAxisID: 'y2',
                        borderWidth: 2
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    }
                },
                scales: {
                    x: {
                        display: true,
                        title: {
                            display: true,
                            text: 'Time'
                        }
                    },
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Heart Rate (bpm)'
                        },
                        min: 50,
                        max: 120
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Blood Pressure (mmHg)'
                        },
                        min: 50,
                        max: 180,
                        grid: {
                            drawOnChartArea: false,
                        }
                    }
                }
            }
        });
        console.log('Chart successfully created for incident:', incidentId);
    } catch (error) {
        console.error('Error creating chart for incident:', incidentId, error);
    }
}

function switchChart(incidentId, type, tabElement) {
    // Update tab styles
    const container = tabElement.parentElement;
    const tabs = container.querySelectorAll('.chart-tab');
    tabs.forEach(tab => tab.classList.remove('active'));
    tabElement.classList.add('active');
    
    // Update chart datasets visibility
    const chart = charts[incidentId];
    if (!chart) return;
    
    chart.data.datasets.forEach((dataset, index) => {
        switch(type) {
            case 'heartRate':
                dataset.hidden = index !== 0;
                break;
            case 'bloodPressure':
                dataset.hidden = index !== 1 && index !== 2;
                break;
            case 'oxygen':
                dataset.hidden = index !== 3;
                break;
            default: // all
                dataset.hidden = false;
        }
    });
    
    chart.update();
}

function updateChart(incidentId, heartRate, bpSystolic, bpDiastolic, oxygenLevel) {
    if (!chartData[incidentId]) {
        chartData[incidentId] = {
            labels: [],
            heartRate: [],
            bpSystolic: [],
            bpDiastolic: [],
            oxygenLevel: []
        };
    }
    
    const time = new Date().toLocaleTimeString('en-US', {hour: '2-digit', minute: '2-digit', second: '2-digit'});
    
    // Add new data
    chartData[incidentId].labels.push(time);
    chartData[incidentId].heartRate.push(heartRate);
    chartData[incidentId].bpSystolic.push(bpSystolic);
    chartData[incidentId].bpDiastolic.push(bpDiastolic);
    chartData[incidentId].oxygenLevel.push(oxygenLevel);
    
    // Keep only last 20 data points
    if (chartData[incidentId].labels.length > 20) {
        chartData[incidentId].labels.shift();
        chartData[incidentId].heartRate.shift();
        chartData[incidentId].bpSystolic.shift();
        chartData[incidentId].bpDiastolic.shift();
        chartData[incidentId].oxygenLevel.shift();
    }
    
    // Update chart
    if (charts[incidentId]) {
        charts[incidentId].data.labels = chartData[incidentId].labels;
        charts[incidentId].data.datasets[0].data = chartData[incidentId].heartRate;
        charts[incidentId].data.datasets[1].data = chartData[incidentId].bpSystolic;
        charts[incidentId].data.datasets[2].data = chartData[incidentId].bpDiastolic;
        charts[incidentId].data.datasets[3].data = chartData[incidentId].oxygenLevel;
        charts[incidentId].update('none'); // Update without animation for smooth real-time updates
    }
}

function recordVitals(incidentId) {
    // Generate realistic vital values
    const heartRate = Math.floor(Math.random() * (100 - 60 + 1)) + 60;
    const bpSystolic = Math.floor(Math.random() * (140 - 100 + 1)) + 100;
    const bpDiastolic = Math.floor(Math.random() * (90 - 60 + 1)) + 60;
    const oxygenLevel = Math.floor(Math.random() * (100 - 95 + 1)) + 95;
    
    // Send to database
    fetch('../../api/vitals/record_vitals.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `incident_id=${incidentId}&bp_systolic=${bpSystolic}&bp_diastolic=${bpDiastolic}&heart_rate=${heartRate}&oxygen_level=${oxygenLevel}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update display
            document.getElementById('hr-' + incidentId).textContent = heartRate + ' bpm';
            document.getElementById('bp-' + incidentId).textContent = bpSystolic + '/' + bpDiastolic;
            document.getElementById('o2-' + incidentId).textContent = oxygenLevel + '%';
            document.getElementById('time-' + incidentId).textContent = new Date().toLocaleTimeString('en-US', {hour: '2-digit', minute: '2-digit'});
            
            // Update chart
            updateChart(incidentId, heartRate, bpSystolic, bpDiastolic, oxygenLevel);
        } else {
            showMessage('Failed to record vitals: ' + data.message, 'error');
        }
    })
    .catch(error => {
        showMessage('Error: ' + error, 'error');
    });
}

// Initialize charts and buttons on page load
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, checking Chart.js...');
    
    // Check if Chart.js is loaded
    if (typeof Chart === 'undefined') {
        console.error('Chart.js is not loaded!');
        return;
    } else {
        console.log('Chart.js is loaded successfully');
    }
    
    <?php if($incidents->num_rows > 0): ?>
        <?php 
        $incidents->data_seek(0);
        while($inc = $incidents->fetch_assoc()): 
        ?>
        console.log('Setting up incident <?php echo $inc['incident_id']; ?>');
        
        // Initialize button
        const btn<?php echo $inc['incident_id']; ?> = document.getElementById('btn-<?php echo $inc['incident_id']; ?>');
        if (btn<?php echo $inc['incident_id']; ?>) {
            btn<?php echo $inc['incident_id']; ?>.innerHTML = '<i class="fa fa-play"></i> Start Live Monitoring';
            btn<?php echo $inc['incident_id']; ?>.style.background = '#3b82f6';
        }
        
        // Initialize chart after a small delay to ensure DOM is ready
        setTimeout(() => {
            initializeChart(<?php echo $inc['incident_id']; ?>);
        }, 100);
        <?php endwhile; ?>
    <?php else: ?>
        console.log('No incidents found');
    <?php endif; ?>
});
</script>

</body>
</html>
