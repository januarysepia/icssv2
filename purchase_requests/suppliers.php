<?php

include '../auth/auth_check.php';

require_role([
    'Purchasing',
    'Admin',
    'Boss'
]);

include '../config/database.php';

$suppliers = $conn->query("
SELECT *
FROM suppliers
ORDER BY supplier_name ASC
");

?>

<!DOCTYPE html>
<html>
<head>
    <title>Supplier Management</title>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">
</head>

<body class="bg-light">

<div class="container-fluid mt-4">

    <div class="card shadow border-0">

        <div class="card-header bg-dark text-white">

            <div class="d-flex justify-content-between align-items-center">

                <h4 class="mb-0">
                    Supplier Management
                </h4>

                <div class="d-flex flex-wrap gap-1">

                    <a href="../dashboard/index.php"
                       class="btn btn-light btn-sm">
                        Dashboard
                    </a>

                    <a href="supplier_catalog.php"
                       class="btn btn-info btn-sm">
                        Item Catalog
                    </a>

                    <a href="price_comparison.php"
                       class="btn btn-warning btn-sm">
                        Compare Prices
                    </a>

                    <a href="create_supplier.php"
                       class="btn btn-success btn-sm">
                        + Add Supplier
                    </a>

                </div>

            </div>

        </div>

        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-bordered table-hover align-middle">

                    <thead class="table-dark">

                        <tr>
                            <th>ID</th>
                            <th>Supplier Code</th>
                            <th>Supplier Name</th>
                            <th>Contact Person</th>
                            <th>Mobile</th>
                            <th>Email</th>
                            <th>Products Supplied</th>
                            <th>Status</th>
                            <th width="180">Action</th>
                        </tr>

                    </thead>

                    <tbody>

                    <?php if($suppliers && $suppliers->num_rows > 0){ ?>

                        <?php while($row = $suppliers->fetch_assoc()){ ?>

                            <tr>

                                <td>
                                    <?php echo $row['id']; ?>
                                </td>

                                <td>
                                    <?php echo $row['supplier_code']; ?>
                                </td>

                                <td>
                                    <b>
                                        <?php echo $row['supplier_name']; ?>
                                    </b>
                                </td>

                                <td>
                                    <?php echo $row['contact_person']; ?>
                                </td>

                                <td>
                                    <?php echo $row['mobile_number']; ?>
                                </td>

                                <td>
                                    <?php echo $row['email']; ?>
                                </td>

                                <td>
                                    <?php echo $row['products_supplied']; ?>
                                </td>

                                <td>

                                    <?php if($row['status'] == 'Active'){ ?>

                                        <span class="badge bg-success">
                                            Active
                                        </span>

                                    <?php }else{ ?>

                                        <span class="badge bg-danger">
                                            Inactive
                                        </span>

                                    <?php } ?>

                                </td>

                                <td>

                                    <a href="view_supplier.php?id=<?php echo $row['id']; ?>"
                                       class="btn btn-info btn-sm">
                                        View
                                    </a>

                                    <a href="edit_supplier.php?id=<?php echo $row['id']; ?>"
                                       class="btn btn-warning btn-sm">
                                        Edit
                                    </a>

                                </td>

                            </tr>

                        <?php } ?>

                    <?php }else{ ?>

                        <tr>

                            <td colspan="9"
                                class="text-center text-muted">

                                No suppliers found.

                            </td>

                        </tr>

                    <?php } ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>

</body>
</html>
