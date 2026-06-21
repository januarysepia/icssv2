<?php

function markDrawingRevisionsSeen(mysqli $conn, int $jo_id, int $user_id): void
{
    if ($jo_id <= 0 || $user_id <= 0) {
        return;
    }

    $stmt = $conn->prepare("
        INSERT IGNORE INTO drawing_revision_views (attachment_id, user_id)
        SELECT joa.id, ?
        FROM job_order_attachments joa
        WHERE joa.jo_id = ?
          AND COALESCE(joa.version_no, 'Original') <> 'Original'
    ");
    $stmt->bind_param('ii', $user_id, $jo_id);
    $stmt->execute();
    $stmt->close();
}
