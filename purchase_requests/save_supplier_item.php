<?php

include '../auth/auth_check.php';
require_role(['Purchasing', 'Admin']);
include '../config/database.php';

require_post();
verify_csrf();

$id = max(0, (int) ($_POST['id'] ?? 0));
$inventory_id = max(0, (int) ($_POST['inventory_id'] ?? 0));
$supplier_id = max(0, (int) ($_POST['supplier_id'] ?? 0));
$supplier_item_code = trim($_POST['supplier_item_code'] ?? '');
$unit_price = (float) ($_POST['unit_price'] ?? 0);
$is_preferred = isset($_POST['is_preferred']) ? 1 : 0;
$remarks = trim($_POST['remarks'] ?? '');
$user_id = (int) $_SESSION['user_id'];

if ($inventory_id <= 0 || $supplier_id <= 0 || $unit_price <= 0) {
    exit('Item, supplier, and a valid price are required.');
}

$valid = $conn->prepare("
    SELECT ii.id
    FROM inventory_items ii
    INNER JOIN suppliers s ON s.id = ? AND s.status = 'Active'
    WHERE ii.id = ?
");
$valid->bind_param('ii', $supplier_id, $inventory_id);
$valid->execute();
if (!$valid->get_result()->fetch_assoc()) {
    exit('Invalid item or supplier.');
}

$conn->begin_transaction();

if ($is_preferred) {
    $clear = $conn->prepare("UPDATE item_suppliers SET is_preferred = 0 WHERE inventory_id = ?");
    $clear->bind_param('i', $inventory_id);
    $clear->execute();
}

if ($id > 0) {
    $stmt = $conn->prepare("
        UPDATE item_suppliers
        SET supplier_item_code = ?, unit_price = ?, is_preferred = ?, remarks = ?
        WHERE id = ? AND inventory_id = ? AND supplier_id = ?
    ");
    $stmt->bind_param('sdisiii', $supplier_item_code, $unit_price, $is_preferred, $remarks, $id, $inventory_id, $supplier_id);
    $stmt->execute();
    $item_supplier_id = $id;
} else {
    $stmt = $conn->prepare("
        INSERT INTO item_suppliers
            (inventory_id, supplier_id, supplier_item_code, unit_price, is_preferred, remarks, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            id = LAST_INSERT_ID(id),
            supplier_item_code = VALUES(supplier_item_code),
            unit_price = VALUES(unit_price),
            is_preferred = VALUES(is_preferred),
            remarks = VALUES(remarks)
    ");
    $stmt->bind_param('iisdisi', $inventory_id, $supplier_id, $supplier_item_code, $unit_price, $is_preferred, $remarks, $user_id);
    $stmt->execute();
    $item_supplier_id = (int) $conn->insert_id;
}

if ($stmt->errno || $item_supplier_id <= 0) {
    $conn->rollback();
    exit('Unable to save supplier item.');
}

$history = $conn->prepare("
    INSERT INTO supplier_price_history
        (item_supplier_id, inventory_id, supplier_id, unit_price, source_type, remarks, recorded_by)
    VALUES (?, ?, ?, ?, 'Manual Quote', ?, ?)
");
$history_note = $remarks !== '' ? $remarks : 'Supplier catalog price updated';
$history->bind_param('iiidsi', $item_supplier_id, $inventory_id, $supplier_id, $unit_price, $history_note, $user_id);
$history->execute();

if ($history->errno) {
    $conn->rollback();
    exit('Unable to save price history.');
}

$conn->commit();
header('Location: supplier_catalog.php?inventory_id=' . $inventory_id);
exit();
