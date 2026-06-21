<?php

include '../auth/auth_check.php';

require_role([
    'Production'
]);

include '../config/database.php';
include '../includes/activity_logger.php';
include '../includes/jo_audit.php';
include '../includes/create_notification.php';

require_post();
verify_csrf();

$id = intval($_POST['id'] ?? 0);
$action = $_POST['action'] ?? '';

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['fullname'] ?? $_SESSION['name'] ?? 'User';

$rework = $conn->query("
SELECT
rework_tasks.*,
job_orders.jo_no

FROM rework_tasks

LEFT JOIN job_orders
ON job_orders.id = rework_tasks.jo_id

WHERE rework_tasks.id = '$id'
")->fetch_assoc();

if(!$rework){
    die('Rework task not found.');
}

if ((int) $rework['assigned_user_id'] !== (int) $user_id) {
    die('Access Denied. This rework task is not assigned to you.');
}

$jo_id = $rework['jo_id'];
$jo_no = $rework['jo_no'];

if($action == 'acknowledge'){

    if ($rework['status'] !== 'Pending') {
        exit('Only a pending rework task can be acknowledged.');
    }

    $conn->query("
    UPDATE rework_tasks
    SET
    status = 'Acknowledged',
    acknowledged_at = NOW()
    WHERE id = '$id'
    ");

    logActivity(
        $conn,
        'Rework',
        $user_name . ' acknowledged rework task for ' . $jo_no,
        $user_id
    );

    logJOAudit(
        $conn,
        $jo_id,
        'Rework Acknowledged',
        $user_id,
        'Rework task acknowledged'
    );
}

elseif($action == 'start'){

    if ($rework['status'] !== 'Acknowledged') {
        exit('The rework task must be acknowledged first.');
    }

    $conn->query("
    UPDATE rework_tasks
    SET
    status = 'In Progress',
    started_at = NOW()
    WHERE id = '$id'
    ");

    $conn->query("
    UPDATE job_orders
    SET workflow_status = 'Rework In Progress'
    WHERE id = '$jo_id'
    ");

    logActivity(
        $conn,
        'Rework',
        $user_name . ' started rework for ' . $jo_no,
        $user_id
    );

    logJOAudit(
        $conn,
        $jo_id,
        'Rework Started',
        $user_id,
        'Rework work started'
    );
}

elseif($action == 'complete'){

    if ($rework['status'] !== 'In Progress') {
        exit('The rework task must be in progress before completion.');
    }

    $conn->query("
    UPDATE rework_tasks
    SET
    status = 'Completed',
    completed_at = NOW()
    WHERE id = '$id'
    ");

    /*
    UPDATE QA TASK
    */

    $conn->query("
    UPDATE qaqc_tasks
    SET
    status = 'For Reinspection',
    rework_status = 'Completed'
    WHERE id = '{$rework['qaqc_task_id']}'
    ");

    /*
    UPDATE JO
    */

    $conn->query("
    UPDATE job_orders
    SET workflow_status = 'For QA Reinspection'
    WHERE id = '$jo_id'
    ");

    /*
    NOTIFY QA ENGINEERS
    */

    $engineers = $conn->query("
    SELECT id
    FROM users
    WHERE system_role = 'Engineer'
    AND status = 'Active'
    ");

    while($eng = $engineers->fetch_assoc()){

        createNotification(
            $conn,
            $eng['id'],
            'QA Reinspection Required',
            $jo_no . ' has completed rework and is ready for QA reinspection.',
            '../qaqc/qaqc_list.php'
        );
    }

    logActivity(
        $conn,
        'Rework',
        $user_name . ' completed rework for ' . $jo_no,
        $user_id
    );

    logJOAudit(
        $conn,
        $jo_id,
        'Rework Completed',
        $user_id,
        'Rework completed and returned to QA'
    );
} else {
    exit('Invalid action.');
}

echo "
<script>
alert('Rework updated successfully.');
window.location='rework_detail.php?id=$id';
</script>
";

?>
