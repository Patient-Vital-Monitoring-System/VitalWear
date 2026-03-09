<?php
session_start();
require_once '../../../database/connection.php';

// Check if management user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'management') {
    header('Location: ../../../login.html');
    exit();
}

$conn = getDBConnection();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Dashboard - VitalWear</title>
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

        #sidebar a.active {
            background: rgba(27, 63, 114, 0.15);
        }

        .topbar {
            position: fixed;
            top: 0;
            left: 260px;
            right: 0;
            background: var(--pure-white);
            border-bottom: 1px solid var(--interface-border);
            padding: 16px 24px;
            font-weight: 600;
            z-index: 999;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            margin-left: 260px;
            margin-top: 80px;
        }

        .page-header {
            background: linear-gradient(135deg, var(--authority-blue) 0%, #2a5298 100%);
            color: white;
            padding: 30px 40px;
            border-radius: var(--radius);
            margin-bottom: 30px;
            box-shadow: var(--shadow);
        }

        .page-header h1 {
            color: white;
            margin: 0 0 8px 0;
            font-size: 1.8rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .page-header p {
            margin: 0;
            opacity: 0.9;
            color: white;
        }

        .btn {
            padding: 10px 20px;
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
            background: var(--pure-white);
            color: var(--authority-blue);
            border: 1px solid var(--interface-border);
        }

        .btn-secondary:hover {
            background: var(--dashboard-light);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--pure-white);
            padding: 24px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--interface-border);
            text-align: center;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(27, 63, 114, 0.12);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--authority-blue);
            margin-bottom: 8px;
        }

        .stat-label {
            color: var(--secondary-text);
            font-size: 0.9rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .reports-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        .report-card {
            background: var(--pure-white);
            padding: 30px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--interface-border);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .report-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--authority-blue) 0%, #2a5298 100%);
        }

        .report-card:hover {
            box-shadow: 0 8px 24px rgba(27, 63, 114, 0.12);
            transform: translateY(-2px);
        }

        .report-card h3 {
            margin: 0 0 20px 0;
            color: var(--authority-blue);
            font-size: 1.2rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .report-card p {
            color: var(--secondary-text);
            margin-bottom: 20px;
            line-height: 1.6;
        }

        .report-btn {
            background: linear-gradient(135deg, var(--authority-blue) 0%, #2a5298 100%);
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: var(--radius);
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            font-weight: 600;
            box-shadow: 0 4px 15px rgba(27, 63, 114, 0.3);
        }

        .report-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(27, 63, 114, 0.4);
        }

        @media (max-width: 768px) {
            .container {
                padding: 10px;
                margin-left: 0;
                margin-top: 60px;
            }
            
            .topbar {
                left: 0;
            }
            
            #sidebar {
                transform: translateX(-100%);
            }
        }
    </style>
</head>

<body>
    <!-- Topbar -->
    <header class="topbar">
        <div style="display: flex; align-items: center; gap: 12px;">
            <i class="fa fa-cogs" style="font-size: 24px; color: var(--authority-blue);"></i>
            <span style="font-size: 18px; font-weight: 700;">VitalWear Management</span>
        </div>
        <div style="display: flex; align-items: center; gap: 16px;">
            <span style="color: var(--secondary-text);">Welcome, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Management User'); ?></span>
        </div>
    </header>

    <!-- Sidebar -->
    <nav id="sidebar">
        <div class="sidebar-logo">
            <img src="../../../assets/logo.png" alt="VitalWear Logo">
        </div>
        <a href="../dashboard.php"><i class="fa fa-gauge"></i> Dashboard</a>
        <a href="../manage_responders.php"><i class="fa fa-user-md"></i> Manage Responders</a>
        <a href="../manage_rescuers.php"><i class="fa fa-user-shield"></i> Manage Rescuers</a>
        <a href="../register_device.php"><i class="fa fa-plus-circle"></i> Register Device</a>
        <a href="../device_list.php"><i class="fa fa-box"></i> Device List</a>
        <a href="../assign_device.php"><i class="fa fa-exchange-alt"></i> Assign Device</a>
        <a href="../verify_return.php"><i class="fa fa-check-double"></i> Verify Return</a>
        <a href="reportdashboard.php" class="active"><i class="fa fa-chart-bar"></i> Reports</a>
        <a href="../../../api/auth/logout.php" class="btn btn-secondary">Logout</a>
    </nav>

    <!-- Main Container -->
    <div class="container">
        <div class="page-header">
            <h1>
                <i class="fa fa-chart-bar"></i>
                Management Reports
            </h1>
            <p>Comprehensive analytics and insights for system management</p>
        </div>
        
        <?php
        // Get some basic statistics for the dashboard
        try {
            // Total devices
            $device_query = "SELECT COUNT(*) as total FROM device";
            $device_result = $conn->query($device_query);
            $total_devices = $device_result ? $device_result->fetch_assoc()['total'] : 0;
            
            // Total incidents
            $incident_query = "SELECT COUNT(*) as total FROM incident";
            $incident_result = $conn->query($incident_query);
            $total_incidents = $incident_result ? $incident_result->fetch_assoc()['total'] : 0;
            
            // Active incidents
            $active_query = "SELECT COUNT(*) as total FROM incident WHERE status IN ('ongoing', 'transferred')";
            $active_result = $conn->query($active_query);
            $active_incidents = $active_result ? $active_result->fetch_assoc()['total'] : 0;
            
            // Total responders
            $responder_query = "SELECT COUNT(*) as total FROM responder";
            $responder_result = $conn->query($responder_query);
            $total_responders = $responder_result ? $responder_result->fetch_assoc()['total'] : 0;
        } catch (Exception $e) {
            $total_devices = $total_incidents = $active_incidents = $total_responders = 0;
        }
        ?>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($total_devices); ?></div>
                <div class="stat-label">Total Devices</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($total_incidents); ?></div>
                <div class="stat-label">Total Incidents</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($active_incidents); ?></div>
                <div class="stat-label">Active Incidents</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($total_responders); ?></div>
                <div class="stat-label">Total Responders</div>
            </div>
        </div>
        
        <div class="reports-grid">
            <div class="report-card">
                <h3><i class="fa fa-chart-line"></i> Device Assignment History</h3>
                <p>View comprehensive reports on device assignments, including current assignments, historical data, and device utilization patterns.</p>
                <a href="device_assignment_history.php" class="report-btn">View Report</a>
            </div>
            
            <div class="report-card">
                <h3><i class="fa fa-exchange-alt"></i> Device Return History</h3>
                <p>Track device return records, maintenance logs, and device lifecycle management with detailed return analytics.</p>
                <a href="device_return_history.php" class="report-btn">View Report</a>
            </div>
            
            <div class="report-card">
                <h3><i class="fa fa-exclamation-triangle"></i> Incident Summary</h3>
                <p>Analyze incident patterns, response times, and resolution rates with comprehensive incident management analytics.</p>
                <a href="incident_summary.php" class="report-btn">View Report</a>
            </div>
            
            <div class="report-card">
                <h3><i class="fa fa-users"></i> Responder Activity</h3>
                <p>Monitor responder performance, activity levels, and incident response metrics with detailed performance analytics.</p>
                <a href="responder_activity.php" class="report-btn">View Report</a>
            </div>
        </div>
    </div>
</body>
</html>
