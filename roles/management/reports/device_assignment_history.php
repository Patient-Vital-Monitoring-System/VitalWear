<?php
session_start();
require_once '../../../database/connection.php';

// Check if management user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'management') {
    header('Location: ../../login.html');
    exit();
}

$conn = getDBConnection();

// Handle filtering
$date_from = $_GET['date_from'] ?? date('Y-m-01'); // First day of current month
$date_to = $_GET['date_to'] ?? date('Y-m-d'); // Today
$responder_filter = $_GET['responder'] ?? 'all';
$device_filter = $_GET['device'] ?? 'all';

// Build query conditions
$where_conditions = ["dl.date_assigned BETWEEN ? AND ?"];
$params = [$date_from, $date_to . ' 23:59:59'];
$types = 'ss';

if ($responder_filter !== 'all') {
    $where_conditions[] = "dl.resp_id = ?";
    $params[] = $responder_filter;
    $types .= 'i';
}

if ($device_filter !== 'all') {
    $where_conditions[] = "dl.dev_id = ?";
    $params[] = $device_filter;
    $types .= 'i';
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Get assignment history
$query = "
    SELECT dl.*, d.dev_serial, r.resp_name, r.resp_email, m.mgmt_name,
           CASE 
               WHEN dl.date_returned IS NOT NULL THEN 'Returned'
               ELSE 'Active'
           END as assignment_status
    FROM device_log dl
    JOIN device d ON dl.dev_id = d.dev_id
    JOIN responder r ON dl.resp_id = r.resp_id
    JOIN management m ON dl.mgmt_id = m.mgmt_id
    $where_clause
    ORDER BY dl.date_assigned DESC
";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$assignments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get filters data
$responders = [];
$result = $conn->query("SELECT resp_id, resp_name FROM responder ORDER BY resp_name");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $responders[] = $row;
    }
}

$devices = [];
$result = $conn->query("SELECT dev_id, dev_serial FROM device ORDER BY dev_serial");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $devices[] = $row;
    }
}

// Calculate statistics
$total_assignments = count($assignments);
$active_assignments = 0;
$returned_assignments = 0;
$avg_duration = 0;

foreach ($assignments as $assignment) {
    if ($assignment['date_returned']) {
        $returned_assignments++;
    } else {
        $active_assignments++;
    }
}

// Calculate average duration for returned assignments
$durations = [];
foreach ($assignments as $assignment) {
    if ($assignment['date_returned']) {
        $assigned = new DateTime($assignment['date_assigned']);
        $returned = new DateTime($assignment['date_returned']);
        $durations[] = $assigned->diff($returned)->days;
    }
}

