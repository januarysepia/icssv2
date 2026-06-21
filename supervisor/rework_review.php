<?php

include '../auth/auth_check.php';

require_role([
    'Boss',
    'Admin',
    'Supervisor'
]);

include '../config/database.php';

$jo_id = intval($_GET['jo_id']);

$qa = $conn->query("
SELECT
qaqc_tasks.*,
job_orders.jo_no,
job_orders.client_name,
job_orders.project_name,
job_orders.engineer_name

FROM qaqc_tasks

LEFT JOIN job_orders
ON job_orders.id = qaqc_tasks.jo_id

WHERE qaqc_tasks.jo_id = '$jo_id'
ORDER BY qaqc_tasks.id DESC
LIMIT 1
")->fetch_assoc();

if(!$qa){
    die('QA record not found.');
}

/*
GET WORKFLOW DEPARTMENTS USED IN THIS JO
ONLY SHOW VALID DEPARTMENTS
*/

$departments = $conn->query("
SELECT DISTINCT
departments.id,
departments.department_name

FROM job_workflow_steps

INNER JOIN users
ON users.id = job_workflow_steps.assigned_user_id

INNER JOIN departments
ON departments.id = users.department_id

WHERE job_workflow_steps.jo_id = '$jo_id'

ORDER BY job_workflow_steps.step_order
");

?>

<!DOCTYPE html>
<html>
<head>

    <title>Supervisor Rework Review</title>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">

</head>

<body class="bg-light">

<div class="container mt-4">

    <div class="card shadow">

        <div class="card-header bg-danger text-white">

            <div class="d-flex justify-content-between">

                <h4>
                    QA Failed Review - <?php echo $qa['jo_no']; ?>
                </h4>

                <a href="../job_orders/view_jo.php?id=<?php echo $jo_id; ?>"
                   class="btn btn-light btn-sm">

                    Back

                </a>

            </div>

        </div>

        <div class="card-body">

            <div class="row">

                <div class="col-md-6">

                    <p>
                        <strong>JO No:</strong>
                        <?php echo $qa['jo_no']; ?>
                    </p>

                    <p>
                        <strong>Client:</strong>
                        <?php echo $qa['client_name']; ?>
                    </p>

                    <p>
                        <strong>Project:</strong>
                        <?php echo $qa['project_name']; ?>
                    </p>

                </div>

                <div class="col-md-6">

                    <p>
                        <strong>Engineer:</strong>
                        <?php echo $qa['engineer_name']; ?>
                    </p>

                    <p>
                        <strong>QA Status:</strong>

                        <span class="badge bg-danger">
                            <?php echo $qa['status']; ?>
                        </span>

                    </p>

                </div>

            </div>

            <hr>

            <div class="alert alert-danger">

                <h5>Failure Reason</h5>

                <?php echo nl2br($qa['failure_reason']); ?>

            </div>

            <form action="save_rework_assignment.php"
                  method="POST">

                <input type="hidden"
                       name="jo_id"
                       value="<?php echo $jo_id; ?>">

                <input type="hidden"
                       name="qaqc_task_id"
                       value="<?php echo $qa['id']; ?>">

                <div class="mb-3">

                    <label class="form-label fw-bold">

                        Select Rework Department

                    </label>

                    <select name="department_id"
                            class="form-control"
                            required>

                        <option value="">
                            Select Department
                        </option>

                        <?php while($dept = $departments->fetch_assoc()){ ?>

                            <option value="<?php echo $dept['id']; ?>">

                                <?php echo $dept['department_name']; ?>

                            </option>

                        <?php } ?>

                    </select>

                </div>

                <div class="mb-3">

                    <label class="form-label fw-bold">

                        Supervisor Remarks

                    </label>

                    <textarea name="remarks"
                              class="form-control"
                              rows="4"></textarea>

                </div>

                <button type="submit"
                        class="btn btn-danger">

                    Create Rework Task

                </button>

            </form>

        </div>

    </div>

</div>

</body>
</html>