<?php

include '../auth/auth_check.php';

require_role([
    'Boss',
    'Admin',
    'Supervisor',
    'Engineer'
]);

include '../config/database.php';

$tasks = $conn->query("
SELECT
qaqc_tasks.*,
job_orders.jo_no,
job_orders.project_name,
job_orders.client_name,
users.fullname AS engineer_name,
users.employee_no

FROM qaqc_tasks

LEFT JOIN job_orders
ON job_orders.id = qaqc_tasks.jo_id

LEFT JOIN users
ON users.id = qaqc_tasks.assigned_engineer_id

ORDER BY qaqc_tasks.id DESC
");

?>

<!DOCTYPE html>
<html>
<head>
    <title>QA/QC Tasks</title>
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
                    Engineering QA/QC Tasks
                </h4>

                <div>
                    <a href="../dashboard/index.php" class="btn btn-light btn-sm">
                        Dashboard
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
                            <th>Assigned Engineer</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th width="160">Action</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php while($row = $tasks->fetch_assoc()){ ?>

                        <tr>

                            <td>
                                <b><?php echo $row['jo_no']; ?></b>
                            </td>

                            <td><?php echo $row['client_name']; ?></td>

                            <td><?php echo $row['project_name']; ?></td>

                            <td>
                                <?php
                                if($row['engineer_name']){
                                    echo $row['employee_no'] . " - " . $row['engineer_name'];
                                }else{
                                    echo "Not Assigned";
                                }
                                ?>
                            </td>

                            <td>
                                <?php if($row['status'] == 'Pending QA'){ ?>
                                    <span class="badge bg-warning text-dark">Pending QA</span>
                                <?php }elseif($row['status'] == 'Acknowledged'){ ?>
                                    <span class="badge bg-info text-dark">Acknowledged</span>
                                <?php }elseif($row['status'] == 'Inspecting'){ ?>
                                    <span class="badge bg-primary">Inspecting</span>
                                <?php }elseif($row['status'] == 'Passed'){ ?>
                                    <span class="badge bg-success">Passed</span>
                                <?php }elseif($row['status'] == 'Failed'){ ?>
                                    <span class="badge bg-danger">Failed</span>
                                <?php }else{ ?>
                                    <span class="badge bg-secondary"><?php echo $row['status']; ?></span>
                                <?php } ?>
                            </td>

                            <td><?php echo $row['created_at']; ?></td>

                            <td>
                                <a href="inspect_jo.php?id=<?php echo $row['id']; ?>"
                                   class="btn btn-primary btn-sm">

                                    Inspect

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