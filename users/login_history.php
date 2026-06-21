<?php
include '../auth/auth_check.php';require_role(['Admin']);include '../config/database.php';
$filter=($_GET['filter']??'all');if(!in_array($filter,['all','success','failed'],true))$filter='all';
$where=$filter==='success'?'WHERE la.success=1':($filter==='failed'?'WHERE la.success=0':'');
$logs=$conn->query("
SELECT la.*,u.fullname,u.employee_no FROM login_attempts la
LEFT JOIN users u ON u.id=la.user_id $where ORDER BY la.id DESC LIMIT 300
");
?>
<!DOCTYPE html><html><head><title>Login History</title><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>body{background:#f4f6f9}.admin-page{max-width:1500px;margin:0 auto;padding:14px}.login-table{font-size:.76rem}@media(max-width:576px){.login-table{min-width:950px}}</style></head><body>
<?php include '../dashboard/header.php'; ?><main class="admin-page"><section class="card shadow-sm border-0">
<header class="card-header bg-dark text-white d-flex justify-content-between align-items-center gap-2">
<h1 class="h5 mb-0">Login & Security History</h1><a href="../dashboard/index.php" class="btn btn-light btn-sm">Dashboard</a></header>
<div class="card-body"><div class="d-flex flex-wrap gap-1 mb-3">
<a href="?filter=all" class="btn <?= $filter==='all'?'btn-dark':'btn-outline-dark' ?> btn-sm">All</a>
<a href="?filter=success" class="btn <?= $filter==='success'?'btn-success':'btn-outline-success' ?> btn-sm">Successful</a>
<a href="?filter=failed" class="btn <?= $filter==='failed'?'btn-danger':'btn-outline-danger' ?> btn-sm">Failed</a>
</div><div class="table-responsive"><table class="table table-bordered table-hover align-middle login-table">
<thead class="table-dark"><tr><th>Date/Time</th><th>User</th><th>Username Attempted</th><th>Result</th><th>IP Address</th><th>Device / Browser</th></tr></thead><tbody>
<?php if($logs->num_rows):while($row=$logs->fetch_assoc()): ?><tr>
<td><?= h($row['attempted_at']) ?></td><td><?= h($row['fullname']?:'Unknown') ?></td><td><?= h($row['username_attempted']) ?></td>
<td><span class="badge <?= $row['success']?'bg-success':'bg-danger' ?>"><?= $row['success']?'Success':'Failed' ?></span></td>
<td><?= h($row['ip_address']?:'-') ?></td><td title="<?= h($row['user_agent']) ?>"><?= h(mb_strimwidth($row['user_agent']?:'-',0,90,'…')) ?></td>
</tr><?php endwhile;else:?><tr><td colspan="6" class="text-center text-muted">No login history yet.</td></tr><?php endif; ?>
</tbody></table></div></div></section></main></body></html>
