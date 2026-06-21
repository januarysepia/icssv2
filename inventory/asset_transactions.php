<?php

include '../auth/auth_check.php';
require_role(['Boss', 'Admin', 'Purchasing', 'Supervisor']);
include '../config/database.php';
require_once '../includes/overdue_asset_notifications.php';

createOverdueAssetNotifications($conn);

$status = $_GET['status'] ?? 'Borrowed';
$allowed_statuses = ['Borrowed', 'Returned', 'All'];
if (!in_array($status, $allowed_statuses, true)) {
    $status = 'Borrowed';
}

$search = trim($_GET['search'] ?? '');
$conditions = [];
$params = [];
$types = '';

if ($status !== 'All') {
    $conditions[] = 'bt.status = ?';
    $params[] = $status;
    $types .= 's';
}

if ($search !== '') {
    $conditions[] = "(
        au.asset_code LIKE ?
        OR au.serial_number LIKE ?
        OR ii.item_name LIKE ?
        OR COALESCE(u.fullname, bt.borrower_name) LIKE ?
        OR COALESCE(d.department_name, bt.borrower_department) LIKE ?
    )";
    $term = '%' . $search . '%';
    for ($i = 0; $i < 5; $i++) {
        $params[] = $term;
        $types .= 's';
    }
}

$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

$stmt = $conn->prepare("
    SELECT
        bt.*,
        au.asset_code,
        au.serial_number,
        ii.item_name,
        ii.brand,
        COALESCE(u.fullname, bt.borrower_name, 'Unknown borrower') AS display_name,
        COALESCE(d.department_name, bt.borrower_department, '-') AS display_department,
        (bt.status = 'Borrowed' AND bt.due_date < NOW()) AS is_overdue,
        GREATEST(TIMESTAMPDIFF(DAY, bt.due_date, NOW()), 0) AS days_overdue
    FROM borrow_transactions bt
    INNER JOIN asset_units au ON au.id = bt.asset_unit_id
    INNER JOIN inventory_items ii ON ii.id = bt.item_id
    LEFT JOIN users u ON u.id = bt.employee_id
    LEFT JOIN departments d ON d.id = u.department_id
    $where
    ORDER BY
        CASE WHEN bt.status = 'Borrowed' AND bt.due_date < NOW() THEN 0 ELSE 1 END,
        bt.borrow_date DESC,
        bt.id DESC
");

if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$transactions = $stmt->get_result();

$summary = $conn->query("
    SELECT
        SUM(status = 'Borrowed') AS active_total,
        SUM(status = 'Borrowed' AND due_date < NOW()) AS overdue_total,
        SUM(status = 'Returned') AS returned_total
    FROM borrow_transactions
")->fetch_assoc();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Asset Borrow / Return</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <?php include '../includes/asset_ui.php'; ?>
</head>
<body class="asset-module">
<?php include '../dashboard/sidebar.php'; ?>
<div class="content-wrapper">
<?php include '../dashboard/header.php'; ?>

<div class="container-fluid asset-page">
    <div class="card">
        <div class="card-header bg-dark text-white d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h4 class="mb-1">Asset Borrow / Return</h4>
                <small>Active borrowers, due dates, overdue items, and completed returns.</small>
            </div>
            <div class="asset-actions">
                <a href="../dashboard/index.php" class="btn btn-light btn-sm">Dashboard</a>
            </div>
        </div>

        <?php include '../includes/asset_module_nav.php'; ?>

        <div class="cards mb-4">
            <div class="card">
                <h3>Currently Borrowed</h3>
                <div class="number"><?= (int) ($summary['active_total'] ?? 0) ?></div>
            </div>
            <div class="card overdue-card">
                <h3>Overdue</h3>
                <div class="number text-danger"><?= (int) ($summary['overdue_total'] ?? 0) ?></div>
            </div>
            <div class="card">
                <h3>Returned</h3>
                <div class="number"><?= (int) ($summary['returned_total'] ?? 0) ?></div>
            </div>
        </div>

        <form method="GET" class="row g-2 align-items-end mb-4">
            <div class="col-lg-6">
                <label class="form-label fw-bold">Search</label>
                <input type="text" name="search" class="form-control"
                       placeholder="Asset code, serial no., item, borrower, department..."
                       value="<?= h($search) ?>">
            </div>
            <div class="col-sm-6 col-lg-3">
                <label class="form-label fw-bold">Status</label>
                <select name="status" class="form-select">
                    <option value="Borrowed" <?= $status === 'Borrowed' ? 'selected' : '' ?>>Currently Borrowed</option>
                    <option value="Returned" <?= $status === 'Returned' ? 'selected' : '' ?>>Returned</option>
                    <option value="All" <?= $status === 'All' ? 'selected' : '' ?>>All Transactions</option>
                </select>
            </div>
            <div class="col-sm-6 col-lg-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-fill">Apply</button>
                <a href="asset_transactions.php" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>Asset</th>
                        <th>Asset Code</th>
                        <th>Borrower</th>
                        <th>Department</th>
                        <th>Purpose</th>
                        <th>Borrowed</th>
                        <th>Due Date</th>
                        <th>Status</th>
                        <th>Returned</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($transactions->num_rows > 0): ?>
                    <?php while ($row = $transactions->fetch_assoc()): ?>
                        <?php $is_overdue = (int) $row['is_overdue'] === 1; ?>
                        <tr>
                            <td>
                                <strong><?= h($row['item_name']) ?></strong>
                                <div class="small text-muted"><?= h($row['brand'] ?: '-') ?></div>
                            </td>
                            <td>
                                <strong><?= h($row['asset_code']) ?></strong>
                                <div class="small text-muted">Serial: <?= h($row['serial_number'] ?: '-') ?></div>
                            </td>
                            <td><?= h($row['display_name']) ?></td>
                            <td><?= h($row['display_department']) ?></td>
                            <td><?= h($row['purpose'] ?: '-') ?></td>
                            <td><?= h($row['borrow_date']) ?></td>
                            <td class="<?= $is_overdue ? 'text-danger fw-bold' : '' ?>">
                                <?= h($row['due_date']) ?>
                                <?php if ($is_overdue): ?>
                                    <div class="small"><?= max(1, (int) $row['days_overdue']) ?> day(s) overdue</div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($is_overdue): ?>
                                    <span class="badge bg-danger">Overdue</span>
                                <?php elseif ($row['status'] === 'Borrowed'): ?>
                                    <span class="badge bg-warning text-dark">Borrowed</span>
                                <?php else: ?>
                                    <span class="badge bg-success">Returned</span>
                                <?php endif; ?>
                            </td>
                            <td><?= h($row['return_date'] ?: '-') ?></td>
                            <td>
                                <div class="asset-row-actions">
                                    <?php if ($row['status'] === 'Borrowed'): ?>
                                        <a href="return_item.php?borrow_id=<?= (int) $row['id'] ?>" class="btn btn-success btn-sm">Return</a>
                                    <?php endif; ?>
                                    <a href="asset_history.php?unit_id=<?= (int) $row['asset_unit_id'] ?>" class="btn btn-info btn-sm">History</a>
                                    <a href="asset_unit_qr_view.php?unit_id=<?= (int) $row['asset_unit_id'] ?>" class="btn btn-dark btn-sm">QR</a>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="10" class="empty">No matching borrow or return transactions.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>
</body>
</html>
