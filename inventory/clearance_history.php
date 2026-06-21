<?php
include '../auth/auth_check.php';

require_role([
    'Boss',
    'Admin',
    'Purchasing'
]);

include '../config/database.php';

$status_filter = $_GET['status'] ?? '';
$employee_search = trim($_GET['employee'] ?? '');

$where = "WHERE 1=1";

if ($status_filter !== '') {
    $safe_status = $conn->real_escape_string($status_filter);
    $where .= " AND ac.status = '$safe_status'";
}

if ($employee_search !== '') {
    $safe_employee = $conn->real_escape_string($employee_search);
    $where .= " AND (
        u.fullname LIKE '%$safe_employee%' 
        OR u.employee_no LIKE '%$safe_employee%'
    )";
}

$sql = "
SELECT
    ac.*,
    u.employee_no,
    u.fullname,
    u.position,
    d.department_name,
    cb.fullname AS cleared_by_name,
    cr.fullname AS created_by_name,
    COUNT(aci.id) AS total_items,
    SUM(CASE WHEN aci.asset_status = 'Pending Return' THEN 1 ELSE 0 END) AS pending_items
FROM asset_clearance ac
LEFT JOIN users u
    ON ac.employee_id = u.id
LEFT JOIN departments d
    ON u.department_id = d.id
LEFT JOIN users cb
    ON ac.cleared_by = cb.id
LEFT JOIN users cr
    ON ac.created_by = cr.id
LEFT JOIN asset_clearance_items aci
    ON ac.id = aci.clearance_id
$where
GROUP BY ac.id
ORDER BY ac.created_at DESC
";

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Clearance History</title>
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

        .filter-card,
        .table-card {
            background:#fff;
            padding:18px;
            border-radius:10px;
            box-shadow:0 2px 7px rgba(0,0,0,0.08);
            margin-bottom:20px;
        }

        .filter-grid {
            display:grid;
            grid-template-columns:2fr 1fr auto auto;
            gap:10px;
            align-items:end;
        }

        input,
        select {
            padding:9px;
            border:1px solid #ccc;
            border-radius:6px;
            width:100%;
            box-sizing:border-box;
        }

        button,
        .btn {
            display:inline-block;
            padding:9px 14px;
            border:none;
            border-radius:6px;
            background:#2980b9;
            color:#fff;
            text-decoration:none;
            cursor:pointer;
            font-size:14px;
        }

        .btn-dark {
            background:#2c3e50;
        }

        .table-wrap {
            overflow-x:auto;
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

        .Cleared { background:#27ae60; }
        .Pending { background:#e67e22; }
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

        @media (max-width:900px) {
            .filter-grid {
                grid-template-columns:1fr;
            }

            .page-header {
                flex-direction:column;
                align-items:flex-start;
                gap:10px;
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
            <h2>Asset Clearance History</h2>

            <div class="top-actions">
                <a href="asset_clearance.php">New Clearance</a>
                <a href="asset_dashboard.php">Asset Dashboard</a>
                <a href="../dashboard/index.php">Dashboard</a>
            </div>
        </div>

        <div class="filter-card">
            <form method="GET">
                <div class="filter-grid">

                    <div>
                        <label>Search Employee</label>
                        <input 
                            type="text" 
                            name="employee"
                            placeholder="Employee no. or full name"
                            value="<?= htmlspecialchars($employee_search) ?>"
                        >
                    </div>

                    <div>
                        <label>Status</label>
                        <select name="status">
                            <option value="">All Status</option>
                            <option value="Cleared" <?= ($status_filter == 'Cleared') ? 'selected' : '' ?>>
                                Cleared
                            </option>
                            <option value="Pending" <?= ($status_filter == 'Pending') ? 'selected' : '' ?>>
                                Pending
                            </option>
                        </select>
                    </div>

                    <div>
                        <button type="submit">Filter</button>
                    </div>

                    <div>
                        <a href="clearance_history.php" class="btn btn-dark">Reset</a>
                    </div>

                </div>
            </form>
        </div>

        <div class="table-card">
            <div class="table-wrap">

                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Employee</th>
                            <th>Department</th>
                            <th>Clearance Date</th>
                            <th>Status</th>
                            <th>Total Items</th>
                            <th>Pending Items</th>
                            <th>Cleared By</th>
                            <th>Created By</th>
                            <th>Created At</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php $no = 1; ?>

                            <?php while ($row = $result->fetch_assoc()): ?>
                                <?php
                                    $status = $row['status'] ?? 'Default';
                                    $statusClass = in_array($status, ['Cleared', 'Pending']) ? $status : 'Default';
                                ?>

                                <tr>
                                    <td><?= $no++ ?></td>

                                    <td>
                                        <?= htmlspecialchars($row['employee_no'] ?? '-') ?>
                                        <br>
                                        <strong><?= htmlspecialchars($row['fullname'] ?? '-') ?></strong>
                                        <br>
                                        <span class="small-text">
                                            <?= htmlspecialchars($row['position'] ?? '-') ?>
                                        </span>
                                    </td>

                                    <td><?= htmlspecialchars($row['department_name'] ?? '-') ?></td>

                                    <td><?= htmlspecialchars($row['clearance_date'] ?? '-') ?></td>

                                    <td>
                                        <span class="badge <?= $statusClass ?>">
                                            <?= htmlspecialchars($status) ?>
                                        </span>
                                    </td>

                                    <td><?= htmlspecialchars($row['total_items'] ?? 0) ?></td>

                                    <td><?= htmlspecialchars($row['pending_items'] ?? 0) ?></td>

                                    <td><?= htmlspecialchars($row['cleared_by_name'] ?? '-') ?></td>

                                    <td><?= htmlspecialchars($row['created_by_name'] ?? '-') ?></td>

                                    <td><?= htmlspecialchars($row['created_at'] ?? '-') ?></td>

                                    <td>
                                        <a 
                                            href="employee_clearance.php?id=<?= $row['id'] ?>" 
                                            class="btn"
                                        >
                                            View
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>

                        <?php else: ?>
                            <tr>
                                <td colspan="11" class="empty">
                                    No clearance history found.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

            </div>
        </div>

    </div>

</div>

</body>
</html>
