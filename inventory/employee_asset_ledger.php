<?php
include '../auth/auth_check.php';

require_role([
    'Boss',
    'Admin',
    'Purchasing'
]);

include '../config/database.php';

$employee_id = intval($_GET['employee_id'] ?? 0);

$employees = $conn->query("
    SELECT id, fullname, position
    FROM users
    WHERE status = 'Active'
    ORDER BY fullname ASC
");

$where = "";
if ($employee_id > 0) {
    $where = "WHERE aa.assigned_to = '$employee_id'";
}

$sql = "
SELECT
    aa.*,
    au.asset_code AS asset_tag,
    au.serial_number AS serial_no,
    ii.item_name,
    ii.brand,
    ii.model,
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
$where
ORDER BY aa.assigned_date DESC
";

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Employee Asset Ledger</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        .page-container { padding:25px; }

        .page-header {
            display:flex;
            justify-content:space-between;
            align-items:center;
            margin-bottom:25px;
        }

        .top-actions a {
            text-decoration:none;
            padding:9px 15px;
            border-radius:6px;
            background:#2c3e50;
            color:#fff;
            margin-left:8px;
            font-size:14px;
        }

        .filter-box, .table-card {
            background:#fff;
            padding:18px;
            border-radius:10px;
            box-shadow:0 2px 7px rgba(0,0,0,0.08);
            margin-bottom:20px;
        }

        .table-card {
            overflow-x:auto;
        }

        select, button {
            padding:9px;
            border-radius:6px;
            border:1px solid #ccc;
        }

        button {
            background:#2980b9;
            color:#fff;
            border:none;
            cursor:pointer;
        }

        table {
            width:100%;
            border-collapse:collapse;
            font-size:14px;
        }

        th {
            background:#34495e;
            color:#fff;
            padding:11px;
            text-align:left;
            white-space:nowrap;
        }

        td {
            padding:10px;
            border-bottom:1px solid #eee;
            vertical-align:top;
        }

        tr:hover { background:#f7f9fb; }

        .badge {
            padding:5px 9px;
            border-radius:20px;
            color:#fff;
            font-size:12px;
            display:inline-block;
        }

        .Active { background:#27ae60; }
        .Returned { background:#2980b9; }
        .Damaged { background:#c0392b; }
        .Lost { background:#e67e22; }
        .Default { background:#7f8c8d; }

        .empty {
            text-align:center;
            padding:25px;
            color:#777;
        }

        .small-text {
            color:#777;
            font-size:12px;
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
            <h2>Employee Asset Ledger</h2>

            <div class="top-actions">
                <a href="asset_list.php">Back</a>
                <a href="../dashboard/index.php">Dashboard</a>
            </div>
        </div>

        <div class="filter-box">
            <form method="GET">
                <select name="employee_id">
                    <option value="">All Employees</option>

                    <?php if ($employees && $employees->num_rows > 0): ?>
                        <?php while ($emp = $employees->fetch_assoc()): ?>
                            <option 
                                value="<?= $emp['id'] ?>"
                                <?= ($employee_id == $emp['id']) ? 'selected' : '' ?>
                            >
                                <?= htmlspecialchars($emp['fullname']) ?>
                                <?= !empty($emp['position']) ? ' - ' . htmlspecialchars($emp['position']) : '' ?>
                            </option>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </select>

                <button type="submit">Filter</button>

                <?php if ($employee_id > 0): ?>
                    <a href="employee_asset_ledger.php" style="margin-left:10px;">Clear Filter</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="table-card">

            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Employee</th>
                        <th>Department</th>
                        <th>Asset</th>
                        <th>Asset Tag</th>
                        <th>Serial No.</th>
                        <th>Brand / Model</th>
                        <th>Assigned Date</th>
                        <th>Return Date</th>
                        <th>Status</th>
                        <th>Condition Before</th>
                        <th>Condition After</th>
                        <th>Remarks</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php $no = 1; ?>

                        <?php while ($row = $result->fetch_assoc()): ?>

                            <?php
                                $status = $row['status'] ?? 'Default';
                                $badgeClass = in_array($status, [
                                    'Active',
                                    'Returned',
                                    'Damaged',
                                    'Lost'
                                ]) ? $status : 'Default';
                            ?>

                            <tr>
                                <td><?= $no++ ?></td>

                                <td>
                                    <?= htmlspecialchars($row['employee_name'] ?? '-') ?>
                                    <br>
                                    <span class="small-text">
                                        <?= htmlspecialchars($row['employee_position'] ?? '-') ?>
                                    </span>
                                </td>

                                <td>
                                    <?= htmlspecialchars($row['employee_department'] ?? '-') ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($row['item_name'] ?? 'Unknown Asset') ?>
                                    <br>
                                    <span class="small-text">
                                        Unit ID: <?= htmlspecialchars($row['asset_unit_id'] ?? '-') ?>
                                    </span>
                                </td>

                                <td><?= htmlspecialchars($row['asset_tag'] ?? '-') ?></td>

                                <td><?= htmlspecialchars($row['serial_no'] ?? '-') ?></td>

                                <td>
                                    <?= htmlspecialchars($row['brand'] ?? '-') ?>
                                    /
                                    <?= htmlspecialchars($row['model'] ?? '-') ?>
                                </td>

                                <td><?= htmlspecialchars($row['assigned_date'] ?? '-') ?></td>

                                <td><?= htmlspecialchars($row['return_date'] ?? '-') ?></td>

                                <td>
                                    <span class="badge <?= $badgeClass ?>">
                                        <?= htmlspecialchars($status) ?>
                                    </span>
                                </td>

                                <td><?= htmlspecialchars($row['condition_before'] ?? '-') ?></td>

                                <td><?= htmlspecialchars($row['condition_after'] ?? '-') ?></td>

                                <td><?= nl2br(htmlspecialchars($row['remarks'] ?? '-')) ?></td>
                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>
                        <tr>
                            <td colspan="13" class="empty">
                                No employee asset ledger found.
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
