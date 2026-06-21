<?php

include '../auth/auth_check.php';
require_role(['Purchasing', 'Admin', 'Boss']);
include '../config/database.php';

$supplier_id = max(0, (int) ($_GET['supplier_id'] ?? 0));
$inventory_id = max(0, (int) ($_GET['inventory_id'] ?? 0));
$search = trim($_GET['search'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$per_page = 10;

$suppliers = $conn->query("
    SELECT id, supplier_code, supplier_name
    FROM suppliers
    WHERE status = 'Active'
    ORDER BY supplier_name
");

$items = $conn->query("
    SELECT id, item_code, item_name, brand, unit
    FROM inventory_items
    ORDER BY item_name, brand
");

$conditions = [];
$params = [];
$types = '';

if ($supplier_id > 0) {
    $conditions[] = 'isp.supplier_id = ?';
    $types .= 'i';
    $params[] = $supplier_id;
}
if ($inventory_id > 0) {
    $conditions[] = 'isp.inventory_id = ?';
    $types .= 'i';
    $params[] = $inventory_id;
}
if ($search !== '') {
    $conditions[] = '(ii.item_code LIKE ? OR ii.item_name LIKE ? OR ii.brand LIKE ? OR s.supplier_name LIKE ?)';
    $types .= 'ssss';
    $term = '%' . $search . '%';
    array_push($params, $term, $term, $term, $term);
}

$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
$count_sql = "
    SELECT COUNT(*) AS total
    FROM item_suppliers isp
    INNER JOIN inventory_items ii ON ii.id = isp.inventory_id
    INNER JOIN suppliers s ON s.id = isp.supplier_id
    $where
";
$count_stmt = $conn->prepare($count_sql);
if ($params) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total = (int) ($count_stmt->get_result()->fetch_assoc()['total'] ?? 0);
$total_pages = max(1, (int) ceil($total / $per_page));
$page = min($page, $total_pages);
$offset = ($page - 1) * $per_page;

$sql = "
    SELECT
        isp.*,
        ii.item_code,
        ii.item_name,
        ii.brand,
        ii.unit,
        s.supplier_code,
        s.supplier_name,
        ranked.lowest_price,
        ranked.highest_price,
        ranked.supplier_count
    FROM item_suppliers isp
    INNER JOIN inventory_items ii ON ii.id = isp.inventory_id
    INNER JOIN suppliers s ON s.id = isp.supplier_id
    INNER JOIN (
        SELECT inventory_id, MIN(unit_price) AS lowest_price,
               MAX(unit_price) AS highest_price, COUNT(*) AS supplier_count
        FROM item_suppliers
        GROUP BY inventory_id
    ) ranked ON ranked.inventory_id = isp.inventory_id
    $where
    ORDER BY ii.item_name, isp.is_preferred DESC, isp.unit_price, s.supplier_name
    LIMIT $per_page OFFSET $offset
";
$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$catalog = $stmt->get_result();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Supplier Item Catalog</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body{background:#f4f6f9}
        .catalog-page{max-width:1700px;margin:0 auto;padding:14px}
        .catalog-header{display:flex;align-items:center;justify-content:space-between;gap:10px}
        .catalog-actions{display:flex;flex-wrap:wrap;gap:6px}
        .filter-grid{display:grid;grid-template-columns:1.4fr 1fr 1fr auto auto;gap:8px}
        .catalog-result-meta{color:#6b7280;font-size:.7rem;margin:-4px 0 8px}
        .catalog-pagination{display:flex;align-items:center;justify-content:space-between;gap:10px;padding-top:8px}
        .catalog-table{font-size:.76rem}
        .catalog-table th{white-space:nowrap}
        .price-low{color:#047857;font-weight:750}
        .price-high{color:#b91c1c;font-weight:700}
        .action-cell{white-space:nowrap}
        @media(max-width:768px){
            .catalog-header{align-items:stretch;flex-direction:column}
            .catalog-actions{display:grid;grid-template-columns:1fr 1fr}
            .filter-grid{grid-template-columns:1fr}
            .catalog-table{min-width:1050px}
        }
    </style>
</head>
<body>
<main class="catalog-page">
    <section class="card shadow-sm border-0">
        <header class="card-header bg-dark text-white catalog-header">
            <div>
                <h1 class="h5 mb-1">Supplier Item Catalog</h1>
                <small class="text-light">Current quotations and supplier price comparison</small>
            </div>
            <div class="catalog-actions">
                <a href="../dashboard/index.php" class="btn btn-light btn-sm">Dashboard</a>
                <a href="price_comparison.php" class="btn btn-info btn-sm">Price Comparison</a>
                <a href="suppliers.php" class="btn btn-secondary btn-sm">Suppliers</a>
                <?php if (in_array($_SESSION['system_role'], ['Purchasing', 'Admin'], true)): ?>
                    <a href="supplier_item_form.php" class="btn btn-success btn-sm">+ Add Supplier Item</a>
                <?php endif; ?>
            </div>
        </header>
        <div class="card-body">
            <form method="get" id="catalogFilters" class="filter-grid mb-3">
                <input type="search" name="search" id="catalogSearch" class="form-control"
                       value="<?= h($search) ?>" placeholder="Search item, code, brand, or supplier">
                <select name="supplier_id" id="catalogSupplier" class="form-select">
                    <option value="">All Suppliers</option>
                    <?php while ($supplier = $suppliers->fetch_assoc()): ?>
                        <option value="<?= (int) $supplier['id'] ?>" <?= $supplier_id === (int) $supplier['id'] ? 'selected' : '' ?>>
                            <?= h($supplier['supplier_code'] . ' — ' . $supplier['supplier_name']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                <select name="inventory_id" id="catalogItem" class="form-select">
                    <option value="">All Items</option>
                    <?php while ($item = $items->fetch_assoc()): ?>
                        <option value="<?= (int) $item['id'] ?>" <?= $inventory_id === (int) $item['id'] ? 'selected' : '' ?>>
                            <?= h(($item['item_code'] ?: 'No Code') . ' — ' . $item['item_name']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                <button class="btn btn-primary">Filter</button>
                <button type="button" id="clearCatalogFilters" class="btn btn-outline-secondary">Clear</button>
            </form>
            <div id="catalogResultMeta" class="catalog-result-meta">
                <?php if ($total > 0): ?>
                    Showing <?= (($page - 1) * $per_page) + 1 ?>–<?= min($page * $per_page, $total) ?> of <?= $total ?> supplier items
                <?php else: ?>
                    No supplier items found
                <?php endif; ?>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle catalog-table">
                    <thead class="table-dark">
                    <tr>
                        <th>Item</th><th>Brand</th><th>Supplier</th><th>Supplier Code</th>
                        <th>Unit</th><th>Current Price</th><th>Comparison</th>
                        <th>Preferred</th><th>Last Purchased</th><th>Updated</th><th>Action</th>
                    </tr>
                    </thead>
                    <tbody id="catalogTableBody">
                    <?php if ($catalog->num_rows > 0): ?>
                        <?php while ($row = $catalog->fetch_assoc()): ?>
                            <?php
                            $is_lowest = (float) $row['unit_price'] === (float) $row['lowest_price'];
                            $is_highest = (float) $row['unit_price'] === (float) $row['highest_price']
                                && (int) $row['supplier_count'] > 1;
                            ?>
                            <tr>
                                <td><strong><?= h($row['item_name']) ?></strong><br><small><?= h($row['item_code'] ?: '-') ?></small></td>
                                <td><?= h($row['brand'] ?: '-') ?></td>
                                <td><?= h($row['supplier_name']) ?></td>
                                <td><?= h($row['supplier_item_code'] ?: '-') ?></td>
                                <td><?= h($row['unit'] ?: '-') ?></td>
                                <td class="<?= $is_lowest ? 'price-low' : ($is_highest ? 'price-high' : '') ?>">
                                    ₱<?= number_format((float) $row['unit_price'], 2) ?>
                                </td>
                                <td>
                                    <?php if ((int) $row['supplier_count'] === 1): ?>
                                        <span class="badge bg-secondary">Only supplier</span>
                                    <?php elseif ($is_lowest): ?>
                                        <span class="badge bg-success">Lowest</span>
                                    <?php elseif ($is_highest): ?>
                                        <span class="badge bg-danger">Highest</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">Mid-range</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= (int) $row['is_preferred'] ? '<span class="badge bg-primary">Preferred</span>' : '-' ?></td>
                                <td><?= h($row['last_purchased_at'] ?: '-') ?></td>
                                <td><?= h($row['updated_at']) ?></td>
                                <td class="action-cell">
                                    <a href="supplier_price_history.php?inventory_id=<?= (int) $row['inventory_id'] ?>"
                                       class="btn btn-outline-info btn-sm">History</a>
                                    <?php if (in_array($_SESSION['system_role'], ['Purchasing', 'Admin'], true)): ?>
                                        <a href="supplier_item_form.php?id=<?= (int) $row['id'] ?>"
                                           class="btn btn-outline-primary btn-sm">Edit</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="11" class="text-center text-muted py-4">No supplier items found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="catalog-pagination">
                <span id="catalogPageInfo" class="catalog-result-meta mb-0">Page <?= $page ?> of <?= $total_pages ?></span>
                <nav aria-label="Supplier catalog pagination">
                    <ul id="catalogPagination" class="pagination pagination-sm mb-0"></ul>
                </nav>
            </div>
        </div>
    </section>
</main>
<script>
const catalogForm = document.getElementById('catalogFilters');
const catalogSearch = document.getElementById('catalogSearch');
const catalogSupplier = document.getElementById('catalogSupplier');
const catalogItem = document.getElementById('catalogItem');
const catalogBody = document.getElementById('catalogTableBody');
const catalogMeta = document.getElementById('catalogResultMeta');
const catalogPagination = document.getElementById('catalogPagination');
const catalogPageInfo = document.getElementById('catalogPageInfo');
let catalogTimer = null;
let catalogRequest = null;
let catalogPage = <?= $page ?>;
let catalogTotalPages = <?= $total_pages ?>;

async function loadSupplierCatalog(resetPage = false) {
    if (resetPage) catalogPage = 1;
    if (catalogRequest) catalogRequest.abort();
    catalogRequest = new AbortController();

    const params = new URLSearchParams({
        search: catalogSearch.value.trim(),
        supplier_id: catalogSupplier.value,
        inventory_id: catalogItem.value,
        page: catalogPage
    });

    catalogBody.innerHTML =
        '<tr><td colspan="11" class="text-center text-muted py-4">Searching supplier catalog...</td></tr>';

    try {
        const response = await fetch('ajax_supplier_catalog.php?' + params.toString(), {
            signal: catalogRequest.signal,
            cache: 'no-store'
        });
        if (!response.ok) throw new Error('Unable to search supplier catalog.');
        const data = await response.json();
        catalogBody.innerHTML = data.html;
        const total = Number(data.total || 0);
        const perPage = Number(data.per_page || 10);
        catalogPage = Number(data.page || 1);
        catalogTotalPages = Number(data.total_pages || 1);
        if (total > 0) {
            const start = ((catalogPage - 1) * perPage) + 1;
            const end = Math.min(catalogPage * perPage, total);
            catalogMeta.textContent = `Showing ${start}–${end} of ${total} supplier items`;
        } else {
            catalogMeta.textContent = 'No supplier items found';
        }
        renderCatalogPagination();
        history.replaceState(null, '', 'supplier_catalog.php?' + params.toString());
    } catch (error) {
        if (error.name === 'AbortError') return;
        catalogBody.innerHTML =
            '<tr><td colspan="11" class="text-center text-danger py-4">Unable to load supplier catalog.</td></tr>';
        catalogMeta.textContent = 'Search failed';
    }
}

function catalogPageButton(label, page, disabled = false, active = false) {
    return `
        <li class="page-item ${disabled ? 'disabled' : ''} ${active ? 'active' : ''}">
            <button type="button" class="page-link" data-page="${page}" ${disabled ? 'disabled' : ''}>${label}</button>
        </li>`;
}

function renderCatalogPagination() {
    catalogPageInfo.textContent = `Page ${catalogPage} of ${catalogTotalPages}`;
    if (catalogTotalPages <= 1) {
        catalogPagination.innerHTML = '';
        return;
    }

    let html = catalogPageButton('Previous', catalogPage - 1, catalogPage <= 1);
    const start = Math.max(1, catalogPage - 2);
    const end = Math.min(catalogTotalPages, catalogPage + 2);
    for (let page = start; page <= end; page++) {
        html += catalogPageButton(page, page, false, page === catalogPage);
    }
    html += catalogPageButton('Next', catalogPage + 1, catalogPage >= catalogTotalPages);
    catalogPagination.innerHTML = html;
}

catalogForm.addEventListener('submit', function (event) {
    event.preventDefault();
    loadSupplierCatalog(true);
});

catalogSearch.addEventListener('input', function () {
    clearTimeout(catalogTimer);
    catalogTimer = setTimeout(function(){ loadSupplierCatalog(true); }, 300);
});

catalogSupplier.addEventListener('change', function(){ loadSupplierCatalog(true); });
catalogItem.addEventListener('change', function(){ loadSupplierCatalog(true); });

catalogPagination.addEventListener('click', function(event){
    const button = event.target.closest('[data-page]');
    if (!button || button.disabled) return;
    catalogPage = Math.max(1, Number(button.dataset.page) || 1);
    loadSupplierCatalog();
    document.querySelector('.filter-grid')?.scrollIntoView({behavior:'smooth', block:'start'});
});

document.getElementById('clearCatalogFilters').addEventListener('click', function () {
    catalogSearch.value = '';
    catalogSupplier.value = '';
    catalogItem.value = '';
    loadSupplierCatalog(true);
});

renderCatalogPagination();
</script>
</body>
</html>
