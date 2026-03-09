<?php
require_once '../../database/connection.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'rescuer') {
    header("Location: ../../login.html");
    exit;
}

$rescuer_id = $_SESSION['user_id'];
$conn = getDBConnection();

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build base query
$base_query = "SELECT i.incident_id, i.start_time, i.end_time, i.status, p.pat_name, p.birthdate, p.contact_number,
               r.resp_name, r.resp_contact, COUNT(v.vital_id) as vital_count,
               MIN(v.recorded_at) as first_vital, MAX(v.recorded_at) as last_vital
               FROM incident i 
               JOIN patient p ON i.pat_id = p.pat_id 
               JOIN responder r ON i.resp_id = r.resp_id 
               LEFT JOIN vitalstat v ON i.incident_id = v.incident_id
               WHERE i.resc_id = ?";

$params = [$rescuer_id];
$types = "i";

// Add status filter
if ($status_filter !== 'all') {
    $base_query .= " AND i.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

// Add date filters
if (!empty($date_from)) {
    $base_query .= " AND DATE(i.start_time) >= ?";
    $params[] = $date_from;
    $types .= "s";
}
if (!empty($date_to)) {
    $base_query .= " AND DATE(i.start_time) <= ?";
    $params[] = $date_to;
    $types .= "s";
}

$base_query .= " GROUP BY i.incident_id, i.start_time, i.end_time, i.status, p.pat_name, p.birthdate, p.contact_number, r.resp_name, r.resp_contact
                ORDER BY i.start_time DESC";

$stmt = $conn->prepare($base_query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$incidents = $stmt->get_result();

// Get statistics
$stats_query = "SELECT 
                 COUNT(CASE WHEN status = 'transferred' THEN 1 END) as transferred,
                 COUNT(CASE WHEN status = 'ongoing' THEN 1 END) as ongoing,
                 COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed,
                 COUNT(*) as total
                 FROM incident WHERE resc_id = ?";
$stmt = $conn->prepare($stats_query);
$stmt->bind_param("i", $rescuer_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Incident Records - VitalWear</title>

<link rel="stylesheet" href="../../assets/css/styles.css">
<script src="https://kit.fontawesome.com/96e37b53f1.js"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
/* VitalWear Soft UI Design System */
:root {
    --deep-hospital-blue: #0A2A55;
    --medical-cyan: #00B6CC;
    --trust-blue: #0A85CC;
    --health-green: #2EDBB3;
    --clinical-white: #F0F4F8;
    --system-gray: #A9B7C6;
    --surface: #ffffff;
    --radius: 12px;
    --radius-lg: 16px;
    --shadow-sm: 0 2px 4px rgba(10, 42, 85, 0.06);
    --shadow: 0 4px 12px rgba(10, 42, 85, 0.08);
    --shadow-md: 0 8px 24px rgba(10, 42, 85, 0.12);
}

body {
    background-color: var(--clinical-white);
    color: var(--deep-hospital-blue);
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
}

/* Soft UI Sidebar */
#sidebar {
    background: var(--surface);
    border-right: 1px solid rgba(169, 183, 198, 0.3);
    box-shadow: var(--shadow);
}

.sidebar-logo {
    padding: 24px 20px;
    text-align: center;
    background: linear-gradient(135deg, var(--deep-hospital-blue) 0%, var(--trust-blue) 100%);
    margin: 12px;
    border-radius: var(--radius);
}

.sidebar-logo img {
    max-width: 140px;
    height: auto;
    filter: brightness(0) invert(1);
}

#sidebar a {
    color: var(--deep-hospital-blue);
    margin: 6px 12px;
    padding: 12px 16px;
    border-radius: var(--radius);
    transition: all 0.2s ease;
    border: none;
    font-weight: 500;
}

#sidebar a:hover {
    background: rgba(0, 182, 204, 0.1);
    color: var(--medical-cyan);
    transform: translateX(4px);
}

/* Soft UI Header */
.topbar {
    background: var(--surface);
    color: var(--deep-hospital-blue);
    border-bottom: 1px solid rgba(169, 183, 198, 0.2);
    box-shadow: var(--shadow-sm);
    padding: 16px 24px;
    font-weight: 600;
}

h2, h3, h4 {
    color: var(--deep-hospital-blue);
    font-weight: 700;
}

/* Modern Soft Edge Navigation */
.bottom-nav {
    background: var(--surface);
    border-top: 1px solid rgba(169, 183, 198, 0.3);
    box-shadow: 0 -4px 20px rgba(10, 42, 85, 0.08);
    padding: 12px 24px;
    display: flex;
    justify-content: space-around;
    align-items: center;
}

