<?php

include '../auth/auth_check.php';

require_role([
    'Boss',
    'Admin',
    'Technical'
]);

include '../config/database.php';
include '../includes/create_notification.php';

$jo_id = intval($_POST['jo_id']);
$requested_by = $_SESSION['user_id'];

if ($jo_id <= 0) {
    exit('Please select a valid job order.');
}

$jo_stmt = $conn->prepare("
    SELECT id, jo_no, workflow_status, overall_status
    FROM job_orders
    WHERE id = ?
");
$jo_stmt->bind_param('i', $jo_id);
$jo_stmt->execute();
$job_order = $jo_stmt->get_result()->fetch_assoc();
$jo_stmt->close();

if (!$job_order) {
    exit('Selected job order was not found.');
}

$is_completed_jo = $job_order['workflow_status'] === 'Completed'
    || $job_order['overall_status'] === 'Completed';
$request_context = $is_completed_jo
    ? 'After Delivery / Correction'
    : 'Ongoing JO';
$request_context_sql = $conn->real_escape_string($request_context);
$request_note = $is_completed_jo
    ? 'After-delivery / correction material request created by Technical for completed JO.'
    : 'Material request created by Technical for ongoing JO.';
$request_note_sql = $conn->real_escape_string($request_note);

$request_no = "MR-" . date("YmdHis");
$request_date = date("Y-m-d");

/*
CREATE MATERIAL REQUEST HEADER
*/

$conn->query("
INSERT INTO material_requests
(
    request_no,
    jo_id,
    requested_by,
    request_date,
    request_context,
    status,
    notes
)
VALUES
(
    '$request_no',
    '$jo_id',
    '$requested_by',
    '$request_date',
    '$request_context_sql',
    'Pending Review',
    '$request_note_sql'
)
");

$request_id = $conn->insert_id;

/*
GET FORM ITEMS
*/

$inventory_ids = $_POST['inventory_id'] ?? [];
$manual_items = $_POST['manual_item'] ?? [];
$descriptions = $_POST['description'] ?? [];
$item_codes = $_POST['item_code'] ?? [];
$brands = $_POST['brand'] ?? [];
$supplier_choices = $_POST['supplier_choice'] ?? [];
$supplier_others = $_POST['supplier_other'] ?? [];
$units = $_POST['unit'] ?? [];
$quantities = $_POST['quantity'] ?? [];
$unit_prices = $_POST['unit_price'] ?? [];

/*
SAVE REQUEST ITEMS
All items start as Pending Approval.
Purchasing/Supervisor approval will decide:
- For Issue
- To Purchase
- Cancelled
*/

for($i = 0; $i < count($quantities); $i++){

    $inventory_id = !empty($inventory_ids[$i])
        ? intval($inventory_ids[$i])
        : 'NULL';

    $description = trim($conn->real_escape_string($descriptions[$i] ?? ''));

    $manual_item = trim($conn->real_escape_string($manual_items[$i] ?? ''));

    if(empty($description) && !empty($manual_item)){
        $description = $manual_item;
    }

    if(empty($description)){
        continue;
    }

    $item_code = $conn->real_escape_string($item_codes[$i] ?? '');
    $brand = $conn->real_escape_string($brands[$i] ?? '');
    $supplier_choice = $supplier_choices[$i] ?? '';
    $supplier_id = 'NULL';
    $supplier = '';
    $suggested_supplier = '';
    $supplier_review_status = 'Registered';

    if ($supplier_choice === '__other__') {
        $suggested_supplier = trim($supplier_others[$i] ?? '');
        if ($suggested_supplier === '') {
            continue;
        }
        $supplier = $suggested_supplier;
        $supplier_review_status = 'Pending';
    } elseif (ctype_digit((string) $supplier_choice)) {
        $selected_supplier_id = intval($supplier_choice);
        $selected_supplier = $conn->query("
            SELECT id, supplier_name
            FROM suppliers
            WHERE id = '$selected_supplier_id'
              AND status = 'Active'
        ")->fetch_assoc();

        if (!$selected_supplier) {
            continue;
        }

        $supplier_id = (int) $selected_supplier['id'];
        $supplier = $selected_supplier['supplier_name'];
    }

    $supplier = $conn->real_escape_string($supplier);
    $suggested_supplier = $conn->real_escape_string($suggested_supplier);
    $unit = $conn->real_escape_string($units[$i] ?? '');
    $quantity = intval($quantities[$i] ?? 0);
    $unit_price = floatval($unit_prices[$i] ?? 0);

    if($quantity <= 0){
        continue;
    }

    $item_status = 'Pending Approval';

    $conn->query("
    INSERT INTO material_request_items
    (
        request_id,
        inventory_id,
        description,
        item_code,
        brand,
        supplier_id,
        supplier,
        unit,
        quantity,
        unit_price,
        supplier_name,
        suggested_supplier_name,
        supplier_review_status,
        item_status
    )
    VALUES
    (
        '$request_id',
        $inventory_id,
        '$description',
        '$item_code',
        '$brand',
        $supplier_id,
        '$supplier',
        '$unit',
        '$quantity',
        '$unit_price',
        '$supplier',
        " . ($suggested_supplier !== '' ? "'$suggested_supplier'" : "NULL") . ",
        '$supplier_review_status',
        '$item_status'
    )
    ");
}

/*
SAVE STATUS LOG
*/

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
    '$request_id',
    '$requested_by',
    'Created',
    'Pending Review',
    'Material request submitted by Technical. Items are pending approval.'
)
");

/*
NOTIFY PURCHASING USERS
*/

$purchasing_users = $conn->query("
SELECT id
FROM users
WHERE system_role = 'Purchasing'
AND status = 'Active'
");

while($user = $purchasing_users->fetch_assoc()){

    createNotification(
        $conn,
        $user['id'],
        'New Material Request',
        'A new material request ' . $request_no . ' has been submitted for review.',
        '../material_requests/view_request.php?id=' . $request_id
    );
}

echo "
<script>
alert('Material Request Submitted Successfully');
window.location='view_request.php?id=$request_id';
</script>
";

?>
