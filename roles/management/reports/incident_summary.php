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
$status_filter = $_GET['status'] ?? 'all';
$responder_filter = $_GET['responder'] ?? 'all';
$rescuer_filter = $_GET['rescuer'] ?? 'all';

// Build query conditions
$where_conditions = ["i.start_time BETWEEN ? AND ?"];
$params = [$date_from, $date_to . ' 23:59:59'];
$types = 'ss';

if ($status_filter !== 'all') {
    $where_conditions[] = "i.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if ($responder_filter !== 'all') {
    $where_conditions[] = "i.resp_id = ?";
    $params[] = $responder_filter;
    $types .= 'i';
}

if ($rescuer_filter !== 'all') {
    $where_conditions[] = "i.resc_id = ?";
    $params[] = $rescuer_filter;
    $types .= 'i';
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Get incident summary
$query = "
    SELECT i.*, p.pat_name, p.birthdate, p.contact_number,
           r.resp_name as responder_name, r.resp_email as responder_email,
           rc.resc_name as rescuer_name, rc.resc_email as rescuer_email,
           d.dev_serial,
           TIMESTAMPDIFF(HOUR, i.start_time, COALESCE(i.end_time, NOW())) as duration_hours,
           TIMESTAMPDIFF(DAY, i.start_time, COALESCE(i.end_time, NOW())) as duration_days
    FROM incident i
    JOIN patient p ON i.pat_id = p.pat_id
    JOIN device_log dl ON i.log_id = dl.log_id
    JOIN device d ON dl.dev_id = d.dev_id
    JOIN responder r ON i.resp_id = r.resp_id
    LEFT JOIN rescuer rc ON i.resc_id = rc.resc_id
    $where_clause
    ORDER BY i.start_time DESC
";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$incidents = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get filters data
$responders = [];
$result = $conn->query("SELECT resp_id, resp_name FROM responder ORDER BY resp_name");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $responders[] = $row;
    }
}

$rescuers = [];
$result = $conn->query("SELECT resc_id, resc_name FROM rescuer ORDER BY resc_name");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $rescuers[] = $row;
    }
}

// Calculate statistics
$total_incidents = count($incidents);
$ongoing_incidents = 0;
$completed_incidents = 0;
$transferred_incidents = 0;
$avg_duration = 0;
$total_vital_readings = 0;

foreach ($incidents as $incident) {
    switch ($incident['status']) {
        case 'ongoing': $ongoing_incidents++; break;
        case 'completed': $completed_incidents++; break;
        case 'transferred': $transferred_incidents++; break;
    }
}

// Calculate average duration for completed incidents
$durations = [];
foreach ($incidents as $incident) {
    if ($incident['end_time']) {
        $start = new DateTime($incident['start_time']);
        $end = new DateTime($incident['end_time']);
        $interval = $start->diff($end);
        $durations[] = $interval->h + ($interval->days * 24);
    }
}

if (!empty($durations)) {
    $avg_duration = round(array_sum($durations) / count($durations), 1);
}

