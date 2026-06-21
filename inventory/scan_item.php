<?php

include '../auth/auth_check.php';

require_role([
    'Boss',
    'Admin',
    'Purchasing',
    'Supervisor',
    'Production',
    'Engineer',
    'Technical',
    'Logistics'
]);

include '../config/database.php';

$unit_id = intval($_GET['unit_id'] ?? 0);

$item = $conn->query("
SELECT
    au.*,
    au.id AS asset_unit_id,
    au.asset_code AS item_code,
    au.unit_status AS asset_status,
    au.condition_status AS item_condition,
    i.id AS inventory_id,
    i.item_name,
    i.brand,
    i.category,
    i.unit,
    i.quantity,
    i.minimum_stock,
    i.asset_usage
FROM asset_units au
INNER JOIN inventory_items i ON i.id=au.inventory_id
WHERE au.id = '$unit_id'
")->fetch_assoc();

if(!$item){
    die("Item not found.");
}

$active_borrow = $conn->query("
SELECT
borrow_transactions.*,
users.fullname,
users.employee_no,
COALESCE(users.fullname, borrow_transactions.borrower_name) AS display_name,
COALESCE(users.employee_no, borrow_transactions.borrower_department) AS display_reference

FROM borrow_transactions

LEFT JOIN users
ON users.id = borrow_transactions.employee_id

WHERE borrow_transactions.asset_unit_id = '$unit_id'
AND borrow_transactions.status = 'Borrowed'

ORDER BY borrow_transactions.id DESC
LIMIT 1
")->fetch_assoc();

$is_overdue = $active_borrow
    && !empty($active_borrow['due_date'])
    && strtotime($active_borrow['due_date']) < time();
$days_overdue = $is_overdue
    ? max(1, (int) floor((time() - strtotime($active_borrow['due_date'])) / 86400))
    : 0;

?>

<!DOCTYPE html>
<html>
<head>
    <title>Scan Item</title>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">
    <?php include '../includes/asset_ui.php'; ?>
</head>

<body class="bg-light asset-module">

<?php include '../dashboard/sidebar.php'; ?>
<div class="content-wrapper">
<?php include '../dashboard/header.php'; ?>

<div class="container-fluid asset-page">

    <div class="card shadow border-0">

        <div class="card-header bg-dark text-white">

            <div class="d-flex justify-content-between align-items-center">

                <h4 class="mb-0">
                    Item Scan - <?php echo $item['item_code']; ?>
                </h4>

                <a href="inventory_list.php"
                   class="btn btn-light btn-sm">
                    Inventory
                </a>

            </div>

        </div>

        <div class="card-body">

            <div class="row mb-3">

                <div class="col-md-6">

                    <p><b>Item Code:</b> <?php echo $item['item_code']; ?></p>
                    <p><b>Item Name:</b> <?php echo $item['item_name']; ?></p>
                    <p><b>Brand:</b> <?php echo $item['brand']; ?></p>
                    <p><b>Category:</b> <?php echo $item['category']; ?></p>
                    <p><b>Unit:</b> <?php echo $item['unit']; ?></p>

                </div>

                <div class="col-md-6">

                    <p>
                        <b>Type:</b>
                        <?php if(
                            ($item['item_type'] ?? 'Consumable') === 'Asset'
                            && in_array(($item['asset_usage'] ?? ''), ['Borrowable', 'Both'], true)
                        ){ ?>
                            <span class="badge bg-primary">Borrowable</span>
                        <?php }else{ ?>
                            <span class="badge bg-success">Consumable</span>
                        <?php } ?>
                    </p>

                    <p><b>Quantity:</b> <?php echo $item['quantity']; ?></p>
                    <p><b>Minimum Stock:</b> <?php echo $item['minimum_stock']; ?></p>
                    <p><b>Location:</b> <?php echo $item['storage_location']; ?></p>

                    <p>
                        <b>Condition:</b>
                        <span class="badge bg-dark">
                            <?php echo $item['item_condition'] ?? 'Good'; ?>
                        </span>
                    </p>

                    <p>
                        <b>Asset Status:</b>
                        <span class="badge bg-secondary">
                            <?php echo $item['asset_status'] ?? 'Available'; ?>
                        </span>
                    </p>

                </div>

            </div>

            <hr>

            <?php if(
                ($item['item_type'] ?? 'Consumable') === 'Asset'
                && in_array(($item['asset_usage'] ?? ''), ['Borrowable', 'Both'], true)
            ){ ?>

                <?php if($active_borrow){ ?>

                    <div class="alert <?= $is_overdue ? 'alert-danger' : 'alert-warning' ?>">

                        <h5><?= $is_overdue ? 'This item is overdue.' : 'This item is currently borrowed.' ?></h5>

                        <p>
                            <b>Borrowed By:</b>
                            <?php echo h($active_borrow['display_reference']); ?>
                            -
                            <?php echo h($active_borrow['display_name']); ?>
                        </p>

                        <p>
                            <b>Borrow Date:</b>
                            <?php echo $active_borrow['borrow_date']; ?>
                        </p>

                        <p>
                            <b>Due Date:</b>
                            <?php echo $active_borrow['due_date']; ?>
                            <?php if ($is_overdue): ?>
                                <span class="badge bg-danger ms-2"><?= $days_overdue ?> day(s) overdue</span>
                            <?php endif; ?>
                        </p>

                        <?php if(!empty($active_borrow['purpose'])){ ?>
                            <p><b>Purpose:</b> <?php echo h($active_borrow['purpose']); ?></p>
                        <?php } ?>

                    </div>

                    <a href="return_item.php?borrow_id=<?php echo $active_borrow['id']; ?>"
                       class="btn btn-success">
                        Return Item
                    </a>

                <?php }else{ ?>

                    <div class="alert alert-success">
                        This borrowable item is available.
                    </div>

                    <a href="borrow_item.php?unit_id=<?php echo $item['asset_unit_id']; ?>"
                       class="btn btn-primary">
                        Borrow Item
                    </a>

                <?php } ?>

            <?php }else{ ?>

                <div class="alert alert-info">
                    This is a consumable item. It is issued/consumed instead of borrowed.
                </div>

            <?php } ?>

            <hr>

            <a href="asset_history.php?unit_id=<?php echo $item['asset_unit_id']; ?>"
               class="btn btn-info">
                View Item History
            </a>

        </div>

    </div>

</div>

</div>

</body>
</html>
