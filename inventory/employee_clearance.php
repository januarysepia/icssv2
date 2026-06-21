<?php
include '../auth/auth_check.php';

require_role([
    'Boss',
    'Admin',
    'Purchasing'
]);

include '../config/database.php';

$clearance_id = intval($_GET['id'] ?? 0);

if ($clearance_id <= 0) {
    echo "<script>alert('Invalid clearance record.'); window.location='clearance_history.php';</script>";
    exit;
}

$clearance = $conn->query("
    SELECT
        ac.*,
        u.employee_no,
        u.fullname,
        u.position,
        u.system_role,
        u.status AS employee_status,
        d.department_name,
        cb.fullname AS cleared_by_name,
        cr.fullname AS created_by_name
    FROM asset_clearance ac
    LEFT JOIN users u
        ON ac.employee_id = u.id
    LEFT JOIN departments d
        ON u.department_id = d.id
    LEFT JOIN users cb
        ON ac.cleared_by = cb.id
    LEFT JOIN users cr
        ON ac.created_by = cr.id
    WHERE ac.id = '$clearance_id'
    LIMIT 1
")->fetch_assoc();

if (!$clearance) {
    echo "<script>alert('Clearance record not found.'); window.location='clearance_history.php';</script>";
    exit;
}

$items = $conn->query("
    SELECT
        aci.*,
        au.asset_code AS asset_tag,
        au.serial_number AS serial_no,
        ii.item_name,
        ii.brand,
        ii.model,
        ii.unit
    FROM asset_clearance_items aci
    LEFT JOIN asset_units au
        ON aci.asset_unit_id = au.id
    LEFT JOIN inventory_items ii
        ON aci.inventory_id = ii.id
    WHERE aci.clearance_id = '$clearance_id'
    ORDER BY aci.id ASC
");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Employee Asset Clearance</title>
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

        .card {
            background:#fff;
            padding:20px;
            border-radius:10px;
            box-shadow:0 2px 7px rgba(0,0,0,0.08);
            margin-bottom:20px;
        }

        .info-grid {
            display:grid;
            grid-template-columns:repeat(3, 1fr);
            gap:12px;
        }

        .info-item {
            border-bottom:1px solid #eee;
            padding-bottom:8px;
        }

        .info-item label {
            display:block;
            font-size:12px;
            color:#777;
            margin-bottom:3px;
        }

        .info-item strong {
            color:#2c3e50;
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

        .badge {
            padding:5px 9px;
            border-radius:20px;
            color:#fff;
            font-size:12px;
            display:inline-block;
        }

        .Cleared,
        .Returned {
            background:#27ae60;
        }

        .Pending,
        .PendingReturn {
            background:#e67e22;
        }

        .Damaged {
            background:#c0392b;
        }

        .Lost {
            background:#7f8c8d;
        }

        .Default {
            background:#34495e;
        }

        .remarks-box {
            white-space:pre-wrap;
            background:#f8f9fa;
            padding:12px;
            border-radius:6px;
            border-left:4px solid #2980b9;
        }

        @media print {
            .top-actions,
            .sidebar {
                display:none !important;
            }

            .main-content {
                margin:0 !important;
                width:100% !important;
            }

            .card {
                box-shadow:none;
                border:1px solid #ddd;
            }
        }

        @media (max-width:800px) {
            .info-grid {
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
            <h2>Employee Asset Clearance</h2>

            <div class="top-actions">
                <a href="asset_clearance.php">New Clearance</a>
                <a href="clearance_history.php">History</a>
                <a href="javascript:window.print()">Print</a>
                <a href="../dashboard/index.php">Dashboard</a>
            </div>
        </div>

        <div class="card">
            <h3>Clearance Information</h3>

            <div class="info-grid">

                <div class="info-item">
                    <label>Clearance ID</label>
                    <strong><?= htmlspecialchars($clearance['id']) ?></strong>
                </div>

                <div class="info-item">
                    <label>Clearance Date</label>
                    <strong><?= htmlspecialchars($clearance['clearance_date'] ?? '-') ?></strong>
                </div>

                <div class="info-item">
                    <label>Status</label>
                    <?php
                        $status = $clearance['status'] ?? 'Default';
                        $statusClass = str_replace(' ', '', $status);
                    ?>
                    <strong>
                        <span class="badge <?= htmlspecialchars($statusClass) ?>">
                            <?= htmlspecialchars($status) ?>
                        </span>
                    </strong>
                </div>

                <div class="info-item">
                    <label>Cleared By</label>
                    <strong><?= htmlspecialchars($clearance['cleared_by_name'] ?? '-') ?></strong>
                </div>

                <div class="info-item">
                    <label>Cleared At</label>
                    <strong><?= htmlspecialchars($clearance['cleared_at'] ?? '-') ?></strong>
                </div>

                <div class="info-item">
                    <label>Created By</label>
                    <strong><?= htmlspecialchars($clearance['created_by_name'] ?? '-') ?></strong>
                </div>

            </div>
        </div>

        <div class="card">
            <h3>Employee Information</h3>

            <div class="info-grid">

                <div class="info-item">
                    <label>Employee No.</label>
                    <strong><?= htmlspecialchars($clearance['employee_no'] ?? '-') ?></strong>
                </div>

                <div class="info-item">
                    <label>Full Name</label>
                    <strong><?= htmlspecialchars($clearance['fullname'] ?? '-') ?></strong>
                </div>

                <div class="info-item">
                    <label>Position</label>
                    <strong><?= htmlspecialchars($clearance['position'] ?? '-') ?></strong>
                </div>

                <div class="info-item">
                    <label>Department</label>
                    <strong><?= htmlspecialchars($clearance['department_name'] ?? '-') ?></strong>
                </div>

                <div class="info-item">
                    <label>System Role</label>
                    <strong><?= htmlspecialchars($clearance['system_role'] ?? '-') ?></strong>
                </div>

                <div class="info-item">
                    <label>Employee Status</label>
                    <strong><?= htmlspecialchars($clearance['employee_status'] ?? '-') ?></strong>
                </div>

            </div>
        </div>

        <div class="card">
            <h3>Cleared Assets / Pending Assets</h3>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Asset</th>
                            <th>Asset Tag</th>
                            <th>Serial No.</th>
                            <th>Brand / Model</th>
                            <th>Unit</th>
                            <th>Status</th>
                            <th>Condition After</th>
                            <th>Returned Date</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if ($items && $items->num_rows > 0): ?>
                            <?php $no = 1; ?>

                            <?php while ($row = $items->fetch_assoc()): ?>
                                <?php
                                    $itemStatus = $row['asset_status'] ?? 'Default';
                                    $itemClass = str_replace(' ', '', $itemStatus);
                                ?>

                                <tr>
                                    <td><?= $no++ ?></td>

                                    <td><?= htmlspecialchars($row['item_name'] ?? 'Unknown Asset') ?></td>

                                    <td><?= htmlspecialchars($row['asset_tag'] ?? '-') ?></td>

                                    <td><?= htmlspecialchars($row['serial_no'] ?? '-') ?></td>

                                    <td>
                                        <?= htmlspecialchars($row['brand'] ?? '-') ?>
                                        /
                                        <?= htmlspecialchars($row['model'] ?? '-') ?>
                                    </td>

                                    <td><?= htmlspecialchars($row['unit'] ?? '-') ?></td>

                                    <td>
                                        <span class="badge <?= htmlspecialchars($itemClass) ?>">
                                            <?= htmlspecialchars($itemStatus) ?>
                                        </span>
                                    </td>

                                    <td><?= htmlspecialchars($row['condition_after'] ?? '-') ?></td>

                                    <td><?= htmlspecialchars($row['returned_date'] ?? '-') ?></td>

                                    <td><?= nl2br(htmlspecialchars($row['remarks'] ?? '-')) ?></td>
                                </tr>
                            <?php endwhile; ?>

                        <?php else: ?>
                            <tr>
                                <td colspan="10" style="text-align:center; padding:25px; color:#777;">
                                    No clearance items found.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <h3>Overall Remarks</h3>

            <div class="remarks-box">
                <?= nl2br(htmlspecialchars($clearance['remarks'] ?? '-')) ?>
            </div>
        </div>

    </div>

</div>

</body>
</html>
