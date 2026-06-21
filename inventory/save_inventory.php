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

$item_code = trim($_POST['item_code'] ?? '');
$item_name = trim($_POST['item_name'] ?? '');
$category = $_POST['category'] ?? '';
$quantity = filter_var($_POST['quantity'] ?? null, FILTER_VALIDATE_INT);
$unit = trim($_POST['unit'] ?? '');

if (
    $item_code === '' ||
    $item_name === '' ||
    !in_array($category, ['Consumable', 'Borrowable'], true) ||
    $quantity === false ||
    $quantity < 0 ||
    $unit === ''
) {
    exit('Invalid inventory details.');
}

$stmt = $conn->prepare("
    INSERT INTO inventory_items (item_code, item_name, category, quantity, unit)
    VALUES (?, ?, ?, ?, ?)
");
$stmt->bind_param('sssis', $item_code, $item_name, $category, $quantity, $unit);

if (!$stmt->execute()) {
    http_response_code(500);
    exit('Unable to save inventory item.');
}

echo "
<script>

alert('Inventory Added Successfully');

window.location='inventory_list.php';

</script>
";
?>
