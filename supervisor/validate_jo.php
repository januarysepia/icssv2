<?php

include '../auth/auth_check.php';
require_role(['Boss', 'Admin', 'Supervisor']);
include '../config/database.php';

$id = intval($_GET['id'] ?? 0);
$mode = ($_GET['mode'] ?? '') === 'revision' ? 'revision' : 'initial';

$stmt = $conn->prepare("SELECT * FROM job_orders WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    exit('Job Order not found.');
}
if ($row['workflow_status'] === 'Completed' || $row['overall_status'] === 'Completed') {
    exit('This job order is completed and its workflow is read-only.');
}

$existing_result = $conn->query("
    SELECT department_id, assigned_user_id, status
    FROM job_workflow_steps
    WHERE jo_id = '$id'
    ORDER BY step_order, id
");
$existing_steps = [];
$progressed_steps = 0;
while ($step = $existing_result->fetch_assoc()) {
    $existing_steps[(int) $step['department_id']] = (int) $step['assigned_user_id'];
    if ($step['status'] !== 'Pending') {
        $progressed_steps++;
    }
}

$has_workflow = count($existing_steps) > 0;
if ($mode === 'initial' && ($has_workflow || $row['workflow_status'] !== 'For Validation')) {
    exit('The initial workflow has already been assigned.');
}
if ($mode === 'revision' && (!$has_workflow || $row['workflow_status'] !== 'In Production' || $progressed_steps > 0)) {
    exit('Workflow revision is locked because production has already progressed.');
}

$attachment_stmt = $conn->prepare("
    SELECT * FROM job_order_attachments WHERE jo_id = ? ORDER BY id DESC LIMIT 1
");
$attachment_stmt->bind_param('i', $id);
$attachment_stmt->execute();
$file = $attachment_stmt->get_result()->fetch_assoc();
$attachment_stmt->close();

$departments = $conn->query("
    SELECT *
    FROM departments
    WHERE department_name IN ('Fabrication','Hotworks','Painting','Busbarring','Assembly','Controls')
    ORDER BY FIELD(department_name,'Fabrication','Hotworks','Painting','Busbarring','Assembly','Controls')
");
?>
<!DOCTYPE html>
<html>
<head>
    <title><?= $mode === 'revision' ? 'Revise' : 'Assign' ?> Workflow</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-4" style="max-width:1050px;">
    <div class="card shadow border-0">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><?= $mode === 'revision' ? 'Revise Production Workflow' : 'Assign Production Workflow' ?></h4>
            <div>
                <a href="../dashboard/index.php" class="btn btn-light btn-sm">Dashboard</a>
                <a href="../job_orders/view_jo.php?id=<?= $id ?>" class="btn btn-secondary btn-sm">Back</a>
            </div>
        </div>
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-6">
                    <p class="mb-1"><b>JO No:</b> <?= h($row['jo_no']) ?></p>
                    <p class="mb-1"><b>Client:</b> <?= h($row['client_name']) ?></p>
                    <p class="mb-1"><b>Project:</b> <?= h($row['project_name']) ?></p>
                </div>
                <div class="col-md-6">
                    <p class="mb-1"><b>Engineer:</b> <?= h($row['engineer_name']) ?></p>
                    <p class="mb-1"><b>Due Date:</b> <?= h($row['due_date']) ?></p>
                    <p class="mb-1"><b>Status:</b> <?= h($row['workflow_status']) ?></p>
                </div>
            </div>

            <?php if ($file): ?>
                <span class="badge <?= ($file['version_no'] ?? 'Original') === 'Original' ? 'bg-secondary' : 'bg-warning text-dark' ?> mb-2">
                    <?= h($file['version_no'] ?? 'Original') ?>
                </span>
                <a href="../uploads/drawings/<?= h($file['file_name']) ?>" target="_blank"
                   class="btn btn-primary btn-sm mb-3">Open Latest Drawing</a>
                <?php if (!empty($file['revision_notes'])): ?>
                    <div class="alert alert-warning py-2">
                        <strong>Drawing revision:</strong> <?= h($file['revision_notes']) ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="alert alert-warning py-2">No drawing attachment found.</div>
            <?php endif; ?>

            <?php if ($mode === 'revision'): ?>
                <div class="alert alert-warning">
                    Revision is allowed because every current step is still Pending. The reason and assignment changes
                    will be recorded in the JO Audit Trail.
                </div>
            <?php endif; ?>

            <form action="save_workflow.php" method="POST" onsubmit="return validateWorkflowForm();">
                <?= csrf_field() ?>
                <input type="hidden" name="jo_id" value="<?= $id ?>">
                <input type="hidden" name="mode" value="<?= h($mode) ?>">

                <?php if ($mode === 'revision'): ?>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Revision Reason</label>
                        <textarea name="revision_reason" class="form-control" rows="3"
                                  placeholder="Why do the workflow assignments need to be changed?" required></textarea>
                    </div>
                <?php endif; ?>

                <div class="table-responsive">
                    <table class="table table-bordered align-middle">
                        <thead class="table-dark">
                        <tr><th width="100">Include</th><th>Production Step</th><th>Assign Employee</th></tr>
                        </thead>
                        <tbody>
                        <?php while ($dept = $departments->fetch_assoc()): ?>
                            <?php
                            $department_id = (int) $dept['id'];
                            $selected_user_id = $existing_steps[$department_id] ?? 0;
                            ?>
                            <tr>
                                <td class="text-center">
                                    <input type="checkbox" name="departments[]" value="<?= $department_id ?>"
                                           <?= $selected_user_id > 0 ? 'checked' : '' ?>>
                                </td>
                                <td><b><?= h($dept['department_name']) ?></b></td>
                                <td>
                                    <select name="assigned_user_<?= $department_id ?>" class="form-control">
                                        <option value="">Select Employee</option>
                                        <?php
                                        $users_stmt = $conn->prepare("
                                            SELECT id, employee_no, fullname
                                            FROM users
                                            WHERE department_id = ? AND status = 'Active'
                                            ORDER BY fullname
                                        ");
                                        $users_stmt->bind_param('i', $department_id);
                                        $users_stmt->execute();
                                        $users = $users_stmt->get_result();
                                        ?>
                                        <?php while ($user = $users->fetch_assoc()): ?>
                                            <option value="<?= (int) $user['id'] ?>"
                                                    <?= $selected_user_id === (int) $user['id'] ? 'selected' : '' ?>>
                                                <?= h(($user['employee_no'] ?: '-') . ' - ' . $user['fullname']) ?>
                                            </option>
                                        <?php endwhile; ?>
                                        <?php $users_stmt->close(); ?>
                                    </select>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

                <button type="submit" class="btn <?= $mode === 'revision' ? 'btn-warning' : 'btn-success' ?>">
                    <?= $mode === 'revision' ? 'Save Workflow Revision' : 'Assign Workflow' ?>
                </button>
            </form>
        </div>
    </div>
</div>
<script>
function validateWorkflowForm() {
    const checked = document.querySelectorAll('input[name="departments[]"]:checked');
    if (checked.length === 0) {
        alert('Please select at least one production step.');
        return false;
    }
    for (const checkbox of checked) {
        const assigned = document.querySelector('[name="assigned_user_' + checkbox.value + '"]');
        if (!assigned || assigned.value === '') {
            alert('Please assign an employee for every selected production step.');
            assigned?.focus();
            return false;
        }
    }
    <?php if ($mode === 'revision'): ?>
    if (document.querySelector('[name="revision_reason"]').value.trim().length < 5) {
        alert('Please provide a clear revision reason.');
        return false;
    }
    return confirm('Revise this workflow? The change will be recorded in the JO Audit Trail.');
    <?php endif; ?>
    return true;
}
</script>
</body>
</html>
