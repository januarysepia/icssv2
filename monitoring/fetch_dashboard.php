<?php

include '../auth/auth_check.php';
require_role(['Boss', 'Admin', 'Supervisor']);
include '../config/database.php';

$jobs_per_page = 8;
$jobs_page = max(1, (int) ($_GET['page'] ?? 1));

$summary = $conn->query("
    SELECT
        COUNT(DISTINCT jo.id) AS active_jo,
        SUM(jws.status = 'Pending') AS pending_steps,
        SUM(jws.status = 'Acknowledged') AS acknowledged_steps,
        SUM(jws.status = 'In Progress') AS in_progress_steps,
        SUM(jws.status = 'Completed') AS completed_steps,
        COUNT(DISTINCT CASE WHEN jo.due_date < CURDATE() THEN jo.id END) AS delayed_jo
    FROM job_orders jo
    LEFT JOIN job_workflow_steps jws ON jws.jo_id = jo.id
    WHERE jo.workflow_status <> 'Completed'
")->fetch_assoc();

$active_jobs_total = (int) ($summary['active_jo'] ?? 0);
$jobs_total_pages = max(1, (int) ceil($active_jobs_total / $jobs_per_page));
if ($jobs_page > $jobs_total_pages) {
    $jobs_page = $jobs_total_pages;
}
$jobs_offset = ($jobs_page - 1) * $jobs_per_page;

$dept_status = $conn->query("
    SELECT
        d.department_name,
        COUNT(jws.id) AS total_tasks,
        SUM(jws.status = 'Pending') AS pending_tasks,
        SUM(jws.status = 'Acknowledged') AS acknowledged_tasks,
        SUM(jws.status = 'In Progress') AS in_progress_tasks,
        SUM(jws.status = 'Completed') AS completed_tasks
    FROM departments d
    LEFT JOIN job_workflow_steps jws ON jws.department_id = d.id
    LEFT JOIN job_orders jo ON jo.id = jws.jo_id AND jo.workflow_status <> 'Completed'
    WHERE d.department_name IN ('Fabrication','Hotworks','Painting','Busbarring','Assembly','Controls')
      AND (jws.id IS NULL OR jo.id IS NOT NULL)
    GROUP BY d.id
    ORDER BY FIELD(d.department_name,'Fabrication','Hotworks','Painting','Busbarring','Assembly','Controls')
");

$jobs_result = $conn->query("
    SELECT
        jo.id,
        jo.jo_no,
        jo.project_name,
        jo.due_date,
        jo.workflow_status,
        COUNT(jws.id) AS total_steps,
        SUM(jws.status = 'Completed') AS completed_steps
    FROM job_orders jo
    LEFT JOIN job_workflow_steps jws ON jws.jo_id = jo.id
    WHERE jo.workflow_status <> 'Completed'
    GROUP BY jo.id
    ORDER BY
        CASE WHEN jo.due_date < CURDATE() THEN 0 ELSE 1 END,
        jo.due_date ASC,
        jo.id DESC
    LIMIT $jobs_per_page OFFSET $jobs_offset
");

$jobs = [];
$job_ids = [];
while ($job = $jobs_result->fetch_assoc()) {
    $job['steps'] = [];
    $jobs[(int) $job['id']] = $job;
    $job_ids[] = (int) $job['id'];
}

if ($job_ids) {
    $id_list = implode(',', $job_ids);
    $steps_result = $conn->query("
        SELECT jws.jo_id, jws.status, d.department_name
        FROM job_workflow_steps jws
        LEFT JOIN departments d ON d.id = jws.department_id
        WHERE jws.jo_id IN ($id_list)
          AND d.department_name IN ('Fabrication','Hotworks','Painting','Busbarring','Assembly','Controls')
        ORDER BY jws.jo_id, jws.step_order, jws.id
    ");
    while ($step = $steps_result->fetch_assoc()) {
        $jobs[(int) $step['jo_id']]['steps'][] = $step;
    }
}

function monitoringStatusBadge(string $status, ?string $due_date): string
{
    if ($due_date && $due_date < date('Y-m-d')) return '<span class="badge bg-danger">Delayed</span>';
    $classes = [
        'For Validation' => 'bg-warning text-dark',
        'In Production' => 'bg-primary',
        'For QA Inspection' => 'bg-info text-dark',
        'QA Passed' => 'bg-success',
        'Preparing Delivery' => 'bg-warning text-dark',
        'Dispatched' => 'bg-secondary',
        'Returned to Production' => 'bg-danger',
    ];
    return '<span class="badge ' . ($classes[$status] ?? 'bg-dark') . '">' . h($status) . '</span>';
}

function stepClass(string $status): string
{
    return match ($status) {
        'Completed' => 'step-completed',
        'In Progress' => 'step-progress',
        'Acknowledged' => 'step-ack',
        'Pending' => 'step-pending',
        default => 'step-other',
    };
}
?>
<section class="monitor-stats">
    <?php
    $stats = [
        ['Active JO', (int) ($summary['active_jo'] ?? 0), ''],
        ['Pending', (int) ($summary['pending_steps'] ?? 0), 'text-warning'],
        ['Acknowledged', (int) ($summary['acknowledged_steps'] ?? 0), 'text-info'],
        ['In Progress', (int) ($summary['in_progress_steps'] ?? 0), 'text-primary'],
        ['Completed Steps', (int) ($summary['completed_steps'] ?? 0), 'text-success'],
        ['Delayed JO', (int) ($summary['delayed_jo'] ?? 0), 'text-danger'],
    ];
    foreach ($stats as $stat):
    ?>
        <div class="monitor-stat">
            <div class="monitor-stat-label"><?= h($stat[0]) ?></div>
            <div class="monitor-stat-value <?= h($stat[2]) ?>"><?= number_format($stat[1]) ?></div>
        </div>
    <?php endforeach; ?>
</section>

<section class="monitor-card">
    <header class="monitor-card-header">
        <h2>Production Status by Department</h2>
        <span class="last-updated">Updated <?= date('h:i:s A') ?></span>
    </header>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-light">
            <tr><th>Department</th><th>Total</th><th>Pending</th><th>Acknowledged</th><th>In Progress</th><th>Completed</th></tr>
            </thead>
            <tbody>
            <?php while ($dept = $dept_status->fetch_assoc()): ?>
                <tr>
                    <td class="department-name"><?= h($dept['department_name']) ?></td>
                    <td><?= (int) $dept['total_tasks'] ?></td>
                    <td><span class="count-pill count-pending"><?= (int) $dept['pending_tasks'] ?></span></td>
                    <td><span class="count-pill count-ack"><?= (int) $dept['acknowledged_tasks'] ?></span></td>
                    <td><span class="count-pill count-progress"><?= (int) $dept['in_progress_tasks'] ?></span></td>
                    <td><span class="count-pill count-completed"><?= (int) $dept['completed_tasks'] ?></span></td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="monitor-card active-jobs-card">
    <header class="monitor-card-header">
        <h2>Active Job Order Workflow Progress</h2>
        <span class="last-updated">
            <?= $active_jobs_total ?> active JO<?= $active_jobs_total === 1 ? '' : 's' ?>
        </span>
    </header>
    <div class="table-responsive">
        <table class="table table-hover align-middle active-jobs-table">
            <thead class="table-light">
            <tr><th>JO No.</th><th>Project</th><th>Due Date</th><th>Status</th><th>Production Progress</th><th>Action</th></tr>
            </thead>
            <tbody>
            <?php if ($jobs): ?>
                <?php foreach ($jobs as $job): ?>
                    <?php
                    $total = (int) $job['total_steps'];
                    $completed = (int) $job['completed_steps'];
                    $percent = $total > 0 ? (int) round(($completed / $total) * 100) : 0;
                    ?>
                    <tr>
                        <td><strong><?= h($job['jo_no'] ?: 'No JO Number') ?></strong></td>
                        <td><?= h($job['project_name']) ?></td>
                        <td class="<?= $job['due_date'] && $job['due_date'] < date('Y-m-d') ? 'text-danger fw-bold' : '' ?>"><?= h($job['due_date'] ?: '-') ?></td>
                        <td><?= monitoringStatusBadge($job['workflow_status'], $job['due_date']) ?></td>
                        <td>
                            <?php if ($total > 0): ?>
                                <div class="workflow-progress">
                                    <div class="d-flex justify-content-between mb-1">
                                        <span class="workflow-progress-label"><?= $completed ?>/<?= $total ?> completed</span>
                                        <span class="workflow-progress-label"><?= $percent ?>%</span>
                                    </div>
                                    <div class="progress">
                                        <div class="progress-bar bg-success" style="width:<?= $percent ?>%"></div>
                                    </div>
                                    <div class="step-list">
                                        <?php foreach ($job['steps'] as $step): ?>
                                            <span class="step-chip <?= stepClass($step['status']) ?>">
                                                <?= h($step['department_name']) ?> · <?= h($step['status']) ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <span class="text-muted">No workflow assigned</span>
                            <?php endif; ?>
                        </td>
                        <td><a href="../job_orders/view_jo.php?id=<?= (int) $job['id'] ?>" class="btn btn-outline-primary btn-sm">View</a></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="6" class="text-center text-muted py-4">No active job orders.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($jobs_total_pages > 1): ?>
        <footer class="monitor-pagination">
            <span class="pagination-summary">
                Page <?= $jobs_page ?> of <?= $jobs_total_pages ?>
            </span>
            <nav aria-label="Active job orders pagination">
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?= $jobs_page <= 1 ? 'disabled' : '' ?>">
                        <button class="page-link monitor-page-link" type="button" data-page="<?= $jobs_page - 1 ?>">Previous</button>
                    </li>
                    <?php
                    $start_page = max(1, $jobs_page - 2);
                    $end_page = min($jobs_total_pages, $jobs_page + 2);
                    for ($page_no = $start_page; $page_no <= $end_page; $page_no++):
                    ?>
                        <li class="page-item <?= $page_no === $jobs_page ? 'active' : '' ?>">
                            <button class="page-link monitor-page-link" type="button" data-page="<?= $page_no ?>"><?= $page_no ?></button>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $jobs_page >= $jobs_total_pages ? 'disabled' : '' ?>">
                        <button class="page-link monitor-page-link" type="button" data-page="<?= $jobs_page + 1 ?>">Next</button>
                    </li>
                </ul>
            </nav>
        </footer>
    <?php endif; ?>
</section>
