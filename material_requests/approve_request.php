<?php

include '../auth/auth_check.php';

require_role([
    'Purchasing',
    'Boss',
    'Admin'
]);

include '../config/database.php';
include '../includes/create_notification.php';

require_post();
verify_csrf();

$id = intval($_POST['id'] ?? 0);
$approved_by = $_SESSION['user_id'];

/*
CHECK REQUEST
*/

$request = $conn->query("
SELECT *
FROM material_requests
WHERE id = '$id'
")->fetch_assoc();

if(!$request){
    die("Invalid Request");
}

if ($request['status'] !== 'Pending Review') {
    exit('This request is no longer pending review.');
}

$pending_suppliers = $conn->query("
    SELECT COUNT(*) AS total
    FROM material_request_items
    WHERE request_id = '$id'
      AND supplier_review_status = 'Pending'
")->fetch_assoc()['total'] ?? 0;

if ((int) $pending_suppliers > 0) {
    echo "
    <script>
        alert('Please resolve all suggested suppliers before approving this material request.');
        window.location='view_request.php?id=$id';
    </script>
    ";
    exit();
}

/*
GET REQUEST ITEMS
*/

$items = $conn->query("
SELECT *
FROM material_request_items
WHERE request_id = '$id'
");

$has_for_issue = false;
$has_to_purchase = false;

/*
DECIDE ITEM STATUS AFTER APPROVAL
*/

while($item = $items->fetch_assoc()){

    $item_id = intval($item['id']);
    $inventory_id = $item['inventory_id'];
    $qty_needed = intval($item['quantity']);

    $new_item_status = 'To Purchase';

    if(!empty($inventory_id)){

        $inventory = $conn->query("
        SELECT *
        FROM inventory_items
        WHERE id = '$inventory_id'
        ")->fetch_assoc();

        if($inventory && intval($inventory['quantity']) >= $qty_needed){

            $new_item_status = 'For Issue';
            $has_for_issue = true;

        }else{

            $new_item_status = 'To Purchase';
            $has_to_purchase = true;
        }

    }else{

        $new_item_status = 'To Purchase';
        $has_to_purchase = true;
    }

    $conn->query("
    UPDATE material_request_items
    SET item_status = '$new_item_status'
    WHERE id = '$item_id'
    ");
}

/*
SET OVERALL REQUEST STATUS
*/

if($has_for_issue && $has_to_purchase){

    $overall_status = 'Partially Approved';

}elseif($has_for_issue){

    $overall_status = 'Approved - For Issue';

}elseif($has_to_purchase){

    $overall_status = 'Approved - To Purchase';

}else{

    $overall_status = 'Approved';
}

/*
APPROVE REQUEST HEADER
*/

$conn->query("
UPDATE material_requests
SET
status = '$overall_status',
approved_by = '$approved_by',
approved_at = NOW()
WHERE id = '$id'
");

/*
STATUS LOG
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
    '$id',
    '$approved_by',
    '{$request['status']}',
    '$overall_status',
    'Request approved. Item statuses were automatically classified as For Issue or To Purchase.'
)
");

/*
NOTIFY REQUESTER
*/

if(!empty($request['requested_by'])){

    createNotification(
        $conn,
        $request['requested_by'],
        'Material Request Approved',
        'Your material request ' . $request['request_no'] . ' has been approved.',
        '../material_requests/view_request.php?id=' . $id
    );
}

/*
NOTIFY PURCHASING IF THERE ARE TO PURCHASE ITEMS
*/

if($has_to_purchase){

    $purchasing_users = $conn->query("
    SELECT id
    FROM users
    WHERE system_role = 'Purchasing'
    AND status = 'Active'
    ");

    while($user = $purchasing_users->fetch_assoc()){

        createNotification(
            $conn,
            $user['id'],
            'Items Ready for Purchase',
            'Material request ' . $request['request_no'] . ' has item/s marked To Purchase.',
            '../material_requests/view_request.php?id=' . $id
        );
    }
}

echo "
<script>
alert('Request approved. Items classified for issue or purchase.');
window.location='view_request.php?id=$id';
</script>
";

?>
