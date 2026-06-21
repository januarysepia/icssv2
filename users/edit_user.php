<?php
include '../auth/auth_check.php';
require_role(['Admin']);
include '../config/database.php';

$id = max(0, (int) ($_GET['id'] ?? 0));
$stmt = $conn->prepare("SELECT * FROM users WHERE id=?");
$stmt->bind_param('i', $id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
if (!$user) exit('User not found.');

$departments = $conn->query("
    SELECT id,department_name FROM departments
    WHERE status='Active'
      AND department_name NOT IN ('QA/QC','Logistics')
    ORDER BY department_name
");
?>
<!DOCTYPE html><html><head>
<title>Edit User</title><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>body{background:#f4f6f9}.admin-form{max-width:1050px;margin:0 auto;padding:14px}</style>
</head><body>
<?php include '../dashboard/header.php'; ?>
<main class="admin-form"><section class="card shadow-sm border-0">
<header class="card-header bg-dark text-white d-flex justify-content-between align-items-center gap-2">
<h1 class="h5 mb-0">Edit User</h1>
<a href="employee_list.php" class="btn btn-light btn-sm">User Management</a>
</header><div class="card-body">
<form action="save_edit_user.php" method="post">
<?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
<div class="row g-2">
<div class="col-md-4"><label class="form-label">Employee No.</label><input name="employee_no" class="form-control" value="<?= h($user['employee_no']) ?>" required></div>
<div class="col-md-4"><label class="form-label">Full Name</label><input name="fullname" class="form-control" value="<?= h($user['fullname']) ?>" required></div>
<div class="col-md-4"><label class="form-label">Username</label><input name="username" class="form-control" value="<?= h($user['username']) ?>" required></div>
<div class="col-md-4"><label class="form-label">System Role</label>
<select name="system_role" id="systemRole" class="form-select" required>
<?php foreach(['Boss','Admin','Technical','Engineer','Supervisor','Production','Purchasing','Logistics'] as $role): ?>
<option value="<?= h($role) ?>" <?= $user['system_role']===$role?'selected':'' ?>><?= h($role) ?></option>
<?php endforeach; ?></select></div>
<div class="col-md-4 <?= $user['system_role']==='Production'?'':'d-none' ?>" id="departmentField">
<label class="form-label">Production Department</label>
<select name="department_id" id="departmentId" class="form-select">
<option value="">Select Department</option>
<?php while($dept=$departments->fetch_assoc()): ?>
<option value="<?= (int)$dept['id'] ?>" <?= (int)$user['department_id']===(int)$dept['id']?'selected':'' ?>><?= h($dept['department_name']) ?></option>
<?php endwhile; ?></select></div>
<div class="col-md-4"><label class="form-label">Position</label><input name="position" class="form-control" value="<?= h($user['position']) ?>"></div>
</div>
<div class="d-flex justify-content-end gap-2 mt-3">
<a href="reset_password.php?id=<?= $id ?>" class="btn btn-warning">Reset Password</a>
<button class="btn btn-primary">Save Changes</button>
</div></form></div></section></main>
<script>
const role=document.getElementById('systemRole'),field=document.getElementById('departmentField'),dept=document.getElementById('departmentId');
function sync(){const production=role.value==='Production';field.classList.toggle('d-none',!production);dept.required=production;dept.disabled=!production;if(!production)dept.value='';}
role.addEventListener('change',sync);sync();
</script></body></html>
