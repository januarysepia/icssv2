<?php

include '../auth/auth_check.php';
require_role(['Technical', 'Engineer']);
include '../config/database.php';

$jo_id = max(0, (int) ($_GET['id'] ?? 0));
$stmt = $conn->prepare("
    SELECT id, jo_no, project_name, workflow_status
    FROM job_orders
    WHERE id = ?
");
$stmt->bind_param('i', $jo_id);
$stmt->execute();
$job = $stmt->get_result()->fetch_assoc();

if (!$job) {
    exit('Job Order not found.');
}

$version_stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM job_order_attachments
    WHERE jo_id = ?
");
$version_stmt->bind_param('i', $jo_id);
$version_stmt->execute();
$attachment_count = (int) ($version_stmt->get_result()->fetch_assoc()['total'] ?? 0);
$next_version = $attachment_count > 0 ? 'Rev. ' . $attachment_count : 'Original';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Upload Drawing Revision</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body{background:#f4f6f9}.revision-page{max-width:850px;margin:0 auto;padding:14px}
        .revision-summary{display:grid;grid-template-columns:repeat(3,1fr);gap:8px}
        .summary-item{padding:9px 11px;border:1px solid #e5e7eb;border-radius:8px;background:#f8fafc}
        .summary-item small{display:block;color:#64748b;font-size:.66rem;text-transform:uppercase}
        @media(max-width:576px){.revision-summary{grid-template-columns:1fr}}
        html[data-theme="dark"] .summary-item{background:#252525;border-color:#444}
    </style>
</head>
<body>
<main class="revision-page">
    <section class="card shadow-sm border-0">
        <header class="card-header bg-dark text-white d-flex justify-content-between align-items-center gap-2">
            <h1 class="h5 mb-0">Upload Revised Drawing</h1>
            <a href="view_jo.php?id=<?= $jo_id ?>" class="btn btn-light btn-sm">Back to JO</a>
        </header>
        <div class="card-body">
            <div class="revision-summary mb-3">
                <div class="summary-item"><small>Job Order</small><strong><?= h($job['jo_no']) ?></strong></div>
                <div class="summary-item"><small>Project</small><strong><?= h($job['project_name']) ?></strong></div>
                <div class="summary-item"><small>New Version</small><strong><?= h($next_version) ?></strong></div>
            </div>

            <div class="alert alert-warning">
                The previous drawing will remain available in the history. The uploaded file will become the latest drawing.
            </div>

            <form action="save_drawing_revision.php" method="post" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="jo_id" value="<?= $jo_id ?>">

                <div class="mb-3">
                    <label class="form-label">Revised Drawing / PDF</label>
                    <input type="file" name="drawing_file" class="form-control"
                           accept=".pdf,.jpg,.jpeg,.png" required>
                    <div class="form-text">PDF, JPG, or PNG; maximum 10 MB.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Revision Reason / Changes</label>
                    <textarea name="revision_notes" class="form-control" rows="4" minlength="5"
                              placeholder="Example: Updated cable tray dimensions based on client comments..." required></textarea>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <a href="view_jo.php?id=<?= $jo_id ?>" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-warning">Save Revised Drawing</button>
                </div>
            </form>
        </div>
    </section>
</main>
</body>
</html>
