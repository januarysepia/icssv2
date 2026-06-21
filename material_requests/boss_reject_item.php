<?php

include '../auth/auth_check.php';

require_role([
    'Boss',
    'Admin'
]);

include '../config/database.php';

$item_id = $_GET['item_id'];
$request_id = $_GET['request_id'];

$rejected_by = $_SESSION['user_id'];

/*
GET ITEM DETAILS
*/

$item = $conn->query("
SELECT *
FROM material_request_items
WHERE id = '$item_id'
")->fetch_assoc();

if(!$item){
    die('Material request item not found.');
}

$old_status = $item['item_status'];

/*
REJECT ITEM
*/

$conn->query("
UPDATE material_request_items
SET item_status = 'Cancelled'
WHERE id = '$item_id'
");

/*
CHECK IF ALL ITEMS ARE CANCELLED
*/

$status_check = $conn->query("
SELECT
COUNT(*) AS total_items,
SUM(CASE WHEN item_status = 'Cancelled' THEN 1 ELSE 0 END) AS cancelled_items

FROM material_request_items
WHERE request_id = '$request_id'
")->fetch_assoc();

/*
GET OLD REQUEST STATUS
*/

$request = $conn->query("
SELECT status
FROM material_requests
WHERE id = '$request_id'
")->fetch_assoc();

$old_request_status = $request['status'];

/*
UPDATE OVERALL REQUEST STATUS
*/

if($status_check['cancelled_items'] == $status_check['total_items']){

    $new_status = 'Cancelled';

}else{

    $new_status = 'Partially Approved';

}

$conn->query("
UPDATE material_requests
SET status = '$new_status'
WHERE id = '$request_id'
");

/*
SAVE STATUS LOG
*/

$conn->query("
INSERT INTO material_request_status_logs
(
request_id,
updated_by,
old_status,
new_status,
notes
)

VALUES

(
'$request_id',
'$rejected_by',
'$old_request_status',
'$new_status',
'Boss rejected item: {$item['description']}'
)
");

echo "
<script>
alert('Item Rejected Successfully');
window.location='view_request.php?id=$request_id';
</script>
";

?>