<?php

include '../auth/auth_check.php';

require_role([
    'Boss',
    'Admin',
    'Supervisor',
    'Technical',
    'Engineer'
]);

include '../config/database.php';
include '../includes/drawing_revision_seen.php';

$id = intval($_GET['id']);

$job = $conn->query("
SELECT
job_orders.*,
users.fullname AS created_by_name,
users.employee_no AS creator_employee_no

FROM job_orders

LEFT JOIN users
ON users.id = job_orders.created_by

WHERE job_orders.id = '$id'
");

$row = $job->fetch_assoc();

if(!$row){
    die("Job Order not found.");
}

markDrawingRevisionsSeen($conn, $id, (int) $_SESSION['user_id']);

$attachment_stmt = $conn->prepare("
    SELECT
        joa.*,
        users.fullname AS uploaded_by_name
    FROM job_order_attachments joa
    LEFT JOIN users ON users.id = joa.uploaded_by
    WHERE joa.jo_id = ?
    ORDER BY joa.id DESC
");
$attachment_stmt->bind_param('i', $id);
$attachment_stmt->execute();
$attachments_result = $attachment_stmt->get_result();
$drawing_versions = $attachments_result->fetch_all(MYSQLI_ASSOC);
$file = $drawing_versions[0] ?? null;

$workflow = $conn->query("
SELECT
job_workflow_steps.*,
departments.department_name,
users.fullname,
users.employee_no

FROM job_workflow_steps

LEFT JOIN departments
ON job_workflow_steps.department_id = departments.id

LEFT JOIN users
ON job_workflow_steps.assigned_user_id = users.id

WHERE job_workflow_steps.jo_id = '$id'

AND departments.department_name IN (
    'Fabrication',
    'Hotworks',
    'Painting',
    'Busbarring',
    'Assembly',
    'Controls'
)

ORDER BY FIELD(
    departments.department_name,
    'Fabrication',
    'Hotworks',
    'Painting',
    'Busbarring',
    'Assembly',
    'Controls'
)
");

$workflow_summary = $conn->query("
    SELECT
        COUNT(*) AS total_steps,
        SUM(status = 'Pending') AS pending_steps,
        SUM(status IN ('Acknowledged', 'In Progress', 'Completed')) AS progressed_steps
    FROM job_workflow_steps
    WHERE jo_id = '$id'
")->fetch_assoc();

$has_workflow = (int) ($workflow_summary['total_steps'] ?? 0) > 0;
$workflow_can_be_revised = $has_workflow
    && $row['workflow_status'] === 'In Production'
    && (int) ($workflow_summary['progressed_steps'] ?? 0) === 0;

$audit_logs = $conn->query("
SELECT
jo_audit_logs.*,
users.fullname,
users.employee_no,
users.system_role

FROM jo_audit_logs

LEFT JOIN users
ON users.id = jo_audit_logs.user_id

WHERE jo_audit_logs.jo_id = '$id'

ORDER BY jo_audit_logs.id DESC
");

?>

<!DOCTYPE html>
<html>
<head>
    <title>View Job Order</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .jo-view-header{
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:12px;
        }
        .jo-view-title{
            min-width:0;
            overflow-wrap:anywhere;
        }
        .jo-view-actions{
            display:flex;
            flex:0 0 auto;
            gap:6px;
        }
        .drawing-actions{
            display:flex;
            flex-wrap:wrap;
            gap:6px;
        }
        .drawing-current{
            display:flex;align-items:flex-start;justify-content:space-between;gap:12px;
            padding:10px 12px;margin-bottom:10px;border:1px solid #bae6fd;
            border-radius:8px;background:#f0f9ff;
        }
        .drawing-current-label{font-size:.68rem;color:#0369a1;text-transform:uppercase;font-weight:750}
        .drawing-history-table{font-size:.75rem}
        .drawing-history-table th{white-space:nowrap}
        html[data-theme="dark"] .drawing-current{background:#173042;border-color:#155e75}
        html[data-theme="dark"] .drawing-current-label{color:#7dd3fc}
        @media(max-width:576px){
            .jo-view-header{
                align-items:stretch;
                flex-direction:column;
                gap:9px;
            }
            .jo-view-title{
                font-size:.95rem!important;
                line-height:1.35;
            }
            .jo-view-actions{
                display:grid;
                grid-template-columns:1fr 1fr;
                width:100%;
            }
            .jo-view-actions .btn{
                width:100%;
                white-space:nowrap;
            }
            .drawing-actions{
                display:grid;
                grid-template-columns:1fr;
            }
            .drawing-actions .btn{
                width:100%;
            }
            .drawing-current{align-items:stretch;flex-direction:column}
            .drawing-current .btn{width:100%}
            .drawing-history-table{min-width:760px}
        }
    </style>
</head>

<body class="bg-light">

<div class="container-fluid mt-4">

    <div class="card shadow border-0">

        <div class="card-header bg-dark text-white">

            <div class="jo-view-header">

                <h4 class="mb-0 jo-view-title">
                    <?php echo $row['jo_no']; ?> - <?php echo $row['project_name']; ?>
                </h4>

                <div class="jo-view-actions">
                    <a href="../dashboard/index.php" class="btn btn-light btn-sm">
                        Dashboard
                    </a>

                    <a href="jo_list.php" class="btn btn-secondary btn-sm">
                        Back to List
                    </a>
                </div>

            </div>

        </div>

        <div class="card-body">

            <div class="row mb-3">

                <div class="col-md-4">
                    <p><b>Client:</b> <?php echo $row['client_name']; ?></p>
                </div>

                <div class="col-md-4">
                    <p><b>Project:</b> <?php echo $row['project_name']; ?></p>
                </div>

                <div class="col-md-4">
                    <p><b>Engineer:</b> <?php echo $row['engineer_name']; ?></p>
                </div>

                <div class="col-md-4">
                    <p><b>Sales:</b> <?php echo $row['sales_name']; ?></p>
                </div>

                <div class="col-md-4">
                    <p><b>Release Date:</b> <?php echo $row['release_date']; ?></p>
                </div>

                <div class="col-md-4">
                    <p><b>Due Date:</b> <?php echo $row['due_date']; ?></p>
                </div>

                <div class="col-md-4">
                    <p>
                        <b>Created By:</b>
                        <?php
                        if(!empty($row['created_by_name'])){
                            echo $row['creator_employee_no'] . ' - ' . $row['created_by_name'];
                        }else{
                            echo 'System / Unknown';
                        }
                        ?>
                    </p>
                </div>

                <div class="col-md-4">
                    <p><b>Date Created:</b> <?php echo $row['created_at']; ?></p>
                </div>

                <div class="col-md-4">
                    <p>
                        <b>Status:</b>
                        <span class="badge bg-dark">
                            <?php echo $row['workflow_status']; ?>
                        </span>
                    </p>
                </div>

            </div>

            <hr>

            <div class="mb-4">

                <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                    <h5 class="mb-0">Drawing Attachments</h5>
                    <?php if (in_array($_SESSION['system_role'], ['Technical', 'Engineer'], true)): ?>
                        <a href="upload_drawing_revision.php?id=<?= (int) $row['id'] ?>"
                           class="btn btn-warning btn-sm">+ Upload Revision</a>
                    <?php endif; ?>
                </div>

                <?php if (isset($_GET['drawing_updated'])): ?>
                    <div class="alert alert-success">Revised drawing uploaded successfully.</div>
                <?php endif; ?>

                <?php if($file){ ?>

                    <div class="drawing-current">
                        <div>
                            <div class="drawing-current-label">Latest Drawing</div>
                            <div class="fw-bold">
                                <?= h($file['version_no'] ?: 'Original') ?>
                                <?php if (count($drawing_versions) > 1): ?>
                                    <span class="badge bg-warning text-dark ms-1">Revised</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary ms-1">Original</span>
                                <?php endif; ?>
                            </div>
                            <div class="small text-muted">
                                <?= h($file['original_name'] ?: $file['file_name']) ?>
                                · <?= h($file['created_at']) ?>
                            </div>
                            <?php if (!empty($file['revision_notes'])): ?>
                                <div class="small mt-1"><strong>Changes:</strong> <?= h($file['revision_notes']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="drawing-actions">
                            <a href="../uploads/drawings/<?= rawurlencode(basename($file['file_name'])) ?>"
                               target="_blank" class="btn btn-primary">Open Latest Drawing</a>
                            <a href="generate_cover_sheet.php?id=<?= (int) $row['id'] ?>"
                               target="_blank" class="btn btn-dark">Generate Cover Sheet</a>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-hover align-middle drawing-history-table">
                            <thead class="table-dark">
                            <tr>
                                <th>Version</th><th>File</th><th>Revision Details</th>
                                <th>Uploaded By</th><th>Date</th><th>Action</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($drawing_versions as $index => $drawing): ?>
                                <tr>
                                    <td>
                                        <strong><?= h($drawing['version_no'] ?: ($index === count($drawing_versions) - 1 ? 'Original' : 'Revision')) ?></strong>
                                        <?= $index === 0 ? '<span class="badge bg-success ms-1">Latest</span>' : '' ?>
                                    </td>
                                    <td><?= h($drawing['original_name'] ?: $drawing['file_name']) ?></td>
                                    <td><?= h($drawing['revision_notes'] ?: ($index === count($drawing_versions) - 1 ? 'Initial drawing submission' : '-')) ?></td>
                                    <td><?= h($drawing['uploaded_by_name'] ?: 'System / Legacy Upload') ?></td>
                                    <td><?= h($drawing['created_at']) ?></td>
                                    <td>
                                        <a href="../uploads/drawings/<?= rawurlencode(basename($drawing['file_name'])) ?>"
                                           target="_blank" class="btn btn-outline-primary btn-sm">Open</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php }else{ ?>

                    <div class="alert alert-warning">
                        No drawing attachment found.
                    </div>

                <?php } ?>

                <?php if(
                    $_SESSION['system_role'] == 'Supervisor' ||
                    $_SESSION['system_role'] == 'Admin' ||
                    $_SESSION['system_role'] == 'Boss'
                ){ ?>

                    <?php if (!$has_workflow && $row['workflow_status'] === 'For Validation'): ?>
                        <a href="../supervisor/validate_jo.php?id=<?php echo $row['id']; ?>"
                           class="btn btn-success">
                            Assign Workflow
                        </a>
                    <?php elseif ($workflow_can_be_revised): ?>
                        <a href="../supervisor/validate_jo.php?id=<?php echo $row['id']; ?>&mode=revision"
                           class="btn btn-warning">
                            Revise Workflow
                        </a>
                    <?php elseif ($has_workflow): ?>
                        <span class="badge bg-secondary p-2">
                            Workflow Locked
                        </span>
                    <?php endif; ?>

                    <a href="generate_qr.php?id=<?php echo $row['id']; ?>"
                    class="btn btn-dark">

                        Generate QR

                    </a>
                <?php } ?>

            </div>

            <hr>

            <h5>Workflow Status</h5>

            <div class="table-responsive">

                <table class="table table-bordered align-middle">

                    <thead class="table-dark">
                        <tr>
                            <th>Step</th>
                            <th>Department</th>
                            <th>Assigned Employee</th>
                            <th>Status</th>
                            <th>Progress</th>
                            <th>Acknowledged</th>
                            <th>Started</th>
                            <th>Completed</th>
                            <th>Completion Details</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php while($step = $workflow->fetch_assoc()){ ?>

                        <tr>

                            <td><?php echo $step['step_order']; ?></td>

                            <td><?php echo $step['department_name']; ?></td>

                            <td>
                                <?php
                                if($step['fullname']){
                                    echo $step['employee_no'] . " - " . $step['fullname'];
                                }else{
                                    echo "Not Assigned";
                                }
                                ?>
                            </td>

                            <td>
                                <?php if($step['status'] == 'Pending'){ ?>

                                    <span class="badge bg-warning text-dark">Pending</span>

                                <?php }elseif($step['status'] == 'Acknowledged'){ ?>

                                    <span class="badge bg-info text-dark">Acknowledged</span>

                                <?php }elseif($step['status'] == 'In Progress'){ ?>

                                    <span class="badge bg-primary">In Progress</span>

                                <?php }elseif($step['status'] == 'Completed'){ ?>

                                    <span class="badge bg-success">Completed</span>

                                <?php }else{ ?>

                                    <span class="badge bg-secondary">
                                        <?php echo $step['status']; ?>
                                    </span>

                                <?php } ?>
                            </td>
                            <td style="min-width:130px">
                                <div class="d-flex justify-content-between small mb-1">
                                    <span><?= (int)($step['progress_percent'] ?? 0) ?>%</span>
                                </div>
                                <div class="progress" style="height:7px">
                                    <div class="progress-bar <?= (int)($step['progress_percent'] ?? 0) >= 100 ? 'bg-success' : 'bg-primary' ?>"
                                         style="width:<?= (int)($step['progress_percent'] ?? 0) ?>%"></div>
                                </div>
                            </td>

                            <td><?php echo $step['acknowledged_at']; ?></td>

                            <td><?php echo $step['started_at']; ?></td>

                            <td><?php echo $step['completed_at']; ?></td>
                            <td>
                                <?php if (!empty($step['completion_remarks'])): ?>
                                    <div class="small"><?= nl2br(h($step['completion_remarks'])) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($step['completion_proof'])): ?>
                                    <a href="../uploads/task_proofs/<?= rawurlencode(basename($step['completion_proof'])) ?>"
                                       target="_blank" class="btn btn-outline-success btn-sm mt-1">Proof Photo</a>
                                <?php endif; ?>
                                <?php if (empty($step['completion_remarks']) && empty($step['completion_proof'])): ?>
                                    -
                                <?php endif; ?>
                            </td>

                        </tr>

                    <?php } ?>

                    </tbody>

                </table>

            </div>

            <hr>

            <h5>Job Order Audit Trail</h5>

            <div class="table-responsive">

                <table class="table table-bordered align-middle">

                    <thead class="table-dark">
                        <tr>
                            <th>Date/Time</th>
                            <th>Action</th>
                            <th>Remarks</th>
                            <th>User</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if($audit_logs && $audit_logs->num_rows > 0){ ?>

                        <?php while($audit = $audit_logs->fetch_assoc()){ ?>

                            <tr>
                                <td><?php echo $audit['created_at']; ?></td>

                                <td>
                                    <span class="badge bg-primary">
                                        <?php echo $audit['action']; ?>
                                    </span>
                                </td>

                                <td><?php echo nl2br($audit['remarks']); ?></td>

                                <td>
                                    <?php
                                    if(!empty($audit['fullname'])){
                                        echo $audit['employee_no'] . ' - ' . $audit['fullname'] . ' (' . $audit['system_role'] . ')';
                                    }else{
                                        echo 'System / Unknown';
                                    }
                                    ?>
                                </td>
                            </tr>

                        <?php } ?>

                    <?php }else{ ?>

                        <tr>
                            <td colspan="4" class="text-center text-muted">
                                No audit logs yet.
                            </td>
                        </tr>

                    <?php } ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>

</body>
</html>
