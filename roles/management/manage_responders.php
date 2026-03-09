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
    $message = "Operation completed successfully!";
    $alert_type = "success";
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add_responder') {
            $name = trim($_POST['name']);
            $email = trim($_POST['email']);
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $contact = trim($_POST['contact']);
            
            $stmt = $conn->prepare("INSERT INTO responder (resp_name, resp_email, resp_password, resp_contact) VALUES (?, ?, ?, ?)");
            if ($stmt->bind_param("ssss", $name, $email, $password, $contact)) {
                if ($stmt->execute()) {
                    $message = "Responder added successfully!";
                    $alert_type = "success";
                    
                    // Log activity
                    $log_desc = "Added new responder: $name ($email)";
                    $stmt_log = $conn->prepare("INSERT INTO activity_log (user_name, user_role, action_type, module, description) VALUES (?, ?, 'add', 'responder', ?)");
                    $stmt_log->bind_param("sss", $_SESSION['user_name'], $_SESSION['user_role'], $log_desc);
                    $stmt_log->execute();
                    
                    // Redirect to clear form and close modal
                    header("Location: manage_responders.php?success=1");
                    exit();
                } else {
                    $message = "Error adding responder: " . $conn->error;
                    $alert_type = "danger";
                }
            }
        } elseif ($_POST['action'] === 'edit_responder') {
            $resp_id = $_POST['resp_id'];
            $name = trim($_POST['name']);
            $email = trim($_POST['email']);
            $contact = trim($_POST['contact']);
            $status = $_POST['status'];
            
            $stmt = $conn->prepare("UPDATE responder SET resp_name = ?, resp_email = ?, resp_contact = ?, status = ? WHERE resp_id = ?");
            if ($stmt->bind_param("ssssi", $name, $email, $contact, $status, $resp_id)) {
                if ($stmt->execute()) {
                    $message = "Responder updated successfully!";
                    $alert_type = "success";
                    
                    // Log activity
                    $log_desc = "Updated responder: $name ($email)";
                    $stmt_log = $conn->prepare("INSERT INTO activity_log (user_name, user_role, action_type, module, description) VALUES (?, ?, 'edit', 'responder', ?)");
                    $stmt_log->bind_param("sss", $_SESSION['user_name'], $_SESSION['user_role'], $log_desc);
                    $stmt_log->execute();
                    
                    // Redirect to clear form and close modal
                    header("Location: manage_responders.php?success=1");
                    exit();
                } else {
                    $message = "Error updating responder: " . $conn->error;
                    $alert_type = "danger";
                }
            }
        } elseif ($_POST['action'] === 'toggle_status') {
            $resp_id = $_POST['resp_id'];
            $status = $_POST['status'];
            
            $stmt = $conn->prepare("UPDATE responder SET status = ? WHERE resp_id = ?");
            if ($stmt->bind_param("si", $status, $resp_id)) {
                if ($stmt->execute()) {
                    $message = "Responder status updated successfully!";
                    $alert_type = "success";
                    
                    // Log activity
                    $log_desc = "Changed responder status to: $status";
                    $stmt_log = $conn->prepare("INSERT INTO activity_log (user_name, user_role, action_type, module, description) VALUES (?, ?, 'status_change', 'responder', ?)");
                    $stmt_log->bind_param("sss", $_SESSION['user_name'], $_SESSION['user_role'], $log_desc);
                    $stmt_log->execute();
                    
                    // Redirect to clear form state
                    header("Location: manage_responders.php?success=1");
                    exit();
                } else {
                    $message = "Error updating status: " . $conn->error;
                    $alert_type = "danger";
                }
            }
        }
    }
}

// Get all responders
$responders = [];
$result = $conn->query("SELECT * FROM responder ORDER BY created_at DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $responders[] = $row;
    }
}

