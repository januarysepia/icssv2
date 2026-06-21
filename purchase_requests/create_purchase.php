<?php

include '../auth/auth_check.php';

require_role([
    'Purchasing',
    'Admin'
]);

include '../config/database.php';
$supplier_options = [];

$supplier_result = $conn->query("
SELECT supplier_name
FROM suppliers
WHERE status = 'Active'
ORDER BY supplier_name ASC
");

while($supplier = $supplier_result->fetch_assoc()){
    $supplier_options[] = $supplier['supplier_name'];
}
$purchase_type = $_GET['purchase_type'] ?? 'from_mr';

$material_requests = $conn->query("
SELECT DISTINCT
material_requests.id,
material_requests.request_no,
job_orders.jo_no,
job_orders.project_name

FROM material_requests

LEFT JOIN job_orders
ON job_orders.id = material_requests.jo_id

INNER JOIN material_request_items
ON material_request_items.request_id = material_requests.id

WHERE material_request_items.item_status IN (
    'To Purchase'
)

ORDER BY material_requests.id DESC
");
$suppliers = $conn->query("
SELECT *
FROM suppliers
WHERE status='Active'
ORDER BY supplier_name ASC
");
$selected_request_id = isset($_GET['material_request_id'])
? intval($_GET['material_request_id'])
: 0;

$items = null;

if($selected_request_id > 0){

    $items = $conn->query("
    SELECT *
    FROM material_request_items
    WHERE request_id = '$selected_request_id'
    AND item_status IN (
        'To Purchase'
    )
    ORDER BY id ASC
    ");
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>Create Purchase Request</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body{ background:#f4f6f9; }
        #manualTable{ min-width:1200px; }
        .price-compare-box{margin-top:6px;padding:7px;border:1px solid #dbe3ec;border-radius:7px;background:#f8fafc;font-size:.68rem;line-height:1.3}
        .price-compare-title{font-weight:750;color:#475569;margin-bottom:4px}
        .price-offer{display:flex;justify-content:space-between;gap:8px;padding:3px 5px;border-radius:4px}
        .price-offer.lowest{color:#047857;background:#d1fae5;font-weight:750}
        .price-offer.preferred{outline:1px solid #60a5fa}
        .price-offer.selected{box-shadow:inset 0 0 0 2px #2563eb}
        .price-offer-label{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
        .price-compare-empty{color:#6b7280}
        html[data-theme="dark"] .price-compare-box{background:#252525;border-color:#444}
        html[data-theme="dark"] .price-compare-title{color:#d1d5db}
        html[data-theme="dark"] .price-offer.lowest{color:#bbf7d0;background:#164e3b}
    </style>
</head>

<body class="bg-light">

<div class="container-fluid mt-4">

<div class="card shadow border-0">

<div class="card-header bg-dark text-white">
    <div class="d-flex justify-content-between align-items-center">
        <h4 class="mb-0">Create Purchase Request</h4>

        <div>
            <a href="../dashboard/index.php" class="btn btn-light btn-sm">Dashboard</a>
            <a href="purchase_list.php" class="btn btn-secondary btn-sm">Back</a>
        </div>
    </div>
</div>

<div class="card-body">

    <form method="GET" class="mb-4">

        <label class="fw-bold">Purchase Type</label>

        <div class="row">

            <div class="col-md-4">
                <select name="purchase_type" class="form-control" onchange="this.form.submit()">
                    <option value="from_mr" <?php echo ($purchase_type == 'from_mr') ? 'selected' : ''; ?>>
                        From Material Request
                    </option>

                    <option value="manual" <?php echo ($purchase_type == 'manual') ? 'selected' : ''; ?>>
                        Manual Purchase
                    </option>
                </select>
            </div>

        </div>

    </form>

    <?php if($purchase_type == 'from_mr'){ ?>

        <form method="GET" class="mb-4">

            <input type="hidden" name="purchase_type" value="from_mr">

            <label class="fw-bold">Select Material Request</label>

            <div class="row">

                <div class="col-md-8">

                    <select name="material_request_id" class="form-control" required>

                        <option value="">
                            Select Material Request with For Purchase Items
                        </option>

                        <?php while($mr = $material_requests->fetch_assoc()){ ?>

                            <option value="<?php echo $mr['id']; ?>"
                                <?php echo ($selected_request_id == $mr['id']) ? 'selected' : ''; ?>>

                                <?php echo $mr['request_no']; ?>
                                -
                                <?php echo $mr['jo_no']; ?>
                                -
                                <?php echo $mr['project_name']; ?>

                            </option>

                        <?php } ?>

                    </select>

                </div>

                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary">
                        Load Items
                    </button>
                </div>

            </div>

        </form>

        <?php if($selected_request_id > 0){ ?>

            <form action="save_purchase.php" method="POST">
                <?php echo csrf_field(); ?>

                <input type="hidden" name="purchase_type" value="from_mr">
                <input type="hidden" name="material_request_id" value="<?php echo $selected_request_id; ?>">

                <div class="mb-3">
                    <label class="fw-bold">Remarks</label>
                    <input type="text" name="remarks" class="form-control" placeholder="Optional purchasing remarks">
                </div>

                <div class="table-responsive">

                    <table class="table table-bordered align-middle">

                        <thead class="table-dark">
                            <tr>
                                <th width="80">Include</th>
                                <th>Description</th>
                                <th>Item Code</th>
                                <th>Brand</th>
                                <th>Supplier</th>
                                <th>Unit</th>
                                <th>Qty</th>
                                <th>Unit Price</th>
                                <th>Item Status</th>
                            </tr>
                        </thead>

                        <tbody>

                        <?php if($items && $items->num_rows > 0){ ?>

                            <?php while($item = $items->fetch_assoc()){ ?>

                                <tr>
                                    <td class="text-center">
                                        <input type="checkbox"
                                               name="selected_items[]"
                                               value="<?php echo $item['id']; ?>"
                                               checked>
                                    </td>

                                    <td><?php echo $item['description']; ?></td>
                                    <td><?php echo $item['item_code']; ?></td>
                                    <td><?php echo $item['brand']; ?></td>

                                    <td>

                                        <select
                                            name="supplier_<?php echo $item['id']; ?>"
                                            class="form-control mr-supplier-field"
                                            required>

                                            <option value="">
                                                Select Supplier
                                            </option>

                                            <?php

                                            $item_inventory_id = intval($item['inventory_id'] ?? 0);
                                            $linked_supplier_names = [];
                                            $linked_supplier_offers = [];

                                            if($item_inventory_id > 0){

                                                $linked_suppliers = $conn->query("
                                                SELECT
                                                suppliers.supplier_name,
                                                item_suppliers.unit_price,
                                                item_suppliers.is_preferred

                                                FROM item_suppliers

                                                INNER JOIN suppliers
                                                ON suppliers.id = item_suppliers.supplier_id

                                                WHERE item_suppliers.inventory_id = '$item_inventory_id'

                                                ORDER BY item_suppliers.is_preferred DESC, item_suppliers.unit_price ASC
                                                ");

                                                if($linked_suppliers && $linked_suppliers->num_rows > 0){
                                                    ?>

                                                    <optgroup label="Suppliers for this item">

                                                        <?php while($ls = $linked_suppliers->fetch_assoc()){

                                                            $linked_supplier_names[] = $ls['supplier_name'];
                                                            $linked_supplier_offers[] = $ls;
                                                        ?>

                                                            <option
                                                                value="<?php echo $ls['supplier_name']; ?>"
                                                                data-price="<?php echo $ls['unit_price']; ?>"
                                                                <?php echo ($item['supplier'] == $ls['supplier_name']) ? 'selected' : ''; ?>>

                                                                <?php echo $ls['supplier_name']; ?>
                                                                <?php echo $ls['is_preferred'] ? ' (Preferred)' : ''; ?>
                                                                - &#8369;<?php echo number_format($ls['unit_price'], 2); ?>

                                                            </option>

                                                        <?php } ?>

                                                    </optgroup>

                                                    <optgroup label="All Suppliers">

                                                    <?php } ?>

                                            <?php } ?>

                                            <?php

                                            $supplier_list = $conn->query("
                                            SELECT *
                                            FROM suppliers
                                            WHERE status='Active'
                                            ORDER BY supplier_name ASC
                                            ");

                                            while($supplier = $supplier_list->fetch_assoc()){

                                                if(in_array($supplier['supplier_name'], $linked_supplier_names)){
                                                    continue;
                                                }

                                            ?>

                                                <option
                                                    value="<?php echo $supplier['supplier_name']; ?>"
                                                    <?php echo ($item['supplier'] == $supplier['supplier_name']) ? 'selected' : ''; ?>>

                                                    <?php echo $supplier['supplier_name']; ?>

                                                </option>

                                            <?php } ?>

                                            <?php if($item_inventory_id > 0 && count($linked_supplier_names) > 0){ ?>
                                                </optgroup>
                                            <?php } ?>

                                        </select>

                                        <div class="price-compare-box">
                                            <div class="price-compare-title">Supplier Price Comparison</div>
                                            <?php if ($linked_supplier_offers): ?>
                                                <?php $lowest_offer = min(array_column($linked_supplier_offers, 'unit_price')); ?>
                                                <?php foreach ($linked_supplier_offers as $offer): ?>
                                                    <div class="price-offer <?= (float) $offer['unit_price'] === (float) $lowest_offer ? 'lowest' : '' ?> <?= (int) $offer['is_preferred'] ? 'preferred' : '' ?> <?= $item['supplier'] === $offer['supplier_name'] ? 'selected' : '' ?>"
                                                         data-supplier="<?= h($offer['supplier_name']) ?>">
                                                        <span class="price-offer-label">
                                                            <?= h($offer['supplier_name']) ?><?= (int) $offer['is_preferred'] ? ' ★ Preferred' : '' ?>
                                                        </span>
                                                        <span>₱<?= number_format((float) $offer['unit_price'], 2) ?></span>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <div class="price-compare-empty">No saved supplier prices for this item.</div>
                                            <?php endif; ?>
                                        </div>

                                    </td>

                                    <td><?php echo $item['unit']; ?></td>

                                    <td>
                                        <input type="number"
                                               name="quantity_<?php echo $item['id']; ?>"
                                               class="form-control"
                                               value="<?php echo $item['quantity']; ?>"
                                               required>
                                    </td>

                                    <td>
                                        <input type="number"
                                               step="0.01"
                                               name="unit_price_<?php echo $item['id']; ?>"
                                               class="form-control mr-unit-price-field"
                                               value="<?php echo $item['unit_price']; ?>"
                                               required>
                                    </td>

                                    <td>
                                        <span class="badge bg-primary">
                                            <?php echo $item['item_status']; ?>
                                        </span>
                                    </td>
                                </tr>

                            <?php } ?>

                        <?php }else{ ?>

                            <tr>
                                <td colspan="9" class="text-center text-muted">
                                    No items available for purchase request.
                                </td>
                            </tr>

                        <?php } ?>

                        </tbody>

                    </table>

                </div>

                <?php if($items && $items->num_rows > 0){ ?>
                    <button type="submit" class="btn btn-success">
                        Submit for Boss Approval
                    </button>
                <?php } ?>

            </form>

        <?php } ?>

    <?php } ?>

    <?php if($purchase_type == 'manual'){ ?>

        <form action="save_purchase.php" method="POST">
            <?php echo csrf_field(); ?>

            <input type="hidden" name="purchase_type" value="manual">

            <div class="mb-3">
                <label class="fw-bold">Remarks</label>
                <input type="text" name="remarks" class="form-control" placeholder="Purpose / reason for manual purchase">
            </div>

            <p class="text-muted small">
                Start typing in the Item field to search existing inventory items.
                Picking an item will auto-fill its code, brand, unit, and known suppliers.
                You can also type a brand-new item name if it isn't in inventory yet.
            </p>

            <div class="table-responsive">

                <table class="table table-bordered align-middle" id="manualTable">

                    <thead class="table-dark">
                        <tr>
                            <th width="220">Item</th>
                            <th>Item Code</th>
                            <th>Brand</th>
                            <th width="220">Supplier</th>
                            <th>Unit</th>
                            <th>Qty</th>
                            <th>Unit Price</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>

                        <tr class="manual-row">

                            <td>
                                <input type="text"
                                       name="description[]"
                                       class="form-control item-search-input"
                                       autocomplete="off"
                                       placeholder="Search item or type new"
                                       required>

                                <input type="hidden" name="inventory_id[]" class="inventory-id-field" value="">

                                <div class="item-search-results list-group position-absolute shadow"
                                     style="z-index:1050; display:none;"></div>
                            </td>

                            <td><input type="text" name="item_code[]" class="form-control item-code-field"></td>
                            <td><input type="text" name="brand[]" class="form-control brand-field"></td>

                            <td>
                                <select name="supplier[]" class="form-control supplier-field" required>
                                    <option value="">Select Supplier</option>

                                    <?php foreach($supplier_options as $supplier_name){ ?>
                                        <option value="<?php echo $supplier_name; ?>">
                                            <?php echo $supplier_name; ?>
                                        </option>
                                    <?php } ?>

                                    <option value="__other__">+ Other / New Supplier</option>
                                </select>

                                <input type="text"
                                       name="supplier_other[]"
                                       class="form-control supplier-other-field mt-1 d-none"
                                       placeholder="Type new supplier name">
                                <div class="price-compare-box">
                                    <div class="price-compare-title">Supplier Price Comparison</div>
                                    <div class="price-compare-empty">Select an inventory item to view offers.</div>
                                </div>
                            </td>

                            <td><input type="text" name="unit[]" class="form-control unit-field" required></td>
                            <td><input type="number" name="quantity[]" class="form-control" min="1" required></td>
                            <td><input type="number" step="0.01" name="unit_price[]" class="form-control unit-price-field" required></td>
                            <td class="text-center">
                                <button type="button" class="btn btn-danger btn-sm removeRow">X</button>
                            </td>
                        </tr>

                    </tbody>

                </table>

            </div>

            <button type="button" class="btn btn-secondary mb-3" id="addRow">
                + Add Item
            </button>

            <br>

            <button type="submit" class="btn btn-success">
                Submit Manual Purchase for Boss Approval
            </button>

        </form>

    <?php } ?>

</div>

</div>

</div>
<script>
let supplierOptions = `
<option value="">Select Supplier</option>
<?php foreach($supplier_options as $supplier_name){ ?>
<option value="<?php echo $supplier_name; ?>"><?php echo $supplier_name; ?></option>
<?php } ?>
<option value="__other__">+ Other / New Supplier</option>
`;
</script>
<script>

function escapePurchaseHtml(value){
    return String(value ?? '').replace(/[&<>"']/g, function(character){
        return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[character];
    });
}

function renderPriceComparison(row, suppliers, selectedSupplier = ''){
    const box = row.querySelector('.price-compare-box');
    if(!box) return;

    if(!suppliers || suppliers.length === 0){
        box.innerHTML = `
            <div class="price-compare-title">Supplier Price Comparison</div>
            <div class="price-compare-empty">No saved supplier prices for this item.</div>`;
        return;
    }

    const lowest = Math.min(...suppliers.map(supplier => Number(supplier.unit_price || 0)));
    box.innerHTML = `
        <div class="price-compare-title">Supplier Price Comparison</div>
        ${suppliers.map(function(supplier){
            const price = Number(supplier.unit_price || 0);
            return `
                <div class="price-offer ${price === lowest ? 'lowest' : ''} ${supplier.is_preferred ? 'preferred' : ''} ${supplier.supplier_name === selectedSupplier ? 'selected' : ''}"
                     data-supplier="${escapePurchaseHtml(supplier.supplier_name)}">
                    <span class="price-offer-label">
                        ${escapePurchaseHtml(supplier.supplier_name)}
                        ${supplier.is_preferred ? ' ★ Preferred' : ''}
                    </span>
                    <span>₱${price.toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2})}</span>
                </div>`;
        }).join('')}`;
}

function buildManualRow(){

    return `
    <tr class="manual-row">

        <td>
            <input type="text"
                   name="description[]"
                   class="form-control item-search-input"
                   autocomplete="off"
                   placeholder="Search item or type new"
                   required>

            <input type="hidden" name="inventory_id[]" class="inventory-id-field" value="">

            <div class="item-search-results list-group position-absolute shadow"
                 style="z-index:1050; display:none;"></div>
        </td>

        <td><input type="text" name="item_code[]" class="form-control item-code-field"></td>
        <td><input type="text" name="brand[]" class="form-control brand-field"></td>

        <td>
            <select name="supplier[]" class="form-control supplier-field" required>
                ${supplierOptions}
            </select>

            <input type="text"
                   name="supplier_other[]"
                   class="form-control supplier-other-field mt-1 d-none"
                   placeholder="Type new supplier name">
            <div class="price-compare-box">
                <div class="price-compare-title">Supplier Price Comparison</div>
                <div class="price-compare-empty">Select an inventory item to view offers.</div>
            </div>
        </td>

        <td><input type="text" name="unit[]" class="form-control unit-field" required></td>
        <td><input type="number" name="quantity[]" class="form-control" min="1" required></td>
        <td><input type="number" step="0.01" name="unit_price[]" class="form-control unit-price-field" required></td>
        <td class="text-center">
            <button type="button" class="btn btn-danger btn-sm removeRow">X</button>
        </td>
    </tr>
    `;
}

let addRowBtn = document.getElementById('addRow');

if(addRowBtn){

    addRowBtn.addEventListener('click', function(){

        let table = document.querySelector('#manualTable tbody');

        table.insertAdjacentHTML('beforeend', buildManualRow());
    });
}

document.addEventListener('click', function(e){

    if(e.target.classList.contains('removeRow')){

        let rows = document.querySelectorAll('#manualTable tbody tr');

        if(rows.length > 1){
            e.target.closest('tr').remove();
        }
    }
});

/*
SUPPLIER "OTHER" TOGGLE
*/

document.addEventListener('change', function(e){

    if(e.target.classList.contains('supplier-field')){

        let row = e.target.closest('tr');
        let otherField = row.querySelector('.supplier-other-field');

        if(e.target.value === '__other__'){
            otherField.classList.remove('d-none');
            otherField.required = true;
        }else{
            otherField.classList.add('d-none');
            otherField.required = false;
            otherField.value = '';
        }
    }
});

/*
ITEM SEARCH (LINKS ROW TO INVENTORY ITEM + AUTO-FILLS SUPPLIERS)
*/

let itemSearchTimeout = null;

document.addEventListener('input', function(e){

    if(!e.target.classList.contains('item-search-input')){
        return;
    }

    let input = e.target;
    let row = input.closest('tr');
    let resultsBox = row.querySelector('.item-search-results');

    // typing manually clears any previous inventory link
    row.querySelector('.inventory-id-field').value = '';

    let query = input.value.trim();

    clearTimeout(itemSearchTimeout);

    if(query.length < 2){
        resultsBox.style.display = 'none';
        resultsBox.innerHTML = '';
        return;
    }

    itemSearchTimeout = setTimeout(function(){

        fetch('ajax_item_search.php?search=' + encodeURIComponent(query))
            .then(res => res.json())
            .then(data => {

                if(!data || data.length === 0){
                    resultsBox.style.display = 'none';
                    resultsBox.innerHTML = '';
                    return;
                }

                resultsBox.innerHTML = data.map(function(item){

                    return `
                    <button type="button"
                            class="list-group-item list-group-item-action item-result-option"
                            data-item='${JSON.stringify(item).replace(/'/g, "&apos;")}'>
                        <strong>${item.item_name}</strong>
                        <div class="small text-muted">${item.item_code || ''} ${item.brand ? '&middot; ' + item.brand : ''}</div>
                    </button>
                    `;
                }).join('');

                resultsBox.style.display = 'block';
            })
            .catch(() => {
                resultsBox.style.display = 'none';
            });

    }, 300);
});

document.addEventListener('click', function(e){

    let option = e.target.closest('.item-result-option');

    if(!option){

        // clicked elsewhere - close any open result boxes
        document.querySelectorAll('.item-search-results').forEach(function(box){
            box.style.display = 'none';
        });

        return;
    }

    let item = JSON.parse(option.dataset.item.replace(/&apos;/g, "'"));
    let row = option.closest('tr');

    row.querySelector('.item-search-input').value = item.item_name;
    row.querySelector('.inventory-id-field').value = item.inventory_id;
    row.querySelector('.item-code-field').value = item.item_code || '';
    row.querySelector('.brand-field').value = item.brand || '';
    row.querySelector('.unit-field').value = item.unit || '';
    row.querySelector('.unit-price-field').value = item.unit_price || '';

    let supplierSelect = row.querySelector('.supplier-field');
    let otherField = row.querySelector('.supplier-other-field');

    // rebuild supplier dropdown: linked suppliers first, then the general list
    let optionsHtml = '<option value="">Select Supplier</option>';

    if(item.suppliers && item.suppliers.length > 0){

        optionsHtml += '<optgroup label="Suppliers for this item">';

        item.suppliers.forEach(function(s){
            optionsHtml += `<option value="${s.supplier_name}" data-price="${s.unit_price}" ${s.is_preferred ? 'selected' : ''}>
                ${s.supplier_name}${s.is_preferred ? ' (Preferred)' : ''} - ₱${s.unit_price.toFixed(2)}
            </option>`;
        });

        optionsHtml += '</optgroup>';
    }

    optionsHtml += '<optgroup label="All Suppliers">' + supplierOptions + '</optgroup>';

    supplierSelect.innerHTML = optionsHtml;
    renderPriceComparison(row, item.suppliers || [], supplierSelect.value);
    row.dataset.supplierOffers = JSON.stringify(item.suppliers || []);
    otherField.classList.add('d-none');
    otherField.required = false;

    // if a preferred supplier was auto-selected, use its price
    let selectedOption = supplierSelect.options[supplierSelect.selectedIndex];

    if(selectedOption && selectedOption.dataset.price){
        row.querySelector('.unit-price-field').value = selectedOption.dataset.price;
    }

    row.querySelector('.item-search-results').style.display = 'none';
});

// update price field when a linked supplier (with known price) is selected
document.addEventListener('change', function(e){

    if(e.target.classList.contains('supplier-field') && e.target.value !== '__other__'){

        let selectedOption = e.target.options[e.target.selectedIndex];

        if(selectedOption && selectedOption.dataset.price){
            let row = e.target.closest('tr');
            row.querySelector('.unit-price-field').value = selectedOption.dataset.price;
            renderPriceComparison(row, JSON.parse(row.dataset.supplierOffers || '[]'), e.target.value);
        }
    }

    if(e.target.classList.contains('mr-supplier-field')){
        const selectedOption = e.target.options[e.target.selectedIndex];
        const row = e.target.closest('tr');
        if(selectedOption && selectedOption.dataset.price){
            row.querySelector('.mr-unit-price-field').value = selectedOption.dataset.price;
        }
        row.querySelectorAll('.price-offer').forEach(function(offer){
            offer.classList.toggle(
                'selected',
                offer.dataset.supplier === e.target.value
            );
        });
    }
});

</script>


</body>
</html>
