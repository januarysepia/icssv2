<?php

include '../auth/auth_check.php';

require_role([
    'Boss',
    'Admin',
    'Supervisor',
    'Logistics'
]);

include '../config/database.php';

$tasks = $conn->query("
SELECT
logistics_tasks.*,
job_orders.jo_no,
job_orders.client_name,
job_orders.project_name,
users.fullname,
users.employee_no

FROM logistics_tasks

LEFT JOIN job_orders
ON logistics_tasks.jo_id = job_orders.id

LEFT JOIN users
ON logistics_tasks.assigned_logistics_id = users.id

ORDER BY logistics_tasks.id DESC
");

?>

<!DOCTYPE html>
<html>
<head>

    <title>Logistics Tasks</title>

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

                    Logistics Tasks

                </h4>

                <div>

                    <a href="../dashboard/index.php"
                       class="btn btn-light btn-sm">

                        Dashboard

                    </a>

                    <a href="../job_orders/jo_list.php"
                       class="btn btn-secondary btn-sm">

                        Back

                    </a>

                </div>

            </div>

        </div>

        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-bordered table-hover align-middle">

                    <thead class="table-dark">

                        <tr>

                            <th>JO No</th>

                            <th>Client</th>

                            <th>Project</th>

                            <th>Assigned Logistics</th>

                            <th>Status</th>

                            <th>Delivery Date</th>

                            <th>Created</th>

                            <th width="160">Action</th>

                        </tr>

                    </thead>

                    <tbody>

                    <?php while($row = $tasks->fetch_assoc()){ ?>

                        <tr>

                            <td>

                                <b>
                                    <?php echo $row['jo_no']; ?>
                                </b>

                            </td>

                            <td>
                                <?php echo $row['client_name']; ?>
                            </td>

                            <td>
                                <?php echo $row['project_name']; ?>
                            </td>

                            <td>

                                <?php

                                if($row['fullname']){

                                    echo $row['employee_no']
                                    . " - "
                                    . $row['fullname'];

                                }else{

                                    echo "Not Assigned";

                                }

                                ?>

                            </td>

                            <td>

                                <?php if($row['status'] == 'Pending Logistics'){ ?>

                                    <span class="badge bg-warning text-dark">
                                        Pending Logistics
                                    </span>

                                <?php }elseif($row['status'] == 'Preparing'){ ?>

                                    <span class="badge bg-info text-dark">
                                        Preparing
                                    </span>

                                <?php }elseif($row['status'] == 'Dispatched'){ ?>

                                    <span class="badge bg-primary">
                                        Dispatched
                                    </span>

                                <?php }elseif($row['status'] == 'Delivered'){ ?>

                                    <span class="badge bg-success">
                                        Delivered
                                    </span>

                                <?php }else{ ?>

                                    <span class="badge bg-secondary">
                                        <?php echo $row['status']; ?>
                                    </span>

                                <?php } ?>

                            </td>

                            <td>
                                <?php echo $row['delivery_date']; ?>
                            </td>

                            <td>
                                <?php echo $row['created_at']; ?>
                            </td>

                            <td>

                                <a href="prepare_delivery.php?id=<?php echo $row['id']; ?>"
                                   class="btn btn-primary btn-sm">

                                    Open

                                </a>

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