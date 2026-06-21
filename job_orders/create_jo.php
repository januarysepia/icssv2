<?php

include '../auth/auth_check.php';

require_role([
    'Boss',
    'Admin',
    'Technical'
]);

include '../config/database.php';

/*
Preview next JO number
Actual JO number is still generated in save_jo.php
*/

$year = date('Y');

$last = $conn->query("
SELECT jo_no
FROM job_orders
WHERE jo_no LIKE 'JO-$year-%'
ORDER BY CAST(SUBSTRING_INDEX(jo_no, '-', -1) AS UNSIGNED) DESC
LIMIT 1
")->fetch_assoc();

if($last){
    $last_number = intval(substr($last['jo_no'], -3));
    $next_number = $last_number + 1;
}else{
    $next_number = 1;
}

$preview_jo_no = 'JO-' . $year . '-' . str_pad($next_number, 3, '0', STR_PAD_LEFT);

$engineers = $conn->query("
    SELECT fullname
    FROM users
    WHERE system_role = 'Engineer'
      AND status = 'Active'
    ORDER BY fullname
");

$sales_people = $conn->query("
    SELECT sales_name
    FROM sales_representatives
    WHERE status = 'Active'
    ORDER BY sales_name
");

?>

<!DOCTYPE html>
<html>
<head>
    <title>Create Job Order</title>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">

    <style>
        body{
            background:#f4f6f9;
        }

        .create-jo-page{
            max-width:1100px;
            margin:0 auto;
            padding:14px 16px 26px;
        }

        .page-card{
            border:1px solid #e1e5ea;
            border-radius:10px;
            box-shadow:0 3px 11px rgba(15,23,42,.07);
            overflow:hidden;
        }

        .page-card .card-header{
            padding:10px 14px;
        }

        .page-card .card-header h1{
            font-size:1rem;
            font-weight:750;
        }

        .page-card .card-header .btn{
            padding:.28rem .52rem;
            font-size:.72rem;
        }

        .page-card .card-body{
            padding:14px;
        }

        .jo-preview{
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:12px;
            padding:8px 11px;
            margin-bottom:14px;
            font-size:.76rem;
        }

        .jo-preview strong{
            font-size:.82rem;
        }

        .jo-preview small{
            display:block;
            margin-top:1px;
            font-size:.66rem;
        }

        .section-title{
            color:#374151;
            font-size:.76rem;
            font-weight:750;
            text-transform:uppercase;
            letter-spacing:.035em;
            margin:14px 0 9px;
            border-bottom:1px solid #e5e7eb;
            padding-bottom:6px;
        }

        .section-title:first-of-type{
            margin-top:0;
        }

        .compact-field{
            margin-bottom:10px;
        }

        .compact-field label{
            display:block;
            margin-bottom:4px;
            font-size:.72rem;
            font-weight:700;
        }

        .compact-field .form-control,
        .compact-field .form-select{
            min-height:34px;
            padding:.38rem .58rem;
            font-size:.78rem;
        }

        .compact-field small{
            display:block;
            margin-top:4px;
            font-size:.66rem;
        }

        .form-actions{
            margin-top:15px;
            padding-top:11px;
            border-top:1px solid #e5e7eb;
        }

        .form-actions .btn{
            min-width:105px;
            padding:.38rem .7rem;
            font-size:.76rem;
        }

        html[data-theme="dark"] .section-title{
            color:#d1d5db;
            border-color:#414141;
        }

        html[data-theme="dark"] .form-actions{
            border-color:#414141;
        }

        @media(max-width:576px){
            .create-jo-page{
                padding:10px;
            }

            .page-card .card-header > div{
                align-items:flex-start!important;
                gap:8px;
            }

            .jo-preview{
                align-items:flex-start;
                flex-direction:column;
            }

            .form-actions .btn{
                flex:1;
                min-width:0;
            }
        }
    </style>
</head>

<body>

<main class="create-jo-page">

    <div class="card page-card">

        <div class="card-header bg-dark text-white">

            <div class="d-flex justify-content-between align-items-center">

                <h1 class="mb-0">
                    Create Job Order
                </h1>

                <div>
                    <a href="../dashboard/index.php"
                       class="btn btn-light btn-sm">
                        Dashboard
                    </a>

                    <a href="jo_list.php"
                       class="btn btn-secondary btn-sm">
                        Back
                    </a>
                </div>

            </div>

        </div>

        <div class="card-body">

            <div class="alert alert-info jo-preview">
                <div>
                    <strong>Next JO No: <?php echo h($preview_jo_no); ?></strong>
                    <small>Preview only. The final number is generated automatically when saved.</small>
                </div>
                <span class="badge bg-info text-dark">Auto-generated</span>
            </div>

            <form action="save_jo.php"
                  method="POST"
                  enctype="multipart/form-data">
                <?php echo csrf_field(); ?>

                <div class="section-title">
                    Job Order Information
                </div>

                <div class="row g-2">

                    <div class="col-md-6 compact-field">
                        <label>Client Name</label>

                        <input type="text"
                               name="client_name"
                               class="form-control"
                               required>
                    </div>

                    <div class="col-md-6 compact-field">
                        <label>Project Name</label>

                        <input type="text"
                               name="project_name"
                               class="form-control"
                               required>
                    </div>

                    <div class="col-md-6 compact-field">
                        <label>Engineer Name</label>

                        <select name="engineer_name" class="form-select" required>
                            <option value="">Select Engineer</option>
                            <?php while ($engineer = $engineers->fetch_assoc()): ?>
                                <option value="<?= h($engineer['fullname']) ?>">
                                    <?= h($engineer['fullname']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="col-md-6 compact-field">
                        <div class="d-flex justify-content-between align-items-center gap-2">
                            <label>Sales Name</label>
                            <a href="manage_sales.php" class="small">Manage Sales</a>
                        </div>
                        <select name="sales_name" class="form-select" required>
                            <option value="">Select Sales Personnel</option>
                            <?php while ($sales = $sales_people->fetch_assoc()): ?>
                                <option value="<?= h($sales['sales_name']) ?>">
                                    <?= h($sales['sales_name']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                </div>

                <div class="section-title">
                    Schedule
                </div>

                <div class="row g-2">

                    <div class="col-md-6 compact-field">
                        <label>Release Date</label>

                        <input type="date"
                               name="release_date"
                               class="form-control"
                               value="<?php echo date('Y-m-d'); ?>">
                    </div>

                    <div class="col-md-6 compact-field">
                        <label>Due Date</label>

                        <input type="date"
                               name="due_date"
                               class="form-control"
                               required>
                    </div>

                </div>

                <div class="section-title">
                    Drawing Attachment
                </div>

                <div class="compact-field">
                    <label>Upload Drawing / PDF</label>

                    <input type="file"
                           name="drawing_file"
                           class="form-control"
                           accept=".pdf,.jpg,.jpeg,.png"
                           required>

                    <small class="text-muted">
                        Recommended: PDF drawing. This file will be linked to the Job Order.
                    </small>
                </div>

                <div class="d-flex justify-content-end gap-2 form-actions">

                    <a href="jo_list.php"
                       class="btn btn-secondary">
                        Cancel
                    </a>

                    <button type="submit"
                            class="btn btn-primary">
                        Save Job Order
                    </button>

                </div>

            </form>

        </div>

    </div>

</main>

</body>
</html>
