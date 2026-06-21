<?php

include '../auth/auth_check.php';

require_role([
    'Boss',
    'Admin',
    'Supervisor',
    'Engineer'
]);

include '../config/database.php';
include '../includes/activity_logger.php';
include '../includes/jo_audit.php';
include '../includes/create_notification.php';

$task_id = intval($_POST['task_id'] ?? 0);
$status = $conn->real_escape_string($_POST['status'] ?? '');
$remarks = $conn->real_escape_string($_POST['remarks'] ?? '');
$failure_reason = $conn->real_escape_string($_POST['failure_reason'] ?? '');

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['fullname'] ?? $_SESSION['name'] ?? 'User';

/*
GET QA/QC TASK + JO
*/

$task = $conn->query("
SELECT
    qaqc_tasks.*,
    job_orders.jo_no,
    job_orders.project_name
FROM qaqc_tasks

LEFT JOIN job_orders
ON job_orders.id = qaqc_tasks.jo_id

WHERE qaqc_tasks.id = '$task_id'
")->fetch_assoc();

if(!$task){
    die("QA/QC task not found.");
}

$old_status = $task['status'];
$jo_id = $task['jo_id'];
$jo_no = $task['jo_no'];

/*
PREVENT UPDATE IF QA IS ALREADY COMPLETED
*/

if($old_status == 'Passed' || $old_status == 'Failed'){

    echo "
    <script>
        alert('This QA/QC inspection is already completed and can no longer be updated.');
        window.location='inspect_jo.php?id=$task_id';
    </script>
    ";

    exit();
}

/*
VALIDATE STATUS MOVEMENT
*/

$valid_movement = false;

if($old_status == 'Pending QA' && $status == 'Acknowledged'){
    $valid_movement = true;
}

if($old_status == 'Acknowledged' && $status == 'Inspecting'){
    $valid_movement = true;
}

if($old_status == 'Inspecting' && ($status == 'Passed' || $status == 'Failed')){
    $valid_movement = true;
}

if($old_status == 'For Reinspection' && $status == 'Inspecting'){
    $valid_movement = true;
}

if(!$valid_movement){

    echo "
    <script>
        alert('Invalid QA/QC status movement. Current status is $old_status.');
        window.location='inspect_jo.php?id=$task_id';
    </script>
    ";

    exit();
}

/*
VALIDATE FAILURE REASON
*/

if($status == 'Failed' && empty($failure_reason)){

    echo "
    <script>
        alert('Please enter failure reason.');
        window.history.back();
    </script>
    ";

    exit();
}

/*
UPDATE QA/QC TASK
*/

$query = "
UPDATE qaqc_tasks
SET
    status = '$status',
    remarks = '$remarks'
";

if($status == 'Acknowledged'){
    $query .= ",
    acknowledged_at = NOW()
    ";
}

if($status == 'Inspecting'){
    $query .= ",
    inspected_at = NOW()
    ";
}

if($status == 'Passed'){
    $query .= ",
    completed_at = NOW(),
    failure_reason = NULL,
    rework_department_id = NULL,
    rework_status = NULL
    ";
}

if($status == 'Failed'){
    $query .= ",
    completed_at = NOW(),
    failure_reason = '$failure_reason',
    rework_department_id = NULL,
    rework_status = 'Waiting Supervisor Review'
    ";
}

$query .= "
WHERE id = '$task_id'
";

$conn->query($query);

if($conn->error){
    die($conn->error);
}

/*
ACTIVITY + JO AUDIT
*/

logActivity(
    $conn,
    'QA/QC',
    $user_name . ' updated QA status of ' . $jo_no . ' from ' . $old_status . ' to ' . $status,
    $user_id
);

logJOAudit(
    $conn,
    $jo_id,
    'QA Status Updated',
    $user_id,
    'QA status changed from ' . $old_status . ' to ' . $status . '. Remarks: ' . $remarks
);

/*
IF QA PASSED
*/

if($status == 'Passed'){

    $conn->query("
    UPDATE job_orders
    SET workflow_status = 'QA Passed'
    WHERE id = '$jo_id'
    ");

    /*
    CREATE LOGISTICS TASK IF NOT EXISTING
    */

    $existing_logistics = $conn->query("
    SELECT id
    FROM logistics_tasks
    WHERE jo_id = '$jo_id'
    LIMIT 1
    ");

    if($existing_logistics && $existing_logistics->num_rows == 0){

        $conn->query("
        INSERT INTO logistics_tasks
        (
            jo_id,
            status,
            remarks
        )
        VALUES
        (
            '$jo_id',
            'Pending Logistics',
            'Created automatically after QA Passed'
        )
        ");

        $logistics_task_id = $conn->insert_id;

        /*
        OPTIONAL LOGISTICS INITIAL LOG
        */

        $conn->query("
        INSERT INTO logistics_logs
        (
            logistics_task_id,
            updated_by,
            old_status,
            new_status,
            remarks
        )
        VALUES
        (
            '$logistics_task_id',
            '$user_id',
            'QA Passed',
            'Pending Logistics',
            'Created automatically after QA Passed'
        )
        ");
    }

    logActivity(
        $conn,
        'QA/QC',
        $user_name . ' passed QA for ' . $jo_no,
        $user_id
    );

    logJOAudit(
        $conn,
        $jo_id,
        'QA Passed',
        $user_id,
        'QA passed by ' . $user_name . '. Remarks: ' . $remarks
    );

    /*
    NOTIFY LOGISTICS USERS
    */

    $logistics_users = $conn->query("
    SELECT id
    FROM users
    WHERE system_role = 'Logistics'
    AND status = 'Active'
    ");

    while($lg = $logistics_users->fetch_assoc()){

        createNotification(
            $conn,
            $lg['id'],
            'JO Ready for Logistics',
            $jo_no . ' has passed QA and is ready for logistics.',
            '../logistics/logistics_list.php'
        );
    }
}

/*
IF QA FAILED
*/

if($status == 'Failed'){

    $conn->query("
    UPDATE job_orders
    SET workflow_status = 'Waiting Supervisor Review'
    WHERE id = '$jo_id'
    ");

    logActivity(
        $conn,
        'QA/QC',
        $user_name . ' failed QA for ' . $jo_no,
        $user_id
    );

    logJOAudit(
        $conn,
        $jo_id,
        'QA Failed',
        $user_id,
        'Failure Reason: ' . $failure_reason
    );

    /*
    NOTIFY SUPERVISORS
    */

    $supervisors = $conn->query("
    SELECT id
    FROM users
    WHERE system_role = 'Supervisor'
    AND status = 'Active'
    ");

    while($sup = $supervisors->fetch_assoc()){

        createNotification(
            $conn,
            $sup['id'],
            'QA Failed - Review Required',
            $jo_no . ' failed QA and requires supervisor review.',
            '../supervisor/rework_review.php?jo_id=' . $jo_id
        );
    }
}

echo "
<script>
alert('QA/QC Updated Successfully');
window.location='inspect_jo.php?id=$task_id';
</script>
";

?>