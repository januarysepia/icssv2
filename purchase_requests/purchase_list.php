<?php

include '../auth/auth_check.php';

require_role([
    'Purchasing',
    'Boss',
    'Admin'
]);

include '../config/database.php';

$role = $_SESSION['system_role'] ?? $_SESSION['role'] ?? '';

$purchases = $conn->query("
SELECT
purchase_requests.*,
users.fullname

FROM purchase_requests

LEFT JOIN users
ON users.id = purchase_requests.requested_by

ORDER BY purchase_requests.id DESC
");

?>

<!DOCTYPE html>
<html>
<head>

    <title>Purchase Requests</title>

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

                    Purchase Requests

                </h4>

                <div>

                    <a href="../dashboard/index.php"
                       class="btn btn-light btn-sm">

                        Dashboard

                    </a>

                    <?php if(
                        $role == 'Purchasing' ||
                        $role == 'Admin'
                    ){ ?>

                        <a href="create_purchase.php"
                           class="btn btn-success btn-sm">

                            + Create Purchase

                        </a>

                    <?php } ?>

                </div>

            </div>

        </div>

        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-bordered table-hover align-middle">

                    <thead class="table-dark">

                        <tr>

                            <th>ID</th>

                            <th>Purchase No</th>

                            <th>Requested By</th>

                            <th>Date Created</th>

                            <th>Status</th>

                            <th>Total Amount</th>

                            <th width="260">Action</th>

                        </tr>

                    </thead>

                    <tbody>

                    <?php while($row = $purchases->fetch_assoc()){ ?>

                        <?php

                        /*
                        COMPUTE TOTAL
                        */

                        $purchase_id = $row['id'];

                        $total_query = $conn->query("
                        SELECT
                        SUM(quantity * unit_price) AS grand_total
                        FROM purchase_request_items
                        WHERE purchase_request_id = '$purchase_id'
                        ");

                        $total_data = $total_query->fetch_assoc();

                        $grand_total = $total_data['grand_total'] ?? 0;

                        ?>

                        <tr>

                            <td>
                                <?php echo $row['id']; ?>
                            </td>

                            <td>
                                <b>
                                    <?php echo $row['purchase_no']; ?>
                                </b>
                            </td>

                            <td>
                                <?php echo $row['fullname']; ?>
                            </td>

                            <td>
                                <?php echo $row['created_at']; ?>
                            </td>

                            <td>

                                <?php if($row['status'] == 'For Boss Approval'){ ?>

                                    <span class="badge bg-warning text-dark">
                                        For Boss Approval
                                    </span>

                                <?php }elseif($row['status'] == 'Boss Approved'){ ?>

                                    <span class="badge bg-success">
                                        Boss Approved
                                    </span>

                                <?php }elseif($row['status'] == 'Boss Rejected'){ ?>

                                    <span class="badge bg-danger">
                                        Boss Rejected
                                    </span>

                                <?php }elseif($row['status'] == 'Waiting Delivery'){ ?>

                                    <span class="badge bg-secondary">
                                        Waiting Delivery
                                    </span>

                                <?php }elseif($row['status'] == 'Received'){ ?>

                                    <span class="badge bg-primary">
                                        Received
                                    </span>

                                <?php }else{ ?>

                                    <span class="badge bg-dark">
                                        <?php echo $row['status']; ?>
                                    </span>

                                <?php } ?>

                            </td>

                            <td>
                                ₱ <?php echo number_format($grand_total,2); ?>
                            </td>

                            <td>

                                <a href="view_purchase.php?id=<?php echo $row['id']; ?>"
                                   class="btn btn-primary btn-sm">

                                    View

                                </a>

                                <?php if(
                                    $role == 'Boss'
                                    &&
                                    $row['status'] == 'For Boss Approval'
                                ){ ?>

                                    <form action="boss_approve.php" method="POST" class="d-inline">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>">
                                        <button type="submit" class="btn btn-success btn-sm">Approve</button>
                                    </form>

                                    <form action="boss_reject.php" method="POST" class="d-inline">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">Reject</button>
                                    </form>

                                <?php } ?>

                                <?php if(
                                    ($role == 'Purchasing' || $role == 'Admin')
                                    &&
                                    $row['status'] == 'Boss Approved'
                                ){ ?>

                                    <a href="receive_items.php?id=<?php echo $row['id']; ?>"
                                       class="btn btn-warning btn-sm">

                                        Receive

                                    </a>

                                <?php } ?>

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
