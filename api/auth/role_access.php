<?php
session_start();
require_once '../../database/connection.php';

header("Content-Type: application/json");

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(["status" => "error", "message" => "Not authenticated"]);
    exit();
}

$conn = getDBConnection();
$user_role = $_SESSION['user_role'];
$user_id = $_SESSION['user_id'];

// Get requested action
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get_permissions':
        // Return role-based permissions
        $permissions = getRolePermissions($user_role);
        echo json_encode(["status" => "success", "role" => $user_role, "permissions" => $permissions]);
        break;
        
    case 'get_accessible_modules':
        // Return modules accessible to this role
        $modules = getAccessibleModules($user_role);
        echo json_encode(["status" => "success", "modules" => $modules]);
        break;
        
    case 'check_access':
        // Check if user can access specific module
        $module = $_GET['module'] ?? '';
        $can_access = canAccessModule($user_role, $module);
        echo json_encode(["status" => "success", "can_access" => $can_access]);
        break;
        
    case 'get_role_stats':
        // Get role-specific statistics
        $stats = getRoleStatistics($user_role, $user_id, $conn);
        echo json_encode(["status" => "success", "stats" => $stats]);
        break;
        
    default:
        echo json_encode(["status" => "error", "message" => "Unknown action"]);
}

function getRolePermissions($role) {
    $permissions = [
        'admin' => [
            'user_management' => ['create', 'read', 'update', 'delete'],
            'device_management' => ['create', 'read', 'update', 'delete'],
            'incident_management' => ['read'],
            'vital_monitoring' => ['read'],
            'system_administration' => ['create', 'read', 'update', 'delete'],
            'reporting' => ['read', 'create'],
            'audit_logs' => ['read']
        ],
        'management' => [
            'user_management' => ['read', 'update'], // Only for responders/rescuers
            'device_management' => ['create', 'read', 'update'],
            'incident_management' => ['read'],
            'vital_monitoring' => ['read'],
            'system_administration' => [],
            'reporting' => ['read', 'create'],
            'audit_logs' => []
        ],
        'responder' => [
            'user_management' => [],
            'device_management' => ['read'], // Only assigned devices
            'incident_management' => ['create', 'read', 'update'],
            'vital_monitoring' => ['create', 'read', 'update'],
            'system_administration' => [],
            'reporting' => ['read'], // Only personal reports
            'audit_logs' => []
        ],
        'rescuer' => [
            'user_management' => [],
            'device_management' => ['read'], // Only assigned devices
            'incident_management' => ['read', 'update'], // Only transferred incidents
            'vital_monitoring' => ['create', 'read', 'update'],
            'system_administration' => [],
            'reporting' => ['read'], // Only personal reports
            'audit_logs' => []
        ]
    ];
    
    return $permissions[$role] ?? [];
}

function getAccessibleModules($role) {
    $modules = [
        'admin' => [
            'dashboard' => ['url' => '../roles/admin/dashboard.php', 'name' => 'Admin Dashboard'],
            'users' => ['url' => '../roles/admin/users.php', 'name' => 'User Management'],
            'audit_logs' => ['url' => '../roles/admin/audit_logs.php', 'name' => 'Audit Logs'],
            'reports' => ['url' => '../roles/admin/reports.php', 'name' => 'System Reports'],
            'devices' => ['url' => '../roles/admin/devices.php', 'name' => 'Device Oversight'],
            'settings' => ['url' => '#', 'name' => 'System Settings']
        ],
        'management' => [
            'dashboard' => ['url' => '../roles/management/dashboard.php', 'name' => 'Management Dashboard'],
            'manage_responders' => ['url' => '../roles/management/manage_responders.php', 'name' => 'Manage Responders'],
            'manage_rescuers' => ['url' => '../roles/management/manage_rescuers.php', 'name' => 'Manage Rescuers'],
            'register_device' => ['url' => '../roles/management/register_device.php', 'name' => 'Register Device'],
            'device_list' => ['url' => '../roles/management/device_list.php', 'name' => 'Device List'],
            'assign_device' => ['url' => '../roles/management/assign_device.php', 'name' => 'Assign Device'],
            'verify_return' => ['url' => '../roles/management/verify_return.php', 'name' => 'Verify Return'],
            'reports' => ['url' => '../roles/management/reports/index.php', 'name' => 'Reports']
        ],
        'responder' => [
            'dashboard' => ['url' => '../roles/responder/dashboard.php', 'name' => 'Responder Dashboard'],
            'device' => ['url' => '../roles/responder/device.php', 'name' => 'My Device'],
            'active_incidents' => ['url' => '../roles/responder/active_incidents.php', 'name' => 'Active Incidents'],
            'create_incident' => ['url' => '../roles/responder/create_incident.php', 'name' => 'Create Incident'],
            'patient_vitals' => ['url' => '../roles/responder/patient_vitals.php', 'name' => 'Patient Vitals'],
            'transfer_incident' => ['url' => '../roles/responder/transfer_incident.php', 'name' => 'Transfer Incident'],
            'incident_history' => ['url' => '../roles/responder/incident_history.php', 'name' => 'Incident History']
        ],
        'rescuer' => [
            'dashboard' => ['url' => '../roles/rescuer/dashboard.php', 'name' => 'Rescuer Dashboard'],
            'transferred_incidents' => ['url' => '../roles/rescuer/transferred_incidents.php', 'name' => 'Transferred Incidents'],
            'active_incidents' => ['url' => '../roles/rescuer/active_incidents.php', 'name' => 'Active Incidents'],
            'ongoing_monitoring' => ['url' => '../roles/rescuer/ongoing_monitoring.php', 'name' => 'Ongoing Monitoring'],
            'add_vitals' => ['url' => '../roles/rescuer/add_vitals.php', 'name' => 'Add Vitals'],
            'completed_cases' => ['url' => '../roles/rescuer/completed_cases.php', 'name' => 'Completed Cases'],
            'incident_history' => ['url' => '../roles/rescuer/incident_history.php', 'name' => 'Incident History']
        ]
    ];
    
    return $modules[$role] ?? [];
}

