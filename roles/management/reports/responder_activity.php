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
$activity_filter = $_GET['activity'] ?? 'all';

// Build query conditions
$where_conditions = [];
$params = [];
$types = '';

if ($date_from && $date_to) {
    $where_conditions[] = "DATE(al.created_at) BETWEEN ? AND ?";
    $params[] = $date_from;
    $params[] = $date_to;
    $types .= 'ss';
}

if ($responder_filter !== 'all') {
    $where_conditions[] = "r.resp_id = ?";
    $params[] = $responder_filter;
    $types .= 'i';
}

if ($activity_filter !== 'all') {
    $where_conditions[] = "al.action_type = ?";
    $params[] = $activity_filter;
    $types .= 's';
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Get responder activities
$query = "
    SELECT al.*, r.resp_name, r.resp_email, r.status as responder_status
    FROM activity_log al
    LEFT JOIN responder r ON (al.user_role = 'responder' AND al.user_name = r.resp_name)
    $where_clause
    ORDER BY al.created_at DESC
";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$activities = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get filters data
$responders = [];
$result = $conn->query("SELECT resp_id, resp_name FROM responder ORDER BY resp_name");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $responders[] = $row;
    }
}

// Get activity types
$activity_types = [];
$result = $conn->query("SELECT DISTINCT action_type FROM activity_log WHERE user_role = 'responder' ORDER BY action_type");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $activity_types[] = $row['action_type'];
    }
}

// Calculate date range statistics
$date_from_obj = new DateTime($date_from);
$date_to_obj = new DateTime($date_to);
$interval = $date_from_obj->diff($date_to_obj);
$date_range = [
    'days' => $interval->days + 1
];

// Calculate statistics
$total_activities = count($activities);
$active_responders = 0;
$unique_responders = [];
$activity_counts = [];

foreach ($activities as $activity) {
    if ($activity['resp_name'] && $activity['responder_status'] === 'active') {
        $active_responders++;
        $unique_responders[$activity['resp_name']] = true;
    }
    
    $action = $activity['action_type'];
    if (!isset($activity_counts[$action])) {
        $activity_counts[$action] = 0;
    }
    $activity_counts[$action]++;
}

