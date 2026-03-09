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

// Get ongoing incidents for this rescuer
$ongoing_incidents = false;
if ($conn && !$conn->connect_error) {
    $ongoing_query = "SELECT i.incident_id, i.start_time, p.pat_name, p.birthdate, p.contact_number,
                      r.resp_name, COUNT(v.vital_id) as vital_count
                      FROM incident i 
                      JOIN patient p ON i.pat_id = p.pat_id 
                      JOIN responder r ON i.resp_id = r.resp_id 
                      LEFT JOIN vitalstat v ON i.incident_id = v.incident_id
                      WHERE i.resc_id = ? AND i.status = 'ongoing' 
                      GROUP BY i.incident_id, i.start_time, p.pat_name, p.birthdate, p.contact_number, r.resp_name
                      ORDER BY i.start_time DESC";
    $stmt = $conn->prepare($ongoing_query);
    if ($stmt) {
        $stmt->bind_param("i", $rescuer_id);
        $stmt->execute();
        $ongoing_incidents = $stmt->get_result();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ongoing Monitoring - VitalWear</title>

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

/* Modern Soft Edge Buttons */
.btn-quick-action {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 12px 20px;
    background: linear-gradient(135deg, var(--medical-cyan) 0%, var(--trust-blue) 100%);
    color: white;
    text-decoration: none;
    border-radius: var(--radius-lg);
    font-weight: 600;
    font-size: 14px;
    box-shadow: var(--shadow);
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-quick-action:hover {
    transform: translateY(-3px);
    box-shadow: var(--shadow-md);
}

.btn-link {
    color: var(--medical-cyan);
    text-decoration: none;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s ease;
}

.btn-link:hover {
    color: var(--trust-blue);
    transform: translateX(4px);
}

/* Status Badges */
.status-badge {
    display: inline-flex;
    align-items: center;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 14px;
    font-weight: 600;
}

.status-ongoing {
    background: rgba(46, 219, 179, 0.15);
    color: var(--health-green);
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

.incident-info h3 {
    color: var(--deep-hospital-blue);
    font-size: 1.25rem;
    margin: 0 0 8px 0;
    font-weight: 700;
}

.incident-info p {
    color: var(--system-gray);
    margin: 4px 0;
    font-size: 14px;
}

.incident-actions {
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

.btn-warning {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
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

.btn-warning:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}

.btn-danger {
    background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
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

.btn-danger:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}

.vital-count {
    font-size: 12px;
    color: var(--system-gray);
    margin-top: 8px;
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
            ❤️
        </div>
        <div>
            <h1 style="color: white; margin: 0; font-size: 1.75rem; font-weight: 700;">Ongoing Monitoring</h1>
            <p style="color: rgba(255,255,255,0.9); margin: 4px 0 0 0; font-size: 1rem;">Track and manage active patient cases</p>
        </div>
    </div>
</div>

<?php if ($ongoing_incidents && $ongoing_incidents->num_rows > 0): ?>
    <?php while ($incident = $ongoing_incidents->fetch_assoc()): ?>
        <div class="incident-card">
            <div class="incident-header">
                <div class="incident-info">
                    <h3>Incident #<?php echo $incident['incident_id']; ?></h3>
                    <p><i class="fa fa-user"></i> Patient: <?php echo htmlspecialchars($incident['pat_name']); ?></p>
                    <p><i class="fa fa-user-md"></i> From: <?php echo htmlspecialchars($incident['resp_name']); ?></p>
                    <p><i class="fa fa-clock"></i> Started: <?php echo date('M j, Y H:i', strtotime($incident['start_time'])); ?></p>
                    <div class="vital-count">
                        <i class="fa fa-heartbeat"></i> <?php echo $incident['vital_count']; ?> vitals recorded
                    </div>
                </div>
                <div style="text-align: right;">
                    <span class="status-badge status-ongoing">
                        <i class="fa fa-circle" style="font-size: 8px; margin-right: 6px;"></i>
                        Ongoing
                    </span>
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
            
            <div class="incident-actions">
                <a href="#" class="btn-primary" onclick="startMonitoring(<?php echo $incident['incident_id']; ?>)">
                    <i class="fa fa-play"></i> Start Monitoring
                </a>
                <a href="case_vitals_history.php?id=<?php echo $incident['incident_id']; ?>" class="btn-secondary">
                    <i class="fa fa-chart-line"></i> View History
                </a>
                <a href="complete_incident.php?id=<?php echo $incident['incident_id']; ?>" class="btn-warning">
                    <i class="fa fa-check"></i> Complete
                </a>
            </div>
        </div>
    <?php endwhile; ?>
<?php else: ?>
    <div class="dashboard-card" style="text-align:center;">
        <div style="font-size:64px;margin-bottom:20px;color:var(--system-gray);">
            ❤️
        </div>
        <h3 style="color:var(--deep-hospital-blue);margin-bottom:12px;">No Ongoing Monitoring</h3>
        <p style="color:var(--system-gray);margin-bottom:24px;">There are no ongoing incidents at the moment.</p>
        <a href="transferred_incidents.php" class="btn-quick-action">
            <i class="fa fa-exclamation-circle"></i> View Transferred Cases
        </a>
    </div>
<?php endif; ?>

</main>

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
let charts = {};
let chartData = {};

function initializeChart(incidentId) {
    console.log('Initializing chart for incident:', incidentId);
    
    const canvas = document.getElementById('chart-' + incidentId);
    if (!canvas) {
        console.error('Canvas not found for incident:', incidentId);
        return;
    }
    
    const ctx = canvas.getContext('2d');
    
    // Initialize data structure with sample data for testing
    if (!chartData[incidentId]) {
        chartData[incidentId] = {
            labels: ['12:00:00', '12:00:05', '12:00:10'],
            heartRate: [72, 75, 73],
            bpSystolic: [120, 122, 118],
            bpDiastolic: [80, 82, 78],
            oxygenLevel: [98, 97, 99]
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
                        fill: true
                    },
                    {
                        label: 'Systolic BP',
                        data: chartData[incidentId].bpSystolic,
                        borderColor: '#00B6CC',
                        backgroundColor: 'rgba(0, 182, 204, 0.1)',
                        tension: 0.4,
                        fill: true
                    },
                    {
                        label: 'Diastolic BP',
                        data: chartData[incidentId].bpDiastolic,
                        borderColor: '#2EDBB3',
                        backgroundColor: 'rgba(46, 219, 179, 0.1)',
                        tension: 0.4,
                        fill: true
                    },
                    {
                        label: 'Oxygen Level',
                        data: chartData[incidentId].oxygenLevel,
                        borderColor: '#0A2A55',
                        backgroundColor: 'rgba(10, 42, 85, 0.1)',
                        tension: 0.4,
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: false,
                        min: 50,
                        max: 180,
                        grid: {
                            drawOnChartArea: false
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
            case 'all':
            default:
                dataset.hidden = false;
                break;
        }
    });
    
    chart.update();
}

function startMonitoring(incidentId) {
    // Change button to stop monitoring
    const button = event.target;
    if (button.classList.contains('btn-primary')) {
        button.innerHTML = '<i class="fa fa-stop"></i> Stop Monitoring';
        button.classList.remove('btn-primary');
        button.classList.add('btn-danger');
        
        // Start real-time updates (simulate with random data)
        startRealTimeUpdates(incidentId);
        
        // Show notification
        showNotification('Monitoring started for Incident #' + incidentId + ' - Saving vital signs to database', 'success');
    } else {
        button.innerHTML = '<i class="fa fa-play"></i> Start Monitoring';
        button.classList.remove('btn-danger');
        button.classList.add('btn-primary');
        
        // Stop real-time updates
        stopRealTimeUpdates(incidentId);
        
        // Show notification
        showNotification('Monitoring stopped for Incident #' + incidentId, 'info');
    }
}

function showNotification(message, type) {
    // Create notification element
    const notification = document.createElement('div');
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 16px 24px;
        border-radius: 12px;
        color: white;
        font-weight: 600;
        z-index: 9999;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        transform: translateX(100%);
        transition: transform 0.3s ease;
    `;
    
    // Set background color based on type
    switch(type) {
        case 'success':
            notification.style.background = 'linear-gradient(135deg, #27ae60 0%, #229954 100%)';
            break;
        case 'info':
            notification.style.background = 'linear-gradient(135deg, #3498db 0%, #2980b9 100%)';
            break;
        case 'error':
            notification.style.background = 'linear-gradient(135deg, #e74c3c 0%, #c0392b 100%)';
            break;
        default:
            notification.style.background = 'linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%)';
    }
    
    notification.textContent = message;
    document.body.appendChild(notification);
    
    // Animate in
    setTimeout(() => {
        notification.style.transform = 'translateX(0)';
    }, 100);
    
    // Remove after 3 seconds
    setTimeout(() => {
        notification.style.transform = 'translateX(100%)';
        setTimeout(() => {
            document.body.removeChild(notification);
        }, 300);
    }, 3000);
}

let monitoringIntervals = {};

function startRealTimeUpdates(incidentId) {
    // Update chart every 2 seconds with simulated data
    monitoringIntervals[incidentId] = setInterval(() => {
        updateChartWithRandomData(incidentId);
    }, 2000);
}

function stopRealTimeUpdates(incidentId) {
    if (monitoringIntervals[incidentId]) {
        clearInterval(monitoringIntervals[incidentId]);
        delete monitoringIntervals[incidentId];
    }
}

function updateChartWithRandomData(incidentId) {
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
    
    // Generate realistic random vital signs
    const heartRate = 65 + Math.floor(Math.random() * 25); // 65-90 bpm
    const bpSystolic = 110 + Math.floor(Math.random() * 30); // 110-140 mmHg
    const bpDiastolic = 70 + Math.floor(Math.random() * 20); // 70-90 mmHg
    const oxygenLevel = 95 + Math.floor(Math.random() * 5); // 95-100%
    
    // Save to database via API
    saveVitalsToDatabase(incidentId, heartRate, bpSystolic, bpDiastolic, oxygenLevel)
        .then(response => {
            if (response.success) {
                console.log('✅ Vitals saved to database successfully');
                // Update chart with saved data
                updateChartData(incidentId, time, heartRate, bpSystolic, bpDiastolic, oxygenLevel);
            } else {
                console.error('❌ Failed to save vitals:', response.message);
                // Show error notification
                showNotification('Failed to save vitals: ' + response.message, 'error');
                // Still update chart even if save fails, so user sees monitoring
                updateChartData(incidentId, time, heartRate, bpSystolic, bpDiastolic, oxygenLevel);
            }
        })
        .catch(error => {
            console.error('❌ Error saving vitals:', error);
            // Show error notification
            showNotification('Error saving vitals to database', 'error');
            // Still update chart even if save fails
            updateChartData(incidentId, time, heartRate, bpSystolic, bpDiastolic, oxygenLevel);
        });
}

function saveVitalsToDatabase(incidentId, heartRate, bpSystolic, bpDiastolic, oxygenLevel) {
    console.log('Saving vitals to database:', {
        incidentId: incidentId,
        heartRate: heartRate,
        bpSystolic: bpSystolic,
        bpDiastolic: bpDiastolic,
        oxygenLevel: oxygenLevel
    });
    
    // Use the original API with database
    return fetch('api/save_vitals.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            incident_id: incidentId,
            heart_rate: heartRate,
            bp_systolic: bpSystolic,
            bp_diastolic: bpDiastolic,
            oxygen_level: oxygenLevel
        })
    })
    .then(response => {
        console.log('API response status:', response.status);
        
        // Check if response is actually JSON
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            console.error('Non-JSON response, content-type:', contentType);
            return response.text().then(text => {
                console.error('Response text:', text);
                throw new Error('Server returned non-JSON response: ' + text.substring(0, 200));
            });
        }
        
        return response.text().then(text => {
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('Failed to parse JSON:', text);
                throw new Error('Invalid JSON response: ' + text.substring(0, 100));
            }
        });
    })
    .then(data => {
        console.log('API response data:', data);
        return data;
    })
    .catch(error => {
        console.error('API call error:', error);
        throw error;
    });
}

function updateChartData(incidentId, time, heartRate, bpSystolic, bpDiastolic, oxygenLevel) {
    // Add new data to chart
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

// Initialize charts when page loads
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($ongoing_incidents && $ongoing_incidents->num_rows > 0): ?>
        <?php 
        // Reset the result pointer to iterate again for chart initialization
        $ongoing_incidents->data_seek(0);
        while ($incident = $ongoing_incidents->fetch_assoc()): 
        ?>
            initializeChart(<?php echo $incident['incident_id']; ?>);
        <?php endwhile; ?>
    <?php endif; ?>
});
</script>

</body>
</html>
