<?php

include '../auth/auth_check.php';
require_role(['Boss', 'Admin', 'Purchasing']);
include '../config/database.php';

$inventory_id = intval($_GET['inventory_id'] ?? 0);
$stmt = $conn->prepare("SELECT * FROM inventory_items WHERE id=? AND item_type='Asset'");
$stmt->bind_param('i', $inventory_id);
$stmt->execute();
$asset = $stmt->get_result()->fetch_assoc();

if (!$asset) {
    exit('Asset catalog item not found.');
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Add Asset Units</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <?php include '../includes/asset_ui.php'; ?>
</head>
<body class="asset-module">
<?php include '../dashboard/sidebar.php'; ?>
<div class="content-wrapper">
<?php include '../dashboard/header.php'; ?>
<div class="container-fluid asset-page">
    <div class="card">
        <div class="card-header bg-dark text-white d-flex justify-content-between">
            <h4 class="mb-0">Add Physical Units</h4>
            <a href="asset_catalog.php" class="btn btn-light btn-sm">Back</a>
        </div>
        <div class="alert alert-info">
            Adding units to <strong><?= h($asset['item_code'] . ' — ' . $asset['item_name']) ?></strong>.
            Each unit receives a unique internal asset code.
        </div>
        <form action="save_asset_units.php" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="inventory_id" value="<?= $inventory_id ?>">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-bold">Number of Units</label>
                    <input type="number" name="quantity" class="form-control" min="1" max="100" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-bold">Initial Condition</label>
                    <select name="condition_status" class="form-select">
                        <option>Good</option>
                        <option>With Minor Issue</option>
                        <option>Damaged</option>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-bold">Storage Location</label>
                    <input type="text" name="storage_location" class="form-control" value="<?= h($asset['storage_location']) ?>">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label fw-bold">Serial Numbers</label>
                <textarea name="serial_numbers" class="form-control" rows="6" placeholder="One serial number per line; optional."></textarea>
            </div>
            <button class="btn btn-success">Save Individual Units</button>
        </form>
    </div>
</div>
</div>
</body>
</html>
