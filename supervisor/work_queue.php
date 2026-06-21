<?php

include '../auth/auth_check.php';
require_role(['Boss', 'Admin', 'Supervisor']);
include '../config/database.php';

$setup_jobs = $conn->query("
    SELECT jo.*
    FROM job_orders jo
    WHERE jo.workflow_status = 'For Validation'
      AND COALESCE(jo.overall_status, '') <> 'Completed'
    ORDER BY
        CASE WHEN jo.due_date < CURDATE() THEN 0 ELSE 1 END,
        jo.due_date ASC,
        jo.id DESC
");

$active_jobs = $conn->query("
    SELECT
        jo.*,
        COUNT(jws.id) AS total_steps,
        SUM(jws.status = 'Completed') AS completed_steps,
        SUM(jws.status = 'In Progress') AS in_progress_steps,
        SUM(jws.status = 'Acknowledged') AS acknowledged_steps,
        SUM(jws.status = 'Pending') AS pending_steps
    FROM job_orders jo
    INNER JOIN job_workflow_steps jws ON jws.jo_id = jo.id
    WHERE jo.workflow_status <> 'Completed'
      AND COALESCE(jo.overall_status, '') <> 'Completed'
    GROUP BY jo.id
    HAVING SUM(jws.status <> 'Completed') > 0
    ORDER BY
        CASE WHEN jo.due_date < CURDATE() THEN 0 ELSE 1 END,
        jo.due_date ASC,
        jo.id DESC
");

$rework_jobs = $conn->query("
    SELECT
        qt.id AS qaqc_task_id,
        qt.jo_id,
        qt.failure_reason,
        qt.rework_status,
        qt.created_at AS failed_at,
        jo.jo_no,
        jo.client_name,
        jo.project_name,
        jo.due_date
    FROM qaqc_tasks qt
    INNER JOIN job_orders jo ON jo.id = qt.jo_id
    LEFT JOIN rework_tasks rt ON rt.qaqc_task_id = qt.id
    WHERE qt.status = 'Failed'
      AND jo.workflow_status <> 'Completed'
      AND rt.id IS NULL
    ORDER BY qt.created_at ASC
");

$setup_count = $setup_jobs ? $setup_jobs->num_rows : 0;
$active_count = $active_jobs ? $active_jobs->num_rows : 0;
$rework_count = $rework_jobs ? $rework_jobs->num_rows : 0;
?>
<!DOCTYPE html>
<html>
<head>
    <title>Supervisor Work Queue</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background:#f4f6f9; }
        .work-queue-page { max-width:1600px; margin:0 auto; }
        .page-header h2 { font-size:1.45rem; font-weight:700; }
        .page-header .text-muted { font-size:.85rem; }
        .page-header .btn { padding:.4rem .7rem; font-size:.8rem; }
        .queue-card {
            border:0;
            border-radius:10px;
            box-shadow:0 2px 8px rgba(0,0,0,.07);
        }
        .queue-card .card-header { padding:.65rem .85rem; }
        .queue-card .table { font-size:.82rem; }
        .queue-card .table th {
            padding:.55rem .65rem;
            font-size:.75rem;
            text-transform:uppercase;
            letter-spacing:.025em;
            white-space:nowrap;
        }
        .queue-card .table td { padding:.55rem .65rem; }
        .queue-card .btn-sm { padding:.25rem .48rem; font-size:.74rem; }
        .queue-card .badge { font-size:.7rem; }
        .queue-card .small { font-size:.73rem; }
        .stat-card {
            border:1px solid #e5e7eb;
            border-radius:9px;
            box-shadow:0 2px 7px rgba(0,0,0,.05);
        }
        .stat-card .card-body { padding:.7rem .85rem; }
        .stat-card .text-muted {
            font-size:.72rem;
            font-weight:700;
            text-transform:uppercase;
            letter-spacing:.035em;
        }
        .stat-number { font-size:1.45rem; line-height:1.1; font-weight:700; margin-top:.2rem; }
        .progress { min-width:120px; height:7px; }
        .section-title { font-size:.95rem; font-weight:700; margin:0; }
        @media(max-width:768px) {
            .page-header { align-items:flex-start !important; flex-direction:column; gap:10px; }
            .page-header .d-flex { width:100%; }
            .page-header .btn { flex:1 1 auto; }
            .queue-card .table { min-width:760px; }
        }
    </style>
</head>
<body>
<?php include '../dashboard/sidebar.php'; ?>
<div class="content-wrapper">
<?php include '../dashboard/header.php'; ?>

<div class="container-fluid work-queue-page py-3 px-3 px-lg-4">
    <div class="page-header d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="mb-1">Supervisor Work Queue</h2>
            <div class="text-muted">Only active items requiring supervision are shown here.</div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="../monitoring/dashboard.php" class="btn btn-primary">Production Monitoring</a>
            <a href="../job_orders/jo_list.php" class="btn btn-dark">Job Orders</a>
        </div>
    </div>

    <div class="row g-2 mb-3">
        <div class="col-md-4">
            <div class="card stat-card h-100"><div class="card-body">
                <div class="text-muted">Needs Workflow Setup</div>
                <div class="stat-number text-warning"><?= $setup_count ?></div>
            </div></div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card h-100"><div class="card-body">
                <div class="text-muted">Active Production</div>
                <div class="stat-number text-primary"><?= $active_count ?></div>
            </div></div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card h-100"><div class="card-body">
                <div class="text-muted">Needs Rework Action</div>
                <div class="stat-number text-danger"><?= $rework_count ?></div>
            </div></div>
        </div>
    </div>

    <div class="card queue-card mb-3">
        <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
            <h4 class="section-title">Needs Workflow Setup</h4>
            <span class="badge bg-dark"><?= $setup_count ?></span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                    <tr><th>JO No.</th><th>Client / Project</th><th>Due Date</th><th>Status</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                    <?php if ($setup_count > 0): ?>
                        <?php while ($job = $setup_jobs->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?= h($job['jo_no'] ?: 'No JO Number') ?></strong></td>
                                <td><?= h($job['client_name']) ?><div class="small text-muted"><?= h($job['project_name']) ?></div></td>
                                <td class="<?= $job['due_date'] < date('Y-m-d') ? 'text-danger fw-bold' : '' ?>"><?= h($job['due_date'] ?: '-') ?></td>
                                <td><span class="badge bg-warning text-dark">For Validation</span></td>
                                <td>
                                    <a href="validate_jo.php?id=<?= (int) $job['id'] ?>" class="btn btn-success btn-sm">Set Up Workflow</a>
                                    <a href="../job_orders/view_jo.php?id=<?= (int) $job['id'] ?>" class="btn btn-outline-primary btn-sm">View</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">No job orders waiting for workflow setup.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card queue-card mb-3">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h4 class="section-title">Active Production</h4>
            <span class="badge bg-light text-primary"><?= $active_count ?></span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                    <tr><th>JO No.</th><th>Client / Project</th><th>Workflow Status</th><th>Progress</th><th>Due Date</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                    <?php if ($active_count > 0): ?>
                        <?php while ($job = $active_jobs->fetch_assoc()): ?>
                            <?php
                            $total_steps = max(1, (int) $job['total_steps']);
                            $completed_steps = (int) $job['completed_steps'];
                            $percent = (int) round(($completed_steps / $total_steps) * 100);
                            ?>
                            <tr>
                                <td><strong><?= h($job['jo_no'] ?: 'No JO Number') ?></strong></td>
                                <td><?= h($job['client_name']) ?><div class="small text-muted"><?= h($job['project_name']) ?></div></td>
                                <td><span class="badge bg-primary"><?= h($job['workflow_status']) ?></span></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="progress flex-grow-1">
                                            <div class="progress-bar" style="width:<?= $percent ?>%"></div>
                                        </div>
                                        <small><?= $completed_steps ?>/<?= $total_steps ?></small>
                                    </div>
                                    <div class="small text-muted mt-1">
                                        <?= (int) $job['in_progress_steps'] ?> in progress ·
                                        <?= (int) $job['acknowledged_steps'] ?> acknowledged ·
                                        <?= (int) $job['pending_steps'] ?> pending
                                    </div>
                                </td>
                                <td class="<?= $job['due_date'] < date('Y-m-d') ? 'text-danger fw-bold' : '' ?>"><?= h($job['due_date'] ?: '-') ?></td>
                                <td><a href="../job_orders/view_jo.php?id=<?= (int) $job['id'] ?>" class="btn btn-primary btn-sm">View Workflow</a></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">No active production workflows.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card queue-card">
        <div class="card-header bg-danger text-white d-flex justify-content-between align-items-center">
            <h4 class="section-title">Needs Rework Action</h4>
            <span class="badge bg-light text-danger"><?= $rework_count ?></span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                    <tr><th>JO No.</th><th>Client / Project</th><th>QA Failure</th><th>Failed At</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                    <?php if ($rework_count > 0): ?>
                        <?php while ($job = $rework_jobs->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?= h($job['jo_no'] ?: 'No JO Number') ?></strong></td>
                                <td><?= h($job['client_name']) ?><div class="small text-muted"><?= h($job['project_name']) ?></div></td>
                                <td class="text-danger"><?= nl2br(h($job['failure_reason'] ?: 'No failure reason provided.')) ?></td>
                                <td><?= h($job['failed_at']) ?></td>
                                <td>
                                    <a href="rework_review.php?jo_id=<?= (int) $job['jo_id'] ?>" class="btn btn-danger btn-sm">Assign Rework</a>
                                    <a href="../job_orders/view_jo.php?id=<?= (int) $job['jo_id'] ?>" class="btn btn-outline-primary btn-sm">View</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">No QA failures waiting for rework assignment.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</div>
</body>
</html>
