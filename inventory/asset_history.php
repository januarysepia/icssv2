<?php
include '../auth/auth_check.php';

require_role([
    'Boss',
    'Admin',
    'Purchasing'
]);

include '../config/database.php';

$unit_id = intval($_GET['unit_id'] ?? 0);

$where = "";
if ($unit_id > 0) {
    $where = "WHERE ah.asset_unit_id = '$unit_id'";
}

$sql = "
SELECT 
    ah.*,
    au.asset_code AS asset_tag,
    au.serial_number,
    ii.item_name,
    COALESCE(u.fullname, ah.borrower_name_snapshot, bt.borrower_name) AS employee_name,
    COALESCE(ah.borrower_department_snapshot, bt.borrower_department, d.department_name) AS employee_department,
    COALESCE(ah.purpose_snapshot, bt.purpose) AS borrow_purpose,
    creator.fullname AS created_by_name
FROM asset_history ah
LEFT JOIN asset_units au
    ON ah.asset_unit_id = au.id
LEFT JOIN inventory_items ii 
    ON ah.inventory_id = ii.id
LEFT JOIN users u 
    ON ah.employee_id = u.id
LEFT JOIN departments d
    ON u.department_id = d.id
LEFT JOIN borrow_transactions bt
    ON ah.borrow_transaction_id = bt.id
LEFT JOIN users creator 
    ON ah.created_by = creator.id
$where
ORDER BY ah.created_at DESC
";

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Asset History</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        .page-container {
            padding: 25px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .page-header h2 {
            margin: 0;
            color: #333;
        }

        .top-actions a {
            text-decoration: none;
            padding: 9px 15px;
            border-radius: 6px;
            background: #2c3e50;
            color: #fff;
            margin-left: 8px;
            font-size: 14px;
        }

        .filter-box {
            background: #fff;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 7px rgba(0,0,0,0.08);
        }

        .filter-box input {
            padding: 9px;
            width: 250px;
            border: 1px solid #ccc;
            border-radius: 6px;
        }

        .filter-box button {
            padding: 9px 15px;
            border: none;
            background: #2980b9;
            color: #fff;
            border-radius: 6px;
            cursor: pointer;
        }

        .table-card {
            background: #fff;
            padding: 18px;
            border-radius: 10px;
            box-shadow: 0 2px 7px rgba(0,0,0,0.08);
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        th {
            background: #34495e;
            color: #fff;
            padding: 11px;
            text-align: left;
            white-space: nowrap;
        }

        td {
            padding: 10px;
            border-bottom: 1px solid #eee;
            vertical-align: top;
        }

        tr:hover {
            background: #f7f9fb;
        }

        .badge {
            padding: 5px 9px;
            border-radius: 20px;
            color: #fff;
            font-size: 12px;
            display: inline-block;
        }

        .Assigned {
            background: #27ae60;
        }

        .Returned {
            background: #2980b9;
        }

        .Transferred {
            background: #8e44ad;
        }

        .Damaged {
            background: #c0392b;
        }

        .Cleared {
            background: #16a085;
        }

        .Default {
            background: #7f8c8d;
        }

        .empty {
            text-align: center;
            padding: 25px;
            color: #777;
        }
    </style>
    <?php include '../includes/asset_ui.php'; ?>
</head>

<body class="asset-module">

<?php include '../dashboard/sidebar.php'; ?>

<div class="content-wrapper">
    <?php include '../dashboard/header.php'; ?>

    <div class="page-container asset-page">

        <div class="page-header">
            <h2>Asset History</h2>

            <div class="top-actions">
                <a href="../dashboard/index.php">Dashboard</a>
            </div>
        </div>

        <?php include '../includes/asset_module_nav.php'; ?>

        <div class="quick-links">
            <h3>Related Asset Records</h3>
            <a href="employee_asset_ledger.php">Employee Asset Ledger</a>
            <a href="asset_clearance.php">Asset Clearance</a>
            <a href="clearance_history.php">Clearance History</a>
        </div>

        <div class="filter-box">
            <form method="GET">
                <input 
                    type="number" 
                    name="unit_id"
                    placeholder="Search by Asset / Inventory ID"
                    value="<?= htmlspecialchars($_GET['unit_id'] ?? '') ?>"
                >

                <button type="submit">Search</button>

                <?php if ($unit_id > 0): ?>
                    <a href="asset_history.php" style="margin-left:10px;">Clear Filter</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="table-card">

            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Asset</th>
                        <th>Asset Tag</th>
                        <th>Action</th>
                        <th>Employee</th>
                        <th>Department</th>
                        <th>Purpose</th>
                        <th>Date</th>
                        <th>Condition</th>
                        <th>Remarks</th>
                        <th>Encoded By</th>
                        <th>Encoded At</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php $no = 1; ?>
                        <?php while ($row = $result->fetch_assoc()): ?>

                            <?php
                                $action = $row['action_type'] ?? 'Default';
                                $badgeClass = in_array($action, [
                                    'Assigned',
                                    'Returned',
                                    'Transferred',
                                    'Damaged',
                                    'Cleared'
                                ]) ? $action : 'Default';
                            ?>

                            <tr>
                                <td><?= $no++ ?></td>

                                <td>
                                    <?= htmlspecialchars($row['item_name'] ?? 'Unknown Asset') ?>
                                    <br>
                                    <small>Unit ID: <?= htmlspecialchars($row['asset_unit_id'] ?? '-') ?></small>
                                </td>

                                <td>
                                    <?= htmlspecialchars($row['asset_tag'] ?? '-') ?>
                                </td>

                                <td>
                                    <span class="badge <?= $badgeClass ?>">
                                        <?= htmlspecialchars($action) ?>
                                    </span>
                                </td>

                                <td>
                                    <?= htmlspecialchars($row['employee_name'] ?? '-') ?>
                                </td>

                                <td><?= htmlspecialchars($row['employee_department'] ?? '-') ?></td>

                                <td><?= nl2br(htmlspecialchars($row['borrow_purpose'] ?? '-')) ?></td>

                                <td>
                                    <?= htmlspecialchars($row['action_date'] ?? '-') ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($row['condition_status'] ?? '-') ?>
                                </td>

                                <td>
                                    <?= nl2br(htmlspecialchars($row['remarks'] ?? '-')) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($row['created_by_name'] ?? '-') ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($row['created_at'] ?? '-') ?>
                                </td>
                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="12" class="empty">
                                No asset history found.
                            </td>
                        </tr>

                    <?php endif; ?>
                </tbody>
            </table>

        </div>

    </div>

</div>

</body>
</html>