$unique_responder_count = count($unique_responders);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Responder Activity Report - VitalWear</title>
        <script src="https://kit.fontawesome.com/96e37b53f1.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* VitalWear Management Color Palette */
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
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--dashboard-light);
            color: var(--authority-blue);
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
            background: linear-gradient(135deg, var(--authority-blue) 0%, #2A5298 100%);
            margin: 12px;
            border-radius: var(--radius-lg);
        }

        .sidebar-logo img {
            max-width: 140px;
            height: auto;
            filter: brightness(0) invert(1);
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

        /* Main Content */
        .main-content {
            margin-left: 260px;
            min-height: 100vh;
            background: var(--dashboard-light);
        }

        /* Modern Header */
        .header {
            background: var(--pure-white);
            padding: 20px 32px;
            border-bottom: 1px solid var(--interface-border);
            box-shadow: var(--shadow-sm);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-title h1 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--authority-blue);
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .header-title p {
            color: var(--secondary-text);
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
            border-radius: var(--radius);
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
            background: var(--authority-blue);
            color: var(--pure-white);
        }

        .btn-primary:hover {
            background: #152E5A;
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .btn-success {
            background: var(--system-success);
            color: var(--pure-white);
        }

        .btn-success:hover {
            background: #1FB380;
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .btn-secondary {
            background: var(--pure-white);
            color: var(--authority-blue);
            border: 1px solid var(--interface-border);
        }

        .btn-secondary:hover {
            background: var(--dashboard-light);
            border-color: var(--interface-border);
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
            background: var(--pure-white);
            padding: 24px;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            border: 1px solid var(--interface-border);
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: var(--radius);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
            font-size: 1.25rem;
        }

        .stat-icon.blue {
            background: rgba(27, 63, 114, 0.1);
            color: var(--authority-blue);
        }

        .stat-icon.green {
            background: rgba(44, 201, 144, 0.1);
            color: var(--system-success);
        }

        .stat-icon.yellow {
            background: rgba(255, 193, 7, 0.1);
            color: var(--system-warning);
        }

        .stat-icon.purple {
            background: rgba(74, 144, 226, 0.1);
            color: #4A90E2;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--authority-blue);
            margin-bottom: 4px;
        }

        .stat-label {
            color: var(--secondary-text);
            font-size: 0.875rem;
            font-weight: 500;
        }

        /* Filters Section */
        .filters-section {
            background: var(--pure-white);
            padding: 24px;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            border: 1px solid var(--interface-border);
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
            color: var(--authority-blue);
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
            color: var(--authority-blue);
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .filter-group input,
        .filter-group select {
            padding: 10px 14px;
            border: 1px solid var(--interface-border);
            border-radius: var(--radius);
            font-size: 0.875rem;
            transition: all 0.2s ease;
            background: var(--pure-white);
            color: var(--authority-blue);
        }

        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: var(--authority-blue);
            box-shadow: 0 0 0 3px rgba(27, 63, 114, 0.1);
        }

        .filter-actions {
            display: flex;
            gap: 12px;
            align-items: end;
        }

        /* Table Section */
        .table-section {
            background: var(--pure-white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            border: 1px solid var(--interface-border);
            overflow: hidden;
        }

        .table-header {
            padding: 24px;
            border-bottom: 1px solid var(--interface-border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-header h2 {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--authority-blue);
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
            background: var(--dashboard-light);
            padding: 12px 16px;
            text-align: left;
            font-weight: 600;
            color: var(--authority-blue);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 1px solid var(--interface-border);
            white-space: nowrap;
        }

        .modern-table th i {
            margin-right: 6px;
            color: var(--secondary-text);
        }

        .modern-table td {
            padding: 16px;
            border-bottom: 1px solid var(--interface-border);
            color: var(--authority-blue);
            font-size: 0.875rem;
        }

        .modern-table tbody tr {
            transition: background-color 0.2s ease;
        }

        .modern-table tbody tr:hover {
            background: var(--dashboard-light);
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
            color: var(--system-success);
        }

        .badge-warning {
            background: rgba(255, 193, 7, 0.1);
            color: var(--system-warning);
        }

        .badge-info {
            background: rgba(27, 63, 114, 0.1);
            color: var(--authority-blue);
        }

        .badge-primary {
            background: rgba(27, 63, 114, 0.1);
            color: var(--authority-blue);
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
            background: var(--authority-blue);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--pure-white);
            font-weight: 600;
            font-size: 0.875rem;
        }

        .person-details {
            flex: 1;
        }

        .person-name {
            font-weight: 600;
            color: var(--authority-blue);
        }

        .person-email {
            font-size: 0.75rem;
            color: var(--secondary-text);
        }

        /* Date Info */
        .date-info {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--secondary-text);
        }

        .date-info i {
            color: var(--secondary-text);
        }

        /* Empty State */
        .empty-state {
            padding: 64px 32px;
            text-align: center;
            color: var(--secondary-text);
        }

        .empty-state i {
            font-size: 3rem;
            color: var(--interface-border);
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
                <h1><i class="fa fa-users"></i> Responder Activity Report</h1>
                <p>Monitor responder activities and engagement</p>
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
                        <i class="fa fa-list"></i>
                    </div>
                    <div class="stat-value"><?php echo $total_activities; ?></div>
                    <div class="stat-label">Total Activities</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon green">
                        <i class="fa fa-user-md"></i>
                    </div>
                    <div class="stat-value"><?php echo $unique_responder_count; ?></div>
                    <div class="stat-label">Unique Responders</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon yellow">
                        <i class="fa fa-calendar-check"></i>
                    </div>
                    <div class="stat-value"><?php echo $date_range['days']; ?></div>
                    <div class="stat-label">Days in Range</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon purple">
                        <i class="fa fa-chart-line"></i>
                    </div>
                    <div class="stat-value"><?php echo round($total_activities / max($date_range['days'], 1), 1); ?></div>
                    <div class="stat-label">Avg. Activities/Day</div>
                </div>
            </div>

            <!-- Filters Section -->
            <section class="filters-section">
                <div class="filters-header">
                    <h2>
                        <i class="fa fa-filter"></i>
                        Filter Activity Records
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
                        <label for="activity_type">
                            <i class="fa fa-cogs"></i>
                            Activity Type
                        </label>
                        <select id="activity_type" name="activity_type">
                            <option value="all" <?php echo $activity_type_filter === 'all' ? 'selected' : ''; ?>>All Activities</option>
                            <option value="login" <?php echo $activity_type_filter === 'login' ? 'selected' : ''; ?>>Login</option>
                            <option value="device_assignment" <?php echo $activity_type_filter === 'device_assignment' ? 'selected' : ''; ?>>Device Assignment</option>
                            <option value="device_return" <?php echo $activity_type_filter === 'device_return' ? 'selected' : ''; ?>>Device Return</option>
                            <option value="incident_created" <?php echo $activity_type_filter === 'incident_created' ? 'selected' : ''; ?>>Incident Created</option>
                            <option value="incident_updated" <?php echo $activity_type_filter === 'incident_updated' ? 'selected' : ''; ?>>Incident Updated</option>
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
                        Activity Records
                    </h2>
                </div>
                <div class="table-wrapper">
                    <table class="modern-table">
                        <thead>
                            <tr>
                                <th><i class="fa fa-hashtag"></i> ID</th>
                                <th><i class="fa fa-user-md"></i> Responder</th>
                                <th><i class="fa fa-cogs"></i> Activity Type</th>
                                <th><i class="fa fa-file-alt"></i> Description</th>
                                <th><i class="fa fa-calendar"></i> Date</th>
                                <th><i class="fa fa-clock"></i> Time</th>
                                <th><i class="fa fa-info-circle"></i> Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($activities)): ?>
                                <tr>
                                    <td colspan="7" class="empty-state">
                                        <i class="fa fa-inbox"></i>
                                        <p>No activity records found matching your criteria.</p>
                                        <a href="responder_activity.php" class="btn btn-primary">
                                            <i class="fa fa-refresh"></i>
                                            Reset Filters
                                        </a>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($activities as $activity): ?>
                                    <tr>
                                        <td>
                                            <span class="badge badge-primary">#<?php echo $activity['activity_id']; ?></span>
                                        </td>
                                        <td>
                                            <div class="person-info">
                                                <div class="person-avatar">
                                                    <?php echo strtoupper(substr($activity['resp_name'], 0, 1)); ?>
                                                </div>
                                                <div class="person-details">
                                                    <div class="person-name"><?php echo htmlspecialchars($activity['resp_name']); ?></div>
                                                    <div class="person-email"><?php echo htmlspecialchars($activity['resp_email']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <?php 
                                            $activity_icons = [
                                                'login' => 'fa-sign-in-alt',
                                                'device_assignment' => 'fa-box',
                                                'device_return' => 'fa-check-double',
                                                'incident_created' => 'fa-ambulance',
                                                'incident_updated' => 'fa-edit'
                                            ];
                                            $activity_type = $activity['activity_type'] ?? $activity['action_type'] ?? 'unknown';
                                            $icon = $activity_icons[$activity_type] ?? 'fa-cog';
                                            echo '<span class="badge badge-info"><i class="fa ' . $icon . '"></i> ' . ucfirst(str_replace('_', ' ', $activity_type)) . '</span>';
                                            ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($activity['description'] ?? 'No description'); ?>
                                        </td>
                                        <td>
                                            <div class="date-info">
                                                <i class="fa fa-calendar"></i>
                                                <span><?php echo date('M j, Y', strtotime($activity['created_at'])); ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="date-info">
                                                <i class="fa fa-clock"></i>
                                                <span><?php echo date('H:i:s', strtotime($activity['created_at'])); ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if (!empty($activity['additional_data']) || !empty($activity['details'])): ?>
                                                <button class="btn btn-secondary" onclick="showDetails(<?php echo $activity['activity_id'] ?? $activity['id'] ?? 0; ?>)">
                                                    <i class="fa fa-eye"></i>
                                                    View Details
                                                </button>
                                            <?php else: ?>
                                                <span class="badge badge-warning">No Details</span>
                                            <?php endif; ?>
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
                    if (j === 1) { // Responder column
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
            a.setAttribute('download', 'responder_activity_<?php echo date('Y-m-d'); ?>.csv');
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        }

        function showDetails(activityId) {
            // This would typically open a modal or navigate to a details page
            alert('Activity details for ID: ' + activityId + '\n\nThis would show additional information about the activity in a modal or detailed view.');
        }
    </script>
</body>
</html>
                                
                                        
                                    
                    
            
                                                                        
                                                                                                                                                                                        document.body.removeChild(a);
        }
    </script>
</body>
</html>
