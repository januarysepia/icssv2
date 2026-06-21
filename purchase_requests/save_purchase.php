<?php

include '../auth/auth_check.php';

require_role([
    'Purchasing',
    'Admin'
]);

include '../config/database.php';
include '../includes/create_notification.php';
include '../includes/purchase_approval_email.php';

require_post();
verify_csrf();

$requested_by = $_SESSION['user_id'];
$purchase_type = $_POST['purchase_type'] ?? 'manual';
$remarks = $conn->real_escape_string($_POST['remarks'] ?? '');

if (!in_array($purchase_type, ['manual', 'from_mr'], true)) {
    exit('Invalid purchase type.');
}

$conn->begin_transaction();

$material_request_id = NULL;

if($purchase_type == 'from_mr'){
    $material_request_id = intval($_POST['material_request_id']);
}

/*
VALIDATION FOR MATERIAL REQUEST ITEMS
*/

if($purchase_type == 'from_mr'){

    if(
        !isset($_POST['selected_items'])
        ||
        count($_POST['selected_items']) == 0
    ){
        echo "
        <script>
            alert('Please select at least one item.');
            window.history.back();
        </script>
        ";
        exit();
    }
}

$purchase_no = "PR-" . date("YmdHis") . "-" . strtoupper(bin2hex(random_bytes(2)));

/*
CREATE PURCHASE REQUEST HEADER
*/