function canAccessModule($role, $module) {
    $accessible_modules = array_keys(getAccessibleModules($role));
    return in_array($module, $accessible_modules);
}

function getRoleStatistics($role, $user_id, $conn) {
    $stats = [];
    
    switch ($role) {
        case 'admin':
            $stats = [
                'total_users' => getTotalUsers($conn),
                'total_devices' => getTotalDevices($conn),
                'total_incidents' => getTotalIncidents($conn),
                'system_health' => getSystemHealth($conn)
            ];
            break;
            
        case 'management':
            $stats = [
                'total_devices' => getTotalDevices($conn),
                'available_devices' => getAvailableDevices($conn),
                'assigned_devices' => getAssignedDevices($conn),
                'active_incidents' => getActiveIncidents($conn),
                'pending_returns' => getPendingReturns($conn)
            ];
            break;
            
        case 'responder':
            $stats = [
                'assigned_device' => getAssignedDevice($user_id, $conn),
                'active_incidents' => getResponderActiveIncidents($user_id, $conn),
                'total_incidents' => getResponderTotalIncidents($user_id, $conn),
                'recent_vitals' => getRecentVitals($user_id, $conn)
            ];
            break;
            
        case 'rescuer':
            $stats = [
                'active_cases' => getRescuerActiveCases($user_id, $conn),
                'available_incidents' => getAvailableIncidents($conn),
                'completed_cases' => getRescuerCompletedCases($user_id, $conn),
                'total_vitals' => getRescuerVitalsCount($user_id, $conn)
            ];
            break;
    }
    
    return $stats;
}

// Helper functions for statistics
function getTotalUsers($conn) {
    $count = 0;
    $result = $conn->query("SELECT COUNT(*) as count FROM admin");
    if ($result) $count += $result->fetch_assoc()['count'];
    $result = $conn->query("SELECT COUNT(*) as count FROM management");
    if ($result) $count += $result->fetch_assoc()['count'];
    $result = $conn->query("SELECT COUNT(*) as count FROM responder");
    if ($result) $count += $result->fetch_assoc()['count'];
    $result = $conn->query("SELECT COUNT(*) as count FROM rescuer");
    if ($result) $count += $result->fetch_assoc()['count'];
    return $count;
}

function getTotalDevices($conn) {
    $result = $conn->query("SELECT COUNT(*) as count FROM device");
    return $result ? $result->fetch_assoc()['count'] : 0;
}

function getAvailableDevices($conn) {
    $result = $conn->query("SELECT COUNT(*) as count FROM device WHERE dev_status = 'available'");
    return $result ? $result->fetch_assoc()['count'] : 0;
}

function getAssignedDevices($conn) {
    $result = $conn->query("SELECT COUNT(*) as count FROM device WHERE dev_status = 'assigned'");
    return $result ? $result->fetch_assoc()['count'] : 0;
}

function getActiveIncidents($conn) {
    $result = $conn->query("SELECT COUNT(*) as count FROM incident WHERE status IN ('ongoing', 'transferred')");
    return $result ? $result->fetch_assoc()['count'] : 0;
}

function getPendingReturns($conn) {
    $result = $conn->query("SELECT COUNT(*) as count FROM device_log WHERE date_returned IS NOT NULL AND verified_return = 0");
    return $result ? $result->fetch_assoc()['count'] : 0;
}

function getAssignedDevice($user_id, $conn) {
    $stmt = $conn->prepare("
        SELECT d.dev_serial, d.dev_status 
        FROM device_log dl
        JOIN device d ON dl.dev_id = d.dev_id
        WHERE dl.resp_id = ? AND dl.date_returned IS NULL
        LIMIT 1
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function getResponderActiveIncidents($user_id, $conn) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM incident WHERE resp_id = ? AND status = 'ongoing'");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc()['count'];
}

function getAvailableIncidents($conn) {
    $result = $conn->query("SELECT COUNT(*) as count FROM incident WHERE status = 'transferred' AND resc_id IS NULL");
    return $result ? $result->fetch_assoc()['count'] : 0;
}

function getRescuerActiveCases($user_id, $conn) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM incident WHERE resc_id = ? AND status = 'transferred'");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc()['count'];
}

function getSystemHealth($conn) {
    return [
        'database' => $conn->ping() ? 'Connected' : 'Disconnected',
        'uptime' => 'Running',
        'last_backup' => 'Not Available'
    ];
}
?>
