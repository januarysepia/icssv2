<?php

include '../auth/auth_check.php';

require_role([
    'Boss',
    'Admin',
    'Purchasing'
]);

include '../config/database.php';

/*
GET ACTIVE SUPPLIERS
*/

$supplier_options = [];

$supplier_result = $conn->query("
SELECT supplier_name
FROM suppliers
WHERE status = 'Active'
ORDER BY supplier_name ASC
");

if($supplier_result){
    while($supplier = $supplier_result->fetch_assoc()){
        $supplier_options[] = $supplier['supplier_name'];
    }
}

?>

<!DOCTYPE html>
<html>
<head>

    <title>Create Inventory Item</title>

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
                    Add Inventory Item
                </h4>

                <div>

                    <a href="../dashboard/index.php"
                       class="btn btn-light btn-sm">
                        Dashboard
                    </a>

                    <a href="inventory_list.php"
                       class="btn btn-secondary btn-sm">
                        Back
                    </a>

                </div>

            </div>

        </div>

        <div class="card-body">

            <form action="save_item.php"
                  method="POST">
                <?php echo csrf_field(); ?>

                <div class="row">

                    <div class="col-md-4 mb-3">

                        <label class="fw-bold">
                            Item Code
                        </label>

                        <input type="text"
                               name="item_code"
                               class="form-control"
                               required>

                    </div>

                    <div class="col-md-4 mb-3">

                        <label class="fw-bold">
                            Item Name
                        </label>

                        <input type="text"
                               name="item_name"
                               class="form-control"
                               required>

                    </div>

                    <div class="col-md-4 mb-3">

                        <label class="fw-bold">
                            Brand
                        </label>

                        <input type="text"
                               name="brand"
                               class="form-control">

                    </div>

                </div>

                <div class="row">

                    <div class="col-md-4 mb-3">

                        <label class="fw-bold">
                            Item Type
                        </label>

                        <select name="item_type"
                                id="item_type"
                                class="form-control"
                                onchange="toggleAssetFields()"
                                required>

                            <option value="">
                                Select Item Type
                            </option>

                            <option value="Consumable">
                                Consumable
                            </option>

                            <option value="Asset">
                                Asset
                            </option>

                        </select>

                    </div>

                    <div class="col-md-4 mb-3 asset-field" style="display:none;">

                        <label class="fw-bold">
                            Asset Usage
                        </label>

                        <select name="asset_usage"
                                id="asset_usage"
                                class="form-control">

                            <option value="">
                                Select Asset Usage
                            </option>

                            <option value="Borrowable">
                                Borrowable
                            </option>

                            <option value="Assigned">
                                Assigned
                            </option>

                            <option value="Both">
                                Both
                            </option>

                        </select>

                    </div>

                    <div class="col-md-4 mb-3 asset-field" style="display:none;">

                        <label class="fw-bold">
                            Asset Status
                        </label>

                        <select name="asset_status"
                                class="form-control">

                            <option value="Available">
                                Available
                            </option>

                            <option value="Under Repair">
                                Under Repair
                            </option>

                            <option value="Damaged">
                                Damaged
                            </option>

                        </select>

                    </div>

                </div>

                <div class="row">

                    <div class="col-md-4 mb-3">

                        <label class="fw-bold">
                            Category
                        </label>

                        <select name="category"
                                class="form-control"
                                required>

                            <option value="">
                                Select Category
                            </option>

                            <option value="Electrical">
                                Electrical
                            </option>

                            <option value="Mechanical">
                                Mechanical
                            </option>

                            <option value="Consumables">
                                Consumables
                            </option>

                            <option value="Tools">
                                Tools
                            </option>

                            <option value="PPE">
                                PPE
                            </option>

                            <option value="Office Supplies">
                                Office Supplies
                            </option>

                            <option value="Raw Materials">
                                Raw Materials
                            </option>

                            <option value="Office Equipment">
                                Office Equipment
                            </option>

                            <option value="Testing Equipment">
                                Testing Equipment
                            </option>

                            <option value="Others">
                                Others
                            </option>

                        </select>

                    </div>

                    <div class="col-md-4 mb-3">

                        <label class="fw-bold">
                            Unit
                        </label>

                        <input type="text"
                               name="unit"
                               class="form-control"
                               placeholder="pcs / box / meter"
                               required>

                    </div>

                    <div class="col-md-4 mb-3">

                        <label class="fw-bold">
                            Quantity / Number of Physical Units
                        </label>

                        <input type="number"
                               name="quantity"
                               class="form-control"
                               min="0"
                               required>

                    </div>

                </div>

                <div class="row asset-field" style="display:none;">

                    <div class="col-md-6 mb-3">

                        <label class="fw-bold">
                            Model
                        </label>

                        <input type="text"
                               name="model"
                               class="form-control">

                    </div>

                    <div class="col-md-6 mb-3">

                        <label class="fw-bold">Serial Numbers</label>
                        <textarea name="serial_numbers"
                                  class="form-control"
                                  rows="4"
                                  placeholder="One serial number per line. Leave blank if a unit has no manufacturer serial."></textarea>
                        <small class="text-muted">
                            Internal asset codes will be generated automatically, e.g. DRILL-001-001.
                        </small>

                    </div>

                </div>

                <div class="row">

                    <div class="col-md-4 mb-3">

                        <label class="fw-bold">
                            Minimum Stock
                        </label>

                        <input type="number"
                               name="minimum_stock"
                               class="form-control"
                               min="0"
                               value="5"
                               required>

                    </div>

                    <div class="col-md-4 mb-3">

                        <label class="fw-bold">
                            Storage Location
                        </label>

                        <input type="text"
                               name="storage_location"
                               class="form-control"
                               placeholder="Rack A-1 / Warehouse 1">

                    </div>

                    <div class="col-md-4 mb-3">

                        <label class="fw-bold">
                            Unit Price
                        </label>

                        <input type="number"
                               step="0.01"
                               name="unit_price"
                               class="form-control"
                               min="0"
                               required>

                    </div>

                </div>

                <div class="row">

                    <div class="col-md-6 mb-3">

                        <label class="fw-bold">
                            Item Condition
                        </label>

                        <select name="item_condition"
                                class="form-control">

                            <option value="Good">
                                Good
                            </option>

                            <option value="With Minor Issue">
                                With Minor Issue
                            </option>

                            <option value="Damaged">
                                Damaged
                            </option>

                        </select>

                    </div>

                    <div class="col-md-6 mb-3">

                        <label class="fw-bold">
                            Supplier
                        </label>

                        <select name="supplier"
                                class="form-control">

                            <option value="">
                                Select Supplier
                            </option>

                            <?php foreach($supplier_options as $supplier_name){ ?>

                                <option value="<?php echo $supplier_name; ?>">
                                    <?php echo $supplier_name; ?>
                                </option>

                            <?php } ?>

                        </select>

                    </div>

                </div>

                <div class="mb-3">

                    <label class="fw-bold">
                        Description
                    </label>

                    <textarea name="description"
                              class="form-control"
                              rows="4"></textarea>

                </div>

                <button type="submit"
                        class="btn btn-success">
                    Save Item
                </button>

            </form>

        </div>

    </div>

</div>

</div>

<script>

function toggleAssetFields(){

    let itemType = document.getElementById('item_type').value;
    let assetFields = document.querySelectorAll('.asset-field');
    let assetUsage = document.getElementById('asset_usage');

    if(itemType === 'Asset'){

        assetFields.forEach(function(field){
            field.style.display = '';
        });

        assetUsage.required = true;

    }else{

        assetFields.forEach(function(field){
            field.style.display = 'none';
        });

        assetUsage.required = false;
        assetUsage.value = '';
    }
}

</script>

</body>
</html>
