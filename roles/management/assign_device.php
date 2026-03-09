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
$selected_device_id = $_GET['device_id'] ?? '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'assign_device') {
        $device_id = $_POST['device_id'];
        $responder_id = $_POST['responder_id'];
        $management_id = $_SESSION['user_id'];
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Create device log entry
            $stmt = $conn->prepare("INSERT INTO device_log (dev_id, resp_id, mgmt_id, date_assigned) VALUES (?, ?, ?, NOW())");
            $stmt->bind_param("iii", $device_id, $responder_id, $management_id);
            $stmt->execute();
            
            // Update device status
            $stmt = $conn->prepare("UPDATE device SET dev_status = 'assigned' WHERE dev_id = ?");
            $stmt->bind_param("i", $device_id);
            $stmt->execute();
            
            // Get device and responder info for logging
            $device_result = $conn->query("SELECT dev_serial FROM device WHERE dev_id = $device_id");
            $device_info = $device_result->fetch_assoc();
            
            $responder_result = $conn->query("SELECT resp_name, resp_email FROM responder WHERE resp_id = $responder_id");
            $responder_info = $responder_result->fetch_assoc();
            
            // Log activity
            $log_desc = "Assigned device {$device_info['dev_serial']} to {$responder_info['resp_name']} ({$responder_info['resp_email']})";
            $stmt_log = $conn->prepare("INSERT INTO activity_log (user_name, user_role, action_type, module, description) VALUES (?, ?, 'assign', 'device', ?)");
            $stmt_log->bind_param("sss", $_SESSION['user_name'], $_SESSION['user_role'], $log_desc);
            $stmt_log->execute();
            
            $conn->commit();
            
            $message = "Device assigned successfully!";
            $alert_type = "success";
            
            // Clear selection
            $selected_device_id = '';
            
        } catch (Exception $e) {
            $conn->rollback();
            $message = "Error assigning device: " . $e->getMessage();
            $alert_type = "danger";
        }
    }
}

// Get available devices
$available_devices = [];
$result = $conn->query("SELECT * FROM device WHERE dev_status = 'available' ORDER BY dev_serial");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $available_devices[] = $row;
    }
}

// Get active responders
$active_responders = [];
$result = $conn->query("SELECT * FROM responder WHERE status = 'active' ORDER BY resp_name");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $active_responders[] = $row;
    }
}

// Get selected device info
$selected_device = null;
if (!empty($selected_device_id)) {
    $stmt = $conn->prepare("SELECT * FROM device WHERE dev_id = ? AND dev_status = 'available'");
    $stmt->bind_param("i", $selected_device_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 1) {
        $selected_device = $result->fetch_assoc();
    }
}

