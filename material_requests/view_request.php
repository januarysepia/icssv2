<?php

include '../auth/auth_check.php';

require_role([
    'Boss',
    'Admin',
    'Technical',
    'Purchasing',
    'Supervisor'
]);

include '../config/database.php';

$id = intval($_GET['id']);

$role = $_SESSION['system_role'] ?? $_SESSION['role'] ?? '';

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

$summary = $conn->query("
SELECT
SUM(CASE WHEN item_status = 'Pending Approval' THEN 1 ELSE 0 END) AS pending_count,
SUM(CASE WHEN item_status = 'For Issue' THEN 1 ELSE 0 END) AS for_issue_count,
SUM(CASE WHEN item_status = 'Issued' THEN 1 ELSE 0 END) AS issued_count,
SUM(CASE WHEN item_status = 'To Purchase' THEN 1 ELSE 0 END) AS to_purchase_count,
SUM(CASE WHEN item_status = 'Purchased' THEN 1 ELSE 0 END) AS purchased_count,
SUM(CASE WHEN item_status = 'Received' THEN 1 ELSE 0 END) AS received_count,
SUM(CASE WHEN item_status = 'Cancelled' THEN 1 ELSE 0 END) AS cancelled_count,
SUM(CASE WHEN supplier_review_status = 'Pending' THEN 1 ELSE 0 END) AS supplier_pending_count,
COUNT(*) AS total_count

FROM material_request_items

WHERE request_id = '$id'
")->fetch_assoc();

$logs = $conn->query("
SELECT
material_request_status_logs.*,
users.fullname

FROM material_request_status_logs

LEFT JOIN users
ON users.id = material_request_status_logs.updated_by

WHERE material_request_status_logs.request_id = '$id'

ORDER BY material_request_status_logs.id DESC
");

$issuance_logs = $conn->query("
SELECT
material_issuance_logs.*,
material_request_items.description,
inventory_items.item_name,
users.fullname

FROM material_issuance_logs

LEFT JOIN material_request_items
ON material_request_items.id = material_issuance_logs.material_request_item_id

LEFT JOIN inventory_items
ON inventory_items.id = material_issuance_logs.inventory_id

LEFT JOIN users
ON users.id = material_issuance_logs.issued_by

WHERE material_issuance_logs.material_request_id = '$id'

ORDER BY material_issuance_logs.id DESC
");

function requestBadge($status){

    if($status == 'Pending Review'){
        return '<span class="badge bg-warning text-dark">Pending Review</span>';
    }

    if($status == 'Approved - For Issue'){
        return '<span class="badge bg-success">Approved - For Issue</span>';
    }

    if($status == 'Approved - To Purchase'){
        return '<span class="badge bg-danger">Approved - To Purchase</span>';
    }

    if($status == 'Partially Approved'){
        return '<span class="badge bg-primary">Partially Approved</span>';
    }

    if($status == 'Partially Released'){
        return '<span class="badge bg-info text-dark">Partially Released</span>';
    }

    if($status == 'Partially Released / Pending Purchase'){
        return '<span class="badge bg-secondary">Partially Released / Pending Purchase</span>';
    }

    if($status == 'Pending Purchase'){
        return '<span class="badge bg-danger">Pending Purchase</span>';
    }

    if($status == 'Released to Production'){
        return '<span class="badge bg-success">Released to Production</span>';
    }

    if($status == 'Completed'){
        return '<span class="badge bg-success">Completed</span>';
    }

    return '<span class="badge bg-dark">' . $status . '</span>';
}

function itemBadge($status){

    if($status == 'Pending Approval'){
        return '<span class="badge bg-warning text-dark">Pending Approval</span>';
    }

    if($status == 'For Issue'){
        return '<span class="badge bg-success">For Issue</span>';
    }

    if($status == 'Issued'){
        return '<span class="badge bg-primary">Issued</span>';
    }

    if($status == 'To Purchase'){
        return '<span class="badge bg-danger">To Purchase</span>';
    }

    if($status == 'Purchased'){
        return '<span class="badge bg-info text-dark">Purchased</span>';
    }

    if($status == 'Received'){
        return '<span class="badge bg-secondary">Received</span>';
    }

    if($status == 'Cancelled'){
        return '<span class="badge bg-dark">Cancelled</span>';
    }

    return '<span class="badge bg-light text-dark">' . $status . '</span>';
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>View Material Request</title>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">

    <style>
        body{
            background:#f4f6f9;
        }

        .card-box{
            border:0;
            border-radius:14px;
            box-shadow:0 4px 14px rgba(0,0,0,0.08);
        }

        .summary-box{
            background:#f8fafc;
            border:1px solid #e5e7eb;
            border-radius:12px;
            padding:14px;
            text-align:center;
        }

        .summary-number{
            font-size:24px;
            font-weight:bold;
        }
    </style>
</head>

<body>

<div class="container-fluid mt-4">

    <div class="card shadow border-0">

        <div class="card-header bg-dark text-white">

            <div class="d-flex justify-content-between align-items-center">

                <h4 class="mb-0">
                    Material Request Details
                </h4>

                <div>
                    <a href="../dashboard/index.php"
                       class="btn btn-light btn-sm">
                        Dashboard
                    </a>

                    <a href="request_list.php"
                       class="btn btn-secondary btn-sm">
                        Back
                    </a>
                </div>

            </div>

        </div>

        <div class="card-body">

            <div class="row mb-4">

                <div class="col-md-6">

                    <p>
                        <b>Request No:</b>
                        <?php echo $request['request_no']; ?>
                    </p>

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
                        <b>Requested By:</b>
                        <?php echo $request['fullname']; ?>
                    </p>

                    <p>
                        <b>Request Date:</b>
                        <?php echo $request['request_date']; ?>
                    </p>

                    <p>
                        <b>Request Context:</b>
                        <?php if (($request['request_context'] ?? 'Ongoing JO') === 'After Delivery / Correction'): ?>
                            <span class="badge bg-warning text-dark">After Delivery / Correction</span>
                        <?php else: ?>
                            <span class="badge bg-primary">Ongoing JO</span>
                        <?php endif; ?>
                    </p>

                    <p>
                        <b>Overall Status:</b>
                        <?php echo requestBadge($request['status']); ?>
                    </p>

                </div>

            </div>

            <div class="mb-3">

                <b>Notes:</b>

                <div class="border rounded p-3 bg-light mt-2">
                    <?php
                    echo !empty($request['notes'])
                    ? nl2br($request['notes'])
                    : "<span class='text-muted'>No notes available.</span>";
                    ?>
                </div>

            </div>

            <hr>

            <div class="row g-3 mb-4">

                <div class="col-md-2">
                    <div class="summary-box">
                        <div class="summary-number text-warning">
                            <?php echo intval($summary['pending_count']); ?>
                        </div>
                        <small>Pending Approval</small>
                    </div>
                </div>

                <div class="col-md-2">
                    <div class="summary-box">
                        <div class="summary-number text-success">
                            <?php echo intval($summary['for_issue_count']); ?>
                        </div>
                        <small>For Issue</small>
                    </div>
                </div>

                <div class="col-md-2">
                    <div class="summary-box">
                        <div class="summary-number text-primary">
                            <?php echo intval($summary['issued_count']); ?>
                        </div>
                        <small>Issued</small>
                    </div>
                </div>

                <div class="col-md-2">
                    <div class="summary-box">
                        <div class="summary-number text-danger">
                            <?php echo intval($summary['to_purchase_count']); ?>
                        </div>
                        <small>To Purchase</small>
                    </div>
                </div>

                <div class="col-md-2">
                    <div class="summary-box">
                        <div class="summary-number text-info">
                            <?php echo intval($summary['received_count']); ?>
                        </div>
                        <small>Received</small>
                    </div>
                </div>

                <div class="col-md-2">
                    <div class="summary-box">
                        <div class="summary-number">
                            <?php echo intval($summary['total_count']); ?>
                        </div>
                        <small>Total Items</small>
                    </div>
                </div>

            </div>

            <div class="d-flex justify-content-between align-items-center mb-3">

                <h5 class="mb-0">
                    Requested Items
                </h5>

                <div>

                    <?php if(
                        ($role == 'Purchasing' || $role == 'Admin' || $role == 'Boss')
                        &&
                        $request['status'] == 'Pending Review'
                        &&
                        intval($summary['supplier_pending_count']) === 0
                    ){ ?>

                        <form action="approve_request.php" method="POST" class="d-inline"
                              onsubmit="return confirm('Approve this material request?');">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="id" value="<?php echo (int) $request['id']; ?>">
                            <button type="submit" class="btn btn-success btn-sm">Approve Request</button>
                        </form>

                    <?php } ?>

                    <?php if(
                        ($role == 'Purchasing' || $role == 'Admin' || $role == 'Boss')
                        &&
                        $request['status'] == 'Pending Review'
                        &&
                        intval($summary['supplier_pending_count']) > 0
                    ){ ?>
                        <span class="badge bg-warning text-dark">
                            <?= (int) $summary['supplier_pending_count'] ?> supplier suggestion(s) need review
                        </span>
                    <?php } ?>

                    <?php if(
                        ($role == 'Purchasing' || $role == 'Admin' || $role == 'Boss')
                        &&
                        intval($summary['for_issue_count']) > 0
                    ){ ?>

                        <a href="issue_materials.php?id=<?php echo $request['id']; ?>"
                           class="btn btn-primary btn-sm">

                            Issue Materials

                        </a>

                    <?php } ?>

                    <?php if(
                        ($role == 'Purchasing' || $role == 'Admin' || $role == 'Boss')
                        &&
                        intval($summary['to_purchase_count']) > 0
                    ){ ?>

                        <a href="../purchase_requests/create_purchase.php?material_request_id=<?php echo $request['id']; ?>"
                           class="btn btn-danger btn-sm">

                            Create Purchase Request

                        </a>

                    <?php } ?>

                </div>

            </div>

            <div class="table-responsive">

                <table class="table table-bordered align-middle table-hover">

                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Inventory Item</th>
                            <th>Description</th>
                            <th>Item Code</th>
                            <th>Brand</th>
                            <th>Supplier</th>
                            <th>Unit</th>
                            <th>Requested Qty</th>
                            <th>Available Stock</th>
                            <th>Unit Price</th>
                            <th>Total</th>
                            <th>Item Status</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php

                    $counter = 1;
                    $grand_total = 0;

                    while($item = $items->fetch_assoc()){

                        $total = $item['quantity'] * $item['unit_price'];
                        $grand_total += $total;

                        $item_status = $item['item_status'] ?? 'Pending Approval';

                    ?>

                        <tr>

                            <td>
                                <?php echo $counter++; ?>
                            </td>

                            <td>
                                <?php
                                echo !empty($item['item_name'])
                                ? $item['item_name']
                                : 'Manual Entry';
                                ?>
                            </td>

                            <td>
                                <?php echo $item['description']; ?>
                            </td>

                            <td>
                                <?php echo $item['item_code']; ?>
                            </td>

                            <td>
                                <?php echo $item['brand']; ?>
                            </td>

                            <td>
                                <?php if (($item['supplier_review_status'] ?? '') === 'Pending'): ?>
                                    <div class="fw-bold"><?= h($item['suggested_supplier_name'] ?: $item['supplier']) ?></div>
                                    <span class="badge bg-warning text-dark">Pending Supplier Review</span>
                                    <?php if (
                                        ($role === 'Purchasing' || $role === 'Admin')
                                        && $request['status'] === 'Pending Review'
                                    ): ?>
                                        <div class="mt-2">
                                            <a href="resolve_supplier.php?item_id=<?= (int) $item['id'] ?>"
                                               class="btn btn-sm btn-primary">
                                                Review Supplier
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <?= h(!empty($item['supplier_name']) ? $item['supplier_name'] : ($item['supplier'] ?: '-')) ?>
                                    <?php if (($item['supplier_review_status'] ?? '') === 'Legacy'): ?>
                                        <div><span class="badge bg-secondary">Legacy Entry</span></div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?php echo $item['unit']; ?>
                            </td>

                            <td>
                                <?php echo $item['quantity']; ?>
                            </td>

                            <td>
                                <?php echo $item['available_stock'] ?? 0; ?>
                            </td>

                            <td>
                                ₱ <?php echo number_format($item['unit_price'],2); ?>
                            </td>

                            <td>
                                ₱ <?php echo number_format($total,2); ?>
                            </td>

                            <td>
                                <?php echo itemBadge($item_status); ?>
                            </td>

                        </tr>

                    <?php } ?>

                    </tbody>

                    <tfoot>
                        <tr>
                            <th colspan="10" class="text-end">
                                Grand Total
                            </th>

                            <th colspan="2">
                                ₱ <?php echo number_format($grand_total,2); ?>
                            </th>
                        </tr>
                    </tfoot>

                </table>

            </div>

            <hr>

            <h5>Status History</h5>

            <div class="table-responsive">

                <table class="table table-bordered align-middle">

                    <thead class="table-dark">
                        <tr>
                            <th>Date/Time</th>
                            <th>Updated By</th>
                            <th>Old Status</th>
                            <th>New Status</th>
                            <th>Notes</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if($logs && $logs->num_rows > 0){ ?>

                        <?php while($log = $logs->fetch_assoc()){ ?>

                            <tr>
                                <td><?php echo $log['created_at']; ?></td>
                                <td><?php echo $log['fullname']; ?></td>
                                <td><?php echo $log['old_status']; ?></td>
                                <td><?php echo $log['new_status']; ?></td>
                                <td><?php echo nl2br($log['notes']); ?></td>
                            </tr>

                        <?php } ?>

                    <?php }else{ ?>

                        <tr>
                            <td colspan="5" class="text-center text-muted">
                                No status history yet.
                            </td>
                        </tr>

                    <?php } ?>

                    </tbody>

                </table>

            </div>

            <hr>

            <h5>Material Issuance History</h5>

            <div class="table-responsive">

                <table class="table table-bordered align-middle">

                    <thead class="table-dark">
                        <tr>
                            <th>Date/Time</th>
                            <th>Item</th>
                            <th>Description</th>
                            <th>Qty Issued</th>
                            <th>Issued By</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if($issuance_logs && $issuance_logs->num_rows > 0){ ?>

                        <?php while($issue = $issuance_logs->fetch_assoc()){ ?>

                            <tr>
                                <td><?php echo $issue['created_at']; ?></td>

                                <td>
                                    <?php
                                    echo !empty($issue['item_name'])
                                    ? $issue['item_name']
                                    : 'Manual Entry';
                                    ?>
                                </td>

                                <td><?php echo $issue['description']; ?></td>

                                <td><?php echo $issue['quantity_issued']; ?></td>

                                <td><?php echo $issue['fullname']; ?></td>

                                <td><?php echo $issue['remarks']; ?></td>
                            </tr>

                        <?php } ?>

                    <?php }else{ ?>

                        <tr>
                            <td colspan="6" class="text-center text-muted">
                                No issuance history yet.
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