.bottom-nav .bottom-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    padding: 10px 20px;
    border-radius: var(--radius);
    color: var(--system-gray);
    text-decoration: none;
    transition: all 0.3s ease;
    font-weight: 500;
    font-size: 12px;
}

.bottom-nav .bottom-item i {
    font-size: 20px;
    transition: all 0.3s ease;
}

.bottom-nav .bottom-item:hover {
    color: var(--medical-cyan);
    background: rgba(0, 182, 204, 0.1);
    transform: translateY(-2px);
}

.bottom-nav .bottom-item.active {
    color: var(--medical-cyan);
    background: rgba(0, 182, 204, 0.15);
}

.bottom-nav .bottom-item.active i {
    transform: scale(1.1);
}

/* Welcome Banner */
.welcome-banner {
    background: linear-gradient(135deg, var(--deep-hospital-blue) 0%, var(--trust-blue) 100%);
    padding: 32px;
    border-radius: var(--radius-lg);
    margin-bottom: 24px;
    color: white;
    box-shadow: var(--shadow-md);
}

/* Statistics Cards */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: var(--surface);
    padding: 24px;
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow);
    border: 1px solid rgba(169, 183, 198, 0.2);
    text-align: center;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, var(--medical-cyan) 0%, var(--trust-blue) 100%);
}

.stat-card:hover {
    box-shadow: var(--shadow-md);
    transform: translateY(-2px);
}

.stat-value {
    font-size: 2.5rem;
    font-weight: 700;
    color: var(--medical-cyan);
    margin-bottom: 8px;
}

.stat-label {
    color: var(--system-gray);
    font-size: 0.9rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Filter Section */
.filter-section {
    background: var(--surface);
    padding: 30px;
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow);
    border: 1px solid rgba(169, 183, 198, 0.2);
    margin-bottom: 30px;
}

.filter-form {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
    align-items: flex-end;
}

.filter-group {
    flex: 1;
    min-width: 200px;
}

.filter-label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: var(--deep-hospital-blue);
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.filter-input {
    width: 100%;
    padding: 12px 16px;
    border: 1px solid rgba(169, 183, 198, 0.3);
    border-radius: var(--radius);
    font-size: 14px;
    transition: all 0.3s ease;
    background: var(--clinical-white);
    color: var(--deep-hospital-blue);
}

.filter-input:focus {
    outline: none;
    border-color: var(--medical-cyan);
    box-shadow: 0 0 0 3px rgba(0, 182, 204, 0.1);
}

.filter-buttons {
    display: flex;
    gap: 12px;
    align-items: flex-end;
}

.btn {
    padding: 12px 24px;
    border: none;
    border-radius: var(--radius);
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    position: relative;
    overflow: hidden;
    text-transform: none;
    letter-spacing: 0.3px;
}

.btn::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
    transition: left 0.5s;
}

.btn:hover::before {
    left: 100%;
}

.btn i {
    font-size: 16px;
    transition: transform 0.3s ease;
}

.btn:hover i {
    transform: scale(1.1);
}

.btn-primary {
    background: linear-gradient(135deg, var(--medical-cyan) 0%, var(--trust-blue) 100%);
    color: white;
    box-shadow: 0 4px 15px rgba(0, 182, 204, 0.3);
}

.btn-primary:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0, 182, 204, 0.4);
    background: linear-gradient(135deg, var(--trust-blue) 0%, var(--medical-cyan) 100%);
}

.btn-secondary {
    background: linear-gradient(135deg, var(--system-gray) 0%, #6b7280 100%);
    color: white;
    box-shadow: 0 4px 15px rgba(169, 183, 198, 0.3);
}

.btn-secondary:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(169, 183, 198, 0.4);
    background: linear-gradient(135deg, #6b7280 0%, var(--system-gray) 100%);
}

/* Incident Cards */
.incidents-list {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.incident-card {
    background: var(--surface);
    padding: 30px;
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow);
    border: 1px solid rgba(169, 183, 198, 0.2);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.incident-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--medical-cyan) 0%, var(--trust-blue) 100%);
}

.incident-card:hover {
    box-shadow: var(--shadow-md);
    transform: translateY(-2px);
}

.incident-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 20px;
}

.incident-info {
    flex: 1;
}

.incident-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--deep-hospital-blue);
    margin: 0 0 12px 0;
}

