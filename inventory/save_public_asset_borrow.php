<?php

date_default_timezone_set('Asia/Manila');

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}

require_once '../includes/security.php';
include '../config/database.php';

require_post();
verify_csrf();

$unit_id = intval($_POST['unit_id'] ?? 0);
$borrower_name = trim($_POST['borrower_name'] ?? '');
$borrower_department = trim($_POST['borrower_department'] ?? '');
$purpose = trim($_POST['purpose'] ?? '');
$due_input = $_POST['due_date'] ?? '';

if (
    $unit_id <= 0 ||
    $borrower_name === '' || mb_strlen($borrower_name) > 150 ||
    $borrower_department === '' || mb_strlen($borrower_department) > 150 ||
    $purpose === '' || mb_strlen($purpose) > 500
) {
    http_response_code(422);
    exit('Please complete all required fields correctly.');
}

$due_timestamp = strtotime($due_input);

if ($due_timestamp === false || $due_timestamp <= time()) {
    http_response_code(422);
    exit('Expected return date must be later than the current time.');
}

$due_date = date('Y-m-d H:i:s', $due_timestamp);
$borrow_source = 'Public QR';

$conn->begin_transaction();

try {
    $asset_stmt = $conn->prepare("
        SELECT
            au.id AS unit_id,
            au.inventory_id,
            au.asset_code,
            au.unit_status AS asset_status,
            au.condition_status AS item_condition,
            i.item_name,
            i.asset_usage
        FROM asset_units au
        INNER JOIN inventory_items i ON i.id = au.inventory_id
        WHERE au.id = ? AND i.item_type = 'Asset'
        FOR UPDATE
    ");
    $asset_stmt->bind_param('i', $unit_id);
    $asset_stmt->execute();
    $asset = $asset_stmt->get_result()->fetch_assoc();

    if (!$asset) {
        throw new RuntimeException('Asset not found.');
    }

    if (!in_array($asset['asset_usage'], ['Borrowable', 'Both'], true)) {
        throw new RuntimeException('This asset is not borrowable.');
    }

    if ($asset['asset_status'] !== 'Available') {
        throw new RuntimeException('This asset is no longer available.');
    }

    $active_stmt = $conn->prepare("
        SELECT id FROM borrow_transactions
        WHERE asset_unit_id = ? AND status = 'Borrowed'
        LIMIT 1
        FOR UPDATE
    ");
    $active_stmt->bind_param('i', $unit_id);
    $active_stmt->execute();

    if ($active_stmt->get_result()->num_rows > 0) {
        throw new RuntimeException('This asset already has an active borrowing record.');
    }

    $condition = $asset['item_condition'] ?: 'Good';
    $remarks = 'Manual QR borrowing form';

    $configured_encoder_id = intval(getenv('ICSS_PUBLIC_QR_ENCODER_ID') ?: 0);
    $encoder_id = 0;

    if ($configured_encoder_id > 0) {
        $encoder_check = $conn->prepare("
            SELECT id FROM users
            WHERE id = ? AND system_role = 'Purchasing' AND status = 'Active'
        ");
        $encoder_check->bind_param('i', $configured_encoder_id);
        $encoder_check->execute();
        $encoder_id = (int) ($encoder_check->get_result()->fetch_assoc()['id'] ?? 0);
    }

    if ($encoder_id <= 0) {
        $encoder = $conn->query("
            SELECT id FROM users
            WHERE system_role = 'Purchasing' AND status = 'Active'
            ORDER BY id ASC
            LIMIT 1
        ")->fetch_assoc();
        $encoder_id = (int) ($encoder['id'] ?? 0);
    }

    if ($encoder_id <= 0) {
        throw new RuntimeException('No active Purchasing encoder is configured.');
    }

    $insert = $conn->prepare("
        INSERT INTO borrow_transactions
        (
            item_id, asset_unit_id, employee_id, borrower_name, borrower_department, purpose,
            borrow_source, borrow_date, due_date, borrow_condition, remarks, status
        )
        VALUES (?, ?, NULL, ?, ?, ?, ?, NOW(), ?, ?, ?, 'Borrowed')
    ");
    $insert->bind_param(
        'iisssssss',
        $asset['inventory_id'],
        $unit_id,
        $borrower_name,
        $borrower_department,
        $purpose,
        $borrow_source,
        $due_date,
        $condition,
        $remarks
    );
    $insert->execute();
    $borrow_id = $insert->insert_id;

    // Use the unit row as the concurrency boundary.
    $update = $conn->prepare("
        UPDATE asset_units
        SET unit_status = 'Borrowed'
        WHERE id = ? AND unit_status = 'Available'
    ");
    $update->bind_param('i', $unit_id);
    $update->execute();

    if ($update->affected_rows !== 1) {
        throw new RuntimeException('The asset availability changed. Please scan again.');
    }

    $history_remarks = 'Manual QR borrowing form';
    $history = $conn->prepare("
        INSERT INTO asset_history
        (
            inventory_id, asset_unit_id, assignment_id, borrow_transaction_id,
            action_type, employee_id, borrower_name_snapshot,
            borrower_department_snapshot, purpose_snapshot, action_date,
            condition_status, remarks, created_by
        )
        VALUES (?, ?, NULL, ?, 'Borrowed', NULL, ?, ?, ?, CURDATE(), ?, ?, ?)
    ");
    $history->bind_param(
        'iiisssssi',
        $asset['inventory_id'],
        $unit_id,
        $borrow_id,
        $borrower_name,
        $borrower_department,
        $purpose,
        $condition,
        $history_remarks,
        $encoder_id
    );
    $history->execute();

    $inventory_log = $conn->prepare("
        INSERT INTO inventory_logs
        (inventory_id, reference_type, reference_id, movement_type, quantity, remarks, created_by)
        VALUES (?, 'Public QR Borrow', ?, 'Borrowed', 1, ?, ?)
    ");
    $inventory_log->bind_param('iisi', $asset['inventory_id'], $borrow_id, $purpose, $encoder_id);
    $inventory_log->execute();

    $conn->commit();
    header('Location: public_asset_borrow.php?unit_id=' . $unit_id . '&success=1', true, 303);
    exit();
} catch (Throwable $e) {
    $conn->rollback();
    http_response_code(409);
    exit(h($e->getMessage()) . ' Please return to the QR form and try again.');
}
