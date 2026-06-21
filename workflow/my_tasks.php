<?php

include '../auth/auth_check.php';
include '../config/database.php';

$user_id = $_SESSION['user_id'];

$tasks = $conn->query("
SELECT
job_workflow_steps.*,
job_orders.jo_no,
job_orders.client_name,
job_orders.project_name,
job_orders.due_date,
departments.department_name

FROM job_workflow_steps

LEFT JOIN job_orders
ON job_orders.id = job_workflow_steps.jo_id

LEFT JOIN departments
ON departments.id = job_workflow_steps.department_id

WHERE job_workflow_steps.assigned_user_id = '$user_id'
AND job_workflow_steps.status != 'Completed'
AND job_orders.workflow_status != 'Completed'

ORDER BY
    CASE job_workflow_steps.status
        WHEN 'In Progress' THEN 1
        WHEN 'Acknowledged' THEN 2
        WHEN 'Pending' THEN 3
        ELSE 4
    END,
    job_orders.due_date ASC,
    job_workflow_steps.id DESC
");

?>

<!DOCTYPE html>
<html>
<head>
    <title>My Production Tasks</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">

<div class="container-fluid mt-4">

    <div class="card shadow border-0">

        <div class="card-header bg-dark text-white">

            <div class="d-flex justify-content-between align-items-center">

                <h4 class="mb-0">My Production Tasks</h4>

                <div>
                    <a href="../dashboard/index.php" class="btn btn-light btn-sm">
                        Dashboard
                    </a>

                    <a href="../logout.php" class="btn btn-secondary btn-sm">
                        Logout
                    </a>
                </div>

            </div>

        </div>

        <div class="card-body">

            <table class="table table-bordered table-hover align-middle">

                <thead class="table-dark">
                    <tr>
                        <th>JO No</th>
                        <th>Client</th>
                        <th>Project</th>
                        <th>Department</th>
                        <th>Due Date</th>
                        <th>Status</th>
                        <th width="220">Action</th>
                    </tr>
                </thead>

                <tbody>

                <?php if ($tasks && $tasks->num_rows > 0): ?>
                <?php while($row = $tasks->fetch_assoc()){ ?>

                    <tr>
                        <td><b><?php echo h($row['jo_no']); ?></b></td>
                        <td><?php echo h($row['client_name']); ?></td>
                        <td><?php echo h($row['project_name']); ?></td>
                        <td><?php echo h($row['department_name']); ?></td>
                        <td><?php echo h($row['due_date']); ?></td>

                        <td>
                            <?php if($row['status'] == 'Pending'){ ?>
                                <span class="badge bg-warning text-dark">Pending</span>
                            <?php }elseif($row['status'] == 'Acknowledged'){ ?>
                                <span class="badge bg-info text-dark">Acknowledged</span>
                            <?php }elseif($row['status'] == 'In Progress'){ ?>
                                <span class="badge bg-primary">In Progress</span>
                            <?php }elseif($row['status'] == 'Completed'){ ?>
                                <span class="badge bg-success">Completed</span>
                            <?php }else{ ?>
                                <span class="badge bg-secondary"><?php echo h($row['status']); ?></span>
                            <?php } ?>
                        </td>

                        <td>
                            <?php if($row['status'] == 'Pending'){ ?>

                                <form action="update_task.php" method="POST" class="d-inline">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>">
                                    <input type="hidden" name="action" value="acknowledge">
                                    <button type="submit" class="btn btn-warning btn-sm">Acknowledge</button>
                                </form>

                            <?php }elseif($row['status'] == 'Acknowledged'){ ?>

                                <form action="update_task.php" method="POST" class="d-inline">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>">
                                    <input type="hidden" name="action" value="start">
                                    <button type="submit" class="btn btn-primary btn-sm">Start Work</button>
                                </form>

                            <?php }elseif($row['status'] == 'In Progress'){ ?>

                                <form action="update_task.php" method="POST" class="d-inline">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>">
                                    <input type="hidden" name="action" value="complete">
                                    <button type="submit" class="btn btn-success btn-sm">Complete</button>
                                </form>

                            <?php } ?>
                        </td>
                    </tr>

                <?php } ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">
                            No active production tasks.
                        </td>
                    </tr>
                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

</body>
</html>
