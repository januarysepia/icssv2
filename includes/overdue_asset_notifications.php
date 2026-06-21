<?php

require_once __DIR__ . '/create_notification.php';

function createOverdueAssetNotifications(mysqli $conn): void
{
    $overdue = $conn->query("
        SELECT
            bt.id AS borrow_id,
            au.asset_code,
            i.item_name,
            COALESCE(u.fullname, bt.borrower_name, 'Unknown borrower') AS borrower_name,
            bt.due_date
        FROM borrow_transactions bt
        INNER JOIN asset_units au ON au.id = bt.asset_unit_id
        INNER JOIN inventory_items i ON i.id = bt.item_id
        LEFT JOIN users u ON u.id = bt.employee_id
        WHERE bt.status = 'Borrowed'
          AND bt.due_date < NOW()
    ");

    if (!$overdue || $overdue->num_rows === 0) {
        return;
    }

    $purchasing_users = $conn->query("
        SELECT id
        FROM users
        WHERE system_role = 'Purchasing'
          AND status = 'Active'
    ");

    if (!$purchasing_users || $purchasing_users->num_rows === 0) {
        return;
    }

    $recipient_ids = [];
    while ($user = $purchasing_users->fetch_assoc()) {
        $recipient_ids[] = (int) $user['id'];
    }

    while ($row = $overdue->fetch_assoc()) {
        $title = 'Overdue Asset Return';
        $message = sprintf(
            '%s (%s), borrowed by %s, was due on %s.',
            $row['item_name'],
            $row['asset_code'],
            $row['borrower_name'],
            date('M d, Y h:i A', strtotime($row['due_date']))
        );
        $link = '../inventory/return_item.php?borrow_id=' . (int) $row['borrow_id'];
        $key = 'overdue_asset_borrow:' . (int) $row['borrow_id'];

        foreach ($recipient_ids as $user_id) {
            createNotification($conn, $user_id, $title, $message, $link, $key);
        }
    }
}

