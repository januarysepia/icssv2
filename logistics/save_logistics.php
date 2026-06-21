<?php

include '../auth/auth_check.php';

require_role([
    'Boss',
    'Admin',
    'Supervisor',
    'Logistics'
]);

include '../config/database.php';
include '../includes/activity_logger.php';
include '../includes/jo_audit.php';
include '../includes/create_notification.php';

$task_id = intval($_POST['task_id'] ?? 0);
$status = $conn->real_escape_string($_POST['status'] ?? '');

$delivery_date = $conn->real_escape_string($_POST['delivery_date'] ?? '');
$vehicle_info = $conn->real_escape_string($_POST['vehicle_info'] ?? '');
$driver_name = $conn->real_escape_string($_POST['driver_name'] ?? '');
$driver_mobile = $conn->real_escape_string($_POST['driver_mobile'] ?? '');
$delivery_address = $conn->real_escape_string($_POST['delivery_address'] ?? '');
$remarks = $conn->real_escape_string($_POST['remarks'] ?? '');

$updated_by = $_SESSION['user_id'];
$user_name = $_SESSION['fullname'] ?? $_SESSION['name'] ?? 'User';

/*
GET LOGISTICS TASK + JO
*/

$task = $conn->query("
SELECT
logistics_tasks.*,
job_orders.jo_no,
job_orders.project_name

FROM logistics_tasks

LEFT JOIN job_orders
ON job_orders.id = logistics_tasks.jo_id

WHERE logistics_tasks.id = '$task_id'
")->fetch_assoc();

if(!$task){
    die("Logistics task not found.");
}

$old_status = $task['status'];
$jo_id = $task['jo_id'];
$jo_no = $task['jo_no'];

/*
PREVENT UPDATE IF ALREADY DELIVERED
*/

if($old_status == 'Delivered'){

    echo "
    <script>
        alert('This delivery is already delivered and can no longer be updated.');
        window.location='prepare_delivery.php?id=$task_id';
    </script>
    ";

    exit();
}

/*
VALIDATE STATUS MOVEMENT
*/

$allowed_next_status = '';

if($old_status == 'Pending Logistics'){
    $allowed_next_status = 'Preparing';
}

if($old_status == 'Preparing'){
    $allowed_next_status = 'Dispatched';
}

if($old_status == 'Dispatched'){
    $allowed_next_status = 'Delivered';
}

if($status != $allowed_next_status){

    echo "
    <script>
        alert('Invalid logistics status movement. Current status is $old_status.');
        window.location='prepare_delivery.php?id=$task_id';
    </script>
    ";

    exit();
}

/*
RULE:
Delivery information can only be entered/edited while moving
from Pending Logistics to Preparing.
After Preparing, the delivery details become locked.
*/

if($old_status == 'Pending Logistics' && $status == 'Preparing'){

    if(empty($delivery_date)){
        echo "
        <script>
            alert('Delivery date is required.');
            window.location='prepare_delivery.php?id=$task_id';
        </script>
        ";
        exit();
    }

    if(empty($vehicle_info)){
        echo "
        <script>
            alert('Vehicle information is required.');
            window.location='prepare_delivery.php?id=$task_id';
        </script>
        ";
        exit();
    }

    if(empty($driver_name)){
        echo "
        <script>
            alert('Driver name is required.');
            window.location='prepare_delivery.php?id=$task_id';
        </script>
        ";
        exit();
    }

    if(empty($driver_mobile)){
        echo "
        <script>
            alert('Driver mobile number is required.');
            window.location='prepare_delivery.php?id=$task_id';
        </script>
        ";
        exit();
    }

    if(empty($delivery_address)){
        echo "
        <script>
            alert('Delivery address is required.');
            window.location='prepare_delivery.php?id=$task_id';
        </script>
        ";
        exit();
    }
}

/*
USE EXISTING DELIVERY DETAILS AFTER PREPARING
*/

if($old_status == 'Preparing' || $old_status == 'Dispatched'){

    $delivery_date = $conn->real_escape_string($task['delivery_date'] ?? '');
    $vehicle_info = $conn->real_escape_string($task['vehicle_info'] ?? '');
    $driver_name = $conn->real_escape_string($task['driver_name'] ?? '');
    $driver_mobile = $conn->real_escape_string($task['driver_mobile'] ?? '');
    $delivery_address = $conn->real_escape_string($task['delivery_address'] ?? '');
}

/*
PHOTO HANDLING
*/

$photo_sql = "";
$log_photo = "";

if(isset($_FILES['delivery_photo']) && $_FILES['delivery_photo']['name'] != ''){

    /*
    Only allow photo upload during Delivered transition
    */

    if($status != 'Delivered'){

        echo "
        <script>
            alert('Delivery proof can only be uploaded when marking the task as Delivered.');
            window.location='prepare_delivery.php?id=$task_id';
        </script>
        ";

        exit();
    }

    $file = $_FILES['delivery_photo'];
    $original_name = $conn->real_escape_string($file['name']);
    $tmp_name = $file['tmp_name'];

    $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

    $allowed_extensions = ['jpg','jpeg','png','webp'];

    if(!in_array($extension, $allowed_extensions)){

        echo "
        <script>
            alert('Invalid photo format. Please upload JPG, PNG, JPEG, or WEBP only.');
            window.location='prepare_delivery.php?id=$task_id';
        </script>
        ";

        exit();
    }

    $new_name = "delivery_" . time() . "_" . rand(1000,9999) . "." . $extension;
    $upload_path = "../uploads/delivery/" . $new_name;

    if(move_uploaded_file($tmp_name, $upload_path)){

        $photo_sql = ",
        delivery_photo = '$new_name'
        ";

        $log_photo = $new_name;

    }else{

        echo "
        <script>
            alert('Failed to upload delivery photo.');
            window.location='prepare_delivery.php?id=$task_id';
        </script>
        ";

        exit();
    }
}

/*
IF STATUS IS DELIVERED, REQUIRE PHOTO ONLY IF NO EXISTING PHOTO
*/

if($status == 'Delivered' && empty($task['delivery_photo']) && empty($log_photo)){

    echo "
    <script>
        alert('Delivery photo / proof is required before marking as Delivered.');
        window.location='prepare_delivery.php?id=$task_id';
    </script>
    ";

    exit();
}

/*
UPDATE LOGISTICS TASK
*/

$query = "
UPDATE logistics_tasks
SET
    status = '$status',
    delivery_date = '$delivery_date',
    vehicle_info = '$vehicle_info',
    driver_name = '$driver_name',
    driver_mobile = '$driver_mobile',
    delivery_address = '$delivery_address',
    remarks = '$remarks'
    $photo_sql
";

if($status == 'Preparing'){
    $query .= ",
    prepared_at = NOW()
    ";
}

if($status == 'Dispatched'){
    $query .= ",
    dispatched_at = NOW()
    ";
}

if($status == 'Delivered'){
    $query .= ",
    delivered_at = NOW()
    ";
}

$query .= "
WHERE id = '$task_id'
";

$conn->query($query);

if($conn->error){
    die($conn->error);
}

/*
SAVE LOGISTICS HISTORY
*/

$conn->query("
INSERT INTO logistics_logs
(
    logistics_task_id,
    updated_by,
    old_status,
    new_status,
    remarks,
    photo
)
VALUES
(
    '$task_id',
    '$updated_by',
    '$old_status',
    '$status',
    '$remarks',
    '$log_photo'
)
");

/*
ACTIVITY + JO AUDIT
*/

logActivity(
    $conn,
    'Logistics',
    $user_name . ' updated logistics status of ' . $jo_no . ' from ' . $old_status . ' to ' . $status,
    $updated_by
);

logJOAudit(
    $conn,
    $jo_id,
    'Logistics Status Updated',
    $updated_by,
    'Logistics status changed from ' . $old_status . ' to ' . $status . '. Remarks: ' . $remarks
);

/*
UPDATE JOB ORDER STATUS
*/

if($status == 'Preparing'){

    $conn->query("
    UPDATE job_orders
    SET workflow_status = 'Preparing Delivery'
    WHERE id = '$jo_id'
    ");

    logJOAudit(
        $conn,
        $jo_id,
        'Delivery Preparing',
        $updated_by,
        'Delivery preparation started by ' . $user_name
    );
}

if($status == 'Dispatched'){

    $conn->query("
    UPDATE job_orders
    SET workflow_status = 'Dispatched'
    WHERE id = '$jo_id'
    ");

    logJOAudit(
        $conn,
        $jo_id,
        'Delivery Dispatched',
        $updated_by,
        'Delivery dispatched by ' . $user_name
    );
}

if($status == 'Delivered'){

    $conn->query("
    UPDATE job_orders
    SET
        workflow_status = 'Completed',
        overall_status = 'Completed',
        completed_by = '$updated_by',
        completed_at = NOW()
    WHERE id = '$jo_id'
    ");

    logActivity(
        $conn,
        'Job Order',
        $jo_no . ' has been completed and delivered by ' . $user_name,
        $updated_by
    );

    logJOAudit(
        $conn,
        $jo_id,
        'JO Completed',
        $updated_by,
        'Job order completed after successful delivery. Delivered by ' . $user_name
    );

    /*
    NOTIFY BOSS, ADMIN, TECHNICAL, SUPERVISOR
    */

    $notify_users = $conn->query("
    SELECT id
    FROM users
    WHERE system_role IN ('Boss','Admin','Technical','Supervisor')
    AND status = 'Active'
    ");

    while($user = $notify_users->fetch_assoc()){

        createNotification(
            $conn,
            $user['id'],
            'Job Order Completed',
            $jo_no . ' has been delivered and marked as completed.',
            '../job_orders/view_jo.php?id=' . $jo_id
        );
    }
}

echo "
<script>
alert('Logistics Updated Successfully');
window.location='logistics_list.php';
</script>
";

?>