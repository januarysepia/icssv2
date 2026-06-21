<?php


include '../auth/auth_check.php';

require_role(['Boss','Admin']);

include '../config/database.php';

require_post();
verify_csrf();

$fullname = trim($_POST['fullname'] ?? '');
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';
$department_id = null;
$position = trim($_POST['position'] ?? '');
$system_role = $_POST['system_role'] ?? '';

$allowed_roles = ['Boss', 'Admin', 'Technical', 'Engineer', 'Supervisor', 'Production', 'Purchasing', 'Logistics', 'QAQC'];

if ($fullname === '' || $username === '' || strlen($password) < 8) {
    exit('Invalid employee details. Password must contain at least 8 characters.');
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
        exit('A valid production department is required.');
    }
}

/*
AUTO EMPLOYEE NUMBER
*/

$getLast = $conn->query("
SELECT id FROM users
ORDER BY id DESC
LIMIT 1
");

if($getLast->num_rows > 0){

    $last = $getLast->fetch_assoc();

    $number = $last['id'] + 1;

}else{

    $number = 1;
}

$employee_no = "EMP-" . str_pad($number, 4, "0", STR_PAD_LEFT);

/*
SAVE USER
*/

$password_hash = password_hash($password, PASSWORD_DEFAULT);
$stmt = $conn->prepare("
    INSERT INTO users
    (employee_no, fullname, username, password, system_role, department_id, position)
    VALUES (?, ?, ?, ?, ?, ?, ?)
");
$stmt->bind_param('sssssis', $employee_no, $fullname, $username, $password_hash, $system_role, $department_id, $position);

if($stmt->execute()){

    echo "
    <script>
        alert('Employee Added Successfully');
        window.location='employee_list.php';
    </script>
    ";

}else{

    http_response_code(500);
    echo 'Unable to save employee.';
}

?>
