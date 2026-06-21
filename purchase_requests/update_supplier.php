<?php

include '../auth/auth_check.php';

require_role([
    'Purchasing',
    'Admin'
]);

include '../config/database.php';

$id = intval($_POST['id']);

$supplier_name = $conn->real_escape_string($_POST['supplier_name']);
$contact_person = $conn->real_escape_string($_POST['contact_person'] ?? '');
$mobile_number = $conn->real_escape_string($_POST['mobile_number'] ?? '');
$telephone_number = $conn->real_escape_string($_POST['telephone_number'] ?? '');
$email = $conn->real_escape_string($_POST['email'] ?? '');
$address = $conn->real_escape_string($_POST['address'] ?? '');
$products_supplied = $conn->real_escape_string($_POST['products_supplied'] ?? '');
$status = $conn->real_escape_string($_POST['status'] ?? 'Active');

/*
CHECK SUPPLIER EXISTS
*/

$supplier = $conn->query("
SELECT *
FROM suppliers
WHERE id = '$id'
")->fetch_assoc();

if(!$supplier){
    die('Supplier not found.');
}

/*
CHECK DUPLICATE SUPPLIER NAME EXCEPT CURRENT RECORD
*/

$check_name = $conn->query("
SELECT id
FROM suppliers
WHERE supplier_name = '$supplier_name'
AND id != '$id'
LIMIT 1
");

if($check_name && $check_name->num_rows > 0){

    echo "
    <script>
        alert('Supplier name already exists.');
        window.history.back();
    </script>
    ";

    exit();
}

/*
UPDATE SUPPLIER
*/

$conn->query("
UPDATE suppliers
SET
    supplier_name = '$supplier_name',
    contact_person = '$contact_person',
    mobile_number = '$mobile_number',
    telephone_number = '$telephone_number',
    email = '$email',
    address = '$address',
    products_supplied = '$products_supplied',
    status = '$status',
    updated_at = NOW()
WHERE id = '$id'
");

if($conn->error){
    die($conn->error);
}

echo "
<script>
    alert('Supplier Updated Successfully');
    window.location='suppliers.php';
</script>
";

?>