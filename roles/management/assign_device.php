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
        
        .assignment-container, .recent-container {
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
        
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.2s;
        }
        
        .form-group select:focus {
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
        
        .device-preview {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #007bff;
        }
        
        .device-preview h4 {
            margin: 0 0 10px 0;
            color: #333;
        }
        
        .device-preview p {
            margin: 5px 0;
            font-size: 14px;
            color: #666;
        }
        
        .assignment-list {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .assignment-item {
            padding: 15px;
            border: 1px solid #eee;
            border-radius: 5px;
            margin-bottom: 10px;
            transition: background-color 0.2s;
        }
        
        .assignment-item:hover {
            background-color: #f8f9fa;
        }
        
        .assignment-info h4 {
            margin: 0 0 5px 0;
            color: #333;
        }
        
        .assignment-info p {
            margin: 3px 0;
            font-size: 12px;
            color: #666;
        }
        
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
        
        .quick-select {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }
        
        .quick-select-btn {
            padding: 8px 12px;
            background: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
            transition: background-color 0.2s;
        }
        
        .quick-select-btn:hover {
            background: #e9ecef;
        }
        
        .quick-select-btn.selected {
            background: #007bff;
            color: white;
            border-color: #007bff;
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
                <h1 style="margin: 0;">🔁 Assign Device</h1>
                <p style="margin: 5px 0 0 0; opacity: 0.9;">Assign available devices to responders</p>
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
            <div class="assignment-container">
                <h2>Device Assignment</h2>
                
                <?php if ($selected_device): ?>
                    <div class="device-preview">
                        <h4>Selected Device</h4>
                        <p><strong>Serial:</strong> <?php echo htmlspecialchars($selected_device['dev_serial']); ?></p>
                        <p><strong>Status:</strong> <?php echo ucfirst($selected_device['dev_status']); ?></p>
                        <p><strong>Created:</strong> <?php echo date('M j, Y', strtotime($selected_device['created_at'])); ?></p>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($available_devices)): ?>
                    <?php if (!$selected_device): ?>
                        <div class="form-group">
                            <label>Quick Select Device:</label>
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
                        <label for="device_id">Select Device *</label>
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
                        <label for="responder_id">Select Responder *</label>
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
                    
                    <div style="display: flex; gap: 10px;">
                        <button type="submit" class="btn btn-success" style="flex: 1;">🔁 Assign Device</button>
                        <button type="reset" class="btn btn-secondary" style="flex: 1;">Clear Form</button>
                    </div>
                </form>
                
                <?php if (empty($available_devices)): ?>
                    <div style="text-align: center; padding: 40px; color: #666;">
                        <p>No available devices to assign.</p>
                        <a href="register_device.php" class="btn btn-primary">Register New Device</a>
                    </div>
                <?php endif; ?>
            </div>

            <div class="recent-container">
                <h2>Recent Assignments</h2>
                
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
                        <p style="text-align: center; color: #666; padding: 20px;">No recent assignments found.</p>
                    <?php else: ?>
                        <?php foreach ($recent_assignments as $assignment): ?>
                            <div class="assignment-item">
                                <div class="assignment-info">
                                    <h4><?php echo htmlspecialchars($assignment['dev_serial']); ?></h4>
                                    <p><strong>Assigned to:</strong> <?php echo htmlspecialchars($assignment['resp_name']); ?></p>
                                    <p><strong>Email:</strong> <?php echo htmlspecialchars($assignment['resp_email']); ?></p>
                                    <p><strong>Assigned by:</strong> <?php echo htmlspecialchars($assignment['mgmt_name']); ?></p>
                                    <p><strong>Date:</strong> <?php echo date('M j, Y H:i', strtotime($assignment['date_assigned'])); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

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
