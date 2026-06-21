<?php

include '../auth/auth_check.php';
include '../config/database.php';

$jo_id = intval($_GET['id']);

$user_id = $_SESSION['user_id'];
$role = $_SESSION['system_role'] ?? $_SESSION['role'] ?? '';

/*
CHECK JO
*/

$jo = $conn->query("
SELECT *
FROM job_orders
WHERE id = '$jo_id'
")->fetch_assoc();

if(!$jo){
    die('Job Order not found.');
}

/*
IF PRODUCTION USER
CHECK ACTIVE REWORK TASK FIRST
*/

if($role == 'Production'){

    $rework = $conn->query("
    SELECT id
    FROM rework_tasks
    WHERE jo_id = '$jo_id'
    AND assigned_user_id = '$user_id'
    AND status != 'Completed'
    ORDER BY id DESC
    LIMIT 1
    ")->fetch_assoc();

    if($rework){
        header("Location: ../workflow/rework_detail.php?id=" . $rework['id']);
        exit();
    }

    /*
    IF NO REWORK, SEND TO NORMAL PRODUCTION TASK
    */

    $task = $conn->query("
    SELECT id
    FROM job_workflow_steps
    WHERE jo_id = '$jo_id'
    AND assigned_user_id = '$user_id'
    AND status != 'Completed'
    ORDER BY step_order ASC
    LIMIT 1
    ")->fetch_assoc();

    if($task){
        header("Location: ../workflow/task_detail.php?id=" . $task['id']);
        exit();
    }
}

/*
IF ENGINEER
SEND TO QA TASK
*/

if($role == 'Engineer'){

    $qa = $conn->query("
    SELECT id
    FROM qaqc_tasks
    WHERE jo_id = '$jo_id'
    AND assigned_engineer_id = '$user_id'
    AND status NOT IN ('Passed','Failed')
    ORDER BY id DESC
    LIMIT 1
    ")->fetch_assoc();

    if($qa){
        header("Location: ../qaqc/inspect_jo.php?id=" . $qa['id']);
        exit();
    }
}

/*
IF LOGISTICS
SEND TO LOGISTICS TASK
*/

if($role == 'Logistics'){

    $logistics = $conn->query("
    SELECT id
    FROM logistics_tasks
    WHERE jo_id = '$jo_id'
    AND status != 'Completed'
    ORDER BY id DESC
    LIMIT 1
    ")->fetch_assoc();

    if($logistics){
        header("Location: ../logistics/prepare_delivery.php?id=" . $logistics['id']);
        exit();
    }
}

/*
DEFAULT VIEW
Boss, Admin, Technical, Supervisor, or unassigned users
*/

header("Location: view_jo.php?id=" . $jo_id);
exit();

?>