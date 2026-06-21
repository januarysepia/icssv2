<?php

include '../auth/auth_check.php';

require_role([
    'Boss',
    'Admin',
    'Purchasing',
    'Supervisor'
]);

include '../config/database.php';
include '../libs/phpqrcode/qrlib.php';

$id = intval($_GET['id']);

$item = $conn->query("
SELECT *
FROM inventory_items
WHERE id = '$id'
")->fetch_assoc();

if(!$item){
    die("Item not found.");
}

$item_code = $item['item_code'];

$qr_folder = "../uploads/item_qrcodes/";

if(!file_exists($qr_folder)){
    mkdir($qr_folder, 0777, true);
}

$qr_file = $qr_folder . $item_code . ".png";

/*
PALITAN MO NG ACTUAL IP NG PC/SERVER MO
*/

$server_ip = "192.168.10.15";

$qr_url =
"http://" .
$server_ip .
"/icssv2/inventory/scan_item.php?id=" .
$id;

if(!file_exists($qr_file)){

    QRcode::png(
        $qr_url,
        $qr_file,
        QR_ECLEVEL_H,
        10
    );
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>Item QR - <?php echo $item_code; ?></title>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">

    <style>
        body{
            background:#f4f6f9;
        }

        .qr-card{
            max-width:420px;
            margin:auto;
            background:white;
            border-radius:16px;
            padding:25px;
            text-align:center;
            box-shadow:0 4px 14px rgba(0,0,0,0.1);
        }

        .qr-image{
            width:250px;
            max-width:100%;
        }

        @media print{
            .no-print{
                display:none;
            }

            body{
                background:white;
            }

            .qr-card{
                box-shadow:none;
                border:2px solid #000;
            }
        }
    </style>
</head>

<body>

<div class="container mt-4">

    <div class="qr-card">

        <h4>ICSS ERP</h4>

        <h5>Inventory Asset QR</h5>

        <img src="../uploads/item_qrcodes/<?php echo $item_code; ?>.png"
             class="qr-image">

        <h4 class="mt-3">
            <?php echo $item_code; ?>
        </h4>

        <p class="mb-1">
            <b><?php echo $item['item_name']; ?></b>
        </p>

        <p class="mb-1">
            Type:
            <?php echo $item['item_type'] ?? 'Consumable'; ?>
        </p>

        <p class="mb-1">
            Condition:
            <?php echo $item['item_condition'] ?? 'Good'; ?>
        </p>

        <p class="mb-3">
            Status:
            <?php echo $item['asset_status'] ?? 'Available'; ?>
        </p>

        <div class="no-print">

            <a href="../uploads/item_qrcodes/<?php echo $item_code; ?>.png"
               target="_blank"
               class="btn btn-primary">
                View QR
            </a>

            <button onclick="window.print();"
                    class="btn btn-success">
                Print QR
            </button>

            <a href="inventory_list.php"
               class="btn btn-secondary">
                Back
            </a>

        </div>

    </div>

</div>

</body>
</html>