<?php

include '../auth/auth_check.php';

require_role([
    'Boss',
    'Admin'
]);

include '../config/database.php';

$employees = $conn->query("
SELECT
users.*,
departments.department_name

FROM users

LEFT JOIN departments
ON departments.id = users.department_id

ORDER BY users.id DESC
");

?>

<!DOCTYPE html>
<html>
<head>

    <title>Employee List</title>

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

        .employee-avatar{

            width:45px;
            height:45px;

            border-radius:50%;

            background:#111827;

            color:white;

            display:flex;

            align-items:center;
            justify-content:center;

            font-weight:700;
        }

        .status-badge{

            padding:6px 10px;

            border-radius:999px;

            font-size:12px;
        }

        .admin-page{
            width:100%;
        }

        .admin-page > .container-fluid{
            max-width:1700px;
            margin-left:auto;
            margin-right:auto;
        }

        @media (max-width:768px){

            .table-responsive{

                font-size:13px;
            }

            .btn{

                font-size:12px;
            }
        }

    </style>

</head>

<body>

<?php if (($_SESSION['system_role'] ?? '') !== 'Admin') include '../dashboard/sidebar.php'; ?>

<div class="<?= ($_SESSION['system_role'] ?? '') === 'Admin' ? 'admin-page' : 'content-wrapper' ?>">

<?php include '../dashboard/header.php'; ?>

<div class="container-fluid mt-4">

    <div class="card card-box">

        <div class="card-header bg-dark text-white">

            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">

                <h4 class="mb-0">
                    Employee Management
                </h4>

                <div class="d-flex flex-wrap gap-1">
                    <a href="../dashboard/index.php" class="btn btn-light btn-sm">Dashboard</a>
                    <?php if (($_SESSION['system_role'] ?? '') === 'Admin'): ?>
                        <a href="departments.php" class="btn btn-info btn-sm">Departments</a>
                        <a href="login_history.php" class="btn btn-warning btn-sm">Login History</a>
                    <?php endif; ?>
                    <a href="create_user.php" class="btn btn-success btn-sm">+ Add User</a>
                </div>

            </div>

        </div>

        <div class="card-body">

            <div class="row mb-4">

                <div class="col-md-3 mb-3">

                    <div class="card border-0 shadow-sm">

                        <div class="card-body">

                            <div class="text-muted">
                                Total Employees
                            </div>

                            <h3>
                                <?php echo $employees->num_rows; ?>
                            </h3>

                        </div>

                    </div>

                </div>

                <div class="col-md-3 mb-3">

                    <div class="card border-0 shadow-sm">

                        <div class="card-body">

                            <div class="text-muted">
                                Active Users
                            </div>

                            <h3 class="text-success">

                                <?php

                                $active = $conn->query("
                                SELECT COUNT(*) AS total
                                FROM users
                                WHERE status='Active'
                                ")->fetch_assoc();

                                echo $active['total'];

                                ?>

                            </h3>

                        </div>

                    </div>

                </div>

                <div class="col-md-3 mb-3">

                    <div class="card border-0 shadow-sm">

                        <div class="card-body">

                            <div class="text-muted">
                                Inactive Users
                            </div>

                            <h3 class="text-danger">

                                <?php

                                $inactive = $conn->query("
                                SELECT COUNT(*) AS total
                                FROM users
                                WHERE status='Inactive'
                                ")->fetch_assoc();

                                echo $inactive['total'];

                                ?>

                            </h3>

                        </div>

                    </div>

                </div>

                <div class="col-md-3 mb-3">

                    <div class="card border-0 shadow-sm">

                        <div class="card-body">

                            <div class="text-muted">
                                Departments
                            </div>

                            <h3 class="text-primary">

                                <?php

                                $dept = $conn->query("
                                SELECT COUNT(DISTINCT department_id) AS total
                                FROM users
                                WHERE department_id IS NOT NULL
                                ")->fetch_assoc();

                                echo $dept['total'];

                                ?>

                            </h3>

                        </div>

                    </div>

                </div>

            </div>

            <div class="table-responsive">

                <table class="table table-hover align-middle">

                    <thead class="table-dark">

                        <tr>

                            <th>#</th>

                            <th>Employee</th>

                            <th>Employee No</th>

                            <th>Department</th>

                            <th>Role</th>

                            <th>Username</th>

                            <th>Status</th>

                            <th width="250">
                                Actions
                            </th>

                        </tr>

                    </thead>

                    <tbody>

                    <?php

                    $count = 1;

                    mysqli_data_seek($employees,0);

                    while($row = $employees->fetch_assoc()){

                    ?>

                        <tr>

                            <td>
                                <?php echo $count++; ?>
                            </td>

                            <td>

                                <div class="d-flex align-items-center gap-2">

                                    <div class="employee-avatar">

                                        <?php
                                        echo strtoupper(substr($row['fullname'],0,1));
                                        ?>

                                    </div>

                                    <div>

                                        <div class="fw-bold">
                                            <?php echo $row['fullname']; ?>
                                        </div>

                                        <small class="text-muted">
                                            <?php echo $row['email'] ?? 'No Email'; ?>
                                        </small>

                                    </div>

                                </div>

                            </td>

                            <td>
                                <?php echo $row['employee_no']; ?>
                            </td>

                            <td>

                                <?php
                                echo $row['department_name']
                                ?? 'No Department';
                                ?>

                            </td>

                            <td>

                                <span class="badge bg-primary">
                                    <?php echo $row['system_role']; ?>
                                </span>

                            </td>

                            <td>
                                <?php echo $row['username']; ?>
                            </td>

                            <td>

                                <?php if($row['status'] == 'Active'){ ?>

                                    <span class="badge bg-success status-badge">
                                        Active
                                    </span>

                                <?php }else{ ?>

                                    <span class="badge bg-danger status-badge">
                                        Inactive
                                    </span>

                                <?php } ?>

                            </td>

                            <td>

                                <?php if (($_SESSION['system_role'] ?? '') === 'Admin'): ?>
                                <a href="edit_user.php?id=<?php echo $row['id']; ?>"
                                   class="btn btn-primary btn-sm">

                                    Edit

                                </a>

                                <a href="reset_password.php?id=<?= (int)$row['id'] ?>"
                                   class="btn btn-warning btn-sm">Reset</a>

                                <?php if ((int)$row['id'] !== (int)$_SESSION['user_id']): ?>
                                    <form action="toggle_user_status.php" method="post" class="d-inline"
                                          onsubmit="return confirm('<?= $row['status']==='Active'?'Deactivate':'Activate' ?> this user?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                        <button class="btn <?= $row['status']==='Active'?'btn-danger':'btn-success' ?> btn-sm">
                                            <?= $row['status']==='Active'?'Deactivate':'Activate' ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted small">View only</span>
                                <?php endif; ?>

                            </td>

                        </tr>

                    <?php } ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>

</div>

</body>
</html>
