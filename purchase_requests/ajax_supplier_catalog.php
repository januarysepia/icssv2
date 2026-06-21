<?php

include '../auth/auth_check.php';
require_role(['Purchasing', 'Admin', 'Boss']);
include '../config/database.php';

header('Content-Type: application/json; charset=utf-8');

$supplier_id = max(0, (int) ($_GET['supplier_id'] ?? 0));
$inventory_id = max(0, (int) ($_GET['inventory_id'] ?? 0));
$search = trim($_GET['search'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$per_page = 10;

$conditions = [];
$params = [];
$types = '';

if ($supplier_id > 0) {
    $conditions[] = 'isp.supplier_id = ?';
    $types .= 'i';
    $params[] = $supplier_id;
}
if ($inventory_id > 0) {
    $conditions[] = 'isp.inventory_id = ?';
    $types .= 'i';
    $params[] = $inventory_id;
}
if ($search !== '') {
    $conditions[] = '(ii.item_code LIKE ? OR ii.item_name LIKE ? OR ii.brand LIKE ? OR s.supplier_name LIKE ? OR isp.supplier_item_code LIKE ?)';
    $types .= 'sssss';
    $term = '%' . $search . '%';
    array_push($params, $term, $term, $term, $term, $term);
}

$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
$count_stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM item_suppliers isp
    INNER JOIN inventory_items ii ON ii.id = isp.inventory_id
    INNER JOIN suppliers s ON s.id = isp.supplier_id
    $where
");
if ($params) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total = (int) ($count_stmt->get_result()->fetch_assoc()['total'] ?? 0);
$total_pages = max(1, (int) ceil($total / $per_page));
$page = min($page, $total_pages);
$offset = ($page - 1) * $per_page;

$stmt = $conn->prepare("
    SELECT
        isp.*,
        ii.item_code,
        ii.item_name,
        ii.brand,
        ii.unit,
        s.supplier_code,
        s.supplier_name,
        ranked.lowest_price,
        ranked.highest_price,
        ranked.supplier_count
    FROM item_suppliers isp
    INNER JOIN inventory_items ii ON ii.id = isp.inventory_id
    INNER JOIN suppliers s ON s.id = isp.supplier_id
    INNER JOIN (
        SELECT inventory_id, MIN(unit_price) AS lowest_price,
               MAX(unit_price) AS highest_price, COUNT(*) AS supplier_count
        FROM item_suppliers
        GROUP BY inventory_id
    ) ranked ON ranked.inventory_id = isp.inventory_id
    $where
    ORDER BY ii.item_name, isp.is_preferred DESC, isp.unit_price, s.supplier_name
    LIMIT $per_page OFFSET $offset
");
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$catalog = $stmt->get_result();
$count = $catalog->num_rows;
$can_edit = in_array($_SESSION['system_role'], ['Purchasing', 'Admin'], true);

ob_start();
if ($count > 0):
    while ($row = $catalog->fetch_assoc()):
        $is_lowest = (float) $row['unit_price'] === (float) $row['lowest_price'];
        $is_highest = (float) $row['unit_price'] === (float) $row['highest_price']
            && (int) $row['supplier_count'] > 1;
?>
    <tr>
        <td><strong><?= h($row['item_name']) ?></strong><br><small><?= h($row['item_code'] ?: '-') ?></small></td>
        <td><?= h($row['brand'] ?: '-') ?></td>
        <td><?= h($row['supplier_name']) ?></td>
        <td><?= h($row['supplier_item_code'] ?: '-') ?></td>
        <td><?= h($row['unit'] ?: '-') ?></td>
        <td class="<?= $is_lowest ? 'price-low' : ($is_highest ? 'price-high' : '') ?>">
            ₱<?= number_format((float) $row['unit_price'], 2) ?>
        </td>
        <td>
            <?php if ((int) $row['supplier_count'] === 1): ?>
                <span class="badge bg-secondary">Only supplier</span>
            <?php elseif ($is_lowest): ?>
                <span class="badge bg-success">Lowest</span>
            <?php elseif ($is_highest): ?>
                <span class="badge bg-danger">Highest</span>
            <?php else: ?>
                <span class="badge bg-warning text-dark">Mid-range</span>
            <?php endif; ?>
        </td>
        <td><?= (int) $row['is_preferred'] ? '<span class="badge bg-primary">Preferred</span>' : '-' ?></td>
        <td><?= h($row['last_purchased_at'] ?: '-') ?></td>
        <td><?= h($row['updated_at']) ?></td>
        <td class="action-cell">
            <a href="supplier_price_history.php?inventory_id=<?= (int) $row['inventory_id'] ?>"
               class="btn btn-outline-info btn-sm">History</a>
            <?php if ($can_edit): ?>
                <a href="supplier_item_form.php?id=<?= (int) $row['id'] ?>"
                   class="btn btn-outline-primary btn-sm">Edit</a>
            <?php endif; ?>
        </td>
    </tr>
<?php
    endwhile;
else:
?>
    <tr><td colspan="11" class="text-center text-muted py-4">No supplier items found.</td></tr>
<?php
endif;
$html = ob_get_clean();

echo json_encode([
    'html' => $html,
    'count' => $count,
    'total' => $total,
    'page' => $page,
    'per_page' => $per_page,
    'total_pages' => $total_pages,
], JSON_UNESCAPED_UNICODE);
