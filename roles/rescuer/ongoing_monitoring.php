<?php
require_once 'session_check.php';
require_once '../../database/connection.php';

$rescuer_id = $_SESSION['user_id'];
$conn = getDBConnection();

// Get ongoing incidents for this rescuer
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
$stmt->bind_param("i", $rescuer_id);
$stmt->execute();
$ongoing_incidents = $stmt->get_result();

// If specific incident ID is provided, get detailed data
$specific_incident = null;
if (isset($_GET['id'])) {
    $incident_id = $_GET['id'];
    $detail_query = "SELECT i.incident_id, i.start_time, p.pat_name, p.birthdate, p.contact_number,
                    r.resp_name, r.resp_contact
                    FROM incident i 
                    JOIN patient p ON i.pat_id = p.pat_id 
                    JOIN responder r ON i.resp_id = r.resp_id 
                    WHERE i.incident_id = ? AND i.resc_id = ? AND i.status = 'ongoing'";
    $stmt = $conn->prepare($detail_query);
    $stmt->bind_param("ii", $incident_id, $rescuer_id);
    $stmt->execute();
    $specific_incident = $stmt->get_result()->fetch_assoc();
    
    if ($specific_incident) {
        // Get vital statistics for this incident
        $vitals_query = "SELECT bp_systolic, bp_diastolic, heart_rate, oxygen_level, recorded_at, recorded_by 
                        FROM vitalstat 
                        WHERE incident_id = ? 
                        ORDER BY recorded_at DESC";
        $stmt = $conn->prepare($vitals_query);
        $stmt->bind_param("i", $incident_id);
        $stmt->execute();
        $vitals = $stmt->get_result();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ongoing Monitoring - VitalWear</title>
    <link rel="stylesheet" href="../../../assets/css/styles.css">
    <script src="https://kit.fontawesome.com/96e37b53f1.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
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
        .incidents-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .incident-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px;
            transition: transform 0.3s ease;
            cursor: pointer;
        }
        .incident-card:hover {
            transform: translateY(-3px);
        }
        .incident-title {
            font-size: 1.2em;
            font-weight: bold;
            color: #2d3748;
            margin-bottom: 15px;
        }
        .incident-info {
            margin-bottom: 10px;
        }
        .info-label {
            font-weight: 600;
            color: #4a5568;
        }
        .info-value {
            color: #2d3748;
        }
        .monitor-btn {
            background: #48bb78;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin-top: 15px;
            transition: all 0.3s ease;
        }
        .monitor-btn:hover {
            background: #38a169;
            transform: translateY(-2px);
        }
        .btn-secondary {
            background: #ed8936;
            color: white;
        }
        .monitoring-detail {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .charts-container {
            display: grid;
            grid-template-columns: 1fr;
            gap: 30px;
            margin-top: 20px;
        }
        .chart-wrapper {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .chart-wrapper canvas {
            max-height: 300px;
        }
        .monitoring-header {
            background: #f7fafc;
            padding: 20px;
            border-bottom: 1px solid #e2e8f0;
        }
        .monitoring-body {
            padding: 20px;
        }
        .btn-primary {
            background: #48bb78;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            background: #38a169;
            transform: translateY(-2px);
        }
        .btn-danger {
            background: #f56565;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        .btn-danger:hover {
            background: #e53e3e;
            transform: translateY(-2px);
        }
        .vitals-history {
            margin-top: 30px;
        }
        .vitals-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
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
        .recorded-by-responder {
            background: #e6fffa;
        }
        .recorded-by-rescuer {
            background: #f0fff4;
        }
        .no-ongoing {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .no-ongoing h3 {
            color: #718096;
            margin-bottom: 10px;
        }
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>
            <a href="#" onclick="logout()" class="logout-btn">Logout</a>
            <h1>❤️ Ongoing Monitoring</h1>
            <p>Continue monitoring patient vital signs</p>
        </div>

        <?php if ($specific_incident): ?>
            <div class="monitoring-detail">
                <div class="monitoring-header">
                    <h2>Incident #<?php echo $specific_incident['incident_id']; ?> - <?php echo htmlspecialchars($specific_incident['pat_name']); ?></h2>
                    <p>Started: <?php echo date('M j, Y H:i', strtotime($specific_incident['start_time'])); ?></p>
                </div>
                
                <div class="monitoring-body">
                    <div class="patient-info">
                        <h3>Patient Information</h3>
                        <p><strong>Name:</strong> <?php echo htmlspecialchars($specific_incident['pat_name']); ?></p>
                        <p><strong>Age:</strong> <?php echo date('Y') - date('Y', strtotime($specific_incident['birthdate'])); ?> years</p>
                        <p><strong>Contact:</strong> <?php echo htmlspecialchars($specific_incident['contact_number'] ?: 'N/A'); ?></p>
                        <p><strong>Initial Responder:</strong> <?php echo htmlspecialchars($specific_incident['resp_name']); ?></p>
                    </div>

                    <div class="vital-stats">
                        <h3>📈 Current Vital Statistics</h3>
                        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;text-align:center;margin-top:15px;">
                            <div style="background:#fef2f2;padding:10px;border-radius:8px;">
                                <p style="color:#777;font-size:11px;">Heart Rate</p>
                                <p style="font-size:16px;font-weight:bold;color:#e74c3c;" id="currentHR">-</p>
                            </div>
                            <div style="background:#f0fdf4;padding:10px;border-radius:8px;">
                                <p style="color:#777;font-size:11px;">Blood Pressure</p>
                                <p style="font-size:16px;font-weight:bold;color:#22c55e;" id="currentBP">-</p>
                            </div>
                            <div style="background:#eff6ff;padding:10px;border-radius:8px;">
                                <p style="color:#777;font-size:11px;">Oxygen</p>
                                <p style="font-size:16px;font-weight:bold;color:#0ea5e9;" id="currentO2">-</p>
                            </div>
                            <div style="background:#f5f3ff;padding:10px;border-radius:8px;">
                                <p style="color:#777;font-size:11px;">Time</p>
                                <p style="font-size:12px;font-weight:bold;color:#7c3aed;" id="lastVitalTime">-</p>
                            </div>
                        </div>
                        
                        <div style="margin-top:20px;padding-top:15px;border-top:1px solid #eee;">
                            <p style="font-size:14px;font-weight:600;color:#333;margin-bottom:10px;">Statistics:</p>
                            <div style="display:grid;grid-template-columns:repeat(1,1fr);gap:10px;text-align:center;">
                                <div style="background:#f7fafc;padding:10px;border-radius:8px;">
                                    <p style="color:#777;font-size:11px;">Total Readings</p>
                                    <p style="font-size:16px;font-weight:bold;color:#4a5568;" id="totalReadings">-</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="vitals-history">
                        <h3>📈 Vital Signs History</h3>
                        <div class="charts-container">
                            <div class="chart-wrapper">
                                <canvas id="heartRateChart"></canvas>
                            </div>
                            <div class="chart-wrapper">
                                <canvas id="bloodPressureChart"></canvas>
                            </div>
                            <div class="chart-wrapper">
                                <canvas id="oxygenChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="action-buttons">
                        <button id="autoBtn<?php echo $specific_incident['incident_id']; ?>" onclick="toggleAutoInsert(<?php echo $specific_incident['incident_id']; ?>)" style="display:inline-block;margin-right:10px;padding:8px 16px;background:#3b82f6;color:white;border:none;border-radius:6px;font-size:13px;cursor:pointer;"><i class="fa fa-play"></i> Auto Start</button>
                        <a href="complete_incident.php?id=<?php echo $specific_incident['incident_id']; ?>" class="btn-danger" onclick="return confirm('Are you sure you want to complete this incident?')">✅ Complete Incident</a>
                        <a href="ongoing_monitoring.php" class="monitor-btn">← Back to All Incidents</a>
                        <a href="transferred_incidents.php" class="monitor-btn">View Transferred Incidents</a>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <?php if ($ongoing_incidents->num_rows > 0): ?>
                <h2 style="margin-bottom: 20px;">Select Incident to Monitor</h2>
                <div class="incidents-grid">
                    <?php while ($incident = $ongoing_incidents->fetch_assoc()): ?>
                        <div class="incident-card" onclick="window.location.href='ongoing_monitoring.php?id=<?php echo $incident['incident_id']; ?>'">
                            <div class="incident-title">Incident #<?php echo $incident['incident_id']; ?></div>
                            <div class="incident-info">
                                <p><span class="info-label">Patient:</span> <span class="info-value"><?php echo htmlspecialchars($incident['pat_name']); ?></span></p>
                                <p><span class="info-label">Started:</span> <span class="info-value"><?php echo date('M j, Y H:i', strtotime($incident['start_time'])); ?></span></p>
                                <p><span class="info-label">Vital Records:</span> <span class="info-value"><?php echo $incident['vital_count']; ?></span></p>
                            </div>
                            <a href="ongoing_monitoring.php?id=<?php echo $incident['incident_id']; ?>" class="monitor-btn">Continue Monitoring</a>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="no-ongoing">
                    <h3>❤️ No Ongoing Incidents</h3>
                    <p>You have no ongoing incidents to monitor at the moment.</p>
                    <a href="transferred_incidents.php" class="monitor-btn" style="margin-top: 20px;">View Transferred Incidents</a>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <?php require_once 'logout_script.php'; ?>
    
    <script>
// Simulate vital stats and insert into database every 10 seconds
function simulateVitals(incidentId) {
    // Generate realistic random vital values
    // Heart rate: 60-100 bpm
    var heartRate = Math.floor(Math.random() * (100 - 60 + 1)) + 60;
    // Systolic BP: 100-140 mmHg
    var bpSystolic = Math.floor(Math.random() * (140 - 100 + 1)) + 100;
    // Diastolic BP: 60-90 mmHg
    var bpDiastolic = Math.floor(Math.random() * (90 - 60 + 1)) + 60;
    // Oxygen level: 95-100%
    var oxygenLevel = Math.floor(Math.random() * (100 - 95 + 1)) + 95;
    
    // Get current time
    var now = new Date();
    var timeString = now.toLocaleTimeString('en-US', {hour: 'numeric', minute: '2-digit'});
    
    // Insert vitals into database via API
    fetch('add_vitals.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `incident_id=${incidentId}&bp_systolic=${bpSystolic}&bp_diastolic=${bpDiastolic}&heart_rate=${heartRate}&oxygen_level=${oxygenLevel}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            console.log('Vitals recorded successfully');
            // Update the vital display
            updateVitalDisplay(incidentId, heartRate, bpSystolic, bpDiastolic, oxygenLevel, timeString);
        } else {
            console.error('Failed to record vitals:', data.message);
        }
    })
    .catch(error => {
        console.error('Error recording vitals:', error);
    });
}

// Update vital display without LIVE indicator
function updateVitalDisplay(incidentId, heartRate, bpSystolic, bpDiastolic, oxygenLevel, timeString) {
    document.getElementById('currentHR').innerHTML = heartRate + ' bpm';
    document.getElementById('currentBP').innerHTML = bpSystolic + '/' + bpDiastolic;
    document.getElementById('currentO2').innerHTML = oxygenLevel + '%';
    document.getElementById('lastVitalTime').innerHTML = timeString;
    
    // Update total readings
    var totalElement = document.getElementById('totalReadings');
    var currentTotal = parseInt(totalElement.textContent) || 0;
    totalElement.textContent = currentTotal + 1;
    
    // Update charts with new data
    updateCharts({
        heartRate: heartRate,
        bpSystolic: bpSystolic,
        bpDiastolic: bpDiastolic,
        oxygenLevel: oxygenLevel
    });
}

// Store interval IDs for each incident
var simulateIntervals = {};

// Start simulating vitals for an incident
function startSimulation(incidentId) {
    // Clear existing interval if any
    if (simulateIntervals[incidentId]) {
        clearInterval(simulateIntervals[incidentId]);
    }
    
    // Simulate immediately
    simulateVitals(incidentId);
    
    // Then simulate every 10 seconds
    simulateIntervals[incidentId] = setInterval(function() {
        simulateVitals(incidentId);
    }, 10000);
}

// Stop simulating vitals for an incident
function stopSimulation(incidentId) {
    if (simulateIntervals[incidentId]) {
        clearInterval(simulateIntervals[incidentId]);
        delete simulateIntervals[incidentId];
    }
}

// Toggle simulation mode
function toggleAutoInsert(incidentId) {
    var btn = document.getElementById('autoBtn' + incidentId);
    
    if (simulateIntervals[incidentId]) {
        // Stop simulation
        stopSimulation(incidentId);
        btn.innerHTML = '<i class="fa fa-play"></i> Auto Start';
        btn.style.background = '#3b82f6';
        
        // Remove LIVE indicators
        document.getElementById('currentHR').innerHTML = document.getElementById('currentHR').textContent.replace(/ <span.*?<\/span>/, '');
    } else {
        // Start simulation
        startSimulation(incidentId);
        btn.innerHTML = '<i class="fa fa-stop"></i> Stop Live';
        btn.style.background = '#ef4444';
    }
}

// Do not auto-start simulation - user must manually start
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($specific_incident): ?>
        // Load initial vital data
        loadInitialVitals(<?php echo $specific_incident['incident_id']; ?>);
        
        // Auto-start disabled - simulation must be started manually
        // startSimulation(<?php echo $specific_incident['incident_id']; ?>);
        
        // Keep button in initial state
        var btn = document.getElementById('autoBtn<?php echo $specific_incident['incident_id']; ?>');
        if (btn) {
            btn.innerHTML = '<i class="fa fa-play"></i> Auto Start';
            btn.style.background = '#3b82f6';
        }
    <?php endif; ?>
});

