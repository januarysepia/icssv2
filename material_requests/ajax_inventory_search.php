<?php

include '../auth/auth_check.php';
require_role(['Boss', 'Admin', 'Technical']);
include '../config/database.php';

header('Content-Type: application/json; charset=utf-8');

$search = trim($_GET['q'] ?? '');
if (mb_strlen($search) < 1) {
    echo json_encode([]);
    exit;
}

$term = '%' . $search . '%';
$stmt = $conn->prepare("
    SELECT id, item_code, item_name, brand, category, unit, quantity, unit_price
    FROM inventory_items
    WHERE item_code LIKE ?
       OR item_name LIKE ?
       OR brand LIKE ?
       OR category LIKE ?
    ORDER BY item_name ASC, brand ASC
    LIMIT 20
");
$stmt->bind_param('ssss', $term, $term, $term, $term);
$stmt->execute();
$result = $stmt->get_result();

$rows = [];
while ($row = $result->fetch_assoc()) {
    $rows[] = [
        'id' => (int) $row['id'],
        'item_code' => $row['item_code'],
        'item_name' => $row['item_name'],
        'brand' => $row['brand'],
        'category' => $row['category'],
        'unit' => $row['unit'],
        'quantity' => (int) $row['quantity'],
        'unit_price' => (float) $row['unit_price'],
    ];
}

$stmt->close();
echo json_encode($rows, JSON_UNESCAPED_UNICODE);

