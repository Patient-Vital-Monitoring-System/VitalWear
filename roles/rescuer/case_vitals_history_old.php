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

// Verify incident belongs to this rescuer
$verify_query = "SELECT incident_id FROM incident WHERE incident_id = ? AND resc_id = ?";
$stmt = $conn->prepare($verify_query);
$stmt->bind_param("ii", $incident_id, $rescuer_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: incident_records.php');
    exit();
}

// Get vital statistics
$vitals_query = "SELECT bp_systolic, bp_diastolic, heart_rate, oxygen_level, recorded_at, recorded_by 
                  FROM vitalstat 
                  WHERE incident_id = ? 
                  ORDER BY recorded_at ASC";
$stmt = $conn->prepare($vitals_query);
$stmt->bind_param("i", $incident_id);
$stmt->execute();
$vitals = $stmt->get_result();

// Debug: Show how many records found
$total_vitals = $vitals->num_rows;

// Get incident info for header
$incident_query = "SELECT i.incident_id, i.status, p.pat_name, i.start_time
                  FROM incident i 
                  JOIN patient p ON i.pat_id = p.pat_id 
                  WHERE i.incident_id = ?";
$stmt = $conn->prepare($incident_query);
$stmt->bind_param("i", $incident_id);
$stmt->execute();
$incident = $stmt->get_result()->fetch_assoc();

// Prepare vitals data for charts
$vitals_data = [];
while ($vital = $vitals->fetch_assoc()) {
    $vitals_data[] = $vital;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vitals History - VitalWear</title>
    <link rel="stylesheet" href="../../../assets/css/styles.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
        .chart-container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .chart-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 30px;
            margin-bottom: 30px;
        }
        .chart-box {
            background: #f7fafc;
            padding: 20px;
            border-radius: 8px;
        }
        .chart-title {
            font-weight: 600;
            color: #4a5568;
            margin-bottom: 15px;
            text-align: center;
        }
        .vitals-table {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .vitals-table table {
            width: 100%;
            border-collapse: collapse;
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
        .btn-warning {
            background: #ed8936;
            color: white;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="view_incident_details.php?id=<?php echo $incident_id; ?>" class="back-btn">← Back to Details</a>
            <a href="#" onclick="logout()" class="logout-btn">Logout</a>
            <h1>📊 Vitals History - Incident #<?php echo $incident['incident_id']; ?></h1>
            <p>Patient: <?php echo htmlspecialchars($incident['pat_name']); ?></p>
        </div>

        <div class="chart-container">
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

        <div class="vitals-table">
            <h2>📋 Detailed Vitals Data</h2>
            <p style="background: #e3f2fd; padding: 10px; border-radius: 5px; margin-bottom: 15px;">
                <strong>Debug Info:</strong> Found <?php echo $total_vitals; ?> vital records for Incident #<?php echo $incident_id; ?>
                | Data array count: <?php echo count($vitals_data); ?>
            </p>
            <?php if (count($vitals_data) > 0): ?>
            <table>
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
                    <?php foreach ($vitals_data as $index => $vital): ?>
                    <tr class="<?php echo $vital['recorded_by'] === 'responder' ? 'recorded-by-responder' : 'recorded-by-rescuer'; ?>">
                        <td><?php echo date('M j, Y H:i:s', strtotime($vital['recorded_at'])); ?></td>
                        <td><?php echo $vital['bp_systolic']; ?>/<?php echo $vital['bp_diastolic']; ?></td>
                        <td><?php echo $vital['heart_rate']; ?> bpm</td>
                        <td><?php echo $vital['oxygen_level']; ?>%</td>
                        <td>
                            <?php if ($vital['recorded_by'] === 'responder'): ?>
                                🚑 Responder
                            <?php else: ?>
                                ❤️ Rescuer
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
                <p style="text-align: center; padding: 20px; background: #f8f9fa; border-radius: 8px;">No vital signs recorded yet.</p>
            <?php endif; ?>
        </div>

        <div style="text-align: center; margin-top: 30px;">
            <a href="view_incident_details.php?id=<?php echo $incident_id; ?>" class="btn btn-primary">← Back to Incident Details</a>
            <?php if ($incident['status'] === 'completed'): ?>
                <a href="generate_case_report.php?id=<?php echo $incident_id; ?>" class="btn btn-warning" style="margin-left: 10px;">📄 Generate Report</a>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Debug: Show data count
        console.log('Total vitals data:', <?php echo count($vitals_data); ?>);
        
        // Prepare data for charts
        const vitalsData = <?php echo json_encode($vitals_data); ?>;
        console.log('Vitals data:', vitalsData);
        
        // Extract data for charts
        const labels = vitalsData.map(v => new Date(v.recorded_at).toLocaleString());
        const systolicData = vitalsData.map(v => v.bp_systolic);
        const diastolicData = vitalsData.map(v => v.bp_diastolic);
        const heartRateData = vitalsData.map(v => v.heart_rate);
        const oxygenData = vitalsData.map(v => v.oxygen_level);
        
        console.log('Labels:', labels);
        console.log('Heart Rate Data:', heartRateData);

        // Blood Pressure Chart
        const bpCtx = document.getElementById('bpChart').getContext('2d');
        new Chart(bpCtx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Systolic',
                    data: systolicData,
                    borderColor: '#f56565',
                    backgroundColor: 'rgba(245, 101, 101, 0.1)',
                    tension: 0.4
                }, {
                    label: 'Diastolic',
                    data: diastolicData,
                    borderColor: '#4299e1',
                    backgroundColor: 'rgba(66, 153, 225, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Blood Pressure Over Time'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: false,
                        title: {
                            display: true,
                            text: 'mmHg'
                        }
                    }
                }
            }
        });

        // Heart Rate Chart
        const hrCtx = document.getElementById('hrChart').getContext('2d');
        new Chart(hrCtx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Heart Rate',
                    data: heartRateData,
                    borderColor: '#48bb78',
                    backgroundColor: 'rgba(72, 187, 120, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Heart Rate Over Time'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: false,
                        title: {
                            display: true,
                            text: 'bpm'
                        }
                    }
                }
            }
        });

        // Oxygen Level Chart
        const o2Ctx = document.getElementById('o2Chart').getContext('2d');
        new Chart(o2Ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Oxygen Level',
                    data: oxygenData,
                    borderColor: '#ed8936',
                    backgroundColor: 'rgba(237, 137, 54, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Oxygen Level Over Time'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: false,
                        min: 80,
                        max: 100,
                        title: {
                            display: true,
                            text: '%'
                        }
                    }
                }
            }
        });
    </script>
    
    <?php require_once 'logout_script.php'; ?>
</body>
</html>
