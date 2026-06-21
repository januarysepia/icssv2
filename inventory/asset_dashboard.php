<?php
include '../auth/auth_check.php';

require_role([
    'Boss',
    'Admin',
    'Purchasing'
]);

include '../config/database.php';
require_once '../includes/overdue_asset_notifications.php';

createOverdueAssetNotifications($conn);

$total_assets = $conn->query("
    SELECT COUNT(*) AS total 
    FROM asset_units
")->fetch_assoc()['total'] ?? 0;

$assigned_assets = $conn->query("
    SELECT COUNT(*) AS total 
    FROM asset_units
    WHERE unit_status = 'Assigned'
")->fetch_assoc()['total'] ?? 0;

$returned_assets = $conn->query("
    SELECT COUNT(*) AS total 
    FROM asset_assignments 
    WHERE status = 'Returned'
")->fetch_assoc()['total'] ?? 0;

$damaged_assets = $conn->query("
    SELECT COUNT(*) AS total 
    FROM asset_units
    WHERE unit_status IN ('Damaged', 'Under Repair', 'Lost')
")->fetch_assoc()['total'] ?? 0;

$available_assets = $conn->query("
    SELECT COUNT(*) AS total
    FROM asset_units
    WHERE unit_status = 'Available'
")->fetch_assoc()['total'] ?? 0;

$overdue_assets = $conn->query("
    SELECT COUNT(*) AS total
    FROM borrow_transactions
    WHERE status = 'Borrowed'
      AND due_date < NOW()
")->fetch_assoc()['total'] ?? 0;

$overdue_borrows = $conn->query("
    SELECT
        bt.id AS borrow_id,
        bt.due_date,
        TIMESTAMPDIFF(DAY, bt.due_date, NOW()) AS days_overdue,
        au.asset_code,
        au.serial_number,
        ii.item_name,
        COALESCE(u.fullname, bt.borrower_name, 'Unknown borrower') AS borrower_name,
        COALESCE(d.department_name, bt.borrower_department, '-') AS borrower_department
    FROM borrow_transactions bt
    INNER JOIN asset_units au ON au.id = bt.asset_unit_id
    INNER JOIN inventory_items ii ON ii.id = bt.item_id
    LEFT JOIN users u ON u.id = bt.employee_id
    LEFT JOIN departments d ON d.id = u.department_id
    WHERE bt.status = 'Borrowed'
      AND bt.due_date < NOW()
    ORDER BY bt.due_date ASC
");

$recent_history = $conn->query("
    SELECT 
        ah.*,
        au.asset_code,
        au.serial_number,
        ii.item_name,
        u.fullname AS employee_name
    FROM asset_history ah
    LEFT JOIN asset_units au
        ON ah.asset_unit_id = au.id
    LEFT JOIN inventory_items ii 
        ON ah.inventory_id = ii.id
    LEFT JOIN users u 
        ON ah.employee_id = u.id
    ORDER BY ah.created_at DESC
    LIMIT 10
");

$active_assignments = $conn->query("
    SELECT 
        aa.*,
        au.asset_code,
        au.serial_number AS serial_no,
        ii.item_name,
        u.fullname AS employee_name,
        u.position AS employee_position,
        d.department_name AS employee_department
    FROM asset_assignments aa
    LEFT JOIN asset_units au
        ON aa.asset_unit_id = au.id
    LEFT JOIN inventory_items ii 
        ON aa.inventory_id = ii.id
    LEFT JOIN users u 
        ON aa.assigned_to = u.id
    LEFT JOIN departments d
        ON u.department_id = d.id
    WHERE aa.status = 'Assigned'
    ORDER BY aa.assigned_date DESC
    LIMIT 10
");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Asset Dashboard</title>
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

        .top-actions a {
            text-decoration: none;
            padding: 9px 15px;
            border-radius: 6px;
            background: #2c3e50;
            color: #fff;
            margin-left: 8px;
            font-size: 14px;
        }

        .cards {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 15px;
            margin-bottom: 25px;
        }

        .card {
            background: #fff;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 7px rgba(0,0,0,0.08);
        }

        .card h3 {
            margin: 0;
            font-size: 15px;
            color: #555;
        }

        .card .number {
            font-size: 32px;
            font-weight: bold;
            margin-top: 10px;
            color: #2c3e50;
        }

        .card.overdue-card {
            border-left: 5px solid #dc3545;
        }

        .card.overdue-card .number {
            color: #dc3545;
        }

        .quick-links {
            background: #fff;
            padding: 18px;
            border-radius: 10px;
            box-shadow: 0 2px 7px rgba(0,0,0,0.08);
            margin-bottom: 25px;
        }

        .quick-links a {
            display: inline-block;
            text-decoration: none;
            background: #2980b9;
            color: #fff;
            padding: 10px 15px;
            border-radius: 6px;
            margin: 5px;
            font-size: 14px;
        }

        .table-card {
            background: #fff;
            padding: 18px;
            border-radius: 10px;
            box-shadow: 0 2px 7px rgba(0,0,0,0.08);
            margin-bottom: 25px;
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

        .Assigned, .Active {
            background: #27ae60;
        }

        .Returned {
            background: #2980b9;
        }

        .Damaged {
            background: #c0392b;
        }

        .Transferred {
            background: #8e44ad;
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

        .small-text {
            color: #777;
            font-size: 12px;
        }

        @media (max-width: 1100px) {
            .cards {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 600px) {
            .cards {
                grid-template-columns: 1fr;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
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
            <h2>Asset Dashboard</h2>

            <div class="top-actions">
                <a href="../dashboard/index.php">Dashboard</a>
            </div>
        </div>

        <?php include '../includes/asset_module_nav.php'; ?>

        <div class="cards">

            <div class="card">
                <h3>Total Assets</h3>
                <div class="number"><?= $total_assets ?></div>
            </div>

            <div class="card">
                <h3>Available Assets</h3>
                <div class="number"><?= $available_assets ?></div>
            </div>

            <div class="card">
                <h3>Assigned Assets</h3>
                <div class="number"><?= $assigned_assets ?></div>
            </div>

            <div class="card">
                <h3>Returned Assets</h3>
                <div class="number"><?= $returned_assets ?></div>
            </div>

            <div class="card">
                <h3>Damaged Assets</h3>
                <div class="number"><?= $damaged_assets ?></div>
            </div>

            <div class="card overdue-card">
                <h3>Overdue Borrowed</h3>
                <div class="number"><?= $overdue_assets ?></div>
            </div>

        </div>

        <div class="table-card">
            <h3>Overdue Asset Returns</h3>

            <table>
                <thead>
                    <tr>
                        <th>Asset</th>
                        <th>Asset Code</th>
                        <th>Serial No.</th>
                        <th>Borrower</th>
                        <th>Department</th>
                        <th>Due Date</th>
                        <th>Overdue</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($overdue_borrows && $overdue_borrows->num_rows > 0): ?>
                    <?php while ($row = $overdue_borrows->fetch_assoc()): ?>
                        <tr>
                            <td><?= h($row['item_name']) ?></td>
                            <td><strong><?= h($row['asset_code']) ?></strong></td>
                            <td><?= h($row['serial_number'] ?: '-') ?></td>
                            <td><?= h($row['borrower_name']) ?></td>
                            <td><?= h($row['borrower_department']) ?></td>
                            <td class="text-danger fw-bold"><?= h($row['due_date']) ?></td>
                            <td><span class="badge bg-danger"><?= max(1, (int) $row['days_overdue']) ?> day(s)</span></td>
                            <td>
                                <a href="return_item.php?borrow_id=<?= (int) $row['borrow_id'] ?>" class="btn btn-success btn-sm">Return</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="8" class="empty">No overdue asset returns.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="table-card">
            <h3>Currently Assigned Assets</h3>

            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Asset</th>
                        <th>Asset Tag</th>
                        <th>Serial No.</th>
                        <th>Employee</th>
                        <th>Department</th>
                        <th>Assigned Date</th>
                        <th>Status</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if ($active_assignments && $active_assignments->num_rows > 0): ?>
                        <?php $no = 1; ?>

                        <?php while ($row = $active_assignments->fetch_assoc()): ?>
                            <tr>
                                <td><?= $no++ ?></td>

                                <td><?= htmlspecialchars($row['item_name'] ?? 'Unknown Asset') ?></td>

                                <td><?= htmlspecialchars($row['asset_code'] ?? '-') ?></td>

                                <td><?= htmlspecialchars($row['serial_no'] ?? '-') ?></td>

                                <td>
                                    <?= htmlspecialchars($row['employee_name'] ?? '-') ?>
                                    <br>
                                    <span class="small-text">
                                        <?= htmlspecialchars($row['employee_position'] ?? '-') ?>
                                    </span>
                                </td>

                                <td><?= htmlspecialchars($row['employee_department'] ?? '-') ?></td>

                                <td><?= htmlspecialchars($row['assigned_date'] ?? '-') ?></td>

                                <td>
                                    <span class="badge Active">
                                        <?= htmlspecialchars($row['status'] ?? 'Active') ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endwhile; ?>

                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="empty">
                                No active asset assignments.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="table-card">
            <h3>Recent Asset History</h3>

            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Asset</th>
                        <th>Asset Tag</th>
                        <th>Action</th>
                        <th>Employee</th>
                        <th>Date</th>
                        <th>Condition</th>
                        <th>Remarks</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if ($recent_history && $recent_history->num_rows > 0): ?>
                        <?php $no = 1; ?>

                        <?php while ($row = $recent_history->fetch_assoc()): ?>

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

                                <td><?= htmlspecialchars($row['item_name'] ?? 'Unknown Asset') ?></td>

                                <td><?= htmlspecialchars($row['asset_code'] ?? '-') ?></td>

                                <td>
                                    <span class="badge <?= $badgeClass ?>">
                                        <?= htmlspecialchars($action) ?>
                                    </span>
                                </td>

                                <td><?= htmlspecialchars($row['employee_name'] ?? '-') ?></td>

                                <td><?= htmlspecialchars($row['action_date'] ?? '-') ?></td>

                                <td><?= htmlspecialchars($row['condition_status'] ?? '-') ?></td>

                                <td><?= nl2br(htmlspecialchars($row['remarks'] ?? '-')) ?></td>
                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="empty">
                                No recent asset history.
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
