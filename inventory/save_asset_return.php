<?php

include '../auth/auth_check.php';

require_role([
    'Boss',
    'Admin',
    'Purchasing'
]);

include '../config/database.php';
include_once 'asset_history_helper.php';

require_post();
verify_csrf();

$inventory_id = intval($_POST['inventory_id'] ?? 0);
$asset_unit_id = intval($_POST['asset_unit_id'] ?? 0);
$assignment_id = intval($_POST['assignment_id'] ?? 0);

$return_date = $conn->real_escape_string(
    $_POST['return_date'] ?? date('Y-m-d')
);

$condition_after = $conn->real_escape_string(
    $_POST['condition_after'] ?? 'Good'
);

$remarks = $conn->real_escape_string(
    $_POST['remarks'] ?? ''
);

$returned_by = $_SESSION['user_id'];

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $return_date)) {
    exit('Invalid return date.');
}

if (!in_array($condition_after, ['Good', 'With Minor Issue', 'Damaged', 'Lost', 'Under Repair'], true)) {
    exit('Invalid return condition.');
}

$conn->begin_transaction();

/*
VALIDATION
*/

if($inventory_id <= 0 || $asset_unit_id <= 0 || $assignment_id <= 0){

    echo "
    <script>
    alert('Invalid return transaction.');
    window.location='asset_list.php';
    </script>
    ";
    exit();
}

/*
GET ASSET
*/

$asset = $conn->query("
SELECT au.*, i.asset_usage, i.item_name
FROM asset_units au
INNER JOIN inventory_items i ON i.id = au.inventory_id
WHERE au.id = '$asset_unit_id'
AND au.inventory_id = '$inventory_id'
AND i.item_type = 'Asset'
")->fetch_assoc();

if(!$asset){
    $conn->rollback();
    echo "
    <script>
    alert('Asset not found.');
    window.location='asset_list.php';
    </script>
    ";
    exit();
}

/*
VERIFY ASSIGNMENT CAPABILITY
*/

if(
    $asset['asset_usage'] != 'Assigned'
    &&
    $asset['asset_usage'] != 'Both'
){
    $conn->rollback();
    echo "
    <script>
    alert('This asset is not configured for assignment tracking.');
    window.location='asset_list.php';
    </script>
    ";
    exit();
}

/*
GET ASSIGNMENT
*/

$assignment = $conn->query("
SELECT *
FROM asset_assignments
WHERE id = '$assignment_id'
AND inventory_id = '$inventory_id'
AND asset_unit_id = '$asset_unit_id'
AND status = 'Assigned'
")->fetch_assoc();

if(!$assignment){
    $conn->rollback();
    echo "
    <script>
    alert('Assignment record not found.');
    window.location='asset_list.php';
    </script>
    ";
    exit();
}

/*
DETERMINE NEW STATUS
*/

$new_asset_status = 'Available';

switch($condition_after){

    case 'Damaged':
        $new_asset_status = 'Damaged';
        break;

    case 'Lost':
        $new_asset_status = 'Lost';
        break;

    case 'Under Repair':
        $new_asset_status = 'Under Repair';
        break;

    default:
        $new_asset_status = 'Available';
}

/*
UPDATE ASSIGNMENT HISTORY
*/

$conn->query("
UPDATE asset_assignments
SET
    return_date = '$return_date',
    returned_by = '$returned_by',
    condition_after = '$condition_after',
    remarks = CONCAT(
        IFNULL(remarks,''),
        '\n\nRETURN REMARKS:\n',
        '$remarks'
    ),
    status = 'Returned'
WHERE id = '$assignment_id'
");

if($conn->error){
    $conn->rollback();
    die('Unable to update assignment.');
}

/*
UPDATE INVENTORY ITEM
*/

$conn->query("
UPDATE asset_units
SET
    assigned_to = NULL,
    assigned_date = NULL,
    unit_status = '$new_asset_status',
    condition_status = '$condition_after'
WHERE id = '$asset_unit_id'
");

if($conn->error){
    $conn->rollback();
    die('Unable to update asset.');
}

/*
GET EMPLOYEE INFO
*/

$employee = $conn->query("
SELECT
employee_no,
fullname
FROM users
WHERE id = '".$assignment['assigned_to']."'
")->fetch_assoc();

$employee_text = '';

if($employee){

    $employee_text =
    $employee['employee_no']
    . ' - '
    . $employee['fullname'];
}

/*
SAVE INVENTORY LOG
*/

$log_remarks = $conn->real_escape_string(
    'Asset returned by ' .
    $employee_text .
    '. Condition: ' .
    $condition_after .
    '. ' .
    $remarks
);

$conn->query("
INSERT INTO inventory_logs
(
    inventory_id,
    reference_type,
    reference_id,
    movement_type,
    quantity,
    remarks,
    created_by
)
VALUES
(
    '$inventory_id',
    'Asset Return',
    '$assignment_id',
    'Returned',
    1,
    '$log_remarks',
    '$returned_by'
)
");

if($conn->error){
    $conn->rollback();
    die('Unable to save return log.');
}

$history_action = in_array($condition_after, ['Damaged', 'Lost'], true)
    ? $condition_after
    : 'Returned';

if (!logAssetHistory(
    $conn,
    $inventory_id,
    $asset_unit_id,
    $assignment_id,
    $history_action,
    (int) $assignment['assigned_to'],
    $return_date,
    $condition_after,
    $remarks,
    $returned_by
)) {
    $conn->rollback();
    die('Unable to save asset history.');
}

$conn->commit();

/*
SUCCESS
*/

echo "
<script>
alert('Asset Returned Successfully');
window.location='asset_list.php';
</script>
";

?>
