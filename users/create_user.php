<?php

include '../auth/auth_check.php';

require_role([
    'Boss',
    'Admin'
]);

include '../config/database.php';

$departments = $conn->query("
SELECT *
FROM departments
WHERE status = 'Active'
  AND department_name NOT IN ('QA/QC','Logistics')
ORDER BY department_name ASC
");

?>

<!DOCTYPE html>
<html>
<head>
    <title>Add Employee</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .admin-page{width:100%}
        .admin-form-page{max-width:1100px;margin-left:auto;margin-right:auto}
        @media(max-width:576px){
            .user-form-header{align-items:stretch!important;flex-direction:column}
            .user-form-actions{display:grid!important;grid-template-columns:1fr 1fr}
        }
    </style>
</head>

<body class="bg-light">

<?php if (($_SESSION['system_role'] ?? '') !== 'Admin') include '../dashboard/sidebar.php'; ?>

<div class="<?= ($_SESSION['system_role'] ?? '') === 'Admin' ? 'admin-page' : 'content-wrapper' ?>">

<?php include '../dashboard/header.php'; ?>

<div class="container mt-4 admin-form-page">

    <div class="card shadow border-0">

        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center gap-2 user-form-header">
            <h4 class="mb-0">Add Employee</h4>

            <div class="d-flex flex-wrap gap-1 user-form-actions">
                <a href="../dashboard/index.php" class="btn btn-light btn-sm">Dashboard</a>
                <a href="employee_list.php" class="btn btn-secondary btn-sm">User Management</a>
            </div>
        </div>

        <div class="card-body">

            <form action="save_user.php" method="POST">
                <?php echo csrf_field(); ?>

                <div class="row">

                    <div class="col-md-4 mb-3">
                        <label class="fw-bold">Employee No</label>
                        <input type="text" name="employee_no" class="form-control" required>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="fw-bold">Full Name</label>
                        <input type="text" name="fullname" class="form-control" required>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="fw-bold">Username</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>

                </div>

                <div class="row">

                    <div class="col-md-4 mb-3">
                        <label class="fw-bold">Password</label>
                        <input type="password" name="password" class="form-control" minlength="8" required>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="fw-bold">System Role</label>
                        <select name="system_role" id="systemRole" class="form-control" required>
                            <option value="">Select Role</option>
                            <option value="Boss">Boss</option>
                            <option value="Admin">Admin</option>
                            <option value="Technical">Technical</option>
                            <option value="Engineer">Engineer</option>
                            <option value="Supervisor">Supervisor</option>
                            <option value="Production">Production</option>
                            <option value="Purchasing">Purchasing</option>
                            <option value="Logistics">Logistics</option>
                        </select>
                    </div>

                    <div class="col-md-4 mb-3 d-none" id="departmentField">
                        <label class="fw-bold">Production Department</label>
                        <select name="department_id" id="departmentId" class="form-control">
                            <option value="">Select Production Department</option>

                            <?php while($dept = $departments->fetch_assoc()){ ?>
                                <option value="<?php echo $dept['id']; ?>">
                                    <?php echo $dept['department_name']; ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>

                </div>

                <div class="mb-3">
                    <label class="fw-bold">Position</label>
                    <input type="text" name="position" class="form-control">
                </div>

                <button type="submit" class="btn btn-success">
                    Save Employee
                </button>

            </form>

        </div>

    </div>

</div>

</div>

<script>
(function(){
    const role = document.getElementById('systemRole');
    const field = document.getElementById('departmentField');
    const department = document.getElementById('departmentId');

    function syncDepartmentField(){
        const isProduction = role.value === 'Production';
        field.classList.toggle('d-none', !isProduction);
        department.required = isProduction;
        department.disabled = !isProduction;
        if(!isProduction) department.value = '';
    }

    role.addEventListener('change', syncDepartmentField);
    syncDepartmentField();
})();
</script>

</body>
</html>
