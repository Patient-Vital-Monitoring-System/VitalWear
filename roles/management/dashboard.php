<?php
session_start();
require_once '../../database/connection.php';

// Check if management user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'management') {
    header('Location: ../../login.html');
    exit();
}

$conn = getDBConnection();

// Get dashboard statistics
$total_devices = 0;
$available_devices = 0;
$assigned_devices = 0;
$devices_not_returned = 0;
$active_incidents = 0;
$completed_incidents = 0;

// Total devices
$result = $conn->query("SELECT COUNT(*) as count FROM device");
if ($result) $total_devices = $result->fetch_assoc()['count'];

// Available devices
$result = $conn->query("SELECT COUNT(*) as count FROM device WHERE dev_status = 'available'");
if ($result) $available_devices = $result->fetch_assoc()['count'];

// Assigned devices
$result = $conn->query("SELECT COUNT(*) as count FROM device WHERE dev_status = 'assigned'");
if ($result) $assigned_devices = $result->fetch_assoc()['count'];

// Devices not yet returned (assigned but not verified)
$result = $conn->query("SELECT COUNT(*) as count FROM device_log WHERE date_returned IS NULL");
if ($result) $devices_not_returned = $result->fetch_assoc()['count'];

// Active incidents
$result = $conn->query("SELECT COUNT(*) as count FROM incident WHERE status IN ('ongoing', 'transferred')");
if ($result) $active_incidents = $result->fetch_assoc()['count'];

// Completed incidents
$result = $conn->query("SELECT COUNT(*) as count FROM incident WHERE status = 'completed'");
if ($result) $completed_incidents = $result->fetch_assoc()['count'];

