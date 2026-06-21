<?php

include '../auth/auth_check.php';

require_role([
    'Boss',
    'Admin',
    'Supervisor',
    'Engineer'
]);

include '../config/database.php';

$id = intval($_GET['id'] ?? 0);

$task = $conn->query("
SELECT  
    qaqc_tasks.*,
    job_orders.jo_no,
    job_orders.client_name,
    job_orders.project_name,
    job_orders.engineer_name,
    job_orders.due_date,
    users.fullname AS engineer_fullname,
    users.employee_no

FROM qaqc_tasks

LEFT JOIN job_orders
ON job_orders.id = qaqc_tasks.jo_id

LEFT JOIN users
ON users.id = qaqc_tasks.assigned_engineer_id

WHERE qaqc_tasks.id = '$id'
")->fetch_assoc();

if(!$task){
    die("QA/QC task not found.");
}

$is_view_only = false;

if(
    $task['status'] == 'Passed'
    ||
    $task['status'] == 'Failed'
){
    $is_view_only = true;
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>Inspect JO</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <script>
        function toggleReworkFields(){

            const status = document.getElementById('status').value;
            const reworkBox = document.getElementById('reworkBox');
            const failureReason = document.getElementById('failure_reason');

            if(status === 'Failed'){
                reworkBox.style.display = 'block';
                failureReason.required = true;
            }else{
                reworkBox.style.display = 'none';
                failureReason.required = false;
            }
        }
    </script>
</head>

<body class="bg-light">

<div class="container mt-4 mb-5">

    <div class="card shadow border-0">

        <div class="card-header bg-dark text-white">

            <div class="d-flex justify-content-between align-items-center">

                <h4 class="mb-0">
                    QA/QC Inspection - <?php echo $task['jo_no']; ?>
                </h4>

                <div>
                    <a href="../dashboard/index.php" class="btn btn-light btn-sm">
                        Dashboard
                    </a>

                    <a href="qaqc_list.php" class="btn btn-secondary btn-sm">
                        Back
                    </a>
                </div>

            </div>

        </div>

        <div class="card-body">

            <?php if($is_view_only){ ?>

                <div class="alert alert-info">
                    This QA/QC inspection has already been completed.
                    Result:
                    <b><?php echo $task['status']; ?></b>
                    . This record is now view-only.
                </div>

            <?php } ?>

            <div class="row mb-3">

                <div class="col-md-6">
                    <p><b>Client:</b> <?php echo $task['client_name']; ?></p>
                    <p><b>Project:</b> <?php echo $task['project_name']; ?></p>
                    <p><b>Engineer:</b> <?php echo $task['engineer_name']; ?></p>
                </div>

                <div class="col-md-6">
                    <p>
                        <b>Assigned QA Engineer:</b>
                        <?php echo $task['employee_no']; ?> -
                        <?php echo $task['engineer_fullname']; ?>
                    </p>

                    <p><b>Due Date:</b> <?php echo $task['due_date']; ?></p>

                    <p>
                        <b>Status:</b>

                        <?php if($task['status'] == 'Passed'){ ?>

                            <span class="badge bg-success">
                                Passed
                            </span>

                        <?php }elseif($task['status'] == 'Failed'){ ?>

                            <span class="badge bg-danger">
                                Failed
                            </span>

                        <?php }elseif($task['status'] == 'Inspecting'){ ?>

                            <span class="badge bg-primary">
                                Inspecting
                            </span>

                        <?php }elseif($task['status'] == 'Acknowledged'){ ?>

                            <span class="badge bg-info text-dark">
                                Acknowledged
                            </span>

                        <?php }elseif($task['status'] == 'For Reinspection'){ ?>

                            <span class="badge bg-warning text-dark">
                                For Reinspection
                            </span>

                        <?php }else{ ?>

                            <span class="badge bg-dark">
                                <?php echo $task['status']; ?>
                            </span>

                        <?php } ?>

                    </p>
                </div>

            </div>

            <?php if(!empty($task['failure_reason'])){ ?>

                <div class="alert alert-danger">
                    <b>Failure Reason:</b><br>
                    <?php echo nl2br($task['failure_reason']); ?>
                </div>

            <?php } ?>

            <hr>

            <form action="save_qaqc.php" method="POST">

                <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">

                <div class="mb-3">
                    <label class="fw-bold">Inspection Result</label>

                    <?php if($is_view_only){ ?>

                        <input type="text"
                               class="form-control"
                               value="<?php echo $task['status']; ?>"
                               readonly>

                    <?php }else{ ?>

                        <select name="status"
                                id="status"
                                class="form-control"
                                onchange="toggleReworkFields()"
                                required>

                            <option value="">
                                Select Result
                            </option>

                            <?php if($task['status'] == 'Pending QA'){ ?>

                                <option value="Acknowledged">
                                    Acknowledge QA
                                </option>

                            <?php } ?>

                            <?php if($task['status'] == 'Acknowledged'){ ?>

                                <option value="Inspecting">
                                    Start Inspecting
                                </option>

                            <?php } ?>

                            <?php if($task['status'] == 'Inspecting'){ ?>

                                <option value="Passed">
                                    Passed
                                </option>

                                <option value="Failed">
                                    Failed / Send to Rework
                                </option>

                            <?php } ?>

                            <?php if($task['status'] == 'For Reinspection'){ ?>

                                <option value="Inspecting">
                                    Start Reinspection
                                </option>

                            <?php } ?>

                        </select>

                    <?php } ?>
                </div>

                <div class="mb-3">
                    <label class="fw-bold">Remarks</label>

                    <textarea name="remarks"
                              class="form-control"
                              rows="4"
                              placeholder="Inspection remarks, findings, corrections needed..."
                              <?php echo $is_view_only ? 'readonly' : ''; ?>><?php echo $task['remarks']; ?></textarea>
                </div>

                <?php if(!$is_view_only){ ?>

                    <div id="reworkBox" style="display:none;">

                        <div class="alert alert-danger">
                            QA Failed. Please provide the failure reason.
                            The Supervisor will review and assign the proper rework department.
                        </div>

                        <div class="mb-3">
                            <label class="fw-bold">Failure Reason</label>

                            <textarea name="failure_reason"
                                      id="failure_reason"
                                      class="form-control"
                                      rows="5"
                                      placeholder="Example: Paint color mismatch, missing component, loose wiring, incorrect dimensions..."></textarea>
                        </div>

                    </div>

                    <button type="submit" class="btn btn-success">
                        Save QA/QC
                    </button>

                <?php } ?>

            </form>

        </div>

    </div>

</div>

</body>
</html>