.incident-details {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.incident-detail {
    color: var(--system-gray);
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 6px;
}

.incident-detail strong {
    color: var(--deep-hospital-blue);
    font-weight: 500;
}

.incident-status {
    text-align: right;
}

.status-badge {
    display: inline-block;
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 8px;
}

.status-transferred {
    background: linear-gradient(135deg, var(--medical-cyan) 0%, var(--trust-blue) 100%);
    color: white;
}

.status-ongoing {
    background: linear-gradient(135deg, var(--health-green) 0%, #20c997 100%);
    color: white;
}

.status-completed {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    color: white;
}

.vitals-count {
    color: var(--system-gray);
    font-size: 0.85rem;
}

.incident-actions {
    display: flex;
    gap: 12px;
    margin-top: 20px;
}

.btn-small {
    padding: 8px 16px;
    font-size: 12px;
    border-radius: var(--radius);
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    background: var(--surface);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow);
    border: 1px solid rgba(169, 183, 198, 0.2);
}

.empty-state h3 {
    color: var(--deep-hospital-blue);
    margin-bottom: 16px;
    font-size: 1.3rem;
}

.empty-state p {
    color: var(--system-gray);
    margin-bottom: 24px;
}

/* Responsive Design */
@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .filter-form {
        flex-direction: column;
        align-items: stretch;
    }
    
    .filter-buttons {
        justify-content: center;
        margin-top: 20px;
    }
    
    .incident-header {
        flex-direction: column;
        gap: 16px;
    }
    
    .incident-status {
        text-align: left;
    }
    
    .incident-actions {
        flex-direction: column;
    }
    
    .btn-small {
        width: 100%;
        justify-content: center;
    }
}
</style>

</head>

<body>

<header class="topbar">
    <div style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
        <div style="display: flex; align-items: center; gap: 12px;">
            <i class="fa fa-folder" style="font-size: 24px; color: var(--medical-cyan);"></i>
            <span style="font-size: 18px; font-weight: 700;">VitalWear</span>
        </div>
        <div style="display: flex; align-items: center; gap: 8px; color: var(--deep-hospital-blue); font-weight: 500;">
            <i class="fa fa-user-circle" style="font-size: 20px; color: var(--medical-cyan);"></i>
            <span><?php echo isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Rescuer'; ?></span>
        </div>
    </div>
</header>

<nav id="sidebar">
<div class="sidebar-logo">
    <img src="../../assets/logo.png" alt="VitalWear Logo">
</div>
<a href="dashboard.php"><i class="fa fa-gauge"></i> Dashboard</a>
<a href="transferred_incidents.php"><i class="fa fa-exclamation-circle"></i> Transferred Incidents</a>
<a href="ongoing_monitoring.php"><i class="fa fa-heart-pulse"></i> Ongoing Monitoring</a>
<a href="completed_cases.php"><i class="fa fa-check-circle"></i> Completed Cases</a>
<a href="incident_records.php"><i class="fa fa-folder"></i> Incident Records</a>
<a href="return_device.php"><i class="fa fa-undo"></i> Return Device</a>
<a href="../../api/auth/logout.php" class="btn btn-secondary">Logout</a>
</nav>

<main class="container" style="display:block;overflow-y:auto;">

<!-- Welcome Banner -->
<div class="welcome-banner">
    <div style="display: flex; align-items: center; gap: 16px;">
        <div style="width: 60px; height: 60px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 28px;">
            📁
        </div>
        <div>
            <h1 style="color: white; margin: 0; font-size: 1.75rem; font-weight: 700;">Incident Records</h1>
            <p style="color: rgba(255,255,255,0.9); margin: 4px 0 0 0; font-size: 1rem;">Complete history of all managed incidents</p>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-value"><?php echo $stats['total']; ?></div>
        <div class="stat-label">Total Cases</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?php echo $stats['transferred']; ?></div>
        <div class="stat-label">Transferred</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?php echo $stats['ongoing']; ?></div>
        <div class="stat-label">Ongoing</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?php echo $stats['completed']; ?></div>
        <div class="stat-label">Completed</div>
    </div>
</div>