// Get responder being edited
$edit_responder = null;
if (isset($_GET['edit'])) {
    $resp_id = $_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM responder WHERE resp_id = ?");
    $stmt->bind_param("i", $resp_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 1) {
        $edit_responder = $result->fetch_assoc();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Responders - VitalWear</title>
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

        .btn-secondary {
            background: linear-gradient(135deg, var(--secondary-text) 0%, #6b7280 100%);
            color: var(--pure-white);
            box-shadow: var(--shadow);
        }

        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .btn-warning {
            background: linear-gradient(135deg, var(--system-warning) 0%, #e0a800 100%);
            color: var(--pure-white);
            box-shadow: var(--shadow);
        }

        .btn-warning:hover {
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

        .btn-danger {
            background: linear-gradient(135deg, var(--system-error) 0%, #c82333 100%);
            color: var(--pure-white);
            box-shadow: var(--shadow);
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        /* Table Container */
        .table-container {
            background: var(--pure-white);
            padding: 30px;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            border: 1px solid var(--interface-border);
            overflow-x: auto;
            transition: all 0.3s ease;
        }

        .table-container:hover {
            box-shadow: var(--shadow-md);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 16px 12px;
            text-align: left;
            border-bottom: 1px solid var(--interface-border);
        }

        th {
            background: var(--dashboard-light);
            font-weight: 700;
            color: var(--authority-blue);
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 0.5px;
        }

        tr:hover {
            background: rgba(244, 247, 252, 0.5);
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

        .status-active {
            background: rgba(44, 201, 144, 0.15);
            color: var(--system-success);
            border-color: rgba(44, 201, 144, 0.3);
        }

        .status-inactive {
            background: rgba(220, 53, 69, 0.15);
            color: var(--system-error);
            border-color: rgba(220, 53, 69, 0.3);
        }

        /* Device Info */
        .device-info {
            font-size: 12px;
            color: var(--secondary-text);
            font-weight: 500;
        }

        /* Actions */
        .actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .actions .btn {
            padding: 8px 16px;
            font-size: 12px;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(27, 63, 114, 0.5);
            backdrop-filter: blur(4px);
        }

        .modal-content {
            background-color: var(--pure-white);
            margin: 5% auto;
            padding: 40px;
            border-radius: var(--radius-lg);
            width: 90%;
            max-width: 500px;
            box-shadow: var(--shadow-md);
            position: relative;
            animation: slideInUp 0.3s ease-out;
        }

        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-content h2 {
            margin: 0 0 24px 0;
            color: var(--authority-blue);
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        /* Form Groups */
        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--authority-blue);
        }

        .form-group input,
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

        .form-group input:focus,
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
            
            .page-header-content {
                flex-direction: column;
                gap: 20px;
                text-align: center;
            }
            
            .page-header-actions {
                flex-direction: column;
                width: 100%;
            }
            
            .page-header-actions .btn {
                width: 100%;
                justify-content: center;
            }
            
            .table-container {
                padding: 20px 16px;
            }
            
            th, td {
                padding: 12px 8px;
                font-size: 14px;
            }
            
            .actions {
                flex-direction: column;
            }
            
            .actions .btn {
                width: 100%;
                justify-content: center;
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
        <a href="manage_responders.php" class="active"><i class="fa fa-user-md"></i> Manage Responders</a>
        <a href="manage_rescuers.php"><i class="fa fa-user-shield"></i> Manage Rescuers</a>
        <a href="register_device.php"><i class="fa fa-plus-circle"></i> Register Device</a>
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
                    <h1><i class="fa fa-user-md"></i> Manage Responders</h1>
                    <p>Add, edit, and manage responder accounts</p>
                </div>
                <div class="page-header-actions">
                    <button class="btn btn-primary" onclick="openAddModal()">
                        <i class="fa fa-plus-circle"></i> Add Responder
                    </button>
                </div>
            </div>
        </header>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $alert_type; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Contact</th>
                        <th>Status</th>
                        <th>Assigned Devices</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($responders as $responder): ?>
                        <?php
                        // Get assigned devices count
                        $device_count = 0;
                        $device_result = $conn->query("SELECT COUNT(*) as count FROM device_log WHERE resp_id = {$responder['resp_id']} AND date_returned IS NULL");
                        if ($device_result) $device_count = $device_result->fetch_assoc()['count'];
                        ?>
                        <tr>
                            <td><?php echo $responder['resp_id']; ?></td>
                            <td><?php echo htmlspecialchars($responder['resp_name']); ?></td>
                            <td><?php echo htmlspecialchars($responder['resp_email']); ?></td>
                            <td><?php echo htmlspecialchars($responder['resp_contact'] ?: 'N/A'); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $responder['status']; ?>">
                                    <?php echo ucfirst($responder['status']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="device-info">
                                    <?php echo $device_count; ?> device(s) assigned
                                </div>
                            </td>
                            <td><?php echo date('M j, Y', strtotime($responder['created_at'])); ?></td>
                            <td>
                                <div class="actions">
                                    <button class="btn btn-warning" onclick="openEditModal(<?php echo $responder['resp_id']; ?>)">Edit</button>
                                    <?php if ($responder['status'] === 'active'): ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="resp_id" value="<?php echo $responder['resp_id']; ?>">
                                            <input type="hidden" name="status" value="inactive">
                                            <button type="submit" class="btn btn-secondary" onclick="return confirm('Deactivate this responder?')">Deactivate</button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="resp_id" value="<?php echo $responder['resp_id']; ?>">
                                            <input type="hidden" name="status" value="active">
                                            <button type="submit" class="btn btn-success">Activate</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>

    <!-- Add Responder Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <h2><i class="fa fa-plus-circle"></i> Add New Responder</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add_responder">
                
                <div class="form-group">
                    <label for="name">Name:</label>
                    <input type="text" id="name" name="name" required>
                </div>
                
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <div class="form-group">
                    <label for="contact">Contact Number:</label>
                    <input type="text" id="contact" name="contact">
                </div>
                
                <div style="display: flex; gap: 12px; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">
                        <i class="fa fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa fa-save"></i> Add Responder
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Responder Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <h2><i class="fa fa-edit"></i> Edit Responder</h2>
            <form method="POST" id="editForm">
                <input type="hidden" name="action" value="edit_responder">
                <input type="hidden" name="resp_id" id="edit_resp_id">
                
                <div class="form-group">
                    <label for="edit_name">Name:</label>
                    <input type="text" id="edit_name" name="name" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_email">Email:</label>
                    <input type="email" id="edit_email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_contact">Contact Number:</label>
                    <input type="text" id="edit_contact" name="contact">
                </div>
                
                <div class="form-group">
                    <label for="edit_status">Status:</label>
                    <select id="edit_status" name="status">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                
                <div style="display: flex; gap: 12px; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editModal')">
                        <i class="fa fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa fa-save"></i> Update Responder
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAddModal() {
            document.getElementById('addModal').style.display = 'block';
        }
        
        function openEditModal(respId) {
            // Load responder data via AJAX or pre-filled data
            window.location.href = '?edit=' + respId;
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
        
        // Pre-fill edit modal if editing
        <?php if ($edit_responder): ?>
            document.addEventListener('DOMContentLoaded', function() {
                document.getElementById('edit_resp_id').value = '<?php echo $edit_responder['resp_id']; ?>';
                document.getElementById('edit_name').value = '<?php echo htmlspecialchars($edit_responder['resp_name']); ?>';
                document.getElementById('edit_email').value = '<?php echo htmlspecialchars($edit_responder['resp_email']); ?>';
                document.getElementById('edit_contact').value = '<?php echo htmlspecialchars($edit_responder['resp_contact']); ?>';
                document.getElementById('edit_status').value = '<?php echo $edit_responder['status']; ?>';
                document.getElementById('editModal').style.display = 'block';
            });
        <?php endif; ?>
    </script>
</body>
</html>
