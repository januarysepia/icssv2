<?php

include '../auth/auth_check.php';

require_role([
    'Boss',
    'Admin',
    'Supervisor'
]);

include '../config/database.php';

$logs = $conn->query("
SELECT
activity_logs.*,
users.fullname,
users.employee_no,
users.system_role

FROM activity_logs

LEFT JOIN users
ON users.id = activity_logs.user_id

ORDER BY activity_logs.id DESC
LIMIT 200
");

?>

<!DOCTYPE html>
<html>
<head>
    <title>Activity Logs</title>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">

    <style>
        body{
            background:#f4f6f9;
        }

        .card-box{
            border:0;
            border-radius:16px;
            box-shadow:0 4px 14px rgba(0,0,0,0.08);
        }

        .timeline-item{
            border-left:4px solid #111827;
            padding-left:18px;
            margin-bottom:20px;
            position:relative;
        }

        .timeline-dot{
            width:14px;
            height:14px;
            background:#111827;
            border-radius:50%;
            position:absolute;
            left:-9px;
            top:5px;
        }

        .module-badge{
            font-size:12px;
            padding:5px 9px;
            border-radius:999px;
        }

        .admin-page{width:100%}
        .admin-page > .container-fluid{
            max-width:1500px;
            margin-left:auto;
            margin-right:auto;
        }

        @media(max-width:768px){
            .container-fluid{
                padding-left:12px;
                padding-right:12px;
            }

            .timeline-item{
                font-size:14px;
            }
        }
    </style>
</head>

<body>

<?php if (($_SESSION['system_role'] ?? '') !== 'Admin') include 'sidebar.php'; ?>

<div class="<?= ($_SESSION['system_role'] ?? '') === 'Admin' ? 'admin-page' : 'content-wrapper' ?>">

<?php include 'header.php'; ?>

<div class="container-fluid mt-4">

    <div class="card card-box">

        <div class="card-header bg-dark text-white">

            <div class="d-flex justify-content-between align-items-center">

                <h4 class="mb-0">
                    System Activity Logs
                </h4>

                <a href="index.php"
                   class="btn btn-light btn-sm">

                    Dashboard

                </a>

            </div>

        </div>

        <div class="card-body">

            <?php if($logs && $logs->num_rows > 0){ ?>

                <?php while($row = $logs->fetch_assoc()){ ?>

                    <div class="timeline-item">

                        <div class="timeline-dot"></div>

                        <div class="mb-1">

                            <span class="badge bg-dark module-badge">
                                <?php echo $row['module_name']; ?>
                            </span>

                            <span class="text-muted ms-2">
                                <?php echo $row['created_at']; ?>
                            </span>

                        </div>

                        <div class="fw-bold">
                            <?php echo $row['activity']; ?>
                        </div>

                        <div class="text-muted mt-1">

                            By:

                            <?php

                            if(!empty($row['fullname'])){

                                echo $row['employee_no']
                                . " - "
                                . $row['fullname']
                                . " ("
                                . $row['system_role']
                                . ")";

                            }else{

                                echo "System / Unknown";
                            }

                            ?>

                        </div>

                    </div>

                <?php } ?>

            <?php }else{ ?>

                <div class="alert alert-info">
                    No activity logs yet.
                </div>

            <?php } ?>

        </div>

    </div>

</div>

</div>

</body>
</html>