// Load initial vital data from database
function loadInitialVitals(incidentId) {
    fetch('get_vital_history.php?id=' + incidentId)
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success' && data.vitals && data.vitals.length > 0) {
                // Update display with latest vital
                const latest = data.vitals[0];
                document.getElementById('currentHR').innerHTML = latest.heart_rate + ' bpm';
                document.getElementById('currentBP').innerHTML = latest.bp_systolic + '/' + latest.bp_diastolic;
                document.getElementById('currentO2').innerHTML = latest.oxygen_level + '%';
                document.getElementById('lastVitalTime').innerHTML = new Date(latest.recorded_at).toLocaleTimeString('en-US', {hour: 'numeric', minute: '2-digit'});
                document.getElementById('totalReadings').textContent = data.vitals.length;
                
                // Initialize charts with historical data
                initializeCharts(data.vitals);
            } else {
                // Initialize empty charts
                initializeCharts([]);
            }
        })
        .catch(error => {
            console.error('Error loading initial vitals:', error);
            initializeCharts([]);
        });
}

// Chart variables
let heartRateChart, bloodPressureChart, oxygenChart;

// Initialize charts with vital data
function initializeCharts(vitals) {
    // Prepare data for charts (reverse to show oldest to newest)
    const sortedVitals = vitals.slice().reverse();
    const labels = sortedVitals.map(v => new Date(v.recorded_at).toLocaleTimeString('en-US', {hour: 'numeric', minute: '2-digit'}));
    const heartRateData = sortedVitals.map(v => v.heart_rate);
    const bpSystolicData = sortedVitals.map(v => v.bp_systolic);
    const bpDiastolicData = sortedVitals.map(v => v.bp_diastolic);
    const oxygenData = sortedVitals.map(v => v.oxygen_level);

    // Heart Rate Chart
    const hrCtx = document.getElementById('heartRateChart').getContext('2d');
    if (heartRateChart) heartRateChart.destroy();
    heartRateChart = new Chart(hrCtx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Heart Rate',
                data: heartRateData,
                borderColor: '#e74c3c',
                backgroundColor: 'rgba(231, 76, 60, 0.1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: true,
                    text: '❤️ Heart Rate (bpm)'
                },
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: false,
                    min: 50,
                    max: 120,
                    title: {
                        display: true,
                        text: 'BPM'
                    }
                }
            }
        }
    });

    // Blood Pressure Chart
    const bpCtx = document.getElementById('bloodPressureChart').getContext('2d');
    if (bloodPressureChart) bloodPressureChart.destroy();
    bloodPressureChart = new Chart(bpCtx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Systolic',
                data: bpSystolicData,
                borderColor: '#22c55e',
                backgroundColor: 'rgba(34, 197, 94, 0.1)',
                tension: 0.4,
                fill: true
            }, {
                label: 'Diastolic',
                data: bpDiastolicData,
                borderColor: '#3b82f6',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: true,
                    text: '🩸 Blood Pressure (mmHg)'
                }
            },
            scales: {
                y: {
                    beginAtZero: false,
                    min: 50,
                    max: 160,
                    title: {
                        display: true,
                        text: 'mmHg'
                    }
                }
            }
        }
    });

    // Oxygen Level Chart
    const o2Ctx = document.getElementById('oxygenChart').getContext('2d');
    if (oxygenChart) oxygenChart.destroy();
    oxygenChart = new Chart(o2Ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Oxygen Level',
                data: oxygenData,
                borderColor: '#0ea5e9',
                backgroundColor: 'rgba(14, 165, 233, 0.1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: true,
                    text: '💨 Oxygen Level (%)'
                },
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: false,
                    min: 85,
                    max: 100,
                    title: {
                        display: true,
                        text: '%'
                    }
                }
            }
        }
    });
}

