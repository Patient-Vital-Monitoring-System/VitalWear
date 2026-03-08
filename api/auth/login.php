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

$stmt = $conn->prepare("
SELECT resp_id,resp_name
FROM responder
WHERE resp_email=?
AND resp_password=?
");

$stmt->bind_param("ss",$email,$password);
$stmt->execute();

$result = $stmt->get_result();

if($result->num_rows > 0){

$user = $result->fetch_assoc();

$_SESSION['responder_id']=$user['resp_id'];
$_SESSION['responder_name']=$user['resp_name'];

echo json_encode([
  "status"=>"success",
  "responder_id"=>$user['resp_id'],
  "responder_name"=>$user['resp_name']
]);

}else{

echo json_encode(["status"=>"invalid"]);

}