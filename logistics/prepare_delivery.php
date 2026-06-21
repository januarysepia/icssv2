<?php

include '../auth/auth_check.php';

require_role([
    'Boss',
    'Admin',
    'Supervisor',
    'Logistics'
]);

include '../config/database.php';

$id = intval($_GET['id']);

$task = $conn->query("
SELECT
logistics_tasks.*,
job_orders.jo_no,
job_orders.client_name,
job_orders.project_name,
job_orders.engineer_name,
job_orders.due_date,
users.fullname,
users.employee_no

FROM logistics_tasks

LEFT JOIN job_orders
ON logistics_tasks.jo_id = job_orders.id

LEFT JOIN users
ON logistics_tasks.assigned_logistics_id = users.id

WHERE logistics_tasks.id = '$id'
")->fetch_assoc();

if(!$task){
    die("Logistics task not found.");
}

$is_view_only = false;
$is_info_locked = false;

if($task['status'] == 'Delivered'){
    $is_view_only = true;
}

if($task['status'] == 'Dispatched'){
    $is_info_locked = true;
}

$logs = $conn->query("
SELECT
logistics_logs.*,
users.fullname

FROM logistics_logs

LEFT JOIN users
ON users.id = logistics_logs.updated_by

WHERE logistics_logs.logistics_task_id = '$id'

ORDER BY logistics_logs.id DESC
");

?>

<!DOCTYPE html>
<html>
<head>
    <title>Prepare Delivery</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">

<div class="container mt-4 mb-5">

    <div class="card shadow border-0">

        <div class="card-header bg-dark text-white">
            <div class="d-flex justify-content-between align-items-center">

                <h4 class="mb-0">
                    Logistics Delivery - <?php echo $task['jo_no']; ?>
                </h4>

                <div>
                    <a href="../dashboard/index.php" class="btn btn-light btn-sm">
                        Dashboard
                    </a>

                    <a href="logistics_list.php" class="btn btn-secondary btn-sm">
                        Back
                    </a>
                </div>

            </div>
        </div>

        <div class="card-body">

            <?php if($is_view_only){ ?>

                <div class="alert alert-warning">
                    This logistics task is already
                    <b><?php echo $task['status']; ?></b>.
                    Delivery information can no longer be edited.
                </div>

            <?php } ?>

            <div class="row mb-3">

                <div class="col-md-6">
                    <p><b>Client:</b> <?php echo $task['client_name']; ?></p>
                    <p><b>Project:</b> <?php echo $task['project_name']; ?></p>
                    <p><b>Engineer:</b> <?php echo $task['engineer_name']; ?></p>
                </div>

                <div class="col-md-6">
                    <p><b>Assigned Logistics:</b>
                        <?php
                        if($task['fullname']){
                            echo $task['employee_no'] . " - " . $task['fullname'];
                        }else{
                            echo "Not Assigned";
                        }
                        ?>
                    </p>

                    <p><b>Due Date:</b> <?php echo $task['due_date']; ?></p>
                    <p><b>Status:</b> <?php echo $task['status']; ?></p>
                </div>

            </div>

            <hr>

            <form action="save_logistics.php"
                  method="POST"
                  enctype="multipart/form-data">

                <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">

                <div class="row">

                    <div class="col-md-6 mb-3">
                        <label class="fw-bold">Logistics Status</label>

                        <select name="status"
                                class="form-control"
                                <?php echo $is_view_only ? 'disabled' : 'required'; ?>>

                            <option value="">Select Status</option>

                            <?php if($task['status'] == 'Pending Logistics'){ ?>
                                <option value="Preparing">Preparing</option>
                            <?php } ?>

                            <?php if($task['status'] == 'Preparing'){ ?>
                                <option value="Dispatched">Dispatched</option>
                            <?php } ?>

                            <?php if($task['status'] == 'Dispatched'){ ?>
                                <option value="Delivered" selected>Delivered</option>
                            <?php } ?>

                            <?php if($task['status'] == 'Delivered'){ ?>
                                <option value="Delivered" selected>Delivered</option>
                            <?php } ?>

                        </select>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="fw-bold">Delivery Date</label>

                        <input type="date"
                               name="delivery_date"
                               class="form-control"
                               value="<?php echo $task['delivery_date']; ?>"
                               <?php echo ($is_view_only || $is_info_locked) ? 'readonly' : ''; ?>>
                    </div>

                </div>

                <div class="row">

                    <div class="col-md-4 mb-3">
                        <label class="fw-bold">Vehicle Information</label>

                        <input type="text"
                               name="vehicle_info"
                               class="form-control"
                               placeholder="Truck / Vehicle"
                               value="<?php echo $task['vehicle_info']; ?>"
                               <?php echo ($is_view_only || $is_info_locked) ? 'readonly' : ''; ?>>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="fw-bold">Driver Name</label>

                        <input type="text"
                               name="driver_name"
                               class="form-control"
                               placeholder="Driver"
                               value="<?php echo $task['driver_name']; ?>"
                               <?php echo ($is_view_only || $is_info_locked) ? 'readonly' : ''; ?>>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="fw-bold">Driver Mobile Number</label>

                        <input type="text"
                               name="driver_mobile"
                               class="form-control"
                               placeholder="09XXXXXXXXX"
                               value="<?php echo $task['driver_mobile'] ?? ''; ?>"
                               <?php echo ($is_view_only || $is_info_locked) ? 'readonly' : ''; ?>>
                    </div>

                </div>

                <div class="mb-3">
                    <label class="fw-bold">Delivery Address</label>

                    <textarea name="delivery_address"
                              class="form-control"
                              rows="3"
                              placeholder="Delivery Address"
                              <?php echo ($is_view_only || $is_info_locked) ? 'readonly' : ''; ?>><?php echo $task['delivery_address']; ?></textarea>
                </div>

                <div class="mb-3">
                    <label class="fw-bold">Remarks</label>

                    <textarea name="remarks"
                              class="form-control"
                              rows="4"
                              placeholder="Logistics remarks"
                              <?php echo ($is_view_only || $is_info_locked) ? 'readonly' : ''; ?>><?php echo $task['remarks']; ?></textarea>
                </div>

                <div class="mb-3">
                    <label class="fw-bold">Delivery Photo / Proof</label>

                    <?php if(!$is_view_only){ ?>

                        <input type="file"
                               name="delivery_photo"
                               class="form-control"
                               accept="image/*"
                               capture="environment">

                        <small class="text-muted">
                            On mobile, this can open the camera directly for proof of delivery.
                        </small>

                    <?php }else{ ?>

                        <div class="form-control bg-light">
                            Upload disabled because this delivery is already <?php echo $task['status']; ?>.
                        </div>

                    <?php } ?>

                    <?php if(!empty($task['delivery_photo'])){ ?>

                        <div class="mt-3">

                            <p class="mb-1"><b>Current Photo:</b></p>

                            <img src="../uploads/delivery/<?php echo $task['delivery_photo']; ?>"
                                 width="250"
                                 class="img-thumbnail">

                            <br>

                            <a href="../uploads/delivery/<?php echo $task['delivery_photo']; ?>"
                               target="_blank"
                               class="btn btn-sm btn-primary mt-2">
                                View Full Image
                            </a>

                        </div>

                    <?php } ?>
                </div>

                <?php if(!$is_view_only){ ?>

                    <button type="submit" class="btn btn-success">
                        Save Logistics
                    </button>

                <?php } ?>

            </form>

            <hr>

            <h5>Logistics History</h5>

            <div class="table-responsive">

                <table class="table table-bordered align-middle">

                    <thead class="table-dark">
                        <tr>
                            <th>Date/Time</th>
                            <th>Updated By</th>
                            <th>Old Status</th>
                            <th>New Status</th>
                            <th>Remarks</th>
                            <th>Photo</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php while($log = $logs->fetch_assoc()){ ?>

                        <tr>
                            <td><?php echo $log['created_at']; ?></td>

                            <td><?php echo $log['fullname']; ?></td>

                            <td><?php echo $log['old_status']; ?></td>

                            <td><?php echo $log['new_status']; ?></td>

                            <td><?php echo $log['remarks']; ?></td>

                            <td>
                                <?php if(!empty($log['photo'])){ ?>

                                    <a href="../uploads/delivery/<?php echo $log['photo']; ?>"
                                       target="_blank"
                                       class="btn btn-sm btn-primary">
                                        View Photo
                                    </a>

                                <?php }else{ ?>

                                    No photo

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