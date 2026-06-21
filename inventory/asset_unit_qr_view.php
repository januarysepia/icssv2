<?php

include '../auth/auth_check.php';
require_role(['Boss', 'Admin', 'Purchasing', 'Supervisor']);
include '../config/database.php';

$unit_id = intval($_GET['unit_id'] ?? 0);
$stmt = $conn->prepare("
    SELECT au.*, i.item_code AS catalog_code, i.item_name, i.brand, i.model, i.asset_usage,
           u.fullname AS assigned_name, u.employee_no
    FROM asset_units au
    INNER JOIN inventory_items i ON i.id = au.inventory_id
    LEFT JOIN users u ON u.id = au.assigned_to
    WHERE au.id = ?
");
$stmt->bind_param('i', $unit_id);
$stmt->execute();
$unit = $stmt->get_result()->fetch_assoc();

if (!$unit) {
    exit('Asset unit not found.');
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Asset Unit QR</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <?php include '../includes/asset_ui.php'; ?>
    <style>
        .unit-detail {
            height: 100%;
        }

        .unit-detail label {
            display: block;
            margin-bottom: 6px;
            color: #6b7280;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .02em;
            text-transform: uppercase;
        }

        .unit-detail strong {
            display: block;
            color: #111827;
            font-size: 16px;
            line-height: 1.35;
            overflow-wrap: anywhere;
        }
    </style>
</head>
<body class="asset-module">
<?php include '../dashboard/sidebar.php'; ?>
<div class="content-wrapper">
<?php include '../dashboard/header.php'; ?>
<div class="container-fluid asset-page">
    <div class="card">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Asset Unit QR</h4>
            <a href="asset_list.php" class="btn btn-light btn-sm">Back</a>
        </div>
        <div class="row g-4 align-items-center">
            <div class="col-md-4 text-center">
                <?php if (!empty($unit['qr_code']) && file_exists('../' . $unit['qr_code'])): ?>
                    <img src="../<?= h($unit['qr_code']) ?>" class="img-fluid" style="max-width:260px" alt="Asset QR">
                <?php else: ?>
                    <div class="alert alert-warning">No QR generated yet.</div>
                <?php endif; ?>
                <div class="mt-3">
                    <a href="generate_asset_unit_qr.php?unit_id=<?= $unit_id ?>" class="btn btn-dark">
                        <?= empty($unit['qr_code']) ? 'Generate QR' : 'Regenerate QR' ?>
                    </a>
                </div>
            </div>
            <div class="col-md-8">
                <h3><?= h($unit['item_name']) ?></h3>
                <div class="row g-3">
                    <div class="col-sm-6"><div class="detail-item unit-detail"><label>Unit Asset Code</label><strong><?= h($unit['asset_code']) ?></strong></div></div>
                    <div class="col-sm-6"><div class="detail-item unit-detail"><label>Catalog Code</label><strong><?= h($unit['catalog_code']) ?></strong></div></div>
                    <div class="col-sm-6"><div class="detail-item unit-detail"><label>Serial Number</label><strong><?= h($unit['serial_number'] ?: '-') ?></strong></div></div>
                    <div class="col-sm-6"><div class="detail-item unit-detail"><label>Status</label><strong><?= h($unit['unit_status']) ?></strong></div></div>
                    <div class="col-sm-6"><div class="detail-item unit-detail"><label>Condition</label><strong><?= h($unit['condition_status']) ?></strong></div></div>
                    <div class="col-sm-6"><div class="detail-item unit-detail"><label>Assigned To</label><strong><?= h($unit['assigned_name'] ?: 'Not assigned') ?></strong></div></div>
                </div>
            </div>
        </div>
    </div>
</div>
</div>
</body>
</html>
