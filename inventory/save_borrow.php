<?php

include '../auth/auth_check.php';

require_role([
    'Boss',
    'Admin',
    'Purchasing',
    'Supervisor',
    'Production',
    'Engineer',
    'Technical',
    'Logistics'
]);

include '../config/database.php';

require_post();
verify_csrf();

$item_id = intval($_POST['item_id'] ?? 0);
$asset_unit_id = intval($_POST['asset_unit_id'] ?? 0);
$employee_id = $_SESSION['user_id'];

$borrow_condition = $conn->real_escape_string($_POST['borrow_condition']);
$due_date = $conn->real_escape_string($_POST['due_date']);
$remarks = $conn->real_escape_string($_POST['remarks'] ?? '');

$item = $conn->query("
SELECT au.*, i.asset_usage
FROM asset_units au
INNER JOIN inventory_items i ON i.id=au.inventory_id
WHERE au.id = '$asset_unit_id'
AND au.inventory_id = '$item_id'
FOR UPDATE
")->fetch_assoc();

if(!$item){
    die("Item not found.");
}

if(
    ($item['item_type'] ?? 'Consumable') !== 'Asset'
    || !in_array(($item['asset_usage'] ?? ''), ['Borrowable', 'Both'], true)
){
    die("This item is not borrowable.");
}

if(($item['unit_status'] ?? 'Available') != 'Available'){
    die("This item is not available.");
}

/*
SAVE BORROW TRANSACTION
*/

$conn->query("
INSERT INTO borrow_transactions
(
    item_id,
    asset_unit_id,
    employee_id,
    borrow_date,
    due_date,
    borrow_condition,
    remarks,
    status
)
VALUES
(
    '$item_id',
    '$asset_unit_id',
    '$employee_id',
    NOW(),
    '$due_date',
    '$borrow_condition',
    '$remarks',
    'Borrowed'
)
");

/*
UPDATE ITEM STATUS
*/

$conn->query("
UPDATE asset_units
SET
unit_status = 'Borrowed',
condition_status = '$borrow_condition'
WHERE id = '$asset_unit_id'
");

echo "
<script>
alert('Item borrowed successfully.');
window.location='scan_item.php?unit_id=$asset_unit_id';
</script>
";

?>
