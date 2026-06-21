<?php

include '../auth/auth_check.php';

require_role([
    'Production',
    'Boss',
    'Admin',
    'Supervisor'
]);

include '../config/database.php';

$id = intval($_GET['id']);

$user_id = $_SESSION['user_id'];
$role = $_SESSION['system_role'] ?? $_SESSION['role'] ?? '';

$rework = $conn->query("
SELECT
rework_tasks.*,
job_orders.jo_no,
job_orders.client_name,
job_orders.project_name,
job_orders.engineer_name,
job_orders.due_date,
departments.department_name,
users.fullname,
users.employee_no

FROM rework_tasks

LEFT JOIN job_orders
ON job_orders.id = rework_tasks.jo_id

LEFT JOIN departments
ON departments.id = rework_tasks.department_id

LEFT JOIN users
ON users.id = rework_tasks.assigned_user_id

WHERE rework_tasks.id = '$id'
")->fetch_assoc();

if(!$rework){
    die('Rework task not found.');
}

if($role == 'Production' && $rework['assigned_user_id'] != $user_id){
    die('Access Denied. This rework task is not assigned to you.');
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>Rework Task</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">

<?php include '../dashboard/sidebar.php'; ?>

<div class="content-wrapper">

<?php include '../dashboard/header.php'; ?>

<div class="container-fluid mt-4">

    <div class="card shadow border-0">

        <div class="card-header bg-danger text-white">

            <div class="d-flex justify-content-between align-items-center">

                <h4 class="mb-0">
                    Rework Task - <?php echo h($rework['jo_no']); ?>
                </h4>

                <a href="my_tasks.php" class="btn btn-light btn-sm">
                    Back
                </a>

            </div>

        </div>

        <div class="card-body">

            <div class="row mb-3">

                <div class="col-md-6">
                    <p><b>JO No:</b> <?php echo h($rework['jo_no']); ?></p>
                    <p><b>Client:</b> <?php echo h($rework['client_name']); ?></p>
                    <p><b>Project:</b> <?php echo h($rework['project_name']); ?></p>
                    <p><b>Engineer:</b> <?php echo h($rework['engineer_name']); ?></p>
                </div>

                <div class="col-md-6">
                    <p><b>Rework Department:</b> <?php echo h($rework['department_name']); ?></p>
                    <p>
                        <b>Assigned To:</b>
                        <?php
                        echo h(!empty($rework['fullname'])
                        ? $rework['employee_no'] . ' - ' . $rework['fullname']
                        : 'Not Assigned');
                        ?>
                    </p>
                    <p><b>Due Date:</b> <?php echo h($rework['due_date']); ?></p>
                    <p>
                        <b>Status:</b>
                        <span class="badge bg-dark">
                            <?php echo h($rework['status']); ?>
                        </span>
                    </p>
                </div>

            </div>

            <hr>

            <div class="alert alert-warning">
                <b>Failure Reason:</b><br>
                <?php echo nl2br(h($rework['failure_reason'])); ?>
            </div>

            <h5>Rework Timeline</h5>

            <div class="table-responsive mb-4">

                <table class="table table-bordered align-middle">

                    <thead class="table-dark">
                        <tr>
                            <th>Acknowledged At</th>
                            <th>Started At</th>
                            <th>Completed At</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr>
                            <td><?php echo $rework['acknowledged_at']; ?></td>
                            <td><?php echo $rework['started_at']; ?></td>
                            <td><?php echo $rework['completed_at']; ?></td>
                        </tr>
                    </tbody>

                </table>

            </div>

            <hr>

            <h5>Actions</h5>

            <?php if($role == 'Production'){ ?>

                <?php if($rework['status'] == 'Pending'){ ?>

                    <form action="update_rework.php" method="POST" class="d-inline"
                          onsubmit="return confirm('Acknowledge this rework task?');">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="id" value="<?php echo (int) $rework['id']; ?>">
                        <input type="hidden" name="action" value="acknowledge">
                        <button type="submit" class="btn btn-info">Acknowledge</button>
                    </form>

                <?php } ?>

                <?php if($rework['status'] == 'Acknowledged'){ ?>

                    <form action="update_rework.php" method="POST" class="d-inline"
                          onsubmit="return confirm('Start this rework task?');">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="id" value="<?php echo (int) $rework['id']; ?>">
                        <input type="hidden" name="action" value="start">
                        <button type="submit" class="btn btn-primary">Start Rework</button>
                    </form>

                <?php } ?>

                <?php if($rework['status'] == 'In Progress'){ ?>

                    <form action="update_rework.php" method="POST" class="d-inline"
                          onsubmit="return confirm('Complete this rework task?');">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="id" value="<?php echo (int) $rework['id']; ?>">
                        <input type="hidden" name="action" value="complete">
                        <button type="submit" class="btn btn-success">Complete Rework</button>
                    </form>

                <?php } ?>

                <?php if($rework['status'] == 'Completed'){ ?>

                    <div class="alert alert-success">
                        Rework completed. JO is now ready for QA re-inspection.
                    </div>

                <?php } ?>

            <?php }else{ ?>

                <div class="alert alert-info">
                    Read-only view. Only assigned production user can update this rework task.
                </div>

            <?php } ?>

        </div>

    </div>

</div>

</div>

</body>
</html>
