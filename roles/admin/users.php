<?php
session_start();
require_once '../../database/connection.php';

// Check if admin user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /VitalWear-1/login.html');
    exit();
}

$conn = getDBConnection();

// Get all users from different tables
$users = [];
$error_message = '';

// Get admin users
try {
    $result = $conn->query("SELECT admin_id as id, admin_name as name, admin_email as email, 'admin' as role, NULL as contact, NULL as status, created_at FROM admin");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $row['status'] = 'active'; // Admins are always active
            $users[] = $row;
        }
    }
} catch (Exception $e) {
    $error_message .= "Error fetching admin users: " . $e->getMessage() . "<br>";
}

// Get management users
try {
    $result = $conn->query("SELECT mgmt_id as id, mgmt_name as name, mgmt_email as email, 'management' as role, NULL as contact, NULL as status, created_at FROM management");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $row['status'] = 'active'; // Management are always active
            $users[] = $row;
        }
    }
} catch (Exception $e) {
    $error_message .= "Error fetching management users: " . $e->getMessage() . "<br>";
}

// Get responder users
try {
    $result = $conn->query("SELECT resp_id as id, resp_name as name, resp_email as email, resp_contact as contact, 'responder' as role, status, created_at FROM responder");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
    }
} catch (Exception $e) {
    $error_message .= "Error fetching responder users: " . $e->getMessage() . "<br>";
}

// Get rescuer users
try {
    $result = $conn->query("SELECT resc_id as id, resc_name as name, resc_email as email, resc_contact as contact, 'rescuer' as role, status, created_at FROM rescuer");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
    }
} catch (Exception $e) {
    $error_message .= "Error fetching rescuer users: " . $e->getMessage() . "<br>";
}

// Sort users by role and name
usort($users, function($a, $b) {
    if ($a['role'] !== $b['role']) {
        $role_order = ['admin' => 0, 'management' => 1, 'responder' => 2, 'rescuer' => 3];
        return $role_order[$a['role']] - $role_order[$b['role']];
    }
    return strcmp($a['name'], $b['name']);
});

// Get statistics
$stats = [
    'total_users' => count($users),
    'admin_count' => 0,
    'management_count' => 0,
    'responder_count' => 0,
    'rescuer_count' => 0,
    'active_responders' => 0,
    'active_rescuers' => 0
];

