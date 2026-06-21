<?php

include '../auth/auth_check.php';

require_role([
    'Boss',
    'Admin',
    'Purchasing'
]);

include '../config/database.php';

$id = $_GET['id'];

$request = $conn->query("
SELECT
material_requests.*,
job_orders.jo_no,
job_orders.project_name,
users.fullname

FROM material_requests

LEFT JOIN job_orders
ON job_orders.id = material_requests.jo_id

LEFT JOIN users
ON users.id = material_requests.requested_by

WHERE material_requests.id = '$id'
")->fetch_assoc();

if(!$request){
    die('Material Request not found.');
}

$items = $conn->query("
SELECT
material_request_items.*,
inventory_items.item_name,
inventory_items.quantity AS available_stock

FROM material_request_items

LEFT JOIN inventory_items
ON inventory_items.id = material_request_items.inventory_id

WHERE material_request_items.request_id = '$id'
");

?>

<!DOCTYPE html>
<html>
<head>
    <title>Update Item Status</title>
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
                    Update Material Item Status
                </h4>

                <div>
                    <a href="../dashboard/index.php" class="btn btn-light btn-sm">
                        Dashboard
                    </a>

                    <a href="view_request.php?id=<?php echo $id; ?>" class="btn btn-secondary btn-sm">
                        Back
                    </a>
                </div>

            </div>

        </div>

        <div class="card-body">

            <div class="row mb-3">

                <div class="col-md-6">
                    <p><b>Request No:</b> <?php echo $request['request_no']; ?></p>
                    <p><b>JO No:</b> <?php echo $request['jo_no']; ?></p>
                    <p><b>Project:</b> <?php echo $request['project_name']; ?></p>
                </div>

                <div class="col-md-6">
                    <p><b>Requested By:</b> <?php echo $request['fullname']; ?></p>
                    <p><b>Request Date:</b> <?php echo $request['request_date']; ?></p>
                    <p><b>Overall Status:</b> <?php echo $request['status']; ?></p>
                </div>

            </div>

            <hr>

            <form action="save_status.php" method="POST">

                <input type="hidden" name="request_id" value="<?php echo $id; ?>">

                <div class="table-responsive">

                    <table class="table table-bordered align-middle">

                        <thead class="table-dark">
                            <tr>
                                <th>#</th>
                                <th>Inventory Item</th>
                                <th>Description</th>
                                <th>Requested Qty</th>
                                <th>Available Stock</th>
                                <th>Current Item Status</th>
                                <th>New Item Status</th>
                                <th>Notes</th>
                            </tr>
                        </thead>

                        <tbody>

                        <?php
                        $count = 1;
                        while($item = $items->fetch_assoc()){
                        ?>

                            <tr>

                                <td><?php echo $count++; ?></td>

                                <td>
                                    <?php
                                    echo !empty($item['item_name'])
                                    ? $item['item_name']
                                    : 'Manual Entry';
                                    ?>
                                </td>

                                <td><?php echo $item['description']; ?></td>

                                <td><?php echo $item['quantity']; ?></td>

                                <td><?php echo $item['available_stock'] ?? 0; ?></td>

                                <td>
                                    <span class="badge bg-secondary">
                                        <?php echo $item['item_status'] ?? 'Pending'; ?>
                                    </span>
                                </td>

                                <td>
                                    <input type="hidden"
                                           name="item_id[]"
                                           value="<?php echo $item['id']; ?>">

                                    <select name="item_status[]"
                                            class="form-control"
                                            required>

                                        <option value="<?php echo $item['item_status']; ?>">
                                            Keep: <?php echo $item['item_status']; ?>
                                        </option>

                                        <option value="In Stock">
                                            In Stock
                                        </option>

                                        <option value="For Purchase">
                                            For Purchase
                                        </option>

                                        <option value="For Boss Approval">
                                            For Boss Approval
                                        </option>

                                        <option value="Approved">
                                            Approved
                                        </option>

                                        <option value="Waiting Delivery">
                                            Waiting Delivery
                                        </option>

                                        <option value="Received">
                                            Received
                                        </option>

                                        <option value="Cancelled">
                                            Cancelled
                                        </option>

                                    </select>
                                </td>

                                <td>
                                    <input type="text"
                                           name="notes[]"
                                           class="form-control"
                                           placeholder="Optional notes">
                                </td>

                            </tr>

                        <?php } ?>

                        </tbody>

                    </table>

                </div>

                <button type="submit" class="btn btn-success">
                    Save Item Status
                </button>

            </form>

        </div>

    </div>

</div>

</body>
</html>