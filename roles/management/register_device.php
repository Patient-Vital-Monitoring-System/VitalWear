<?php
session_start();
require_once '../../database/connection.php';

// Check if management user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'management') {
    header('Location: ../../login.html');
    exit();
}

$conn = getDBConnection();
$message = '';
$alert_type = '';

// Handle success message from redirect
if (isset($_GET['success']) && $_GET['success'] == '1') {
    $message = "Device registered successfully!";
    $alert_type = "success";
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'register_device') {
        $serial_number = trim($_POST['serial_number']);
        $status = $_POST['status'];
        
        // Check if serial number already exists
        $check_stmt = $conn->prepare("SELECT dev_id FROM device WHERE dev_serial = ?");
        $check_stmt->bind_param("s", $serial_number);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        if ($result->num_rows > 0) {
            $message = "Device with this serial number already exists!";
            $alert_type = "danger";
        } else {
            // Insert new device
            $stmt = $conn->prepare("INSERT INTO device (dev_serial, dev_status) VALUES (?, ?)");
            if ($stmt->bind_param("ss", $serial_number, $status)) {
                if ($stmt->execute()) {
                    $message = "Device registered successfully!";
                    $alert_type = "success";
                    
                    // Log activity
                    $log_desc = "Registered new device: $serial_number";
                    $stmt_log = $conn->prepare("INSERT INTO activity_log (user_name, user_role, action_type, module, description) VALUES (?, ?, 'add', 'device', ?)");
                    $stmt_log->bind_param("sss", $_SESSION['user_name'], $_SESSION['user_role'], $log_desc);
                    $stmt_log->execute();
                    
                    // Redirect to clear form and show success message
                    header("Location: register_device.php?success=1");
                    exit();
                } else {
                    $message = "Error registering device: " . $conn->error;
                    $alert_type = "danger";
                }
            }
        }
    }
}

