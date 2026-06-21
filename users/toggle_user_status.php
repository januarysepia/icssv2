<?php
include '../auth/auth_check.php';require_role(['Admin']);include '../config/database.php';include '../includes/activity_logger.php';
require_post();verify_csrf();
$id=(int)($_POST['id']??0);
if($id<=0||$id===(int)$_SESSION['user_id'])exit('You cannot deactivate your own active session.');
$stmt=$conn->prepare("UPDATE users SET status=IF(status='Active','Inactive','Active') WHERE id=?");
$stmt->bind_param('i',$id);$stmt->execute();
if($stmt->affected_rows!==1)exit('Unable to update user status.');
logActivity($conn,'Administration','Changed status of user ID '.$id,(int)$_SESSION['user_id']);
header('Location: employee_list.php');exit();
