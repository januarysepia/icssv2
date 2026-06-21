<?php
include '../auth/auth_check.php';

require_role([
    'Boss',
    'Admin',
    'Purchasing'
]);

include '../config/database.php';

require_post();
verify_csrf();

$employee_id = intval($_POST['employee_id'] ?? 0);
$clearance_date = $_POST['clearance_date'] ?? date('Y-m-d');
$remarks = trim($_POST['remarks'] ?? '');
$created_by = $_SESSION['user_id'] ?? null;

$assignment_ids = $_POST['assignment_id'] ?? [];
$inventory_ids = $_POST['inventory_id'] ?? [];
$asset_unit_ids = $_POST['asset_unit_id'] ?? [];
$asset_statuses = $_POST['asset_status'] ?? [];
$condition_afters = $_POST['condition_after'] ?? [];
$item_remarks = $_POST['item_remarks'] ?? [];

if ($employee_id <= 0) {
    echo "
    <script>
        alert('Invalid employee.');
        window.location='asset_clearance.php';
    </script>";
    exit;
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $clearance_date)) {
    exit('Invalid clearance date.');
}

$employeeCheck = $conn->prepare("SELECT id FROM users WHERE id = ?");
$employeeCheck->bind_param('i', $employee_id);
$employeeCheck->execute();

if ($employeeCheck->get_result()->num_rows !== 1) {
    exit('Employee not found.');
}

$conn->begin_transaction();

try {

    $has_pending = false;

    foreach ($asset_statuses as $status) {
        if ($status === 'Pending Return') {
            $has_pending = true;
            break;
        }
    }

    $overall_status = $has_pending ? 'Pending' : 'Cleared';
    $cleared_by = $overall_status === 'Cleared' ? $created_by : null;
    $cleared_at = $overall_status === 'Cleared' ? date('Y-m-d H:i:s') : null;

    $stmt = $conn->prepare("
        INSERT INTO asset_clearance
        (
            employee_id,
            clearance_date,
            status,
            remarks,
            cleared_by,
            cleared_at,
            created_by
        )
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->bind_param(
        "isssisi",
        $employee_id,
        $clearance_date,
        $overall_status,
        $remarks,
        $cleared_by,
        $cleared_at,
        $created_by
    );

    $stmt->execute();
    $clearance_id = $stmt->insert_id;

    for ($i = 0; $i < count($inventory_ids); $i++) {

        $assignment_id = intval($assignment_ids[$i] ?? 0);
        $inventory_id = intval($inventory_ids[$i] ?? 0);
        $asset_unit_id = intval($asset_unit_ids[$i] ?? 0);
        $asset_status = $asset_statuses[$i] ?? 'Pending Return';
        $condition_after = trim($condition_afters[$i] ?? '');
        $item_remark = trim($item_remarks[$i] ?? '');

        if ($inventory_id <= 0 || $asset_unit_id <= 0) {
            continue;
        }

        if (!in_array($asset_status, ['Returned', 'Damaged', 'Lost', 'Pending Return'], true)) {
            throw new Exception('Invalid clearance asset status.');
        }

        if ($asset_status === 'Returned' && $condition_after === '') {
            $condition_after = 'Good';
        } elseif ($asset_status === 'Damaged' && $condition_after === '') {
            $condition_after = 'Damaged';
        } elseif ($asset_status === 'Lost') {
            $condition_after = 'Lost';
        }

        $activeAssignment = $conn->prepare("
            SELECT id
            FROM asset_assignments
            WHERE id = ? AND inventory_id = ? AND asset_unit_id = ? AND assigned_to = ? AND status = 'Assigned'
        ");
        $activeAssignment->bind_param('iiii', $assignment_id, $inventory_id, $asset_unit_id, $employee_id);
        $activeAssignment->execute();

        if ($activeAssignment->get_result()->num_rows !== 1) {
            throw new Exception('Invalid or inactive asset assignment.');
        }

        $returned_date = ($asset_status === 'Pending Return') ? null : $clearance_date;

        $itemStmt = $conn->prepare("
            INSERT INTO asset_clearance_items
            (
                clearance_id,
                inventory_id,
                asset_unit_id,
                assignment_id,
                asset_status,
                condition_after,
                remarks,
                returned_date
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $itemStmt->bind_param(
            "iiiissss",
            $clearance_id,
            $inventory_id,
            $asset_unit_id,
            $assignment_id,
            $asset_status,
            $condition_after,
            $item_remark,
            $returned_date
        );

        $itemStmt->execute();

        if ($asset_status !== 'Pending Return') {

            $updateAssign = $conn->prepare("
                UPDATE asset_assignments
                SET 
                    status = ?,
                    return_date = ?,
                    condition_after = ?,
                    remarks = ?
                WHERE id = ?
            ");

            $updateAssign->bind_param(
                "ssssi",
                $asset_status,
                $clearance_date,
                $condition_after,
                $item_remark,
                $assignment_id
            );

            $updateAssign->execute();

            $updateAsset = $conn->prepare("
                UPDATE asset_units
                SET
                    condition_status = ?,
                    unit_status = ?,
                    assigned_to = NULL,
                    assigned_date = NULL
                WHERE id = ?
            ");

            $inventory_status = $asset_status === 'Returned' ? 'Available' : $asset_status;

            $updateAsset->bind_param(
                "ssi",
                $condition_after,
                $inventory_status,
                $asset_unit_id
            );

            $updateAsset->execute();

            $history_action = $asset_status;

            if ($asset_status === 'Returned') {
                $history_action = 'Cleared';
            }

            $historyStmt = $conn->prepare("
                INSERT INTO asset_history
                (
                    inventory_id,
                    asset_unit_id,
                    assignment_id,
                    action_type,
                    employee_id,
                    action_date,
                    condition_status,
                    remarks,
                    created_by
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $historyStmt->bind_param(
                "iiisisssi",
                $inventory_id,
                $asset_unit_id,
                $assignment_id,
                $history_action,
                $employee_id,
                $clearance_date,
                $condition_after,
                $item_remark,
                $created_by
            );

            $historyStmt->execute();
        }
    }

    $conn->commit();

    echo "
    <script>
        alert('Asset clearance saved successfully.');
        window.location='employee_clearance.php?id=$clearance_id';
    </script>";
    exit;

} catch (Exception $e) {

    $conn->rollback();

    echo "
    <script>
        alert('Error saving clearance: " . addslashes($e->getMessage()) . "');
        window.location='asset_clearance.php?employee_id=$employee_id';
    </script>";
    exit;
}
?>
