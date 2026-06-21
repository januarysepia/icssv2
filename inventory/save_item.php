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

$item_code = $conn->real_escape_string($_POST['item_code'] ?? '');
$item_name = $conn->real_escape_string($_POST['item_name'] ?? '');
$brand = $conn->real_escape_string($_POST['brand'] ?? '');
$model = $conn->real_escape_string($_POST['model'] ?? '');
$serial_number = $conn->real_escape_string($_POST['serial_number'] ?? '');
$serial_numbers_text = trim($_POST['serial_numbers'] ?? '');

$item_type = $conn->real_escape_string($_POST['item_type'] ?? '');
$asset_usage = $conn->real_escape_string($_POST['asset_usage'] ?? '');
$asset_status = $conn->real_escape_string($_POST['asset_status'] ?? 'Available');

$category = $conn->real_escape_string($_POST['category'] ?? '');
$unit = $conn->real_escape_string($_POST['unit'] ?? '');
$quantity = intval($_POST['quantity'] ?? 0);
$minimum_stock = intval($_POST['minimum_stock'] ?? 0);
$storage_location = $conn->real_escape_string($_POST['storage_location'] ?? '');
$unit_price = floatval($_POST['unit_price'] ?? 0);
$supplier = $conn->real_escape_string($_POST['supplier'] ?? '');
$item_condition = $conn->real_escape_string($_POST['item_condition'] ?? 'Good');
$description = $conn->real_escape_string($_POST['description'] ?? '');

$created_by = $_SESSION['user_id'];

/*
VALIDATION
*/

if(empty($item_code) || empty($item_name) || !in_array($item_type, ['Consumable', 'Asset'], true)){

    echo "
    <script>
        alert('Item code, item name, and item type are required.');
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

if ($item_type === 'Asset' && $quantity <= 0) {
    exit('Asset quantity must be at least 1.');
}

if($item_type == 'Consumable'){
    $asset_usage = NULL;
    $asset_status = 'Available';
}

/*
CHECK DUPLICATE ITEM CODE
*/

$check = $conn->query("
SELECT id
FROM inventory_items
WHERE item_code = '$item_code'
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
INSERT INVENTORY ITEM
*/

$conn->begin_transaction();

if($asset_usage === NULL){

    $conn->query("
    INSERT INTO inventory_items
    (
        item_code,
        item_name,
        brand,
        model,
        serial_number,
        item_type,
        asset_usage,
        category,
        unit,
        quantity,
        minimum_stock,
        storage_location,
        unit_price,
        supplier,
        description,
        item_condition,
        asset_status,
        created_by
    )
    VALUES
    (
        '$item_code',
        '$item_name',
        '$brand',
        '$model',
        '$serial_number',
        '$item_type',
        NULL,
        '$category',
        '$unit',
        '$quantity',
        '$minimum_stock',
        '$storage_location',
        '$unit_price',
        '$supplier',
        '$description',
        '$item_condition',
        '$asset_status',
        '$created_by'
    )
    ");

}else{

    $conn->query("
    INSERT INTO inventory_items
    (
        item_code,
        item_name,
        brand,
        model,
        serial_number,
        item_type,
        asset_usage,
        category,
        unit,
        quantity,
        minimum_stock,
        storage_location,
        unit_price,
        supplier,
        description,
        item_condition,
        asset_status,
        created_by
    )
    VALUES
    (
        '$item_code',
        '$item_name',
        '$brand',
        '$model',
        '$serial_number',
        '$item_type',
        '$asset_usage',
        '$category',
        '$unit',
        '$quantity',
        '$minimum_stock',
        '$storage_location',
        '$unit_price',
        '$supplier',
        '$description',
        '$item_condition',
        '$asset_status',
        '$created_by'
    )
    ");
}

if($conn->error){
    $conn->rollback();
    die($conn->error);
}

$inventory_id = $conn->insert_id;

if ($item_type === 'Asset') {
    $serial_lines = preg_split('/\R/', $serial_numbers_text);
    $serials = [];

    foreach ($serial_lines as $line) {
        $line = trim($line);
        if ($line !== '') {
            $serials[] = $line;
        }
    }

    if (count($serials) > $quantity) {
        $conn->rollback();
        exit('The number of serial numbers cannot exceed the asset quantity.');
    }

    if (count($serials) !== count(array_unique($serials))) {
        $conn->rollback();
        exit('Duplicate serial numbers were entered.');
    }

    $unitInsert = $conn->prepare("
        INSERT INTO asset_units
        (inventory_id, asset_code, serial_number, unit_status, condition_status, storage_location)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    for ($i = 1; $i <= $quantity; $i++) {
        $asset_code = $item_code . '-' . str_pad((string) $i, 3, '0', STR_PAD_LEFT);
        $unit_serial = $serials[$i - 1] ?? null;
        $unit_status = $asset_status;

        $unitInsert->bind_param(
            'isssss',
            $inventory_id,
            $asset_code,
            $unit_serial,
            $unit_status,
            $item_condition,
            $storage_location
        );

        if (!$unitInsert->execute()) {
            $conn->rollback();
            exit('Unable to create individual asset units. Check duplicate asset codes or serial numbers.');
        }
    }
}

/*
SAVE INVENTORY LOG
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
    '$inventory_id',
    'Inventory Creation',
    '$inventory_id',
    'Stock In',
    '$quantity',
    'Initial inventory item creation',
    '$created_by'
)
");

if($conn->error){
    $conn->rollback();
    die($conn->error);
}

$conn->commit();

/*
SUCCESS
*/

echo "
<script>
    alert('Inventory Item Saved Successfully');
    window.location='inventory_list.php';
</script>
";

?>
