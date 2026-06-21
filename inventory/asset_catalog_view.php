<?php

include '../auth/auth_check.php';
require_role(['Boss', 'Admin', 'Purchasing', 'Supervisor']);
include '../config/database.php';

$inventory_id = intval($_GET['inventory_id'] ?? 0);

$catalog_stmt = $conn->prepare("
    SELECT *
    FROM inventory_items
    WHERE id = ? AND item_type = 'Asset'
");
$catalog_stmt->bind_param('i', $inventory_id);
$catalog_stmt->execute();
$catalog = $catalog_stmt->get_result()->fetch_assoc();

if (!$catalog) {
    http_response_code(404);
    exit('Asset catalog item not found.');
}

$units_stmt = $conn->prepare("
    SELECT
        au.*,
        assigned_user.employee_no AS assigned_employee_no,
        assigned_user.fullname AS assigned_employee_name,
        assigned_department.department_name AS assigned_department,
        bt.id AS borrow_id,
        COALESCE(borrow_user.fullname, bt.borrower_name) AS borrower_name,
        COALESCE(borrow_department.department_name, bt.borrower_department) AS borrower_department,
        bt.purpose,
        bt.borrow_date,
        bt.due_date,
        (bt.due_date IS NOT NULL AND bt.due_date < NOW()) AS is_overdue,
        GREATEST(TIMESTAMPDIFF(DAY, bt.due_date, NOW()), 0) AS days_overdue
    FROM asset_units au
    LEFT JOIN users assigned_user
        ON assigned_user.id = au.assigned_to
    LEFT JOIN departments assigned_department
        ON assigned_department.id = assigned_user.department_id
    LEFT JOIN borrow_transactions bt
        ON bt.asset_unit_id = au.id
       AND bt.status = 'Borrowed'
    LEFT JOIN users borrow_user
        ON borrow_user.id = bt.employee_id
    LEFT JOIN departments borrow_department
        ON borrow_department.id = borrow_user.department_id
    WHERE au.inventory_id = ?
    ORDER BY au.asset_code ASC
");
$units_stmt->bind_param('i', $inventory_id);
$units_stmt->execute();
$units = $units_stmt->get_result();

$history_stmt = $conn->prepare("
    SELECT
        ah.*,
        au.asset_code,
        COALESCE(employee.fullname, ah.borrower_name_snapshot, bt.borrower_name) AS employee_name,
        COALESCE(department.department_name, ah.borrower_department_snapshot, bt.borrower_department) AS employee_department,
        COALESCE(ah.purpose_snapshot, bt.purpose) AS purpose,
        encoder.fullname AS encoded_by_name
    FROM asset_history ah
    LEFT JOIN asset_units au
        ON au.id = ah.asset_unit_id
    LEFT JOIN users employee
        ON employee.id = ah.employee_id
    LEFT JOIN departments department
        ON department.id = employee.department_id
    LEFT JOIN borrow_transactions bt
        ON bt.id = ah.borrow_transaction_id
    LEFT JOIN users encoder
        ON encoder.id = ah.created_by
    WHERE ah.inventory_id = ?
    ORDER BY ah.created_at DESC, ah.id DESC
");
$history_stmt->bind_param('i', $inventory_id);
$history_stmt->execute();
$history = $history_stmt->get_result();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Asset Catalog Details</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <?php include '../includes/asset_ui.php'; ?>
</head>
<body class="asset-module">
<div class="content-wrapper">
<?php include '../dashboard/header.php'; ?>

<div class="container-fluid asset-page">
    <div class="card">
        <div class="card-header bg-dark text-white d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h4 class="mb-0">Asset Details — <?= h($catalog['item_name']) ?></h4>
            <div class="asset-actions">
                <a href="../dashboard/index.php" class="btn btn-light btn-sm">Dashboard</a>
                <a href="asset_catalog.php" class="btn btn-secondary btn-sm">Stock Summary</a>
                <a href="asset_list.php" class="btn btn-info btn-sm">Individual Units</a>
                <?php if (in_array($_SESSION['system_role'], ['Boss', 'Admin', 'Purchasing'], true)): ?>
                    <a href="add_asset_units.php?inventory_id=<?= $inventory_id ?>" class="btn btn-success btn-sm">+ Add Units</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-3"><div class="detail-item unit-detail"><label>Catalog Code</label><strong><?= h($catalog['item_code']) ?></strong></div></div>
            <div class="col-md-3"><div class="detail-item unit-detail"><label>Brand / Model</label><strong><?= h(($catalog['brand'] ?: '-') . ' / ' . ($catalog['model'] ?: '-')) ?></strong></div></div>
            <div class="col-md-3"><div class="detail-item unit-detail"><label>Usage</label><strong><?= h($catalog['asset_usage']) ?></strong></div></div>
            <div class="col-md-3"><div class="detail-item unit-detail"><label>Location</label><strong><?= h($catalog['storage_location'] ?: 'Not set') ?></strong></div></div>
        </div>

        <h5 class="mb-3">Individual Units and Current Holder</h5>
        <div class="table-responsive mb-4">
            <table class="table table-bordered table-hover align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>Asset Code</th>
                        <th>Serial No.</th>
                        <th>Status</th>
                        <th>Condition</th>
                        <th>Current Holder</th>
                        <th>Department</th>
                        <th>Purpose</th>
                        <th>Borrow / Assigned Date</th>
                        <th>Expected Return</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($units->num_rows > 0): ?>
                    <?php while ($unit = $units->fetch_assoc()): ?>
                        <?php
                            $holder = '-';
                            $department = '-';
                            $purpose = '-';
                            $start_date = $unit['assigned_date'] ?: '-';
                            $due_date = '-';

                            if ($unit['unit_status'] === 'Borrowed') {
                                $holder = $unit['borrower_name'] ?: '-';
                                $department = $unit['borrower_department'] ?: '-';
                                $purpose = $unit['purpose'] ?: '-';
                                $start_date = $unit['borrow_date'] ?: '-';
                                $due_date = $unit['due_date'] ?: '-';
                            } elseif ($unit['unit_status'] === 'Assigned') {
                                $holder = trim(($unit['assigned_employee_no'] ?: '') . ' - ' . ($unit['assigned_employee_name'] ?: ''), ' -');
                                $department = $unit['assigned_department'] ?: '-';
                            }
                            $is_overdue = $unit['unit_status'] === 'Borrowed' && (int) $unit['is_overdue'] === 1;
                        ?>
                        <tr>
                            <td><strong><?= h($unit['asset_code']) ?></strong></td>
                            <td><?= h($unit['serial_number'] ?: '-') ?></td>
                            <td>
                                <?php if ($is_overdue): ?>
                                    <span class="badge bg-danger">Overdue</span>
                                <?php else: ?>
                                    <span class="badge bg-<?= $unit['unit_status'] === 'Available' ? 'success' : ($unit['unit_status'] === 'Borrowed' ? 'warning text-dark' : 'primary') ?>"><?= h($unit['unit_status']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?= h($unit['condition_status']) ?></td>
                            <td><?= h($holder ?: '-') ?></td>
                            <td><?= h($department) ?></td>
                            <td><?= h($purpose) ?></td>
                            <td><?= h($start_date) ?></td>
                            <td class="<?= $is_overdue ? 'text-danger fw-bold' : '' ?>">
                                <?= h($due_date) ?>
                                <?php if ($is_overdue): ?>
                                    <div class="small"><?= max(1, (int) $unit['days_overdue']) ?> day(s) overdue</div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="asset-row-actions">
                                    <a href="asset_history.php?unit_id=<?= (int) $unit['id'] ?>" class="btn btn-info btn-sm">History</a>
                                    <a href="asset_unit_qr_view.php?unit_id=<?= (int) $unit['id'] ?>" class="btn btn-dark btn-sm">QR</a>
                                    <?php if ($unit['unit_status'] === 'Borrowed' && !empty($unit['borrow_id'])): ?>
                                        <a href="return_item.php?borrow_id=<?= (int) $unit['borrow_id'] ?>" class="btn btn-success btn-sm">Return</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="10" class="empty">No individual asset units found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <h5 class="mb-3">Complete Activity History</h5>
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>Date / Time</th>
                        <th>Asset Code</th>
                        <th>Action</th>
                        <th>Employee / Borrower</th>
                        <th>Department</th>
                        <th>Purpose</th>
                        <th>Condition</th>
                        <th>Remarks</th>
                        <th>Encoded By</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($history->num_rows > 0): ?>
                    <?php while ($log = $history->fetch_assoc()): ?>
                        <tr>
                            <td><?= h($log['created_at']) ?></td>
                            <td><?= h($log['asset_code'] ?: '-') ?></td>
                            <td><span class="badge bg-secondary"><?= h($log['action_type']) ?></span></td>
                            <td><?= h($log['employee_name'] ?: '-') ?></td>
                            <td><?= h($log['employee_department'] ?: '-') ?></td>
                            <td><?= h($log['purpose'] ?: '-') ?></td>
                            <td><?= h($log['condition_status'] ?: '-') ?></td>
                            <td><?= nl2br(h($log['remarks'] ?: '-')) ?></td>
                            <td><?= h($log['encoded_by_name'] ?: '-') ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="9" class="empty">No activity history yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>
</body>
</html>
