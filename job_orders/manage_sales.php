<?php

include '../auth/auth_check.php';
require_role(['Boss', 'Admin', 'Technical']);
include '../config/database.php';

$message = '';
$message_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post();
    verify_csrf();

    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $sales_name = trim($_POST['sales_name'] ?? '');

        if ($sales_name === '') {
            $message = 'Sales name is required.';
            $message_type = 'danger';
        } elseif (mb_strlen($sales_name) > 150) {
            $message = 'Sales name is too long.';
            $message_type = 'danger';
        } else {
            $stmt = $conn->prepare("
                INSERT INTO sales_representatives (sales_name, status)
                VALUES (?, 'Active')
                ON DUPLICATE KEY UPDATE status = 'Active'
            ");
            $stmt->bind_param('s', $sales_name);
            $stmt->execute();
            $message = 'Sales personnel saved.';
        }
    } elseif ($action === 'toggle') {
        $sales_id = (int) ($_POST['sales_id'] ?? 0);
        $stmt = $conn->prepare("
            UPDATE sales_representatives
            SET status = IF(status = 'Active', 'Inactive', 'Active')
            WHERE id = ?
        ");
        $stmt->bind_param('i', $sales_id);
        $stmt->execute();
        $message = 'Sales status updated.';
    }
}

$sales_people = $conn->query("
    SELECT id, sales_name, status, created_at
    FROM sales_representatives
    ORDER BY status = 'Active' DESC, sales_name
");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Sales Personnel</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body{background:#f4f6f9}
        .sales-page{max-width:900px;margin:0 auto;padding:14px 16px 28px}
        .sales-card{border:1px solid #e1e5ea;border-radius:10px;overflow:hidden;box-shadow:0 3px 11px rgba(15,23,42,.07)}
        .sales-card .card-header{padding:10px 14px}
        .sales-card .card-header h1{margin:0;font-size:1rem;font-weight:750}
        .sales-card .card-body{padding:14px}
        .add-sales{display:grid;grid-template-columns:1fr auto;gap:8px;margin-bottom:14px}
        .add-sales .form-control,.add-sales .btn{font-size:.78rem}
        .table{font-size:.75rem}
        .table th{font-size:.67rem;text-transform:uppercase;letter-spacing:.03em;white-space:nowrap}
        .table td,.table th{padding:.48rem .55rem}
        @media(max-width:576px){.sales-page{padding:10px}.add-sales{grid-template-columns:1fr}}
    </style>
</head>
<body>
<main class="sales-page">
    <section class="card sales-card">
        <header class="card-header bg-dark text-white d-flex justify-content-between align-items-center gap-2">
            <h1>Sales Personnel</h1>
            <a href="create_jo.php" class="btn btn-light btn-sm">Back to Create JO</a>
        </header>
        <div class="card-body">
            <?php if ($message !== ''): ?>
                <div class="alert alert-<?= h($message_type) ?> py-2 small"><?= h($message) ?></div>
            <?php endif; ?>

            <form method="post" class="add-sales">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add">
                <input type="text" name="sales_name" class="form-control"
                       maxlength="150" placeholder="Enter sales personnel name" required>
                <button type="submit" class="btn btn-primary">+ Add Sales</button>
            </form>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-dark">
                    <tr><th>Sales Name</th><th>Status</th><th class="text-end">Action</th></tr>
                    </thead>
                    <tbody>
                    <?php while ($sales = $sales_people->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?= h($sales['sales_name']) ?></strong></td>
                            <td>
                                <span class="badge <?= $sales['status'] === 'Active' ? 'bg-success' : 'bg-secondary' ?>">
                                    <?= h($sales['status']) ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <form method="post" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="sales_id" value="<?= (int) $sales['id'] ?>">
                                    <button type="submit" class="btn btn-outline-secondary btn-sm">
                                        <?= $sales['status'] === 'Active' ? 'Deactivate' : 'Activate' ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</main>
</body>
</html>
