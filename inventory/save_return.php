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

$borrow_id = intval($_POST['borrow_id'] ?? 0);
$item_id = intval($_POST['item_id'] ?? 0);
$asset_unit_id = intval($_POST['asset_unit_id'] ?? 0);
$return_condition = $conn->real_escape_string($_POST['return_condition'] ?? '');
$remarks = $conn->real_escape_string($_POST['remarks'] ?? '');

if (!in_array($return_condition, ['Good', 'With Minor Issue', 'Damaged', 'Lost'], true)) {
    exit('Invalid return condition.');
}

$conn->begin_transaction();

$borrow = $conn->query("
SELECT *
FROM borrow_transactions
WHERE id = '$borrow_id'
AND item_id = '$item_id'
AND asset_unit_id = '$asset_unit_id'
FOR UPDATE
")->fetch_assoc();

if(!$borrow){
    $conn->rollback();
    die("Borrow transaction not found.");
}

if($borrow['status'] != 'Borrowed'){
    $conn->rollback();
    die("This item has already been returned.");
}

/*
UPDATE BORROW TRANSACTION
*/

$conn->query("
UPDATE borrow_transactions
SET
return_date = NOW(),
return_condition = '$return_condition',
remarks = CONCAT(IFNULL(remarks,''), '\nReturn Remarks: $remarks'),
status = 'Returned'
WHERE id = '$borrow_id'
");

/*
UPDATE ITEM STATUS BASED ON RETURN CONDITION
*/

if($return_condition == 'Good' || $return_condition == 'With Minor Issue'){

    $asset_status = 'Available';

}elseif($return_condition == 'Damaged'){

    $asset_status = 'Under Repair';

}elseif($return_condition == 'Lost'){

    $asset_status = 'Lost';

}else{

    $asset_status = 'Available';
}

$conn->query("
UPDATE asset_units
SET
unit_status = '$asset_status',
condition_status = '$return_condition'
WHERE id = '$asset_unit_id'
");

$borrower_label = !empty($borrow['borrower_name'])
    ? $borrow['borrower_name']
    : 'Employee ID ' . $borrow['employee_id'];
$history_remarks = $conn->real_escape_string(
    'Returned by ' . $borrower_label . '. ' . $remarks
);
$history_employee = !empty($borrow['employee_id']) ? intval($borrow['employee_id']) : 'NULL';
$returned_by = intval($_SESSION['user_id']);

$conn->query("
INSERT INTO asset_history
(inventory_id, asset_unit_id, assignment_id, borrow_transaction_id, action_type, employee_id, action_date, condition_status, remarks, borrower_name_snapshot, borrower_department_snapshot, purpose_snapshot, created_by)
VALUES
('$item_id', '$asset_unit_id', NULL, '$borrow_id', 'Returned', $history_employee, CURDATE(), '$return_condition', '$history_remarks',
 " . (!empty($borrow['borrower_name']) ? "'" . $conn->real_escape_string($borrow['borrower_name']) . "'" : "NULL") . ",
 " . (!empty($borrow['borrower_department']) ? "'" . $conn->real_escape_string($borrow['borrower_department']) . "'" : "NULL") . ",
 " . (!empty($borrow['purpose']) ? "'" . $conn->real_escape_string($borrow['purpose']) . "'" : "NULL") . ",
 '$returned_by')
");

$notification_key = $conn->real_escape_string('overdue_asset_borrow:' . $borrow_id);
$conn->query("
    UPDATE notifications
    SET is_read = 1
    WHERE notification_key = '$notification_key'
");

$conn->commit();

echo "
<script>
alert('Item returned successfully.');
window.location='scan_item.php?unit_id=$asset_unit_id';
</script>
";

?>
