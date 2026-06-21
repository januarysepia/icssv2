<?php

include '../auth/auth_check.php';
require_role(['Purchasing', 'Admin']);
include '../config/database.php';

header('Content-Type: application/json; charset=utf-8');

$search = trim($_GET['search'] ?? '');
if (mb_strlen($search) < 2) {
    echo json_encode([]);
    exit();
}

$term = '%' . $search . '%';
$stmt = $conn->prepare("
    SELECT id, item_code, item_name, brand, category, unit, quantity, unit_price
    FROM inventory_items
    WHERE item_code LIKE ? OR item_name LIKE ? OR brand LIKE ? OR category LIKE ?
    ORDER BY item_name, brand
    LIMIT 20
");
$stmt->bind_param('ssss', $term, $term, $term, $term);
$stmt->execute();
$result = $stmt->get_result();
$rows = [];

while ($item = $result->fetch_assoc()) {
    $supplier_stmt = $conn->prepare("
        SELECT s.id, s.supplier_name, isp.unit_price, isp.is_preferred, isp.remarks, isp.updated_at
        FROM item_suppliers isp
        INNER JOIN suppliers s ON s.id = isp.supplier_id
        WHERE isp.inventory_id = ? AND s.status = 'Active'
        ORDER BY isp.unit_price, isp.is_preferred DESC, s.supplier_name
    ");
    $supplier_stmt->bind_param('i', $item['id']);
    $supplier_stmt->execute();
    $supplier_result = $supplier_stmt->get_result();
    $supplier_offers = [];

    while ($supplier = $supplier_result->fetch_assoc()) {
        $supplier_offers[] = [
            'supplier_id' => (int) $supplier['id'],
            'supplier_name' => $supplier['supplier_name'],
            'unit_price' => (float) $supplier['unit_price'],
            'is_preferred' => (bool) $supplier['is_preferred'],
            'remarks' => $supplier['remarks'],
            'updated_at' => $supplier['updated_at'],
        ];
    }

    $rows[] = [
        'inventory_id' => (int) $item['id'],
        'item_code' => $item['item_code'],
        'item_name' => $item['item_name'],
        'brand' => $item['brand'],
        'category' => $item['category'],
        'unit' => $item['unit'],
        'quantity' => (int) $item['quantity'],
        'unit_price' => (float) $item['unit_price'],
        'suppliers' => $supplier_offers,
    ];
}

echo json_encode($rows, JSON_UNESCAPED_UNICODE);
