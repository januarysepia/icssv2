<?php

include '../auth/auth_check.php';

require_role([
    'Production'
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

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['fullname'] ?? $_SESSION['name'] ?? 'User';

require_post();
verify_csrf();

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$action = $_POST['action'] ?? '';

function taskFeedbackRedirect($id, $type, $message, $code = ''){
    $_SESSION['task_feedback'] = [
        'type' => $type === 'success' ? 'success' : 'danger',
        'message' => $message,
        'code' => $code
    ];

    $target = $id > 0
        ? 'task_detail.php?id=' . (int) $id
        : 'my_tasks.php';

    header('Location: ' . $target);
    exit();
}

if($id <= 0 || empty($action)){
    taskFeedbackRedirect($id, 'error', 'The submitted task request is incomplete or invalid.', 'TASK-REQ-001');
}

/*
GET TASK DETAILS
*/

$task = $conn->query("
SELECT
job_workflow_steps.*,
TIMESTAMPDIFF(SECOND, job_workflow_steps.started_at, NOW()) AS elapsed_work_seconds,
job_orders.jo_no,
job_orders.project_name,
departments.department_name

FROM job_workflow_steps

LEFT JOIN job_orders
ON job_orders.id = job_workflow_steps.jo_id

LEFT JOIN departments
ON departments.id = job_workflow_steps.department_id

WHERE job_workflow_steps.id = '$id'
")->fetch_assoc();

if(!$task){
    taskFeedbackRedirect($id, 'error', 'The requested production task could not be found.', 'TASK-NOTFOUND-001');
}

/*
ONLY ASSIGNED PRODUCTION USER CAN UPDATE OWN TASK
Boss/Admin/Supervisor can view but should not update via this route
*/

if((int) $task['assigned_user_id'] !== (int) $user_id){
    taskFeedbackRedirect($id, 'error', 'Access denied. This task is not assigned to your account.', 'TASK-AUTH-001');
}

$jo_id = $task['jo_id'];
$jo_no = $task['jo_no'];
$department_name = $task['department_name'] ?? 'Production';

/*
HELPER SAFE LOGS
*/

function safeActivityLog($conn, $module, $activity, $user_id){
    if(function_exists('logActivity')){
        logActivity($conn, $module, $activity, $user_id);
    }
}

function safeJOAudit($conn, $jo_id, $action, $user_id, $remarks = ''){
    if(function_exists('logJOAudit')){
        logJOAudit($conn, $jo_id, $action, $user_id, $remarks);
    }
}

/*
ACTION HANDLER
*/

if($action == 'acknowledge'){

    if($task['status'] != 'Pending'){
        taskFeedbackRedirect($id, 'error', 'Only a pending task can be acknowledged.', 'TASK-STATE-001');
    }

    $update = $conn->prepare("
        UPDATE job_workflow_steps
        SET status = 'Acknowledged', acknowledged_by = ?, acknowledged_at = NOW()
        WHERE id = ? AND assigned_user_id = ? AND status = 'Pending'
    ");
    $update->bind_param('iii', $user_id, $id, $user_id);
    $update->execute();

    if ($update->affected_rows !== 1) {
        taskFeedbackRedirect($id, 'error', 'The task was not updated because its status may have already changed.', 'TASK-CONFLICT-001');
    }

    safeActivityLog(
        $conn,
        'Production',
        $user_name . ' acknowledged ' . $department_name . ' task for ' . $jo_no,
        $user_id
    );

    safeJOAudit(
        $conn,
        $jo_id,
        'Production Task Acknowledged',
        $user_id,
        $department_name . ' task acknowledged by ' . $user_name
    );

}elseif($action == 'start'){

    if($task['status'] != 'Acknowledged'){
        taskFeedbackRedirect($id, 'error', 'The task must be acknowledged before work can start.', 'TASK-STATE-002');
    }

    $update = $conn->prepare("
        UPDATE job_workflow_steps
        SET status = 'In Progress', started_at = NOW(), progress_percent = 1
        WHERE id = ? AND assigned_user_id = ? AND status = 'Acknowledged'
    ");
    $update->bind_param('ii', $id, $user_id);
    $update->execute();

    if ($update->affected_rows !== 1) {
        taskFeedbackRedirect($id, 'error', 'The task was not started because its status may have already changed.', 'TASK-CONFLICT-002');
    }

    safeActivityLog(
        $conn,
        'Production',
        $user_name . ' started ' . $department_name . ' task for ' . $jo_no,
        $user_id
    );

    safeJOAudit(
        $conn,
        $jo_id,
        'Production Task Started',
        $user_id,
        $department_name . ' task started by ' . $user_name
    );

}elseif($action == 'update_progress'){

    if($task['status'] != 'In Progress'){
        taskFeedbackRedirect($id, 'error', 'Progress can only be updated while the task is in progress.', 'TASK-PROGRESS-001');
    }

    $progress_percent = max(1, min(100, intval($_POST['progress_percent'] ?? 0)));
    $progress_remarks = trim($_POST['progress_remarks'] ?? '');
    $current_progress = (int) ($task['progress_percent'] ?? 0);

    if ($progress_percent < $current_progress) {
        taskFeedbackRedirect($id, 'error', 'Progress cannot be lower than the previously saved percentage.', 'TASK-PROGRESS-002');
    }
    if ($progress_percent === $current_progress && $progress_remarks === '') {
        taskFeedbackRedirect($id, 'error', 'Move the progress slider or enter an update note before saving.', 'TASK-PROGRESS-003');
    }

    $conn->begin_transaction();
    $update = $conn->prepare("
        UPDATE job_workflow_steps
        SET progress_percent = ?
        WHERE id = ? AND assigned_user_id = ? AND status = 'In Progress'
    ");
    $update->bind_param('iii', $progress_percent, $id, $user_id);
    $update->execute();

    $log = $conn->prepare("
        INSERT INTO production_progress_logs
            (workflow_step_id, jo_id, user_id, progress_percent, remarks)
        VALUES (?, ?, ?, ?, ?)
    ");
    $log->bind_param('iiiis', $id, $jo_id, $user_id, $progress_percent, $progress_remarks);
    $log->execute();

    if ($update->errno || $log->errno) {
        $conn->rollback();
        taskFeedbackRedirect($id, 'error', 'The production progress update could not be saved. Please try again.', 'TASK-PROGRESS-004');
    }
    $conn->commit();

    safeActivityLog($conn, 'Production', $user_name . ' updated ' . $department_name . ' progress to ' . $progress_percent . '% for ' . $jo_no, $user_id);
    safeJOAudit(
        $conn,
        $jo_id,
        'Production Progress Updated',
        $user_id,
        $department_name . ' progress: ' . $progress_percent . '%'
            . ($progress_remarks !== '' ? '. ' . $progress_remarks : '')
    );

}elseif($action == 'complete'){

    if($task['status'] != 'In Progress'){
        taskFeedbackRedirect($id, 'error', 'The task must be in progress before it can be completed.', 'TASK-STATE-003');
    }

    $minimum_work_minutes = 15;
    $completion_remarks = trim($_POST['completion_remarks'] ?? '');

    if (empty($task['started_at'])) {
        taskFeedbackRedirect($id, 'error', 'The task start time is missing. Please contact the administrator.', 'TASK-TIME-001');
    }

    if ((int) ($task['progress_percent'] ?? 0) < 100) {
        taskFeedbackRedirect($id, 'error', 'Save the task progress at 100% before completing the task.', 'TASK-COMPLETE-001');
    }

    $elapsed_seconds = max(0, (int) ($task['elapsed_work_seconds'] ?? 0));
    if ($elapsed_seconds < ($minimum_work_minutes * 60)) {
        $remaining_seconds = ($minimum_work_minutes * 60) - $elapsed_seconds;
        $remaining_minutes = (int) ceil($remaining_seconds / 60);
        taskFeedbackRedirect(
            $id,
            'error',
            'The task cannot be completed yet. Please continue working for approximately ' . $remaining_minutes . ' more minute(s).',
            'TASK-COMPLETE-002'
        );
    }

    $proof_file_name = null;
    $proof_path = null;
    $proof = $_FILES['completion_proof'] ?? null;

    if ($proof && ($proof['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        if (($proof['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            taskFeedbackRedirect($id, 'error', 'The proof photo upload failed. Please select the file again.', 'TASK-UPLOAD-001');
        }
        if (($proof['size'] ?? 0) <= 0 || $proof['size'] > 5 * 1024 * 1024) {
            taskFeedbackRedirect($id, 'error', 'The proof photo must be a valid file no larger than 5 MB.', 'TASK-UPLOAD-002');
        }

        $allowed_proof_types = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
        ];
        $proof_mime = (new finfo(FILEINFO_MIME_TYPE))->file($proof['tmp_name']);
        if (!isset($allowed_proof_types[$proof_mime])) {
            taskFeedbackRedirect($id, 'error', 'Only valid JPG and PNG proof photos are allowed.', 'TASK-UPLOAD-003');
        }

        $proof_file_name = bin2hex(random_bytes(16)) . '.' . $allowed_proof_types[$proof_mime];
        $proof_directory = __DIR__ . '/../uploads/task_proofs';
        if (!is_dir($proof_directory) && !mkdir($proof_directory, 0775, true) && !is_dir($proof_directory)) {
            taskFeedbackRedirect($id, 'error', 'The proof photo storage could not be prepared.', 'TASK-UPLOAD-004');
        }
        $proof_path = $proof_directory . '/' . $proof_file_name;
        if (!move_uploaded_file($proof['tmp_name'], $proof_path)) {
            taskFeedbackRedirect($id, 'error', 'The proof photo could not be stored. Please try again.', 'TASK-UPLOAD-005');
        }
    }

    $update = $conn->prepare("
        UPDATE job_workflow_steps
        SET status = 'Completed',
            completed_at = NOW(),
            progress_percent = 100,
            completion_remarks = ?,
            completion_proof = ?
        WHERE id = ?
          AND assigned_user_id = ?
          AND status = 'In Progress'
          AND started_at <= DATE_SUB(NOW(), INTERVAL 15 MINUTE)
    ");
    $update->bind_param('ssii', $completion_remarks, $proof_file_name, $id, $user_id);
    $update->execute();

    if ($update->affected_rows !== 1) {
        if ($proof_path && is_file($proof_path)) {
            @unlink($proof_path);
        }
        taskFeedbackRedirect($id, 'error', 'The task was not completed because its status may have already changed.', 'TASK-CONFLICT-003');
    }

    safeActivityLog(
        $conn,
        'Production',
        $user_name . ' completed ' . $department_name . ' task for ' . $jo_no,
        $user_id
    );

    safeJOAudit(
        $conn,
        $jo_id,
        'Production Task Completed',
        $user_id,
        $department_name . ' task completed by ' . $user_name
            . ($completion_remarks !== '' ? '. Remarks: ' . $completion_remarks : '. No completion remarks provided.')
    );

    /*
    CHECK IF ALL PRODUCTION STEPS ARE COMPLETED
    */

    $remaining = $conn->query("
    SELECT COUNT(*) AS total
    FROM job_workflow_steps
    WHERE jo_id = '$jo_id'
    AND status != 'Completed'
    ")->fetch_assoc();

    if($remaining['total'] == 0){

        $conn->query("
        UPDATE job_orders
        SET workflow_status = 'For QA Inspection'
        WHERE id = '$jo_id'
        ");

        safeActivityLog(
            $conn,
            'Production',
            'All production steps completed for ' . $jo_no,
            $user_id
        );

        safeJOAudit(
            $conn,
            $jo_id,
            'Production Completed',
            $user_id,
            'All production workflow steps are completed'
        );

        /*
        ASSIGN QA TASK TO FIRST ACTIVE ENGINEER
        */

        $engineer = $conn->query("
        SELECT id
        FROM users
        WHERE system_role = 'Engineer'
        AND status = 'Active'
        ORDER BY id ASC
        LIMIT 1
        ")->fetch_assoc();

        if($engineer){

            $engineer_id = $engineer['id'];

            $existing_qa = $conn->query("
            SELECT id
            FROM qaqc_tasks
            WHERE jo_id = '$jo_id'
            LIMIT 1
            ");

            if($existing_qa->num_rows == 0){

                $conn->query("
                INSERT INTO qaqc_tasks
                (
                    jo_id,
                    assigned_engineer_id,
                    status,
                    remarks
                )

                VALUES

                (
                    '$jo_id',
                    '$engineer_id',
                    'Pending QA',
                    'Created automatically after production completion'
                )
                ");

                if(function_exists('createNotification')){
                    createNotification(
                        $conn,
                        $engineer_id,
                        'JO Ready for QA',
                        $jo_no . ' is ready for QA inspection.',
                        '../qaqc/qaqc_list.php'
                    );
                }

                safeActivityLog(
                    $conn,
                    'QA',
                    $jo_no . ' was sent to QA inspection',
                    $user_id
                );

                safeJOAudit(
                    $conn,
                    $jo_id,
                    'Sent to QA',
                    $user_id,
                    'QA task created automatically after production completion'
                );
            }
        }
    }

}else{

    taskFeedbackRedirect($id, 'error', 'The selected task action is not supported.', 'TASK-ACTION-001');
}

taskFeedbackRedirect($id, 'success', 'Task updated successfully.');

?>
