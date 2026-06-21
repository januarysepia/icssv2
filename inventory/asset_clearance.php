<?php
include '../auth/auth_check.php';

require_role([
    'Boss',
    'Admin',
    'Purchasing'
]);

include '../config/database.php';

$employees = $conn->query("
    SELECT 
        id,
        employee_no,
        fullname,
        position,
        system_role
    FROM users
    WHERE status = 'Active'
    ORDER BY fullname ASC
");

$selected_employee_id = intval($_GET['employee_id'] ?? 0);

$employee = null;
$active_assets = null;

if ($selected_employee_id > 0) {

    $employee = $conn->query("
        SELECT 
            u.*,
            d.department_name
        FROM users u
        LEFT JOIN departments d
            ON u.department_id = d.id
        WHERE u.id = '$selected_employee_id'
        LIMIT 1
    ")->fetch_assoc();

    $active_assets = $conn->query("
        SELECT
            aa.id AS assignment_id,
            aa.inventory_id,
            aa.asset_unit_id,
            aa.assigned_date,
            aa.condition_before,
            aa.remarks AS assignment_remarks,
            ii.item_name,
            au.asset_code AS asset_tag,
            au.serial_number AS serial_no,
            ii.brand,
            ii.model
        FROM asset_assignments aa
        LEFT JOIN asset_units au
            ON aa.asset_unit_id = au.id
        LEFT JOIN inventory_items ii
            ON aa.inventory_id = ii.id
        WHERE aa.assigned_to = '$selected_employee_id'
          AND aa.status = 'Assigned'
        ORDER BY aa.assigned_date DESC
    ");
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Asset Clearance</title>
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

        select,
        input,
        textarea {
            padding:9px;
            border:1px solid #ccc;
            border-radius:6px;
            width:100%;
            box-sizing:border-box;
        }

        button {
            padding:10px 16px;
            border:none;
            border-radius:6px;
            background:#2980b9;
            color:#fff;
            cursor:pointer;
        }

        .employee-grid {
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
            background:#e67e22;
        }

        .empty {
            text-align:center;
            padding:25px;
            color:#777;
        }

        .submit-area {
            text-align:right;
            margin-top:20px;
        }

        .note {
            background:#f8f9fa;
            padding:12px;
            border-left:4px solid #2980b9;
            margin-bottom:15px;
            font-size:14px;
            color:#555;
        }

        @media (max-width:800px) {
            .employee-grid {
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
            <h2>Asset Clearance</h2>

            <div class="top-actions">
                <a href="asset_dashboard.php">Asset Dashboard</a>
                <a href="clearance_history.php">Clearance History</a>
                <a href="../dashboard/index.php">Dashboard</a>
            </div>
        </div>

        <div class="card">
            <form method="GET">
                <label>Select Employee for Clearance</label>
                <br><br>

                <select name="employee_id" required>
                    <option value="">-- Select Employee --</option>

                    <?php if ($employees && $employees->num_rows > 0): ?>
                        <?php while ($emp = $employees->fetch_assoc()): ?>
                            <option 
                                value="<?= $emp['id'] ?>"
                                <?= ($selected_employee_id == $emp['id']) ? 'selected' : '' ?>
                            >
                                <?= htmlspecialchars($emp['employee_no']) ?>
                                -
                                <?= htmlspecialchars($emp['fullname']) ?>
                                |
                                <?= htmlspecialchars($emp['position']) ?>
                            </option>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </select>

                <br><br>

                <button type="submit">Check Assets</button>
            </form>
        </div>

        <?php if ($selected_employee_id > 0 && $employee): ?>

            <div class="card">
                <h3>Employee Information</h3>

                <div class="employee-grid">

                    <div class="info-item">
                        <label>Employee No.</label>
                        <strong><?= htmlspecialchars($employee['employee_no'] ?? '-') ?></strong>
                    </div>

                    <div class="info-item">
                        <label>Full Name</label>
                        <strong><?= htmlspecialchars($employee['fullname'] ?? '-') ?></strong>
                    </div>

                    <div class="info-item">
                        <label>Position</label>
                        <strong><?= htmlspecialchars($employee['position'] ?? '-') ?></strong>
                    </div>

                    <div class="info-item">
                        <label>System Role</label>
                        <strong><?= htmlspecialchars($employee['system_role'] ?? '-') ?></strong>
                    </div>

                    <div class="info-item">
                        <label>Department</label>
                        <strong><?= htmlspecialchars($employee['department_name'] ?? '-') ?></strong>
                    </div>

                    <div class="info-item">
                        <label>Status</label>
                        <strong><?= htmlspecialchars($employee['status'] ?? '-') ?></strong>
                    </div>

                </div>
            </div>

            <div class="card">
                <h3>Active Assets for Clearance</h3>

                <div class="note">
                    Kapag may active assets pa ang employee, piliin kung Returned, Damaged, or Lost.
                    Kapag wala nang active assets, puwedeng i-save as Cleared.
                </div>

                <form method="POST" action="save_clearance.php">
                    <?= csrf_field() ?>

                    <input type="hidden" name="employee_id" value="<?= $selected_employee_id ?>">
                    <input type="hidden" name="clearance_date" value="<?= date('Y-m-d') ?>">

                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Asset</th>
                                    <th>Asset Tag</th>
                                    <th>Serial No.</th>
                                    <th>Brand / Model</th>
                                    <th>Assigned Date</th>
                                    <th>Condition Before</th>
                                    <th>Clearance Status</th>
                                    <th>Condition After</th>
                                    <th>Remarks</th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php if ($active_assets && $active_assets->num_rows > 0): ?>
                                    <?php $no = 1; ?>

                                    <?php while ($asset = $active_assets->fetch_assoc()): ?>
                                        <tr>
                                            <td><?= $no++ ?></td>

                                            <td>
                                                <?= htmlspecialchars($asset['item_name'] ?? 'Unknown Asset') ?>

                                                <input 
                                                    type="hidden" 
                                                    name="assignment_id[]" 
                                                    value="<?= htmlspecialchars($asset['assignment_id']) ?>"
                                                >

                                                <input 
                                                    type="hidden" 
                                                    name="inventory_id[]" 
                                                    value="<?= htmlspecialchars($asset['inventory_id']) ?>"
                                                >

                                                <input type="hidden"
                                                       name="asset_unit_id[]"
                                                       value="<?= htmlspecialchars($asset['asset_unit_id']) ?>">
                                            </td>

                                            <td><?= htmlspecialchars($asset['asset_tag'] ?? '-') ?></td>

                                            <td><?= htmlspecialchars($asset['serial_no'] ?? '-') ?></td>

                                            <td>
                                                <?= htmlspecialchars($asset['brand'] ?? '-') ?>
                                                /
                                                <?= htmlspecialchars($asset['model'] ?? '-') ?>
                                            </td>

                                            <td><?= htmlspecialchars($asset['assigned_date'] ?? '-') ?></td>

                                            <td><?= htmlspecialchars($asset['condition_before'] ?? '-') ?></td>

                                            <td>
                                                <select name="asset_status[]" required>
                                                    <option value="Returned">Returned</option>
                                                    <option value="Damaged">Damaged</option>
                                                    <option value="Lost">Lost</option>
                                                    <option value="Pending Return">Pending Return</option>
                                                </select>
                                            </td>

                                            <td>
                                                <input 
                                                    type="text" 
                                                    name="condition_after[]" 
                                                    placeholder="Good / Damaged / Missing Parts"
                                                >
                                            </td>

                                            <td>
                                                <textarea 
                                                    name="item_remarks[]" 
                                                    rows="2"
                                                    placeholder="Item remarks"
                                                ></textarea>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>

                                <?php else: ?>
                                    <tr>
                                        <td colspan="10" class="empty">
                                            No active assets found. Employee can be cleared.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <br>

                    <label>Overall Clearance Remarks</label>
                    <br><br>

                    <textarea 
                        name="remarks" 
                        rows="3" 
                        placeholder="Example: Employee returned all issued company assets."
                    ></textarea>

                    <div class="submit-area">
                        <button type="submit">
                            Save Clearance
                        </button>
                    </div>

                </form>
            </div>

        <?php elseif ($selected_employee_id > 0): ?>

            <div class="card">
                <p class="empty">Employee not found.</p>
            </div>

        <?php endif; ?>

    </div>

</div>

</body>
</html>
