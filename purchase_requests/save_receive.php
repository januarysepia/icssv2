<?php

include '../auth/auth_check.php';

require_role([
    'Purchasing',
    'Admin'
]);

include '../config/database.php';
include '../includes/create_notification.php';

require_post();
verify_csrf();

$purchase_request_id = intval($_POST['purchase_request_id'] ?? 0);

$purchase_item_ids = $_POST['purchase_item_id'] ?? ($_POST['item_id'] ?? []);
$quantities_received = $_POST['quantity_received'] ?? [];
$remarks = $_POST['remarks'] ?? [];
$conditions = $_POST['condition'] ?? [];

$received_by = $_SESSION['user_id'];

$conn->begin_transaction();

$purchase = $conn->query("
SELECT *
FROM purchase_requests
WHERE id = '$purchase_request_id'
")->fetch_assoc();

if(!$purchase){
    $conn->rollback();
    die('Purchase request not found.');
}

if ($purchase['status'] !== 'Boss Approved') {
    $conn->rollback();
    exit('Only an approved purchase request can be received.');
}

$purchase_no = $purchase['purchase_no'];
$requested_by = $purchase['requested_by'];
$material_request_id = $purchase['material_request_id'];
$received_item_count = 0;

for($i = 0; $i < count($purchase_item_ids); $i++){

    $purchase_item_id = intval($purchase_item_ids[$i]);
    $qty_received = intval($quantities_received[$i]);
    $remark = $conn->real_escape_string($remarks[$i] ?? '');

    $condition = isset($conditions[$i])
        ? $conn->real_escape_string($conditions[$i])
        : 'Good';

    if($qty_received <= 0){
        continue;
    }

    $item = $conn->query("
    SELECT *
    FROM purchase_request_items
    WHERE id = '$purchase_item_id'
    AND purchase_request_id = '$purchase_request_id'
    ")->fetch_assoc();

    if(!$item){
        continue;
    }

    if ($qty_received > (int) $item['quantity']) {
        continue;
    }

    $received_item_count++;

    $description = $conn->real_escape_string($item['description']);
    $item_code = $conn->real_escape_string($item['item_code']);
    $brand = $conn->real_escape_string($item['brand']);
    $supplier = $conn->real_escape_string($item['supplier']);
    $unit = $conn->real_escape_string($item['unit']);
    $unit_price = floatval($item['unit_price']);

    $conn->query("
    INSERT INTO purchase_receiving_logs
    (
        purchase_request_id,
        item_id,
        received_by,
        quantity_received,
        supplier,
        remarks
    )
    VALUES
    (
        '$purchase_request_id',
        '$purchase_item_id',
        '$received_by',
        '$qty_received',
        '$supplier',
        '$remark'
    )
    ");

    $inventory = $conn->query("
    SELECT *
    FROM inventory_items
    WHERE item_code = '$item_code'
    LIMIT 1
    ");

    if($inventory && $inventory->num_rows > 0){

        $inventory_item = $inventory->fetch_assoc();
        $inventory_id = $inventory_item['id'];

        $conn->query("
        UPDATE inventory_items
        SET
            quantity = quantity + '$qty_received',
            unit_price = '$unit_price',
            supplier = '$supplier',
            item_condition = '$condition'
        WHERE id = '$inventory_id'
        ");

    }else{

        $conn->query("
        INSERT INTO inventory_items
        (
            item_code,
            item_name,
            brand,
            category,
            unit,
            quantity,
            minimum_stock,
            storage_location,
            unit_price,
            supplier,
            description,
            item_type,
            item_condition,
            asset_status,
            created_by
        )
        VALUES
        (
            '$item_code',
            '$description',
            '$brand',
            'Consumables',
            '$unit',
            '$qty_received',
            5,
            'Receiving Area',
            '$unit_price',
            '$supplier',
            '$description',
            'Consumable',
            '$condition',
            'Available',
            '$received_by'
        )
        ");

        $inventory_id = $conn->insert_id;
    }

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
        'Purchase Receiving',
        '$purchase_request_id',
        'Stock In',
        '$qty_received',
        'Received from purchase request $purchase_no',
        '$received_by'
    )
    ");

    if(!empty($item['material_request_item_id'])){

        $material_request_item_id = $item['material_request_item_id'];

        $conn->query("
        UPDATE material_request_items
        SET
            inventory_id = '$inventory_id',
            item_status = 'For Issue'
        WHERE id = '$material_request_item_id'
        ");
    }
}

if ($received_item_count === 0) {
    $conn->rollback();
    exit('No valid items were received.');
}

/*
UPDATE PURCHASE REQUEST
*/

$conn->query("
UPDATE purchase_requests
SET status = 'Received'
WHERE id = '$purchase_request_id'
");

/*
UPDATE MATERIAL REQUEST STATUS
Received purchased items are now For Issue, not Completed.
*/

if(!empty($material_request_id)){

    $summary = $conn->query("
    SELECT
        COUNT(*) AS total_items,
        SUM(CASE WHEN item_status = 'Issued' THEN 1 ELSE 0 END) AS issued_count,
        SUM(CASE WHEN item_status = 'For Issue' THEN 1 ELSE 0 END) AS for_issue_count,
        SUM(CASE WHEN item_status = 'To Purchase' THEN 1 ELSE 0 END) AS to_purchase_count,
        SUM(CASE WHEN item_status = 'Purchased' THEN 1 ELSE 0 END) AS purchased_count,
        SUM(CASE WHEN item_status = 'Waiting Delivery' THEN 1 ELSE 0 END) AS waiting_delivery_count
    FROM material_request_items
    WHERE request_id = '$material_request_id'
    ")->fetch_assoc();

    $total_items = intval($summary['total_items']);
    $issued_count = intval($summary['issued_count']);
    $for_issue_count = intval($summary['for_issue_count']);
    $to_purchase_count = intval($summary['to_purchase_count']);
    $purchased_count = intval($summary['purchased_count']);
    $waiting_delivery_count = intval($summary['waiting_delivery_count']);

    if($total_items > 0 && $issued_count == $total_items){

        $new_mr_status = 'Completed';

    }elseif($for_issue_count > 0 && $issued_count > 0){

        $new_mr_status = 'Partially Released';

    }elseif($for_issue_count > 0){

        $new_mr_status = 'Approved - For Issue';

    }elseif($to_purchase_count > 0){

        $new_mr_status = 'Pending Purchase';

    }elseif($purchased_count > 0 || $waiting_delivery_count > 0){

        $new_mr_status = 'Waiting Delivery';

    }else{

        $new_mr_status = 'Partially Received';
    }

    $conn->query("
    UPDATE material_requests
    SET status = '$new_mr_status'
    WHERE id = '$material_request_id'
    ");
}

/*
LOG
*/

$conn->query("
INSERT INTO purchase_approval_logs
(
    purchase_request_id,
    user_id,
    action,
    remarks
)
VALUES
(
    '$purchase_request_id',
    '$received_by',
    'Items Received',
    'Purchase items received and added to inventory. Material request items are now ready for issue.'
)
");

/*
NOTIFICATIONS
*/

createNotification(
    $conn,
    $requested_by,
    'Purchase Items Received',
    'Items from purchase request ' . $purchase_no . ' have been received and are ready for issue.',
    '../purchase_requests/view_purchase.php?id=' . $purchase_request_id
);

$boss_users = $conn->query("
SELECT id
FROM users
WHERE system_role = 'Boss'
AND status = 'Active'
");

while($boss = $boss_users->fetch_assoc()){

    createNotification(
        $conn,
        $boss['id'],
        'Purchase Received',
        'Purchase request ' . $purchase_no . ' has been received.',
        '../purchase_requests/view_purchase.php?id=' . $purchase_request_id
    );
}

$conn->commit();

if(!empty($material_request_id)){

    echo "
    <script>
    alert('Items Received Successfully. Items are now ready for issue.');
    window.location='../material_requests/view_request.php?id=$material_request_id';
    </script>
    ";

}else{

    echo "
    <script>
    alert('Items Received Successfully. Items have been added to inventory.');
    window.location='view_purchase.php?id=$purchase_request_id';
    </script>
    ";

}

?>

?>
