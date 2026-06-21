<?php

include '../auth/auth_check.php';

require_role([
    'Logistics',
    'Admin',
    'Boss'
]);

include '../config/database.php';

$id = $_GET['id'];

$completed_by = $_SESSION['user_id'];

/*
GET LOGISTICS TASK
*/

$task = $conn->query("
SELECT *
FROM logistics_tasks
WHERE id = '$id'
")->fetch_assoc();

if(!$task){
    die('Logistics task not found.');
}

$jo_id = $task['jo_id'];

/*
UPDATE LOGISTICS TASK
*/

$conn->query("
UPDATE logistics_tasks
SET
status = 'Completed',
completed_at = NOW()
WHERE id = '$id'
");

/*
UPDATE JOB ORDER STATUS
*/

$conn->query("
UPDATE job_orders
SET workflow_status = 'Completed'
WHERE id = '$jo_id'
");

/*
SAVE LOG
*/

$conn->query("
INSERT INTO logistics_logs
(
jo_id,
updated_by,
status,
remarks
)

VALUES

(
'$jo_id',
'$completed_by',
'Completed',
'Delivery completed successfully'
)
");

echo "
<script>
alert('Delivery Completed Successfully');
window.location='dashboard.php';
</script>
";

?>