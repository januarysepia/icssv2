<?php

include '../auth/auth_check.php';
require_role(['Boss', 'Admin', 'Purchasing', 'Supervisor']);
include '../config/database.php';
require_once '../includes/overdue_asset_notifications.php';

createOverdueAssetNotifications($conn);

$catalog = $conn->query("
    SELECT
        i.id,
        i.item_code,
        i.item_name,
        i.brand,
        i.model,
        i.asset_usage,
        COUNT(au.id) AS total_units,
        SUM(au.unit_status = 'Available') AS available_units,
        SUM(au.unit_status = 'Borrowed') AS borrowed_units,
        SUM(bt.id IS NOT NULL AND bt.due_date < NOW()) AS overdue_units,
        SUM(au.unit_status = 'Assigned') AS assigned_units,
        SUM(au.unit_status IN ('Damaged','Under Repair','Lost')) AS unavailable_units
    FROM inventory_items i
    LEFT JOIN asset_units au ON au.inventory_id = i.id
    LEFT JOIN borrow_transactions bt
        ON bt.asset_unit_id = au.id
       AND bt.status = 'Borrowed'
    WHERE i.item_type = 'Asset'
    GROUP BY i.id
    ORDER BY i.item_name, i.brand, i.model
");
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Asset Catalog</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <?php include '../includes/asset_ui.php'; ?>
</head>
<body class="asset-module">
<?php include '../dashboard/sidebar.php'; ?>
<div class="content-wrapper">
<?php include '../dashboard/header.php'; ?>
<div class="container-fluid asset-page">
    <div class="card">
        <div class="card-header bg-dark text-white d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h4 class="mb-0">Asset Catalog / Stock Summary</h4>
            <div>
                <a href="../dashboard/index.php" class="btn btn-light btn-sm">Dashboard</a>
                <a href="create_item.php" class="btn btn-success btn-sm">+ New Asset Model</a>
                <a href="asset_list.php" class="btn btn-light btn-sm">Individual Units</a>
            </div>
        </div>
        <?php include '../includes/asset_module_nav.php'; ?>
        <div class="alert alert-info">
            Each row is one asset model/catalog item. Stock counts are computed from its individual physical units.
        </div>
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>Catalog Code</th>
                        <th>Asset</th>
                        <th>Brand / Model</th>
                        <th>Usage</th>
                        <th>Total</th>
                        <th>Available</th>
                        <th>Borrowed</th>
                        <th>Overdue</th>
                        <th>Assigned</th>
                        <th>Repair / Lost</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php while ($row = $catalog->fetch_assoc()): ?>
                    <tr>
                        <td><strong><?= h($row['item_code']) ?></strong></td>
                        <td><?= h($row['item_name']) ?></td>
                        <td><?= h(($row['brand'] ?: '-') . ' / ' . ($row['model'] ?: '-')) ?></td>
                        <td><?= h($row['asset_usage']) ?></td>
                        <td><span class="badge bg-dark"><?= (int) $row['total_units'] ?></span></td>
                        <td><span class="badge bg-success"><?= (int) $row['available_units'] ?></span></td>
                        <td><span class="badge bg-warning text-dark"><?= (int) $row['borrowed_units'] ?></span></td>
                        <td><span class="badge bg-danger"><?= (int) $row['overdue_units'] ?></span></td>
                        <td><span class="badge bg-primary"><?= (int) $row['assigned_units'] ?></span></td>
                        <td><span class="badge bg-danger"><?= (int) $row['unavailable_units'] ?></span></td>
                        <td>
                            <div class="asset-row-actions">
                                <a href="asset_catalog_view.php?inventory_id=<?= (int) $row['id'] ?>" class="btn btn-info btn-sm">View</a>
                                <?php if(in_array($_SESSION['system_role'], ['Boss','Admin','Purchasing'], true)): ?>
                                    <a href="add_asset_units.php?inventory_id=<?= (int) $row['id'] ?>" class="btn btn-success btn-sm">+ Units</a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>
</body>
</html>
