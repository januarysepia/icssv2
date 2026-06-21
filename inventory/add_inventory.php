<?php

include '../auth/auth_check.php';

require_role([
    'Boss',
    'Admin',
    'Purchasing'
]);

include '../config/database.php';

?>

<!DOCTYPE html>
<html>
<head>

    <title>Add Inventory</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

</head>

<body class="bg-light">

<div class="container mt-5">

    <div class="card shadow border-0">

        <div class="card-header bg-dark text-white">

            <h4 class="mb-0">
                Add Inventory Item
            </h4>

        </div>

        <div class="card-body">

            <form action="save_inventory.php" method="POST">
                <?php echo csrf_field(); ?>

                <div class="mb-3">

                    <label>Item Code</label>

                    <input type="text"
                           name="item_code"
                           class="form-control"
                           required>

                </div>

                <div class="mb-3">

                    <label>Item Name</label>

                    <input type="text"
                           name="item_name"
                           class="form-control"
                           required>

                </div>

                <div class="mb-3">

                    <label>Category</label>

                    <select name="category"
                            class="form-control"
                            required>

                        <option value="">
                            Select Category
                        </option>

                        <option value="Consumable">
                            Consumable
                        </option>

                        <option value="Borrowable">
                            Borrowable
                        </option>

                    </select>

                </div>

                <div class="mb-3">

                    <label>Quantity</label>

                    <input type="number"
                           name="quantity"
                           class="form-control"
                           required>

                </div>

                <div class="mb-3">

                    <label>Unit</label>

                    <input type="text"
                           name="unit"
                           class="form-control"
                           placeholder="pcs, box, roll, set"
                           required>

                </div>

                <button type="submit"
                        class="btn btn-primary">

                    Save Inventory

                </button>

            </form>

        </div>

    </div>

</div>

</body>
</html>
