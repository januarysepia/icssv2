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
    i.quantity,
    COALESCE(au.storage_location, i.storage_location) AS storage_location
FROM asset_units au
INNER JOIN inventory_items i ON i.id = au.inventory_id
WHERE au.id = '$unit_id'
AND i.item_type = 'Asset'
")->fetch_assoc();

if(!$asset){
    die("Asset not found.");
}

if(
    $asset['asset_usage'] != 'Assigned'
    &&
    $asset['asset_usage'] != 'Both'
){
    echo "
    <script>
        alert('This asset is not allowed for employee assignment.');
        window.location='asset_list.php';
    </script>
    ";
    exit();
}

if($asset['asset_status'] != 'Available' || !empty($asset['assigned_to'])){
    echo "
    <script>
        alert('This asset is not available for assignment.');
        window.location='asset_list.php';
    </script>
    ";
    exit();
}

$employees = $conn->query("
SELECT
    id,
    employee_no,
    fullname,
    system_role
FROM users
WHERE status = 'Active'
ORDER BY fullname ASC
");

?>

<!DOCTYPE html>
<html>
<head>
    <title>Assign Asset</title>
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
                    Assign Asset
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

                    <p><b>Asset Code:</b> <?php echo $asset['item_code']; ?></p>
                    <p><b>Asset Name:</b> <?php echo $asset['item_name']; ?></p>
                    <p><b>Brand:</b> <?php echo $asset['brand']; ?></p>
                    <p><b>Model:</b> <?php echo $asset['model'] ?? '-'; ?></p>
                    <p><b>Serial Number:</b> <?php echo h($asset['serial_number'] ?? '-'); ?></p>

                </div>

                <div class="col-md-6">

                    <p><b>Asset Usage:</b> <?php echo $asset['asset_usage']; ?></p>
                    <p><b>Condition:</b> <?php echo $asset['item_condition'] ?? 'Good'; ?></p>
                    <p><b>Status:</b> <?php echo $asset['asset_status'] ?? 'Available'; ?></p>
                    <p><b>Location:</b> <?php echo $asset['storage_location'] ?? 'Not Set'; ?></p>
                    <p><b>Quantity:</b> <?php echo $asset['quantity']; ?></p>

                </div>

            </div>

            <hr>

            <form action="save_asset_assignment.php" method="POST">
                <?php echo csrf_field(); ?>

                <input type="hidden"
                       name="asset_unit_id"
                       value="<?php echo $asset['asset_unit_id']; ?>">

                <div class="mb-3">
                    <label class="fw-bold">
                        Assign To Employee
                    </label>

                    <select name="assigned_to"
                            class="form-control"
                            required>

                        <option value="">
                            Select Employee
                        </option>

                        <?php while($emp = $employees->fetch_assoc()){ ?>

                            <option value="<?php echo $emp['id']; ?>">
                                <?php echo $emp['employee_no']; ?>
                                -
                                <?php echo $emp['fullname']; ?>
                                (<?php echo $emp['system_role']; ?>)
                            </option>

                        <?php } ?>

                    </select>
                </div>

                <div class="mb-3">
                    <label class="fw-bold">
                        Assigned Date
                    </label>

                    <input type="date"
                           name="assigned_date"
                           class="form-control"
                           value="<?php echo date('Y-m-d'); ?>"
                           required>
                </div>

                <div class="mb-3">
                    <label class="fw-bold">
                        Condition Before Assignment
                    </label>

                    <select name="condition_before"
                            class="form-control"
                            required>

                        <option value="Good"
                            <?php echo (($asset['item_condition'] ?? '') == 'Good') ? 'selected' : ''; ?>>
                            Good
                        </option>

                        <option value="With Minor Issue"
                            <?php echo (($asset['item_condition'] ?? '') == 'With Minor Issue') ? 'selected' : ''; ?>>
                            With Minor Issue
                        </option>

                        <option value="Damaged"
                            <?php echo (($asset['item_condition'] ?? '') == 'Damaged') ? 'selected' : ''; ?>>
                            Damaged
                        </option>

                    </select>
                </div>

                <div class="mb-3">
                    <label class="fw-bold">
                        Remarks
                    </label>

                    <textarea name="remarks"
                              class="form-control"
                              rows="4"
                              placeholder="Reason / purpose of asset assignment..."></textarea>
                </div>

                <div class="d-flex justify-content-end gap-2">

                    <a href="asset_list.php"
                       class="btn btn-secondary">
                        Cancel
                    </a>

                    <button type="submit"
                            class="btn btn-success">
                        Save Assignment
                    </button>

                </div>

            </form>

        </div>

    </div>

</div>

</div>

</body>
</html>
