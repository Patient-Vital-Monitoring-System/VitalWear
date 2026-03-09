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
    <link rel="stylesheet" href="../../../assets/css/styles.css">
    <style>
        .page-header {
            background: #007bff;
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: background-color 0.2s;
        }
        
        .btn-primary { background: #007bff; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-warning { background: #ffc107; color: black; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        
        .btn:hover { opacity: 0.9; }
        
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }
        
        .form-container, .devices-container {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #333;
        }
        
        .form-group input, .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.2s;
        }
        
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #007bff;
        }
        
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
        .devices-list {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .device-item {
            padding: 15px;
            border: 1px solid #eee;
            border-radius: 5px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: background-color 0.2s;
        }
        
        .device-item:hover {
            background-color: #f8f9fa;
        }
        
        .device-info h4 {
            margin: 0 0 5px 0;
            color: #333;
        }
        
        .device-info p {
            margin: 0;
            font-size: 12px;
            color: #666;
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status-available { background: #d4edda; color: #155724; }
        .status-assigned { background: #fff3cd; color: #856404; }
        .status-maintenance { background: #f8d7da; color: #721c24; }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            text-align: center;
            padding: 15px;
            border-radius: 5px;
            background: #f8f9fa;
        }
        
        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #007bff;
        }
        
        .stat-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
        }
        
        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="page-header">
            <div>
                <h1 style="margin: 0;">📦 Register Device</h1>
                <p style="margin: 5px 0 0 0; opacity: 0.9;">Add new monitoring devices to the system</p>
            </div>
            <div>
                <a href="device_list.php" class="btn btn-primary">View All Devices</a>
            </div>
        </header>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $alert_type; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="content-grid">
            <div class="form-container">
                <h2>Register New Device</h2>
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
                    
                    <div style="display: flex; gap: 10px;">
                        <button type="submit" class="btn btn-primary" style="flex: 1;">Register Device</button>
                        <button type="reset" class="btn btn-secondary" style="flex: 1;">Clear Form</button>
                    </div>
                </form>
                
                <div style="margin-top: 30px; padding: 15px; background: #f8f9fa; border-radius: 5px;">
                    <h4 style="margin: 0 0 10px 0;">Device Status Guide:</h4>
                    <ul style="margin: 0; padding-left: 20px; font-size: 14px;">
                        <li><strong>Available:</strong> Device is ready for assignment</li>
                        <li><strong>Maintenance:</strong> Device is under maintenance</li>
                        <li><strong>Assigned:</strong> Device is currently assigned to a responder</li>
                    </ul>
                </div>
            </div>

            <div class="devices-container">
                <h2>Recently Registered Devices</h2>
                
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
                        <p style="text-align: center; color: #666; padding: 20px;">No devices registered yet.</p>
                    <?php else: ?>
                        <?php 
                        // Show only last 10 devices
                        $recent_devices = array_slice($devices, 0, 10);
                        foreach ($recent_devices as $device): 
                        ?>
                            <div class="device-item">
                                <div class="device-info">
                                    <h4><?php echo htmlspecialchars($device['dev_serial']); ?></h4>
                                    <p>Registered: <?php echo date('M j, Y H:i', strtotime($device['created_at'])); ?></p>
                                </div>
                                <span class="status-badge status-<?php echo $device['dev_status']; ?>">
                                    <?php echo ucfirst($device['dev_status']); ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <?php if (count($devices) > 10): ?>
                    <div style="text-align: center; margin-top: 15px;">
                        <a href="device_list.php" class="btn btn-secondary">View All Devices (<?php echo count($devices); ?> total)</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
