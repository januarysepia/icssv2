<?php

include '../auth/auth_check.php';

require_role([
    'Purchasing',
    'Admin',
    'Boss'
]);

include '../config/database.php';

$id = intval($_GET['id']);

$supplier = $conn->query("
SELECT *
FROM suppliers
WHERE id = '$id'
")->fetch_assoc();

if(!$supplier){
    die('Supplier not found.');
}

/*
SUPPLIER STATISTICS
*/

$stats = $conn->query("
SELECT
COUNT(DISTINCT purchase_request_id) AS total_pr,
COUNT(*) AS total_items,
SUM(quantity) AS total_qty,
SUM(quantity * unit_price) AS total_amount

FROM purchase_request_items

WHERE supplier = '".$supplier['supplier_name']."'
")->fetch_assoc();

$total_pr = intval($stats['total_pr']);
$total_items = intval($stats['total_items']);
$total_qty = intval($stats['total_qty']);
$total_amount = floatval($stats['total_amount']);

$last_purchase = $conn->query("
SELECT
purchase_requests.purchase_no,
purchase_requests.created_at

FROM purchase_request_items

LEFT JOIN purchase_requests
ON purchase_requests.id = purchase_request_items.purchase_request_id

WHERE purchase_request_items.supplier = '".$supplier['supplier_name']."'

ORDER BY purchase_requests.id DESC
LIMIT 1
")->fetch_assoc();

/*
PURCHASE HISTORY
*/

$purchase_history = $conn->query("
SELECT
purchase_requests.purchase_no,
purchase_requests.status,
purchase_requests.created_at,
purchase_request_items.description,
purchase_request_items.quantity,
purchase_request_items.unit_price

FROM purchase_request_items

LEFT JOIN purchase_requests
ON purchase_requests.id = purchase_request_items.purchase_request_id

WHERE purchase_request_items.supplier = '".$supplier['supplier_name']."'

ORDER BY purchase_requests.id DESC
LIMIT 20
");

$supplier_catalog = $conn->prepare("
    SELECT
        isp.id,
        isp.inventory_id,
        isp.supplier_item_code,
        isp.unit_price,
        isp.is_preferred,
        isp.updated_at,
        ii.item_code,
        ii.item_name,
        ii.brand,
        ii.unit,
        prices.lowest_price,
        prices.supplier_count
    FROM item_suppliers isp
    INNER JOIN inventory_items ii ON ii.id = isp.inventory_id
    INNER JOIN (
        SELECT inventory_id, MIN(unit_price) AS lowest_price, COUNT(*) AS supplier_count
        FROM item_suppliers
        GROUP BY inventory_id
    ) prices ON prices.inventory_id = isp.inventory_id
    WHERE isp.supplier_id = ?
    ORDER BY ii.item_name, ii.brand
");
$supplier_catalog->bind_param('i', $id);
$supplier_catalog->execute();
$supplier_items = $supplier_catalog->get_result();

?>

<!DOCTYPE html>
<html>
<head>
    <title>View Supplier</title>

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
                    Supplier Details
                </h4>

                <div class="d-flex flex-wrap gap-1">

                    <a href="../dashboard/index.php"
                       class="btn btn-light btn-sm">
                        Dashboard
                    </a>

                    <a href="suppliers.php"
                       class="btn btn-secondary btn-sm">
                        Back
                    </a>

                    <a href="supplier_catalog.php?supplier_id=<?php echo $id; ?>"
                       class="btn btn-info btn-sm">
                        Item Catalog
                    </a>

                    <?php if (in_array($_SESSION['system_role'], ['Purchasing', 'Admin'], true)): ?>
                        <a href="supplier_item_form.php?supplier_id=<?php echo $id; ?>"
                           class="btn btn-success btn-sm">
                            + Add Item
                        </a>
                    <?php endif; ?>

                </div>

            </div>

        </div>

        <div class="card-body">

            <div class="row">

                <div class="col-md-6">

                    <table class="table table-bordered">

                        <tr>
                            <th width="200">Supplier Code</th>
                            <td><?php echo $supplier['supplier_code']; ?></td>
                        </tr>

                        <tr>
                            <th>Supplier Name</th>
                            <td><?php echo $supplier['supplier_name']; ?></td>
                        </tr>

                        <tr>
                            <th>Contact Person</th>
                            <td><?php echo $supplier['contact_person']; ?></td>
                        </tr>

                        <tr>
                            <th>Mobile Number</th>
                            <td>   
                                <a href="tel:<?php echo $supplier['mobile_number']; ?>"
                                    class="btn btn-success btn-sm">
                                        📞 Call
                                </a>

                                <a href="viber://chat?number=%2B63<?php echo substr($supplier['mobile_number'],1); ?>"
                                    class="btn btn-purple btn-sm">
                                        💬 Viber
                                </a>
                                <?php echo $supplier['mobile_number']; ?>
                            </td>
                        </tr>

                        <tr>
                            <th>Telephone Number</th>
                            <td><?php echo $supplier['telephone_number']; ?></td>
                        </tr>

                        <tr>
                            <th>Email Address</th>
                            <td><a href="mailto:<?php echo $supplier['email']; ?>">
                                <?php echo $supplier['email']; ?>
                            </a></td>
                        </tr>

                        <tr>
                            <th>Status</th>
                            <td>

                                <?php if($supplier['status'] == 'Active'){ ?>

                                    <span class="badge bg-success">
                                        Active
                                    </span>

                                <?php }else{ ?>

                                    <span class="badge bg-danger">
                                        Inactive
                                    </span>

                                <?php } ?>

                            </td>
                        </tr>

                    </table>

                </div>

                <div class="col-md-6">

                    <table class="table table-bordered">

                        <tr>
                            <th width="200">Address</th>
                            <td><?php echo nl2br($supplier['address']); ?></td>
                        </tr>

                        <tr>
                            <th>Products Supplied</th>
                            <td><?php echo nl2br($supplier['products_supplied']); ?></td>
                        </tr>

                        <tr>
                            <th>Created At</th>
                            <td><?php echo $supplier['created_at']; ?></td>
                        </tr>

                        <tr>
                            <th>Last Updated</th>
                            <td>
                                <?php
                                echo !empty($supplier['updated_at'])
                                ? $supplier['updated_at']
                                : '-';
                                ?>
                            </td>
                        </tr>

                    </table>

                </div>

            </div>

            <hr>

            <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                <h5 class="mb-0">Items and Current Prices</h5>
                <a href="price_comparison.php" class="btn btn-outline-primary btn-sm">Compare All Prices</a>
            </div>

            <div class="table-responsive mb-4">
                <table class="table table-bordered table-hover align-middle">
                    <thead class="table-dark">
                    <tr>
                        <th>Item</th><th>Brand</th><th>Supplier Code</th><th>Unit</th>
                        <th>Current Price</th><th>Comparison</th><th>Preferred</th><th>Updated</th><th>Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if ($supplier_items->num_rows > 0): ?>
                        <?php while ($catalog_item = $supplier_items->fetch_assoc()): ?>
                            <?php $is_lowest = (float) $catalog_item['unit_price'] === (float) $catalog_item['lowest_price']; ?>
                            <tr>
                                <td><strong><?= h($catalog_item['item_name']) ?></strong><br><small><?= h($catalog_item['item_code'] ?: '-') ?></small></td>
                                <td><?= h($catalog_item['brand'] ?: '-') ?></td>
                                <td><?= h($catalog_item['supplier_item_code'] ?: '-') ?></td>
                                <td><?= h($catalog_item['unit'] ?: '-') ?></td>
                                <td class="<?= $is_lowest ? 'text-success fw-bold' : '' ?>">₱<?= number_format((float) $catalog_item['unit_price'], 2) ?></td>
                                <td>
                                    <?php if ((int) $catalog_item['supplier_count'] === 1): ?>
                                        <span class="badge bg-secondary">Only supplier</span>
                                    <?php elseif ($is_lowest): ?>
                                        <span class="badge bg-success">Lowest</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">Higher offer</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= (int) $catalog_item['is_preferred'] ? '<span class="badge bg-primary">Preferred</span>' : '-' ?></td>
                                <td><?= h($catalog_item['updated_at']) ?></td>
                                <td>
                                    <a href="supplier_price_history.php?inventory_id=<?= (int) $catalog_item['inventory_id'] ?>"
                                       class="btn btn-outline-info btn-sm">History</a>
                                    <?php if (in_array($_SESSION['system_role'], ['Purchasing', 'Admin'], true)): ?>
                                        <a href="supplier_item_form.php?id=<?= (int) $catalog_item['id'] ?>"
                                           class="btn btn-outline-primary btn-sm">Edit</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="9" class="text-center text-muted">No catalog items recorded for this supplier.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="row mb-4">

                <div class="col-md-3">

                    <div class="card border-0 shadow-sm">

                        <div class="card-body text-center">

                            <h6>Total Purchase Requests</h6>

                            <h3 class="text-primary">
                                <?php echo $total_pr; ?>
                            </h3>

                        </div>

                    </div>

                </div>

                <div class="col-md-3">

                    <div class="card border-0 shadow-sm">

                        <div class="card-body text-center">

                            <h6>Total Items Purchased</h6>

                            <h3 class="text-success">
                                <?php echo $total_items; ?>
                            </h3>

                        </div>

                    </div>

                </div>

                <div class="col-md-3">

                    <div class="card border-0 shadow-sm">

                        <div class="card-body text-center">

                            <h6>Total Quantity</h6>

                            <h3 class="text-warning">
                                <?php echo number_format($total_qty); ?>
                            </h3>

                        </div>

                    </div>

                </div>

                <div class="col-md-3">

                    <div class="card border-0 shadow-sm">

                        <div class="card-body text-center">

                            <h6>Total Purchase Amount</h6>

                            <h5 class="text-danger">
                                ₱<?php echo number_format($total_amount,2); ?>
                            </h5>

                        </div>

                    </div>

                </div>

            </div>

            <div class="alert alert-info">

                <strong>Last Purchase:</strong>

                <?php

                if($last_purchase){

                    echo $last_purchase['purchase_no']
                        . ' - '
                        . $last_purchase['created_at'];

                }else{

                    echo 'No purchase history yet';

                }

                ?>

            </div>
            <hr>
            <h5 class="mb-3">
                Purchase History
            </h5>

            <div class="table-responsive">

                <table class="table table-bordered table-hover">

                    <thead class="table-dark">

                        <tr>
                            <th>Purchase No</th>
                            <th>Description</th>
                            <th>Quantity</th>
                            <th>Unit Price</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>

                    </thead>

                    <tbody>

                    <?php if($purchase_history && $purchase_history->num_rows > 0){ ?>

                        <?php while($row = $purchase_history->fetch_assoc()){ ?>

                            <tr>

                                <td>
                                    <?php echo $row['purchase_no']; ?>
                                </td>

                                <td>
                                    <?php echo $row['description']; ?>
                                </td>

                                <td>
                                    <?php echo $row['quantity']; ?>
                                </td>

                                <td>
                                    ₱<?php echo number_format($row['unit_price'],2); ?>
                                </td>

                                <td>
                                    <?php echo $row['status']; ?>
                                </td>

                                <td>
                                    <?php echo $row['created_at']; ?>
                                </td>

                            </tr>

                        <?php } ?>

                    <?php }else{ ?>

                        <tr>

                            <td colspan="6"
                                class="text-center text-muted">

                                No purchase history found.

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