<!-- Filters -->
<div class="filter-section">
    <form method="GET" class="filter-form">
        <div class="filter-group">
            <label class="filter-label">Status</label>
            <select name="status" class="filter-input">
                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                <option value="transferred" <?php echo $status_filter === 'transferred' ? 'selected' : ''; ?>>Transferred</option>
                <option value="ongoing" <?php echo $status_filter === 'ongoing' ? 'selected' : ''; ?>>Ongoing</option>
                <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
            </select>
        </div>
        <div class="filter-group">
            <label class="filter-label">From Date</label>
            <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>" class="filter-input">
        </div>
        <div class="filter-group">
            <label class="filter-label">To Date</label>
            <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>" class="filter-input">
        </div>
        <div class="filter-buttons">
            <button type="submit" class="btn btn-primary">
                <i class="fa fa-filter"></i> Filter
            </button>
            <a href="incident_records.php" class="btn btn-secondary">
                <i class="fa fa-times"></i> Clear
            </a>
        </div>
    </form>
</div>

<!-- Incidents List -->
<?php if ($incidents->num_rows > 0): ?>
    <div class="incidents-list">
        <?php while ($incident = $incidents->fetch_assoc()): ?>
            <div class="incident-card">
                <div class="incident-header">
                    <div class="incident-info">
                        <h3 class="incident-title">Incident #<?php echo $incident['incident_id']; ?></h3>
                        <div class="incident-details">
                            <div class="incident-detail">
                                <i class="fa fa-user"></i>
                                <strong>Patient:</strong> <?php echo htmlspecialchars($incident['pat_name']); ?>
                            </div>
                            <div class="incident-detail">
                                <i class="fa fa-ambulance"></i>
                                <strong>From:</strong> <?php echo htmlspecialchars($incident['resp_name']); ?>
                            </div>
                            <div class="incident-detail">
                                <i class="fa fa-clock"></i>
                                <strong>Started:</strong> <?php echo date('M j, Y H:i', strtotime($incident['start_time'])); ?>
                            </div>
                            <?php if ($incident['end_time']): ?>
                                <div class="incident-detail">
                                    <i class="fa fa-flag-checkered"></i>
                                    <strong>Ended:</strong> <?php echo date('M j, Y H:i', strtotime($incident['end_time'])); ?>
                                </div>
                            <?php endif; ?>
                            <div class="incident-detail">
                                <i class="fa fa-heart-pulse"></i>
                                <strong><?php echo $incident['vital_count']; ?></strong> vitals recorded
                            </div>
                        </div>
                    </div>
                    <div class="incident-status">
                        <span class="status-badge status-<?php echo $incident['status']; ?>">
                            <?php echo ucfirst($incident['status']); ?>
                        </span>
                        <div class="vitals-count">
                            📊 <?php echo $incident['vital_count']; ?> measurements
                        </div>
                    </div>
                </div>
                
                <div class="incident-actions">
                    <a href="view_incident_details.php?id=<?php echo $incident['incident_id']; ?>" class="btn btn-primary btn-small">
                        <i class="fa fa-eye"></i> View Details
                    </a>
                    <a href="case_vitals_history.php?id=<?php echo $incident['incident_id']; ?>" class="btn btn-secondary btn-small">
                        <i class="fa fa-chart-line"></i> View History
                    </a>
                    <?php if ($incident['status'] === 'completed'): ?>
                        <a href="generate_case_report.php?id=<?php echo $incident['incident_id']; ?>" class="btn btn-primary btn-small">
                            <i class="fa fa-file-medical"></i> Generate Report
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
<?php else: ?>
    <div class="empty-state">
        <div style="font-size: 64px; margin-bottom: 20px; color: var(--system-gray);">
            📁
        </div>
        <h3>No Incident Records Found</h3>
        <p>No incidents match your current filters.</p>
        <a href="incident_records.php" class="btn btn-primary">
            <i class="fa fa-refresh"></i> Clear Filters
        </a>
    </div>
<?php endif; ?>

</main>

<nav class="bottom-nav">
<a href="dashboard.php" class="bottom-item">
    <i class="fa fa-gauge"></i>
    <span>Home</span>
</a>

<a href="transferred_incidents.php" class="bottom-item">
    <i class="fa fa-exclamation-circle"></i>
    <span>Transfer</span>
</a>

<a href="ongoing_monitoring.php" class="bottom-item">
    <i class="fa fa-heart-pulse"></i>
    <span>Monitor</span>
</a>

<a href="completed_cases.php" class="bottom-item">
    <i class="fa fa-check-circle"></i>
    <span>Complete</span>
</a>

<a href="incident_records.php" class="bottom-item active">
    <i class="fa fa-folder"></i>
    <span>Records</span>
</a>

<a href="../../api/auth/logout.php" class="bottom-item">
    <i class="fa fa-sign-out"></i>
    <span>Logout</span>
</a>
</nav>

</body>
</html>
