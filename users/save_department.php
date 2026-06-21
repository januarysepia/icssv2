<?php
include '../auth/auth_check.php';require_role(['Admin']);include '../config/database.php';include '../includes/activity_logger.php';
require_post();verify_csrf();$action=$_POST['action']??'';$admin=(int)$_SESSION['user_id'];
if($action==='add'){
    $name=trim($_POST['department_name']??'');if($name===''||mb_strlen($name)>100)exit('Valid department name is required.');
    $check=$conn->prepare("SELECT id FROM departments WHERE LOWER(TRIM(department_name))=LOWER(TRIM(?))");$check->bind_param('s',$name);$check->execute();
    if($check->get_result()->fetch_assoc())exit('Department already exists.');
    $stmt=$conn->prepare("INSERT INTO departments(department_name,status) VALUES(?,'Active')");$stmt->bind_param('s',$name);$stmt->execute();
    logActivity($conn,'Administration','Added department: '.$name,$admin);
}elseif($action==='toggle'){
    $id=(int)($_POST['id']??0);$stmt=$conn->prepare("UPDATE departments SET status=IF(status='Active','Inactive','Active') WHERE id=?");$stmt->bind_param('i',$id);$stmt->execute();
    if($stmt->affected_rows!==1)exit('Unable to update department.');
    logActivity($conn,'Administration','Changed department status for ID '.$id,$admin);
}else exit('Invalid action.');
header('Location: departments.php');exit();
