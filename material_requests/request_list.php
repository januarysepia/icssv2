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

$role = '';

if(isset($_SESSION['role'])){
    $role = $_SESSION['role'];
}

if(isset($_SESSION['system_role'])){
    $role = $_SESSION['system_role'];
}

$requests = $conn->query("
SELECT
material_requests.*,
job_orders.jo_no,
job_orders.project_name,
users.fullname,
(
    SELECT COUNT(*)
    FROM material_request_items mri
    WHERE mri.request_id = material_requests.id
      AND mri.supplier_review_status = 'Pending'
) AS pending_supplier_count

FROM material_requests

LEFT JOIN job_orders
ON job_orders.id = material_requests.jo_id

LEFT JOIN users
ON users.id = material_requests.requested_by

ORDER BY material_requests.id DESC
");

?>

<!DOCTYPE html>
<html>
<head>
    <title>Material Request List</title>

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
                    Material Requests
                </h4>

                <div>
                    <a href="../dashboard/index.php"
                       class="btn btn-light btn-sm">
                        Dashboard
                    </a>

                    <?php if(
                        $role == 'Technical' ||
                        $role == 'Admin' ||
                        $role == 'Boss'
                    ){ ?>

                        <a href="create_request.php"
                           class="btn btn-success btn-sm">
                            + Create Request
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
                            <th>Request No</th>
                            <th>JO No</th>
                            <th>Project</th>
                            <th>Requested By</th>
                            <th>Request Date</th>
                            <th>Context</th>
                            <th>Status</th>
                            <th width="300">Action</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php while($row = $requests->fetch_assoc()){ ?>

                        <tr>

                            <td><?php echo $row['id']; ?></td>

                            <td>
                                <b><?php echo $row['request_no']; ?></b>
                            </td>

                            <td><?php echo $row['jo_no']; ?></td>

                            <td><?php echo $row['project_name']; ?></td>

                            <td><?php echo $row['fullname']; ?></td>

                            <td><?php echo $row['request_date']; ?></td>

                            <td>
                                <?php if (($row['request_context'] ?? 'Ongoing JO') === 'After Delivery / Correction'): ?>
                                    <span class="badge bg-warning text-dark">After Delivery / Correction</span>
                                <?php else: ?>
                                    <span class="badge bg-primary">Ongoing JO</span>
                                <?php endif; ?>
                            </td>

                            <td>

                                <?php

                                $status = $row['status'];

                                    if($status == 'Pending Review'){

                                        echo "<span class='badge bg-warning text-dark'>Pending Review</span>";

                                    }elseif($status == 'Approved - For Issue'){

                                        echo "<span class='badge bg-success'>Approved - For Issue</span>";

                                    }elseif($status == 'Approved - To Purchase'){

                                        echo "<span class='badge bg-danger'>Approved - To Purchase</span>";

                                    }elseif($status == 'Partially Approved'){

                                        echo "<span class='badge bg-primary'>Partially Approved</span>";

                                    }elseif($status == 'Pending Purchase'){

                                        echo "<span class='badge bg-danger'>Pending Purchase</span>";

                                    }elseif($status == 'Purchase Request Created'){

                                        echo "<span class='badge bg-dark'>Purchase Request Created</span>";

                                    }elseif($status == 'Waiting Delivery'){

                                        echo "<span class='badge bg-secondary'>Waiting Delivery</span>";

                                    }elseif($status == 'Partially Released'){

                                        echo "<span class='badge bg-info text-dark'>Partially Released</span>";

                                    }elseif($status == 'Partially Released / Pending Purchase'){

                                        echo "<span class='badge bg-primary'>Partially Released / Pending Purchase</span>";

                                    }elseif($status == 'Partially Received'){

                                        echo "<span class='badge bg-secondary'>Partially Received</span>";

                                    }elseif($status == 'Released to Production'){

                                        echo "<span class='badge bg-success'>Released to Production</span>";

                                    }elseif($status == 'Completed'){

                                        echo "<span class='badge bg-success'>Completed</span>";

                                    }elseif($status == 'Cancelled'){

                                        echo "<span class='badge bg-danger'>Cancelled</span>";

                                    }else{

                                        echo "<span class='badge bg-light text-dark'>" . $status . "</span>";

                                    }

                                ?>

                                <?php if ((int) $row['pending_supplier_count'] > 0): ?>
                                    <div class="mt-1">
                                        <span class="badge bg-warning text-dark">
                                            Supplier Review: <?= (int) $row['pending_supplier_count'] ?>
                                        </span>
                                    </div>
                                <?php endif; ?>

                            </td>

                            <td>

                                <a href="view_request.php?id=<?php echo $row['id']; ?>"
                                   class="btn btn-primary btn-sm">
                                    View
                                </a>

                                

                                <?php if(
                                    $role == 'Purchasing'
                                    &&
                                    (
                                        $row['status'] == 'Approved - For Issue'
                                        ||
                                        $row['status'] == 'Partially Approved'
                                        ||
                                        $row['status'] == 'Partially Released'
                                        ||
                                        $row['status'] == 'Partially Released / Pending Purchase'
                                    )
                                ){ ?>

                                    <a href="issue_materials.php?id=<?php echo $row['id']; ?>"
                                       class="btn btn-success btn-sm">
                                        Issue
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
