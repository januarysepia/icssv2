<?php

include '../auth/auth_check.php';

require_role([
    'Boss',
    'Admin',
    'Purchasing'
]);

include '../config/database.php';

$unit_id = intval($_GET['unit_id'] ?? 0);

$asset = $conn->query("
SELECT
    au.*,
    au.id AS asset_unit_id,
    au.asset_code AS item_code,
    au.unit_status AS asset_status,
    au.condition_status AS item_condition,
    i.id AS inventory_id,
    i.item_name,
    i.brand,
    i.model,
    i.asset_usage,
    users.fullname AS assigned_fullname,
    users.employee_no AS assigned_employee_no,
    users.system_role AS assigned_role

FROM asset_units au

INNER JOIN inventory_items i
ON i.id = au.inventory_id

LEFT JOIN users ON users.id = au.assigned_to

WHERE au.id = '$unit_id'
AND i.item_type = 'Asset'
")->fetch_assoc();

if(!$asset){
    die('Asset not found.');
}

if(
    $asset['asset_usage'] != 'Assigned'
    &&
    $asset['asset_usage'] != 'Both'
){
    die('This asset is not configured for employee assignment.');
}

if(
    $asset['asset_status'] != 'Assigned'
    ||
    empty($asset['assigned_to'])
){
    echo "
    <script>
        alert('This asset is not currently assigned.');
        window.location='asset_list.php';
    </script>
    ";
    exit();
}

$assignment = $conn->query("
SELECT *
FROM asset_assignments
WHERE asset_unit_id = '$unit_id'
AND status = 'Assigned'
ORDER BY id DESC
LIMIT 1
")->fetch_assoc();

if(!$assignment){
    die('Active asset assignment record not found.');
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>Return Asset</title>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">
    <?php include '../includes/asset_ui.php'; ?>
</head>

<body class="bg-light asset-module">

<?php include '../dashboard/sidebar.php'; ?>
<div class="content-wrapper">
<?php include '../dashboard/header.php'; ?>

<div class="container-fluid asset-page">

    <div class="card shadow border-0">

        <div class="card-header bg-dark text-white">

            <div class="d-flex justify-content-between align-items-center">

                <h4 class="mb-0">
                    Return Assigned Asset
                </h4>

                <div>

                    <a href="../dashboard/index.php"
                       class="btn btn-light btn-sm">
                        Dashboard
                    </a>

                    <a href="asset_list.php"
                       class="btn btn-secondary btn-sm">
                        Back
                    </a>

                </div>

            </div>

        </div>

        <div class="card-body">

            <div class="row mb-3">

                <div class="col-md-6">

                    <p>
                        <b>Asset Code:</b>
                        <?php echo $asset['item_code']; ?>
                    </p>

                    <p>
                        <b>Asset Name:</b>
                        <?php echo $asset['item_name']; ?>
                    </p>

                    <p>
                        <b>Brand:</b>
                        <?php echo $asset['brand']; ?>
                    </p>

                    <p>
                        <b>Model:</b>
                        <?php echo $asset['model'] ?? '-'; ?>
                    </p>

                    <p>
                        <b>Serial Number:</b>
                        <?php echo $asset['serial_number'] ?? '-'; ?>
                    </p>

                </div>

                <div class="col-md-6">

                    <p>
                        <b>Assigned To:</b>
                        <?php echo $asset['assigned_employee_no']; ?>
                        -
                        <?php echo $asset['assigned_fullname']; ?>
                        (<?php echo $asset['assigned_role']; ?>)
                    </p>

                    <p>
                        <b>Asset Usage:</b>
                        <?php echo $asset['asset_usage']; ?>
                    </p>

                    <p>
                        <b>Assigned Date:</b>
                        <?php echo $asset['assigned_date']; ?>
                    </p>

                    <p>
                        <b>Condition Before:</b>
                        <?php echo $assignment['condition_before']; ?>
                    </p>

                    <p>
                        <b>Status:</b>
                        <?php echo $asset['asset_status']; ?>
                    </p>

                </div>

            </div>

            <hr>

            <form action="save_asset_return.php"
                  method="POST">
                <?php echo csrf_field(); ?>

                <input type="hidden"
                       name="inventory_id"
                       value="<?php echo $asset['inventory_id']; ?>">

                <input type="hidden"
                       name="asset_unit_id"
                       value="<?php echo $asset['asset_unit_id']; ?>">

                <input type="hidden"
                       name="assignment_id"
                       value="<?php echo $assignment['id']; ?>">

                <div class="mb-3">

                    <label class="fw-bold">
                        Return Date
                    </label>

                    <input type="date"
                           name="return_date"
                           class="form-control"
                           value="<?php echo date('Y-m-d'); ?>"
                           required>

                </div>

                <div class="mb-3">

                    <label class="fw-bold">
                        Condition After Return
                    </label>

                    <select name="condition_after"
                            class="form-control"
                            required>

                        <option value="Good">
                            Good
                        </option>

                        <option value="With Minor Issue">
                            With Minor Issue
                        </option>

                        <option value="Damaged">
                            Damaged
                        </option>

                        <option value="Lost">
                            Lost
                        </option>

                        <option value="Under Repair">
                            Under Repair
                        </option>

                    </select>

                </div>

                <div class="mb-3">

                    <label class="fw-bold">
                        Return Remarks
                    </label>

                    <textarea name="remarks"
                              class="form-control"
                              rows="4"
                              placeholder="Return notes / condition details..."></textarea>

                </div>

                <div class="d-flex justify-content-end gap-2">

                    <a href="asset_list.php"
                       class="btn btn-secondary">
                        Cancel
                    </a>

                    <button type="submit"
                            class="btn btn-warning">
                        Save Return
                    </button>

                </div>

            </form>

        </div>

    </div>

</div>

</div>

</body>
</html>
