<?php

date_default_timezone_set('Asia/Manila');

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}

require_once '../includes/security.php';
include '../config/database.php';

$unit_id = intval($_GET['unit_id'] ?? 0);

if ($unit_id <= 0 && !empty($_GET['asset_id'])) {
    $legacy_inventory_id = intval($_GET['asset_id']);
    $legacy = $conn->prepare("SELECT id FROM asset_units WHERE inventory_id = ? ORDER BY id LIMIT 1");
    $legacy->bind_param('i', $legacy_inventory_id);
    $legacy->execute();
    $unit_id = (int) ($legacy->get_result()->fetch_assoc()['id'] ?? 0);
}

$stmt = $conn->prepare("
    SELECT
        au.id AS unit_id,
        au.asset_code,
        au.serial_number,
        au.unit_status AS asset_status,
        au.condition_status AS item_condition,
        COALESCE(au.storage_location, i.storage_location) AS storage_location,
        i.id AS inventory_id,
        i.item_code,
        i.item_name,
        i.brand,
        i.model,
        i.asset_usage
    FROM asset_units au
    INNER JOIN inventory_items i ON i.id = au.inventory_id
    WHERE au.id = ? AND i.item_type = 'Asset'
    LIMIT 1
");
$stmt->bind_param('i', $unit_id);
$stmt->execute();
$asset = $stmt->get_result()->fetch_assoc();

if (!$asset) {
    http_response_code(404);
}

$is_borrowable = $asset && in_array($asset['asset_usage'], ['Borrowable', 'Both'], true);
$is_available = $asset && $asset['asset_status'] === 'Available';
$success = isset($_GET['success']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ICSS Asset Borrowing</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background:#f4f6f9; color:#111827; }
        .public-wrap { max-width:680px; margin:0 auto; padding:20px 14px 40px; }
        .brand { font-weight:800; font-size:20px; margin-bottom:18px; }
        .borrow-card { border:0; border-radius:18px; box-shadow:0 8px 28px rgba(15,23,42,.09); }
        .asset-summary { background:#111827; color:#fff; border-radius:18px 18px 0 0; padding:22px; }
        .asset-code { color:#93c5fd; font-size:13px; font-weight:700; letter-spacing:.06em; }
        .asset-name { font-size:25px; font-weight:800; margin:5px 0 12px; }
        .detail-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:10px; }
        .detail { background:rgba(255,255,255,.08); padding:10px 12px; border-radius:10px; }
        .detail small { display:block; color:#cbd5e1; }
        .form-body { padding:22px; }
        .form-label { font-weight:700; }
        .required::after { content:" *"; color:#dc2626; }
        @media(max-width:520px) {
            .public-wrap { padding:0 0 28px; }
            .brand { padding:16px 16px 0; }
            .borrow-card { border-radius:0; }
            .asset-summary { border-radius:0; }
            .detail-grid { grid-template-columns:1fr; }
            .form-body { padding:20px 16px; }
        }
    </style>
</head>
<body>
<main class="public-wrap">
    <div class="brand">ICSS Asset Borrowing</div>

    <?php if (!$asset): ?>
        <div class="alert alert-danger">Asset not found or the QR code is invalid.</div>
    <?php else: ?>
        <section class="card borrow-card">
            <div class="asset-summary">
                <div class="asset-code"><?= h($asset['asset_code']) ?></div>
                <div class="asset-name"><?= h($asset['item_name']) ?></div>
                <div class="detail-grid">
                    <div class="detail"><small>Brand / Model</small><?= h(trim(($asset['brand'] ?: '-') . ' / ' . ($asset['model'] ?: '-'))) ?></div>
                    <div class="detail"><small>Condition</small><?= h($asset['item_condition'] ?: '-') ?></div>
                    <div class="detail"><small>Location</small><?= h($asset['storage_location'] ?: 'Not set') ?></div>
                    <div class="detail"><small>Status</small><?= h($asset['asset_status']) ?></div>
                </div>
            </div>

            <div class="form-body">
                <?php if ($success): ?>
                    <div class="alert alert-success mb-0">
                        Borrowing record saved successfully. Please present this screen to the inventory personnel.
                    </div>
                <?php elseif (!$is_borrowable): ?>
                    <div class="alert alert-warning mb-0">This asset is not configured for borrowing.</div>
                <?php elseif (!$is_available): ?>
                    <div class="alert alert-warning mb-0">
                        This asset is currently <strong><?= h($asset['asset_status']) ?></strong> and cannot be borrowed.
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        Complete the form while the inventory personnel prepares the asset.
                    </div>

                    <form method="POST" action="save_public_asset_borrow.php">
                        <?= csrf_field() ?>
                        <input type="hidden" name="unit_id" value="<?= (int) $asset['unit_id'] ?>">

                        <div class="mb-3">
                            <label class="form-label required">Name</label>
                            <input type="text" name="borrower_name" class="form-control" maxlength="150" autocomplete="name" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label required">Department</label>
                            <input type="text" name="borrower_department" class="form-control" maxlength="150" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label required">Purpose</label>
                            <textarea name="purpose" class="form-control" rows="3" maxlength="500" required></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Borrow Date / Time</label>
                                <input type="text" class="form-control" value="<?= h(date('M d, Y h:i A')) ?>" readonly>
                                <div class="form-text">Recorded automatically by the system.</div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label required">Expected Return</label>
                                <input type="datetime-local" name="due_date" class="form-control"
                                       min="<?= h(date('Y-m-d\TH:i')) ?>" required>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-dark btn-lg w-100">
                            Save Borrowing Record
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>
</main>
</body>
</html>
