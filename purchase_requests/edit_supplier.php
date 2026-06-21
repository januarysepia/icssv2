<?php

include '../auth/auth_check.php';



require_role([
    'Purchasing',
    'Admin'
]);

include '../config/database.php';

$id = intval($_GET['id']);

$supplier = $conn->query("
SELECT *
FROM suppliers
WHERE id = '$id'
")->fetch_assoc();

if(!$supplier){
    die('Supplier not found.');
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Supplier</title>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">
</head>

<body class="bg-light">

<div class="container mt-4 mb-5">

    <div class="card shadow border-0">

        <div class="card-header bg-dark text-white">

            <div class="d-flex justify-content-between align-items-center">

                <h4 class="mb-0">
                    Edit Supplier
                </h4>

                <div>

                    <a href="../dashboard/index.php"
                       class="btn btn-light btn-sm">
                        Dashboard
                    </a>

                    <a href="suppliers.php"
                       class="btn btn-secondary btn-sm">
                        Back
                    </a>

                </div>

            </div>

        </div>

        <div class="card-body">

            <form action="update_supplier.php"
                  method="POST">

                <input type="hidden"
                       name="id"
                       value="<?php echo $supplier['id']; ?>">

                <div class="row">

                    <div class="col-md-4 mb-3">

                        <label class="fw-bold">
                            Supplier Code
                        </label>

                        <input type="text"
                               class="form-control"
                               value="<?php echo $supplier['supplier_code']; ?>"
                               readonly>

                    </div>

                    <div class="col-md-8 mb-3">

                        <label class="fw-bold">
                            Supplier Name
                        </label>

                        <input type="text"
                               name="supplier_name"
                               class="form-control"
                               value="<?php echo $supplier['supplier_name']; ?>"
                               required>

                    </div>

                    <div class="col-md-6 mb-3">

                        <label class="fw-bold">
                            Contact Person
                        </label>

                        <input type="text"
                               name="contact_person"
                               class="form-control"
                               value="<?php echo $supplier['contact_person']; ?>">

                    </div>

                    <div class="col-md-3 mb-3">

                        <label class="fw-bold">
                            Mobile Number
                        </label>

                        <input type="text"
                               name="mobile_number"
                               class="form-control"
                               value="<?php echo $supplier['mobile_number']; ?>">

                    </div>

                    <div class="col-md-3 mb-3">

                        <label class="fw-bold">
                            Telephone Number
                        </label>

                        <input type="text"
                               name="telephone_number"
                               class="form-control"
                               value="<?php echo $supplier['telephone_number']; ?>">

                    </div>

                    <div class="col-md-6 mb-3">

                        <label class="fw-bold">
                            Email Address
                        </label>

                        <input type="email"
                               name="email"
                               class="form-control"
                               value="<?php echo $supplier['email']; ?>">

                    </div>

                    <div class="col-md-6 mb-3">

                        <label class="fw-bold">
                            Status
                        </label>

                        <select name="status"
                                class="form-control">

                            <option value="Active"
                                <?php echo ($supplier['status'] == 'Active') ? 'selected' : ''; ?>>
                                Active
                            </option>

                            <option value="Inactive"
                                <?php echo ($supplier['status'] == 'Inactive') ? 'selected' : ''; ?>>
                                Inactive
                            </option>

                        </select>

                    </div>

                    <div class="col-md-12 mb-3">

                        <label class="fw-bold">
                            Address
                        </label>

                        <textarea name="address"
                                  class="form-control"
                                  rows="3"><?php echo $supplier['address']; ?></textarea>

                    </div>

                    <div class="col-md-12 mb-3">

                        <label class="fw-bold">
                            Products Supplied
                        </label>

                        <textarea name="products_supplied"
                                  class="form-control"
                                  rows="4"><?php echo $supplier['products_supplied']; ?></textarea>

                    </div>

                </div>

                <div class="d-flex justify-content-end gap-2">

                    <a href="suppliers.php"
                       class="btn btn-secondary">
                        Cancel
                    </a>

                    <button type="submit"
                            class="btn btn-primary">
                        Update Supplier
                    </button>

                </div>

            </form>

        </div>

    </div>

</div>

</body>
</html>