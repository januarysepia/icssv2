<?php

include '../auth/auth_check.php';

require_role([
    'Admin',
    'Purchasing'
]);

include '../config/database.php';

$id = intval($_GET['id']);

$item = $conn->query("
SELECT *
FROM inventory_items
WHERE id = '$id'
")->fetch_assoc();

if(!$item){
    die("Item not found.");
}

if (strcasecmp((string) ($item['item_type'] ?? ''), 'Asset') === 0) {
    die("Asset quantities must be managed through individual units in the Asset Catalog.");
}

$suppliers = $conn->query("
    SELECT id, supplier_code, supplier_name
    FROM suppliers
    WHERE status = 'Active'
    ORDER BY supplier_name ASC
");

?>

<!DOCTYPE html>
<html>
<head>
    <title>Manual Stock Adjustment</title>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">
    <?php include '../includes/asset_ui.php'; ?>
</head>

<body class="bg-light asset-module">

<div class="content-wrapper">
<?php include '../dashboard/header.php'; ?>

<div class="container-fluid asset-page">

    <div class="card shadow border-0">

        <div class="card-header bg-dark text-white">
            <h4 class="mb-0">
                Manual Stock Adjustment - <?php echo h($item['item_code']); ?>
            </h4>
        </div>

        <div class="card-body">

            <div class="alert alert-warning">
                <strong>For exceptional adjustments only.</strong>
                Items received through an approved Purchase Request are already added to inventory automatically.
                Do not encode them again here.
            </div>

            <div class="row mb-3">

                <div class="col-md-6">
                    <p><b>Item Code:</b> <?php echo $item['item_code']; ?></p>
                    <p><b>Item Name:</b> <?php echo $item['item_name']; ?></p>
                    <p><b>Brand:</b> <?php echo $item['brand']; ?></p>
                </div>

                <div class="col-md-6">
                    <p><b>Current Quantity:</b> <?php echo $item['quantity']; ?></p>
                    <p><b>Unit:</b> <?php echo $item['unit']; ?></p>
                    <p><b>Location:</b> <?php echo $item['storage_location']; ?></p>
                </div>

            </div>

            <hr>

            <form action="save_restock.php" method="POST">
                <?php echo csrf_field(); ?>

                <input type="hidden"
                       name="item_id"
                       value="<?php echo $item['id']; ?>">

                <div class="mb-3">
                    <label class="fw-bold">Adjustment Category</label>

                    <select name="adjustment_category" class="form-select" required>
                        <option value="">Select reason category</option>
                        <option value="Beginning Inventory">Beginning Inventory</option>
                        <option value="Stock Count Correction">Stock Count Correction</option>
                        <option value="Donation or Free Replacement">Donation / Free Replacement</option>
                        <option value="Returned Unused Materials">Returned Unused Materials</option>
                        <option value="Emergency Purchase">Emergency Purchase Outside Regular PR</option>
                        <option value="Other">Other</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="fw-bold">Movement</label>

                    <select name="movement_type" class="form-select" required>
                        <option value="">Select movement</option>
                        <option value="Stock In">Stock In — add quantity</option>
                        <option value="Stock Out">Stock Out — deduct quantity</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="fw-bold">Adjustment Quantity</label>

                    <input type="number"
                           name="quantity"
                           class="form-control"
                           min="1"
                           required>
                </div>

                <div class="mb-3">
                    <label class="fw-bold">Supplier <span class="text-muted">(if applicable)</span></label>

                    <select name="supplier_id" class="form-select">
                        <option value="">Not applicable</option>
                        <?php while($supplier = $suppliers->fetch_assoc()){ ?>
                            <option value="<?php echo (int) $supplier['id']; ?>"
                                <?php echo (($item['supplier'] ?? '') === $supplier['supplier_name']) ? 'selected' : ''; ?>>
                                <?php echo h($supplier['supplier_code'] . ' - ' . $supplier['supplier_name']); ?>
                            </option>
                        <?php } ?>
                    </select>
                    <div class="form-text">
                        Suppliers are maintained in Supplier Management.
                    </div>
                </div>

                <div class="mb-3">
                    <label class="fw-bold">Unit Price</label>

                    <input type="number"
                           step="0.01"
                           name="unit_price"
                           class="form-control"
                           value="<?php echo $item['unit_price'] ?? 0; ?>">
                </div>

                <div class="mb-3">
                    <label class="fw-bold">Detailed Explanation</label>

                    <textarea name="remarks"
                              class="form-control"
                              rows="3"
                              minlength="5"
                              placeholder="Explain why this manual adjustment is necessary..."
                              required></textarea>
                </div>

                <button type="submit"
                        class="btn btn-success">
                    Save Adjustment
                </button>

                <a href="inventory_list.php"
                   class="btn btn-secondary">
                    Cancel
                </a>

            </form>

        </div>

    </div>

</div>

</div>

</body>
</html>
