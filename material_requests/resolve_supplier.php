<?php

include '../auth/auth_check.php';
require_role(['Purchasing', 'Admin']);
include '../config/database.php';

$item_id = intval($_GET['item_id'] ?? 0);

$item = $conn->query("
    SELECT
        mri.*,
        mr.request_no,
        mr.status AS request_status
    FROM material_request_items mri
    INNER JOIN material_requests mr ON mr.id = mri.request_id
    WHERE mri.id = '$item_id'
")->fetch_assoc();

if (!$item) {
    http_response_code(404);
    exit('Material request item not found.');
}

if ($item['request_status'] !== 'Pending Review' || $item['supplier_review_status'] !== 'Pending') {
    exit('This supplier suggestion is no longer pending review.');
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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Review Suggested Supplier</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4" style="max-width:900px;">
    <div class="card shadow border-0">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
            <div>
                <h4 class="mb-1">Review Suggested Supplier</h4>
                <small><?= h($item['request_no']) ?> — <?= h($item['description']) ?></small>
            </div>
            <a href="view_request.php?id=<?= (int) $item['request_id'] ?>" class="btn btn-light btn-sm">Back</a>
        </div>
        <div class="card-body">
            <div class="alert alert-warning">
                Technical suggested <strong><?= h($item['suggested_supplier_name']) ?></strong>.
                Match it to an existing supplier or add it to the centralized Supplier Database.
            </div>

            <form action="save_resolved_supplier.php" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="item_id" value="<?= $item_id ?>">

                <div class="border rounded p-3 mb-3">
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="radio" name="resolution" id="existing" value="existing" checked>
                        <label class="form-check-label fw-bold" for="existing">Match to Existing Supplier</label>
                    </div>
                    <select name="existing_supplier_id" class="form-select">
                        <option value="">Select supplier...</option>
                        <?php while ($supplier = $suppliers->fetch_assoc()): ?>
                            <option value="<?= (int) $supplier['id'] ?>">
                                <?= h($supplier['supplier_code'] . ' — ' . $supplier['supplier_name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="border rounded p-3 mb-3">
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="radio" name="resolution" id="new" value="new">
                        <label class="form-check-label fw-bold" for="new">Add as New Supplier</label>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Supplier Name</label>
                            <input type="text" name="supplier_name" class="form-control"
                                   value="<?= h($item['suggested_supplier_name']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Contact Person</label>
                            <input type="text" name="contact_person" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Mobile Number</label>
                            <input type="text" name="mobile_number" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Address</label>
                            <textarea name="address" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Products Supplied</label>
                            <textarea name="products_supplied" class="form-control" rows="2"><?= h($item['description']) ?></textarea>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-success">Save and Resolve Supplier</button>
                <a href="view_request.php?id=<?= (int) $item['request_id'] ?>" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
    </div>
</div>
</body>
</html>

