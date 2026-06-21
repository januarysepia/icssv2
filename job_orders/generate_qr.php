<?php

include '../auth/auth_check.php';

require_role([
    'Boss',
    'Admin',
    'Supervisor',
    'Technical'
]);

include '../config/database.php';
include '../libs/phpqrcode/qrlib.php';

$id = intval($_GET['id']);

$job = $conn->query("
SELECT *
FROM job_orders
WHERE id = '$id'
")->fetch_assoc();

if(!$job){
    die("Job Order not found.");
}

$jo_no = $job['jo_no'];

$qr_folder = "../uploads/qrcodes/";

if(!file_exists($qr_folder)){
    mkdir($qr_folder, 0777, true);
}

$qr_file = $qr_folder . $jo_no . ".png";

/*
CHANGE THIS TO YOUR SERVER IP
*/

$server_ip = "192.168.10.15";

/*
QR URL
*/

$qr_url =
"http://" .
$server_ip .
"/icssv2/job_orders/scan_jo.php?id=" .
$id;

/*
GENERATE QR
*/

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

    <title>
        QR Code -
        <?php echo $jo_no; ?>
    </title>

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

            max-width:450px;

            margin:auto;

            text-align:center;

            background:white;

            border-radius:15px;

            box-shadow:0 4px 14px rgba(0,0,0,0.1);

            padding:25px;
        }

        .qr-image{

            width:260px;

            max-width:100%;
        }

        .company-name{

            font-size:26px;

            font-weight:bold;

            margin-bottom:15px;
        }

        .jo-title{

            font-size:22px;

            font-weight:bold;

            margin-top:15px;
        }

        .info-box{

            margin-top:15px;

            text-align:left;
        }

        .info-box p{

            margin-bottom:8px;
        }

        @media print{

            .no-print{

                display:none !important;
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

        <div class="company-name">

            ICSS ERP

        </div>

        <img
            src="../uploads/qrcodes/<?php echo $jo_no; ?>.png"
            class="qr-image"
        >

        <div class="jo-title">

            <?php echo $jo_no; ?>

        </div>

        <div class="info-box">

            <p>

                <strong>Project:</strong>

                <?php echo $job['project_name']; ?>

            </p>

            <p>

                <strong>Client:</strong>

                <?php echo $job['client_name']; ?>

            </p>

            <p>

                <strong>Engineer:</strong>

                <?php echo $job['engineer_name']; ?>

            </p>

            <p>

                <strong>Status:</strong>

                <?php echo $job['workflow_status']; ?>

            </p>

            <p>

                <strong>Due Date:</strong>

                <?php echo $job['due_date']; ?>

            </p>

        </div>

        <div class="mt-4 no-print">

            <a href="../uploads/qrcodes/<?php echo $jo_no; ?>.png"
               target="_blank"
               class="btn btn-primary">

                View QR

            </a>

            <button
                onclick="window.print();"
                class="btn btn-success">

                Print QR

            </button>

            <a href="view_jo.php?id=<?php echo $id; ?>"
               class="btn btn-secondary">

                Back

            </a>

        </div>

    </div>

</div>

</body>
</html>