foreach ($users as $user) {
    $stats[$user['role'] . '_count']++;
    if ($user['role'] === 'responder' && ($user['status'] ?? 'active') === 'active') {
        $stats['active_responders']++;
    }
    if ($user['role'] === 'rescuer' && ($user['status'] ?? 'active') === 'active') {
        $stats['active_rescuers']++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Admin</title>
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="admin-layout">
        <!-- Sidebar -->
        <aside class="admin-sidebar">
            <div class="sidebar-header">
                <div class="sidebar-title">VitalWear Admin</div>
                <div class="sidebar-subtitle">System Management</div>
            </div>
            
            <nav class="nav-menu">
                <div class="nav-group">
                    <a href="dashboard.php" class="nav-item">
                        🏠 Dashboard
                    </a>
                </div>
                
                <div class="nav-group">
                    <div class="nav-group-title">User Management</div>
                    <div class="nav-group-items">
                        <a href="users.php" class="nav-item active">
                            👥 Staff Directory
                        </a>
                        <a href="users/view_management.php" class="nav-item">
                            👨‍💼 Management
                        </a>
                        <a href="users/view_responders.php" class="nav-item">
                            🚑 Responders
                        </a>
                        <a href="users/view_rescuers.php" class="nav-item">
                            🆘 Rescuers
                        </a>
                        <a href="users/view_admins.php" class="nav-item">
                            👨‍💻 Admins
                        </a>
                    </div>
                </div>
                
                <div class="nav-group">
                    <div class="nav-group-title">Reports</div>
                    <div class="nav-group-items">
                        <a href="system_reports.php" class="nav-item">
                            📊 System Reports
                        </a>
                        <a href="vitals_analytics.php" class="nav-item">
                            ❤️ Vital Analytics
                        </a>
                        <a href="audit_log.php" class="nav-item">
                            📋 Activity Log
                        </a>
                    </div>
                </div>
                
                <div class="nav-group">
                    <div class="nav-group-title">Monitoring</div>
                    <div class="nav-group-items">
                        <a href="device_incidents.php" class="nav-item">
                            📦 Device Overview
                        </a>
                        <a href="vitals.php" class="nav-item">
                            👤 User Activity
                        </a>
                    </div>
                </div>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="admin-main">
            <!-- Top Navigation -->
            <header class="navbar">
                <div>
                    <h1 class="navbar-brand">User Management</h1>
                </div>
                <div class="navbar-actions">
                    <span class="text-muted">Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                    <a href="/VitalWear-1/api/auth/logout.php" class="btn btn-secondary">Logout</a>
                </div>
            </header>

            <!-- Page Content -->
            <div class="content">
                <div class="content-header">
                    <h1 class="content-title">Staff Directory</h1>
                    <p class="content-subtitle">View all system users and their information</p>
                </div>

                <!-- User Statistics -->
                <div class="metrics-grid">
                    <div class="metric-card">
                        <div class="metric-icon">👥</div>
                        <div class="metric-value"><?php echo number_format($stats['total_users']); ?></div>
                        <div class="metric-label">Total Users</div>
                    </div>
                    
                    <div class="metric-card">
                        <div class="metric-icon">👨‍💼</div>
                        <div class="metric-value"><?php echo number_format($stats['management_count']); ?></div>
                        <div class="metric-label">Management</div>
                    </div>
                    
                    <div class="metric-card">
                        <div class="metric-icon">🚑</div>
                        <div class="metric-value"><?php echo number_format($stats['responder_count']); ?></div>
                        <div class="metric-label">Responders</div>
                    </div>
                    
                    <div class="metric-card">
                        <div class="metric-icon">🆘</div>
                        <div class="metric-value"><?php echo number_format($stats['rescuer_count']); ?></div>
                        <div class="metric-label">Rescuers</div>
                    </div>
                </div>

                <!-- Additional Stats -->
                <div class="metrics-grid">
                    <div class="metric-card">
                        <div class="metric-icon">👨‍💻</div>
                        <div class="metric-value"><?php echo number_format($stats['admin_count']); ?></div>
                        <div class="metric-label">Admins</div>
                    </div>
                    
                    <div class="metric-card">
                        <div class="metric-icon">✅</div>
                        <div class="metric-value"><?php echo number_format($stats['active_responders']); ?></div>
                        <div class="metric-label">Active Responders</div>
                    </div>
                    
                    <div class="metric-card">
                        <div class="metric-icon">✅</div>
                        <div class="metric-value"><?php echo number_format($stats['active_rescuers']); ?></div>
                        <div class="metric-label">Active Rescuers</div>
                    </div>
                </div>

                <!-- Error Display -->
                <?php if (!empty($error_message)): ?>
                <div class="card" style="margin-bottom: 2rem;">
                    <div class="card-header" style="background: linear-gradient(135deg, rgba(239, 68, 68, 0.1) 0%, rgba(220, 38, 38, 0.1) 100%); color: var(--danger);">
                        Database Connection Issues
                    </div>
                    <div class="card-body">
                        <div style="color: var(--danger); padding: 1rem; background: rgba(239, 68, 68, 0.05); border-radius: var(--radius); border: 1px solid rgba(239, 68, 68, 0.2);">
                            <?php echo $error_message; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- User List -->
                <div class="card">
                    <div class="card-header">
                        All Users Directory (<?php echo count($users); ?> users found)
                    </div>
                    <div class="card-body">
                        <div class="table" style="overflow-x: auto;">
                            <table style="width: 100%; min-width: 800px;">
                                <thead>
                                    <tr>
                                        <th style="width: 20%;">Name</th>
                                        <th style="width: 25%;">Email</th>
                                        <th style="width: 15%;">Role</th>
                                        <th style="width: 15%;">Contact</th>
                                        <th style="width: 10%;">Status</th>
                                        <th style="width: 15%;">Joined</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($users)): ?>
                                        <?php foreach ($users as $user): ?>
                                        <tr style="border-bottom: 1px solid var(--border);">
                                            <td style="padding: 1rem; vertical-align: middle;">
                                                <div style="font-weight: 600; color: var(--text);">
                                                    <?php echo htmlspecialchars($user['name'] ?? 'N/A'); ?>
                                                </div>
                                            </td>
                                            <td style="padding: 1rem; vertical-align: middle;">
                                                <div style="color: var(--text-secondary); word-break: break-word;">
                                                    <?php echo htmlspecialchars($user['email'] ?? 'N/A'); ?>
                                                </div>
                                            </td>
                                            <td style="padding: 1rem; vertical-align: middle;">
                                                <span class="badge badge-<?php echo $user['role']; ?>">
                                                    <?php 
                                                    $role_display = ucfirst($user['role']);
                                                    echo $role_display === 'Management' ? 'Staff' : $role_display;
                                                    ?>
                                                </span>
                                            </td>
                                            <td style="padding: 1rem; vertical-align: middle;">
                                                <div style="color: var(--text-secondary);">
                                                    <?php 
                                                    if (!empty($user['contact']) && $user['contact'] !== 'NULL') {
                                                        echo htmlspecialchars($user['contact']);
                                                    } else {
                                                        echo '<span style="color: var(--muted); font-style: italic;">Not provided</span>';
                                                    }
                                                    ?>
                                                </div>
                                            </td>
                                            <td style="padding: 1rem; vertical-align: middle;">
                                                <span class="badge badge-<?php echo ($user['status'] ?? 'active') === 'active' ? 'success' : 'warning'; ?>">
                                                    <?php echo htmlspecialchars(ucfirst($user['status'] ?? 'active')); ?>
                                                </span>
                                            </td>
                                            <td style="padding: 1rem; vertical-align: middle;">
                                                <div style="color: var(--muted); font-size: 0.875rem; font-family: 'Inter', monospace;">
                                                    <?php 
                                                    if (!empty($user['created_at']) && $user['created_at'] !== 'NULL') {
                                                        echo date('M j, Y', strtotime($user['created_at']));
                                                    } else {
                                                        echo 'N/A';
                                                    }
                                                    ?>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" style="text-align: center; padding: 3rem; color: var(--muted);">
                                                <div style="font-size: 1.1rem; margin-bottom: 0.5rem;">No users found in the system</div>
                                                <div style="font-size: 0.9rem;">Please check the database connection or add users through the appropriate channels</div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Quick Info -->
                <div class="card" style="margin-top: 2rem;">
                    <div class="card-header">
                        System Information
                    </div>
                    <div class="card-body">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem;">
                            <div>
                                <h4 style="color: var(--text); margin-bottom: 1rem;">User Roles Overview</h4>
                                <ul style="list-style: none; padding: 0;">
                                    <li style="padding: 0.5rem 0; border-bottom: 1px solid var(--border);">
                                        <strong>Admins:</strong> System administrators with full access
                                    </li>
                                    <li style="padding: 0.5rem 0; border-bottom: 1px solid var(--border);">
                                        <strong>Management:</strong> Staff who manage devices and operations
                                    </li>
                                    <li style="padding: 0.5rem 0; border-bottom: 1px solid var(--border);">
                                        <strong>Responders:</strong> First responders who handle incidents
                                    </li>
                                    <li style="padding: 0.5rem 0;">
                                        <strong>Rescuers:</strong> Medical rescue team members
                                    </li>
                                </ul>
                            </div>
                            <div>
                                <h4 style="color: var(--text); margin-bottom: 1rem;">Status Information</h4>
                                <ul style="list-style: none; padding: 0;">
                                    <li style="padding: 0.5rem 0; border-bottom: 1px solid var(--border);">
                                        <strong>Active:</strong> User is currently active in the system
                                    </li>
                                    <li style="padding: 0.5rem 0; border-bottom: 1px solid var(--border);">
                                        <strong>Inactive:</strong> User account is temporarily disabled
                                    </li>
                                    <li style="padding: 0.5rem 0;">
                                        <strong>Contact:</strong> Phone number for field staff members
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </main>
    </div>
</body>
</html>
