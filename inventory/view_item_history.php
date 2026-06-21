<?php

include '../auth/auth_check.php';

require_role([
    'Boss',
    'Admin',
    'Purchasing',
    'Supervisor'
]);

include '../config/database.php';

$id = $_GET['id'];

$item = $conn->query("
SELECT *
FROM inventory_items
WHERE id = '$id'
")->fetch_assoc();

if(!$item){
    die('Inventory item not found.');
}

$logs = $conn->query("
SELECT
inventory_logs.*,
users.fullname,
users.employee_no,
suppliers.supplier_name AS current_supplier_name

FROM inventory_logs

LEFT JOIN users
ON users.id = inventory_logs.created_by

LEFT JOIN suppliers
ON suppliers.id = inventory_logs.supplier_id

WHERE inventory_logs.inventory_id = '$id'

ORDER BY inventory_logs.id DESC
");

?>

<!DOCTYPE html>
<html>
<head>
    <title>Inventory Item History</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">

<div class="container-fluid mt-4">

    <div class="card shadow border-0">

        <div class="card-header bg-dark text-white">

            <div class="d-flex justify-content-between align-items-center">

                <h4 class="mb-0">
                    Inventory History - <?php echo $item['item_name']; ?>
                </h4>

                <div>
                    <a href="../dashboard/index.php" class="btn btn-light btn-sm">
                        Dashboard
                    </a>

                    <a href="inventory_list.php" class="btn btn-secondary btn-sm">
                        Back
                    </a>
                </div>

            </div>

        </div>

        <div class="card-body">

            <div class="row mb-4">

                <div class="col-md-4">
                    <p><b>Item Code:</b> <?php echo $item['item_code']; ?></p>
                    <p><b>Item Name:</b> <?php echo $item['item_name']; ?></p>
                    <p><b>Brand:</b> <?php echo $item['brand']; ?></p>
                </div>

                <div class="col-md-4">
                    <p><b>Category:</b> <?php echo $item['category']; ?></p>
                    <p><b>Unit:</b> <?php echo $item['unit']; ?></p>
                    <p><b>Current Quantity:</b> <?php echo $item['quantity']; ?></p>
                </div>

                <div class="col-md-4">
                    <p><b>Minimum Stock:</b> <?php echo $item['minimum_stock']; ?></p>
                    <p><b>Storage Location:</b> <?php echo $item['storage_location']; ?></p>
                    <p><b>Supplier:</b> <?php echo $item['supplier']; ?></p>
                </div>

            </div>

            <hr>

            <h5>Movement History</h5>

            <div class="table-responsive">

                <table class="table table-bordered table-hover align-middle">

                    <thead class="table-dark">
                        <tr>
                            <th>Date/Time</th>
                            <th>Movement</th>
                            <th>Quantity</th>
                            <th>Reference Type</th>
                            <th>Reference ID</th>
                            <th>Supplier</th>
                            <th>Unit Price</th>
                            <th>Remarks</th>
                            <th>Created By</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php while($row = $logs->fetch_assoc()){ ?>

                        <tr>

                            <td><?php echo $row['created_at']; ?></td>

                            <td>
                                <?php if($row['movement_type'] == 'Stock In'){ ?>

                                    <span class="badge bg-success">Stock In</span>

                                <?php }elseif($row['movement_type'] == 'Stock Out'){ ?>

                                    <span class="badge bg-danger">Stock Out</span>

                                <?php }else{ ?>

                                    <span class="badge bg-warning text-dark">
                                        <?php echo $row['movement_type']; ?>
                                    </span>

                                <?php } ?>
                            </td>

                            <td><?php echo $row['quantity']; ?></td>

                            <td><?php echo $row['reference_type']; ?></td>

                            <td><?php echo $row['reference_id']; ?></td>

                            <td>
                                <?php echo h(
                                    $row['supplier_name_snapshot']
                                    ?: $row['current_supplier_name']
                                    ?: '-'
                                ); ?>
                            </td>

                            <td>
                                <?php echo $row['unit_price_snapshot'] !== null
                                    ? '₱' . number_format((float) $row['unit_price_snapshot'], 2)
                                    : '-'; ?>
                            </td>

                            <td><?php echo $row['remarks']; ?></td>

                            <td>
                                <?php
                                if($row['fullname']){
                                    echo $row['employee_no'] . " - " . $row['fullname'];
                                }else{
                                    echo "System / Unknown";
                                }
                                ?>
                            </td>

                        </tr>

                    <?php } ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>

</body>
</html>
