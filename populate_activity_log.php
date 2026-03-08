<?php
require_once '../../database/connection.php';

// Insert some sample activity log data
$sample_activities = [
    [
        'user_name' => 'System Admin',
        'user_role' => 'admin',
        'action_type' => 'LOGIN',
        'module' => 'Authentication',
        'description' => 'Admin user logged into the system'
    ],
    [
        'user_name' => 'Operations Manager',
        'user_role' => 'management',
        'action_type' => 'DEVICE_ASSIGN',
        'module' => 'Device Management',
        'description' => 'Assigned device VW001 to responder Juan Dela Cruz'
    ],
    [
        'user_name' => 'Juan Dela Cruz',
        'user_role' => 'responder',
        'action_type' => 'INCIDENT_CREATE',
        'module' => 'Incident Management',
        'description' => 'Created new incident for patient John Smith'
    ],
    [
        'user_name' => 'System',
        'user_role' => null,
        'action_type' => 'SYSTEM_BACKUP',
        'module' => 'System',
        'description' => 'Automated system backup completed successfully'
    ],
    [
        'user_name' => 'Leo Ramirez',
        'user_role' => 'responder',
        'action_type' => 'VITAL_RECORD',
        'module' => 'Patient Monitoring',
        'description' => 'Recorded vital signs for patient Jane Doe'
    ]
];

// Insert sample data
foreach ($sample_activities as $activity) {
    $stmt = $conn->prepare("
        INSERT INTO activity_log (user_name, user_role, action_type, module, description) 
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("sssss", 
        $activity['user_name'], 
        $activity['user_role'], 
        $activity['action_type'], 
        $activity['module'], 
        $activity['description']
    );
    $stmt->execute();
}

echo "Sample activity log data inserted successfully!";
?>
