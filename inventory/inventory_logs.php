<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

include '../auth/auth_check.php';

require_role([
    'Boss',
    'Admin',
    'Purchasing',
    'Supervisor'
]);

include '../config/database.php';

$logs = $conn->query("
SELECT
inventory_logs.*,
inventory_items.item_code,
inventory_items.item_name,
inventory_items.brand,
inventory_items.unit,
users.fullname,
users.employee_no,
suppliers.supplier_name AS current_supplier_name

FROM inventory_logs

LEFT JOIN inventory_items
ON inventory_items.id = inventory_logs.inventory_id

LEFT JOIN users
ON users.id = inventory_logs.created_by

LEFT JOIN suppliers
ON suppliers.id = inventory_logs.supplier_id

ORDER BY inventory_logs.id DESC
");

if(!$logs){
    die('Inventory logs query error: ' . $conn->error);
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>Inventory Movement Logs</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">

<div class="container-fluid mt-4">

    <div class="card shadow border-0">

        <div class="card-header bg-dark text-white">
            <div class="d-flex justify-content-between align-items-center">
                <h4 class="mb-0">Inventory Movement Logs</h4>

                <div>
                    <a href="../dashboard/index.php" class="btn btn-light btn-sm">Dashboard</a>
                    <a href="inventory_list.php" class="btn btn-secondary btn-sm">Back</a>
                </div>
            </div>
        </div>

        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-bordered table-hover align-middle">

                    <thead class="table-dark">
                        <tr>
                            <th>Date/Time</th>
                            <th>Item Code</th>
                            <th>Item Name</th>
                            <th>Brand</th>
                            <th>Movement</th>
                            <th>Quantity</th>
                            <th>Unit</th>
                            <th>Reference Type</th>
                            <th>Reference ID</th>
                            <th>Supplier</th>
                            <th>Unit Price</th>
                            <th>Remarks</th>
                            <th>Created By</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if($logs->num_rows > 0){ ?>

                        <?php while($row = $logs->fetch_assoc()){ ?>

                            <tr>
                                <td><?php echo $row['created_at']; ?></td>
                                <td><?php echo $row['item_code'] ?? 'N/A'; ?></td>
                                <td><b><?php echo $row['item_name'] ?? 'Deleted / Unknown Item'; ?></b></td>
                                <td><?php echo $row['brand'] ?? 'N/A'; ?></td>

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
                                <td><?php echo $row['unit'] ?? ''; ?></td>
                                <td><?php echo $row['reference_type']; ?></td>
                                <td><?php echo $row['reference_id']; ?></td>
                                <td><?php echo h(
                                    $row['supplier_name_snapshot']
                                    ?: $row['current_supplier_name']
                                    ?: '-'
                                ); ?></td>
                                <td><?php echo $row['unit_price_snapshot'] !== null
                                    ? '₱' . number_format((float) $row['unit_price_snapshot'], 2)
                                    : '-'; ?></td>
                                <td><?php echo nl2br($row['remarks']); ?></td>

                                <td>
                                    <?php
                                    if(!empty($row['fullname'])){
                                        echo $row['employee_no'] . " - " . $row['fullname'];
                                    }else{
                                        echo "System / Unknown";
                                    }
                                    ?>
                                </td>
                            </tr>

                        <?php } ?>

                    <?php }else{ ?>

                        <tr>
                            <td colspan="13" class="text-center text-muted">
                                No inventory logs found.
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
