<?php
require_once '../../database/connection.php';

// Get average BP this month
$avgBpQuery = $conn->prepare("
    SELECT 
        AVG(bp_systolic) as avg_systolic,
        AVG(bp_diastolic) as avg_diastolic,
        COUNT(*) as total_readings
    FROM vitalstat 
    WHERE MONTH(recorded_at) = MONTH(CURDATE()) 
    AND YEAR(recorded_at) = YEAR(CURDATE())
");
$avgBpQuery->execute();
$avgBp = $avgBpQuery->get_result()->fetch_assoc();

// Get high BP incidents this month
$highBpQuery = $conn->prepare("
    SELECT COUNT(*) as high_bp_count
    FROM vitalstat
    WHERE (bp_systolic > 140 OR bp_diastolic > 90)
    AND MONTH(recorded_at) = MONTH(CURDATE())
    AND YEAR(recorded_at) = YEAR(CURDATE())
");
$highBpQuery->execute();
$highBpData = $highBpQuery->get_result()->fetch_assoc();
$highBpCount = $highBpData['high_bp_count'];

// Get peak hour based on vital readings
$peakHourQuery = $conn->prepare("
    SELECT 
        HOUR(recorded_at) AS peak_hour,
        COUNT(*) AS total
    FROM vitalstat
    WHERE MONTH(recorded_at) = MONTH(CURDATE())
      AND YEAR(recorded_at) = YEAR(CURDATE())
    GROUP BY HOUR(recorded_at)
    ORDER BY total DESC
    LIMIT 1
");
$peakHourQuery->execute();
$peakHourData = $peakHourQuery->get_result()->fetch_assoc();
$peakHour = $peakHourData ? (int)$peakHourData['peak_hour'] : null;

// Get monitoring frequency
$monitoringFreqQuery = $conn->prepare("
    SELECT 
        COUNT(*) as total_readings,
        COUNT(DISTINCT DATE(recorded_at)) as days_monitored,
        ROUND(COUNT(*) / COUNT(DISTINCT DATE(recorded_at)), 1) as readings_per_day
    FROM vitalstat
    WHERE MONTH(recorded_at) = MONTH(CURDATE())
    AND YEAR(recorded_at) = YEAR(CURDATE())
");
$monitoringFreqQuery->execute();
$monitoringFreq = $monitoringFreqQuery->get_result()->fetch_assoc();

// Get BP trend by week
$bpTrendQuery = $conn->prepare("
    SELECT 
        WEEK(recorded_at) as week_num,
        AVG(bp_systolic) as avg_systolic,
        AVG(bp_diastolic) as avg_diastolic
    FROM vitalstat
    WHERE YEAR(recorded_at) = YEAR(CURDATE())
    GROUP BY WEEK(recorded_at)
    ORDER BY WEEK(recorded_at) DESC
    LIMIT 5
");
$bpTrendQuery->execute();
$bpTrends = array_reverse($bpTrendQuery->get_result()->fetch_all(MYSQLI_ASSOC));

// Get distribution of vital readings by hour
$incidentHourlyQuery = $conn->prepare("
    SELECT 
        HOUR(recorded_at) as hour,
        COUNT(*) as incident_count
    FROM vitalstat
    WHERE YEAR(recorded_at) = YEAR(CURDATE())
      AND MONTH(recorded_at) = MONTH(CURDATE())
    GROUP BY HOUR(recorded_at)
    ORDER BY hour ASC
");
$incidentHourlyQuery->execute();
$incidentHourly = $incidentHourlyQuery->get_result()->fetch_all(MYSQLI_ASSOC);

// Get vital signs distribution
$vitalDistQuery = $conn->prepare("
    SELECT 
        COUNT(CASE WHEN bp_systolic <= 120 AND bp_diastolic <= 80 THEN 1 END) as normal,
        COUNT(CASE WHEN (bp_systolic > 120 AND bp_systolic <= 140) OR (bp_diastolic > 80 AND bp_diastolic <= 90) THEN 1 END) as elevated,
        COUNT(CASE WHEN bp_systolic > 140 OR bp_diastolic > 90 THEN 1 END) as high
    FROM vitalstat
    WHERE MONTH(recorded_at) = MONTH(CURDATE())
    AND YEAR(recorded_at) = YEAR(CURDATE())
");
$vitalDistQuery->execute();
$vitalDist = $vitalDistQuery->get_result()->fetch_assoc();

// Get detailed vital statistics table
$vitalTableQuery = $conn->prepare("
    SELECT 
        p.pat_name,
        v.bp_systolic,
        v.bp_diastolic,
        v.heart_rate,
        v.oxygen_level,
        v.recorded_at,
        CASE 
            WHEN v.bp_systolic <= 120 AND v.bp_diastolic <= 80 THEN 'Normal'
            WHEN (v.bp_systolic > 120 AND v.bp_systolic <= 140) OR (v.bp_diastolic > 80 AND v.bp_diastolic <= 90) THEN 'Elevated'
            WHEN v.bp_systolic > 140 OR v.bp_diastolic > 90 THEN 'High'
        END as bp_status
    FROM vitalstat v
    JOIN incident i ON v.incident_id = i.incident_id
    JOIN patient p ON i.pat_id = p.pat_id
    INNER JOIN (
        SELECT 
            i2.pat_id,
            MAX(v2.recorded_at) AS latest_recorded_at
        FROM vitalstat v2
        JOIN incident i2 ON v2.incident_id = i2.incident_id
        WHERE MONTH(v2.recorded_at) = MONTH(CURDATE())
          AND YEAR(v2.recorded_at) = YEAR(CURDATE())
        GROUP BY i2.pat_id
    ) lv ON i.pat_id = lv.pat_id
       AND v.recorded_at = lv.latest_recorded_at
    WHERE MONTH(v.recorded_at) = MONTH(CURDATE())
      AND YEAR(v.recorded_at) = YEAR(CURDATE())
    ORDER BY v.recorded_at DESC
    LIMIT 50
");
$vitalTableQuery->execute();
$vitalTableData = $vitalTableQuery->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vital Statistics Analytics - Admin</title>
    <link rel="stylesheet" href="../../assets/css/styles.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --bg: #0a0e1a;
            --surface: #111827;
            --surface2: #1a2235;
            --border: #1f2d45;
            --accent: #00e5ff;
            --accent2: #ff4d6d;
            --accent3: #39ff14;
            --text: #e2e8f0;
            --muted: #64748b;
            --warn: #f59e0b;
            --danger: #ef4444;
            --success: #10b981;
        }

        * { margin:0; padding:0; box-sizing:border-box; }

        body {
            font-family:'Syne',sans-serif;
            background:var(--bg);
            color:var(--text);
            min-height:100vh;
            overflow-x:hidden;
            display: flex;
            flex-direction: column;
        }

        .navbar-top {
            position: sticky;
            top: 0;
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            color: white;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
            z-index: 1030;
        }

        .page-wrapper {
            display: flex;
            flex: 1;
            min-height: calc(100vh - 70px);
        }

        .sidebar {
            width: 320px;
            background-color: var(--surface);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            min-height: 100%;
            overflow-y: auto;
        }

        .sidebar-header {
            background-color: var(--surface2);
            color: white;
            border-bottom: 1px solid var(--border);
            padding: 24px;
        }

        .sidebar-title {
            font-weight: 700;
            font-size: 1.3rem;
            color: var(--accent);
            letter-spacing: 1px;
            font-family: 'Space Mono', monospace;
        }

        .sidebar-nav {
            display: flex;
            flex-direction: column;
            padding: 0;
            margin: 0;
            flex: 1;
        }

        .sidebar-nav .nav-link {
            color: var(--muted);
            padding: 18px 24px;
            border-left: 3px solid transparent;
            transition: all 0.3s ease;
            text-decoration: none;
            display: block;
            font-weight: 600;
            font-size: 14px;
        }

        .sidebar-nav .nav-link:hover {
            background-color: rgba(0, 229, 255, 0.1);
            color: var(--accent);
            border-left-color: var(--accent);
        }

        .sidebar-nav .nav-link.active {
            color: var(--accent);
            background-color: rgba(0, 229, 255, 0.15);
            border-left-color: var(--accent);
        }

        .nav-group {
            display: flex;
            flex-direction: column;
        }

        .nav-group-toggle {
            color: var(--muted);
            padding: 18px 24px;
            border-left: 3px solid transparent;
            border: none;
            background: transparent;
            transition: all 0.3s ease;
            text-decoration: none;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            width: 100%;
            text-align: left;
            font-family: inherit;
        }

        .nav-group-toggle:hover {
            background-color: rgba(0, 229, 255, 0.1);
            color: var(--accent);
            border-left-color: var(--accent);
        }

        .dropdown-arrow {
            transition: transform 0.3s ease;
            display: inline-block;
            font-size: 12px;
        }

        .nav-group.active .dropdown-arrow {
            transform: rotate(180deg);
        }

        .nav-group-items {
            display: none;
            flex-direction: column;
            background-color: rgba(0, 0, 0, 0.2);
            border-left: 3px solid var(--border);
        }

        .nav-group.active .nav-group-items {
            display: flex;
        }

        .nav-group .nav-link {
            padding: 14px 24px 14px 48px;
            border-left: none;
            font-size: 13px;
            color: var(--muted);
        }

        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
        }

        .navbar-brand {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 700;
            color: white;
            letter-spacing: -0.5px;
            flex: 1;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 32px 20px;
        }

        h1 {
            color: var(--accent);
            font-weight: 800;
            margin-bottom: 16px;
            margin-top: 0;
            font-size: 2rem;
            letter-spacing: -0.5px;
        }

        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .metric-card {
            background: var(--surface);
            border: 2px solid var(--border);
            border-radius: 12px;
            padding: 24px;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }

        .metric-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--accent), var(--accent2), var(--accent3));
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .metric-card:hover {
            border-color: var(--accent);
            box-shadow: 0 8px 24px rgba(0, 229, 255, 0.15);
            transform: translateY(-4px);
        }

        .metric-card:hover::before {
            opacity: 1;
        }

        .metric-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            margin-bottom: 16px;
        }

        .metric-header h4 {
            margin: 0;
            font-size: 16px;
            color: var(--muted);
        }

        .metric-icon {
            font-size: 28px;
            color: var(--accent);
        }

        .metric-value {
            font-size: 32px;
            font-weight: bold;
            color: var(--accent);
            margin: 16px 0;
            font-family: 'Syne', sans-serif;
        }

        .metric-label {
            font-size: 13px;
            color: var(--muted);
            font-family: 'Space Mono', monospace;
        }

        .chart-container {
            background: var(--surface);
            padding: 24px;
            border-radius: 12px;
            border: 1px solid var(--border);
            margin-bottom: 30px;
        }

        .chart-container h3 {
            margin: 0 0 24px 0;
            color: var(--accent);
            font-size: 16px;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
        }

        .table thead {
            background-color: var(--surface2);
        }

        .table thead th {
            padding: 12px;
            font-size: 10px;
            letter-spacing: 1.5px;
            color: var(--muted);
            text-align: left;
            font-family: 'Space Mono', monospace;
            border-bottom: 2px solid var(--border);
            font-weight: 700;
            white-space: nowrap;
        }

        .table tbody td {
            padding: 12px;
            font-size: 13px;
            border-bottom: 1px solid rgba(31, 45, 69, 0.5);
            color: var(--text);
        }

        .table tbody tr:hover td {
            background-color: rgba(0, 229, 255, 0.05);
        }

        .table tbody tr:last-child td {
            border-bottom: none;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            font-family: 'Space Mono', monospace;
        }

        .status-badge.normal {
            background: rgba(57, 255, 20, 0.15);
            color: #39ff14;
            border: 1px solid rgba(57, 255, 20, 0.3);
        }

        .status-badge.elevated {
            background: rgba(245, 158, 11, 0.15);
            color: var(--warn);
            border: 1px solid rgba(245, 158, 11, 0.3);
        }

        .status-badge.high {
            background: rgba(255, 77, 109, 0.15);
            color: #ff4d6d;
            border: 1px solid rgba(255, 77, 109, 0.3);
        }

        .btn {
            padding: 10px 14px;
            border-radius: 6px;
            font-family: 'Syne', sans-serif;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-block;
            letter-spacing: 0.5px;
        }

        .btn-secondary {
            background: var(--surface2);
            color: var(--text);
            border: 1px solid var(--border);
        }

        .btn-secondary:hover {
            background: var(--border);
            border-color: var(--accent);
        }

        @import url('https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;600;700;800&display=swap');
    </style>
</head>
<body>
    <nav class="navbar-top">
        <h2 class="navbar-brand">Vital Statistics Analytics</h2>
        <a href="/VitalWear-1/api/auth/logout.php" class="btn btn-secondary">Logout</a>
    </nav>

    <div class="page-wrapper">
        <aside class="sidebar">
            <div class="sidebar-header">
                <h5 class="sidebar-title">Menu</h5>
            </div>
            <nav class="sidebar-nav">
                <a class="nav-link" href="dashboard.php">Dashboard</a>
                
                <!-- User Management -->
                <div class="nav-group">
                    <button class="nav-group-toggle">User Management <span class="dropdown-arrow">▼</span></button>
                    <div class="nav-group-items">
                        <a class="nav-link" href="users.php">Staff Directory</a>
                        <a class="nav-link" href="user_status.php">User Status</a>
                    </div>
                </div>

                <!-- Reports -->
                <div class="nav-group active">
                    <button class="nav-group-toggle">Reports <span class="dropdown-arrow">▼</span></button>
                    <div class="nav-group-items">
                        <a class="nav-link active" href="vitals_analytics.php">Vital Statistics</a>
                        <a class="nav-link" href="audit_log.php">System Activity Log</a>
                    </div>
                </div>

                <!-- Monitoring -->
                <div class="nav-group">
                    <button class="nav-group-toggle">Monitoring <span class="dropdown-arrow">▼</span></button>
                    <div class="nav-group-items">
                        <a class="nav-link" href="incidents.php">Incident Monitoring</a>
                        <a class="nav-link" href="device_incidents.php">Device Overview</a>
                        <a class="nav-link" href="vitals.php">User Activity</a>
                    </div>
                </div>

                <!-- Accounts -->
                <div class="nav-group">
                    <button class="nav-group-toggle">Accounts <span class="dropdown-arrow">▼</span></button>
                    <div class="nav-group-items">
                        <a class="nav-link" href="profile.php">Profile</a>
                        <a class="nav-link" href="/VitalWear-1/api/auth/logout.php" style="color: #ff4d6d;">Logout</a>
                    </div>
                </div>
            </nav>
        </aside>

        <main class="main-content">
            <div class="container">
                <h1>📊 Vital Statistics Analytics</h1>
                <p>Comprehensive analysis of patient vital signs and monitoring patterns.</p>
                
                <!-- Top Row: Key Metrics -->
                <div class="metrics-grid">
                    
                    <!-- Average BP Card -->
                    <div class="metric-card">
                        <div class="metric-header">
                            <h4>Average BP</h4>
                            <span class="metric-icon">📊</span>
                        </div>
                        <div>
                            <div class="metric-value">
                                <?php echo round($avgBp['avg_systolic'] ?? 0) . "/" . round($avgBp['avg_diastolic'] ?? 0) . " mmHg"; ?>
                            </div>
                            <p class="metric-label">
                                Based on <?php echo intval($avgBp['total_readings'] ?? 0); ?> readings this month
                            </p>
                        </div>
                    </div>

                    <!-- High BP Incidents Card -->
                    <div class="metric-card">
                        <div class="metric-header">
                            <h4>High BP Incidents</h4>
                            <span class="metric-icon">⚠️</span>
                        </div>
                        <div>
                            <div class="metric-value">
                                <?php echo $highBpCount; ?>
                            </div>
                            <p class="metric-label">
                                Readings above 140/90 this month
                            </p>
                        </div>
                    </div>

                    <!-- Peak Hour Card -->
                    <div class="metric-card">
                        <div class="metric-header">
                            <h4>Peak Incident Hour</h4>
                            <span class="metric-icon">🕐</span>
                        </div>
                        <div>
                            <div class="metric-value">
                                <?php echo $peakHour !== null ? date('g A', mktime((int)$peakHour, 0)) : 'N/A'; ?>
                            </div>
                            <p class="metric-label">
                                Most incidents occur around this time
                            </p>
                        </div>
                    </div>

                    <!-- Monitoring Frequency Card -->
                    <div class="metric-card">
                        <div class="metric-header">
                            <h4>Monitoring Frequency</h4>
                            <span class="metric-icon">📈</span>
                        </div>
                        <div>
                            <div class="metric-value">
                                <?php echo round($monitoringFreq['readings_per_day'] ?? 0, 1); ?>
                            </div>
                            <p class="metric-label">
                                Average readings per day
                            </p>
                        </div>
                    </div>

                </div>

                <!-- Middle Section: Charts -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(450px, 1fr)); gap: 30px; margin-bottom: 40px;">
                    
                    <!-- BP Trend Chart -->
                    <div class="chart-container">
                        <h3>📊 BP Trend (Last 5 Weeks)</h3>
                        <canvas id="bpTrendChart" style="max-height: 300px;"></canvas>
                    </div>

                    <!-- Vital Status Distribution Chart -->
                    <div class="chart-container">
                        <h3>🏥 Vital Signs Distribution</h3>
                        <canvas id="vitalDistChart" style="max-height: 300px;"></canvas>
                    </div>

                </div>

                <!-- Incident Distribution by Hour -->
                <div class="chart-container">
                    <h3>⏰ Incident Distribution by Hour</h3>
                    <canvas id="incidentHourlyChart" style="max-height: 350px;"></canvas>
                </div>

                <!-- Detailed Vital Statistics Table -->
                <div class="chart-container" style="overflow-x: auto;">
                    <h3>📋 Recent Vital Readings (This Month)</h3>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Patient</th>
                                <th>Systolic</th>
                                <th>Diastolic</th>
                                <th>Heart Rate</th>
                                <th>O₂ Level (%)</th>
                                <th>BP Status</th>
                                <th>Recorded</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($vitalTableData as $vital): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($vital['pat_name']); ?></td>
                                <td><?php echo intval($vital['bp_systolic']); ?></td>
                                <td><?php echo intval($vital['bp_diastolic']); ?></td>
                                <td><?php echo intval($vital['heart_rate']); ?> bpm</td>
                                <td><?php echo intval($vital['oxygen_level']); ?></td>
                                <td>
                                    <span class="status-badge <?php echo strtolower($vital['bp_status']); ?>">
                                        <?php echo htmlspecialchars($vital['bp_status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, g:i A', strtotime($vital['recorded_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <?php if (empty($vitalTableData)): ?>
                    <div style="text-align: center; padding: 40px; color: var(--muted);">
                        No vital records found for this month.
                    </div>
                    <?php endif; ?>
                </div>

            </div>
        </main>
    </div>

    <script>
        // Toggle navigation groups
        document.querySelectorAll('.nav-group-toggle').forEach(toggle => {
            toggle.addEventListener('click', function() {
                this.parentElement.classList.toggle('active');
            });
        });

        // BP Trend Chart
        const bpTrendCtx = document.getElementById('bpTrendChart').getContext('2d');
        new Chart(bpTrendCtx, {
            type: 'line',
            data: {
                labels: [<?php foreach ($bpTrends as $trend) echo "'Week " . $trend['week_num'] . "', "; ?>],
                datasets: [
                    {
                        label: 'Average Systolic',
                        data: [<?php foreach ($bpTrends as $trend) echo round($trend['avg_systolic']) . ", "; ?>],
                        borderColor: '#ff4d6d',
                        backgroundColor: 'rgba(255, 77, 109, 0.1)',
                        borderWidth: 2,
                        tension: 0.4,
                        fill: true
                    },
                    {
                        label: 'Average Diastolic',
                        data: [<?php foreach ($bpTrends as $trend) echo round($trend['avg_diastolic']) . ", "; ?>],
                        borderColor: '#00e5ff',
                        backgroundColor: 'rgba(0, 229, 255, 0.1)',
                        borderWidth: 2,
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
                        labels: { color: '#a0aec0' }
                    }
                },
                scales: {
                    y: {
                        ticks: { color: '#a0aec0' },
                        grid: { color: 'rgba(160, 174, 192, 0.1)' }
                    },
                    x: {
                        ticks: { color: '#a0aec0' },
                        grid: { color: 'rgba(160, 174, 192, 0.1)' }
                    }
                }
            }
        });

        // Vital Status Distribution
        const vitalDistCtx = document.getElementById('vitalDistChart').getContext('2d');
        new Chart(vitalDistCtx, {
            type: 'doughnut',
            data: {
                labels: ['Normal', 'Elevated', 'High'],
                datasets: [{
                    data: [<?php echo $vitalDist['normal'] . ", " . $vitalDist['elevated'] . ", " . $vitalDist['high']; ?>],
                    backgroundColor: ['#39ff14', '#f59e0b', '#ff4d6d'],
                    borderColor: '#0a0e1a',
                    borderWidth: 3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        labels: { color: '#a0aec0' }
                    }
                }
            }
        });

        // Incident Distribution by Hour
        const incidentCtx = document.getElementById('incidentHourlyChart').getContext('2d');
        new Chart(incidentCtx, {
            type: 'bar',
            data: {
                labels: [<?php foreach ($incidentHourly as $ih) echo "'" . date('g A', mktime((int)$ih['hour'], 0)) . "', "; ?>],
                datasets: [{
                    label: 'Readings Count',
                    data: [<?php foreach ($incidentHourly as $ih) echo $ih['incident_count'] . ", "; ?>],
                    backgroundColor: '#00e5ff',
                    borderColor: '#00c9e8',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        labels: { color: '#a0aec0' }
                    }
                },
                scales: {
                    y: {
                        ticks: { color: '#a0aec0' },
                        grid: { color: 'rgba(160, 174, 192, 0.1)' }
                    },
                    x: {
                        ticks: { color: '#a0aec0' },
                        grid: { color: 'rgba(160, 174, 192, 0.1)' }
                    }
                }
            }
        });
    </script>
</body>
</html>