// Get vital readings count
foreach ($incidents as $incident) {
    $vital_result = $conn->query("SELECT COUNT(*) as count FROM vitalstat WHERE incident_id = {$incident['incident_id']}");
    if ($vital_result) {
        $total_vital_readings += $vital_result->fetch_assoc()['count'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Incident Summary Report - VitalWear</title>
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
            transition: transform 0.3s ease;
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
            display: flex;
            align-items: center;
            gap: 12px;
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

        .stat-icon.red {
            background: rgba(220, 53, 69, 0.1);
            color: var(--brand-danger);
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

        .badge-danger {
            background: rgba(220, 53, 69, 0.1);
            color: var(--brand-danger);
        }

        .badge-info {
            background: rgba(27, 63, 114, 0.1);
            color: var(--brand-primary);
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
        <a href="index.php" class="active"><i class="fa fa-chart-bar"></i> Reports</a>
        <a href="../../../api/auth/logout.php" class="btn btn-secondary">Logout</a>
    </nav>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Modern Header -->
        <header class="header">
            <div class="header-title">
                <h1><i class="fa fa-ambulance"></i> Incident Summary Report</h1>
                <p>Comprehensive incident analysis and statistics</p>
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
                        <i class="fa fa-ambulance"></i>
                    </div>
                    <div class="stat-value"><?php echo $total_incidents; ?></div>
                    <div class="stat-label">Total Incidents</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon yellow">
                        <i class="fa fa-exclamation-triangle"></i>
                    </div>
                    <div class="stat-value"><?php echo $ongoing_incidents; ?></div>
                    <div class="stat-label">Ongoing Incidents</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon green">
                        <i class="fa fa-check-circle"></i>
                    </div>
                    <div class="stat-value"><?php echo $completed_incidents; ?></div>
                    <div class="stat-label">Completed Incidents</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon red">
                        <i class="fa fa-heartbeat"></i>
                    </div>
                    <div class="stat-value"><?php echo $total_vital_readings; ?></div>
                    <div class="stat-label">Total Vital Readings</div>
                </div>
            </div>

            <!-- Filters Section -->
            <section class="filters-section">
                <div class="filters-header">
                    <h2>
                        <i class="fa fa-filter"></i>
                        Filter Incident Records
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
                        <label for="status">
                            <i class="fa fa-info-circle"></i>
                            Status
                        </label>
                        <select id="status" name="status">
                            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="ongoing" <?php echo $status_filter === 'ongoing' ? 'selected' : ''; ?>>Ongoing</option>
                            <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
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
                        Incident Records
                    </h2>
                </div>
                <div class="table-wrapper">
                    <table class="modern-table">
                        <thead>
                            <tr>
                                <th><i class="fa fa-hashtag"></i> ID</th>
                                <th><i class="fa fa-user"></i> Patient</th>
                                <th><i class="fa fa-user-md"></i> Responder</th>
                                <th><i class="fa fa-calendar"></i> Start Time</th>
                                <th><i class="fa fa-calendar-check"></i> End Time</th>
                                <th><i class="fa fa-clock"></i> Duration</th>
                                <th><i class="fa fa-heartbeat"></i> Vital Readings</th>
                                <th><i class="fa fa-info-circle"></i> Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($incidents)): ?>
                                <tr>
                                    <td colspan="8" class="empty-state">
                                        <i class="fa fa-inbox"></i>
                                        <p>No incident records found matching your criteria.</p>
                                        <a href="incident_summary.php" class="btn btn-primary">
                                            <i class="fa fa-refresh"></i>
                                            Reset Filters
                                        </a>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($incidents as $incident): ?>
                                    <tr>
                                        <td>
                                            <span class="badge badge-info">#<?php echo $incident['incident_id']; ?></span>
                                        </td>
                                        <td>
                                            <div class="person-info">
                                                <div class="person-avatar">
                                                    <?php echo strtoupper(substr($incident['pat_name'], 0, 1)); ?>
                                                </div>
                                                <div class="person-details">
                                                    <div class="person-name"><?php echo htmlspecialchars($incident['pat_name']); ?></div>
                                                    <div class="person-email">Age: <?php 
                                                        $birthdate = new DateTime($incident['birthdate']);
                                                        $today = new DateTime();
                                                        $age = $birthdate->diff($today)->y;
                                                        echo $age; 
                                                    ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="person-info">
                                                <div class="person-avatar">
                                                    <?php echo strtoupper(substr($incident['responder_name'], 0, 1)); ?>
                                                </div>
                                                <div class="person-details">
                                                    <div class="person-name"><?php echo htmlspecialchars($incident['responder_name']); ?></div>
                                                    <div class="person-email"><?php echo htmlspecialchars($incident['responder_email']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="date-info">
                                                <i class="fa fa-calendar"></i>
                                                <span><?php echo date('M j, Y H:i', strtotime($incident['start_time'])); ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($incident['end_time']): ?>
                                                <div class="date-info">
                                                    <i class="fa fa-calendar-check"></i>
                                                    <span><?php echo date('M j, Y H:i', strtotime($incident['end_time'])); ?></span>
                                                </div>
                                            <?php else: ?>
                                                <span class="badge badge-warning">Ongoing</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($incident['end_time']): ?>
                                                <?php 
                                                $start = new DateTime($incident['start_time']);
                                                $end = new DateTime($incident['end_time']);
                                                $duration = $start->diff($end);
                                                echo '<span class="badge badge-info">' . $duration->format('%h hours %i minutes') . '</span>';
                                                ?>
                                            <?php else: ?>
                                                <?php 
                                                $start = new DateTime($incident['start_time']);
                                                $now = new DateTime();
                                                $duration = $start->diff($now);
                                                echo '<span class="badge badge-warning">' . $duration->format('%h hours %i minutes') . '</span>';
                                                ?>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php 
                                            $vital_result = $conn->query("SELECT COUNT(*) as count FROM vitalstat WHERE incident_id = {$incident['incident_id']}");
                                            $vital_count = $vital_result ? $vital_result->fetch_assoc()['count'] : 0;
                                            echo '<span class="badge badge-success">' . $vital_count . ' readings</span>';
                                            ?>
                                        </td>
                                        <td>
                                            <?php 
                                            if ($incident['end_time']) {
                                                echo '<span class="badge badge-success">Completed</span>';
                                            } else {
                                                echo '<span class="badge badge-warning">Ongoing</span>';
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
                    // Handle special cases for person info
                    if (j === 1 || j === 2) { // Patient and Responder columns
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
            a.setAttribute('download', 'incident_summary_<?php echo date('Y-m-d'); ?>.csv');
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        }
    </script>
</body>
</html>
                    
                                                            
                                        
                
                                        
            
                                        
                                                                
                                                                                                                                                                                                                                                                                                
                                            
