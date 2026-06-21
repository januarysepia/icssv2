<?php

include '../auth/auth_check.php';
require_role(['Boss', 'Admin', 'Technical']);
include '../config/database.php';

$suppliers = $conn->query("
    SELECT id, supplier_name
    FROM suppliers
    WHERE status = 'Active'
    ORDER BY supplier_name ASC
");

$supplier_options = [];
while ($supplier = $suppliers->fetch_assoc()) {
    $supplier_options[] = [
        'id' => (int) $supplier['id'],
        'name' => $supplier['supplier_name'],
    ];
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Create Material Request</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background:#f4f6f9; }
        #itemsTable { min-width:1600px; }
        #itemsTable input, #itemsTable select, #itemsTable textarea { font-size:14px; }
        .search-wrap { position:relative; }
        .search-results {
            display:none;
            position:absolute;
            top:100%;
            left:0;
            right:0;
            max-height:300px;
            overflow-y:auto;
            margin-top:4px;
            background:#fff;
            border:1px solid #ced4da;
            border-radius:8px;
            box-shadow:0 10px 25px rgba(0,0,0,.14);
            z-index:1080;
        }
        .search-results.show { display:block; }
        .search-result {
            display:block;
            width:100%;
            padding:10px 12px;
            text-align:left;
            background:#fff;
            border:0;
            border-bottom:1px solid #edf0f2;
        }
        .search-result:hover, .search-result:focus { background:#f1f5f9; }
        .search-result:last-child { border-bottom:0; }
        .search-result small { display:block; color:#6c757d; }
        .selected-record {
            display:none;
            margin-top:6px;
            padding:8px 10px;
            border-radius:7px;
            background:#e8f4ff;
            color:#0a4b78;
            font-size:13px;
        }
        .selected-record.show { display:block; }
    </style>
</head>
<body>
<div class="container-fluid mt-4">
    <div class="card shadow border-0">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Create Material Request</h4>
            <div>
                <a href="../dashboard/index.php" class="btn btn-light btn-sm">Dashboard</a>
                <a href="request_list.php" class="btn btn-secondary btn-sm">Back</a>
            </div>
        </div>

        <div class="card-body">
            <form action="save_request.php" method="POST" id="mrfForm">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <label class="fw-bold">Job Order</label>
                        <div class="search-wrap">
                            <input type="hidden" name="jo_id" id="joId" required>
                            <input type="text" id="joSearch" class="form-control"
                                   placeholder="Type JO number, project, or client..."
                                   autocomplete="off" required>
                            <div id="joResults" class="search-results"></div>
                        </div>
                        <div id="selectedJo" class="selected-record"></div>
                        <div id="completedJoNotice" class="alert alert-warning mt-2 mb-0 d-none">
                            This JO is completed. The MRF will stay under the same JO as an
                            <strong>After Delivery / Correction</strong> request.
                        </div>
                        <small class="text-muted">
                            Ongoing and completed JOs are searchable. Multiple MRFs are allowed under the same JO.
                        </small>
                    </div>
                    <div class="col-md-3">
                        <label class="fw-bold">Requested By</label>
                        <input type="text" class="form-control" value="<?= h($_SESSION['fullname']) ?>" readonly>
                    </div>
                    <div class="col-md-3">
                        <label class="fw-bold">Request Date</label>
                        <input type="date" class="form-control" value="<?= date('Y-m-d') ?>" readonly>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered align-middle" id="itemsTable">
                        <thead class="table-dark">
                            <tr>
                                <th width="4%">No</th>
                                <th width="16%">Inventory Item</th>
                                <th width="20%">Description</th>
                                <th width="10%">Item Code</th>
                                <th width="10%">Brand</th>
                                <th width="12%">Supplier</th>
                                <th width="7%">Unit</th>
                                <th width="7%">Stock</th>
                                <th width="7%">Qty</th>
                                <th width="9%">Unit Price</th>
                                <th width="8%">Action</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>

                <button type="button" class="btn btn-secondary mb-3" id="addRow">+ Add Item</button>
                <br>
                <button type="submit" class="btn btn-primary">Submit Request</button>
            </form>
        </div>
    </div>
</div>

<script>
const supplierOptions = <?= json_encode($supplier_options, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
let itemRowId = 0;

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, char => ({
        '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#039;'
    })[char]);
}

function debounce(fn, delay = 250) {
    let timer;
    return (...args) => {
        clearTimeout(timer);
        timer = setTimeout(() => fn(...args), delay);
    };
}

function supplierSelectHtml() {
    const options = supplierOptions.map(s =>
        `<option value="${s.id}">${escapeHtml(s.name)}</option>`
    ).join('');
    return `
        <select name="supplier_choice[]" class="form-control supplier-field" required>
            <option value="">Select Supplier</option>
            ${options}
            <option value="__other__">Other / Suggested Supplier</option>
        </select>
        <input type="text" name="supplier_other[]"
               class="form-control mt-1 supplier-other-field d-none"
               placeholder="Enter suggested supplier name">
        <small class="text-muted">Other suppliers will be reviewed by Purchasing.</small>
    `;
}

function addItemRow() {
    const id = ++itemRowId;
    document.querySelector('#itemsTable tbody').insertAdjacentHTML('beforeend', `
        <tr data-row-id="${id}">
            <td class="row-number text-center"></td>
            <td>
                <div class="search-wrap">
                    <input type="hidden" name="inventory_id[]" class="inventory-id">
                    <input type="hidden" name="manual_item[]" class="manual-item">
                    <input type="text" class="form-control inventory-search"
                           placeholder="Type item name, code, brand..." autocomplete="off">
                    <div class="search-results inventory-results"></div>
                </div>
                <div class="selected-record selected-item"></div>
                <small class="text-muted">No result? Your typed text can be used as a manual item.</small>
            </td>
            <td><textarea name="description[]" class="form-control description-field" rows="2" placeholder="Description"></textarea></td>
            <td><input type="text" name="item_code[]" class="form-control item-code-field"></td>
            <td><input type="text" name="brand[]" class="form-control brand-field"></td>
            <td>${supplierSelectHtml()}</td>
            <td><input type="text" name="unit[]" class="form-control unit-field"></td>
            <td><input type="text" class="form-control stock-field" readonly></td>
            <td><input type="number" name="quantity[]" class="form-control qty-field" min="1" required></td>
            <td><input type="number" step="0.01" name="unit_price[]" class="form-control unit-price-field" required></td>
            <td class="text-center"><button type="button" class="btn btn-danger btn-sm removeRow">X</button></td>
        </tr>
    `);
    updateRowNumbers();
}

function updateRowNumbers() {
    document.querySelectorAll('#itemsTable tbody tr').forEach((row, index) => {
        row.querySelector('.row-number').textContent = index + 1;
    });
}

const joSearch = document.getElementById('joSearch');
const joResults = document.getElementById('joResults');
const joId = document.getElementById('joId');
const selectedJo = document.getElementById('selectedJo');
const completedJoNotice = document.getElementById('completedJoNotice');

const searchJobOrders = debounce(async () => {
    const query = joSearch.value.trim();
    joId.value = '';
    selectedJo.classList.remove('show');
    completedJoNotice.classList.add('d-none');

    if (!query) {
        joResults.classList.remove('show');
        return;
    }

    const response = await fetch('ajax_jo_search.php?q=' + encodeURIComponent(query));
    const rows = await response.json();
    joResults.innerHTML = '';

    rows.forEach(jo => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'search-result';
        button.innerHTML = `<strong>${escapeHtml(jo.jo_no || 'No JO Number')} — ${escapeHtml(jo.project_name)}</strong>
            <small>${escapeHtml(jo.client_name)} · ${escapeHtml(jo.workflow_status)} · ${escapeHtml(jo.context)}</small>`;
        button.addEventListener('click', () => {
            joId.value = jo.id;
            joSearch.value = `${jo.jo_no || 'No JO Number'} — ${jo.project_name}`;
            selectedJo.textContent = `${jo.context}: ${jo.workflow_status}`;
            selectedJo.classList.add('show');
            completedJoNotice.classList.toggle('d-none', !jo.is_completed);
            joResults.classList.remove('show');
        });
        joResults.appendChild(button);
    });

    if (!rows.length) {
        joResults.innerHTML = '<div class="p-3 text-muted">No matching job order.</div>';
    }
    joResults.classList.add('show');
});

joSearch.addEventListener('input', searchJobOrders);
document.getElementById('addRow').addEventListener('click', addItemRow);

const searchInventory = debounce(async input => {
    const row = input.closest('tr');
    const query = input.value.trim();
    const results = row.querySelector('.inventory-results');
    const inventoryId = row.querySelector('.inventory-id');
    const hadSelectedItem = inventoryId.value !== '';

    inventoryId.value = '';
    row.querySelector('.manual-item').value = query;
    row.querySelector('.selected-item').classList.remove('show');

    if (hadSelectedItem) {
        row.querySelector('.description-field').value = '';
        row.querySelector('.item-code-field').value = '';
        row.querySelector('.brand-field').value = '';
        row.querySelector('.unit-field').value = '';
        row.querySelector('.stock-field').value = '';
        row.querySelector('.unit-price-field').value = '';
    }

    if (!query) {
        results.classList.remove('show');
        return;
    }

    const response = await fetch('ajax_inventory_search.php?q=' + encodeURIComponent(query));
    const items = await response.json();
    results.innerHTML = '';

    items.forEach(item => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'search-result';
        button.innerHTML = `<strong>${escapeHtml(item.item_name)}</strong>
            <small>${escapeHtml(item.item_code || '-')} · ${escapeHtml(item.brand || '-')} · Stock: ${item.quantity}</small>`;
        button.addEventListener('click', () => {
            row.querySelector('.inventory-id').value = item.id;
            row.querySelector('.manual-item').value = '';
            input.value = `${item.item_name} (${item.item_code || 'No code'})`;
            row.querySelector('.description-field').value = item.item_name || '';
            row.querySelector('.item-code-field').value = item.item_code || '';
            row.querySelector('.brand-field').value = item.brand || '';
            row.querySelector('.unit-field').value = item.unit || '';
            row.querySelector('.stock-field').value = item.quantity;
            row.querySelector('.unit-price-field').value = Number(item.unit_price || 0).toFixed(2);
            const selected = row.querySelector('.selected-item');
            selected.textContent = `Database item selected · Current stock: ${item.quantity}`;
            selected.classList.add('show');
            results.classList.remove('show');
        });
        results.appendChild(button);
    });

    const manual = document.createElement('button');
    manual.type = 'button';
    manual.className = 'search-result';
    manual.innerHTML = `<strong>Use "${escapeHtml(query)}" as a manual item</strong>
        <small>Choose this when the item is not yet in the inventory database.</small>`;
    manual.addEventListener('click', () => {
        row.querySelector('.inventory-id').value = '';
        row.querySelector('.manual-item').value = query;
        row.querySelector('.description-field').value = query;
        const selected = row.querySelector('.selected-item');
        selected.textContent = 'Manual / not in inventory';
        selected.classList.add('show');
        results.classList.remove('show');
    });
    results.appendChild(manual);
    results.classList.add('show');
}, 250);

document.addEventListener('input', event => {
    if (event.target.classList.contains('inventory-search')) {
        searchInventory(event.target);
    }
});

document.addEventListener('change', event => {
    if (!event.target.classList.contains('supplier-field')) return;
    const other = event.target.closest('td').querySelector('.supplier-other-field');
    const isOther = event.target.value === '__other__';
    other.classList.toggle('d-none', !isOther);
    other.required = isOther;
    if (!isOther) other.value = '';
});

document.addEventListener('click', event => {
    if (event.target.classList.contains('removeRow')) {
        const rows = document.querySelectorAll('#itemsTable tbody tr');
        if (rows.length > 1) {
            event.target.closest('tr').remove();
            updateRowNumbers();
        }
    }

    if (!event.target.closest('.search-wrap')) {
        document.querySelectorAll('.search-results').forEach(el => el.classList.remove('show'));
    }
});

document.getElementById('mrfForm').addEventListener('submit', event => {
    if (!joId.value) {
        event.preventDefault();
        alert('Please search and select a job order from the results.');
        joSearch.focus();
    }
});

addItemRow();
</script>
</body>
</html>
