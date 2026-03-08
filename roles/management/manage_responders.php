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
        
        .table-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        th {
            background: #f8f9fa;
            font-weight: bold;
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .status-active { background: #d4edda; color: #155724; }
        .status-inactive { background: #f8d7da; color: #721c24; }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 30px;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .form-group input, .form-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
        .actions {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        
        .device-info {
            font-size: 12px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="page-header">
            <div>
                <h1 style="margin: 0;">👥 Manage Responders</h1>
                <p style="margin: 5px 0 0 0; opacity: 0.9;">Add, edit, and manage responder accounts</p>
            </div>
            <div>
                <a href="dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
                <button class="btn btn-primary" onclick="openAddModal()">+ Add Responder</button>
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
    </div>

    <!-- Add Responder Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <h2>Add New Responder</h2>
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
                
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Responder</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Responder Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <h2>Edit Responder</h2>
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
                
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Responder</button>
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
