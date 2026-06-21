<?php

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/email.php';

function sendPurchaseApprovalEmail(mysqli $conn, int $purchase_id): array
{
    $config = icssEmailConfig();
    $recipient = $config['boss_approval_email'];

    $purchase_stmt = $conn->prepare("
        SELECT
            pr.*,
            purchasing.fullname AS purchasing_name,
            mr.request_no AS material_request_no,
            mr.request_context,
            jo.jo_no,
            jo.project_name,
            jo.client_name,
            technical.fullname AS technical_requestor
        FROM purchase_requests pr
        LEFT JOIN users purchasing ON purchasing.id = pr.requested_by
        LEFT JOIN material_requests mr ON mr.id = pr.material_request_id
        LEFT JOIN job_orders jo ON jo.id = mr.jo_id
        LEFT JOIN users technical ON technical.id = mr.requested_by
        WHERE pr.id = ?
    ");
    $purchase_stmt->bind_param('i', $purchase_id);
    $purchase_stmt->execute();
    $purchase = $purchase_stmt->get_result()->fetch_assoc();
    $purchase_stmt->close();

    if (!$purchase || $purchase['status'] !== 'For Boss Approval') {
        return ['sent' => false, 'error' => 'Purchase request is not awaiting Boss approval.'];
    }

    $items_stmt = $conn->prepare("
        SELECT description, item_code, brand, supplier, unit, quantity, unit_price
        FROM purchase_request_items
        WHERE purchase_request_id = ?
        ORDER BY id
    ");
    $items_stmt->bind_param('i', $purchase_id);
    $items_stmt->execute();
    $items = $items_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $items_stmt->close();

    $boss = $conn->query("
        SELECT id
        FROM users
        WHERE system_role = 'Boss' AND status = 'Active'
        ORDER BY id
        LIMIT 1
    ")->fetch_assoc();

    if (!$boss) {
        return ['sent' => false, 'error' => 'No active Boss user was found.'];
    }

    $raw_token = bin2hex(random_bytes(32));
    $token_hash = hash('sha256', $raw_token);
    $boss_id = (int) $boss['id'];

    $conn->query("
        UPDATE purchase_email_approvals
        SET used_at = NOW(), action = 'Replaced'
        WHERE purchase_request_id = '$purchase_id'
          AND used_at IS NULL
    ");

    $token_stmt = $conn->prepare("
        INSERT INTO purchase_email_approvals
        (purchase_request_id, boss_user_id, recipient_email, token_hash, expires_at)
        VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 7 DAY))
    ");
    $token_stmt->bind_param('iiss', $purchase_id, $boss_id, $recipient, $token_hash);
    $token_stmt->execute();
    $approval_id = $conn->insert_id;
    $token_stmt->close();

    if ($config['smtp_username'] === '' || $config['smtp_password'] === '' || $config['from_email'] === '') {
        $error = 'Gmail SMTP credentials are not configured.';
        $error_stmt = $conn->prepare("
            UPDATE purchase_email_approvals
            SET send_error = ?
            WHERE id = ?
        ");
        $error_stmt->bind_param('si', $error, $approval_id);
        $error_stmt->execute();
        $error_stmt->close();
        return ['sent' => false, 'error' => $error];
    }

    $base_url = icssAppUrl();
    $approval_url = $base_url . '/purchase_requests/email_approval.php?token=' . urlencode($raw_token);

    $rows = '';
    $grand_total = 0.0;
    foreach ($items as $index => $item) {
        $total = (float) $item['quantity'] * (float) $item['unit_price'];
        $grand_total += $total;
        $rows .= '<tr>'
            . '<td style="padding:8px;border:1px solid #ddd;">' . ($index + 1) . '</td>'
            . '<td style="padding:8px;border:1px solid #ddd;">' . htmlspecialchars($item['description']) . '</td>'
            . '<td style="padding:8px;border:1px solid #ddd;">' . htmlspecialchars($item['item_code'] ?: '-') . '</td>'
            . '<td style="padding:8px;border:1px solid #ddd;">' . htmlspecialchars($item['brand'] ?: '-') . '</td>'
            . '<td style="padding:8px;border:1px solid #ddd;">' . htmlspecialchars($item['supplier'] ?: '-') . '</td>'
            . '<td style="padding:8px;border:1px solid #ddd;text-align:center;">' . (int) $item['quantity'] . ' ' . htmlspecialchars($item['unit']) . '</td>'
            . '<td style="padding:8px;border:1px solid #ddd;text-align:right;">₱' . number_format((float) $item['unit_price'], 2) . '</td>'
            . '<td style="padding:8px;border:1px solid #ddd;text-align:right;">₱' . number_format($total, 2) . '</td>'
            . '</tr>';
    }

    $html = '<div style="font-family:Arial,sans-serif;color:#1f2937;max-width:900px;margin:auto;">'
        . '<h2 style="background:#111827;color:white;padding:18px;">Purchase Request for Approval</h2>'
        . '<table style="width:100%;border-collapse:collapse;margin-bottom:18px;">'
        . emailInfoRow('Purchase No.', $purchase['purchase_no'])
        . emailInfoRow('Material Request', $purchase['material_request_no'] ?: 'Manual Purchase')
        . emailInfoRow('JO No.', $purchase['jo_no'] ?: '-')
        . emailInfoRow('Project / Client', trim(($purchase['project_name'] ?: '-') . ' / ' . ($purchase['client_name'] ?: '-')))
        . emailInfoRow('Technical Requestor', $purchase['technical_requestor'] ?: '-')
        . emailInfoRow('Prepared by Purchasing', $purchase['purchasing_name'] ?: '-')
        . emailInfoRow('Request Context', $purchase['request_context'] ?: 'Manual Purchase')
        . emailInfoRow('Date Created', $purchase['created_at'])
        . emailInfoRow('Remarks', $purchase['remarks'] ?: 'No remarks')
        . '</table>'
        . '<table style="width:100%;border-collapse:collapse;">'
        . '<thead><tr style="background:#e5e7eb;">'
        . '<th style="padding:8px;border:1px solid #ddd;">#</th><th style="padding:8px;border:1px solid #ddd;">Item</th>'
        . '<th style="padding:8px;border:1px solid #ddd;">Code</th><th style="padding:8px;border:1px solid #ddd;">Brand</th>'
        . '<th style="padding:8px;border:1px solid #ddd;">Supplier</th><th style="padding:8px;border:1px solid #ddd;">Qty</th>'
        . '<th style="padding:8px;border:1px solid #ddd;">Unit Price</th><th style="padding:8px;border:1px solid #ddd;">Total</th>'
        . '</tr></thead><tbody>' . $rows . '</tbody>'
        . '<tfoot><tr><th colspan="7" style="padding:10px;text-align:right;border:1px solid #ddd;">Grand Total</th>'
        . '<th style="padding:10px;text-align:right;border:1px solid #ddd;">₱' . number_format($grand_total, 2) . '</th></tr></tfoot>'
        . '</table>'
        . '<div style="text-align:center;margin:26px 0;">'
        . '<a href="' . htmlspecialchars($approval_url) . '" style="display:inline-block;background:#2563eb;color:white;padding:13px 28px;text-decoration:none;border-radius:6px;margin:5px;font-weight:bold;">Review Purchase Request</a>'
        . '</div>'
        . '<p style="font-size:12px;color:#6b7280;text-align:center;">Review the complete request, then choose Approve or Decline on the secure page. No system login is required. This one-time link expires in 7 days.</p>'
        . '</div>';

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = $config['smtp_host'];
        $mail->SMTPAuth = true;
        $mail->Username = $config['smtp_username'];
        $mail->Password = $config['smtp_password'];
        $mail->SMTPSecure = $config['smtp_encryption'];
        $mail->Port = $config['smtp_port'];
        $mail->CharSet = 'UTF-8';
        $mail->setFrom($config['from_email'], $config['from_name']);
        $mail->addAddress($recipient);
        $mail->isHTML(true);
        $mail->Subject = 'Approval Required: ' . $purchase['purchase_no'] . ' — ₱' . number_format($grand_total, 2);
        $mail->Body = $html;
        $mail->AltBody = 'Purchase request ' . $purchase['purchase_no']
            . ' requires approval. Total: PHP ' . number_format($grand_total, 2)
            . '. Open this link: ' . $approval_url;
        $mail->send();

        $conn->query("
            UPDATE purchase_email_approvals
            SET sent_at = NOW(), send_error = NULL
            WHERE id = '$approval_id'
        ");

        return ['sent' => true, 'recipient' => $recipient];
    } catch (Exception $exception) {
        $error = $mail->ErrorInfo ?: $exception->getMessage();
        $error_stmt = $conn->prepare("
            UPDATE purchase_email_approvals
            SET send_error = ?
            WHERE id = ?
        ");
        $error_stmt->bind_param('si', $error, $approval_id);
        $error_stmt->execute();
        $error_stmt->close();
        return ['sent' => false, 'error' => $error];
    }
}

function emailInfoRow(string $label, string $value): string
{
    return '<tr><th style="padding:8px;border:1px solid #ddd;text-align:left;width:220px;background:#f8fafc;">'
        . htmlspecialchars($label)
        . '</th><td style="padding:8px;border:1px solid #ddd;">'
        . nl2br(htmlspecialchars($value))
        . '</td></tr>';
}
