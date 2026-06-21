<?php

include '../auth/auth_check.php';
require_role(['Purchasing', 'Admin']);
include '../config/database.php';
require_once '../includes/purchase_approval_email.php';

require_post();
verify_csrf();

$id = intval($_POST['id'] ?? 0);
$result = sendPurchaseApprovalEmail($conn, $id);
$message = $result['sent']
    ? 'Approval email sent to ' . $result['recipient'] . '.'
    : 'Email was not sent: ' . $result['error'];
$message = addslashes($message);

echo "
<script>
alert('$message');
window.location='view_purchase.php?id=$id';
</script>
";

