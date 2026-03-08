<?php

error_reporting(E_ALL);
ini_set('display_errors',1);

session_start();
include "../../database/connection.php";

header("Content-Type: application/json");

$email = $_POST['email'] ?? "";
$password = $_POST['password'] ?? "";

if(empty($email) || empty($password)){
echo json_encode(["status"=>"empty"]);
exit();
}

// Check all user tables - handle both MD5 and password_hash
$queries = [
    'admin' => "SELECT admin_id as user_id, admin_name as user_name, 'admin' as user_role, admin_password as password FROM admin WHERE admin_email=?",
    'management' => "SELECT mgmt_id as user_id, mgmt_name as user_name, 'management' as user_role, mgmt_password as password FROM management WHERE mgmt_email=?",
    'responder' => "SELECT resp_id as user_id, resp_name as user_name, 'responder' as user_role, resp_password as password FROM responder WHERE resp_email=?",
    'rescuer' => "SELECT resc_id as user_id, resc_name as user_name, 'rescuer' as user_role, resc_password as password FROM rescuer WHERE resc_email=?"
];

$user = null;
foreach ($queries as $role => $query) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $userData = $result->fetch_assoc();
        $storedPassword = $userData['password'];
        
        // Check password with both MD5 and password_hash
        $md5Password = md5($password);
        $passwordValid = false;
        
        if ($storedPassword === $md5Password) {
            // MD5 hash matches (for seed data)
            $passwordValid = true;
        } elseif (password_verify($password, $storedPassword)) {
            // Modern password hash matches (for newly created users)
            $passwordValid = true;
        }
        
        if ($passwordValid) {
            $user = [
                'user_id' => $userData['user_id'],
                'user_name' => $userData['user_name'],
                'user_role' => $userData['user_role']
            ];
            break;
        }
    }
}

if ($user) {
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['user_name'] = $user['user_name'];
    $_SESSION['user_role'] = $user['user_role'];
    
    echo json_encode([
        "status" => "success",
        "user_id" => $user['user_id'],
        "user_name" => $user['user_name'],
        "user_role" => $user['user_role']
    ]);
} else {
    echo json_encode(["status" => "invalid"]);
}