<?php
include '../auth/auth_check.php'; require_role(['Admin']); include '../config/database.php';
$id=(int)($_GET['id']??0);
$stmt=$conn->prepare("SELECT id,fullname,username FROM users WHERE id=?");$stmt->bind_param('i',$id);$stmt->execute();
$user=$stmt->get_result()->fetch_assoc();if(!$user)exit('User not found.');
?>
<!DOCTYPE html><html><head><title>Reset Password</title><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-light">
<?php include '../dashboard/header.php'; ?><main style="max-width:650px;margin:0 auto;padding:14px">
<section class="card shadow-sm border-0"><header class="card-header bg-dark text-white"><h1 class="h5 mb-0">Reset Password</h1></header>
<div class="card-body"><p><strong><?= h($user['fullname']) ?></strong> · <?= h($user['username']) ?></p>
<form action="save_reset_password.php" method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
<div class="mb-3"><label class="form-label">New Password</label><input type="password" name="password" class="form-control" minlength="8" required></div>
<div class="mb-3"><label class="form-label">Confirm Password</label><input type="password" name="confirm_password" class="form-control" minlength="8" required></div>
<div class="d-flex justify-content-end gap-2"><a href="employee_list.php" class="btn btn-secondary">Cancel</a><button class="btn btn-warning">Reset Password</button></div>
</form></div></section></main></body></html>
