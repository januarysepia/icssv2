<?php

include '../auth/auth_check.php';

require_role([
    'Purchasing',
    'Admin'
]);

include '../config/database.php';

$id = $_GET['id'];

$purchase = $conn->query("
SELECT *
FROM purchase_requests
WHERE id = '$id'
")->fetch_assoc();

$items = $conn->query("
SELECT *
FROM purchase_request_items
WHERE purchase_request_id = '$id'
");

?>

<!DOCTYPE html>
<html>
<head>
    <title>Receive Purchase Items</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">

<div class="container-fluid mt-4">

    <div class="card shadow border-0">

        <div class="card-header bg-dark text-white">
            <h4 class="mb-0">Receive Purchase Items</h4>
        </div>

        <div class="card-body">

            <form action="save_receive.php" method="POST">
                <?php echo csrf_field(); ?>

                <input type="hidden" name="purchase_request_id" value="<?php echo $id; ?>">

                <div class="mb-3">
                    <label class="fw-bold">Purchase No</label>
                    <input type="text" class="form-control" value="<?php echo $purchase['purchase_no']; ?>" readonly>
                </div>

                <table class="table table-bordered align-middle">

                    <thead class="table-dark">
                        <tr>
                            <th>No</th>
                            <th>Description</th>
                            <th>Item Code</th>
                            <th>Brand</th>
                            <th>Supplier</th>
                            <th>Unit</th>
                            <th>Ordered Qty</th>
                            <th>Received Qty</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php
                    $count = 1;
                    while($row = $items->fetch_assoc()){
                    ?>

                        <tr>
                            <td><?php echo $count++; ?></td>

                            <td><?php echo $row['description']; ?></td>

                            <td><?php echo $row['item_code']; ?></td>

                            <td><?php echo $row['brand']; ?></td>

                            <td><?php echo $row['supplier']; ?></td>

                            <td><?php echo $row['unit']; ?></td>

                            <td><?php echo $row['quantity']; ?></td>

                            <td>
                                <input type="hidden"
                                       name="item_id[]"
                                       value="<?php echo $row['id']; ?>">

                                <input type="number"
                                       name="quantity_received[]"
                                       class="form-control"
                                       value="<?php echo $row['quantity']; ?>"
                                       required>
                            </td>

                            <td>
                                <input type="text"
                                       name="remarks[]"
                                       class="form-control"
                                       placeholder="Optional remarks">
                            </td>
                        </tr>

                    <?php } ?>

                    </tbody>

                </table>

                <button type="submit" class="btn btn-success">
                    Save Receiving
                </button>

                <a href="view_purchase.php?id=<?php echo $id; ?>" class="btn btn-secondary">
                    Back
                </a>

            </form>

        </div>

    </div>

</div>

</body>
</html>
