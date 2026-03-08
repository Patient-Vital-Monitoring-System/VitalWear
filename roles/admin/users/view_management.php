<?php
session_start();
require_once '../../../database/connection.php';

// Check if admin user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /VitalWear-1/login.html');
    exit();
}

$conn = getDBConnection();

// Get all management accounts (read-only)
$management_accounts = [];
$error_message = '';

try {
    $result = $conn->query("
        SELECT mgmt_id as id, mgmt_name as name, mgmt_email as email, 
               NULL as contact, 'management' as role, 'active' as status, created_at
        FROM management 
        ORDER BY mgmt_name
    ");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $management_accounts[] = $row;
        }
    }
} catch (Exception $e) {
    $error_message = "Error fetching management accounts: " . $e->getMessage();
}

// Get management statistics
$total_management = count($management_accounts);
$active_management = $total_management; // All management are considered active
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Management Accounts - VitalWear Admin</title>
    <link rel="stylesheet" href="../../../assets/css/admin.css">
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
                    <a href="../dashboard.php" class="nav-item">
                        🏠 Dashboard
                    </a>
                </div>
                
                <div class="nav-group">
                    <div class="nav-group-title">User Management</div>
                    <div class="nav-group-items">
                        <a href="../users.php" class="nav-item">
                            👥 Staff Directory
                        </a>
                        <a href="view_management.php" class="nav-item active">
                            👨‍💼 Management
                        </a>
                        <a href="view_responders.php" class="nav-item">
                            🚑 Responders
                        </a>
                        <a href="view_rescuers.php" class="nav-item">
                            🆘 Rescuers
                        </a>
                        <a href="view_admins.php" class="nav-item">
                            👨‍💻 Admins
                        </a>
                    </div>
                </div>
                
                <div class="nav-group">
                    <div class="nav-group-title">Reports</div>
                    <div class="nav-group-items">
                        <a href="../system_reports.php" class="nav-item">
                            📊 System Reports
                        </a>
                        <a href="../vitals_analytics.php" class="nav-item">
                            ❤️ Vital Analytics
                        </a>
                        <a href="../audit_log.php" class="nav-item">
                            📋 Activity Log
                        </a>
                    </div>
                </div>
                
                <div class="nav-group">
                    <div class="nav-group-title">Monitoring</div>
                    <div class="nav-group-items">
                        <a href="../device_incidents.php" class="nav-item">
                            📦 Device Overview
                        </a>
                        <a href="../vitals.php" class="nav-item">
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
                    <h1 class="navbar-brand">← Back to Admin Dashboard</h1>
                </div>
                <div class="navbar-actions">
                    <span class="text-muted">Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                    <a href="/VitalWear-1/api/auth/logout.php" class="btn btn-secondary">Logout</a>
                </div>
            </header>

            <!-- Page Content -->
            <div class="content">
                <div class="content-header">
                    <h1 class="content-title">👔 View Management Accounts</h1>
                    <p class="content-subtitle">Read-only overview of all management users in the system</p>
                </div>

                <!-- Statistics -->
                <div class="metrics-grid">
                    <div class="metric-card">
                        <div class="metric-icon">👁</div>
                        <div class="metric-value">View-Only Access</div>
                        <div class="metric-label">Permission Level</div>
                    </div>
                    
                    <div class="metric-card">
                        <div class="metric-icon">👥</div>
                        <div class="metric-value"><?php echo number_format($total_management); ?></div>
                        <div class="metric-label">Total Management Users</div>
                    </div>
                    
                    <div class="metric-card">
                        <div class="metric-icon">✅</div>
                        <div class="metric-value"><?php echo number_format($active_management); ?></div>
                        <div class="metric-label">Currently Active</div>
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

                <!-- Management Accounts Table -->
                <div class="card">
                    <div class="card-header">
                        Management ID | Name | Email Address | Contact Number | Created Date
                    </div>
                    <div class="card-body">
                        <div class="table" style="overflow-x: auto;">
                            <table style="width: 100%; min-width: 800px;">
                                <thead>
                                    <tr>
                                        <th style="width: 15%;">Management ID</th>
                                        <th style="width: 20%;">Name</th>
                                        <th style="width: 25%;">Email Address</th>
                                        <th style="width: 15%;">Contact Number</th>
                                        <th style="width: 25%;">Created Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($management_accounts)): ?>
                                        <?php foreach ($management_accounts as $account): ?>
                                        <tr style="border-bottom: 1px solid var(--border);">
                                            <td style="padding: 1rem; vertical-align: middle;">
                                                <div style="font-weight: 600; color: var(--accent);">
                                                    #<?php echo htmlspecialchars($account['id']); ?>
                                                </div>
                                            </td>
                                            <td style="padding: 1rem; vertical-align: middle;">
                                                <div style="font-weight: 500; color: var(--text);">
                                                    <?php echo htmlspecialchars($account['name'] ?? 'N/A'); ?>
                                                </div>
                                            </td>
                                            <td style="padding: 1rem; vertical-align: middle;">
                                                <div style="color: var(--text-secondary); word-break: break-word;">
                                                    <?php echo htmlspecialchars($account['email'] ?? 'N/A'); ?>
                                                </div>
                                            </td>
                                            <td style="padding: 1rem; vertical-align: middle;">
                                                <div style="color: var(--text-secondary);">
                                                    <?php 
                                                    if (!empty($account['contact']) && $account['contact'] !== 'NULL') {
                                                        echo htmlspecialchars($account['contact']);
                                                    } else {
                                                        echo '<span style="color: var(--muted); font-style: italic;">Not provided</span>';
                                                    }
                                                    ?>
                                                </div>
                                            </td>
                                            <td style="padding: 1rem; vertical-align: middle;">
                                                <div style="color: var(--muted); font-size: 0.875rem; font-family: 'Inter', monospace;">
                                                    <?php 
                                                    if (!empty($account['created_at']) && $account['created_at'] !== 'NULL') {
                                                        echo date('M j, Y H:i:s', strtotime($account['created_at']));
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
                                            <td colspan="5" style="text-align: center; padding: 3rem; color: var(--muted);">
                                                <div style="font-size: 1.1rem; margin-bottom: 0.5rem;">No Management Accounts Found</div>
                                                <div style="font-size: 0.9rem;">There are currently no management accounts in the system.</div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Footer Info -->
                <div style="text-align: center; margin-top: 2rem; padding: 2rem; color: var(--muted); border-top: 1px solid var(--border);">
                    <p style="margin: 0;">Admin Access - Read Only | Management account overview for VitalWear IoT Health Monitoring Platform</p>
                </div>

            </div>
        </main>
    </div>
</body>
</html>
