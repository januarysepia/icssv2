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

$asset_unit_id = intval($_POST['asset_unit_id'] ?? 0);
$assigned_to = intval($_POST['assigned_to'] ?? 0);
$assigned_date = $conn->real_escape_string($_POST['assigned_date'] ?? date('Y-m-d'));
$condition_before = $conn->real_escape_string($_POST['condition_before'] ?? 'Good');
$remarks = $conn->real_escape_string($_POST['remarks'] ?? '');

$assigned_by = $_SESSION['user_id'];

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $assigned_date)) {
    exit('Invalid assignment date.');
}

if (!in_array($condition_before, ['Good', 'With Minor Issue', 'Damaged'], true)) {
    exit('Invalid asset condition.');
}

$conn->begin_transaction();

/*
VALIDATION
*/

if($asset_unit_id <= 0 || $assigned_to <= 0){

    echo "
    <script>
        alert('Invalid asset assignment data.');
        window.location='asset_list.php';
    </script>
    ";

    exit();
}

/*
GET ASSET
*/

$asset = $conn->query("
SELECT au.*, i.id AS inventory_id, i.item_name, i.asset_usage, i.item_code
FROM asset_units au
INNER JOIN inventory_items i ON i.id = au.inventory_id
WHERE au.id = '$asset_unit_id'
AND i.item_type = 'Asset'
FOR UPDATE
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
CHECK IF ASSET IS ALLOWED FOR ASSIGNMENT
*/

if(
    $asset['asset_usage'] != 'Assigned'
    &&
    $asset['asset_usage'] != 'Both'
){
    $conn->rollback();
    echo "
    <script>
        alert('This asset is not allowed for employee assignment.');
        window.location='asset_list.php';
    </script>
    ";

    exit();
}

/*
CHECK AVAILABILITY
*/

if($asset['unit_status'] != 'Available' || !empty($asset['assigned_to'])){
    $conn->rollback();
    echo "
    <script>
        alert('This asset is not available for assignment.');
        window.location='asset_list.php';
    </script>
    ";

    exit();
}

/*
GET EMPLOYEE
*/

$employee = $conn->query("
SELECT *
FROM users
WHERE id = '$assigned_to'
AND status = 'Active'
")->fetch_assoc();

if(!$employee){
    $conn->rollback();
    echo "
    <script>
        alert('Selected employee not found or inactive.');
        window.location='assign_asset.php?unit_id=$asset_unit_id';
    </script>
    ";

    exit();
}

/*
SAVE ASSET ASSIGNMENT HISTORY
*/

$conn->query("
INSERT INTO asset_assignments
(
    inventory_id,
    asset_unit_id,
    assigned_to,
    assigned_by,
    assigned_date,
    status,
    condition_before,
    remarks
)
VALUES
(
    '{$asset['inventory_id']}',
    '$asset_unit_id',
    '$assigned_to',
    '$assigned_by',
    '$assigned_date',
    'Assigned',
    '$condition_before',
    '$remarks'
)
");

if($conn->error){
    $conn->rollback();
    die('Unable to save asset assignment.');
}

$assignment_id = $conn->insert_id;
$inventory_id = (int) $asset['inventory_id'];

/*
UPDATE INVENTORY ITEM
*/

$conn->query("
UPDATE asset_units
SET
    assigned_to = '$assigned_to',
    assigned_date = '$assigned_date',
    unit_status = 'Assigned',
    condition_status = '$condition_before'
WHERE id = '$asset_unit_id'
");

if($conn->error){
    $conn->rollback();
    die('Unable to update asset status.');
}

/*
SAVE INVENTORY LOG
*/

$log_remarks = $conn->real_escape_string(
    'Asset assigned to ' .
    $employee['employee_no'] .
    ' - ' .
    $employee['fullname'] .
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
    'Asset Assignment',
    '$assignment_id',
    'Assigned',
    1,
    '$log_remarks',
    '$assigned_by'
)
");

if($conn->error){
    $conn->rollback();
    die('Unable to save assignment log.');
}

if (!logAssetHistory(
    $conn,
    $inventory_id,
    $asset_unit_id,
    $assignment_id,
    'Assigned',
    $assigned_to,
    $assigned_date,
    $condition_before,
    $remarks,
    $assigned_by
)) {
    $conn->rollback();
    die('Unable to save asset history.');
}

$conn->commit();

echo "
<script>
    alert('Asset Assigned Successfully');
    window.location='asset_list.php';
</script>
";

?>
