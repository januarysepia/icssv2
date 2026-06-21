<?php

include '../auth/auth_check.php';
require_role(['Technical', 'Supervisor', 'Engineer', 'Production']);
include '../config/database.php';

$role = $_SESSION['system_role'] ?? '';
$user_id = (int) $_SESSION['user_id'];
$show = ($_GET['show'] ?? 'unread') === 'all' ? 'all' : 'unread';
$page = max(1, (int) ($_GET['page'] ?? 1));
$per_page = 10;

$scope = '';
if ($role === 'Production') {
    $scope = "
        AND EXISTS (
            SELECT 1
            FROM job_workflow_steps assigned_step
            WHERE assigned_step.jo_id = jo.id
              AND assigned_step.assigned_user_id = ?
        )
    ";
}

$seen_filter = $show === 'unread'
    ? "AND drv.id IS NULL"
    : "";

$count_sql = "
    SELECT COUNT(*) AS total
    FROM job_order_attachments joa
    INNER JOIN job_orders jo ON jo.id = joa.jo_id
    LEFT JOIN drawing_revision_views drv
        ON drv.attachment_id = joa.id
       AND drv.user_id = ?
    WHERE COALESCE(joa.version_no, 'Original') <> 'Original'
      AND jo.workflow_status <> 'Completed'
      $seen_filter
      $scope
";
$count_stmt = $conn->prepare($count_sql);
if ($role === 'Production') {
    $count_stmt->bind_param('ii', $user_id, $user_id);
} else {
    $count_stmt->bind_param('i', $user_id);
}
$count_stmt->execute();
$total = (int) ($count_stmt->get_result()->fetch_assoc()['total'] ?? 0);
$total_pages = max(1, (int) ceil($total / $per_page));
$page = min($page, $total_pages);
$offset = ($page - 1) * $per_page;

$sql = "
    SELECT
        joa.id AS attachment_id,
        joa.version_no,
        joa.revision_notes,
        joa.original_name,
        joa.created_at,
        jo.id AS jo_id,
        jo.jo_no,
        jo.project_name,
        jo.workflow_status,
        uploader.fullname AS uploaded_by_name,
        drv.viewed_at,
        " . ($role === 'Production' ? "(
            SELECT jws.id
            FROM job_workflow_steps jws
            WHERE jws.jo_id = jo.id
              AND jws.assigned_user_id = ?
            ORDER BY FIELD(jws.status,'In Progress','Acknowledged','Pending','Completed'), jws.id
            LIMIT 1
        )" : "jo.id") . " AS open_id
    FROM job_order_attachments joa
    INNER JOIN job_orders jo ON jo.id = joa.jo_id
    LEFT JOIN users uploader ON uploader.id = joa.uploaded_by
    LEFT JOIN drawing_revision_views drv
        ON drv.attachment_id = joa.id
       AND drv.user_id = ?
    WHERE COALESCE(joa.version_no, 'Original') <> 'Original'
      AND jo.workflow_status <> 'Completed'
      $seen_filter
      $scope
    ORDER BY joa.id DESC
    LIMIT $per_page OFFSET $offset
";
$stmt = $conn->prepare($sql);
if ($role === 'Production') {
    $stmt->bind_param('iii', $user_id, $user_id, $user_id);
} else {
    $stmt->bind_param('i', $user_id);
}
$stmt->execute();
$revisions = $stmt->get_result();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Drawing Revisions</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body{background:#f4f6f9}
        .revision-list-page{max-width:1400px;margin:0 auto;padding:14px}
        .revision-list-header{display:flex;justify-content:space-between;align-items:center;gap:10px}
        .revision-header-actions{display:flex;flex-wrap:wrap;gap:6px}
        .revision-table{font-size:.77rem}
        .revision-table th{white-space:nowrap}
        .revision-note{max-width:420px}
        .unread-row td{background:#fffbeb}
        .revision-pagination{display:flex;justify-content:space-between;align-items:center;gap:10px;padding-top:8px}
        @media(max-width:576px){
            .revision-list-header{align-items:stretch;flex-direction:column}
            .revision-header-actions{display:grid;grid-template-columns:1fr 1fr}
            .revision-table{min-width:950px}
            .revision-pagination{align-items:flex-start;flex-direction:column}
        }
        html[data-theme="dark"] .unread-row td{background:#34270f!important}
    </style>
</head>
<body>
<main class="revision-list-page">
    <section class="card shadow-sm border-0">
        <header class="card-header bg-dark text-white revision-list-header">
            <div>
                <h1 class="h5 mb-1">Drawing Revisions</h1>
                <small class="text-light">Review revised drawings before continuing work.</small>
            </div>
            <div class="revision-header-actions">
                <a href="../dashboard/index.php" class="btn btn-light btn-sm">Dashboard</a>
                <a href="?show=unread" class="btn <?= $show === 'unread' ? 'btn-warning' : 'btn-outline-light' ?> btn-sm">Unread</a>
                <a href="?show=all" class="btn <?= $show === 'all' ? 'btn-info' : 'btn-outline-light' ?> btn-sm">All Revisions</a>
            </div>
        </header>
        <div class="card-body">
            <div class="alert alert-warning py-2">
                Opening a Job Order or Production Task marks its revisions as seen for your account only.
            </div>

            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle revision-table">
                    <thead class="table-dark">
                    <tr>
                        <th>JO No.</th><th>Project</th><th>Version</th><th>Revision Details</th>
                        <th>Uploaded By</th><th>Date</th><th>Seen</th><th>Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if ($revisions->num_rows > 0): ?>
                        <?php while ($revision = $revisions->fetch_assoc()): ?>
                            <tr class="<?= empty($revision['viewed_at']) ? 'unread-row' : '' ?>">
                                <td><strong><?= h($revision['jo_no'] ?: 'No JO Number') ?></strong></td>
                                <td><?= h($revision['project_name']) ?></td>
                                <td><span class="badge bg-warning text-dark"><?= h($revision['version_no']) ?></span></td>
                                <td class="revision-note"><?= h($revision['revision_notes'] ?: 'Revised drawing uploaded') ?></td>
                                <td><?= h($revision['uploaded_by_name'] ?: 'System') ?></td>
                                <td><?= h($revision['created_at']) ?></td>
                                <td>
                                    <?php if ($revision['viewed_at']): ?>
                                        <span class="badge bg-success">Seen</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Unread</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $open_url = $role === 'Production'
                                        ? '../workflow/task_detail.php?id=' . (int) $revision['open_id']
                                        : 'view_jo.php?id=' . (int) $revision['jo_id'];
                                    ?>
                                    <a href="<?= h($open_url) ?>" class="btn btn-primary btn-sm">Open</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">
                            <?= $show === 'unread' ? 'No unread drawing revisions.' : 'No drawing revisions found.' ?>
                        </td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_pages > 1): ?>
                <div class="revision-pagination">
                    <small class="text-muted">Page <?= $page ?> of <?= $total_pages ?> · <?= $total ?> revision<?= $total === 1 ? '' : 's' ?></small>
                    <nav>
                        <ul class="pagination pagination-sm mb-0">
                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="?show=<?= h($show) ?>&page=<?= max(1, $page - 1) ?>">Previous</a>
                            </li>
                            <?php for ($number = 1; $number <= $total_pages; $number++): ?>
                                <li class="page-item <?= $number === $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?show=<?= h($show) ?>&page=<?= $number ?>"><?= $number ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                                <a class="page-link" href="?show=<?= h($show) ?>&page=<?= min($total_pages, $page + 1) ?>">Next</a>
                            </li>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>
</body>
</html>
