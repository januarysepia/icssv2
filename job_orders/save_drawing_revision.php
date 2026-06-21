<?php

include '../auth/auth_check.php';
require_role(['Technical', 'Engineer']);
include '../config/database.php';
include '../includes/create_notification.php';
include '../includes/activity_logger.php';
include '../includes/jo_audit.php';

require_post();
verify_csrf();

$jo_id = max(0, (int) ($_POST['jo_id'] ?? 0));
$revision_notes = trim($_POST['revision_notes'] ?? '');
$uploaded_by = (int) $_SESSION['user_id'];
$uploader_name = $_SESSION['fullname'] ?? 'User';

if ($jo_id <= 0 || mb_strlen($revision_notes) < 5) {
    exit('A valid Job Order and revision reason are required.');
}

$job_stmt = $conn->prepare("SELECT id, jo_no, project_name FROM job_orders WHERE id = ?");
$job_stmt->bind_param('i', $jo_id);
$job_stmt->execute();
$job = $job_stmt->get_result()->fetch_assoc();
if (!$job) {
    exit('Job Order not found.');
}

$file = $_FILES['drawing_file'] ?? null;
if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    exit('Drawing upload failed.');
}
if (($file['size'] ?? 0) <= 0 || $file['size'] > 10 * 1024 * 1024) {
    exit('Drawing must be no larger than 10 MB.');
}

$allowed_types = [
    'application/pdf' => 'pdf',
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
];
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
if (!isset($allowed_types[$mime])) {
    exit('Only valid PDF, JPG, and PNG drawings are allowed.');
}

$count_stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM job_order_attachments
    WHERE jo_id = ?
");
$count_stmt->bind_param('i', $jo_id);
$count_stmt->execute();
$current_count = (int) ($count_stmt->get_result()->fetch_assoc()['total'] ?? 0);
$version_no = $current_count > 0 ? 'Rev. ' . $current_count : 'Original';

$new_name = bin2hex(random_bytes(16)) . '.' . $allowed_types[$mime];
$upload_path = __DIR__ . '/../uploads/drawings/' . $new_name;
if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
    exit('Unable to store the revised drawing.');
}

$original_name = basename($file['name']);
$stmt = $conn->prepare("
    INSERT INTO job_order_attachments
        (jo_id, file_name, original_name, file_type, version_no, revision_notes, uploaded_by)
    VALUES (?, ?, ?, ?, ?, ?, ?)
");
$stmt->bind_param(
    'isssssi',
    $jo_id,
    $new_name,
    $original_name,
    $mime,
    $version_no,
    $revision_notes,
    $uploaded_by
);

if (!$stmt->execute()) {
    @unlink($upload_path);
    exit('Unable to save the drawing revision.');
}
$attachment_id = (int) $conn->insert_id;

/* The uploader has already seen the revision being submitted. */
$seen_stmt = $conn->prepare("
    INSERT IGNORE INTO drawing_revision_views (attachment_id, user_id)
    VALUES (?, ?)
");
$seen_stmt->bind_param('ii', $attachment_id, $uploaded_by);
$seen_stmt->execute();

logActivity(
    $conn,
    'Job Order',
    $uploader_name . ' uploaded ' . $version_no . ' drawing for ' . $job['jo_no'],
    $uploaded_by
);
logJOAudit(
    $conn,
    $jo_id,
    'Drawing Revision Uploaded',
    $uploaded_by,
    $version_no . ': ' . $revision_notes
);

$recipients = $conn->prepare("
    SELECT DISTINCT user_id
    FROM (
        SELECT created_by AS user_id FROM job_orders WHERE id = ?
        UNION
        SELECT assigned_user_id AS user_id FROM job_workflow_steps WHERE jo_id = ?
        UNION
        SELECT id AS user_id FROM users WHERE system_role IN ('Supervisor','Engineer') AND status = 'Active'
    ) recipients
    WHERE user_id IS NOT NULL AND user_id <> ?
");
$recipients->bind_param('iii', $jo_id, $jo_id, $uploaded_by);
$recipients->execute();
$recipient_rows = $recipients->get_result();
while ($recipient = $recipient_rows->fetch_assoc()) {
    createNotification(
        $conn,
        (int) $recipient['user_id'],
        'Revised Drawing Uploaded',
        $version_no . ' drawing is now available for ' . $job['jo_no'] . '.',
        '../job_orders/view_jo.php?id=' . $jo_id
    );
}

header('Location: view_jo.php?id=' . $jo_id . '&drawing_updated=1');
exit();