if($material_request_id === NULL){

    $conn->query("
    INSERT INTO purchase_requests
    (
        purchase_no,
        material_request_id,
        requested_by,
        status,
        remarks
    )
    VALUES
    (
        '$purchase_no',
        NULL,
        '$requested_by',
        'For Boss Approval',
        '$remarks'
    )
    ");

}else{

    $conn->query("
    INSERT INTO purchase_requests
    (
        purchase_no,
        material_request_id,
        requested_by,
        status,
        remarks
    )
    VALUES
    (
        '$purchase_no',
        '$material_request_id',
        '$requested_by',
        'For Boss Approval',
        '$remarks'
    )
    ");
}

$purchase_request_id = $conn->insert_id;
$inserted_items = 0;

function syncSupplierCatalogPrice(
    mysqli $conn,
    int $inventory_id,
    int $supplier_id,
    float $unit_price,
    int $purchase_request_id,
    int $user_id,
    string $purchase_no
): void {
    if ($inventory_id <= 0 || $supplier_id <= 0 || $unit_price <= 0) {
        return;
    }

    $upsert = $conn->prepare("
        INSERT INTO item_suppliers
            (inventory_id, supplier_id, unit_price, last_purchased_at, created_by)
        VALUES (?, ?, ?, NOW(), ?)
        ON DUPLICATE KEY UPDATE
            id = LAST_INSERT_ID(id),
            unit_price = VALUES(unit_price),
            last_purchased_at = NOW()
    ");
    $upsert->bind_param('iidi', $inventory_id, $supplier_id, $unit_price, $user_id);
    $upsert->execute();
    if ($upsert->errno) {
        throw new RuntimeException('Unable to update supplier item catalog.');
    }

    $item_supplier_id = (int) $conn->insert_id;
    $remarks = 'Price used in ' . $purchase_no;
    $history = $conn->prepare("
        INSERT INTO supplier_price_history
            (item_supplier_id, inventory_id, supplier_id, unit_price, source_type, source_id, remarks, recorded_by)
        VALUES (?, ?, ?, ?, 'Purchase Request', ?, ?, ?)
    ");
    $history->bind_param(
        'iiidisi',
        $item_supplier_id,
        $inventory_id,
        $supplier_id,
        $unit_price,
        $purchase_request_id,
        $remarks,
        $user_id
    );
    $history->execute();
    if ($history->errno) {
        throw new RuntimeException('Unable to save supplier price history.');
    }
}

/*
FROM MATERIAL REQUEST
*/

if($purchase_type == 'from_mr'){

    foreach($_POST['selected_items'] as $item_id){

        $item = $conn->query("
        SELECT *
        FROM material_request_items
        WHERE id = '$item_id'
        AND request_id = '$material_request_id'
        AND item_status = 'To Purchase'
        ")->fetch_assoc();

        if(!$item){
            continue;
        }


        $description = $conn->real_escape_string($item['description']);
        $item_code = $conn->real_escape_string($item['item_code']);
        $brand = $conn->real_escape_string($item['brand']);

        $supplier = $conn->real_escape_string(
            $_POST['supplier_' . $item_id] ?? $item['supplier']
        );

        $registered_supplier = $conn->query("
            SELECT id, supplier_name
            FROM suppliers
            WHERE supplier_name = '$supplier'
              AND status = 'Active'
            LIMIT 1
        ")->fetch_assoc();

        if (!$registered_supplier) {
            $conn->rollback();
            exit('All suppliers from a material request must be registered in the Supplier Database.');
        }

        $supplier = $conn->real_escape_string($registered_supplier['supplier_name']);
        $supplier_id = (int) $registered_supplier['id'];

        $unit = $conn->real_escape_string($item['unit']);

        $quantity = intval(
            $_POST['quantity_' . $item_id] ?? $item['quantity']
        );

        $unit_price = floatval(
            $_POST['unit_price_' . $item_id] ?? $item['unit_price']
        );

        if($quantity <= 0){
            continue;
        }

        $conn->query("
        INSERT INTO purchase_request_items
        (
            purchase_request_id,
            material_request_item_id,
            description,
            item_code,
            brand,
            supplier,
            unit,
            quantity,
            unit_price
        )
        VALUES
        (
            '$purchase_request_id',
            '$item_id',
            '$description',
            '$item_code',
            '$brand',
            '$supplier',
            '$unit',
            '$quantity',
            '$unit_price'
        )
        ");
        $inserted_items++;

        syncSupplierCatalogPrice(
            $conn,
            (int) ($item['inventory_id'] ?? 0),
            $supplier_id,
            $unit_price,
            $purchase_request_id,
            $requested_by,
            $purchase_no
        );

        /*
        MARK MATERIAL REQUEST ITEM AS PURCHASED REQUEST CREATED
        */

        $conn->query("
        UPDATE material_request_items
        SET
        item_status = 'Purchased',
        supplier_id = '$supplier_id',
        supplier = '$supplier',
        supplier_name = '$supplier',
        unit_price = '$unit_price'
        WHERE id = '$item_id'
        ");
    }

    /*
    UPDATE MATERIAL REQUEST STATUS
    */

    $conn->query("
    UPDATE material_requests
    SET status = 'Purchase Request Created'
    WHERE id = '$material_request_id'
    ");
}

/*
MANUAL PURCHASE
*/

if($purchase_type == 'manual'){

    $descriptions = $_POST['description'] ?? [];
    $item_codes = $_POST['item_code'] ?? [];
    $brands = $_POST['brand'] ?? [];
    $suppliers = $_POST['supplier'] ?? [];
    $inventory_ids = $_POST['inventory_id'] ?? [];
    $units = $_POST['unit'] ?? [];
    $quantities = $_POST['quantity'] ?? [];
    $unit_prices = $_POST['unit_price'] ?? [];

    for($i = 0; $i < count($descriptions); $i++){

        $description = trim($conn->real_escape_string($descriptions[$i] ?? ''));

        if(empty($description)){
            continue;
        }

        $item_code = $conn->real_escape_string($item_codes[$i] ?? '');
        $brand = $conn->real_escape_string($brands[$i] ?? '');
        $supplier = $conn->real_escape_string($suppliers[$i] ?? '');
        $inventory_id = intval($inventory_ids[$i] ?? 0);
        $unit = $conn->real_escape_string($units[$i] ?? '');
        $quantity = intval($quantities[$i] ?? 0);
        $unit_price = floatval($unit_prices[$i] ?? 0);

        if($quantity <= 0){
            continue;
        }

        $conn->query("
        INSERT INTO purchase_request_items
        (
            purchase_request_id,
            material_request_item_id,
            description,
            item_code,
            brand,
            supplier,
            unit,
            quantity,
            unit_price
        )
        VALUES
        (
            '$purchase_request_id',
            NULL,
            '$description',
            '$item_code',
            '$brand',
            '$supplier',
            '$unit',
            '$quantity',
            '$unit_price'
        )
        ");
        $inserted_items++;

        if ($inventory_id > 0 && $supplier !== '') {
            $supplier_record = $conn->query("
                SELECT id
                FROM suppliers
                WHERE supplier_name = '$supplier'
                  AND status = 'Active'
                LIMIT 1
            ")->fetch_assoc();

            if ($supplier_record) {
                syncSupplierCatalogPrice(
                    $conn,
                    $inventory_id,
                    (int) $supplier_record['id'],
                    $unit_price,
                    $purchase_request_id,
                    $requested_by,
                    $purchase_no
                );
            }
        }
    }
}

if ($inserted_items === 0) {
    $conn->rollback();
    exit('No valid purchase items were submitted.');
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
    '$requested_by',
    'Created Purchase Request',
    '$remarks'
)
");

/*
NOTIFY BOSS USERS
*/

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
        'Purchase Request For Approval',
        'Purchase request ' . $purchase_no . ' is waiting for your approval.',
        '../purchase_requests/view_purchase.php?id=' . $purchase_request_id
    );
}

$conn->commit();

$email_result = sendPurchaseApprovalEmail($conn, $purchase_request_id);
$email_message = $email_result['sent']
    ? ' Approval email sent to ' . $email_result['recipient'] . '.'
    : ' Purchase request was saved, but the approval email was not sent: ' . $email_result['error'];
$email_message = addslashes($email_message);

echo "
<script>
alert('Purchase Request Submitted for Boss Approval.$email_message');
window.location='view_purchase.php?id=$purchase_request_id';
</script>
";

?>
