<?php

include '../auth/auth_check.php';
require_role(['Purchasing', 'Admin', 'Boss']);
include '../config/database.php';

$search = trim($_GET['search'] ?? '');
$condition = '';
$params = [];
$types = '';
if ($search !== '') {
    $condition = "WHERE ii.item_code LIKE ? OR ii.item_name LIKE ? OR ii.brand LIKE ?";
    $term = '%' . $search . '%';
    $params = [$term, $term, $term];
    $types = 'sss';
}

$stmt = $conn->prepare("
    SELECT
        ii.id,
        ii.item_code,
        ii.item_name,
        ii.brand,
        ii.unit,
        COUNT(isp.id) AS supplier_count,
        MIN(isp.unit_price) AS lowest_price,
        MAX(isp.unit_price) AS highest_price,
        AVG(isp.unit_price) AS average_price,
        GROUP_CONCAT(
            CONCAT(s.supplier_name, '||', isp.unit_price, '||', isp.is_preferred)
            ORDER BY isp.unit_price ASC, s.supplier_name
            SEPARATOR '##'
        ) AS offers
    FROM inventory_items ii
    INNER JOIN item_suppliers isp ON isp.inventory_id = ii.id
    INNER JOIN suppliers s ON s.id = isp.supplier_id AND s.status = 'Active'
    $condition
    GROUP BY ii.id
    ORDER BY ii.item_name, ii.brand
");
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$comparisons = $stmt->get_result();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Supplier Price Comparison</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body{background:#f4f6f9}.compare-page{max-width:1500px;margin:0 auto;padding:14px}
        .compare-header{display:flex;justify-content:space-between;align-items:center;gap:10px}
        .offer-list{display:flex;flex-direction:column;gap:4px;min-width:260px}
        .offer{display:flex;justify-content:space-between;gap:12px;padding:5px 7px;border-radius:6px;background:#f3f4f6}
        .offer.lowest{color:#065f46;background:#d1fae5;font-weight:750}
        .offer.preferred{outline:1px solid #3b82f6}
        .spread{font-weight:700;color:#b45309}
        @media(max-width:576px){.compare-header{align-items:stretch;flex-direction:column}.compare-header .btn{width:100%}.compare-table{min-width:900px}}
        html[data-theme="dark"] .offer{background:#2d2d2d}
        html[data-theme="dark"] .offer.lowest{color:#bbf7d0;background:#164e3b}
    </style>
</head>
<body>
<main class="compare-page">
    <section class="card shadow-sm border-0">
        <header class="card-header bg-dark text-white compare-header">
            <div>
                <h1 class="h5 mb-1">Supplier Price Comparison</h1>
                <small class="text-light">Compare like-for-like inventory items across suppliers</small>
            </div>
            <div class="d-flex flex-wrap gap-1">
                <a href="../dashboard/index.php" class="btn btn-light btn-sm">Dashboard</a>
                <a href="supplier_catalog.php" class="btn btn-info btn-sm">Supplier Catalog</a>
            </div>
        </header>
        <div class="card-body">
            <form class="d-flex gap-2 mb-3" method="get">
                <input type="search" name="search" class="form-control" value="<?= h($search) ?>"
                       placeholder="Search item code, item, or brand">
                <button class="btn btn-primary">Search</button>
            </form>
            <div class="alert alert-info">
                Lowest price is highlighted, but Purchasing should still consider quality, lead time,
                availability, payment terms, and delivery cost.
            </div>
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle compare-table">
                    <thead class="table-dark">
                    <tr><th>Item</th><th>Brand / Unit</th><th>Supplier Offers</th><th>Lowest</th><th>Highest</th><th>Price Spread</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                    <?php if ($comparisons->num_rows > 0): ?>
                        <?php while ($row = $comparisons->fetch_assoc()): ?>
                            <?php
                            $offers = array_filter(explode('##', (string) $row['offers']));
                            $spread = (float) $row['highest_price'] - (float) $row['lowest_price'];
                            ?>
                            <tr>
                                <td><strong><?= h($row['item_name']) ?></strong><br><small><?= h($row['item_code'] ?: '-') ?></small></td>
                                <td><?= h($row['brand'] ?: '-') ?><br><small><?= h($row['unit'] ?: '-') ?></small></td>
                                <td>
                                    <div class="offer-list">
                                        <?php foreach ($offers as $offer): ?>
                                            <?php
                                            [$supplier_name, $price, $preferred] = array_pad(explode('||', $offer), 3, '');
                                            $is_lowest = (float) $price === (float) $row['lowest_price'];
                                            ?>
                                            <div class="offer <?= $is_lowest ? 'lowest' : '' ?> <?= (int) $preferred ? 'preferred' : '' ?>">
                                                <span><?= h($supplier_name) ?><?= (int) $preferred ? ' ★' : '' ?></span>
                                                <span>₱<?= number_format((float) $price, 2) ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                                <td class="text-success fw-bold">₱<?= number_format((float) $row['lowest_price'], 2) ?></td>
                                <td class="text-danger fw-bold">₱<?= number_format((float) $row['highest_price'], 2) ?></td>
                                <td class="spread">₱<?= number_format($spread, 2) ?></td>
                                <td>
                                    <a href="supplier_catalog.php?inventory_id=<?= (int) $row['id'] ?>" class="btn btn-outline-primary btn-sm">Open</a>
                                    <a href="supplier_price_history.php?inventory_id=<?= (int) $row['id'] ?>" class="btn btn-outline-info btn-sm">History</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">No price comparison data found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</main>
</body>
</html>
