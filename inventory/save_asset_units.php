<?php

include '../auth/auth_check.php';
require_role(['Boss', 'Admin', 'Purchasing']);
include '../config/database.php';
require_post();
verify_csrf();

$inventory_id = intval($_POST['inventory_id'] ?? 0);
$quantity = intval($_POST['quantity'] ?? 0);
$condition = $_POST['condition_status'] ?? 'Good';
$location = trim($_POST['storage_location'] ?? '');
$serial_text = trim($_POST['serial_numbers'] ?? '');

if ($inventory_id <= 0 || $quantity < 1 || $quantity > 100) {
    exit('Invalid unit quantity.');
}
if (!in_array($condition, ['Good','With Minor Issue','Damaged'], true)) {
    exit('Invalid condition.');
}

$serials = [];
foreach (preg_split('/\R/', $serial_text) as $line) {
    $line = trim($line);
    if ($line !== '') $serials[] = $line;
}
if (count($serials) > $quantity || count($serials) !== count(array_unique($serials))) {
    exit('Check the serial number list for excess or duplicate entries.');
}

$conn->begin_transaction();
try {
    $catalogStmt = $conn->prepare("SELECT item_code FROM inventory_items WHERE id=? AND item_type='Asset' FOR UPDATE");
    $catalogStmt->bind_param('i', $inventory_id);
    $catalogStmt->execute();
    $catalog = $catalogStmt->get_result()->fetch_assoc();
    if (!$catalog) throw new RuntimeException('Asset catalog item not found.');

    $existing = $conn->prepare("SELECT COUNT(*) total FROM asset_units WHERE inventory_id=?");
    $existing->bind_param('i', $inventory_id);
    $existing->execute();
    $start = (int) $existing->get_result()->fetch_assoc()['total'] + 1;

    $insert = $conn->prepare("
        INSERT INTO asset_units
        (inventory_id, asset_code, serial_number, unit_status, condition_status, storage_location)
        VALUES (?, ?, ?, 'Available', ?, ?)
    ");
    for ($offset = 0; $offset < $quantity; $offset++) {
        $number = $start + $offset;
        $code = $catalog['item_code'] . '-' . str_pad((string) $number, 3, '0', STR_PAD_LEFT);
        $serial = $serials[$offset] ?? null;
        $insert->bind_param('issss', $inventory_id, $code, $serial, $condition, $location);
        $insert->execute();
    }

    $update = $conn->prepare("UPDATE inventory_items SET quantity=(SELECT COUNT(*) FROM asset_units WHERE inventory_id=?) WHERE id=?");
    $update->bind_param('ii', $inventory_id, $inventory_id);
    $update->execute();
    $conn->commit();
    header('Location: asset_catalog.php', true, 303);
} catch (Throwable $e) {
    $conn->rollback();
    http_response_code(409);
    exit('Unable to add units: ' . h($e->getMessage()));
}
