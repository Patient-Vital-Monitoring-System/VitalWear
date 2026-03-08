<?php
session_start();
require_once '../../../database/connection.php';

// Check if admin user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../../login.html');
    exit();
}

$conn = getDBConnection();
$message = '';
$alert_type = '';

// Handle form submissions for user management
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add_user') {
            $role = $_POST['role'];
            $name = trim($_POST['name']);
            $email = trim($_POST['email']);
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $contact = trim($_POST['contact'] ?? '');
            
            // Insert based on role
            switch ($role) {
                case 'admin':
                    $stmt = $conn->prepare("INSERT INTO admin (admin_name, admin_email, admin_password) VALUES (?, ?, ?)");
                    $stmt->bind_param("sss", $name, $email, $password);
                    break;
                case 'management':
                    $stmt = $conn->prepare("INSERT INTO management (mgmt_name, mgmt_email, mgmt_password) VALUES (?, ?, ?)");
                    $stmt->bind_param("sss", $name, $email, $password);
                    break;
                case 'responder':
                    $stmt = $conn->prepare("INSERT INTO responder (resp_name, resp_email, resp_password, resp_contact) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("ssss", $name, $email, $password, $contact);
                    break;
                case 'rescuer':
                    $stmt = $conn->prepare("INSERT INTO rescuer (resc_name, resc_email, resc_password, resc_contact) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("ssss", $name, $email, $password, $contact);
                    break;
            }
            
            if ($stmt->execute()) {
                $message = "User added successfully!";
                $alert_type = "success";
                
                // Log activity
                $log_desc = "Admin added new $role user: $name ($email)";
                $stmt_log = $conn->prepare("INSERT INTO activity_log (user_name, user_role, action_type, module, description) VALUES (?, ?, 'add', 'user_management', ?)");
                $stmt_log->bind_param("sss", $_SESSION['user_name'], $_SESSION['user_role'], $log_desc);
                $stmt_log->execute();
            } else {
                $message = "Error adding user: " . $conn->error;
                $alert_type = "danger";
            }
        } elseif ($_POST['action'] === 'toggle_status') {
            $role = $_POST['role'];
            $user_id = $_POST['user_id'];
            $status = $_POST['status'];
            
            // Update based on role
            switch ($role) {
                case 'responder':
                    $stmt = $conn->prepare("UPDATE responder SET status = ? WHERE resp_id = ?");
                    $stmt->bind_param("si", $status, $user_id);
                    break;
                case 'rescuer':
                    $stmt = $conn->prepare("UPDATE rescuer SET status = ? WHERE resc_id = ?");
                    $stmt->bind_param("si", $status, $user_id);
                    break;
            }
            
            if ($stmt->execute()) {
                $message = "User status updated successfully!";
                $alert_type = "success";
            } else {
                $message = "Error updating status: " . $conn->error;
                $alert_type = "danger";
            }
        } elseif ($_POST['action'] === 'delete_user') {
            $role = $_POST['role'];
            $user_id = $_POST['user_id'];
            
            // Delete based on role (with safety checks)
            switch ($role) {
                case 'admin':
                    // Don't allow deleting the last admin
                    $admin_count = $conn->query("SELECT COUNT(*) as count FROM admin")->fetch_assoc()['count'];
                    if ($admin_count > 1) {
                        $stmt = $conn->prepare("DELETE FROM admin WHERE admin_id = ?");
                        $stmt->bind_param("i", $user_id);
                    } else {
                        $message = "Cannot delete the last admin user!";
                        $alert_type = "danger";
                    }
                    break;
                case 'management':
                    $stmt = $conn->prepare("DELETE FROM management WHERE mgmt_id = ?");
                    $stmt->bind_param("i", $user_id);
                    break;
                case 'responder':
                    $stmt = $conn->prepare("DELETE FROM responder WHERE resp_id = ?");
                    $stmt->bind_param("i", $user_id);
                    break;
                case 'rescuer':
                    $stmt = $conn->prepare("DELETE FROM rescuer WHERE resc_id = ?");
                    $stmt->bind_param("i", $user_id);
                    break;
            }
            
            if (isset($stmt) && $stmt->execute()) {
                $message = "User deleted successfully!";
                $alert_type = "success";
            } elseif (empty($message)) {
                $message = "Error deleting user: " . $conn->error;
                $alert_type = "danger";
            }
        }
    }
}

