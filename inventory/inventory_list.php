<?php

include '../auth/auth_check.php';

require_role([
    'Boss',
    'Admin',
    'Purchasing',
    'Supervisor'
]);

include '../config/database.php';

?>

<!DOCTYPE html>
<html>
<head>
    <title>Inventory Items</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <?php include '../includes/asset_ui.php'; ?>
    <style>
        .inventory-header{
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:12px;
        }
        .inventory-header h4{
            min-width:0;
            overflow-wrap:anywhere;
        }
        .inventory-header-actions{
            display:flex;
            flex-wrap:wrap;
            justify-content:flex-end;
            gap:6px;
        }
        .inventory-filters{
            row-gap:8px;
        }
        .inventory-pagination{
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:10px;
            padding-top:8px;
        }
        .inventory-page-info{
            color:#6b7280;
            font-size:.72rem;
        }
        @media(max-width:576px){
            .inventory-header{
                align-items:stretch;
                flex-direction:column;
            }
            .inventory-header-actions{
                display:grid;
                grid-template-columns:repeat(2,minmax(0,1fr));
                width:100%;
            }
            .inventory-header-actions .btn{
                width:100%;
                white-space:nowrap;
            }
            .inventory-header-actions .btn:last-child:nth-child(odd){
                grid-column:1 / -1;
            }
            .inventory-pagination{
                align-items:flex-start;
                flex-direction:column;
            }
        }
    </style>
</head>

<body class="bg-light asset-module">

<div class="content-wrapper">
<?php include '../dashboard/header.php'; ?>

<div class="container-fluid asset-page">

    <div class="card shadow border-0">

        <div class="card-header bg-dark text-white">

            <div class="inventory-header">

                <h4 class="mb-0">Inventory / Catalog Items</h4>

                <div class="inventory-header-actions">
                    <a href="../dashboard/index.php" class="btn btn-light btn-sm">
                        Dashboard
                    </a>

                    <a href="asset_list.php" class="btn btn-info btn-sm">
                        Asset Units
                    </a>

                    <?php if(
                        $_SESSION['system_role'] == 'Purchasing' ||
                        $_SESSION['system_role'] == 'Admin' ||
                        $_SESSION['system_role'] == 'Boss'
                    ){ ?>
                        <a href="create_item.php" class="btn btn-success btn-sm">
                            + Add Item
                        </a>
                    <?php } ?>
                </div>

            </div>

        </div>

        <div class="card-body">

            <div class="row mb-3 inventory-filters">

                <div class="col-md-6">
                    <input type="text"
                           id="searchInput"
                           class="form-control"
                           placeholder="Search item code, item name, brand, category, location...">
                </div>

                <div class="col-md-3">
                    <select id="typeFilter" class="form-control">
                        <option value="">All Types</option>
                        <option value="Consumable">Consumable</option>
                        <option value="Asset">Asset</option>
                    </select>
                </div>

            </div>

            <div class="table-responsive">

                <table class="table table-bordered table-hover align-middle">

                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Item Code</th>
                            <th>Item Name</th>
                            <th>Type</th>
                            <th>Brand</th>
                            <th>Category</th>
                            <th>Qty</th>
                            <th>Condition</th>
                            <th>Asset Status</th>
                            <th>Location</th>
                            <th>Stock Status</th>
                            <th width="230">Action</th>
                        </tr>
                    </thead>

                    <tbody id="inventoryTableBody">
                        <tr>
                            <td colspan="12" class="text-center">
                                Loading inventory...
                            </td>
                        </tr>
                    </tbody>

                </table>

            </div>

            <div class="inventory-pagination">
                <span id="inventoryPageInfo" class="inventory-page-info">Loading inventory...</span>
                <nav aria-label="Inventory pagination">
                    <ul id="inventoryPagination" class="pagination pagination-sm mb-0"></ul>
                </nav>
            </div>

        </div>

    </div>

</div>

</div>

<script>

let inventoryPage = 1;
let inventoryPages = 1;
let inventoryRequest = null;
let searchTimer = null;

function inventoryMenuOpen(){
    return Boolean(document.querySelector('#inventoryTableBody .dropdown-menu.show'));
}

async function loadInventory(force = false){
    if (!force && inventoryMenuOpen()) return;

    const search = document.getElementById('searchInput').value;
    const type = document.getElementById('typeFilter').value;

    if (inventoryRequest) inventoryRequest.abort();
    inventoryRequest = new AbortController();

    try {
        const response = await fetch(
            'ajax_inventory.php?search=' + encodeURIComponent(search) +
            '&type=' + encodeURIComponent(type) +
            '&page=' + encodeURIComponent(inventoryPage),
            { signal: inventoryRequest.signal, cache:'no-store' }
        );
        if (!response.ok) throw new Error('Failed to load inventory.');

        const data = await response.json();
        if (!force && inventoryMenuOpen()) return;

        inventoryPage = Number(data.page || 1);
        inventoryPages = Number(data.total_pages || 1);
        document.getElementById('inventoryTableBody').innerHTML = data.html;
        renderInventoryPagination(data);
    } catch (error) {
        if (error.name === 'AbortError') return;
        document.getElementById('inventoryTableBody').innerHTML =
        '<tr><td colspan="12" class="text-center text-danger">Failed to load inventory.</td></tr>';
    }
}

function paginationButton(label, page, disabled = false, active = false){
    return `
        <li class="page-item ${disabled ? 'disabled' : ''} ${active ? 'active' : ''}">
            <button type="button" class="page-link" data-page="${page}" ${disabled ? 'disabled' : ''}>
                ${label}
            </button>
        </li>`;
}

function renderInventoryPagination(data){
    const total = Number(data.total || 0);
    const perPage = Number(data.per_page || 10);
    const pageInfo = document.getElementById('inventoryPageInfo');
    const pagination = document.getElementById('inventoryPagination');

    if (total > 0) {
        const start = ((inventoryPage - 1) * perPage) + 1;
        const end = Math.min(inventoryPage * perPage, total);
        pageInfo.textContent = `Showing ${start}–${end} of ${total} items`;
    } else {
        pageInfo.textContent = 'No inventory items found';
    }

    if (inventoryPages <= 1) {
        pagination.innerHTML = '';
        return;
    }

    let html = paginationButton('Previous', inventoryPage - 1, inventoryPage <= 1);
    const startPage = Math.max(1, inventoryPage - 2);
    const endPage = Math.min(inventoryPages, inventoryPage + 2);
    for (let page = startPage; page <= endPage; page++) {
        html += paginationButton(page, page, false, page === inventoryPage);
    }
    html += paginationButton('Next', inventoryPage + 1, inventoryPage >= inventoryPages);
    pagination.innerHTML = html;
}

document.getElementById('searchInput').addEventListener('input', function(){
    clearTimeout(searchTimer);
    searchTimer = setTimeout(function(){
        inventoryPage = 1;
        loadInventory(true);
    }, 300);
});

document.getElementById('typeFilter').addEventListener('change', function(){
    inventoryPage = 1;
    loadInventory(true);
});

document.getElementById('inventoryPagination').addEventListener('click', function(event){
    const button = event.target.closest('[data-page]');
    if (!button || button.disabled) return;
    inventoryPage = Math.max(1, Number(button.dataset.page) || 1);
    loadInventory(true);
    document.querySelector('.inventory-filters')?.scrollIntoView({behavior:'smooth', block:'start'});
});

loadInventory();

/* Refresh quietly, but never replace the table while an Actions menu is open. */
setInterval(function(){ loadInventory(false); }, 15000);

</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
