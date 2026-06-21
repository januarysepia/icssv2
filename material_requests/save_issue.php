<?php

include '../auth/auth_check.php';

require_role([
    'Purchasing',
    'Admin',
    'Boss'
]);

include '../config/database.php';

$material_request_id = intval($_POST['material_request_id']);
$jo_id = intval($_POST['jo_id']);

$request_item_ids = $_POST['request_item_id'] ?? [];
$inventory_ids = $_POST['inventory_id'] ?? [];
$quantities_issued = $_POST['quantity_issued'] ?? [];
$remarks = $_POST['remarks'] ?? [];

$issued_by = $_SESSION['user_id'];

for($i = 0; $i < count($request_item_ids); $i++){

    $request_item_id = intval($request_item_ids[$i]);
    $inventory_id = intval($inventory_ids[$i]);
    $quantity_issued = intval($quantities_issued[$i]);
    $remark = $conn->real_escape_string($remarks[$i] ?? '');

    if($quantity_issued <= 0){
        continue;
    }

    if($inventory_id <= 0){
        echo "
        <script>
            alert('Cannot issue item without inventory record.');
            window.location='issue_materials.php?id=$material_request_id';
        </script>
        ";
        exit();
    }

    $item = $conn->query("
    SELECT *
    FROM material_request_items
    WHERE id = '$request_item_id'
    ")->fetch_assoc();

    if(!$item){
        echo "
        <script>
            alert('Material request item not found.');
            window.location='issue_materials.php?id=$material_request_id';
        </script>
        ";
        exit();
    }

    if(
        $item['item_status'] != 'For Issue'
        &&
        $item['item_status'] != 'Received'
    ){
        echo "
        <script>
            alert('Only For Issue or Received items can be issued.');
            window.location='issue_materials.php?id=$material_request_id';
        </script>
        ";
        exit();
    }

    if($quantity_issued > intval($item['quantity'])){
        echo "
        <script>
            alert('Issued quantity cannot exceed requested quantity.');
            window.location='issue_materials.php?id=$material_request_id';
        </script>
        ";
        exit();
    }

    $inventory = $conn->query("
    SELECT *
    FROM inventory_items
    WHERE id = '$inventory_id'
    ")->fetch_assoc();

    if(!$inventory){
        echo "
        <script>
            alert('Inventory item not found.');
            window.location='issue_materials.php?id=$material_request_id';
        </script>
        ";
        exit();
    }

    if(intval($inventory['quantity']) < $quantity_issued){
        echo "
        <script>
            alert('Insufficient stock for ".$inventory['item_name']."');
            window.location='issue_materials.php?id=$material_request_id';
        </script>
        ";
        exit();
    }

    $conn->query("
    UPDATE inventory_items
    SET quantity = quantity - '$quantity_issued'
    WHERE id = '$inventory_id'
    ");

    $conn->query("
    UPDATE material_request_items
    SET item_status = 'Issued'
    WHERE id = '$request_item_id'
    ");

    $conn->query("
    INSERT INTO material_issuance_logs
    (
        material_request_id,
        material_request_item_id,
        inventory_id,
        jo_id,
        issued_by,
        quantity_issued,
        remarks
    )
    VALUES
    (
        '$material_request_id',
        '$request_item_id',
        '$inventory_id',
        '$jo_id',
        '$issued_by',
        '$quantity_issued',
        '$remark'
    )
    ");

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
        'Material Request',
        '$material_request_id',
        'Stock Out',
        '$quantity_issued',
        'Issued material to production',
        '$issued_by'
    )
    ");
}

/*
AUTO UPDATE OVERALL REQUEST STATUS
*/

$status_summary = $conn->query("
SELECT
SUM(CASE WHEN item_status = 'Issued' THEN 1 ELSE 0 END) AS issued_count,
SUM(CASE WHEN item_status = 'To Purchase' THEN 1 ELSE 0 END) AS to_purchase_count,
SUM(CASE WHEN item_status = 'For Issue' THEN 1 ELSE 0 END) AS for_issue_count,
SUM(CASE WHEN item_status = 'Received' THEN 1 ELSE 0 END) AS received_count,
SUM(CASE WHEN item_status = 'Purchased' THEN 1 ELSE 0 END) AS purchased_count,
SUM(CASE WHEN item_status = 'Waiting Delivery' THEN 1 ELSE 0 END) AS waiting_delivery_count,
COUNT(*) AS total_items

FROM material_request_items
WHERE request_id = '$material_request_id'
")->fetch_assoc();

$issued_count = intval($status_summary['issued_count']);
$to_purchase_count = intval($status_summary['to_purchase_count']);
$for_issue_count = intval($status_summary['for_issue_count']);
$received_count = intval($status_summary['received_count']);
$purchased_count = intval($status_summary['purchased_count']);
$waiting_delivery_count = intval($status_summary['waiting_delivery_count']);
$total_items = intval($status_summary['total_items']);

if($total_items > 0 && $issued_count == $total_items){

    $new_status = 'Completed';

}elseif($received_count > 0){

    $new_status = 'Partially Received';

}elseif($waiting_delivery_count > 0 || $purchased_count > 0){

    $new_status = 'Waiting Delivery';

}elseif($to_purchase_count > 0 && $for_issue_count == 0){

    $new_status = 'Pending Purchase';

}elseif($to_purchase_count > 0){

    $new_status = 'Partially Released / Pending Purchase';

}else{

    $new_status = 'Partially Released';

}

$request = $conn->query("
SELECT status
FROM material_requests
WHERE id = '$material_request_id'
")->fetch_assoc();

$old_status = $request['status'] ?? '';

$conn->query("
UPDATE material_requests
SET status = '$new_status'
WHERE id = '$material_request_id'
");

$conn->query("
INSERT INTO material_request_status_logs
(
    request_id,
    updated_by,
    old_status,
    new_status,
    notes
)
VALUES
(
    '$material_request_id',
    '$issued_by',
    '$old_status',
    '$new_status',
    'Materials issued to production'
)
");

echo "
<script>
    alert('Materials Issued Successfully');
    window.location='view_request.php?id=$material_request_id';
</script>
";

?>