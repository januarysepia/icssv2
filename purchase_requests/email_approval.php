<?php

include '../config/database.php';
require_once '../includes/security.php';
require_once '../includes/purchase_approval_service.php';

$raw_token = trim($_REQUEST['token'] ?? '');
$message = '';
$success = false;

if (!preg_match('/^[a-f0-9]{64}$/', $raw_token)) {
    http_response_code(400);
    exit('Invalid approval link.');
}

$token_hash = hash('sha256', $raw_token);
$token_stmt = $conn->prepare("
    SELECT
        pea.*,
        pr.purchase_no,
        pr.status,
        pr.remarks,
        pr.created_at,
        purchasing.fullname AS purchasing_name,
        mr.request_no AS material_request_no,
        mr.request_context,
        jo.jo_no,
        jo.project_name,
        jo.client_name,
        technical.fullname AS technical_requestor
    FROM purchase_email_approvals pea
    INNER JOIN purchase_requests pr ON pr.id = pea.purchase_request_id
    LEFT JOIN users purchasing ON purchasing.id = pr.requested_by
    LEFT JOIN material_requests mr ON mr.id = pr.material_request_id
    LEFT JOIN job_orders jo ON jo.id = mr.jo_id
    LEFT JOIN users technical ON technical.id = mr.requested_by
    WHERE pea.token_hash = ?
");
$token_stmt->bind_param('s', $token_hash);
$token_stmt->execute();
$approval = $token_stmt->get_result()->fetch_assoc();
$token_stmt->close();

if (!$approval) {
    http_response_code(404);
    exit('Approval link not found.');
}

$items_stmt = $conn->prepare("
    SELECT description, item_code, brand, supplier, unit, quantity, unit_price
    FROM purchase_request_items
    WHERE purchase_request_id = ?
    ORDER BY id
");
$items_stmt->bind_param('i', $approval['purchase_request_id']);
$items_stmt->execute();
$items = $items_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$items_stmt->close();

$is_expired = strtotime($approval['expires_at']) < time();
$is_used = !empty($approval['used_at']);
$can_decide = !$is_expired && !$is_used && $approval['status'] === 'For Boss Approval';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $decision = $_POST['decision'] ?? '';
    $reason = trim($_POST['reason'] ?? '');

    if (!in_array($decision, ['approve', 'decline'], true)) {
        $message = 'Invalid decision.';
    } elseif ($decision === 'decline' && $reason === '') {
        $message = 'Please provide the reason for declining this purchase request.';
    } else {
        $conn->begin_transaction();
        try {
            $lock = $conn->prepare("
                SELECT *
                FROM purchase_email_approvals
                WHERE token_hash = ?
                FOR UPDATE
            ");
            $lock->bind_param('s', $token_hash);
            $lock->execute();
            $locked_token = $lock->get_result()->fetch_assoc();
            $lock->close();

            if (
                !$locked_token
                || !empty($locked_token['used_at'])
                || strtotime($locked_token['expires_at']) < time()
            ) {
                throw new RuntimeException('This approval link has expired or was already used.');
            }

            processPurchaseDecision(
                $conn,
                (int) $locked_token['purchase_request_id'],
                (int) $locked_token['boss_user_id'],
                $decision,
                $reason,
                false
            );

            $mark = $conn->prepare("
                UPDATE purchase_email_approvals
                SET used_at = NOW(), action = ?, action_reason = ?
                WHERE id = ?
            ");
            $mark->bind_param('ssi', $decision, $reason, $locked_token['id']);
            $mark->execute();
            $mark->close();
            $conn->commit();

            $success = true;
            $message = $decision === 'approve'
                ? 'Purchase request approved successfully.'
                : 'Purchase request declined and returned to Purchasing.';
            $can_decide = false;
        } catch (Throwable $exception) {
            $conn->rollback();
            $message = $exception->getMessage();
        }
    }
}

$grand_total = 0.0;
foreach ($items as $item) {
    $grand_total += (float) $item['quantity'] * (float) $item['unit_price'];
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Purchase Approval — <?= h($approval['purchase_no']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .boss-decision-box{
            position:sticky;
            top:10px;
            z-index:20;
            margin-bottom:20px;
            padding:12px;
            border:1px solid #dbe3ec;
            border-radius:12px;
            background:#fff;
            box-shadow:0 8px 24px rgba(15,23,42,.14);
        }
        .boss-decision-actions{display:grid;grid-template-columns:1fr 1fr;gap:10px}
        .boss-decision-actions .btn{
            min-height:42px;
            padding:8px 14px;
            font-size:.9rem;
            font-weight:750
        }
        .decline-panel{display:none;margin-top:12px;padding-top:12px;border-top:1px solid #dbe3ec}
        .decline-panel.open{display:block}
        html[data-theme="dark"] .boss-decision-box{background:#252525;border-color:#494949}
        @media(max-width:576px){
            .container{padding:10px!important}
            .boss-decision-box{top:6px}
            .boss-decision-actions .btn{
                min-height:44px;
                padding:8px 10px;
                font-size:.9rem
            }
        }
    </style>
</head>
<body class="bg-light">
<div class="container py-4" style="max-width:1100px;">
    <div class="card shadow border-0">
        <div class="card-header bg-dark text-white">
            <h4 class="mb-1">Purchase Request Approval</h4>
            <div><?= h($approval['purchase_no']) ?></div>
        </div>
        <div class="card-body">
            <?php if ($message !== ''): ?>
                <div class="alert <?= $success ? 'alert-success' : 'alert-warning' ?>"><?= h($message) ?></div>
            <?php endif; ?>

            <?php if ($is_expired): ?>
                <div class="alert alert-danger">This approval link has expired.</div>
            <?php elseif ($is_used): ?>
                <div class="alert alert-info">This link was already used for: <?= h($approval['action']) ?>.</div>
            <?php elseif ($approval['status'] !== 'For Boss Approval'): ?>
                <div class="alert alert-info">This purchase request is already <?= h($approval['status']) ?>.</div>
            <?php endif; ?>

            <div class="table-responsive mb-4">
                <table class="table table-bordered align-middle">
                    <thead class="table-dark">
                    <tr><th>#</th><th>Item</th><th>Code</th><th>Brand</th><th>Supplier</th><th>Qty</th><th>Unit Price</th><th>Total</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($items as $index => $item): ?>
                        <?php $total = (float) $item['quantity'] * (float) $item['unit_price']; ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td><?= h($item['description']) ?></td>
                            <td><?= h($item['item_code'] ?: '-') ?></td>
                            <td><?= h($item['brand'] ?: '-') ?></td>
                            <td><?= h($item['supplier'] ?: '-') ?></td>
                            <td><?= (int) $item['quantity'] ?> <?= h($item['unit']) ?></td>
                            <td>₱<?= number_format((float) $item['unit_price'], 2) ?></td>
                            <td>₱<?= number_format($total, 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot><tr><th colspan="7" class="text-end">Grand Total</th><th>₱<?= number_format($grand_total, 2) ?></th></tr></tfoot>
                </table>
            </div>

            <?php if ($can_decide): ?>
                <form method="POST" id="bossDecisionForm">
                    <input type="hidden" name="token" value="<?= h($raw_token) ?>">
                    <div class="boss-decision-box">
                        <div class="boss-decision-actions">
                            <button type="submit" name="decision" value="approve"
                                    class="btn btn-success"
                                    onclick="return confirm('Approve this purchase request?');">
                                ✓ Approve
                            </button>
                            <button type="button" class="btn btn-danger" id="showDeclinePanel">
                                ✕ Decline
                            </button>
                        </div>
                        <div class="decline-panel" id="declinePanel">
                            <label class="form-label fw-bold" for="declineReason">Reason for declining</label>
                            <textarea name="reason" id="declineReason" class="form-control" rows="3"
                                      placeholder="Please enter the reason for declining."><?= h($_POST['reason'] ?? '') ?></textarea>
                            <div class="d-grid gap-2 mt-2">
                                <button type="submit" name="decision" value="decline"
                                        class="btn btn-danger"
                                        onclick="return confirm('Decline and return this request to Purchasing?');">
                                    Confirm Decline
                                </button>
                                <button type="button" class="btn btn-outline-secondary" id="cancelDecline">
                                    Cancel
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            <?php endif; ?>

            <div class="row g-3 mb-4">
                <div class="col-md-4"><strong>Material Request:</strong><br><?= h($approval['material_request_no'] ?: 'Manual Purchase') ?></div>
                <div class="col-md-4"><strong>JO / Project:</strong><br><?= h(($approval['jo_no'] ?: '-') . ' — ' . ($approval['project_name'] ?: '-')) ?></div>
                <div class="col-md-4"><strong>Client:</strong><br><?= h($approval['client_name'] ?: '-') ?></div>
                <div class="col-md-4"><strong>Technical Requestor:</strong><br><?= h($approval['technical_requestor'] ?: '-') ?></div>
                <div class="col-md-4"><strong>Prepared by Purchasing:</strong><br><?= h($approval['purchasing_name'] ?: '-') ?></div>
                <div class="col-md-4"><strong>Context:</strong><br><?= h($approval['request_context'] ?: 'Manual Purchase') ?></div>
            </div>

            <div class="mb-4">
                <strong>Remarks:</strong>
                <div class="border rounded p-3 mt-2"><?= nl2br(h($approval['remarks'] ?: 'No remarks')) ?></div>
            </div>

        </div>
    </div>
</div>
<script>
const showDeclinePanel = document.getElementById('showDeclinePanel');
const declinePanel = document.getElementById('declinePanel');
const declineReason = document.getElementById('declineReason');
const cancelDecline = document.getElementById('cancelDecline');

showDeclinePanel?.addEventListener('click', function(){
    declinePanel?.classList.add('open');
    declineReason?.focus();
});
cancelDecline?.addEventListener('click', function(){
    declinePanel?.classList.remove('open');
});
</script>
</body>
</html>