// Get all users by role
$users = [
    'admin' => [],
    'management' => [],
    'responder' => [],
    'rescuer' => []
];

// Admin users
$result = $conn->query("SELECT admin_id as user_id, admin_name as name, admin_email as email, created_at FROM admin ORDER BY created_at DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $row['role'] = 'admin';
        $users['admin'][] = $row;
    }
}

// Management users
$result = $conn->query("SELECT mgmt_id as user_id, mgmt_name as name, mgmt_email as email, created_at FROM management ORDER BY created_at DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $row['role'] = 'management';
        $users['management'][] = $row;
    }
}

// Responder users
$result = $conn->query("SELECT resp_id as user_id, resp_name as name, resp_email as email, resp_contact as contact, status, created_at FROM responder ORDER BY created_at DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $row['role'] = 'responder';
        $users['responder'][] = $row;
    }
}

// Rescuer users
$result = $conn->query("SELECT resc_id as user_id, resc_name as name, resc_email as email, resc_contact as contact, status, created_at FROM rescuer ORDER BY created_at DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $row['role'] = 'rescuer';
        $users['rescuer'][] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - VitalWear Admin</title>
    <link rel="stylesheet" href="../../../assets/css/styles.css">
    <style>
        .admin-header {
            background: #dc3545;
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
        .btn-danger { background: #dc3545; color: white; }
        .btn-warning { background: #ffc107; color: black; }
        .btn-success { background: #28a745; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        
        .btn:hover { opacity: 0.9; }
        
        .users-container {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .role-section {
            margin-bottom: 40px;
        }
        
        .role-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #eee;
        }
        
        .role-title {
            font-size: 24px;
            font-weight: bold;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .role-badge {
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .role-admin { background: #dc3545; color: white; }
        .role-management { background: #007bff; color: white; }
        .role-responder { background: #28a745; color: white; }
        .role-rescuer { background: #ffc107; color: black; }
        
        .user-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        .user-table th, .user-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .user-table th {
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
        
        .actions {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        
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
        
        .user-count {
            background: #f8f9fa;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="admin-header">
            <div>
                <h1 style="margin: 0;">👥 User Management</h1>
                <p style="margin: 5px 0 0 0; opacity: 0.9;">Manage all system users across roles</p>
            </div>
            <div>
                <a href="dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
                <button class="btn btn-primary" onclick="openAddModal()">+ Add User</button>
            </div>
        </header>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $alert_type; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="users-container">
            <!-- Admin Users -->
            <div class="role-section">
                <div class="role-header">
                    <div class="role-title">
                        👑 Administrators
                        <span class="role-badge role-admin">Admin</span>
                        <span class="user-count"><?php echo count($users['admin']); ?> users</span>
                    </div>
                </div>
                <table class="user-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users['admin'] as $user): ?>
                            <tr>
                                <td><?php echo $user['user_id']; ?></td>
                                <td><?php echo htmlspecialchars($user['name']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <div class="actions">
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="action" value="delete_user">
                                            <input type="hidden" name="role" value="admin">
                                            <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                            <button type="submit" class="btn btn-danger" onclick="return confirm('Delete this admin user?')" 
                                                    <?php echo count($users['admin']) <= 1 ? 'disabled' : ''; ?>>
                                                Delete
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Management Users -->
            <div class="role-section">
                <div class="role-header">
                    <div class="role-title">
                        📋 Management
                        <span class="role-badge role-management">Management</span>
                        <span class="user-count"><?php echo count($users['management']); ?> users</span>
                    </div>
                </div>
                <table class="user-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users['management'] as $user): ?>
                            <tr>
                                <td><?php echo $user['user_id']; ?></td>
                                <td><?php echo htmlspecialchars($user['name']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <div class="actions">
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="action" value="delete_user">
                                            <input type="hidden" name="role" value="management">
                                            <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                            <button type="submit" class="btn btn-danger" onclick="return confirm('Delete this management user?')">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Responder Users -->
            <div class="role-section">
                <div class="role-header">
                    <div class="role-title">
                        🚑 Responders
                        <span class="role-badge role-responder">Responder</span>
                        <span class="user-count"><?php echo count($users['responder']); ?> users</span>
                    </div>
                </div>
                <table class="user-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Contact</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users['responder'] as $user): ?>
                            <tr>
                                <td><?php echo $user['user_id']; ?></td>
                                <td><?php echo htmlspecialchars($user['name']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo htmlspecialchars($user['contact'] ?: 'N/A'); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $user['status']; ?>">
                                        <?php echo ucfirst($user['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <div class="actions">
                                        <?php if ($user['status'] === 'active'): ?>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="role" value="responder">
                                                <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                                <input type="hidden" name="status" value="inactive">
                                                <button type="submit" class="btn btn-warning">Deactivate</button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="role" value="responder">
                                                <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                                <input type="hidden" name="status" value="active">
                                                <button type="submit" class="btn btn-success">Activate</button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="action" value="delete_user">
                                            <input type="hidden" name="role" value="responder">
                                            <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                            <button type="submit" class="btn btn-danger" onclick="return confirm('Delete this responder?')">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Rescuer Users -->
            <div class="role-section">
                <div class="role-header">
                    <div class="role-title">
                        🆘 Rescuers
                        <span class="role-badge role-rescuer">Rescuer</span>
                        <span class="user-count"><?php echo count($users['rescuer']); ?> users</span>
                    </div>
                </div>
                <table class="user-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Contact</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users['rescuer'] as $user): ?>
                            <tr>
                                <td><?php echo $user['user_id']; ?></td>
                                <td><?php echo htmlspecialchars($user['name']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo htmlspecialchars($user['contact'] ?: 'N/A'); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $user['status']; ?>">
                                        <?php echo ucfirst($user['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <div class="actions">
                                        <?php if ($user['status'] === 'active'): ?>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="role" value="rescuer">
                                                <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                                <input type="hidden" name="status" value="inactive">
                                                <button type="submit" class="btn btn-warning">Deactivate</button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="role" value="rescuer">
                                                <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                                <input type="hidden" name="status" value="active">
                                                <button type="submit" class="btn btn-success">Activate</button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="action" value="delete_user">
                                            <input type="hidden" name="role" value="rescuer">
                                            <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                            <button type="submit" class="btn btn-danger" onclick="return confirm('Delete this rescuer?')">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add User Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <h2>Add New User</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add_user">
                
                <div class="form-group">
                    <label for="role">Role:</label>
                    <select id="role" name="role" required>
                        <option value="">Select Role</option>
                        <option value="admin">Admin</option>
                        <option value="management">Management</option>
                        <option value="responder">Responder</option>
                        <option value="rescuer">Rescuer</option>
                    </select>
                </div>
                
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
                
                <div class="form-group" id="contactGroup" style="display:none;">
                    <label for="contact">Contact Number:</label>
                    <input type="text" id="contact" name="contact">
                </div>
                
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add User</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAddModal() {
            document.getElementById('addModal').style.display = 'block';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        // Show/hide contact field based on role
        document.getElementById('role').addEventListener('change', function() {
            const contactGroup = document.getElementById('contactGroup');
            const role = this.value;
            
            if (role === 'responder' || role === 'rescuer') {
                contactGroup.style.display = 'block';
            } else {
                contactGroup.style.display = 'none';
            }
        });
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>