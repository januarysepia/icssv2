<?php

include '../auth/auth_check.php';
require_role(['Boss', 'Admin', 'Supervisor']);
include '../config/database.php';
include '../includes/activity_logger.php';
include '../includes/jo_audit.php';

require_post();
verify_csrf();

$jo_id = intval($_POST['jo_id'] ?? 0);
$mode = ($_POST['mode'] ?? '') === 'revision' ? 'revision' : 'initial';
$revision_reason = trim($_POST['revision_reason'] ?? '');
$departments = array_values(array_unique(array_map('intval', $_POST['departments'] ?? [])));
$user_id = intval($_SESSION['user_id']);
$user_name = $_SESSION['fullname'] ?? $_SESSION['name'] ?? 'User';

if ($jo_id <= 0 || !$departments) {
    exit('Please select at least one production step.');
}
if ($mode === 'revision' && mb_strlen($revision_reason) < 5) {
    exit('A clear revision reason is required.');
}

$allowed_departments = [];
$allowed_result = $conn->query("
    SELECT id, department_name FROM departments
    WHERE department_name IN ('Fabrication','Hotworks','Painting','Busbarring','Assembly','Controls')
");
while ($department = $allowed_result->fetch_assoc()) {
    $allowed_departments[(int) $department['id']] = $department['department_name'];
}
foreach ($departments as $department_id) {
    if (!isset($allowed_departments[$department_id])) {
        exit('Invalid production department selected.');
    }
}

$conn->begin_transaction();
try {
    $jo_stmt = $conn->prepare("SELECT * FROM job_orders WHERE id = ? FOR UPDATE");
    $jo_stmt->bind_param('i', $jo_id);
    $jo_stmt->execute();
    $jo = $jo_stmt->get_result()->fetch_assoc();
    $jo_stmt->close();

    if (!$jo) {
        throw new RuntimeException('Job Order not found.');
    }
    if ($jo['workflow_status'] === 'Completed' || $jo['overall_status'] === 'Completed') {
        throw new RuntimeException('Completed job orders are read-only.');
    }

    $existing_result = $conn->query("
        SELECT jws.*, d.department_name, u.employee_no, u.fullname
        FROM job_workflow_steps jws
        LEFT JOIN departments d ON d.id = jws.department_id
        LEFT JOIN users u ON u.id = jws.assigned_user_id
        WHERE jws.jo_id = '$jo_id'
        ORDER BY jws.step_order, jws.id
        FOR UPDATE
    ");
    $existing_steps = [];
    $old_summary_parts = [];
    $has_progress = false;
    while ($step = $existing_result->fetch_assoc()) {
        $existing_steps[] = $step;
        $has_progress = $has_progress || $step['status'] !== 'Pending';
        $old_summary_parts[] = ($step['department_name'] ?: 'Unknown Department')
            . ' → ' . trim(($step['employee_no'] ?: '') . ' - ' . ($step['fullname'] ?: ''), ' -');
    }

    if ($mode === 'initial') {
        if ($existing_steps || $jo['workflow_status'] !== 'For Validation') {
            throw new RuntimeException('The initial workflow has already been assigned.');
        }
    } elseif (!$existing_steps || $jo['workflow_status'] !== 'In Production' || $has_progress) {
        throw new RuntimeException('Workflow revision is locked because production has already progressed.');
    }

    $new_assignments = [];
    foreach ($departments as $department_id) {
        $assigned_user = intval($_POST['assigned_user_' . $department_id] ?? 0);
        $user_stmt = $conn->prepare("
            SELECT id, employee_no, fullname FROM users
            WHERE id = ? AND department_id = ? AND status = 'Active'
        ");
        $user_stmt->bind_param('ii', $assigned_user, $department_id);
        $user_stmt->execute();
        $assigned = $user_stmt->get_result()->fetch_assoc();
        $user_stmt->close();
        if (!$assigned) {
            throw new RuntimeException('An assigned employee is invalid or belongs to another department.');
        }
        $new_assignments[] = [
            'department_id' => $department_id,
            'assigned_user_id' => $assigned_user,
            'summary' => $allowed_departments[$department_id] . ' → '
                . trim(($assigned['employee_no'] ?: '') . ' - ' . $assigned['fullname'], ' -'),
        ];
    }

    if ($mode === 'revision') {
        $delete = $conn->prepare("DELETE FROM job_workflow_steps WHERE jo_id = ?");
        $delete->bind_param('i', $jo_id);
        $delete->execute();
        $delete->close();
    }

    $insert = $conn->prepare("
        INSERT INTO job_workflow_steps
        (jo_id, step_order, department_id, assigned_user_id, status)
        VALUES (?, ?, ?, ?, 'Pending')
    ");
    foreach ($new_assignments as $index => $assignment) {
        $step_order = $index + 1;
        $department_id = $assignment['department_id'];
        $assigned_user_id = $assignment['assigned_user_id'];
        $insert->bind_param('iiii', $jo_id, $step_order, $department_id, $assigned_user_id);
        $insert->execute();
    }
    $insert->close();

    $update = $conn->prepare("
        UPDATE job_orders SET workflow_status = 'In Production', approved_by = ?, approved_at = NOW()
        WHERE id = ?
    ");
    $update->bind_param('ii', $user_id, $jo_id);
    $update->execute();
    $update->close();

    $new_summary = implode('; ', array_column($new_assignments, 'summary'));
    if ($mode === 'revision') {
        $audit_action = 'Workflow Revised';
        $audit_remarks = 'Reason: ' . $revision_reason
            . ' | Previous: ' . implode('; ', $old_summary_parts)
            . ' | Revised: ' . $new_summary;
        $activity = $user_name . ' revised workflow for ' . $jo['jo_no'] . '. Reason: ' . $revision_reason;
    } else {
        $audit_action = 'Workflow Assigned';
        $audit_remarks = $new_summary;
        $activity = $user_name . ' assigned workflow for ' . $jo['jo_no'];
    }

    logActivity($conn, 'Workflow', $activity, $user_id);
    logJOAudit($conn, $jo_id, $audit_action, $user_id, $audit_remarks);
    $conn->commit();
} catch (Throwable $exception) {
    $conn->rollback();
    exit(h($exception->getMessage()));
}

$message = $mode === 'revision'
    ? 'Workflow revision saved and recorded in the audit trail.'
    : 'Workflow assigned successfully.';
?>
<script>
alert(<?= json_encode($message) ?>);
window.location='../job_orders/view_jo.php?id=<?= $jo_id ?>';
</script>
