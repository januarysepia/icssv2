<?php

include '../auth/auth_check.php';
require_role(['Purchasing', 'Admin', 'Boss']);
include '../config/database.php';

$inventory_id = max(0, (int) ($_GET['inventory_id'] ?? 0));
if ($inventory_id <= 0) {
    exit('Inventory item is required.');
}

$item_stmt = $conn->prepare("SELECT id, item_code, item_name, brand, unit FROM inventory_items WHERE id = ?");
$item_stmt->bind_param('i', $inventory_id);
$item_stmt->execute();
$item = $item_stmt->get_result()->fetch_assoc();
if (!$item) {
    exit('Inventory item not found.');
}

$history = $conn->prepare("
    SELECT
        sph.*,
        s.supplier_name,
        u.fullname AS recorded_by_name
    FROM supplier_price_history sph
    INNER JOIN suppliers s ON s.id = sph.supplier_id
    LEFT JOIN users u ON u.id = sph.recorded_by
    WHERE sph.inventory_id = ?
    ORDER BY sph.recorded_at DESC, sph.id DESC
    LIMIT 100
");
$history->bind_param('i', $inventory_id);
$history->execute();
$rows = $history->get_result();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Supplier Price History</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>body{background:#f4f6f9}.history-page{max-width:1200px;margin:0 auto;padding:14px}.history-table{font-size:.77rem}@media(max-width:576px){.history-table{min-width:850px}}</style>
</head>
<body>
<main class="history-page">
    <section class="card shadow-sm border-0">
        <header class="card-header bg-dark text-white d-flex justify-content-between align-items-center gap-2">
            <div>
                <h1 class="h5 mb-1">Price History — <?= h($item['item_name']) ?></h1>
                <small class="text-light"><?= h($item['item_code'] ?: 'No Code') ?> · <?= h($item['brand'] ?: 'No Brand') ?></small>
            </div>
            <div class="d-flex flex-wrap gap-1">
                <a href="../dashboard/index.php" class="btn btn-light btn-sm">Dashboard</a>
                <a href="supplier_catalog.php?inventory_id=<?= $inventory_id ?>" class="btn btn-secondary btn-sm">Back to Catalog</a>
            </div>
        </header>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle history-table">
                    <thead class="table-dark">
                    <tr><th>Date</th><th>Supplier</th><th>Price</th><th>Source</th><th>Reference</th><th>Remarks</th><th>Recorded By</th></tr>
                    </thead>
                    <tbody>
                    <?php if ($rows->num_rows > 0): ?>
                        <?php while ($row = $rows->fetch_assoc()): ?>
                            <tr>
                                <td><?= h($row['recorded_at']) ?></td>
                                <td><strong><?= h($row['supplier_name']) ?></strong></td>
                                <td>₱<?= number_format((float) $row['unit_price'], 2) ?></td>
                                <td><span class="badge bg-secondary"><?= h($row['source_type']) ?></span></td>
                                <td><?= $row['source_id'] ? '#' . (int) $row['source_id'] : '-' ?></td>
                                <td><?= h($row['remarks'] ?: '-') ?></td>
                                <td><?= h($row['recorded_by_name'] ?: 'System') ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">No price history found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</main>
</body>
</html>
