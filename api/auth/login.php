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

$password = md5($password);

// Check all user tables
$queries = [
    'admin' => "SELECT admin_id as user_id, admin_name as user_name, 'admin' as user_role FROM admin WHERE admin_email=? AND admin_password=?",
    'management' => "SELECT mgmt_id as user_id, mgmt_name as user_name, 'management' as user_role FROM management WHERE mgmt_email=? AND mgmt_password=?",
    'responder' => "SELECT resp_id as user_id, resp_name as user_name, 'responder' as user_role FROM responder WHERE resp_email=? AND resp_password=?",
    'rescuer' => "SELECT resc_id as user_id, resc_name as user_name, 'rescuer' as user_role FROM rescuer WHERE resc_email=? AND resc_password=?"
];

$user = null;
foreach ($queries as $role => $query) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $email, $password);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        break;
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