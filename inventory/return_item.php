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

$borrow_id = intval($_GET['borrow_id']);

$borrow = $conn->query("
SELECT
borrow_transactions.*,
inventory_items.item_code,
inventory_items.item_name,
inventory_items.brand,
inventory_items.item_condition,
inventory_items.storage_location,
asset_units.asset_code,
asset_units.serial_number,
users.fullname,
users.employee_no,
COALESCE(users.fullname, borrow_transactions.borrower_name) AS display_name,
COALESCE(users.employee_no, borrow_transactions.borrower_department) AS display_reference

FROM borrow_transactions

LEFT JOIN inventory_items
ON inventory_items.id = borrow_transactions.item_id

LEFT JOIN asset_units
ON asset_units.id = borrow_transactions.asset_unit_id

LEFT JOIN users
ON users.id = borrow_transactions.employee_id

WHERE borrow_transactions.id = '$borrow_id'
")->fetch_assoc();

if(!$borrow){
    die("Borrow transaction not found.");
}

if($borrow['status'] != 'Borrowed'){
    die("This item has already been returned.");
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>Return Item</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <?php include '../includes/asset_ui.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">
</head>

<body class="bg-light asset-module">

<?php include '../dashboard/sidebar.php'; ?>
<div class="content-wrapper">
<?php include '../dashboard/header.php'; ?>

<div class="container-fluid asset-page">

    <div class="card shadow border-0">

        <div class="card-header bg-dark text-white">
            <h4 class="mb-0">
                Return Item - <?php echo h($borrow['asset_code'] ?: $borrow['item_code']); ?>
            </h4>
        </div>

        <div class="card-body">

            <div class="row mb-3">

                <div class="col-md-6">
                    <p><b>Item Code:</b> <?php echo $borrow['item_code']; ?></p>
                    <p><b>Item Name:</b> <?php echo $borrow['item_name']; ?></p>
                    <p><b>Brand:</b> <?php echo $borrow['brand']; ?></p>
                    <p>
                        <b>Borrowed By:</b>
                        <?php echo h($borrow['display_reference']); ?> -
                        <?php echo h($borrow['display_name']); ?>
                    </p>
                    <?php if(!empty($borrow['purpose'])){ ?>
                        <p><b>Purpose:</b> <?php echo h($borrow['purpose']); ?></p>
                    <?php } ?>
                </div>

                <div class="col-md-6">
                    <p><b>Borrow Date:</b> <?php echo $borrow['borrow_date']; ?></p>
                    <p><b>Due Date:</b> <?php echo $borrow['due_date']; ?></p>
                    <p><b>Borrow Condition:</b> <?php echo $borrow['borrow_condition']; ?></p>
                    <p><b>Location:</b> <?php echo $borrow['storage_location']; ?></p>
                </div>

            </div>

            <hr>

            <form action="save_return.php" method="POST">
                <?php echo csrf_field(); ?>

                <input type="hidden"
                       name="borrow_id"
                       value="<?php echo $borrow['id']; ?>">

                <input type="hidden"
                       name="item_id"
                       value="<?php echo $borrow['item_id']; ?>">

                <input type="hidden"
                       name="asset_unit_id"
                       value="<?php echo $borrow['asset_unit_id']; ?>">

                <div class="mb-3">
                    <label class="fw-bold">Return Condition</label>

                    <select name="return_condition"
                            class="form-control"
                            required>
                        <option value="">Select Condition</option>
                        <option value="Good">Good</option>
                        <option value="With Minor Issue">With Minor Issue</option>
                        <option value="Damaged">Damaged</option>
                        <option value="Lost">Lost</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="fw-bold">Return Remarks</label>

                    <textarea name="remarks"
                              class="form-control"
                              rows="4"
                              placeholder="Condition remarks / damage notes / missing parts..."></textarea>
                </div>

                <button type="submit"
                        class="btn btn-success">
                    Confirm Return
                </button>

                <a href="scan_item.php?unit_id=<?php echo $borrow['asset_unit_id']; ?>"
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
