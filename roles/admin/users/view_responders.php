<?php
session_start();
require_once '../../../database/connection.php';

// Check if admin user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /VitalWear-1/login.html');
    exit();
}

$conn = getDBConnection();

// Get all responder accounts (read-only)
$responder_accounts = [];
$error_message = '';
$success_message = '';

// Check for URL parameters
if (isset($_GET['error'])) {
    $error_message = urldecode($_GET['error']);
}
if (isset($_GET['success'])) {
    $success_message = urldecode($_GET['success']);
}

try {
    $result = $conn->query("
        SELECT resp_id as id, resp_name as name, resp_email as email, 
               resp_contact as contact, 'responder' as role, status, created_at
        FROM responder 
        ORDER BY resp_name
    ");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $responder_accounts[] = $row;
        }
    }
} catch (Exception $e) {
    $error_message = "Error fetching responder accounts: " . $e->getMessage();
}

// Get responder statistics
$total_responders = count($responder_accounts);
$active_responders = 0;
foreach ($responder_accounts as $responder) {
    if (($responder['status'] ?? 'active') === 'active') {
        $active_responders++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Responder Accounts - VitalWear Admin</title>
    <script src="https://kit.fontawesome.com/96e37b53f1.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* VitalWear Modern Soft UI Design System */
        :root {
            /* Primary Colors - Modern Blue Palette */
            --primary-50: #E8F4FD;
            --primary-100: #D1E9FB;
            --primary-200: #A9D9F5;
            --primary-300: #7BC4F0;
            --primary-400: #4DAEEA;
            --primary-500: #2E96D5;
            --primary-600: #1E7AB8;
            --primary-700: #1A5F9A;
            --primary-800: #1A4975;
            --primary-900: #1A3A5C;
            
            /* Neutral Colors */
            --gray-50: #F9FAFB;
            --gray-100: #F3F4F6;
            --gray-200: #E5E7EB;
            --gray-300: #D1D5DB;
            --gray-400: #9CA3AF;
            --gray-500: #6B7280;
            --gray-600: #4B5563;
            --gray-700: #374151;
            --gray-800: #1F2937;
            --gray-900: #111827;
            
            /* Semantic Colors */
            --success: #10B981;
            --success-light: #D1FAE5;
            --warning: #F59E0B;
            --warning-light: #FEF3C7;
            --error: #EF4444;
            --error-light: #FEE2E2;
            --info: #3B82F6;
            --info-light: #DBEAFE;
            
            /* Core Design Tokens */
            --primary: var(--primary-600);
            --primary-light: var(--primary-100);
            --background: var(--gray-50);
            --surface: var(--pure-white);
            --text-primary: var(--gray-900);
            --text-secondary: var(--gray-600);
            --text-tertiary: var(--gray-500);
            --border: var(--gray-200);
            --border-hover: var(--gray-300);
            
            /* Soft UI Radius System */
            --radius-xs: 4px;
            --radius-sm: 6px;
            --radius: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
            --radius-xl: 20px;
            --radius-2xl: 24px;
            --radius-full: 9999px;
            
            /* Modern Shadow System */
            --shadow-xs: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-sm: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-md: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            --shadow-xl: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            
            /* Transitions */
            --transition-fast: 150ms cubic-bezier(0.4, 0, 0.2, 1);
            --transition: 200ms cubic-bezier(0.4, 0, 0.2, 1);
            --transition-slow: 300ms cubic-bezier(0.4, 0, 0.2, 1);
        }

        body {
            background: var(--background);
            color: var(--text-primary);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            margin: 0;
            padding: 0;
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        /* Modern Soft UI Sidebar */
        .admin-sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 280px;
            height: 100vh;
            background: var(--surface);
            border-right: 1px solid var(--border);
            box-shadow: var(--shadow-lg);
            z-index: 1000;
            overflow-y: auto;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }

        .sidebar-header {
            padding: 32px 24px 24px;
            text-align: center;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-700) 100%);
            margin: 16px;
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-md);
            position: relative;
            overflow: hidden;
        }

        .sidebar-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.1) 0%, transparent 100%);
            pointer-events: none;
        }

        .sidebar-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: white;
            margin-bottom: 4px;
            position: relative;
            z-index: 1;
        }

        .sidebar-subtitle {
            font-size: 0.875rem;
            color: rgba(255, 255, 255, 0.8);
            position: relative;
            z-index: 1;
        }

        .nav-menu {
            padding: 16px;
        }

        .nav-group {
            margin-bottom: 24px;
        }

        .nav-group-title {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-tertiary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 0 16px 8px;
            padding: 8px 0;
        }

        .nav-group-items {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .nav-item {
            color: var(--text-primary);
            padding: 12px 16px;
            border-radius: var(--radius-lg);
            transition: all var(--transition);
            border: none;
            font-weight: 500;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 12px;
            position: relative;
            overflow: hidden;
        }

        .nav-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
            transition: left var(--transition-slow);
        }

        .nav-item:hover {
            background: var(--primary-light);
            color: var(--primary);
            transform: translateX(6px);
            box-shadow: var(--shadow);
        }

        .nav-item:hover::before {
            left: 100%;
        }

        .nav-item.active {
            background: var(--primary);
            color: white;
            box-shadow: var(--shadow-md);
            transform: translateX(4px);
        }

        .nav-item.active::before {
            display: none;
        }

        /* Modern Soft UI Header */
        .admin-header {
            position: fixed;
            top: 0;
            left: 280px;
            right: 0;
            background: var(--surface);
            color: var(--text-primary);
            border-bottom: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
            padding: 20px 32px;
            font-weight: 600;
            z-index: 999;
            display: flex;
            align-items: center;
            justify-content: space-between;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }

        /* Main Container */
        .admin-main {
            max-width: 1400px;
            margin: 0 auto;
            padding: 32px;
            margin-left: 280px;
            margin-top: 80px;
        }

        /* Header Styles */
        .page-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-700) 100%);
            color: white;
            padding: 40px;
            border-radius: var(--radius-xl);
            margin-bottom: 32px;
            box-shadow: var(--shadow-lg);
        }

        .page-header h1 {
            font-size: 2rem;
            font-weight: 700;
            margin: 0 0 8px 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .page-header p {
            margin: 0;
            opacity: 0.9;
            font-size: 1rem;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: var(--surface);
            padding: 32px;
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            text-align: center;
            transition: all var(--transition);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary) 0%, var(--primary-700) 100%);
        }

        .stat-card:hover {
            box-shadow: var(--shadow-lg);
            transform: translateY(-4px);
        }

        .stat-card h3 {
            margin: 0 0 16px 0;
            color: var(--text-secondary);
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary);
            margin: 0;
        }

        /* User Cards Grid */
        .users-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }

        .user-card {
            background: var(--surface);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            padding: 24px;
            transition: all var(--transition);
            position: relative;
            overflow: hidden;
        }

        .user-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #10B981 0%, #059669 100%);
        }

        .user-card:hover {
            box-shadow: var(--shadow-lg);
            transform: translateY(-4px);
        }

        .user-header {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 20px;
        }

        .user-avatar {
            width: 56px;
            height: 56px;
            border-radius: var(--radius-full);
            background: var(--success-light);
            color: var(--success);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.5rem;
            flex-shrink: 0;
            box-shadow: var(--shadow);
        }

        .user-info h4 {
            margin: 0 0 4px 0;
            color: var(--text-primary);
            font-weight: 600;
            font-size: 1.25rem;
        }

        .user-info p {
            margin: 0;
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        .user-details {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid var(--border);
        }

        .user-detail {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            font-size: 0.875rem;
        }

        .user-detail:last-child {
            margin-bottom: 0;
        }

        .user-detail-label {
            color: var(--text-tertiary);
            font-weight: 500;
        }

        .user-detail-value {
            color: var(--text-primary);
            font-weight: 600;
        }

        .role-badge {
            display: inline-block;
            padding: 6px 16px;
            border-radius: var(--radius-full);
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            background: var(--success-light);
            color: var(--success);
        }

        .status-badge {
            display: inline-block;
            padding: 6px 16px;
            border-radius: var(--radius-full);
            font-size: 0.75rem;
            font-weight: 600;
        }

        .status-badge.active {
            background: var(--success-light);
            color: var(--success);
        }

        .status-badge.inactive {
            background: var(--error-light);
            color: var(--error);
        }

        /* Error Message */
        .error-message {
            background: var(--error-light);
            color: var(--error);
            padding: 16px;
            border-radius: var(--radius-lg);
            margin-bottom: 24px;
            border: 1px solid var(--error);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 64px 32px;
            color: var(--text-secondary);
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 24px;
            opacity: 0.3;
        }

        .empty-state h3 {
            margin: 0 0 8px 0;
            color: var(--text-primary);
            font-size: 1.5rem;
        }

        .empty-state p {
            margin: 0;
            font-size: 1rem;
        }

        /* Breadcrumb */
        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 24px;
            font-size: 0.875rem;
        }

        .breadcrumb a {
            color: var(--text-secondary);
            text-decoration: none;
            transition: color var(--transition);
        }

        .breadcrumb a:hover {
            color: var(--primary);
        }

        .breadcrumb .separator {
            color: var(--text-tertiary);
        }

        .breadcrumb .current {
            color: var(--text-primary);
            font-weight: 600;
        }

        /* Logout Button */
        .logout-btn {
            background: var(--error);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: var(--radius-lg);
            font-weight: 600;
            cursor: pointer;
            transition: all var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .logout-btn:hover {
            background: #DC2626;
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        /* Page Header Button */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 2rem;
        }

        .page-header h1 {
            margin: 0 0 0.5rem 0;
            color: var(--text-primary);
            font-size: 2rem;
            font-weight: 700;
        }

        .page-header p {
            margin: 0;
            color: var(--text-secondary);
            font-size: 1rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: var(--radius-lg);
            font-weight: 600;
            cursor: pointer;
            transition: all var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        /* User Action Buttons */
        .user-actions {
            display: flex;
            gap: 0.75rem;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border);
        }

        .btn-update {
            background: var(--warning);
            color: white;
            font-size: 0.75rem;
            padding: 0.5rem 1rem;
        }

        .btn-update:hover {
            background: #e6a800;
            transform: translateY(-1px);
        }

        .btn-delete {
            background: var(--error);
            color: white;
            font-size: 0.75rem;
            padding: 0.5rem 1rem;
        }

        .btn-delete:hover {
            background: #DC2626;
            transform: translateY(-1px);
        }
    </style>
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
                        <i class="fa fa-gauge"></i> Dashboard
                    </a>
                </div>
                
                <div class="nav-group">
                    <div class="nav-group-title">User Management</div>
                    <div class="nav-group-items">
                        <a href="../users.php" class="nav-item">
                            <i class="fa fa-users"></i> Staff Directory
                        </a>
                        <a href="view_management.php" class="nav-item">
                            <i class="fa fa-user-tie"></i> Management
                        </a>
                        <a href="view_responders.php" class="nav-item active">
                            <i class="fa fa-user-md"></i> Responders
                        </a>
                        <a href="view_rescuers.php" class="nav-item">
                            <i class="fa fa-user-shield"></i> Rescuers
                        </a>
                        <a href="view_admins.php" class="nav-item">
                            <i class="fa fa-user-cog"></i> Admins
                        </a>
                    </div>
                </div>
                
                <div class="nav-group">
                    <div class="nav-group-title">Reports</div>
                    <div class="nav-group-items">
                        <a href="../system_reports.php" class="nav-item">
                            <i class="fa fa-chart-line"></i> System Reports
                        </a>
                        <a href="../vitals_analytics.php" class="nav-item">
                            <i class="fa fa-heartbeat"></i> Vital Analytics
                        </a>
                        <a href="../audit_log.php" class="nav-item">
                            <i class="fa fa-clipboard-list"></i> Activity Log
                        </a>
                    </div>
                </div>
                
                <div class="nav-group">
                    <div class="nav-group-title">Monitoring</div>
                    <div class="nav-group-items">
                        <a href="../device_incidents.php" class="nav-item">
                            <i class="fa fa-box"></i> Device Overview
                        </a>
                        <a href="../vitals.php" class="nav-item">
                            <i class="fa fa-user-clock"></i> User Activity
                        </a>
                    </div>
                </div>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="admin-main">
            <!-- Modern Header -->
            <header class="admin-header">
                <div>
                    <h1><i class="fa fa-user-md"></i> Responder Accounts</h1>
                </div>
                <div>
                    <span style="color: var(--text-secondary); margin-right: 16px;">Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                    <a href="/VitalWear-1/logout.php" class="logout-btn">
                        <i class="fa fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </header>

            <!-- Breadcrumb -->
            <div class="breadcrumb">
                <a href="../dashboard.php"><i class="fa fa-gauge"></i> Dashboard</a>
                <span class="separator">/</span>
                <a href="../users.php"><i class="fa fa-users"></i> Staff Directory</a>
                <span class="separator">/</span>
                <span class="current"><i class="fa fa-user-md"></i> Responders</span>
            </div>

            <!-- Page Header -->
            <div class="page-header">
                <div>
                    <h1><i class="fa fa-user-shield"></i> Responder Accounts</h1>
                    <p>Manage all responder users in the system</p>
                </div>
                <button onclick="openAddModal()" class="btn btn-primary">
                    <i class="fa fa-plus"></i> Create Responder
                </button>
            </div>

            <?php if ($error_message): ?>
                <div class="error-message">
                    <i class="fa fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <?php if ($success_message): ?>
                <div class="success-message">
                    <i class="fa fa-check-circle"></i>
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>View-Only Access</h3>
                    <div class="stat-number" style="font-size: 1.5rem;">👁</div>
                </div>
                
                <div class="stat-card">
                    <h3>Total Responders</h3>
                    <div class="stat-number"><?php echo number_format($total_responders); ?></div>
                </div>
                
                <div class="stat-card">
                    <h3>Active Users</h3>
                    <div class="stat-number"><?php echo number_format($active_responders); ?></div>
                </div>
            </div>

            <!-- Users Grid -->
            <div class="users-grid">
                <?php if (!empty($responder_accounts)): ?>
                    <?php foreach ($responder_accounts as $account): ?>
                        <div class="user-card" data-user-id="<?php echo htmlspecialchars($account['id']); ?>">
                            <div class="user-header">
                                <div class="user-avatar">
                                    <?php echo strtoupper(substr(htmlspecialchars($account['name']), 0, 2)); ?>
                                </div>
                                <div class="user-info">
                                    <h4 data-field="name"><?php echo htmlspecialchars($account['name']); ?></h4>
                                    <p data-field="email"><?php echo htmlspecialchars($account['email']); ?></p>
                                </div>
                            </div>
                            
                            <div class="user-details">
                                <div class="user-detail">
                                    <span class="user-detail-label">Role:</span>
                                    <span class="role-badge">Responder</span>
                                </div>
                                
                                <?php if (!empty($account['contact'])): ?>
                                    <div class="user-detail">
                                        <span class="user-detail-label">Contact:</span>
                                        <span class="user-detail-value" data-field="contact"><?php echo htmlspecialchars($account['contact']); ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="user-detail">
                                    <span class="user-detail-label">Status:</span>
                                    <span class="status-badge <?php echo htmlspecialchars($account['status'] ?? 'active'); ?>">
                                        <?php echo htmlspecialchars(ucfirst($account['status'] ?? 'active')); ?>
                                    </span>
                                </div>
                                
                                <div class="user-detail">
                                    <span class="user-detail-label">ID:</span>
                                    <span class="user-detail-value">#<?php echo htmlspecialchars($account['id']); ?></span>
                                </div>
                                
                                <div class="user-detail">
                                    <span class="user-detail-label">Joined:</span>
                                    <span class="user-detail-value"><?php echo date('M j, Y', strtotime($account['created_at'])); ?></span>
                                </div>
                            </div>
                            
                            <!-- Action Buttons -->
                            <div class="user-actions">
                                <button onclick="openUpdateModal(<?php echo htmlspecialchars(json_encode($account)); ?>)" class="btn btn-update">
                                    <i class="fa fa-edit"></i> Update
                                </button>
                                <button onclick="openDeleteModal(<?php echo htmlspecialchars($account['id']); ?>, '<?php echo htmlspecialchars($account['name']); ?>')" class="btn btn-delete">
                                    <i class="fa fa-trash"></i> Delete
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fa fa-user-md"></i>
                        <h3>No Responder Accounts Found</h3>
                        <p>There are currently no responder users in the system.</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Modal Styles -->
    <style>
        .modal {
            display: none;
            position: fixed;
            z-index: 10000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
            animation: fadeIn 0.3s ease;
        }

        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 8px 40px rgba(0, 0, 0, 0.12), 0 2px 8px rgba(0, 0, 0, 0.15);
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            animation: slideUp 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .modal-header {
            padding: 24px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--text-secondary);
            cursor: pointer;
            padding: 4px;
            border-radius: var(--radius);
            transition: all var(--transition);
        }

        .modal-close:hover {
            background: var(--gray-100);
            color: var(--text-primary);
        }

        .modal-body {
            padding: 24px;
            background: #ffffff;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 8px;
            font-size: 0.875rem;
        }

        .form-input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 0.875rem;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            background: #ffffff;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(30, 122, 184, 0.1), 0 2px 8px rgba(30, 122, 184, 0.15);
            transform: translateY(-1px);
        }

        .form-input:hover {
            border-color: #d1d5db;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
        }

        .form-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
        }

        /* Buttons */
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            font-weight: 600;
            letter-spacing: 0.025em;
            position: relative;
            overflow: hidden;
        }

        .btn-cancel {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            color: var(--text-primary);
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1), inset 0 1px 0 rgba(255, 255, 255, 0.8);
        }

        .btn-cancel::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
            transition: left 0.6s ease;
        }

        .btn-cancel:hover {
            background: linear-gradient(135deg, #e2e8f0 0%, #cbd5e1 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15), inset 0 1px 0 rgba(255, 255, 255, 0.6);
        }

        .btn-cancel:hover::before {
            left: 100%;
        }

        .btn-cancel:active {
            transform: translateY(0);
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.12);
        }

        .btn-submit {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-600) 100%);
            color: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(30, 122, 184, 0.2), inset 0 1px 0 rgba(255, 255, 255, 0.3);
        }

        .btn-submit::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
            transition: left 0.6s ease;
        }

        .btn-submit:hover {
            background: linear-gradient(135deg, var(--primary-600) 0%, var(--primary-700) 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(30, 122, 184, 0.3), inset 0 1px 0 rgba(255, 255, 255, 0.2);
        }

        .btn-submit:hover::before {
            left: 100%;
        }

        .btn-submit:active {
            transform: translateY(0);
            box-shadow: 0 2px 6px rgba(30, 122, 184, 0.25);
        }

        .btn-delete {
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
            color: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(220, 38, 38, 0.2), inset 0 1px 0 rgba(255, 255, 255, 0.3);
        }

        .btn-delete::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
            transition: left 0.6s ease;
        }

        .btn-delete:hover {
            background: linear-gradient(135deg, #b91c1c 0%, #991b1b 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3), inset 0 1px 0 rgba(255, 255, 255, 0.2);
        }

        .btn-delete:hover::before {
            left: 100%;
        }

        .btn-delete:active {
            transform: translateY(0);
            box-shadow: 0 2px 6px rgba(220, 38, 38, 0.25);
        }

        .delete-modal .modal-content {
            max-width: 400px;
        }

        .delete-warning {
            text-align: center;
            padding: 20px 0;
        }

        .delete-warning i {
            font-size: 3rem;
            color: var(--error);
            margin-bottom: 16px;
        }

        .delete-warning h3 {
            color: var(--text-primary);
            margin-bottom: 8px;
        }

        .delete-warning p {
            color: var(--text-secondary);
            margin-bottom: 24px;
        }

        .message {
            padding: 12px 16px;
            border-radius: var(--radius-md);
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .success-message {
            background: var(--success-light);
            color: var(--success);
            border: 1px solid var(--success);
        }

        .error-message {
            background: var(--error-light);
            color: var(--error);
            border: 1px solid var(--error);
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideUp {
            from { 
                transform: translateY(20px);
                opacity: 0;
            }
            to { 
                transform: translateY(0);
                opacity: 1;
            }
        }
    </style>

    <!-- Add Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="fa fa-plus"></i> Create Responder
                </h3>
                <button class="modal-close" onclick="closeModal('addModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div id="addMessage"></div>
                <form action="create_responder.php" method="POST">
                    <div class="form-group">
                        <label class="form-label">Full Name *</label>
                        <input type="text" name="name" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email Address *</label>
                        <input type="email" name="email" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Contact Number</label>
                        <input type="tel" name="contact" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Password *</label>
                        <input type="password" name="password" class="form-input" autocomplete="new-password" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Confirm Password *</label>
                        <input type="password" name="confirm_password" class="form-input" autocomplete="new-password" required>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn btn-cancel" onclick="closeModal('addModal')">Cancel</button>
                        <button type="submit" class="btn btn-submit">Create Responder</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Update Modal -->
    <div id="updateModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="fa fa-edit"></i> Update Responder
                </h3>
                <button class="modal-close" onclick="closeModal('updateModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div id="updateMessage"></div>
                <form id="updateForm" onsubmit="handleUpdateSubmit(event, 'responder')">
                    <input type="hidden" name="id" id="updateId">
                    <div class="form-group">
                        <label class="form-label">Full Name *</label>
                        <input type="text" name="name" id="updateName" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email Address *</label>
                        <input type="email" name="email" id="updateEmail" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Contact Number</label>
                        <input type="tel" name="contact" id="updateContact" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">New Password</label>
                        <input type="password" name="password" id="updatePassword" class="form-input" placeholder="Leave blank to keep current password" autocomplete="new-password">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Confirm New Password</label>
                        <input type="password" name="confirm_password" id="updateConfirmPassword" class="form-input" placeholder="Confirm new password" autocomplete="new-password">
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn btn-cancel" onclick="closeModal('updateModal')">Cancel</button>
                        <button type="submit" class="btn btn-submit">Update Responder</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Modal -->
    <div id="deleteModal" class="modal delete-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="fa fa-trash"></i> Delete Responder
                </h3>
                <button class="modal-close" onclick="closeModal('deleteModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="delete-warning">
                    <i class="fa fa-exclamation-triangle"></i>
                    <h3>Delete Responder Account</h3>
                    <p>Are you sure you want to delete <strong id="deleteName"></strong>? This action cannot be undone.</p>
                    <div id="deleteMessage"></div>
                    <form action="delete_responder.php" method="POST">
                        <input type="hidden" name="id" id="deleteId">
                        <div class="form-actions">
                            <button type="button" class="btn btn-cancel" onclick="closeModal('deleteModal')">Cancel</button>
                            <button type="submit" class="btn btn-delete">Delete</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function openAddModal() {
            document.getElementById('addModal').classList.add('show');
            // Clear form fields manually since form doesn't have ID
            const form = document.querySelector('#addModal form');
            if (form) form.reset();
            document.getElementById('addMessage').innerHTML = '';
        }

        function handleUpdateSubmit(event, userType) {
            event.preventDefault();
            
            const form = document.getElementById('updateForm');
            const formData = new FormData(form);
            const updateMessage = document.getElementById('updateMessage');
            
            // Debug: Log form data
            console.log('Submitting update for:', userType);
            console.log('Form data:', Object.fromEntries(formData));
            
            // Show loading message
            updateMessage.innerHTML = '<div style="color: #3B82F6;"><i class="fa fa-spinner fa-spin"></i> Updating...</div>';
            
            // Send AJAX request
            fetch(`api/update_${userType}.php`, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('Response status:', response.status);
                console.log('Response headers:', response.headers);
                return response.text();
            })
            .then(text => {
                console.log('Raw response:', text);
                try {
                    const data = JSON.parse(text);
                    console.log('Parsed data:', data);
                    
                    if (data.success) {
                        updateMessage.innerHTML = '<div style="color: #10B981;"><i class="fa fa-check-circle"></i> ' + data.message + '</div>';
                        
                        // Close modal after 2 seconds
                        setTimeout(() => {
                            closeModal('updateModal');
                            // Refresh the page to show updated data
                            location.reload();
                        }, 2000);
                    } else {
                        updateMessage.innerHTML = '<div style="color: #EF4444;"><i class="fa fa-exclamation-circle"></i> ' + data.message + '</div>';
                    }
                } catch (e) {
                    console.error('JSON parse error:', e);
                    console.error('Response text that failed:', text);
                    updateMessage.innerHTML = '<div style="color: #EF4444;"><i class="fa fa-exclamation-circle"></i> Server error. Please try again.</div>';
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                console.error('Error details:', error.message);
                updateMessage.innerHTML = '<div style="color: #EF4444;"><i class="fa fa-exclamation-circle"></i> Network error. Please check connection.</div>';
            });
        }

        function openUpdateModal(account) {
            console.log('openUpdateModal called with account:', account);
            console.log('Account ID:', account.id);
            console.log('Account Name:', account.name);
            console.log('Account Email:', account.email);
            
            document.getElementById('updateModal').classList.add('show');
            document.getElementById('updateId').value = account.id;
            document.getElementById('updateName').value = account.name;
            document.getElementById('updateEmail').value = account.email;
            document.getElementById('updateContact').value = account.contact || '';
            document.getElementById('updatePassword').value = '';
            document.getElementById('updateConfirmPassword').value = '';
            document.getElementById('updateMessage').innerHTML = '';
            
            console.log('Modal opened, ID set to:', document.getElementById('updateId').value);
        }

        
        function openDeleteModal(id, name) {
            console.log('Opening delete modal for ID:', id, 'Name:', name);
            document.getElementById('deleteModal').classList.add('show');
            document.getElementById('deleteId').value = id;
            document.getElementById('deleteName').textContent = name;
            document.getElementById('deleteMessage').innerHTML = '';
            console.log('Delete modal should now be visible');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }

        // Close modals when clicking outside
        window.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal')) {
                e.target.classList.remove('show');
            }
        });

        // Close modals with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal.show').forEach(modal => {
                    modal.classList.remove('show');
                });
            }
        });
    </script>
</body>
</html>
