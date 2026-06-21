<?php
include '../auth/auth_check.php';

require_role(['Boss','Admin']);

include '../config/database.php';
?>

<!DOCTYPE html>
<html>
<head>

    <title>Add Employee</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

</head>

<body>

<div class="container mt-5">

    <div class="card shadow">

        <div class="card-header">
            <h3>Add Employee</h3>
        </div>

        <div class="card-body">

            <form action="save_employee.php" method="POST">
                <?php echo csrf_field(); ?>

                <div class="mb-3">
                    <label>Full Name</label>
                    <input type="text" name="fullname" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label>Username</label>
                    <input type="text" name="username" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label>Password</label>
                    <input type="password" name="password" class="form-control" minlength="8" required>
                </div>

                <div class="mb-3 d-none" id="departmentField">
                    <label>Production Department</label>

                    <select name="department_id" id="departmentId" class="form-control" disabled>

                        <option value="">Select Production Department</option>

                        <?php

                        $departments = $conn->query("
                            SELECT * FROM departments
                            WHERE status = 'Active'
                              AND department_name NOT IN ('QA/QC','Logistics')
                            ORDER BY department_name
                        ");

                        while($dept = $departments->fetch_assoc()){

                        ?>

                        <option value="<?php echo $dept['id']; ?>">
                            <?php echo $dept['department_name']; ?>
                        </option>

                        <?php } ?>

                    </select>

                </div>

                <div class="mb-3">
                    <label>Position</label>
                    <input type="text" name="position" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label>System Role</label>

                    <select name="system_role" id="systemRole" class="form-control" required>

                        <option value="Production">Production</option>
                        <option value="Supervisor">Supervisor</option>
                        <option value="Technical">Technical</option>
                        <option value="Boss">Boss</option>
                        <option value="Admin">Admin</option>
                        <option value="QAQC">QAQC</option>

                    </select>

                </div>

                <button type="submit" class="btn btn-primary">
                    Save Employee
                </button>

            </form>

        </div>

    </div>

</div>

<script>
(function(){
    const role = document.getElementById('systemRole');
    const field = document.getElementById('departmentField');
    const department = document.getElementById('departmentId');
    function sync(){
        const production = role.value === 'Production';
        field.classList.toggle('d-none', !production);
        department.disabled = !production;
        department.required = production;
        if(!production) department.value = '';
    }
    role.addEventListener('change', sync);
    sync();
})();
</script>
</body>
</html>
