<?php

include '../auth/auth_check.php';

require_role([
    'Boss',
    'Admin',
    'Purchasing'
]);

include '../config/database.php';

$request_id = $_POST['request_id'];

$item_ids = $_POST['item_id'];
$item_statuses = $_POST['item_status'];
$notes_array = $_POST['notes'];

$updated_by = $_SESSION['user_id'];

$summary_notes = "";

/*
UPDATE EACH ITEM STATUS
*/

for($i = 0; $i < count($item_ids); $i++){

    $item_id = $item_ids[$i];

    $new_status = $conn->real_escape_string($item_statuses[$i]);

    $note = $conn->real_escape_string($notes_array[$i]);

    /*
    GET OLD ITEM STATUS
    */

    $old_item = $conn->query("
    SELECT item_status, description
    FROM material_request_items
    WHERE id = '$item_id'
    ")->fetch_assoc();

    if(!$old_item){
        continue;
    }

    $old_status = $old_item['item_status'];

    /*
    UPDATE ITEM
    */

    $conn->query("
    UPDATE material_request_items
    SET item_status = '$new_status'
    WHERE id = '$item_id'
    ");

    /*
    ADD TO SUMMARY NOTES
    */

    $summary_notes .=
    $old_item['description'] .
    ': ' .
    $old_status .
    ' → ' .
    $new_status .
    ' | ' .
    $note .
    '\n';

}

/*
AUTO UPDATE OVERALL REQUEST STATUS
*/

$status_summary = $conn->query("
SELECT
SUM(CASE WHEN item_status = 'Pending' THEN 1 ELSE 0 END) AS pending_count,
SUM(CASE WHEN item_status = 'For Purchase' THEN 1 ELSE 0 END) AS for_purchase_count,
SUM(CASE WHEN item_status = 'For Boss Approval' THEN 1 ELSE 0 END) AS boss_approval_count,
SUM(CASE WHEN item_status = 'Approved' THEN 1 ELSE 0 END) AS approved_count,
SUM(CASE WHEN item_status = 'Waiting Delivery' THEN 1 ELSE 0 END) AS waiting_count,
SUM(CASE WHEN item_status = 'Received' THEN 1 ELSE 0 END) AS received_count,
SUM(CASE WHEN item_status = 'Issued' THEN 1 ELSE 0 END) AS issued_count,
COUNT(*) AS total_items

FROM material_request_items
WHERE request_id = '$request_id'
")->fetch_assoc();

$overall_status = 'Pending Review';

if($status_summary['pending_count'] > 0){
    $overall_status = 'Pending Review';
}

if($status_summary['for_purchase_count'] > 0){
    $overall_status = 'For Purchase';
}

if($status_summary['boss_approval_count'] > 0){
    $overall_status = 'For Boss Approval';
}

if($status_summary['approved_count'] > 0){
    $overall_status = 'Approved';
}

if($status_summary['waiting_count'] > 0){
    $overall_status = 'Waiting Delivery';
}

if($status_summary['received_count'] > 0){
    $overall_status = 'Received';
}

if(
    $status_summary['issued_count'] == $status_summary['total_items']
    &&
    $status_summary['total_items'] > 0
){
    $overall_status = 'Released to Production';
}

/*
GET OLD OVERALL STATUS
*/

$request = $conn->query("
SELECT status
FROM material_requests
WHERE id = '$request_id'
")->fetch_assoc();

$old_overall_status = $request['status'];

/*
UPDATE OVERALL REQUEST
*/

$conn->query("
UPDATE material_requests
SET
status = '$overall_status',
notes = '$summary_notes'
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
'$updated_by',
'$old_overall_status',
'$overall_status',
'$summary_notes'
)
");

echo "
<script>
alert('Item statuses updated successfully');
window.location='view_request.php?id=$request_id';
</script>
";

?>