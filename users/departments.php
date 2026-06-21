<?php
include '../auth/auth_check.php';require_role(['Admin']);include '../config/database.php';
$departments=$conn->query("
SELECT d.*,COUNT(u.id) user_count FROM departments d
LEFT JOIN users u ON u.department_id=d.id GROUP BY d.id ORDER BY d.department_name
");
?>
<!DOCTYPE html><html><head><title>Department Management</title><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>body{background:#f4f6f9}.admin-page{max-width:1000px;margin:0 auto;padding:14px}.add-grid{display:grid;grid-template-columns:1fr auto;gap:8px}@media(max-width:576px){.add-grid{grid-template-columns:1fr}}</style></head><body>
<?php include '../dashboard/header.php'; ?><main class="admin-page"><section class="card shadow-sm border-0">
<header class="card-header bg-dark text-white d-flex justify-content-between align-items-center gap-2"><h1 class="h5 mb-0">Department Management</h1><a href="../dashboard/index.php" class="btn btn-light btn-sm">Dashboard</a></header>
<div class="card-body"><form action="save_department.php" method="post" class="add-grid mb-3"><?= csrf_field() ?>
<input type="hidden" name="action" value="add"><input name="department_name" class="form-control" maxlength="100" placeholder="New department name" required><button class="btn btn-primary">+ Add Department</button></form>
<div class="table-responsive"><table class="table table-bordered table-hover align-middle"><thead class="table-dark"><tr><th>Department</th><th>Assigned Users</th><th>Status</th><th>Action</th></tr></thead><tbody>
<?php while($row=$departments->fetch_assoc()): ?><tr><td><strong><?= h($row['department_name']) ?></strong></td><td><?= (int)$row['user_count'] ?></td>
<td><span class="badge <?= $row['status']==='Active'?'bg-success':'bg-secondary' ?>"><?= h($row['status']) ?></span></td><td>
<form action="save_department.php" method="post" class="d-inline" onsubmit="return confirm('Change this department status?');"><?= csrf_field() ?>
<input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
<button class="btn btn-outline-secondary btn-sm"><?= $row['status']==='Active'?'Deactivate':'Activate' ?></button></form>
</td></tr><?php endwhile; ?></tbody></table></div></div></section></main></body></html>
