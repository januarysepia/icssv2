<?php

include '../auth/auth_check.php';

require_role([
    'Purchasing',
    'Boss',
    'Admin'
]);

include '../config/database.php';

$id = intval($_GET['id']);

$role = $_SESSION['system_role'] ?? $_SESSION['role'] ?? '';

$purchase = $conn->query("
SELECT
purchase_requests.*,
users.fullname

FROM purchase_requests

LEFT JOIN users
ON users.id = purchase_requests.requested_by

WHERE purchase_requests.id = '$id'
")->fetch_assoc();

if(!$purchase){
    die('Purchase Request not found.');
}

$email_status = $conn->query("
    SELECT recipient_email, sent_at, send_error, expires_at, used_at, action
    FROM purchase_email_approvals
    WHERE purchase_request_id = '$id'
    ORDER BY id DESC
    LIMIT 1
")->fetch_assoc();

$items = $conn->query("
SELECT *
FROM purchase_request_items
WHERE purchase_request_id = '$id'
");

$logs = $conn->query("
SELECT
purchase_approval_logs.*,
users.fullname

FROM purchase_approval_logs

LEFT JOIN users
ON users.id = purchase_approval_logs.user_id

WHERE purchase_approval_logs.purchase_request_id = '$id'

ORDER BY purchase_approval_logs.id DESC
");

function purchaseBadge($status){

    if($status == 'For Boss Approval'){
        return '<span class="badge bg-warning text-dark">For Boss Approval</span>';
    }

    if($status == 'Boss Approved'){
        return '<span class="badge bg-success">Boss Approved</span>';
    }

    if($status == 'Boss Rejected'){
        return '<span class="badge bg-danger">Boss Rejected</span>';
    }

    if($status == 'Waiting Delivery'){
        return '<span class="badge bg-secondary">Waiting Delivery</span>';
    }

    if($status == 'Received'){
        return '<span class="badge bg-primary">Received</span>';
    }

    return '<span class="badge bg-dark">' . $status . '</span>';
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>View Purchase Request</title>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">

    <style>
        body{
            background:#f4f6f9;
        }

        .summary-card{
            background:#f8fafc;
            border:1px solid #e5e7eb;
            border-radius:12px;
            padding:15px;
        }
    </style>
</head>

<body class="bg-light">

<div class="container-fluid mt-4">

    <div class="card shadow border-0">

        <div class="card-header bg-dark text-white">

            <div class="d-flex justify-content-between align-items-center">

                <h4 class="mb-0">
                    Purchase Request Details
                </h4>

                <div>
                    <a href="../dashboard/index.php"
                       class="btn btn-light btn-sm">
                        Dashboard
                    </a>

                    <a href="purchase_list.php"
                       class="btn btn-secondary btn-sm">
                        Back
                    </a>
                </div>

            </div>

        </div>

        <div class="card-body">

            <div class="row g-3 mb-4">

                <div class="col-md-3">
                    <div class="summary-card">
                        <small class="text-muted">Purchase No</small>
                        <h5><?php echo $purchase['purchase_no']; ?></h5>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="summary-card">
                        <small class="text-muted">Status</small>
                        <div class="mt-2">
                            <?php echo purchaseBadge($purchase['status']); ?>
                        </div>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="summary-card">
                        <small class="text-muted">Requested By</small>
                        <h6><?php echo $purchase['fullname']; ?></h6>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="summary-card">
                        <small class="text-muted">Date Created</small>
                        <h6><?php echo $purchase['created_at']; ?></h6>
                    </div>
                </div>

            </div>

            <div class="mb-4">
                <p><b>Remarks:</b></p>

                <div class="border rounded p-3 bg-light">
                    <?php
                    echo !empty($purchase['remarks'])
                    ? nl2br($purchase['remarks'])
                    : 'No remarks';
                    ?>
                </div>
            </div>

            <?php if ($email_status): ?>
                <div class="alert <?= !empty($email_status['sent_at']) ? 'alert-info' : 'alert-warning' ?>">
                    <strong>Boss Approval Email:</strong>
                    <?php if (!empty($email_status['sent_at'])): ?>
                        Sent to <?= h($email_status['recipient_email']) ?> on <?= h($email_status['sent_at']) ?>.
                        <?php if (!empty($email_status['used_at'])): ?>
                            Decision: <strong><?= h($email_status['action']) ?></strong>
                        <?php else: ?>
                            Link expires on <?= h($email_status['expires_at']) ?>.
                        <?php endif; ?>
                    <?php else: ?>
                        Not sent. <?= h($email_status['send_error'] ?: 'SMTP configuration is incomplete.') ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <hr>

            <div class="d-flex justify-content-between align-items-center mb-3">

                <h5 class="mb-0">
                    Purchase Items
                </h5>

                <div>

                    <?php if(
                        $role == 'Boss'
                        &&
                        $purchase['status'] == 'For Boss Approval'
                    ){ ?>

                        <form action="boss_approve.php" method="POST" class="d-inline"
                              onsubmit="return confirm('Approve this purchase request?');">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="id" value="<?php echo (int) $purchase['id']; ?>">
                            <button type="submit" class="btn btn-success btn-sm">Approve Purchase</button>
                        </form>

                        <form action="boss_reject.php" method="POST" class="d-inline"
                              onsubmit="return confirm('Reject this purchase request?');">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="id" value="<?php echo (int) $purchase['id']; ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Reject Purchase</button>
                        </form>

                    <?php } ?>

                    <?php if(
                        ($role == 'Purchasing' || $role == 'Admin')
                        &&
                        $purchase['status'] == 'For Boss Approval'
                    ){ ?>

                        <form action="resend_approval_email.php" method="POST" class="d-inline">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="id" value="<?php echo (int) $purchase['id']; ?>">
                            <button type="submit" class="btn btn-outline-primary btn-sm">Send / Resend Approval Email</button>
                        </form>

                    <?php } ?>

                    <?php if(
                        ($role == 'Purchasing' || $role == 'Admin')
                        &&
                        $purchase['status'] == 'Boss Approved'
                    ){ ?>

                        <a href="receive_items.php?id=<?php echo $purchase['id']; ?>"
                           class="btn btn-primary btn-sm">
                            Receive Items
                        </a>

                    <?php } ?>

                </div>

            </div>

            <div class="table-responsive">

                <table class="table table-bordered align-middle">

                    <thead class="table-dark">
                        <tr>
                            <th>No</th>
                            <th>Description</th>
                            <th>Item Code</th>
                            <th>Brand</th>
                            <th>Supplier</th>
                            <th>Unit</th>
                            <th>Qty</th>
                            <th>Unit Price</th>
                            <th>Total</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php
                    $count = 1;
                    $grand_total = 0;

                    if($items && $items->num_rows > 0){

                        while($row = $items->fetch_assoc()){

                            $total = $row['quantity'] * $row['unit_price'];
                            $grand_total += $total;
                    ?>

                        <tr>
                            <td><?php echo $count++; ?></td>
                            <td><?php echo $row['description']; ?></td>
                            <td><?php echo $row['item_code']; ?></td>
                            <td><?php echo $row['brand']; ?></td>
                            <td><?php echo $row['supplier']; ?></td>
                            <td><?php echo $row['unit']; ?></td>
                            <td><?php echo $row['quantity']; ?></td>
                            <td>₱<?php echo number_format($row['unit_price'],2); ?></td>
                            <td>₱<?php echo number_format($total,2); ?></td>
                        </tr>

                    <?php } }else{ ?>

                        <tr>
                            <td colspan="9" class="text-center text-muted">
                                No purchase items found.
                            </td>
                        </tr>

                    <?php } ?>

                    </tbody>

                    <tfoot>
                        <tr>
                            <th colspan="8" class="text-end">
                                Grand Total
                            </th>

                            <th>
                                ₱<?php echo number_format($grand_total,2); ?>
                            </th>
                        </tr>
                    </tfoot>

                </table>

            </div>

            <hr>

            <h5>Approval / Activity History</h5>

            <div class="table-responsive">

                <table class="table table-bordered align-middle">

                    <thead class="table-dark">
                        <tr>
                            <th>Date/Time</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if($logs && $logs->num_rows > 0){ ?>

                        <?php while($log = $logs->fetch_assoc()){ ?>

                            <tr>
                                <td><?php echo $log['created_at']; ?></td>
                                <td><?php echo $log['fullname']; ?></td>
                                <td><?php echo $log['action']; ?></td>
                                <td><?php echo $log['remarks']; ?></td>
                            </tr>

                        <?php } ?>

                    <?php }else{ ?>

                        <tr>
                            <td colspan="4" class="text-center text-muted">
                                No activity history yet.
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
