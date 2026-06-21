<?php

include '../auth/auth_check.php';

require_role([
    'Purchasing',
    'Admin',
    'Boss'
]);

include '../config/database.php';

$id = intval($_GET['id']);

$request = $conn->query("
SELECT
material_requests.*,
job_orders.jo_no,
job_orders.project_name

FROM material_requests

LEFT JOIN job_orders
ON job_orders.id = material_requests.jo_id

WHERE material_requests.id = '$id'
")->fetch_assoc();

if(!$request){
    die('Material request not found.');
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

AND material_request_items.item_status IN (
    'For Issue',
    'Received'
)

AND material_request_items.inventory_id IS NOT NULL

ORDER BY material_request_items.id ASC
");

?>

<!DOCTYPE html>
<html>
<head>
    <title>Issue Materials</title>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">
</head>

<body class="bg-light">

<div class="container-fluid mt-4">

    <div class="card shadow border-0">

        <div class="card-header bg-dark text-white">

            <div class="d-flex justify-content-between align-items-center">

                <h4 class="mb-0">
                    Issue Materials - <?php echo $request['request_no']; ?>
                </h4>

                <div>

                    <a href="../dashboard/index.php"
                       class="btn btn-light btn-sm">
                        Dashboard
                    </a>

                    <a href="view_request.php?id=<?php echo $id; ?>"
                       class="btn btn-secondary btn-sm">
                        Back
                    </a>

                </div>

            </div>

        </div>

        <div class="card-body">

            <div class="row mb-3">

                <div class="col-md-6">

                    <p>
                        <b>JO No:</b>
                        <?php echo $request['jo_no']; ?>
                    </p>

                    <p>
                        <b>Project:</b>
                        <?php echo $request['project_name']; ?>
                    </p>

                </div>

                <div class="col-md-6">

                    <p>
                        <b>Request Status:</b>
                        <?php echo $request['status']; ?>
                    </p>

                    <p>
                        <b>Note:</b>

                        <span class="badge bg-success">
                            For Issue
                        </span>

                        or

                        <span class="badge bg-secondary">
                            Received
                        </span>

                        items can be issued to production.
                    </p>

                </div>

            </div>

            <hr>

            <form action="save_issue.php" method="POST">

                <input type="hidden"
                       name="material_request_id"
                       value="<?php echo $id; ?>">

                <input type="hidden"
                       name="jo_id"
                       value="<?php echo $request['jo_id']; ?>">

                <div class="table-responsive">

                    <table class="table table-bordered align-middle">

                        <thead class="table-dark">

                            <tr>
                                <th>No</th>
                                <th>Inventory Item</th>
                                <th>Description</th>
                                <th>Status</th>
                                <th>Requested Qty</th>
                                <th>Available Stock</th>
                                <th>Qty to Issue</th>
                                <th>Remarks</th>
                            </tr>

                        </thead>

                        <tbody>

                        <?php

                        $count = 1;

                        if($items && $items->num_rows > 0){

                            while($row = $items->fetch_assoc()){

                        ?>

                            <tr>

                                <td>
                                    <?php echo $count++; ?>
                                </td>

                                <td>
                                    <?php
                                    echo !empty($row['item_name'])
                                    ? $row['item_name']
                                    : 'Inventory Item';
                                    ?>
                                </td>

                                <td>
                                    <?php echo $row['description']; ?>
                                </td>

                                <td>

                                    <?php if($row['item_status'] == 'Received'){ ?>

                                        <span class="badge bg-secondary">
                                            Received
                                        </span>

                                    <?php }else{ ?>

                                        <span class="badge bg-success">
                                            For Issue
                                        </span>

                                    <?php } ?>

                                </td>

                                <td>
                                    <?php echo $row['quantity']; ?>
                                </td>

                                <td>
                                    <?php echo $row['available_stock'] ?? 0; ?>
                                </td>

                                <td>

                                    <input type="hidden"
                                           name="request_item_id[]"
                                           value="<?php echo $row['id']; ?>">

                                    <input type="hidden"
                                           name="inventory_id[]"
                                           value="<?php echo $row['inventory_id']; ?>">

                                    <input type="number"
                                           name="quantity_issued[]"
                                           class="form-control"
                                           value="<?php echo $row['quantity']; ?>"
                                           min="0"
                                           max="<?php echo $row['available_stock']; ?>"
                                           required>

                                </td>

                                <td>

                                    <input type="text"
                                           name="remarks[]"
                                           class="form-control"
                                           placeholder="Optional remarks">

                                </td>

                            </tr>

                        <?php

                            }

                        }else{

                        ?>

                            <tr>

                                <td colspan="8"
                                    class="text-center text-muted">

                                    No items available for issuance.

                                </td>

                            </tr>

                        <?php } ?>

                        </tbody>

                    </table>

                </div>

                <?php if($items && $items->num_rows > 0){ ?>

                    <button type="submit"
                            class="btn btn-success">

                        Save Issuance

                    </button>

                <?php } ?>

            </form>

        </div>

    </div>

</div>

</body>
</html>