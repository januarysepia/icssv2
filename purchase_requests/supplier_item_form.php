<?php

include '../auth/auth_check.php';
require_role(['Purchasing', 'Admin']);
include '../config/database.php';

$id = max(0, (int) ($_GET['id'] ?? 0));
$record = null;
if ($id > 0) {
    $stmt = $conn->prepare("SELECT * FROM item_suppliers WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $record = $stmt->get_result()->fetch_assoc();
    if (!$record) {
        exit('Supplier item not found.');
    }
}

$selected_supplier = (int) ($_GET['supplier_id'] ?? ($record['supplier_id'] ?? 0));
$selected_item = (int) ($_GET['inventory_id'] ?? ($record['inventory_id'] ?? 0));
$suppliers = $conn->query("SELECT id, supplier_code, supplier_name FROM suppliers WHERE status='Active' ORDER BY supplier_name");
$items = $conn->query("SELECT id, item_code, item_name, brand, unit FROM inventory_items ORDER BY item_name, brand");
?>
<!DOCTYPE html>
<html>
<head>
    <title><?= $record ? 'Edit' : 'Add' ?> Supplier Item</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body{background:#f4f6f9}.form-page{max-width:850px;margin:0 auto;padding:14px}
        .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
        @media(max-width:576px){.form-grid{grid-template-columns:1fr}}
    </style>
</head>
<body>
<main class="form-page">
    <section class="card shadow-sm border-0">
        <header class="card-header bg-dark text-white d-flex justify-content-between align-items-center gap-2">
            <h1 class="h5 mb-0"><?= $record ? 'Edit' : 'Add' ?> Supplier Item</h1>
            <div class="d-flex flex-wrap gap-1">
                <a href="../dashboard/index.php" class="btn btn-light btn-sm">Dashboard</a>
                <a href="supplier_catalog.php" class="btn btn-secondary btn-sm">Back to Catalog</a>
            </div>
        </header>
        <div class="card-body">
            <div class="alert alert-info">
                Encode the supplier's latest quoted price. Use the same inventory item for an accurate comparison.
            </div>
            <form action="save_supplier_item.php" method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) ($record['id'] ?? 0) ?>">
                <div class="form-grid">
                    <div>
                        <label class="form-label">Inventory Item</label>
                        <select name="inventory_id" class="form-select" required <?= $record ? 'disabled' : '' ?>>
                            <option value="">Select Item</option>
                            <?php while ($item = $items->fetch_assoc()): ?>
                                <option value="<?= (int) $item['id'] ?>" <?= $selected_item === (int) $item['id'] ? 'selected' : '' ?>>
                                    <?= h(($item['item_code'] ?: 'No Code') . ' — ' . $item['item_name'] . ($item['brand'] ? ' / ' . $item['brand'] : '')) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <?php if ($record): ?><input type="hidden" name="inventory_id" value="<?= $selected_item ?>"><?php endif; ?>
                    </div>
                    <div>
                        <label class="form-label">Supplier</label>
                        <select name="supplier_id" class="form-select" required <?= $record ? 'disabled' : '' ?>>
                            <option value="">Select Supplier</option>
                            <?php while ($supplier = $suppliers->fetch_assoc()): ?>
                                <option value="<?= (int) $supplier['id'] ?>" <?= $selected_supplier === (int) $supplier['id'] ? 'selected' : '' ?>>
                                    <?= h($supplier['supplier_code'] . ' — ' . $supplier['supplier_name']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <?php if ($record): ?><input type="hidden" name="supplier_id" value="<?= $selected_supplier ?>"><?php endif; ?>
                    </div>
                    <div>
                        <label class="form-label">Supplier Item Code</label>
                        <input type="text" name="supplier_item_code" class="form-control"
                               value="<?= h($record['supplier_item_code'] ?? '') ?>" maxlength="100">
                    </div>
                    <div>
                        <label class="form-label">Current Quoted Price</label>
                        <input type="number" name="unit_price" class="form-control" step="0.01" min="0.01"
                               value="<?= h($record['unit_price'] ?? '') ?>" required>
                    </div>
                </div>
                <div class="form-check my-3">
                    <input type="checkbox" name="is_preferred" value="1" class="form-check-input" id="preferred"
                           <?= !empty($record['is_preferred']) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="preferred">Mark as preferred supplier for this item</label>
                </div>
                <div class="mb-3">
                    <label class="form-label">Quotation / Price Remarks</label>
                    <textarea name="remarks" class="form-control" rows="3"
                              placeholder="Validity, lead time, minimum order, payment terms..."><?= h($record['remarks'] ?? '') ?></textarea>
                </div>
                <div class="d-flex justify-content-end gap-2">
                    <a href="supplier_catalog.php" class="btn btn-secondary">Cancel</a>
                    <button class="btn btn-primary">Save Supplier Item</button>
                </div>
            </form>
        </div>
    </section>
</main>
</body>
</html>
