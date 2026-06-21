<?php

include '../auth/auth_check.php';

require_role([
    'Boss',
    'Admin',
    'Purchasing'
]);

include '../config/database.php';

require_post();
verify_csrf();

/*
GET FORM DATA
*/

$id = intval($_POST['id'] ?? 0);

$item_code = $conn->real_escape_string($_POST['item_code'] ?? '');
$item_name = $conn->real_escape_string($_POST['item_name'] ?? '');
$brand = $conn->real_escape_string($_POST['brand'] ?? '');
$model = $conn->real_escape_string($_POST['model'] ?? '');
$serial_number = $conn->real_escape_string($_POST['serial_number'] ?? '');

$item_type = $conn->real_escape_string($_POST['item_type'] ?? '');
$asset_usage = $conn->real_escape_string($_POST['asset_usage'] ?? '');
$asset_status = $conn->real_escape_string($_POST['asset_status'] ?? 'Available');

$category = $conn->real_escape_string($_POST['category'] ?? '');
$unit = $conn->real_escape_string($_POST['unit'] ?? '');
$new_quantity = intval($_POST['quantity'] ?? 0);
$minimum_stock = intval($_POST['minimum_stock'] ?? 0);
$storage_location = $conn->real_escape_string($_POST['storage_location'] ?? '');
$unit_price = floatval($_POST['unit_price'] ?? 0);
$supplier = $conn->real_escape_string($_POST['supplier'] ?? '');
$item_condition = $conn->real_escape_string($_POST['item_condition'] ?? 'Good');
$description = $conn->real_escape_string($_POST['description'] ?? '');

$updated_by = $_SESSION['user_id'];

/*
VALIDATION
*/

if($id <= 0 || empty($item_code) || empty($item_name) || !in_array($item_type, ['Consumable', 'Asset'], true)){

    echo "
    <script>
        alert('Invalid inventory item data.');
        window.history.back();
    </script>
    ";

    exit();
}

if($item_type == 'Asset' && empty($asset_usage)){

    echo "
    <script>
        alert('Asset usage is required for asset items.');
        window.history.back();
    </script>
    ";

    exit();
}

if ($item_type === 'Asset' && !in_array($asset_usage, ['Borrowable', 'Assigned', 'Both'], true)) {
    exit('Invalid asset usage.');
}

if($item_type == 'Consumable'){
    $asset_usage = NULL;
    $asset_status = 'Available';
}

/*
GET OLD ITEM
*/

$old_item = $conn->query("
SELECT *
FROM inventory_items
WHERE id = '$id'
")->fetch_assoc();

if(!$old_item){

    echo "
    <script>
        alert('Inventory item not found.');
        window.location='inventory_list.php';
    </script>
    ";

    exit();
}

/*
PREVENT CHANGING ASSIGNED / BORROWED ASSET BACK TO CONSUMABLE
*/

if(
    ($old_item['asset_status'] == 'Assigned' || $old_item['asset_status'] == 'Borrowed')
    &&
    $item_type == 'Consumable'
){

    echo "
    <script>
        alert('This item is currently assigned or borrowed. It cannot be changed to Consumable.');
        window.history.back();
    </script>
    ";

    exit();
}

/*
CHECK DUPLICATE ITEM CODE
*/

$check = $conn->query("
SELECT id
FROM inventory_items
WHERE item_code = '$item_code'
AND id != '$id'
");

if($check && $check->num_rows > 0){

    echo "
    <script>
        alert('Item code already exists.');
        window.history.back();
    </script>
    ";

    exit();
}

/*
UPDATE ITEM
*/

if($asset_usage === NULL){

    $conn->query("
    UPDATE inventory_items
    SET
        item_code = '$item_code',
        item_name = '$item_name',
        brand = '$brand',
        model = '$model',
        serial_number = '$serial_number',
        item_type = '$item_type',
        asset_usage = NULL,
        asset_status = '$asset_status',
        category = '$category',
        unit = '$unit',
        quantity = '$new_quantity',
        minimum_stock = '$minimum_stock',
        storage_location = '$storage_location',
        unit_price = '$unit_price',
        supplier = '$supplier',
        item_condition = '$item_condition',
        description = '$description'
    WHERE id = '$id'
    ");

}else{

    $conn->query("
    UPDATE inventory_items
    SET
        item_code = '$item_code',
        item_name = '$item_name',
        brand = '$brand',
        model = '$model',
        serial_number = '$serial_number',
        item_type = '$item_type',
        asset_usage = '$asset_usage',
        asset_status = '$asset_status',
        category = '$category',
        unit = '$unit',
        quantity = '$new_quantity',
        minimum_stock = '$minimum_stock',
        storage_location = '$storage_location',
        unit_price = '$unit_price',
        supplier = '$supplier',
        item_condition = '$item_condition',
        description = '$description'
    WHERE id = '$id'
    ");
}

if($conn->error){
    die($conn->error);
}

/*
CHECK STOCK CHANGES
*/

$old_quantity = intval($old_item['quantity']);

if($new_quantity != $old_quantity){

    $movement_type = 'Adjustment';

    $difference = $new_quantity - $old_quantity;

    if($difference > 0){

        $movement_type = 'Stock In';

    }elseif($difference < 0){

        $movement_type = 'Stock Out';

        $difference = abs($difference);
    }

    $remarks = "Inventory quantity updated manually";

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
        '$id',
        'Inventory Adjustment',
        '$id',
        '$movement_type',
        '$difference',
        '$remarks',
        '$updated_by'
    )
    ");

    if($conn->error){
        die($conn->error);
    }
}

/*
SAVE ITEM UPDATE LOG
*/

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
    '$id',
    'Inventory Update',
    '$id',
    'Update',
    0,
    'Inventory item details updated',
    '$updated_by'
)
");

if($conn->error){
    die($conn->error);
}

/*
SUCCESS
*/

echo "
<script>
    alert('Inventory Item Updated Successfully');
    window.location='inventory_list.php';
</script>
";

?>
