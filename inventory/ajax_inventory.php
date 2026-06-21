<?php

include '../auth/auth_check.php';

require_role([
    'Boss',
    'Admin',
    'Purchasing',
    'Supervisor'
]);

include '../config/database.php';

header('Content-Type: application/json; charset=utf-8');

$role = $_SESSION['system_role'] ?? $_SESSION['role'] ?? '';

$search = $conn->real_escape_string($_GET['search'] ?? '');
$type = $conn->real_escape_string($_GET['type'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$per_page = 10;

$where = "WHERE 1";

if($search != ''){
    $where .= "
    AND (
        item_code LIKE '%$search%'
        OR item_name LIKE '%$search%'
        OR brand LIKE '%$search%'
        OR category LIKE '%$search%'
        OR storage_location LIKE '%$search%'
    )
    ";
}

if($type != ''){
    $where .= " AND item_type = '$type' ";
}

$count_result = $conn->query("
    SELECT COUNT(*) AS total
    FROM inventory_items
    $where
");

if (!$count_result) {
    http_response_code(500);
    echo json_encode(['error' => 'Unable to count inventory items.']);
    exit();
}

$total = (int) ($count_result->fetch_assoc()['total'] ?? 0);
$total_pages = max(1, (int) ceil($total / $per_page));
$page = min($page, $total_pages);
$offset = ($page - 1) * $per_page;

$items = $conn->query("
SELECT *
FROM inventory_items
$where
ORDER BY id DESC
LIMIT $per_page OFFSET $offset
");

if(!$items){
    http_response_code(500);
    echo json_encode(['error' => 'Unable to load inventory items.']);
    exit();
}

ob_start();

if($items->num_rows == 0){
    echo "
    <tr>
        <td colspan='12' class='text-center text-muted'>
            No inventory items found.
        </td>
    </tr>
    ";
} else {
while($row = $items->fetch_assoc()){

    $item_type = $row['item_type'] ?? 'Consumable';
    $condition = $row['item_condition'] ?? 'Good';
    $asset_status = $row['asset_status'] ?? 'Available';

?>

<tr>

    <td><?php echo $row['id']; ?></td>

    <td><?php echo $row['item_code']; ?></td>

    <td><b><?php echo $row['item_name']; ?></b></td>

    <td>
        <?php if($item_type == 'Asset'){ ?>
            <span class="badge bg-primary">Asset</span>
        <?php }else{ ?>
            <span class="badge bg-success">Consumable</span>
        <?php } ?>
    </td>

    <td><?php echo $row['brand']; ?></td>

    <td>
        <?php echo !empty($row['category']) ? $row['category'] : 'Uncategorized'; ?>
    </td>

    <td><?php echo $row['quantity']; ?></td>

    <td>
        <?php if($condition == 'Good'){ ?>
            <span class="badge bg-success">Good</span>
        <?php }elseif($condition == 'Damaged'){ ?>
            <span class="badge bg-danger">Damaged</span>
        <?php }else{ ?>
            <span class="badge bg-secondary"><?php echo $condition; ?></span>
        <?php } ?>
    </td>

    <td>
        <?php if($asset_status == 'Available'){ ?>
            <span class="badge bg-success">Available</span>
        <?php }elseif($asset_status == 'Borrowed'){ ?>
            <span class="badge bg-warning text-dark">Borrowed</span>
        <?php }elseif($asset_status == 'Under Repair'){ ?>
            <span class="badge bg-info text-dark">Under Repair</span>
        <?php }elseif($asset_status == 'Lost'){ ?>
            <span class="badge bg-danger">Lost</span>
        <?php }else{ ?>
            <span class="badge bg-secondary"><?php echo $asset_status; ?></span>
        <?php } ?>
    </td>

    <td>
        <?php echo !empty($row['storage_location']) ? $row['storage_location'] : 'Not Set'; ?>
    </td>

    <td>
        <?php if($row['quantity'] <= 0){ ?>
            <span class="badge bg-danger">Out of Stock</span>
        <?php }elseif($row['quantity'] <= $row['minimum_stock']){ ?>
            <span class="badge bg-warning text-dark">Low Stock</span>
        <?php }else{ ?>
            <span class="badge bg-success">Available</span>
        <?php } ?>
    </td>

    <td>

        <div class="dropdown">

            <button class="btn btn-dark btn-sm dropdown-toggle"
                    type="button"
                    data-bs-toggle="dropdown">
                Actions
            </button>

            <ul class="dropdown-menu dropdown-menu-end">

                <li>
                    <a class="dropdown-item"
                       href="edit_item.php?id=<?php echo $row['id']; ?>">
                        ✏ Edit Item
                    </a>
                </li>

                <li>
                    <a class="dropdown-item"
                       href="view_item_history.php?id=<?php echo $row['id']; ?>">
                        📜 View History
                    </a>
                </li>

                <?php if(
                    ($role == 'Purchasing' || $role == 'Admin') &&
                    strcasecmp((string) ($row['item_type'] ?? ''), 'Asset') !== 0
                ){ ?>

                    <li>
                        <a class="dropdown-item"
                           href="restock_item.php?id=<?php echo $row['id']; ?>">
                            ⚖ Manual Stock Adjustment
                        </a>
                    </li>

                <?php } ?>

            </ul>

        </div>

    </td>

</tr>

<?php } ?>
<?php } ?>
<?php
$html = ob_get_clean();

echo json_encode([
    'html' => $html,
    'total' => $total,
    'page' => $page,
    'per_page' => $per_page,
    'total_pages' => $total_pages,
], JSON_UNESCAPED_UNICODE);
