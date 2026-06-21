<?php

require_once __DIR__ . '/create_notification.php';

function processPurchaseDecision(
    mysqli $conn,
    int $purchase_id,
    int $actor_id,
    string $decision,
    string $reason = '',
    bool $manage_transaction = true
): array {
    if (!in_array($decision, ['approve', 'decline'], true)) {
        throw new RuntimeException('Invalid purchase decision.');
    }

    if ($manage_transaction) {
        $conn->begin_transaction();
    }

    try {
        $stmt = $conn->prepare("
            SELECT *
            FROM purchase_requests
            WHERE id = ?
            FOR UPDATE
        ");
        $stmt->bind_param('i', $purchase_id);
        $stmt->execute();
        $purchase = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$purchase) {
            throw new RuntimeException('Purchase request not found.');
        }

        if ($purchase['status'] !== 'For Boss Approval') {
            throw new RuntimeException('This purchase request is no longer awaiting approval.');
        }

        $material_request_id = (int) ($purchase['material_request_id'] ?? 0);

        if ($decision === 'approve') {
            $update = $conn->prepare("
                UPDATE purchase_requests
                SET status = 'Boss Approved', approved_by = ?, approved_at = NOW()
                WHERE id = ?
            ");
            $update->bind_param('ii', $actor_id, $purchase_id);
            $update->execute();
            $update->close();

            $conn->query("
                UPDATE material_request_items mri
                INNER JOIN purchase_request_items pri
                    ON pri.material_request_item_id = mri.id
                SET mri.item_status = 'Waiting Delivery'
                WHERE pri.purchase_request_id = '$purchase_id'
            ");

            if ($material_request_id > 0) {
                $summary = $conn->query("
                    SELECT
                        COUNT(*) AS total_items,
                        SUM(item_status IN ('Waiting Delivery','Received','Issued')) AS completed_items
                    FROM material_request_items
                    WHERE request_id = '$material_request_id'
                ")->fetch_assoc();

                if (
                    (int) $summary['total_items'] > 0
                    && (int) $summary['total_items'] === (int) $summary['completed_items']
                ) {
                    $conn->query("
                        UPDATE material_requests
                        SET status = 'Waiting Delivery'
                        WHERE id = '$material_request_id'
                    ");
                }
            }

            $action = 'Boss Approved';
            $log_remarks = $reason !== '' ? $reason : 'Purchase request approved by Boss';
            $notification_title = 'Purchase Request Approved';
            $notification_message = 'Purchase request ' . $purchase['purchase_no'] . ' has been approved by Boss.';
        } else {
            $update = $conn->prepare("
                UPDATE purchase_requests
                SET status = 'Boss Rejected'
                WHERE id = ?
            ");
            $update->bind_param('i', $purchase_id);
            $update->execute();
            $update->close();

            $conn->query("
                UPDATE material_request_items mri
                INNER JOIN purchase_request_items pri
                    ON pri.material_request_item_id = mri.id
                SET mri.item_status = 'To Purchase'
                WHERE pri.purchase_request_id = '$purchase_id'
            ");

            if ($material_request_id > 0) {
                $conn->query("
                    UPDATE material_requests
                    SET status = 'Pending Purchase'
                    WHERE id = '$material_request_id'
                ");
            }

            $action = 'Boss Rejected';
            $log_remarks = $reason !== ''
                ? 'Purchase request rejected by Boss. Reason: ' . $reason
                : 'Purchase request rejected by Boss. Items returned to To Purchase.';
            $notification_title = 'Purchase Request Rejected';
            $notification_message = 'Purchase request ' . $purchase['purchase_no'] . ' has been rejected by Boss.';
        }

        $log = $conn->prepare("
            INSERT INTO purchase_approval_logs
            (purchase_request_id, user_id, action, remarks)
            VALUES (?, ?, ?, ?)
        ");
        $log->bind_param('iiss', $purchase_id, $actor_id, $action, $log_remarks);
        $log->execute();
        $log->close();

        createNotification(
            $conn,
            (int) $purchase['requested_by'],
            $notification_title,
            $notification_message,
            '../purchase_requests/view_purchase.php?id=' . $purchase_id
        );

        if ($manage_transaction) {
            $conn->commit();
        }

        return [
            'purchase_no' => $purchase['purchase_no'],
            'status' => $decision === 'approve' ? 'Boss Approved' : 'Boss Rejected',
        ];
    } catch (Throwable $exception) {
        if ($manage_transaction) {
            $conn->rollback();
        }
        throw $exception;
    }
}

