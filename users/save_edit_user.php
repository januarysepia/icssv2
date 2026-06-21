<?php
include '../auth/auth_check.php';
require_role(['Admin']);
include '../config/database.php';
include '../includes/activity_logger.php';
require_post(); verify_csrf();

$id=(int)($_POST['id']??0);
$employee_no=trim($_POST['employee_no']??'');
$fullname=trim($_POST['fullname']??'');
$username=trim($_POST['username']??'');
$role=$_POST['system_role']??'';
$position=trim($_POST['position']??'');
$department_id=null;
$roles=['Boss','Admin','Technical','Engineer','Supervisor','Production','Purchasing','Logistics'];
if($id<=0||$employee_no===''||$fullname===''||$username===''||!in_array($role,$roles,true)) exit('Invalid user details.');
if($role==='Production'){
    $department_id=(int)($_POST['department_id']??0);
    $check=$conn->prepare("SELECT id FROM departments WHERE id=? AND status='Active' AND department_name NOT IN ('QA/QC','Logistics')");
    $check->bind_param('i',$department_id);$check->execute();
    if(!$check->get_result()->fetch_assoc()) exit('Valid production department is required.');
}
$duplicate=$conn->prepare("SELECT id FROM users WHERE (username=? OR employee_no=?) AND id<>?");
$duplicate->bind_param('ssi',$username,$employee_no,$id);$duplicate->execute();
if($duplicate->get_result()->fetch_assoc()) exit('Username or employee number already exists.');
$stmt=$conn->prepare("UPDATE users SET employee_no=?,fullname=?,username=?,system_role=?,department_id=?,position=? WHERE id=?");
$stmt->bind_param('ssssisi',$employee_no,$fullname,$username,$role,$department_id,$position,$id);
if(!$stmt->execute()) exit('Unable to update user.');
logActivity($conn,'Administration','Updated user account: '.$fullname,(int)$_SESSION['user_id']);
header('Location: employee_list.php'); exit();
