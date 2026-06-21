<?php

include '../auth/auth_check.php';

require_role([
    'Logistics',
    'Admin',
    'Boss'
]);

include '../config/database.php';

$id = $_GET['id'];

$task = $conn->query("
SELECT
logistics_tasks.*,
job_orders.jo_no,
job_orders.project_name

FROM logistics_tasks

LEFT JOIN job_orders
ON job_orders.id = logistics_tasks.jo_id

WHERE logistics_tasks.id = '$id'
")->fetch_assoc();

if(!$task){
    die('Logistics task not found.');
}

?>

<!DOCTYPE html>
<html>
<head>

    <title>Upload Delivery Proof</title>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">

</head>

<body class="bg-light">

<div class="container mt-4">

    <div class="card shadow border-0">

        <div class="card-header bg-dark text-white">

            <div class="d-flex justify-content-between align-items-center">

                <h4 class="mb-0">
                    Upload Delivery Proof
                </h4>

                <div>

                    <a href="../dashboard/index.php"
                       class="btn btn-light btn-sm">

                        Dashboard

                    </a>

                    <a href="dashboard.php"
                       class="btn btn-secondary btn-sm">

                        Back

                    </a>

                </div>

            </div>

        </div>

        <div class="card-body">

            <p><b>JO No:</b> <?php echo $task['jo_no']; ?></p>

            <p><b>Project:</b> <?php echo $task['project_name']; ?></p>

            <hr>

            <form action="save_delivery_proof.php"
                  method="POST"
                  enctype="multipart/form-data">

                <input type="hidden"
                       name="task_id"
                       value="<?php echo $task['id']; ?>">

                <div class="mb-3">

                    <label class="fw-bold">
                        Delivery Photo / Proof
                    </label>

                    <input type="file"
                           name="delivery_photo"
                           class="form-control"
                           accept="image/*"
                           capture="environment"
                           required>

                    <small class="text-muted">
                        Mobile devices can directly open the camera.
                    </small>

                </div>

                <div class="mb-3">

                    <label class="fw-bold">
                        Received By
                    </label>

                    <input type="text"
                           name="received_by"
                           class="form-control"
                           required>

                </div>

                <div class="mb-3">

                    <label class="fw-bold">
                        Delivery Remarks
                    </label>

                    <textarea name="remarks"
                              class="form-control"
                              rows="4"></textarea>

                </div>

                <button type="submit"
                        class="btn btn-success">

                    Upload Proof

                </button>

            </form>

        </div>

    </div>

</div>

</body>
</html>