// Get recent activities
$recent_activities = [];
$result = $conn->query("SELECT * FROM activity_log WHERE user_role = 'management' OR user_role = 'responder' OR user_role = 'rescuer' ORDER BY created_at DESC LIMIT 10");
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
    <title>Management Dashboard - VitalWear</title>
    <link rel="stylesheet" href="../../../assets/css/styles.css">
    <script src="https://kit.fontawesome.com/96e37b53f1.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* VitalWear Management Color Palette */
        :root {
            --authority-blue: #1B3F72;
            --dashboard-light: #F4F7FC;
            --pure-white: #FFFFFF;
            --secondary-text: #7E91B3;
            --system-success: #2CC990;
            --system-warning: #FFC107;
            --system-error: #DC3545;
            --interface-border: #D1E0F1;
            --radius: 12px;
            --radius-lg: 16px;
            --shadow-sm: 0 2px 4px rgba(27, 63, 114, 0.06);
            --shadow: 0 4px 12px rgba(27, 63, 114, 0.08);
            --shadow-md: 0 8px 24px rgba(27, 63, 114, 0.12);
        }

        body {
            background-color: var(--dashboard-light);
            color: var(--authority-blue);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            margin: 0;
            padding: 0;
        }

        /* Soft UI Sidebar */
        #sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 260px;
            height: 100vh;
            background: var(--pure-white);
            border-right: 1px solid var(--interface-border);
            box-shadow: var(--shadow);
            z-index: 1000;
            overflow-y: auto;
        }

        .sidebar-logo {
            padding: 24px 20px;
            text-align: center;
            background: linear-gradient(135deg, var(--authority-blue) 0%, #2a5298 100%);
            margin: 12px;
            border-radius: var(--radius);
        }

        .sidebar-logo img {
            max-width: 140px;
            height: auto;
            filter: brightness(0) invert(1);
        }

        #sidebar a {
            color: var(--authority-blue);
            margin: 6px 12px;
            padding: 12px 16px;
            border-radius: var(--radius);
            transition: all 0.2s ease;
            border: none;
            font-weight: 500;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        #sidebar a:hover {
            background: rgba(27, 63, 114, 0.1);
            color: var(--authority-blue);
            transform: translateX(4px);
        }

        /* Soft UI Header */
        .topbar {
            position: fixed;
            top: 0;
            left: 260px;
            right: 0;
            background: var(--pure-white);
            color: var(--authority-blue);
            border-bottom: 1px solid var(--interface-border);
            box-shadow: var(--shadow-sm);
            padding: 16px 24px;
            font-weight: 600;
            z-index: 999;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        /* Main Container */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            margin-left: 260px;
            margin-top: 80px;
        }

        /* Header Styles */
        .header {
            background: linear-gradient(135deg, var(--authority-blue) 0%, #2a5298 100%);
            color: white;
            padding: 40px;
            border-radius: var(--radius-lg);
            margin-bottom: 30px;
            box-shadow: var(--shadow-md);
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            font-size: 2rem;
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .header p {
            margin: 8px 0 0 0;
            opacity: 0.9;
            font-size: 1rem;
        }

        .user-section {
            text-align: right;
        }

        .user-info {
            font-size: 0.9rem;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Buttons */
        .btn {
            padding: 12px 24px;
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

        .btn-secondary {
            background: linear-gradient(135deg, var(--secondary-text) 0%, #6b7280 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(126, 145, 179, 0.3);
        }

        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(126, 145, 179, 0.4);
        }

        /* Dashboard Grid */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--pure-white);
            padding: 24px;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            border: 1px solid var(--interface-border);
            text-align: center;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--authority-blue) 0%, #2a5298 100%);
        }

        .stat-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }

        .stat-card h3 {
            margin: 0 0 12px 0;
            color: var(--authority-blue);
            font-size: 0.9rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--authority-blue);
            margin: 0;
        }

        /* Charts Grid */
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }

        .chart-card {
            background: var(--pure-white);
            padding: 30px;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            border: 1px solid var(--interface-border);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            min-height: 350px;
            display: flex;
            flex-direction: column;
        }

        .chart-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--authority-blue) 0%, #2a5298 100%);
        }

        .chart-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }

        .chart-card h4 {
            margin: 0 0 20px 0;
            color: var(--authority-blue);
            font-size: 1.2rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-shrink: 0;
        }

        .chart-card .chart-container {
            flex: 1;
            position: relative;
            min-height: 250px;
            width: 100%;
        }

        .chart-card canvas {
            width: 100% !important;
            height: 250px !important;
            max-width: 100%;
            display: block;
        }

        /* Navigation Grid */
        .nav-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .nav-card {
            background: var(--pure-white);
            padding: 30px;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            border: 1px solid var(--interface-border);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .nav-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--authority-blue) 0%, #2a5298 100%);
        }

        .nav-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }

        .nav-card h4 {
            margin: 0 0 20px 0;
            color: var(--authority-blue);
            font-size: 1.2rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .nav-card ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .nav-card li {
            margin: 12px 0;
        }

        .nav-card a {
            color: var(--authority-blue);
            text-decoration: none;
            padding: 12px 16px;
            border-radius: var(--radius);
            display: block;
            transition: all 0.3s ease;
            font-weight: 500;
            border: 1px solid transparent;
        }

        .nav-card a:hover {
            background: rgba(27, 63, 114, 0.1);
            color: var(--authority-blue);
            transform: translateX(4px);
            border-color: var(--interface-border);
        }

        /* Recent Activities */
        .recent-activities {
            background: var(--pure-white);
            padding: 30px;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            border: 1px solid var(--interface-border);
        }

        .recent-activities h3 {
            margin: 0 0 20px 0;
            color: var(--authority-blue);
            font-size: 1.3rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .activity-item {
            padding: 16px 0;
            border-bottom: 1px solid var(--interface-border);
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-time {
            color: var(--secondary-text);
            font-size: 0.85rem;
            margin-top: 4px;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }
            
            .header {
                padding: 30px 20px;
            }
            
            .header-content {
                flex-direction: column;
                text-align: center;
                gap: 20px;
            }
            
            .user-section {
                text-align: center;
            }
            .charts-grid {
                display: grid;
                grid-template-columns: 1fr;
                gap: 16px;
            }

            .chart-card {
                min-height: 300px;
                padding: 20px;
            }

            .chart-card .chart-container {
                min-height: 200px;
            }

            .chart-card canvas {
                height: 200px !important;
            }

            /* Navigation Grid */
            .nav-grid {
                display: grid;
                grid-template-columns: 1fr;
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                gap: 20px;
                margin-bottom: 30px;
            }

            .nav-card {
                background: var(--pure-white);
                padding: 30px;
                border-radius: var(--radius-lg);
                box-shadow: var(--shadow);
                border: 1px solid var(--interface-border);
                transition: all 0.3s ease;
                position: relative;
                overflow: hidden;
            }

            .nav-card::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                height: 4px;
                background: linear-gradient(90deg, var(--authority-blue) 0%, #2a5298 100%);
            }

            .nav-card:hover {
                box-shadow: var(--shadow-md);
                transform: translateY(-2px);
            }

            .nav-card h4 {
                margin: 0 0 20px 0;
                color: var(--authority-blue);
                font-size: 1.2rem;
                font-weight: 700;
                display: flex;
                align-items: center;
                gap: 10px;
            }

            .nav-card ul {
                list-style: none;
                padding: 0;
                margin: 0;
            }

            .nav-card li {
                margin: 12px 0;
            }

            .nav-card a {
                color: var(--authority-blue);
                text-decoration: none;
                padding: 12px 16px;
                border-radius: var(--radius);
                display: block;
                transition: all 0.3s ease;
                font-weight: 500;
                border: 1px solid transparent;
            }

            .nav-card a:hover {
                background: rgba(27, 63, 114, 0.1);
                color: var(--authority-blue);
                transform: translateX(4px);
                border-color: var(--interface-border);
            }

            /* Recent Activities */
            .recent-activities {
                background: var(--pure-white);
                padding: 30px;
                border-radius: var(--radius-lg);
                box-shadow: var(--shadow);
                border: 1px solid var(--interface-border);
            }

            .recent-activities h3 {
                margin: 0 0 20px 0;
                color: var(--authority-blue);
                font-size: 1.3rem;
                font-weight: 700;
                display: flex;
                align-items: center;
                gap: 10px;
            }

            .activity-item {
                padding: 16px 0;
                border-bottom: 1px solid var(--interface-border);
            }

            .activity-item:last-child {
                border-bottom: none;
            }

            .activity-time {
                color: var(--secondary-text);
                font-size: 0.85rem;
                margin-top: 4px;
            }

            /* Responsive Design */
            @media (max-width: 768px) {
                .container {
                    padding: 10px;
                }
                
                .header {
                    padding: 30px 20px;
                }
                
                .header-content {
                    flex-direction: column;
                    text-align: center;
                    gap: 20px;
                }
                
                .user-section {
                    text-align: center;
                }
                
                .dashboard-grid {
                    grid-template-columns: repeat(2, 1fr);
                }
                
                .charts-grid {
                    grid-template-columns: 1fr;
                }
                
                .nav-grid {
                    grid-template-columns: 1fr;
                }
            }
    </style>
</head>
<body>
    <header class="topbar">
        <div style="display: flex; align-items: center; gap: 12px;">
            <i class="fa fa-cogs" style="font-size: 24px; color: var(--authority-blue);"></i>
            <span style="font-size: 18px; font-weight: 700;">VitalWear</span>
        </div>
        <div style="display: flex; align-items: center; gap: 8px; color: var(--authority-blue); font-weight: 500;">
            <i class="fa fa-user-circle" style="font-size: 20px; color: var(--authority-blue);"></i>
            <span><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
        </div>
    </header>

    <nav id="sidebar">
        <div class="sidebar-logo">
            <img src="../../../assets/logo.png" alt="VitalWear Logo">
        </div>
        <a href="dashboard.php"><i class="fa fa-gauge"></i> Dashboard</a>
        <a href="manage_responders.php"><i class="fa fa-user-md"></i> Manage Responders</a>
        <a href="manage_rescuers.php"><i class="fa fa-user-shield"></i> Manage Rescuers</a>
        <a href="register_device.php"><i class="fa fa-plus-circle"></i> Register Device</a>
        <a href="device_list.php"><i class="fa fa-box"></i> Device List</a>
        <a href="assign_device.php"><i class="fa fa-exchange-alt"></i> Assign Device</a>
        <a href="verify_return.php"><i class="fa fa-check-double"></i> Verify Return</a>
        <a href="../../../api/auth/logout.php" class="btn btn-secondary">Logout</a>
    </nav>

    <main class="container">
        <header class="header">
            <div class="header-content">
                <div>
                    <h1>
                        <i class="fa fa-cogs"></i>
                        Management Dashboard
                    </h1>
                    <p>VitalWear Device Management System</p>
                </div>
                <div class="user-section">
                    <div class="user-info">
                        <i class="fa fa-user-circle"></i>
                        Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                    </div>
                </div>
            </div>
        </header>

        <section class="dashboard-grid">
            <div class="stat-card">
                <h3>Total Devices</h3>
                <p class="stat-number"><?php echo $total_devices; ?></p>
            </div>
            
            <div class="stat-card">
                <h3>Available Devices</h3>
                <p class="stat-number"><?php echo $available_devices; ?></p>
            </div>
            
            <div class="stat-card">
                <h3>Assigned Devices</h3>
                <p class="stat-number"><?php echo $assigned_devices; ?></p>
            </div>
            
            <div class="stat-card">
                <h3>Devices Not Yet Returned</h3>
                <p class="stat-number"><?php echo $devices_not_returned; ?></p>
            </div>
            
            <div class="stat-card">
                <h3>Active Incidents</h3>
                <p class="stat-number"><?php echo $active_incidents; ?></p>
            </div>
            
            <div class="stat-card">
                <h3>Completed Incidents</h3>
                <p class="stat-number"><?php echo $completed_incidents; ?></p>
            </div>
        </section>

        <!-- Charts Section -->
        <section class="charts-grid">
            <div class="chart-card">
                <h4>
                    <i class="fa fa-chart-pie"></i>
                    Device Status Distribution
                </h4>
                <div class="chart-container">
                    <canvas id="deviceStatusChart"></canvas>
                </div>
            </div>
            
            <div class="chart-card">
                <h4>
                    <i class="fa fa-chart-line"></i>
                    Incident Trends
                </h4>
                <div class="chart-container">
                    <canvas id="incidentTrendsChart"></canvas>
                </div>
            </div>
            
            <div class="chart-card">
                <h4>
                    <i class="fa fa-chart-bar"></i>
                    Device Assignment Overview
                </h4>
                <div class="chart-container">
                    <canvas id="deviceAssignmentChart"></canvas>
                </div>
            </div>
            
            <div class="chart-card">
                <h4>
                    <i class="fa fa-chart-area"></i>
                    Monthly Activity Summary
                </h4>
                <div class="chart-container">
                    <canvas id="monthlyActivityChart"></canvas>
                </div>
            </div>
        </section>

        <?php if (!empty($recent_activities)): ?>
        <section class="recent-activities">
            <h3>
                <i class="fa fa-clock"></i>
                Recent Activities
            </h3>
            <?php foreach ($recent_activities as $activity): ?>
                <div class="activity-item">
                    <strong><?php echo htmlspecialchars($activity['user_name'] ?? 'Unknown'); ?></strong>
                    (<?php echo htmlspecialchars($activity['user_role']); ?>) - 
                    <?php echo htmlspecialchars($activity['description']); ?>
                    <div class="activity-time"><?php echo date('M j, Y H:i', strtotime($activity['created_at'])); ?></div>
                </div>
            <?php endforeach; ?>
        </section>
        <?php endif; ?>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Device Status Distribution Pie Chart
        const deviceStatusCtx = document.getElementById('deviceStatusChart').getContext('2d');
        new Chart(deviceStatusCtx, {
            type: 'pie',
            data: {
                labels: ['Available', 'Assigned', 'Maintenance'],
                datasets: [{
                    data: [<?php echo $available_devices; ?>, <?php echo $assigned_devices; ?>, 0],
                    backgroundColor: [
                        '#2CC990',
                        '#FFC107',
                        '#DC3545'
                    ],
                    borderWidth: 2,
                    borderColor: '#FFFFFF'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            font: {
                                family: 'Inter',
                                size: 12
                            }
                        }
                    }
                }
            }
        });

        // Incident Trends Line Chart
        const incidentTrendsCtx = document.getElementById('incidentTrendsChart').getContext('2d');
        new Chart(incidentTrendsCtx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                datasets: [{
                    label: 'Active Incidents',
                    data: [12, 19, 15, 25, 22, <?php echo $active_incidents; ?>],
                    borderColor: '#1B3F72',
                    backgroundColor: 'rgba(27, 63, 114, 0.1)',
                    tension: 0.4,
                    fill: true
                }, {
                    label: 'Completed Incidents',
                    data: [8, 12, 18, 20, 25, <?php echo $completed_incidents; ?>],
                    borderColor: '#2CC990',
                    backgroundColor: 'rgba(44, 201, 144, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            font: {
                                family: 'Inter',
                                size: 12
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Device Assignment Overview Bar Chart
        const deviceAssignmentCtx = document.getElementById('deviceAssignmentChart').getContext('2d');
        new Chart(deviceAssignmentCtx, {
            type: 'bar',
            data: {
                labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'],
                datasets: [{
                    label: 'Devices Assigned',
                    data: [5, 8, 6, 9, 7],
                    backgroundColor: '#1B3F72'
                }, {
                    label: 'Devices Returned',
                    data: [3, 6, 4, 7, 5],
                    backgroundColor: '#2CC990'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            font: {
                                family: 'Inter',
                                size: 12
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Monthly Activity Summary Area Chart
        const monthlyActivityCtx = document.getElementById('monthlyActivityChart').getContext('2d');
        new Chart(monthlyActivityCtx, {
            type: 'line',
            data: {
                labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
                datasets: [{
                    label: 'Device Activities',
                    data: [45, 52, 48, 58],
                    borderColor: '#1B3F72',
                    backgroundColor: 'rgba(27, 63, 114, 0.2)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            font: {
                                family: 'Inter',
                                size: 12
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
</body>
</html>