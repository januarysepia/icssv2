<?php

include '../auth/auth_check.php';
require_role(['Purchasing', 'Admin']);
include '../config/database.php';

require_post();
verify_csrf();

$item_id = intval($_POST['item_id'] ?? 0);
$resolution = $_POST['resolution'] ?? '';
$resolved_by = intval($_SESSION['user_id']);

if (!in_array($resolution, ['existing', 'new'], true)) {
    exit('Invalid supplier resolution.');
}

$conn->begin_transaction();

$stmt = $conn->prepare("
    SELECT
        mri.*,
        mr.request_no,
        mr.status AS request_status
    FROM material_request_items mri
    INNER JOIN material_requests mr ON mr.id = mri.request_id
    WHERE mri.id = ?
    FOR UPDATE
");
$stmt->bind_param('i', $item_id);
$stmt->execute();
$item = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$item || $item['request_status'] !== 'Pending Review' || $item['supplier_review_status'] !== 'Pending') {
    $conn->rollback();
    exit('This supplier suggestion is no longer pending review.');
}

$supplier_id = 0;
$supplier_name = '';

if ($resolution === 'existing') {
    $supplier_id = intval($_POST['existing_supplier_id'] ?? 0);
    $supplier_stmt = $conn->prepare("
        SELECT id, supplier_name
        FROM suppliers
        WHERE id = ? AND status = 'Active'
    ");
    $supplier_stmt->bind_param('i', $supplier_id);
    $supplier_stmt->execute();
    $supplier = $supplier_stmt->get_result()->fetch_assoc();
    $supplier_stmt->close();

    if (!$supplier) {
        $conn->rollback();
        exit('Please select a valid existing supplier.');
    }
    $supplier_name = $supplier['supplier_name'];
} else {
    $supplier_name = trim($_POST['supplier_name'] ?? '');
    if ($supplier_name === '') {
        $conn->rollback();
        exit('Supplier name is required.');
    }

    $duplicate_stmt = $conn->prepare("
        SELECT id, supplier_name
        FROM suppliers
        WHERE LOWER(TRIM(supplier_name)) = LOWER(TRIM(?))
        LIMIT 1
    ");
    $duplicate_stmt->bind_param('s', $supplier_name);
    $duplicate_stmt->execute();
    $duplicate = $duplicate_stmt->get_result()->fetch_assoc();
    $duplicate_stmt->close();

    if ($duplicate) {
        $supplier_id = (int) $duplicate['id'];
        $supplier_name = $duplicate['supplier_name'];
    } else {
        $next = $conn->query("
            SELECT COALESCE(MAX(CAST(SUBSTRING(supplier_code, 5) AS UNSIGNED)), 0) + 1 AS next_no
            FROM suppliers
            WHERE supplier_code LIKE 'SUP-%'
            FOR UPDATE
        ")->fetch_assoc();
        $supplier_code = 'SUP-' . str_pad((string) ((int) $next['next_no']), 3, '0', STR_PAD_LEFT);

        $contact_person = trim($_POST['contact_person'] ?? '');
        $mobile_number = trim($_POST['mobile_number'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $products_supplied = trim($_POST['products_supplied'] ?? '');

        $insert = $conn->prepare("
            INSERT INTO suppliers
            (supplier_code, supplier_name, contact_person, mobile_number, email, address, products_supplied, status, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'Active', ?)
        ");
        $insert->bind_param(
            'sssssssi',
            $supplier_code,
            $supplier_name,
            $contact_person,
            $mobile_number,
            $email,
            $address,
            $products_supplied,
            $resolved_by
        );
        $insert->execute();
        $supplier_id = $conn->insert_id;
        $insert->close();
    }
}

$update = $conn->prepare("
    UPDATE material_request_items
    SET
        supplier_id = ?,
        supplier = ?,
        supplier_name = ?,
        supplier_review_status = 'Registered'
    WHERE id = ?
");
$update->bind_param('issi', $supplier_id, $supplier_name, $supplier_name, $item_id);
$update->execute();
$update->close();

$note = 'Suggested supplier "' . $item['suggested_supplier_name'] . '" resolved to "' . $supplier_name . '".';
$request_id = (int) $item['request_id'];
$log = $conn->prepare("
    INSERT INTO material_request_status_logs
    (request_id, updated_by, old_status, new_status, notes)
    VALUES (?, ?, 'Pending Supplier Review', 'Supplier Registered', ?)
");
$log->bind_param('iis', $request_id, $resolved_by, $note);
$log->execute();
$log->close();

$conn->commit();

echo "
<script>
alert('Supplier resolved successfully.');
window.location='view_request.php?id=" . (int) $item['request_id'] . "';
</script>
";
