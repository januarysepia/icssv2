<?php

include '../auth/auth_check.php';

require_role([
    'Purchasing',
    'Admin'
]);

include '../config/database.php';

/*
AUTO GENERATE SUPPLIER CODE
*/

$last = $conn->query("
SELECT supplier_code
FROM suppliers
ORDER BY id DESC
LIMIT 1
")->fetch_assoc();

if($last){
    $last_number = intval(substr($last['supplier_code'], 4));
    $next_number = $last_number + 1;
}else{
    $next_number = 1;
}

$supplier_code = 'SUP-' . str_pad($next_number, 3, '0', STR_PAD_LEFT);

?>

<!DOCTYPE html>
<html>
<head>
    <title>Add Supplier</title>

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
                    Add Supplier
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

            <form action="save_supplier.php"
                  method="POST">

                <div class="row">

                    <div class="col-md-4 mb-3">
                        <label class="fw-bold">Supplier Code</label>

                        <input type="text"
                               name="supplier_code"
                               class="form-control"
                               value="<?php echo $supplier_code; ?>"
                               readonly>
                    </div>

                    <div class="col-md-8 mb-3">
                        <label class="fw-bold">Supplier Name</label>

                        <input type="text"
                               name="supplier_name"
                               class="form-control"
                               required>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="fw-bold">Contact Person</label>

                        <input type="text"
                               name="contact_person"
                               class="form-control">
                    </div>

                    <div class="col-md-3 mb-3">
                        <label class="fw-bold">Mobile Number</label>

                        <input type="text"
                               name="mobile_number"
                               class="form-control">
                    </div>

                    <div class="col-md-3 mb-3">
                        <label class="fw-bold">Telephone Number</label>

                        <input type="text"
                               name="telephone_number"
                               class="form-control">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="fw-bold">Email Address</label>

                        <input type="email"
                               name="email"
                               class="form-control">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="fw-bold">Status</label>

                        <select name="status"
                                class="form-control"
                                required>
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>

                    <div class="col-md-12 mb-3">
                        <label class="fw-bold">Address</label>

                        <textarea name="address"
                                  class="form-control"
                                  rows="3"></textarea>
                    </div>

                    <div class="col-md-12 mb-3">
                        <label class="fw-bold">Products Supplied</label>

                        <textarea name="products_supplied"
                                  class="form-control"
                                  rows="4"
                                  placeholder="Example: MCCB, Contactor, Wires, Busbars, Relays"></textarea>
                    </div>

                </div>

                <div class="d-flex justify-content-end gap-2">

                    <a href="suppliers.php"
                       class="btn btn-secondary">
                        Cancel
                    </a>

                    <button type="submit"
                            class="btn btn-success">
                        Save Supplier
                    </button>

                </div>

            </form>

        </div>

    </div>

</div>

</body>
</html>