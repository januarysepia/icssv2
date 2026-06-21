<?php

include '../auth/auth_check.php';

require_role([
    'Boss',
    'Admin'
]);

include '../config/database.php';

if(
    !isset($_GET['item_id']) ||
    !isset($_GET['request_id'])
){
    die('Invalid request.');
}

$item_id = intval($_GET['item_id']);
$request_id = intval($_GET['request_id']);

$approved_by = $_SESSION['user_id'];

/*
GET ITEM
*/

$item = $conn->query("
SELECT *
FROM material_request_items
WHERE id = '$item_id'
")->fetch_assoc();

if(!$item){
    die('Material request item not found.');
}

$item_description = $conn->real_escape_string($item['description']);

/*
UPDATE ITEM STATUS
*/

$conn->query("
UPDATE material_request_items
SET item_status = 'Approved'
WHERE id = '$item_id'
");

/*
CHECK OVERALL REQUEST STATUS
*/

$status_check = $conn->query("
SELECT
COUNT(*) AS total_items,

SUM(
CASE
WHEN item_status = 'Approved'
OR item_status = 'Issued'
OR item_status = 'Received'
THEN 1
ELSE 0
END
) AS approved_items

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
SET NEW REQUEST STATUS
*/

if(
    $status_check['approved_items'] ==
    $status_check['total_items']
){

    $new_status = 'Approved';

}else{

    $new_status = 'Partially Approved';

}

/*
UPDATE REQUEST STATUS
*/

$conn->query("
UPDATE material_requests
SET status = '$new_status'
WHERE id = '$request_id'
");

/*
SAVE LOG
*/

$note = "Boss approved item: " . $item_description;

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
'$approved_by',
'$old_request_status',
'$new_status',
'$note'
)
");

/*
REDIRECT
*/

echo "
<script>
alert('Item Approved Successfully');
window.location='view_request.php?id=$request_id';
</script>
";

?>