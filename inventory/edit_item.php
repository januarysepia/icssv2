<?php

include '../auth/auth_check.php';

require_role([
    'Boss',
    'Admin',
    'Purchasing'
]);

include '../config/database.php';

$id = intval($_GET['id'] ?? 0);

$item = $conn->query("
SELECT *
FROM inventory_items
WHERE id = '$id'
")->fetch_assoc();

if(!$item){
    die('Inventory item not found.');
}

$suppliers = $conn->query("
SELECT supplier_name
FROM suppliers
WHERE status='Active'
ORDER BY supplier_name ASC
");

?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Inventory Item</title>
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
                    Edit Inventory Item
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

            <form action="update_item.php"
                  method="POST">
                <?php echo csrf_field(); ?>

                <input type="hidden"
                       name="id"
                       value="<?php echo $item['id']; ?>">

                <div class="row">

                    <div class="col-md-4 mb-3">
                        <label class="fw-bold">Item Code</label>

                        <input type="text"
                               name="item_code"
                               class="form-control"
                               value="<?php echo $item['item_code']; ?>"
                               required>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="fw-bold">Item Name</label>

                        <input type="text"
                               name="item_name"
                               class="form-control"
                               value="<?php echo $item['item_name']; ?>"
                               required>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="fw-bold">Brand</label>

                        <input type="text"
                               name="brand"
                               class="form-control"
                               value="<?php echo $item['brand']; ?>">
                    </div>

                </div>

                <div class="row">

                    <div class="col-md-4 mb-3">
                        <label class="fw-bold">Item Type</label>

                        <select name="item_type"
                                id="item_type"
                                class="form-control"
                                onchange="toggleAssetFields()"
                                required>

                            <option value="Consumable"
                                <?php echo (($item['item_type'] ?? '') == 'Consumable') ? 'selected' : ''; ?>>
                                Consumable
                            </option>

                            <option value="Asset"
                                <?php echo (($item['item_type'] ?? '') == 'Asset') ? 'selected' : ''; ?>>
                                Asset
                            </option>

                        </select>
                    </div>

                    <div class="col-md-4 mb-3 asset-field">
                        <label class="fw-bold">Asset Usage</label>

                        <select name="asset_usage"
                                id="asset_usage"
                                class="form-control">

                            <option value="">Select Usage</option>

                            <option value="Borrowable"
                                <?php echo (($item['asset_usage'] ?? '') == 'Borrowable') ? 'selected' : ''; ?>>
                                Borrowable
                            </option>

                            <option value="Assigned"
                                <?php echo (($item['asset_usage'] ?? '') == 'Assigned') ? 'selected' : ''; ?>>
                                Assigned
                            </option>

                            <option value="Both"
                                <?php echo (($item['asset_usage'] ?? '') == 'Both') ? 'selected' : ''; ?>>
                                Both
                            </option>

                        </select>
                    </div>

                    <div class="col-md-4 mb-3 asset-field">
                        <label class="fw-bold">Asset Status</label>

                        <select name="asset_status"
                                class="form-control">

                            <option value="Available"
                                <?php echo (($item['asset_status'] ?? '') == 'Available') ? 'selected' : ''; ?>>
                                Available
                            </option>

                            <option value="Assigned"
                                <?php echo (($item['asset_status'] ?? '') == 'Assigned') ? 'selected' : ''; ?>>
                                Assigned
                            </option>

                            <option value="Borrowed"
                                <?php echo (($item['asset_status'] ?? '') == 'Borrowed') ? 'selected' : ''; ?>>
                                Borrowed
                            </option>

                            <option value="Damaged"
                                <?php echo (($item['asset_status'] ?? '') == 'Damaged') ? 'selected' : ''; ?>>
                                Damaged
                            </option>

                            <option value="Lost"
                                <?php echo (($item['asset_status'] ?? '') == 'Lost') ? 'selected' : ''; ?>>
                                Lost
                            </option>

                            <option value="Under Repair"
                                <?php echo (($item['asset_status'] ?? '') == 'Under Repair') ? 'selected' : ''; ?>>
                                Under Repair
                            </option>

                            <option value="Disposed"
                                <?php echo (($item['asset_status'] ?? '') == 'Disposed') ? 'selected' : ''; ?>>
                                Disposed
                            </option>

                        </select>
                    </div>

                </div>

                <div class="row asset-field">

                    <div class="col-md-6 mb-3">
                        <label class="fw-bold">Model</label>

                        <input type="text"
                               name="model"
                               class="form-control"
                               value="<?php echo $item['model'] ?? ''; ?>">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="fw-bold">Serial Number</label>

                        <input type="text"
                               name="serial_number"
                               class="form-control"
                               value="<?php echo $item['serial_number'] ?? ''; ?>">
                    </div>

                </div>

                <div class="row">

                    <div class="col-md-4 mb-3">
                        <label class="fw-bold">Category</label>

                        <select name="category"
                                class="form-control"
                                required>

                            <?php
                            $categories = [
                                'Electrical',
                                'Mechanical',
                                'Consumables',
                                'Tools',
                                'PPE',
                                'Office Supplies',
                                'Raw Materials',
                                'Office Equipment',
                                'Testing Equipment',
                                'Others'
                            ];

                            foreach($categories as $category){
                            ?>

                                <option value="<?php echo $category; ?>"
                                    <?php echo ($item['category'] == $category) ? 'selected' : ''; ?>>
                                    <?php echo $category; ?>
                                </option>

                            <?php } ?>

                        </select>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="fw-bold">Unit</label>

                        <input type="text"
                               name="unit"
                               class="form-control"
                               value="<?php echo $item['unit']; ?>"
                               required>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="fw-bold">Quantity</label>

                        <input type="number"
                               name="quantity"
                               class="form-control"
                               min="0"
                               value="<?php echo $item['quantity']; ?>"
                               required>
                    </div>

                </div>

                <div class="row">

                    <div class="col-md-4 mb-3">
                        <label class="fw-bold">Minimum Stock</label>

                        <input type="number"
                               name="minimum_stock"
                               class="form-control"
                               min="0"
                               value="<?php echo $item['minimum_stock']; ?>"
                               required>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="fw-bold">Storage Location</label>

                        <input type="text"
                               name="storage_location"
                               class="form-control"
                               value="<?php echo $item['storage_location']; ?>">
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="fw-bold">Unit Price</label>

                        <input type="number"
                               step="0.01"
                               name="unit_price"
                               class="form-control"
                               min="0"
                               value="<?php echo $item['unit_price']; ?>"
                               required>
                    </div>

                </div>

                <div class="row">

                    <div class="col-md-6 mb-3">
                        <label class="fw-bold">Item Condition</label>

                        <select name="item_condition"
                                class="form-control">

                            <option value="Good"
                                <?php echo (($item['item_condition'] ?? '') == 'Good') ? 'selected' : ''; ?>>
                                Good
                            </option>

                            <option value="With Minor Issue"
                                <?php echo (($item['item_condition'] ?? '') == 'With Minor Issue') ? 'selected' : ''; ?>>
                                With Minor Issue
                            </option>

                            <option value="Damaged"
                                <?php echo (($item['item_condition'] ?? '') == 'Damaged') ? 'selected' : ''; ?>>
                                Damaged
                            </option>

                        </select>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="fw-bold">Supplier</label>

                        <select name="supplier"
                                class="form-control">

                            <option value="">Select Supplier</option>

                            <?php if($suppliers){ ?>
                                <?php while($supplier = $suppliers->fetch_assoc()){ ?>

                                    <option value="<?php echo $supplier['supplier_name']; ?>"
                                        <?php echo ($item['supplier'] == $supplier['supplier_name']) ? 'selected' : ''; ?>>

                                        <?php echo $supplier['supplier_name']; ?>

                                    </option>

                                <?php } ?>
                            <?php } ?>

                        </select>
                    </div>

                </div>

                <div class="mb-3">
                    <label class="fw-bold">Description</label>

                    <textarea name="description"
                              class="form-control"
                              rows="4"><?php echo $item['description']; ?></textarea>
                </div>

                <button type="submit"
                        class="btn btn-primary">
                    Update Item
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

toggleAssetFields();

</script>

</body>
</html>