if (!empty($durations)) {
    $avg_duration = round(array_sum($durations) / count($durations), 1);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Device Assignment History - VitalWear</title>
        <script src="https://kit.fontawesome.com/96e37b53f1.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* VitalWear Management Brandkit Palette */
        :root {
            /* Primary Brand Colors */
            --brand-primary: #1B3F72;
            --brand-primary-light: #2A5298;
            --brand-primary-dark: #152E5A;
            --brand-accent: #4A90E2;
            
            /* Secondary Colors */
            --brand-secondary: #7E91B3;
            --brand-secondary-light: #A8B8D4;
            --brand-secondary-dark: #5A6B8C;
            
            /* Status Colors */
            --brand-success: #2CC990;
            --brand-success-light: #4ED9A6;
            --brand-success-dark: #1FB380;
            
            --brand-warning: #FFC107;
            --brand-warning-light: #FFD54F;
            --brand-warning-dark: #FFA000;
            
            --brand-danger: #DC3545;
            --brand-danger-light: #E57373;
            --brand-danger-dark: #C62828;
            
            /* Neutral Colors */
            --brand-white: #FFFFFF;
            --brand-gray-50: #F8F9FA;
            --brand-gray-100: #E9ECEF;
            --brand-gray-200: #DEE2E6;
            --brand-gray-300: #CED4DA;
            --brand-gray-400: #ADB5BD;
            --brand-gray-500: #6C757D;
            --brand-gray-600: #495057;
            --brand-gray-700: #343A40;
            --brand-gray-800: #212529;
            --brand-gray-900: #000000;
            
            /* Background Colors */
            --brand-bg-primary: #F4F7FC;
            --brand-bg-secondary: #FFFFFF;
            --brand-bg-accent: #E8F0FE;
            
            /* Border Colors */
            --brand-border: #D1E0F1;
            --brand-border-light: #E8F2F8;
            --brand-border-dark: #B8D4E8;
            
            /* Shadow System */
            --brand-shadow-sm: 0 2px 4px rgba(27, 63, 114, 0.06);
            --brand-shadow: 0 4px 12px rgba(27, 63, 114, 0.08);
            --brand-shadow-md: 0 8px 24px rgba(27, 63, 114, 0.12);
            --brand-shadow-lg: 0 16px 32px rgba(27, 63, 114, 0.16);
            
            /* Border Radius */
            --brand-radius: 8px;
            --brand-radius-md: 12px;
            --brand-radius-lg: 16px;
            --brand-radius-xl: 20px;
            
            /* Typography */
            --brand-font-primary: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            --brand-font-secondary: 'Roboto', -apple-system, BlinkMacSystemFont, sans-serif;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: var(--brand-font-primary);
            background: linear-gradient(135deg, var(--brand-bg-primary) 0%, var(--brand-bg-accent) 100%);
            color: var(--brand-gray-800);
            line-height: 1.6;
            min-height: 100vh;
        }

        /* Consistent Sidebar Navigation */
        #sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 260px;
            height: 100vh;
            background: var(--brand-bg-secondary);
            border-right: 1px solid var(--brand-border);
            box-shadow: var(--brand-shadow);
            z-index: 1000;
            overflow-y: auto;
        }

        .sidebar-logo {
            padding: 24px 20px;
            text-align: center;
            background: linear-gradient(135deg, var(--brand-primary) 0%, var(--brand-primary-light) 100%);
            margin: 12px;
            border-radius: var(--brand-radius-lg);
        }

        .sidebar-logo img {
            max-width: 140px;
            height: auto;
            filter: brightness(0) invert(1);
        }

        #sidebar a {
            color: var(--brand-primary);
            margin: 6px 12px;
            padding: 12px 16px;
            border-radius: var(--brand-radius);
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
            color: var(--brand-primary);
            transform: translateX(4px);
        }

        #sidebar a.active {
            background: rgba(27, 63, 114, 0.15);
            color: var(--brand-primary);
        }

        /* Main Content */
        .main-content {
            margin-left: 260px;
            min-height: 100vh;
            background: var(--brand-bg-primary);
        }

        /* Modern Header */
        .header {
            background: var(--brand-bg-secondary);
            padding: 20px 32px;
            border-bottom: 1px solid var(--brand-border);
            box-shadow: var(--brand-shadow-sm);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-title h1 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--brand-primary);
            margin-bottom: 4px;
        }

        .header-title p {
            color: var(--brand-secondary);
            font-size: 1rem;
        }

        .header-actions {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        /* Modern Buttons */
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: var(--brand-radius);
            font-weight: 600;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-primary {
            background: var(--brand-primary);
            color: var(--brand-white);
        }

        .btn-primary:hover {
            background: var(--brand-primary-dark);
            transform: translateY(-1px);
            box-shadow: var(--brand-shadow-md);
        }

        .btn-success {
            background: var(--brand-success);
            color: var(--brand-white);
        }

        .btn-success:hover {
            background: var(--brand-success-dark);
            transform: translateY(-1px);
            box-shadow: var(--brand-shadow-md);
        }

        .btn-secondary {
            background: var(--brand-bg-secondary);
            color: var(--brand-gray-700);
            border: 1px solid var(--brand-border);
        }

        .btn-secondary:hover {
            background: var(--brand-gray-50);
            border-color: var(--brand-gray-300);
        }

        /* Content Container */
        .content {
            padding: 32px;
            max-width: 1400px;
            margin: 0 auto;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: var(--brand-bg-secondary);
            padding: 24px;
            border-radius: var(--brand-radius-lg);
            box-shadow: var(--brand-shadow);
            border: 1px solid var(--brand-border);
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--brand-shadow-lg);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: var(--brand-radius);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
            font-size: 1.25rem;
        }

        .stat-icon.blue {
            background: rgba(27, 63, 114, 0.1);
            color: var(--brand-primary);
        }

        .stat-icon.green {
            background: rgba(44, 201, 144, 0.1);
            color: var(--brand-success);
        }

        .stat-icon.yellow {
            background: rgba(255, 193, 7, 0.1);
            color: var(--brand-warning);
        }

        .stat-icon.purple {
            background: rgba(74, 144, 226, 0.1);
            color: var(--brand-accent);
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--brand-gray-800);
            margin-bottom: 4px;
        }

        .stat-label {
            color: var(--brand-secondary);
            font-size: 0.875rem;
            font-weight: 500;
        }

        /* Filters Section */
        .filters-section {
            background: var(--brand-bg-secondary);
            padding: 24px;
            border-radius: var(--brand-radius-lg);
            box-shadow: var(--brand-shadow);
            border: 1px solid var(--brand-border);
            margin-bottom: 32px;
        }

        .filters-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .filters-header h2 {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--brand-primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            align-items: end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .filter-group label {
            font-weight: 500;
            color: var(--brand-gray-700);
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .filter-group input,
        .filter-group select {
            padding: 10px 14px;
            border: 1px solid var(--brand-border);
            border-radius: var(--brand-radius);
            font-size: 0.875rem;
            transition: all 0.2s ease;
            background: var(--brand-bg-secondary);
            color: var(--brand-gray-800);
        }

        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: var(--brand-primary);
            box-shadow: 0 0 0 3px rgba(27, 63, 114, 0.1);
        }

        .filter-actions {
            display: flex;
            gap: 12px;
            align-items: end;
        }

        /* Table Section */
        .table-section {
            background: var(--brand-bg-secondary);
            border-radius: var(--brand-radius-lg);
            box-shadow: var(--brand-shadow);
            border: 1px solid var(--brand-border);
            overflow: hidden;
        }

        .table-header {
            padding: 24px;
            border-bottom: 1px solid var(--brand-border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-header h2 {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--brand-primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        .modern-table {
            width: 100%;
            border-collapse: collapse;
        }

        .modern-table th {
            background: var(--brand-bg-accent);
            padding: 12px 16px;
            text-align: left;
            font-weight: 600;
            color: var(--brand-primary);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 1px solid var(--brand-border);
            white-space: nowrap;
        }

        .modern-table th i {
            margin-right: 6px;
            color: var(--brand-secondary);
        }

        .modern-table td {
            padding: 16px;
            border-bottom: 1px solid var(--brand-border-light);
            color: var(--brand-gray-700);
            font-size: 0.875rem;
        }

        .modern-table tbody tr {
            transition: background-color 0.2s ease;
        }

        .modern-table tbody tr:hover {
            background: var(--brand-bg-accent);
        }

        /* Status Badges */
        .badge {
            padding: 4px 12px;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            display: inline-block;
        }

        .badge-success {
            background: rgba(44, 201, 144, 0.1);
            color: var(--brand-success);
        }

        .badge-warning {
            background: rgba(255, 193, 7, 0.1);
            color: var(--brand-warning);
        }

        .badge-info {
            background: rgba(27, 63, 114, 0.1);
            color: var(--brand-primary);
        }

        /* Device Info */
        .device-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .device-icon {
            width: 32px;
            height: 32px;
            background: var(--brand-bg-accent);
            border-radius: var(--brand-radius);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--brand-primary);
        }

        .device-details {
            flex: 1;
        }

        .device-serial {
            font-weight: 600;
            color: var(--brand-gray-800);
        }

        .device-meta {
            font-size: 0.75rem;
            color: var(--brand-secondary);
        }

        /* Person Info */
        .person-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .person-avatar {
            width: 32px;
            height: 32px;
            background: var(--brand-primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--brand-white);
            font-weight: 600;
            font-size: 0.875rem;
        }

        .person-details {
            flex: 1;
        }

        .person-name {
            font-weight: 600;
            color: var(--brand-gray-800);
        }

        .person-email {
            font-size: 0.75rem;
            color: var(--brand-secondary);
        }

        /* Date Info */
        .date-info {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--brand-gray-600);
        }

        .date-info i {
            color: var(--brand-secondary);
        }

        /* Empty State */
        .empty-state {
            padding: 64px 32px;
            text-align: center;
            color: var(--brand-secondary);
        }

        .empty-state i {
            font-size: 3rem;
            color: var(--brand-gray-400);
            margin-bottom: 16px;
        }

        .empty-state p {
            font-size: 1rem;
            margin-bottom: 24px;
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            #sidebar {
                transform: translateX(-100%);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .header {
                padding: 16px 20px;
                flex-direction: column;
                gap: 16px;
                align-items: stretch;
            }
            
            .header-title h1 {
                font-size: 1.5rem;
            }
            
            .content {
                padding: 20px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .filters-grid {
                grid-template-columns: 1fr;
            }
            
            .filter-actions {
                flex-direction: column;
            }
            
            .table-header {
                flex-direction: column;
                gap: 16px;
                align-items: stretch;
            }
            
            .modern-table {
                font-size: 0.75rem;
            }
            
            .modern-table th,
            .modern-table td {
                padding: 12px 8px;
            }
        }
    </style>
</head>
<body>
    <!-- Consistent Sidebar Navigation -->
    <nav id="sidebar">
        <div class="sidebar-logo">
            <img src="../../../assets/logo.png" alt="VitalWear Logo">
        </div>
        <a href="../dashboard.php"><i class="fa fa-gauge"></i> Dashboard</a>
        <a href="../manage_responders.php"><i class="fa fa-user-md"></i> Manage Responders</a>
        <a href="../manage_rescuers.php"><i class="fa fa-user-shield"></i> Manage Rescuers</a>
        <a href="../register_device.php"><i class="fa fa-plus-circle"></i> Register Device</a>
        <a href="../device_list.php"><i class="fa fa-box"></i> Device List</a>
        <a href="../assign_device.php"><i class="fa fa-exchange-alt"></i> Assign Device</a>
        <a href="../verify_return.php"><i class="fa fa-check-double"></i> Verify Return</a>
        <a href="reportdashboard.php" class="active"><i class="fa fa-chart-bar"></i> Reports</a>
        <a href="../../../api/auth/logout.php" class="btn btn-secondary">Logout</a>
    </nav>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Modern Header -->
        <header class="header">
            <div class="header-title">
                <h1>Device Assignment History</h1>
                <p>Track and analyze device assignments across your organization</p>
            </div>
            <div class="header-actions">
                <button class="btn btn-success" onclick="exportToCSV()">
                    <i class="fa fa-download"></i>
                    Export CSV
                </button>
            </div>
        </header>

        <!-- Content Container -->
        <div class="content">
            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon blue">
                        <i class="fa fa-box"></i>
                    </div>
                    <div class="stat-value"><?php echo $total_assignments; ?></div>
                    <div class="stat-label">Total Assignments</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon green">
                        <i class="fa fa-check-circle"></i>
                    </div>
                    <div class="stat-value"><?php echo $returned_assignments; ?></div>
                    <div class="stat-label">Returned Devices</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon yellow">
                        <i class="fa fa-clock"></i>
                    </div>
                    <div class="stat-value"><?php echo $avg_duration; ?></div>
                    <div class="stat-label">Avg. Duration (Days)</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon purple">
                        <i class="fa fa-user-md"></i>
                    </div>
                    <div class="stat-value"><?php echo $active_assignments; ?></div>
                    <div class="stat-label">Active Assignments</div>
                </div>
            </div>

            <!-- Filters Section -->
            <section class="filters-section">
                <div class="filters-header">
                    <h2>
                        <i class="fa fa-filter"></i>
                        Filter Records
                    </h2>
                </div>
                <form method="GET" class="filters-grid">
                    <div class="filter-group">
                        <label for="date_from">
                            <i class="fa fa-calendar"></i>
                            From Date
                        </label>
                        <input type="date" id="date_from" name="date_from" value="<?php echo $date_from; ?>" required>
                    </div>
                    
                    <div class="filter-group">
                        <label for="date_to">
                            <i class="fa fa-calendar"></i>
                            To Date
                        </label>
                        <input type="date" id="date_to" name="date_to" value="<?php echo $date_to; ?>" required>
                    </div>
                    
                    <div class="filter-group">
                        <label for="responder">
                            <i class="fa fa-user-md"></i>
                            Responder
                        </label>
                        <select id="responder" name="responder">
                            <option value="all" <?php echo $responder_filter === 'all' ? 'selected' : ''; ?>>All Responders</option>
                            <?php foreach ($responders as $responder): ?>
                                <option value="<?php echo $responder['resp_id']; ?>" 
                                        <?php echo $responder_filter == $responder['resp_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($responder['resp_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="device">
                            <i class="fa fa-box"></i>
                            Device
                        </label>
                        <select id="device" name="device">
                            <option value="all" <?php echo $device_filter === 'all' ? 'selected' : ''; ?>>All Devices</option>
                            <?php foreach ($devices as $device): ?>
                                <option value="<?php echo $device['dev_id']; ?>" 
                                        <?php echo $device_filter == $device['dev_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($device['dev_serial']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fa fa-search"></i>
                            Apply Filters
                        </button>
                    </div>
                </form>
            </section>

            <!-- Table Section -->
            <section class="table-section">
                <div class="table-header">
                    <h2>
                        <i class="fa fa-list"></i>
                        Assignment Records
                    </h2>
                </div>
                <div class="table-wrapper">
                    <table class="modern-table">
                        <thead>
                            <tr>
                                <th><i class="fa fa-box"></i> Device</th>
                                <th><i class="fa fa-user-md"></i> Responder</th>
                                <th><i class="fa fa-calendar"></i> Assigned</th>
                                <th><i class="fa fa-calendar-check"></i> Returned</th>
                                <th><i class="fa fa-clock"></i> Duration</th>
                                <th><i class="fa fa-info-circle"></i> Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($assignments)): ?>
                                <tr>
                                    <td colspan="6" class="empty-state">
                                        <i class="fa fa-inbox"></i>
                                        <p>No assignment records found matching your criteria.</p>
                                        <a href="device_assignment_history.php" class="btn btn-primary">
                                            <i class="fa fa-refresh"></i>
                                            Reset Filters
                                        </a>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($assignments as $assignment): ?>
                                    <tr>
                                        <td>
                                            <div class="device-info">
                                                <div class="device-icon">
                                                    <i class="fa fa-box"></i>
                                                </div>
                                                <div class="device-details">
                                                    <div class="device-serial"><?php echo htmlspecialchars($assignment['dev_serial']); ?></div>
                                                    <div class="device-meta">ID: <?php echo $assignment['dev_id']; ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="person-info">
                                                <div class="person-avatar">
                                                    <?php echo strtoupper(substr($assignment['resp_name'], 0, 1)); ?>
                                                </div>
                                                <div class="person-details">
                                                    <div class="person-name"><?php echo htmlspecialchars($assignment['resp_name']); ?></div>
                                                    <div class="person-email"><?php echo htmlspecialchars($assignment['resp_email']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="date-info">
                                                <i class="fa fa-calendar"></i>
                                                <span><?php echo date('M j, Y', strtotime($assignment['date_assigned'])); ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($assignment['date_returned']): ?>
                                                <div class="date-info">
                                                    <i class="fa fa-calendar-check"></i>
                                                    <span><?php echo date('M j, Y', strtotime($assignment['date_returned'])); ?></span>
                                                </div>
                                            <?php else: ?>
                                                <div class="date-info">
                                                    <i class="fa fa-minus"></i>
                                                    <span>Not returned</span>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($assignment['date_returned']): ?>
                                                <span class="badge badge-info">
                                                    <?php 
                                                    $assigned = new DateTime($assignment['date_assigned']);
                                                    $returned = new DateTime($assignment['date_returned']);
                                                    $duration = $assigned->diff($returned)->days;
                                                    echo $duration . ' days';
                                                    ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge badge-warning">
                                                    <?php 
                                                    $assigned = new DateTime($assignment['date_assigned']);
                                                    $now = new DateTime();
                                                    $duration = $assigned->diff($now)->days;
                                                    echo $duration . ' days';
                                                    ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php 
                                            if ($assignment['verified_return']) {
                                                echo '<span class="badge badge-success">Verified</span>';
                                            } elseif ($assignment['date_returned']) {
                                                echo '<span class="badge badge-warning">Returned</span>';
                                            } else {
                                                echo '<span class="badge badge-info">Active</span>';
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </main>

    <script>
        function exportToCSV() {
            const table = document.querySelector('.modern-table');
            let csv = [];
            
            // Get headers
            const headers = [];
            for (let i = 0; i < table.rows[0].cells.length; i++) {
                let headerText = table.rows[0].cells[i].textContent.trim();
                // Remove icon text and clean up
                headerText = headerText.replace(/^[^\w]*/, '').trim();
                headers.push(headerText);
            }
            csv.push(headers.join(','));
            
            // Get data rows
            for (let i = 1; i < table.rows.length; i++) {
                const row = [];
                for (let j = 0; j < table.rows[i].cells.length; j++) {
                    let cellText = table.rows[i].cells[j].textContent.trim();
                    // Clean up text content and remove commas
                    cellText = cellText.replace(/,/g, '');
                    // Handle special cases for device and responder info
                    if (j === 0) { // Device column
                        const deviceSerial = table.rows[i].cells[j].querySelector('.device-serial');
                        if (deviceSerial) {
                            cellText = deviceSerial.textContent.trim();
                        }
                    } else if (j === 1) { // Responder column
                        const personName = table.rows[i].cells[j].querySelector('.person-name');
                        if (personName) {
                            cellText = personName.textContent.trim();
                        }
                    }
                    row.push(cellText);
                }
                csv.push(row.join(','));
            }
            
            // Create download link
            const csvContent = csv.join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.setAttribute('hidden', '');
            a.setAttribute('href', url);
            a.setAttribute('download', 'device_assignment_history_<?php echo date('Y-m-d'); ?>.csv');
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        }
    </script>
</body>
</html>
