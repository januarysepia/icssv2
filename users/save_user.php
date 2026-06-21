<?php

include '../auth/auth_check.php';

require_role([
    'Boss',
    'Admin'
]);

include '../config/database.php';

require_post();
verify_csrf();

$employee_no = trim($_POST['employee_no'] ?? '');
$fullname = trim($_POST['fullname'] ?? '');
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';
$system_role = $_POST['system_role'] ?? '';
$department_id = null;
$position = trim($_POST['position'] ?? '');

$status = 'Active';

$allowed_roles = ['Boss', 'Admin', 'Technical', 'Engineer', 'Supervisor', 'Production', 'Purchasing', 'Logistics', 'QAQC'];

if ($employee_no === '' || $fullname === '' || $username === '' || strlen($password) < 8) {
    exit('Required fields are missing or the password is shorter than 8 characters.');
}

if (!in_array($system_role, $allowed_roles, true)) {
    exit('Invalid system role.');
}

if ($system_role === 'Production') {
    $department_id = intval($_POST['department_id'] ?? 0);
    $department_check = $conn->prepare("
        SELECT id
        FROM departments
        WHERE id = ?
          AND status = 'Active'
          AND department_name NOT IN ('QA/QC','Logistics')
        LIMIT 1
    ");
    $department_check->bind_param('i', $department_id);
    $department_check->execute();
    if ($department_id <= 0 || !$department_check->get_result()->fetch_assoc()) {
        exit('Please select a valid production department.');
    }
}

$check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR employee_no = ?");
$check_stmt->bind_param('ss', $username, $employee_no);
$check_stmt->execute();
$check = $check_stmt->get_result();

if($check->num_rows > 0){

    echo "
    <script>
        alert('Username or Employee No already exists.');
        window.history.back();
    </script>
    ";

    exit();
}

$password_hash = password_hash($password, PASSWORD_DEFAULT);
$insert = $conn->prepare("
    INSERT INTO users
    (employee_no, fullname, username, password, system_role, department_id, position, status)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
");
$insert->bind_param(
    'sssssiss',
    $employee_no,
    $fullname,
    $username,
    $password_hash,
    $system_role,
    $department_id,
    $position,
    $status
);

if (!$insert->execute()) {
    http_response_code(500);
    exit('Unable to save employee.');
}

echo "
<script>
    alert('Employee Added Successfully');
    window.location='employee_list.php';
</script>
";

?>