// Get recent assignments
$recent_assignments = [];
$result = $conn->query("
    SELECT dl.*, d.dev_serial, r.resp_name, r.resp_email, m.mgmt_name
    FROM device_log dl
    JOIN device d ON dl.dev_id = d.dev_id
    JOIN responder r ON dl.resp_id = r.resp_id
    JOIN management m ON dl.mgmt_id = m.mgmt_id
    WHERE dl.date_returned IS NULL
    ORDER BY dl.date_assigned DESC
    LIMIT 10
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $recent_assignments[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Device - VitalWear</title>
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
            display: block;
            margin: 0 auto;
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

        .btn-success {
            background: linear-gradient(135deg, var(--system-success) 0%, #20c997 100%);
            color: var(--pure-white);
            box-shadow: var(--shadow);
        }

        .btn-success:hover {
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
        
        /* Assignment Container */
        .assignment-container, .recent-container {
            background: var(--pure-white);
            padding: 32px;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            border: 1px solid var(--interface-border);
            transition: all 0.3s ease;
        }

        .assignment-container:hover, .recent-container:hover {
            box-shadow: var(--shadow-md);
        }

        .assignment-container h2, .recent-container h2 {
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

        .form-group select {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid var(--interface-border);
            border-radius: var(--radius);
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            transition: all 0.3s ease;
            background: var(--pure-white);
        }

        .form-group select:focus {
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

        /* Device Preview */
        .device-preview {
            background: var(--dashboard-light);
            padding: 20px;
            border-radius: var(--radius);
            margin-bottom: 24px;
            border-left: 4px solid var(--authority-blue);
            border: 1px solid var(--interface-border);
        }

        .device-preview h4 {
            margin: 0 0 12px 0;
            color: var(--authority-blue);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .device-preview p {
            margin: 6px 0;
            font-size: 14px;
            color: var(--authority-blue);
            font-weight: 500;
        }

        .device-preview strong {
            color: var(--authority-blue);
            font-weight: 600;
        }

        /* Quick Select */
        .quick-select {
            display: flex;
            gap: 8px;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }

        .quick-select-btn {
            padding: 8px 12px;
            background: var(--dashboard-light);
            border: 1px solid var(--interface-border);
            border-radius: var(--radius);
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
            color: var(--authority-blue);
            transition: all 0.3s ease;
        }

        .quick-select-btn:hover {
            background: var(--pure-white);
            border-color: var(--authority-blue);
            transform: translateY(-1px);
            box-shadow: var(--shadow-sm);
        }

        .quick-select-btn.selected {
            background: var(--authority-blue);
            color: var(--pure-white);
            border-color: var(--authority-blue);
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

        /* Assignment List */
        .assignment-list {
            max-height: 400px;
            overflow-y: auto;
            padding-right: 8px;
        }

        .assignment-item {
            padding: 16px;
            border: 1px solid var(--interface-border);
            border-radius: var(--radius);
            margin-bottom: 12px;
            transition: all 0.3s ease;
            background: var(--pure-white);
        }

        .assignment-item:hover {
            background: var(--dashboard-light);
            transform: translateX(4px);
            box-shadow: var(--shadow-sm);
        }

        .assignment-info h4 {
            margin: 0 0 8px 0;
            color: var(--authority-blue);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .assignment-info p {
            margin: 4px 0;
            font-size: 12px;
            color: var(--secondary-text);
            font-weight: 500;
        }

        .assignment-info strong {
            color: var(--authority-blue);
            font-weight: 600;
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
        <a href="register_device.php"><i class="fa fa-plus-circle"></i> Register Device</a>
        <a href="device_list.php"><i class="fa fa-box"></i> Device List</a>
        <a href="assign_device.php" class="active"><i class="fa fa-exchange-alt"></i> Assign Device</a>
        <a href="verify_return.php"><i class="fa fa-check-double"></i> Verify Return</a>
        <a href="reports/reportdashboard.php"><i class="fa fa-chart-bar"></i> Reports</a>
        <a href="/VitalWear-1/logout.php" class="btn btn-secondary">Logout</a>
    </nav>

    <main class="container">
        <header class="page-header">
            <div class="page-header-content">
                <div>
                    <h1><i class="fa fa-exchange-alt"></i> Assign Device</h1>
                    <p>Assign available devices to responders</p>
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
            <div class="assignment-container">
                <h2><i class="fa fa-exchange-alt"></i> Device Assignment</h2>
                
                <?php if ($selected_device): ?>
                    <div class="device-preview">
                        <h4><i class="fa fa-box"></i> Selected Device</h4>
                        <p><strong>Serial:</strong> <?php echo htmlspecialchars($selected_device['dev_serial']); ?></p>
                        <p><strong>Status:</strong> <?php echo ucfirst($selected_device['dev_status']); ?></p>
                        <p><strong>Created:</strong> <?php echo date('M j, Y', strtotime($selected_device['created_at'])); ?></p>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($available_devices)): ?>
                    <?php if (!$selected_device): ?>
                        <div class="form-group">
                            <label><i class="fa fa-bolt"></i> Quick Select Device:</label>
                            <div class="quick-select">
                                <?php foreach (array_slice($available_devices, 0, 5) as $device): ?>
                                    <button class="quick-select-btn" onclick="selectDevice(<?php echo $device['dev_id']; ?>)">
                                        <?php echo htmlspecialchars($device['dev_serial']); ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
                
                <form method="POST">
                    <input type="hidden" name="action" value="assign_device">
                    
                    <div class="form-group">
                        <label for="device_id"><i class="fa fa-box"></i> Select Device *</label>
                        <select id="device_id" name="device_id" required onchange="updateDevicePreview()">
                            <option value="">-- Select a device --</option>
                            <?php foreach ($available_devices as $device): ?>
                                <option value="<?php echo $device['dev_id']; ?>" 
                                        <?php echo ($selected_device_id == $device['dev_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($device['dev_serial']); ?> - 
                                    Created: <?php echo date('M j, Y', strtotime($device['created_at'])); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="responder_id"><i class="fa fa-user-md"></i> Select Responder *</label>
                        <select id="responder_id" name="responder_id" required>
                            <option value="">-- Select a responder --</option>
                            <?php foreach ($active_responders as $responder): ?>
                                <option value="<?php echo $responder['resp_id']; ?>">
                                    <?php echo htmlspecialchars($responder['resp_name']); ?> - 
                                    <?php echo htmlspecialchars($responder['resp_email']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div style="display: flex; gap: 12px;">
                        <button type="submit" class="btn btn-success" style="flex: 1;">
                            <i class="fa fa-exchange-alt"></i> Assign Device
                        </button>
                    </div>
                </form>
                
                <?php if (empty($available_devices)): ?>
                    <div class="empty-state">
                        <i class="fa fa-box-open" style="font-size: 48px; margin-bottom: 16px; opacity: 0.5;"></i>
                        <p>No available devices to assign.</p>
                        <a href="register_device.php" class="btn btn-primary">
                            <i class="fa fa-plus-circle"></i> Register New Device
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <div class="recent-container">
                <h2><i class="fa fa-clock"></i> Recent Assignments</h2>
                
                <?php
                // Calculate statistics
                $total_available = count($available_devices);
                $total_responders = count($active_responders);
                $recent_count = count($recent_assignments);
                ?>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $total_available; ?></div>
                        <div class="stat-label">Available</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $total_responders; ?></div>
                        <div class="stat-label">Active Responders</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $recent_count; ?></div>
                        <div class="stat-label">Active Assignments</div>
                    </div>
                </div>
                
                <div class="assignment-list">
                    <?php if (empty($recent_assignments)): ?>
                        <div class="empty-state">
                            <i class="fa fa-exchange-alt" style="font-size: 48px; margin-bottom: 16px; opacity: 0.5;"></i>
                            <p>No recent assignments found.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recent_assignments as $assignment): ?>
                            <div class="assignment-item">
                                <div class="assignment-info">
                                    <h4><i class="fa fa-box"></i> <?php echo htmlspecialchars($assignment['dev_serial']); ?></h4>
                                    <p><strong><i class="fa fa-user-md"></i> Assigned to:</strong> <?php echo htmlspecialchars($assignment['resp_name']); ?></p>
                                    <p><strong><i class="fa fa-envelope"></i> Email:</strong> <?php echo htmlspecialchars($assignment['resp_email']); ?></p>
                                    <p><strong><i class="fa fa-user"></i> Assigned by:</strong> <?php echo htmlspecialchars($assignment['mgmt_name']); ?></p>
                                    <p><strong><i class="fa fa-clock"></i> Date:</strong> <?php echo date('M j, Y H:i', strtotime($assignment['date_assigned'])); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <script>
        function selectDevice(deviceId) {
            document.getElementById('device_id').value = deviceId;
            updateDevicePreview();
            
            // Update button states
            document.querySelectorAll('.quick-select-btn').forEach(btn => {
                btn.classList.remove('selected');
            });
            event.target.classList.add('selected');
        }
        
        function updateDevicePreview() {
            const deviceSelect = document.getElementById('device_id');
            const selectedValue = deviceSelect.value;
            
            if (selectedValue) {
                // Reload page with selected device for preview
                const currentUrl = new URL(window.location);
                currentUrl.searchParams.set('device_id', selectedValue);
                window.location.href = currentUrl.toString();
            }
        }
    </script>
</body>
</html>
