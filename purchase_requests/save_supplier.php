<?php

include '../auth/auth_check.php';

require_role([
    'Purchasing',
    'Admin'
]);

include '../config/database.php';

$supplier_code = $conn->real_escape_string($_POST['supplier_code']);
$supplier_name = $conn->real_escape_string($_POST['supplier_name']);
$contact_person = $conn->real_escape_string($_POST['contact_person'] ?? '');
$mobile_number = $conn->real_escape_string($_POST['mobile_number'] ?? '');
$telephone_number = $conn->real_escape_string($_POST['telephone_number'] ?? '');
$email = $conn->real_escape_string($_POST['email'] ?? '');
$address = $conn->real_escape_string($_POST['address'] ?? '');
$products_supplied = $conn->real_escape_string($_POST['products_supplied'] ?? '');
$status = $conn->real_escape_string($_POST['status'] ?? 'Active');

$created_by = $_SESSION['user_id'];

/*
CHECK DUPLICATE SUPPLIER CODE
*/

$check_code = $conn->query("
SELECT id
FROM suppliers
WHERE supplier_code = '$supplier_code'
LIMIT 1
");

if($check_code && $check_code->num_rows > 0){

    echo "
    <script>
        alert('Supplier code already exists.');
        window.history.back();
    </script>
    ";

    exit();
}

/*
CHECK DUPLICATE SUPPLIER NAME
*/

$check_name = $conn->query("
SELECT id
FROM suppliers
WHERE supplier_name = '$supplier_name'
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
INSERT SUPPLIER
*/

$conn->query("
INSERT INTO suppliers
(
    supplier_code,
    supplier_name,
    contact_person,
    mobile_number,
    telephone_number,
    email,
    address,
    products_supplied,
    status,
    created_by
)
VALUES
(
    '$supplier_code',
    '$supplier_name',
    '$contact_person',
    '$mobile_number',
    '$telephone_number',
    '$email',
    '$address',
    '$products_supplied',
    '$status',
    '$created_by'
)
");

if($conn->error){

    die($conn->error);
}

echo "
<script>
    alert('Supplier Added Successfully');
    window.location='suppliers.php';
</script>
";

?>