<?php
include '../auth/auth_check.php'; require_role(['Admin']); include '../config/database.php'; include '../includes/activity_logger.php';
require_post();verify_csrf();
$id=(int)($_POST['id']??0);$password=$_POST['password']??'';$confirm=$_POST['confirm_password']??'';
if($id<=0||strlen($password)<8||$password!==$confirm)exit('Passwords must match and contain at least 8 characters.');
$hash=password_hash($password,PASSWORD_DEFAULT);
$stmt=$conn->prepare("UPDATE users SET password=? WHERE id=?");$stmt->bind_param('si',$hash,$id);
if(!$stmt->execute()||$stmt->affected_rows!==1)exit('Unable to reset password.');
logActivity($conn,'Administration','Reset password for user ID '.$id,(int)$_SESSION['user_id']);
header('Location: employee_list.php?password_reset=1');exit();
