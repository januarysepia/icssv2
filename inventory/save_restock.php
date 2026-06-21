<?php

include '../auth/auth_check.php';

require_role([
    'Admin',
    'Purchasing'
]);

include '../config/database.php';

require_post();
verify_csrf();

$item_id = intval($_POST['item_id'] ?? 0);
$quantity = intval($_POST['quantity'] ?? 0);
$supplier_id = intval($_POST['supplier_id'] ?? 0);
$adjustment_category = trim($_POST['adjustment_category'] ?? '');
$movement_type = trim($_POST['movement_type'] ?? '');

$unit_price = floatval(
    $_POST['unit_price'] ?? 0
);

$remarks_text = trim($_POST['remarks'] ?? '');

$created_by = $_SESSION['user_id'];

$allowed_categories = [
    'Beginning Inventory',
    'Stock Count Correction',
    'Donation or Free Replacement',
    'Returned Unused Materials',
    'Emergency Purchase',
    'Other',
];

if($item_id <= 0){

    die("Invalid Item ID.");

}

if($quantity <= 0){

    die("Invalid adjustment quantity.");

}

if (!in_array($adjustment_category, $allowed_categories, true)) {
    die("Please select a valid adjustment category.");
}

if (!in_array($movement_type, ['Stock In', 'Stock Out'], true)) {
    die("Please select a valid stock movement.");
}

if (mb_strlen($remarks_text) < 5) {
    die("Please provide a detailed explanation for this manual adjustment.");
}

$supplier = null;
$supplier_snapshot = null;

if ($supplier_id > 0) {
    $supplier_stmt = $conn->prepare("
        SELECT id, supplier_name
        FROM suppliers
        WHERE id = ? AND status = 'Active'
    ");
    $supplier_stmt->bind_param('i', $supplier_id);
    $supplier_stmt->execute();
    $supplier_record = $supplier_stmt->get_result()->fetch_assoc();

    if(!$supplier_record){
        die("Selected supplier is invalid or inactive.");
    }

    $supplier = $supplier_record['supplier_name'];
    $supplier_snapshot = $supplier_record['supplier_name'];
}

$conn->begin_transaction();

/*
GET ITEM
*/

$item_result = $conn->query("
SELECT *
FROM inventory_items
WHERE id = '$item_id'
");

if(!$item_result){

    die($conn->error);

}

if($item_result->num_rows == 0){
    $conn->rollback();
    die("Item not found.");

}

$item = $item_result->fetch_assoc();

if (strcasecmp((string) ($item['item_type'] ?? ''), 'Asset') === 0) {
    $conn->rollback();
    die("Asset quantities must be managed through individual units in the Asset Catalog.");
}

if ($movement_type === 'Stock Out' && $quantity > (int) $item['quantity']) {
    $conn->rollback();
    die("Adjustment cannot result in a negative inventory quantity.");
}

/*
UPDATE INVENTORY
*/

$quantity_operator = $movement_type === 'Stock Out' ? '-' : '+';
$update_fields = ["quantity = quantity $quantity_operator ?"];
$update_types = 'i';
$update_values = [$quantity];

if ($supplier !== null) {
    $update_fields[] = "supplier = ?";
    $update_types .= 's';
    $update_values[] = $supplier;
}

if ($unit_price > 0) {
    $update_fields[] = "unit_price = ?";
    $update_types .= 'd';
    $update_values[] = $unit_price;
}

$update_types .= 'i';
$update_values[] = $item_id;
$update_stmt = $conn->prepare("
    UPDATE inventory_items
    SET " . implode(', ', $update_fields) . "
    WHERE id = ?
");
$update_stmt->bind_param($update_types, ...$update_values);
$update_stmt->execute();

if($update_stmt->errno){
    $conn->rollback();
    die($update_stmt->error);

}

/*
SAVE INVENTORY LOG
*/

$log_remarks = $adjustment_category . ': ' . $remarks_text;
$log_stmt = $conn->prepare("
    INSERT INTO inventory_logs
    (
        inventory_id,
        reference_type,
        reference_id,
        supplier_id,
        supplier_name_snapshot,
        unit_price_snapshot,
        movement_type,
        quantity,
        remarks,
        created_by
    )
    VALUES (?, 'Manual Stock Adjustment', ?, NULLIF(?, 0), ?, ?, ?, ?, ?, ?)
");
$log_stmt->bind_param(
    'iiisdsisi',
    $item_id,
    $item_id,
    $supplier_id,
    $supplier_snapshot,
    $unit_price,
    $movement_type,
    $quantity,
    $log_remarks,
    $created_by
);
$log_stmt->execute();

if($log_stmt->errno){
    $conn->rollback();
    die($log_stmt->error);

}

$conn->commit();

/*
SUCCESS
*/

echo "
<script>
alert('Manual stock adjustment saved successfully.');
window.location='inventory_list.php';
</script>
";

?>