// Update charts with new vital data
function updateCharts(newVital) {
    if (!heartRateChart || !bloodPressureChart || !oxygenChart) return;

    // Add new data point
    const newLabel = new Date().toLocaleTimeString('en-US', {hour: 'numeric', minute: '2-digit'});
    
    // Update Heart Rate Chart
    heartRateChart.data.labels.push(newLabel);
    heartRateChart.data.datasets[0].data.push(newVital.heartRate);
    if (heartRateChart.data.labels.length > 20) {
        heartRateChart.data.labels.shift();
        heartRateChart.data.datasets[0].data.shift();
    }
    heartRateChart.update();

    // Update Blood Pressure Chart
    bloodPressureChart.data.labels.push(newLabel);
    bloodPressureChart.data.datasets[0].data.push(newVital.bpSystolic);
    bloodPressureChart.data.datasets[1].data.push(newVital.bpDiastolic);
    if (bloodPressureChart.data.labels.length > 20) {
        bloodPressureChart.data.labels.shift();
        bloodPressureChart.data.datasets[0].data.shift();
        bloodPressureChart.data.datasets[1].data.shift();
    }
    bloodPressureChart.update();

    // Update Oxygen Chart
    oxygenChart.data.labels.push(newLabel);
    oxygenChart.data.datasets[0].data.push(newVital.oxygenLevel);
    if (oxygenChart.data.labels.length > 20) {
        oxygenChart.data.labels.shift();
        oxygenChart.data.datasets[0].data.shift();
    }
    oxygenChart.update();
}
</script>
</body>
</html>
