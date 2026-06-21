<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

include '../auth/auth_check.php';

require_role([
    'Boss',
    'Admin',
    'Supervisor'
]);

include '../config/database.php';

if(file_exists('../includes/activity_logger.php')){
    include '../includes/activity_logger.php';
}

if(file_exists('../includes/jo_audit.php')){
    include '../includes/jo_audit.php';
}

if(file_exists('../includes/create_notification.php')){
    include '../includes/create_notification.php';
}

$jo_id = intval($_POST['jo_id'] ?? 0);
$qaqc_task_id = intval($_POST['qaqc_task_id'] ?? 0);
$department_id = intval($_POST['department_id'] ?? 0);
$remarks = $conn->real_escape_string($_POST['remarks'] ?? '');

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['fullname'] ?? $_SESSION['name'] ?? 'User';

if($jo_id <= 0 || $qaqc_task_id <= 0 || $department_id <= 0){
    die("Invalid request. Missing JO, QA task, or department.");
}

/*
GET JO
*/

$jo = $conn->query("
SELECT *
FROM job_orders
WHERE id = '$jo_id'
")->fetch_assoc();

if(!$jo){
    die("Job Order not found.");
}

$jo_no = $jo['jo_no'];

/*
GET QA TASK
*/

$qa = $conn->query("
SELECT *
FROM qaqc_tasks
WHERE id = '$qaqc_task_id'
")->fetch_assoc();

if(!$qa){
    die("QA record not found.");
}

$failure_reason = $conn->real_escape_string($qa['failure_reason'] ?? '');

if(empty($failure_reason)){
    die("Failure reason is empty. Please check QA record.");
}

/*
GET DEPARTMENT
*/

$dept = $conn->query("
SELECT *
FROM departments
WHERE id = '$department_id'
")->fetch_assoc();

if(!$dept){
    die("Department not found.");
}

$department_name = $dept['department_name'];

/*
FIND USER ASSIGNED TO THIS DEPARTMENT IN ORIGINAL WORKFLOW
*/

$workflow_user = $conn->query("
SELECT assigned_user_id
FROM job_workflow_steps
WHERE jo_id = '$jo_id'
AND department_id = '$department_id'
LIMIT 1
")->fetch_assoc();

$assigned_user_id_sql = "NULL";
$assigned_user_id_value = null;

if($workflow_user && !empty($workflow_user['assigned_user_id'])){
    $assigned_user_id_value = intval($workflow_user['assigned_user_id']);
    $assigned_user_id_sql = "'$assigned_user_id_value'";
}

/*
PREVENT DUPLICATE ACTIVE REWORK TASK
*/

$existing = $conn->query("
SELECT id
FROM rework_tasks
WHERE jo_id = '$jo_id'
AND qaqc_task_id = '$qaqc_task_id'
AND status != 'Completed'
LIMIT 1
");

if($existing && $existing->num_rows > 0){

    echo "
    <script>
        alert('There is already an active rework task for this QA failure.');
        window.location='rework_review.php?jo_id=$jo_id';
    </script>
    ";
    exit();
}

/*
CREATE REWORK TASK
*/

$insert = $conn->query("
INSERT INTO rework_tasks
(
    jo_id,
    qaqc_task_id,
    department_id,
    assigned_user_id,
    status,
    failure_reason,
    created_by
)
VALUES
(
    '$jo_id',
    '$qaqc_task_id',
    '$department_id',
    $assigned_user_id_sql,
    'Pending',
    '$failure_reason',
    '$user_id'
)
");

if(!$insert){
    die("Failed to create rework task: " . $conn->error);
}

$rework_id = $conn->insert_id;

/*
UPDATE QA TASK
*/

$update_qa = $conn->query("
UPDATE qaqc_tasks
SET
rework_department_id = '$department_id',
rework_status = 'Rework Assigned'
WHERE id = '$qaqc_task_id'
");

if(!$update_qa){
    die("Failed to update QA task: " . $conn->error);
}

/*
UPDATE JO
*/

$update_jo = $conn->query("
UPDATE job_orders
SET workflow_status = 'Rework Required'
WHERE id = '$jo_id'
");

if(!$update_jo){
    die("Failed to update Job Order: " . $conn->error);
}

/*
LOGS
*/

if(function_exists('logActivity')){

    logActivity(
        $conn,
        'Rework',
        $user_name . ' assigned rework for ' . $jo_no . ' to ' . $department_name,
        $user_id
    );
}

if(function_exists('logJOAudit')){

    logJOAudit(
        $conn,
        $jo_id,
        'Rework Assigned',
        $user_id,
        'Rework assigned to ' . $department_name . '. Supervisor remarks: ' . $remarks
    );
}

/*
NOTIFY ASSIGNED PRODUCTION USER
*/

if($assigned_user_id_value !== null && function_exists('createNotification')){

    createNotification(
        $conn,
        $assigned_user_id_value,
        'Rework Task Assigned',
        $jo_no . ' requires rework in ' . $department_name . '.',
        '../workflow/rework_detail.php?id=' . $rework_id
    );
}

echo "
<script>
    alert('Rework task created successfully.');
    window.location='../workflow/rework_detail.php?id=$rework_id';
</script>
";

?>