// Get all devices for display
$devices = [];
$result = $conn->query("SELECT * FROM device ORDER BY created_at DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $devices[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Device - VitalWear</title>
        <script src="https://kit.fontawesome.com/96e37b53f1.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* VitalWear Soft UI Design System */
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

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background-color: var(--dashboard-light);
            color: var(--authority-blue);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            line-height: 1.6;
            overflow-x: hidden;
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
            transition: transform 0.3s ease;
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
            color: var(--authority-blue);
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
            margin-left: 260px;
            margin-top: 80px;
            padding: 24px;
            min-height: calc(100vh - 80px);
            transition: margin-left 0.3s ease;
        }

        h1, h2, h3, h4, h5, h6 {
            color: var(--authority-blue);
            font-weight: 700;
            line-height: 1.3;
        }

        /* Page Header */
        .page-header {
            background: linear-gradient(135deg, var(--authority-blue) 0%, #2a5298 100%);
            color: var(--pure-white);
            padding: 32px;
            border-radius: var(--radius-lg);
            margin-bottom: 32px;
            box-shadow: var(--shadow-md);
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            border-radius: 50%;
        }

        .page-header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            z-index: 1;
        }

        .page-header h1 {
            color: var(--pure-white);
            margin: 0 0 8px 0;
            font-size: 1.75rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .page-header p {
            color: rgba(255,255,255,0.9);
            margin: 0;
            font-size: 1rem;
        }

        .page-header-actions {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        /* Buttons */
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: var(--radius);
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s ease;
        }

        .btn:hover::before {
            left: 100%;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--system-success) 0%, #20c997 100%);
            color: var(--pure-white);
            box-shadow: var(--shadow);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .btn-secondary {
            background: linear-gradient(135deg, var(--secondary-text) 0%, #6b7280 100%);
            color: var(--pure-white);
            box-shadow: var(--shadow);
        }

        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 32px;
        }
        
        /* Form Container */
        .form-container, .devices-container {
            background: var(--pure-white);
            padding: 32px;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            border: 1px solid var(--interface-border);
            transition: all 0.3s ease;
        }

        .form-container:hover, .devices-container:hover {
            box-shadow: var(--shadow-md);
        }

        .form-container h2, .devices-container h2 {
            margin: 0 0 24px 0;
            color: var(--authority-blue);
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        /* Form Groups */
        .form-group {
            margin-bottom: 24px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--authority-blue);
        }

        .form-group input, .form-group select {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid var(--interface-border);
            border-radius: var(--radius);
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            transition: all 0.3s ease;
            background: var(--pure-white);
        }

        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: var(--authority-blue);
            box-shadow: 0 0 0 3px rgba(27, 63, 114, 0.1);
        }

        /* Alerts */
        .alert {
            padding: 16px 20px;
            border-radius: var(--radius);
            margin-bottom: 24px;
            border: 1px solid;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .alert-success {
            background: rgba(44, 201, 144, 0.15);
            color: var(--system-success);
            border-color: rgba(44, 201, 144, 0.3);
        }

        .alert-danger {
            background: rgba(220, 53, 69, 0.15);
            color: var(--system-error);
            border-color: rgba(220, 53, 69, 0.3);
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: var(--dashboard-light);
            padding: 20px;
            border-radius: var(--radius);
            text-align: center;
            border: 1px solid var(--interface-border);
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--authority-blue);
            margin-bottom: 8px;
        }

        .stat-label {
            font-size: 12px;
            color: var(--secondary-text);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        /* Devices List */
        .devices-list {
            max-height: 400px;
            overflow-y: auto;
            padding-right: 8px;
        }

        .device-item {
            padding: 16px;
            border: 1px solid var(--interface-border);
            border-radius: var(--radius);
            margin-bottom: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
            background: var(--pure-white);
        }

        .device-item:hover {
            background: var(--dashboard-light);
            transform: translateX(4px);
            box-shadow: var(--shadow-sm);
        }

        .device-info h4 {
            margin: 0 0 4px 0;
            color: var(--authority-blue);
            font-weight: 600;
        }

        .device-info p {
            margin: 0;
            font-size: 12px;
            color: var(--secondary-text);
            font-weight: 500;
        }

        /* Status Badges */
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: 1px solid;
        }

        .status-available {
            background: rgba(44, 201, 144, 0.15);
            color: var(--system-success);
            border-color: rgba(44, 201, 144, 0.3);
        }

        .status-assigned {
            background: rgba(255, 193, 7, 0.15);
            color: var(--system-warning);
            border-color: rgba(255, 193, 7, 0.3);
        }

        .status-maintenance {
            background: rgba(220, 53, 69, 0.15);
            color: var(--system-error);
            border-color: rgba(220, 53, 69, 0.3);
        }

        /* Info Box */
        .info-box {
            margin-top: 24px;
            padding: 20px;
            background: var(--dashboard-light);
            border-radius: var(--radius);
            border: 1px solid var(--interface-border);
        }

        .info-box h4 {
            margin: 0 0 12px 0;
            color: var(--authority-blue);
            font-weight: 600;
        }

        .info-box ul {
            margin: 0;
            padding-left: 20px;
            font-size: 14px;
            color: var(--authority-blue);
        }

        .info-box li {
            margin-bottom: 8px;
        }

        .info-box strong {
            color: var(--authority-blue);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            color: var(--secondary-text);
            padding: 32px;
            font-size: 14px;
            font-weight: 500;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            #sidebar {
                transform: translateX(-100%);
            }
            
            #sidebar.open {
                transform: translateX(0);
            }
            
            .topbar {
                left: 0;
            }
            
            .container {
                margin-left: 0;
                padding: 16px;
            }
            
            .content-grid {
                grid-template-columns: 1fr;
                gap: 24px;
            }
            
            .page-header-content {
                flex-direction: column;
                gap: 20px;
                text-align: center;
            }
            
            .page-header-actions {
                width: 100%;
            }
            
            .page-header-actions .btn {
                width: 100%;
                justify-content: center;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Scrollbar Styling */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--dashboard-light);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--secondary-text);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--authority-blue);
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
        <a href="register_device.php" class="active"><i class="fa fa-plus-circle"></i> Register Device</a>
        <a href="device_list.php"><i class="fa fa-box"></i> Device List</a>
        <a href="assign_device.php"><i class="fa fa-exchange-alt"></i> Assign Device</a>
        <a href="verify_return.php"><i class="fa fa-check-double"></i> Verify Return</a>
        <a href="reports/reportdashboard.php"><i class="fa fa-chart-bar"></i> Reports</a>
        <a href="/VitalWear-1/logout.php" class="btn btn-secondary">Logout</a>
    </nav>

    <main class="container">
        <header class="page-header">
            <div class="page-header-content">
                <div>
                    <h1><i class="fa fa-plus-circle"></i> Register Device</h1>
                    <p>Add new monitoring devices to the system</p>
                </div>
                <div class="page-header-actions">
                    <a href="device_list.php" class="btn btn-primary">
                        <i class="fa fa-box"></i> View All Devices
                    </a>
                </div>
            </div>
        </header>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $alert_type; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="content-grid">
            <div class="form-container">
                <h2><i class="fa fa-plus-circle"></i> Register New Device</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="register_device">
                    
                    <div class="form-group">
                        <label for="serial_number">Serial Number *</label>
                        <input type="text" id="serial_number" name="serial_number" required 
                               placeholder="e.g., VW-001-ABC123" pattern="[A-Za-z0-9\-]+"
                               title="Only letters, numbers, and hyphens allowed">
                    </div>
                    
                    <div class="form-group">
                        <label for="status">Device Status *</label>
                        <select id="status" name="status" required>
                            <option value="available">Available</option>
                            <option value="maintenance">Maintenance</option>
                            <option value="assigned">Assigned</option>
                        </select>
                    </div>
                    
                    <div style="display: flex; gap: 12px;">
                        <button type="submit" class="btn btn-primary" style="flex: 1;">
                            <i class="fa fa-save"></i> Register Device
                        </button>
                    </div>
                </form>
                
                <div class="info-box">
                    <h4><i class="fa fa-info-circle"></i> Device Status Guide:</h4>
                    <ul>
                        <li><strong>Available:</strong> Device is ready for assignment</li>
                        <li><strong>Maintenance:</strong> Device is under maintenance</li>
                        <li><strong>Assigned:</strong> Device is currently assigned to a responder</li>
                    </ul>
                </div>
            </div>

            <div class="devices-container">
                <h2><i class="fa fa-clock"></i> Recently Registered Devices</h2>
                
                <?php
                // Calculate device statistics
                $total_devices = count($devices);
                $available_count = 0;
                $assigned_count = 0;
                $maintenance_count = 0;
                
                foreach ($devices as $device) {
                    switch ($device['dev_status']) {
                        case 'available': $available_count++; break;
                        case 'assigned': $assigned_count++; break;
                        case 'maintenance': $maintenance_count++; break;
                    }
                }
                ?>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $total_devices; ?></div>
                        <div class="stat-label">Total Devices</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $available_count; ?></div>
                        <div class="stat-label">Available</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $maintenance_count; ?></div>
                        <div class="stat-label">Maintenance</div>
                    </div>
                </div>
                
                <div class="devices-list">
                    <?php if (empty($devices)): ?>
                        <div class="empty-state">
                            <i class="fa fa-box-open" style="font-size: 48px; margin-bottom: 16px; opacity: 0.5;"></i>
                            <p>No devices registered yet.</p>
                        </div>
                    <?php else: ?>
                        <?php 
                        // Show only last 10 devices
                        $recent_devices = array_slice($devices, 0, 10);
                        foreach ($recent_devices as $device): 
                        ?>
                            <div class="device-item">
                                <div class="device-info">
                                    <h4><i class="fa fa-box"></i> <?php echo htmlspecialchars($device['dev_serial']); ?></h4>
                                    <p><i class="fa fa-clock"></i> Registered: <?php echo date('M j, Y H:i', strtotime($device['created_at'])); ?></p>
                                </div>
                                <span class="status-badge status-<?php echo $device['dev_status']; ?>">
                                    <?php echo ucfirst($device['dev_status']); ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <?php if (count($devices) > 10): ?>
                    <div style="text-align: center; margin-top: 20px;">
                        <a href="device_list.php" class="btn btn-secondary">
                            <i class="fa fa-list"></i> View All Devices (<?php echo count($devices); ?> total)
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</body>
</html>
