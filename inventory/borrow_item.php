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
    i.asset_usage,
    COALESCE(au.storage_location,i.storage_location) AS storage_location
FROM asset_units au
INNER JOIN inventory_items i ON i.id=au.inventory_id
WHERE au.id = '$unit_id'
")->fetch_assoc();

if(!$item){
    die("Item not found.");
}

if(
    ($item['item_type'] ?? 'Consumable') !== 'Asset'
    || !in_array(($item['asset_usage'] ?? ''), ['Borrowable', 'Both'], true)
){
    die("This item is not borrowable.");
}

if(($item['asset_status'] ?? 'Available') != 'Available'){
    die("This item is not available for borrowing.");
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>Borrow Item</title>
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

            <h4 class="mb-0">
                Borrow Item - <?php echo $item['item_code']; ?>
            </h4>

        </div>

        <div class="card-body">

            <div class="alert alert-info">
                You are borrowing this asset. Please set the expected return date.
            </div>

            <div class="row mb-3">

                <div class="col-md-6">
                    <p><b>Item Code:</b> <?php echo $item['item_code']; ?></p>
                    <p><b>Item Name:</b> <?php echo $item['item_name']; ?></p>
                    <p><b>Brand:</b> <?php echo $item['brand']; ?></p>
                </div>

                <div class="col-md-6">
                    <p><b>Condition:</b> <?php echo $item['item_condition'] ?? 'Good'; ?></p>
                    <p><b>Status:</b> <?php echo $item['asset_status'] ?? 'Available'; ?></p>
                    <p><b>Location:</b> <?php echo $item['storage_location']; ?></p>
                </div>

            </div>

            <form action="save_borrow.php" method="POST">
                <?php echo csrf_field(); ?>

                <input type="hidden"
                       name="item_id"
                       value="<?php echo $item['inventory_id']; ?>">

                <input type="hidden"
                       name="asset_unit_id"
                       value="<?php echo $item['asset_unit_id']; ?>">

                <div class="mb-3">
                    <label class="fw-bold">Borrow Condition</label>

                    <select name="borrow_condition"
                            class="form-control"
                            required>
                        <option value="Good">Good</option>
                        <option value="With Minor Issue">With Minor Issue</option>
                        <option value="Damaged">Damaged</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="fw-bold">Due Date / Expected Return Date</label>

                    <input type="datetime-local"
                           name="due_date"
                           class="form-control"
                           required>
                </div>

                <div class="mb-3">
                    <label class="fw-bold">Remarks</label>

                    <textarea name="remarks"
                              class="form-control"
                              rows="4"
                              placeholder="Purpose of borrowing / project / notes..."></textarea>
                </div>

                <button type="submit"
                        class="btn btn-primary">
                    Confirm Borrow
                </button>

                <a href="scan_item.php?unit_id=<?php echo $item['asset_unit_id']; ?>"
                   class="btn btn-secondary">
                    Cancel
                </a>

            </form>

        </div>

    </div>

</div>

</div>

</body>
</html>
