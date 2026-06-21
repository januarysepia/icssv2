<?php
include '../auth/auth_check.php';

require_role([
    'Boss',
    'Admin',
    'Purchasing',
    'Technical',
    'Supervisor',
    'Engineer',
    'QA',
    'QAQC',
    'Logistics',
    'Production'
]);

include '../config/database.php';

$asset_id = intval($_GET['asset_id'] ?? 0);

if ($asset_id <= 0) {
    echo "<script>alert('Invalid asset.'); window.location='../dashboard/index.php';</script>";
    exit;
}

$unit = $conn->query("
    SELECT id FROM asset_units
    WHERE inventory_id = '$asset_id'
    ORDER BY id ASC
    LIMIT 1
")->fetch_assoc();

if ($unit) {
    header('Location: asset_unit_qr_view.php?unit_id=' . intval($unit['id']));
    exit();
}

$asset = $conn->query("
    SELECT *
    FROM inventory_items
    WHERE id = '$asset_id'
")->fetch_assoc();

if (!$asset) {
    echo "<script>alert('Asset not found.'); window.location='../dashboard/index.php';</script>";
    exit;
}

$current_assignment = $conn->query("
    SELECT
        aa.*,
        u.fullname AS employee_name,
        u.position AS employee_position,
        u.system_role AS employee_role,
        d.department_name AS employee_department
    FROM asset_assignments aa
    LEFT JOIN users u
        ON aa.assigned_to = u.id
    LEFT JOIN departments d
        ON u.department_id = d.id
    WHERE aa.inventory_id = '$asset_id'
      AND aa.status = 'Assigned'
    ORDER BY aa.id DESC
    LIMIT 1
")->fetch_assoc();

$history = $conn->query("
    SELECT 
        ah.*,
        u.fullname AS employee_name,
        c.fullname AS created_by_name
    FROM asset_history ah
    LEFT JOIN users u 
        ON ah.employee_id = u.id
    LEFT JOIN users c 
        ON ah.created_by = c.id
    WHERE ah.inventory_id = '$asset_id'
    ORDER BY ah.created_at DESC
");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Asset QR Details</title>
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

        .asset-card,
        .table-card {
            background:#fff;
            padding:20px;
            border-radius:10px;
            box-shadow:0 2px 7px rgba(0,0,0,0.08);
            margin-bottom:25px;
        }

        .asset-grid {
            display:grid;
            grid-template-columns:220px 1fr;
            gap:20px;
        }

        .qr-box {
            text-align:center;
            border:1px solid #ddd;
            border-radius:8px;
            padding:15px;
        }

        .qr-box img {
            width:180px;
            max-width:100%;
        }

        .details-grid {
            display:grid;
            grid-template-columns:repeat(2, 1fr);
            gap:12px;
        }

        .detail-item {
            border-bottom:1px solid #eee;
            padding-bottom:8px;
        }

        .detail-item label {
            display:block;
            font-size:12px;
            color:#777;
            margin-bottom:3px;
        }

        .detail-item strong {
            color:#2c3e50;
        }

        .status-badge {
            padding:6px 10px;
            border-radius:20px;
            color:#fff;
            font-size:12px;
            display:inline-block;
        }

        .available { background:#27ae60; }
        .assigned { background:#2980b9; }
        .unknown { background:#7f8c8d; }

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

        .empty {
            text-align:center;
            padding:22px;
            color:#777;
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

            .asset-card,
            .table-card {
                box-shadow:none;
                border:1px solid #ddd;
            }
        }

        @media (max-width:800px) {
            .asset-grid,
            .details-grid {
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
            <h2>Asset QR Details</h2>

            <div class="top-actions">
                <a href="asset_list.php">Asset List</a>
                <a href="asset_history.php?asset_id=<?= $asset_id ?>">History</a>
                <a href="generate_asset_qr.php?id=<?= $asset_id ?>">Regenerate QR</a>
                <a href="../dashboard/index.php">Dashboard</a>
                <a href="javascript:window.print()">Print</a>
            </div>
        </div>

        <div class="asset-card">
            <div class="asset-grid">

                <div class="qr-box">
                    <?php if (!empty($asset['qr_code']) && file_exists('../' . $asset['qr_code'])): ?>
                        <img src="../<?= htmlspecialchars($asset['qr_code']) ?>" alt="Asset QR">
                    <?php else: ?>
                        <p>No QR generated.</p>
                        <a href="generate_asset_qr.php?id=<?= $asset_id ?>">Generate QR</a>
                    <?php endif; ?>

                    <p>
                        <strong><?= htmlspecialchars($asset['item_code'] ?? 'NO-ASSET-TAG') ?></strong>
                    </p>
                </div>

                <div>
                    <h3><?= htmlspecialchars($asset['item_name'] ?? 'Unknown Asset') ?></h3>

                    <?php if ($current_assignment): ?>
                        <span class="status-badge assigned">Assigned</span>
                    <?php else: ?>
                        <span class="status-badge available">Available</span>
                    <?php endif; ?>

                    <br><br>

                    <div class="details-grid">

                        <div class="detail-item">
                            <label>Asset Tag</label>
                            <strong><?= htmlspecialchars($asset['item_code'] ?? '-') ?></strong>
                        </div>

                        <div class="detail-item">
                            <label>Inventory ID</label>
                            <strong><?= htmlspecialchars($asset['id'] ?? '-') ?></strong>
                        </div>

                        <div class="detail-item">
                            <label>Serial No.</label>
                            <strong><?= htmlspecialchars($asset['serial_number'] ?? '-') ?></strong>
                        </div>

                        <div class="detail-item">
                            <label>Brand</label>
                            <strong><?= htmlspecialchars($asset['brand'] ?? '-') ?></strong>
                        </div>

                        <div class="detail-item">
                            <label>Model</label>
                            <strong><?= htmlspecialchars($asset['model'] ?? '-') ?></strong>
                        </div>

                        <div class="detail-item">
                            <label>Unit</label>
                            <strong><?= htmlspecialchars($asset['unit'] ?? '-') ?></strong>
                        </div>

                        <div class="detail-item">
                            <label>Current Condition</label>
                            <strong><?= htmlspecialchars($asset['item_condition'] ?? '-') ?></strong>
                        </div>

                        <div class="detail-item">
                            <label>Category</label>
                            <strong><?= htmlspecialchars($asset['category'] ?? '-') ?></strong>
                        </div>

                    </div>
                </div>

            </div>
        </div>

        <div class="asset-card">
            <h3>Current Assignment</h3>

            <?php if ($current_assignment): ?>
                <div class="details-grid">

                    <div class="detail-item">
                        <label>Assigned To</label>
                        <strong><?= htmlspecialchars($current_assignment['employee_name'] ?? '-') ?></strong>
                    </div>

                    <div class="detail-item">
                        <label>Position</label>
                        <strong><?= htmlspecialchars($current_assignment['employee_position'] ?? '-') ?></strong>
                    </div>

                    <div class="detail-item">
                        <label>System Role</label>
                        <strong><?= htmlspecialchars($current_assignment['employee_role'] ?? '-') ?></strong>
                    </div>

                    <div class="detail-item">
                        <label>Department</label>
                        <strong><?= htmlspecialchars($current_assignment['employee_department'] ?? '-') ?></strong>
                    </div>

                    <div class="detail-item">
                        <label>Assigned Date</label>
                        <strong><?= htmlspecialchars($current_assignment['assigned_date'] ?? '-') ?></strong>
                    </div>

                    <div class="detail-item">
                        <label>Condition Before</label>
                        <strong><?= htmlspecialchars($current_assignment['condition_before'] ?? '-') ?></strong>
                    </div>

                    <div class="detail-item">
                        <label>Status</label>
                        <strong><?= htmlspecialchars($current_assignment['status'] ?? '-') ?></strong>
                    </div>

                    <div class="detail-item">
                        <label>Remarks</label>
                        <strong><?= htmlspecialchars($current_assignment['remarks'] ?? '-') ?></strong>
                    </div>

                </div>
            <?php else: ?>
                <p>No active assignment. This asset is currently available.</p>
            <?php endif; ?>
        </div>

        <div class="table-card">
            <h3>Asset History</h3>

            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Action</th>
                        <th>Employee</th>
                        <th>Date</th>
                        <th>Condition</th>
                        <th>Remarks</th>
                        <th>Encoded By</th>
                        <th>Encoded At</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if ($history && $history->num_rows > 0): ?>
                        <?php $no = 1; ?>

                        <?php while ($h = $history->fetch_assoc()): ?>
                            <tr>
                                <td><?= $no++ ?></td>

                                <td><?= htmlspecialchars($h['action_type'] ?? '-') ?></td>

                                <td><?= htmlspecialchars($h['employee_name'] ?? '-') ?></td>

                                <td><?= htmlspecialchars($h['action_date'] ?? '-') ?></td>

                                <td><?= htmlspecialchars($h['condition_status'] ?? '-') ?></td>

                                <td><?= nl2br(htmlspecialchars($h['remarks'] ?? '-')) ?></td>

                                <td><?= htmlspecialchars($h['created_by_name'] ?? '-') ?></td>

                                <td><?= htmlspecialchars($h['created_at'] ?? '-') ?></td>
                            </tr>
                        <?php endwhile; ?>

                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="empty">
                                No history found for this asset.
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
