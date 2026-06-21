<?php

include '../auth/auth_check.php';

require_role([
    'Boss',
    'Admin',
    'Purchasing',
    'Supervisor'
]);

include '../config/database.php';

$role = $_SESSION['system_role'] ?? $_SESSION['role'] ?? '';

$assets = $conn->query("
SELECT
    au.id AS asset_unit_id,
    au.asset_code,
    au.serial_number,
    au.unit_status AS asset_status,
    au.condition_status AS item_condition,
    au.assigned_to,
    au.assigned_date,
    COALESCE(au.storage_location, inventory_items.storage_location) AS storage_location,
    au.qr_code,
    inventory_items.id AS inventory_id,
    inventory_items.item_code AS catalog_code,
    inventory_items.item_name,
    inventory_items.brand,
    inventory_items.model,
    inventory_items.asset_usage,
    users.fullname AS assigned_fullname,
    users.employee_no AS assigned_employee_no

FROM asset_units au

INNER JOIN inventory_items
ON inventory_items.id = au.inventory_id

LEFT JOIN users
ON users.id = au.assigned_to

ORDER BY inventory_items.item_name ASC, au.asset_code ASC
");

if(!$assets){
    die($conn->error);
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>Asset Management</title>

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
                    Asset Management
                </h4>

                <div>
                    <a href="../dashboard/index.php"
                       class="btn btn-light btn-sm">
                        Dashboard
                    </a>

                    <a href="inventory_list.php"
                       class="btn btn-secondary btn-sm">
                        Inventory
                    </a>
                    <a href="asset_catalog.php" class="btn btn-success btn-sm">Stock Summary</a>
                </div>

            </div>

        </div>

        <div class="card-body">

            <div class="alert alert-info">
                This module monitors company assets, assigned employees, asset status, and accountability records.
            </div>

            <div class="table-responsive">

                <table class="table table-bordered table-hover align-middle">

                    <thead class="table-dark">

                        <tr>
                            <th>Unit ID</th>
                            <th>Asset Code</th>
                            <th>Asset Name</th>
                            <th>Brand</th>
                            <th>Model</th>
                            <th>Serial No.</th>
                            <th>Usage</th>
                            <th>Condition</th>
                            <th>Asset Status</th>
                            <th>Assigned To</th>
                            <th>Assigned Date</th>
                            <th>Location</th>
                            <th width="260">Action</th>
                        </tr>

                    </thead>

                    <tbody>

                    <?php if($assets->num_rows > 0){ ?>

                        <?php while($row = $assets->fetch_assoc()){ ?>

                            <?php
                            $status = $row['asset_status'] ?? 'Available';
                            $usage = $row['asset_usage'] ?? '-';
                            ?>

                            <tr>

                                <td><?php echo $row['asset_unit_id']; ?></td>

                                <td>
                                    <b><?php echo h($row['asset_code']); ?></b>
                                    <br><small class="text-muted">Catalog: <?php echo h($row['catalog_code']); ?></small>
                                </td>

                                <td>
                                    <b><?php echo $row['item_name']; ?></b>
                                </td>

                                <td>
                                    <?php echo $row['brand']; ?>
                                </td>

                                <td>
                                    <?php echo $row['model'] ?? '-'; ?>
                                </td>

                                <td><?php echo h($row['serial_number'] ?: '-'); ?></td>

                                <td>
                                    <?php if($usage == 'Borrowable'){ ?>

                                        <span class="badge bg-warning text-dark">
                                            Borrowable
                                        </span>

                                    <?php }elseif($usage == 'Assigned'){ ?>

                                        <span class="badge bg-primary">
                                            Assigned
                                        </span>

                                    <?php }elseif($usage == 'Both'){ ?>

                                        <span class="badge bg-info text-dark">
                                            Borrowable / Assigned
                                        </span>

                                    <?php }else{ ?>

                                        <span class="badge bg-secondary">
                                            <?php echo $usage; ?>
                                        </span>

                                    <?php } ?>
                                </td>

                                <td>
                                    <?php if(($row['item_condition'] ?? '') == 'Good'){ ?>

                                        <span class="badge bg-success">
                                            Good
                                        </span>

                                    <?php }elseif(($row['item_condition'] ?? '') == 'Damaged'){ ?>

                                        <span class="badge bg-danger">
                                            Damaged
                                        </span>

                                    <?php }elseif(($row['item_condition'] ?? '') == 'With Minor Issue'){ ?>

                                        <span class="badge bg-warning text-dark">
                                            With Minor Issue
                                        </span>

                                    <?php }else{ ?>

                                        <span class="badge bg-secondary">
                                            <?php echo $row['item_condition'] ?? 'Unknown'; ?>
                                        </span>

                                    <?php } ?>
                                </td>

                                <td>
                                    <?php
                                    if($status == 'Available'){
                                        echo "<span class='badge bg-success'>Available</span>";
                                    }elseif($status == 'Assigned'){
                                        echo "<span class='badge bg-primary'>Assigned</span>";
                                    }elseif($status == 'Borrowed'){
                                        echo "<span class='badge bg-warning text-dark'>Borrowed</span>";
                                    }elseif($status == 'Damaged'){
                                        echo "<span class='badge bg-danger'>Damaged</span>";
                                    }elseif($status == 'Under Repair'){
                                        echo "<span class='badge bg-info text-dark'>Under Repair</span>";
                                    }elseif($status == 'Lost'){
                                        echo "<span class='badge bg-dark'>Lost</span>";
                                    }elseif($status == 'Disposed'){
                                        echo "<span class='badge bg-secondary'>Disposed</span>";
                                    }else{
                                        echo "<span class='badge bg-secondary'>".$status."</span>";
                                    }
                                    ?>
                                </td>

                                <td>
                                    <?php
                                    if(!empty($row['assigned_fullname'])){
                                        echo $row['assigned_employee_no'] . ' - ' . $row['assigned_fullname'];
                                    }else{
                                        echo '<span class="text-muted">Not Assigned</span>';
                                    }
                                    ?>
                                </td>

                                <td>
                                    <?php
                                    echo !empty($row['assigned_date'])
                                    ? $row['assigned_date']
                                    : '-';
                                    ?>
                                </td>

                                <td>
                                    <?php
                                    echo !empty($row['storage_location'])
                                    ? $row['storage_location']
                                    : 'Not Set';
                                    ?>
                                </td>

                                <td>

                                    <div class="asset-row-actions">

                                        <a href="asset_history.php?unit_id=<?php echo $row['asset_unit_id']; ?>"
                                           class="btn btn-info btn-sm">
                                            History
                                        </a>

                                        <a href="asset_unit_qr_view.php?unit_id=<?php echo $row['asset_unit_id']; ?>"
                                           class="btn btn-dark btn-sm">
                                            QR
                                        </a>

                                        <?php if(in_array($role, ['Boss','Admin','Purchasing'])){ ?>

                                            <?php if($status == 'Available' && empty($row['assigned_to'])){ ?>

                                                <?php if($usage == 'Assigned' || $usage == 'Both'){ ?>

                                                    <a href="assign_asset.php?unit_id=<?php echo $row['asset_unit_id']; ?>"
                                                       class="btn btn-success btn-sm">
                                                        Assign
                                                    </a>

                                                <?php } ?>

                                            <?php } ?>

                                            <?php if($status == 'Assigned' && !empty($row['assigned_to'])){ ?>

                                                <a href="return_asset.php?unit_id=<?php echo $row['asset_unit_id']; ?>"
                                                   class="btn btn-warning btn-sm">
                                                    Return
                                                </a>

                                            <?php } ?>

                                        <?php } ?>

                                    </div>

                                </td>

                            </tr>

                        <?php } ?>

                    <?php }else{ ?>

                        <tr>
                            <td colspan="13"
                                class="text-center text-muted">
                                No asset records found.
                            </td>
                        </tr>

                    <?php } ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>

</div>

</body>
</html>
