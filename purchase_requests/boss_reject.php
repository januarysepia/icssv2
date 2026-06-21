<?php

include '../auth/auth_check.php';
require_role(['Boss', 'Admin']);
include '../config/database.php';
require_once '../includes/purchase_approval_service.php';

require_post();
verify_csrf();

$id = intval($_POST['id'] ?? 0);
$rejected_by = intval($_SESSION['user_id']);
$reason = trim($_POST['reason'] ?? '');

try {
    processPurchaseDecision($conn, $id, $rejected_by, 'decline', $reason);
} catch (Throwable $exception) {
    exit(h($exception->getMessage()));
}

echo "
<script>
alert('Purchase Request Rejected');
window.location='view_purchase.php?id=$id';
</script>
";

