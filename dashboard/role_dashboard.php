<?php

$role = $_SESSION['system_role'] ?? $_SESSION['role'] ?? '';
$user_id = intval($_SESSION['user_id'] ?? 0);
$fullname = $_SESSION['fullname'] ?? 'User';

function dashCount(mysqli $conn, string $sql): int {
    $result = $conn->query($sql);
    return $result ? (int) ($result->fetch_assoc()['total'] ?? 0) : 0;
}

function dashRows(mysqli $conn, string $sql): array {
    $result = $conn->query($sql);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function dashBadge(string $status): string {
    if (preg_match('/^(Rev\.|Revision)/i', $status)) return 'warning text-dark';
    if (in_array($status, ['Failed','Boss Rejected','Overdue','Returned to Production'], true)) return 'danger';
    if (in_array($status, ['Completed','Received','Passed','Delivered','Boss Approved','Issued'], true)) return 'success';
    if (in_array($status, ['Pending','Pending Review','Pending QA','For Validation','For Boss Approval','Acknowledged'], true)) return 'warning text-dark';
    if (in_array($status, ['In Progress','In Production','Preparing','Dispatched','Waiting Delivery'], true)) return 'primary';
    return 'secondary';
}

$title = 'My Dashboard';
$subtitle = 'Your current priorities and recent activity.';
$stats = [];
$actions = [];
$panels = [];

if ($role === 'Admin') {
    $title = 'Administration Dashboard';
    $subtitle = 'Manage user accounts, employees, access roles, and system activity.';
    $stats = [
        ['Total Users', dashCount($conn, "SELECT COUNT(*) total FROM users"), 'primary', '../users/employee_list.php'],
        ['Active Users', dashCount($conn, "SELECT COUNT(*) total FROM users WHERE status='Active'"), 'success', '../users/employee_list.php'],
        ['Inactive Users', dashCount($conn, "SELECT COUNT(*) total FROM users WHERE status='Inactive'"), 'danger', '../users/employee_list.php'],
        ['Departments', dashCount($conn, "SELECT COUNT(*) total FROM departments WHERE status='Active'"), 'info', '../users/departments.php'],
        ['Failed Logins Today', dashCount($conn, "SELECT COUNT(*) total FROM login_attempts WHERE success=0 AND DATE(attempted_at)=CURDATE()"), 'warning', '../users/login_history.php?filter=failed'],
    ];
    $actions = [
        ['User Management', '../users/employee_list.php'],
        ['+ Add User', '../users/create_user.php'],
        ['Departments', '../users/departments.php'],
        ['Login History', '../users/login_history.php'],
        ['Activity Logs', 'activity_logs.php'],
        ['Download Backup', 'download_database_backup.php'],
    ];
    $panels[] = ['Recent Users', 'No users found.', dashRows($conn, "
        SELECT id,employee_no reference,fullname detail,created_at meta,
        CONCAT(system_role,' · ',status) status
        FROM users
        ORDER BY id DESC
        LIMIT 8
    "), '../users/employee_list.php?user_id='];
    $panels[] = ['Recent System Activity', 'No recent activity.', dashRows($conn, "
        SELECT al.id,al.module_name reference,al.activity detail,al.created_at meta,
        COALESCE(u.fullname,'System') status
        FROM activity_logs al
        LEFT JOIN users u ON u.id=al.user_id
        ORDER BY al.id DESC
        LIMIT 10
    "), ''];
} elseif ($role === 'Boss') {
    $title = 'Executive Dashboard';
    $subtitle = 'Cross-department operational overview.';
    $stats = [
        ['Active Job Orders', dashCount($conn, "SELECT COUNT(*) total FROM job_orders WHERE workflow_status<>'Completed'"), 'primary', '../job_orders/jo_list.php'],
        ['Delayed Job Orders', dashCount($conn, "SELECT COUNT(*) total FROM job_orders WHERE due_date<CURDATE() AND workflow_status<>'Completed'"), 'danger', '../job_orders/jo_list.php'],
        ['Pending Purchase Approval', dashCount($conn, "SELECT COUNT(*) total FROM purchase_requests WHERE status='For Boss Approval'"), 'warning', '../purchase_requests/purchase_list.php'],
        ['Low / Out of Stock', dashCount($conn, "SELECT COUNT(*) total FROM inventory_items WHERE quantity<=minimum_stock"), 'danger', '../inventory/inventory_list.php'],
        ['Pending QA', dashCount($conn, "SELECT COUNT(*) total FROM qaqc_tasks WHERE status='Pending QA'"), 'info', '../qaqc/qaqc_list.php'],
        ['Active Deliveries', dashCount($conn, "SELECT COUNT(*) total FROM logistics_tasks WHERE status IN ('Pending Logistics','Preparing','Dispatched')"), 'primary', '../logistics/logistics_list.php'],
    ];
    $actions = [
        ['Job Orders','../job_orders/jo_list.php'], ['Purchase Requests','../purchase_requests/purchase_list.php'],
        ['Production Monitoring','../monitoring/dashboard.php'], ['Activity Logs','activity_logs.php'],
    ];
    $panels[] = ['Delayed Job Orders', 'No delayed job orders.', dashRows($conn, "
        SELECT id,jo_no reference,project_name detail,due_date meta,workflow_status status
        FROM job_orders WHERE due_date<CURDATE() AND workflow_status<>'Completed'
        ORDER BY due_date LIMIT 8
    "), '../job_orders/view_jo.php?id='];
    $panels[] = ['Recent System Activity', 'No recent activity.', dashRows($conn, "
        SELECT al.id,al.module_name reference,al.activity detail,al.created_at meta,COALESCE(u.fullname,'System') status
        FROM activity_logs al LEFT JOIN users u ON u.id=al.user_id ORDER BY al.id DESC LIMIT 8
    "), ''];
} elseif ($role === 'Technical') {
    $title = 'Technical Dashboard';
    $subtitle = 'Job orders and material requests requiring your attention.';
    $stats = [
        ['Active Job Orders', dashCount($conn, "SELECT COUNT(*) total FROM job_orders WHERE workflow_status<>'Completed'"), 'primary', '../job_orders/jo_list.php'],
        ['For Validation', dashCount($conn, "SELECT COUNT(*) total FROM job_orders WHERE workflow_status='For Validation'"), 'warning', '../job_orders/jo_list.php'],
        ['Delayed Job Orders', dashCount($conn, "SELECT COUNT(*) total FROM job_orders WHERE due_date<CURDATE() AND workflow_status<>'Completed'"), 'danger', '../job_orders/jo_list.php'],
        ['My Pending MRF', dashCount($conn, "SELECT COUNT(*) total FROM material_requests WHERE requested_by='$user_id' AND status NOT IN ('Completed','Cancelled')"), 'info', '../material_requests/request_list.php'],
    ];
    $actions = [
        ['+ Create Job Order','../job_orders/create_jo.php'], ['+ Create Material Request','../material_requests/create_request.php'],
        ['Job Orders','../job_orders/jo_list.php'], ['Material Requests','../material_requests/request_list.php'],
    ];
    $panels[] = ['Recent Active Job Orders', 'No active job orders.', dashRows($conn, "
        SELECT id,jo_no reference,project_name detail,due_date meta,workflow_status status
        FROM job_orders WHERE workflow_status<>'Completed' ORDER BY id DESC LIMIT 8
    "), '../job_orders/view_jo.php?id='];
    $panels[] = ['My Material Requests', 'No material requests.', dashRows($conn, "
        SELECT mr.id,mr.request_no reference,COALESCE(jo.jo_no,'No JO') detail,mr.request_date meta,mr.status
        FROM material_requests mr LEFT JOIN job_orders jo ON jo.id=mr.jo_id
        WHERE mr.requested_by='$user_id' ORDER BY mr.id DESC LIMIT 8
    "), '../material_requests/view_request.php?id='];
} elseif ($role === 'Supervisor') {
    $title = 'Supervisor Dashboard';
    $subtitle = 'Workflow setup, active production, and rework priorities.';
    $stats = [
        ['Needs Workflow Setup', dashCount($conn, "SELECT COUNT(*) total FROM job_orders WHERE workflow_status='For Validation'"), 'warning', '../supervisor/work_queue.php'],
        ['Active Production', dashCount($conn, "SELECT COUNT(*) total FROM (SELECT jo.id FROM job_orders jo JOIN job_workflow_steps jws ON jws.jo_id=jo.id WHERE jo.workflow_status<>'Completed' GROUP BY jo.id HAVING SUM(jws.status<>'Completed')>0) x"), 'primary', '../supervisor/work_queue.php'],
        ['Needs Rework Action', dashCount($conn, "SELECT COUNT(*) total FROM qaqc_tasks qt JOIN job_orders jo ON jo.id=qt.jo_id LEFT JOIN rework_tasks rt ON rt.qaqc_task_id=qt.id WHERE qt.status='Failed' AND jo.workflow_status<>'Completed' AND rt.id IS NULL"), 'danger', '../supervisor/work_queue.php'],
        ['Delayed Active JO', dashCount($conn, "SELECT COUNT(*) total FROM job_orders WHERE due_date<CURDATE() AND workflow_status<>'Completed'"), 'danger', '../job_orders/jo_list.php'],
    ];
    $actions = [
        ['Supervisor Work Queue','../supervisor/work_queue.php'], ['Production Monitoring','../monitoring/dashboard.php'],
        ['Active Job Orders','../job_orders/jo_list.php'], ['Activity Logs','activity_logs.php'],
    ];
    $panels[] = ['Waiting for Workflow Setup', 'No JO needs workflow setup.', dashRows($conn, "
        SELECT id,jo_no reference,project_name detail,due_date meta,workflow_status status
        FROM job_orders WHERE workflow_status='For Validation' ORDER BY due_date,id DESC LIMIT 8
    "), '../supervisor/validate_jo.php?id='];
    $panels[] = ['Active Production Workflows', 'No active workflows.', dashRows($conn, "
        SELECT jo.id,jo.jo_no reference,jo.project_name detail,jo.due_date meta,
        CONCAT(SUM(jws.status='Completed'),'/',COUNT(jws.id),' steps') status
        FROM job_orders jo JOIN job_workflow_steps jws ON jws.jo_id=jo.id
        WHERE jo.workflow_status<>'Completed' GROUP BY jo.id
        HAVING SUM(jws.status<>'Completed')>0 ORDER BY jo.due_date LIMIT 8
    "), '../job_orders/view_jo.php?id='];
} elseif ($role === 'Purchasing') {
    $title = 'Purchasing Dashboard';
    $subtitle = 'Material requests, purchasing, inventory, and asset alerts.';
    $stats = [
        ['MRF Pending Review', dashCount($conn, "SELECT COUNT(*) total FROM material_requests WHERE status='Pending Review'"), 'warning', '../material_requests/request_list.php'],
        ['Items To Purchase', dashCount($conn, "SELECT COUNT(*) total FROM material_request_items WHERE item_status='To Purchase'"), 'danger', '../purchase_requests/create_purchase.php'],
        ['For Boss Approval', dashCount($conn, "SELECT COUNT(*) total FROM purchase_requests WHERE status='For Boss Approval'"), 'warning', '../purchase_requests/purchase_list.php'],
        ['Ready to Receive', dashCount($conn, "SELECT COUNT(*) total FROM purchase_requests WHERE status='Boss Approved'"), 'primary', '../purchase_requests/purchase_list.php'],
        ['Low / Out of Stock', dashCount($conn, "SELECT COUNT(*) total FROM inventory_items WHERE quantity<=minimum_stock"), 'danger', '../inventory/inventory_list.php'],
        ['Overdue Assets', dashCount($conn, "SELECT COUNT(*) total FROM borrow_transactions WHERE status='Borrowed' AND due_date<NOW()"), 'danger', '../inventory/asset_dashboard.php'],
    ];
    $actions = [
        ['Material Requests','../material_requests/request_list.php'], ['+ Create Purchase','../purchase_requests/create_purchase.php'],
        ['Purchase Requests','../purchase_requests/purchase_list.php'], ['Inventory','../inventory/inventory_list.php'],
        ['Supplier Catalog','../purchase_requests/supplier_catalog.php'], ['Compare Prices','../purchase_requests/price_comparison.php'],
        ['Asset Module','../inventory/asset_dashboard.php'],
    ];
    $panels[] = ['Material Requests Requiring Action', 'No MRF requires action.', dashRows($conn, "
        SELECT mr.id,mr.request_no reference,COALESCE(jo.jo_no,'No JO') detail,mr.request_date meta,mr.status
        FROM material_requests mr LEFT JOIN job_orders jo ON jo.id=mr.jo_id
        WHERE mr.status IN ('Pending Review','Pending Purchase','Approved - To Purchase','Partially Approved')
        ORDER BY mr.id DESC LIMIT 8
    "), '../material_requests/view_request.php?id='];
    $panels[] = ['Recent Purchase Requests', 'No purchase requests.', dashRows($conn, "
        SELECT id,purchase_no reference,COALESCE(remarks,'No remarks') detail,created_at meta,status
        FROM purchase_requests ORDER BY id DESC LIMIT 8
    "), '../purchase_requests/view_purchase.php?id='];
} elseif ($role === 'Engineer') {
    $title = 'QA/QC Dashboard';
    $subtitle = 'Your assigned inspections and QA results.';
    $stats = [
        ['My Pending QA', dashCount($conn, "SELECT COUNT(*) total FROM qaqc_tasks WHERE assigned_engineer_id='$user_id' AND status='Pending QA'"), 'warning', '../qaqc/qaqc_list.php'],
        ['QA Passed', dashCount($conn, "SELECT COUNT(*) total FROM qaqc_tasks WHERE assigned_engineer_id='$user_id' AND status='Passed'"), 'success', '../qaqc/qaqc_list.php'],
        ['QA Failed', dashCount($conn, "SELECT COUNT(*) total FROM qaqc_tasks WHERE assigned_engineer_id='$user_id' AND status='Failed'"), 'danger', '../qaqc/qaqc_list.php'],
    ];
    $actions = [['QA/QC Tasks','../qaqc/qaqc_list.php'],['Job Orders','../job_orders/jo_list.php']];
    $panels[] = ['My QA Assignments', 'No QA assignments.', dashRows($conn, "
        SELECT qt.id,COALESCE(jo.jo_no,'No JO') reference,jo.project_name detail,qt.created_at meta,qt.status
        FROM qaqc_tasks qt LEFT JOIN job_orders jo ON jo.id=qt.jo_id
        WHERE qt.assigned_engineer_id='$user_id' ORDER BY qt.id DESC LIMIT 10
    "), '../qaqc/qaqc_list.php?task_id='];
} elseif ($role === 'Logistics') {
    $title = 'Logistics Dashboard';
    $subtitle = 'Delivery preparation and dispatch pipeline.';
    $stats = [
        ['Pending Logistics', dashCount($conn, "SELECT COUNT(*) total FROM logistics_tasks WHERE status='Pending Logistics'"), 'warning', '../logistics/logistics_list.php'],
        ['Preparing', dashCount($conn, "SELECT COUNT(*) total FROM logistics_tasks WHERE status='Preparing'"), 'primary', '../logistics/logistics_list.php'],
        ['Dispatched', dashCount($conn, "SELECT COUNT(*) total FROM logistics_tasks WHERE status='Dispatched'"), 'info', '../logistics/logistics_list.php'],
        ['Delivered', dashCount($conn, "SELECT COUNT(*) total FROM logistics_tasks WHERE status IN ('Delivered','Completed')"), 'success', '../logistics/logistics_list.php'],
    ];
    $actions = [['Logistics Tasks','../logistics/logistics_list.php']];
    $panels[] = ['Delivery Queue', 'No delivery tasks.', dashRows($conn, "
        SELECT lt.id,COALESCE(jo.jo_no,'No JO') reference,jo.project_name detail,
        COALESCE(lt.delivery_date,lt.created_at) meta,lt.status
        FROM logistics_tasks lt LEFT JOIN job_orders jo ON jo.id=lt.jo_id
        WHERE lt.status<>'Completed' ORDER BY lt.id DESC LIMIT 10
    "), '../logistics/logistics_list.php?task_id='];
} elseif ($role === 'Production') {
    $title = 'Production Dashboard';
    $subtitle = 'Only your assigned production work is shown.';
    $stats = [
        ['Pending', dashCount($conn, "SELECT COUNT(*) total FROM job_workflow_steps WHERE assigned_user_id='$user_id' AND status='Pending'"), 'warning', '../workflow/my_tasks.php'],
        ['Acknowledged', dashCount($conn, "SELECT COUNT(*) total FROM job_workflow_steps WHERE assigned_user_id='$user_id' AND status='Acknowledged'"), 'info', '../workflow/my_tasks.php'],
        ['In Progress', dashCount($conn, "SELECT COUNT(*) total FROM job_workflow_steps WHERE assigned_user_id='$user_id' AND status='In Progress'"), 'primary', '../workflow/my_tasks.php'],
        ['Completed Today', dashCount($conn, "SELECT COUNT(*) total FROM job_workflow_steps WHERE assigned_user_id='$user_id' AND status='Completed' AND DATE(completed_at)=CURDATE()"), 'success', '../workflow/my_tasks.php'],
    ];
    $actions = [['My Production Tasks','../workflow/my_tasks.php']];
    $panels[] = ['My Active Tasks', 'No active production tasks.', dashRows($conn, "
        SELECT jws.id,COALESCE(jo.jo_no,'No JO') reference,
        CONCAT(jo.project_name,' · ',d.department_name) detail,jo.due_date meta,jws.status
        FROM job_workflow_steps jws JOIN job_orders jo ON jo.id=jws.jo_id
        LEFT JOIN departments d ON d.id=jws.department_id
        WHERE jws.assigned_user_id='$user_id' AND jws.status<>'Completed' AND jo.workflow_status<>'Completed'
        ORDER BY FIELD(jws.status,'In Progress','Acknowledged','Pending'),jo.due_date LIMIT 10
    "), '../workflow/task_detail.php?id='];
}

/*
Drawing revisions must be visible immediately to departments that rely on the drawing.
Production only sees revisions for JOs currently assigned to that employee.
*/
if (in_array($role, ['Technical', 'Supervisor', 'Engineer', 'Production'], true)) {
    if ($role === 'Production') {
        $revision_count_sql = "
            SELECT COUNT(DISTINCT joa.id) total
            FROM job_order_attachments joa
            INNER JOIN job_orders jo ON jo.id = joa.jo_id
            WHERE COALESCE(joa.version_no, 'Original') <> 'Original'
              AND jo.workflow_status <> 'Completed'
              AND NOT EXISTS (
                  SELECT 1 FROM drawing_revision_views drv
                  WHERE drv.attachment_id = joa.id
                    AND drv.user_id = '$user_id'
              )
              AND EXISTS (
                  SELECT 1 FROM job_workflow_steps jws
                  WHERE jws.jo_id = jo.id
                    AND jws.assigned_user_id = '$user_id'
              )
        ";
        $revision_rows_sql = "
            SELECT
                (
                    SELECT jws.id
                    FROM job_workflow_steps jws
                    WHERE jws.jo_id = jo.id
                      AND jws.assigned_user_id = '$user_id'
                    ORDER BY FIELD(jws.status,'In Progress','Acknowledged','Pending','Completed'), jws.id
                    LIMIT 1
                ) id,
                jo.jo_no reference,
                CONCAT(jo.project_name, ' · ', COALESCE(NULLIF(joa.revision_notes,''), 'Revised drawing uploaded')) detail,
                CONCAT(joa.created_at, ' · ', COALESCE(u.fullname,'System')) meta,
                COALESCE(joa.version_no,'Revision') status
            FROM job_order_attachments joa
            INNER JOIN job_orders jo ON jo.id = joa.jo_id
            LEFT JOIN users u ON u.id = joa.uploaded_by
            WHERE COALESCE(joa.version_no, 'Original') <> 'Original'
              AND jo.workflow_status <> 'Completed'
              AND NOT EXISTS (
                  SELECT 1 FROM drawing_revision_views drv
                  WHERE drv.attachment_id = joa.id
                    AND drv.user_id = '$user_id'
              )
              AND EXISTS (
                  SELECT 1 FROM job_workflow_steps jws
                  WHERE jws.jo_id = jo.id
                    AND jws.assigned_user_id = '$user_id'
              )
            ORDER BY joa.id DESC
            LIMIT 8
        ";
        $revision_panel_link = '../workflow/task_detail.php?id=';
    } else {
        $revision_count_sql = "
            SELECT COUNT(*) total
            FROM job_order_attachments joa
            INNER JOIN job_orders jo ON jo.id = joa.jo_id
            WHERE COALESCE(joa.version_no, 'Original') <> 'Original'
              AND jo.workflow_status <> 'Completed'
              AND NOT EXISTS (
                  SELECT 1 FROM drawing_revision_views drv
                  WHERE drv.attachment_id = joa.id
                    AND drv.user_id = '$user_id'
              )
        ";
        $revision_rows_sql = "
            SELECT
                jo.id,
                jo.jo_no reference,
                CONCAT(jo.project_name, ' · ', COALESCE(NULLIF(joa.revision_notes,''), 'Revised drawing uploaded')) detail,
                CONCAT(joa.created_at, ' · ', COALESCE(u.fullname,'System')) meta,
                COALESCE(joa.version_no,'Revision') status
            FROM job_order_attachments joa
            INNER JOIN job_orders jo ON jo.id = joa.jo_id
            LEFT JOIN users u ON u.id = joa.uploaded_by
            WHERE COALESCE(joa.version_no, 'Original') <> 'Original'
              AND jo.workflow_status <> 'Completed'
              AND NOT EXISTS (
                  SELECT 1 FROM drawing_revision_views drv
                  WHERE drv.attachment_id = joa.id
                    AND drv.user_id = '$user_id'
              )
            ORDER BY joa.id DESC
            LIMIT 8
        ";
        $revision_panel_link = '../job_orders/view_jo.php?id=';
    }

    $revision_count = dashCount($conn, $revision_count_sql);
    $stats[] = ['Drawing Revisions', $revision_count, 'warning', '../job_orders/drawing_revisions.php'];
    if ($revision_count > 0) {
        array_unshift($panels, [
            '⚠ Recent Drawing Revisions',
            '',
            dashRows($conn, $revision_rows_sql),
            $revision_panel_link
        ]);
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>ICSS v2 Dashboard</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body{background:#f3f5f8;color:#111827}.dashboard-content{max-width:1680px;margin:0 auto;padding:18px 22px 32px}
        .welcome{display:flex;justify-content:space-between;align-items:center;gap:18px;padding:18px 20px;margin-bottom:16px;color:#fff;border-radius:14px;background:linear-gradient(135deg,#111827,#24364f);box-shadow:0 5px 18px rgba(15,23,42,.16)}
        .welcome h1{margin:0;font-size:1.4rem;font-weight:750}.welcome p{margin:4px 0 0;color:#d1d5db;font-size:.84rem}.role-chip{padding:7px 11px;border-radius:999px;background:rgba(255,255,255,.13);font-size:.76rem;font-weight:700}
        .quick-actions{display:flex;flex-wrap:wrap;gap:7px;margin-bottom:16px}.quick-actions a{padding:7px 11px;color:#374151;background:#fff;border:1px solid #e5e7eb;border-radius:8px;text-decoration:none;font-size:.78rem;font-weight:650;box-shadow:0 2px 6px rgba(0,0,0,.04)}.quick-actions a:hover{color:#fff;background:#111827;border-color:#111827}
        .stat-grid{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:10px;margin-bottom:16px}.stat-card{position:relative;overflow:hidden;min-height:104px;padding:13px 14px;color:inherit;background:#fff;border:1px solid #e7eaf0;border-radius:11px;text-decoration:none;box-shadow:0 2px 8px rgba(15,23,42,.05)}.stat-card:hover{transform:translateY(-1px);box-shadow:0 5px 14px rgba(15,23,42,.09)}
        .stat-label{color:#6b7280;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.035em}.stat-value{margin-top:9px;font-size:1.65rem;line-height:1;font-weight:780}.stat-card:after{content:"";position:absolute;left:0;right:0;bottom:0;height:4px;background:#6c757d}.tone-primary:after{background:#0d6efd}.tone-danger:after{background:#dc3545}.tone-warning:after{background:#ffc107}.tone-success:after{background:#198754}.tone-info:after{background:#0dcaf0}
        .panel-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.dashboard-panel{background:#fff;border:1px solid #e7eaf0;border-radius:12px;box-shadow:0 2px 8px rgba(15,23,42,.05);overflow:hidden}.dashboard-panel.revision-panel{border-color:#f59e0b;box-shadow:0 3px 12px rgba(245,158,11,.14)}.revision-panel .panel-header{background:#fffbeb}.panel-header{display:flex;justify-content:space-between;align-items:center;padding:11px 14px;border-bottom:1px solid #e7eaf0}.panel-header h2{margin:0;font-size:.92rem;font-weight:750}.panel-body{padding:0 14px}
        .feed-row{display:grid;grid-template-columns:130px minmax(0,1fr) auto;gap:12px;align-items:center;padding:10px 0;border-bottom:1px solid #eef0f3;font-size:.78rem}.feed-row:last-child{border-bottom:0}.feed-reference{font-weight:750}.feed-detail{color:#4b5563;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.feed-meta{color:#6b7280;font-size:.7rem}.feed-action{display:flex;align-items:center;gap:8px;white-space:nowrap}.feed-action .badge{font-size:.65rem}.feed-action a{font-size:.7rem;padding:3px 7px}.empty-state{padding:28px;text-align:center;color:#6b7280;font-size:.82rem}
        @media(max-width:1200px){.stat-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}@media(max-width:850px){.dashboard-content{padding:14px 12px 26px}.welcome{align-items:flex-start;flex-direction:column;padding:15px}.panel-grid{grid-template-columns:1fr}.feed-row{grid-template-columns:105px minmax(0,1fr)}.feed-action{grid-column:1/-1;justify-content:flex-end}}@media(max-width:520px){.stat-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
        html[data-theme="dark"] body{background:#151515;color:#ededed}
        html[data-theme="dark"] .welcome{background:linear-gradient(135deg,#242424,#333);box-shadow:0 5px 18px rgba(0,0,0,.28)}
        html[data-theme="dark"] .quick-actions a,
        html[data-theme="dark"] .stat-card,
        html[data-theme="dark"] .dashboard-panel{color:#ededed;background:#202020;border-color:#3a3a3a;box-shadow:0 2px 8px rgba(0,0,0,.2)}
        html[data-theme="dark"] .quick-actions a:hover{background:#363636;border-color:#505050}
        html[data-theme="dark"] .stat-label,
        html[data-theme="dark"] .feed-meta,
        html[data-theme="dark"] .empty-state{color:#94a3b8}
        html[data-theme="dark"] .feed-reference{color:#f8fafc}
        html[data-theme="dark"] .feed-detail{color:#cbd5e1}
        html[data-theme="dark"] .panel-header,
        html[data-theme="dark"] .feed-row{border-color:#2c3a50}
        html[data-theme="dark"] .dashboard-panel.revision-panel{border-color:#a16207}
        html[data-theme="dark"] .revision-panel .panel-header{background:#3b2b10}
        html[data-theme="dark"] .panel-header .badge{color:#ededed!important;background:#303030!important}
        html[data-theme="dark"] .feed-action .btn-outline-dark{
            color:#fff!important;
            background:#334155!important;
            border-color:#64748b!important;
            font-weight:700
        }
        html[data-theme="dark"] .feed-action .btn-outline-dark:hover{
            background:#2563eb!important;
            border-color:#60a5fa!important
        }
    </style>
</head>
<body class="dashboard-home">
<div class="content-wrapper" style="margin-left:0;width:100%;">
<?php include 'header.php'; ?>
<main class="dashboard-content">
    <section class="welcome">
        <div><h1><?= h($title) ?></h1><p>Good day, <?= h($fullname) ?>. <?= h($subtitle) ?></p></div>
        <div class="role-chip"><?= h($role) ?></div>
    </section>
    <?php if($actions): ?><nav class="quick-actions">
        <?php foreach($actions as $action): ?><a href="<?= h($action[1]) ?>"><?= h($action[0]) ?></a><?php endforeach; ?>
    </nav><?php endif; ?>
    <section class="stat-grid">
        <?php foreach($stats as $stat): ?><a href="<?= h($stat[3]) ?>" class="stat-card tone-<?= h($stat[2]) ?>">
            <div class="stat-label"><?= h($stat[0]) ?></div><div class="stat-value"><?= number_format($stat[1]) ?></div>
        </a><?php endforeach; ?>
    </section>
    <section class="panel-grid">
        <?php foreach($panels as $panel): ?><article class="dashboard-panel <?= str_contains($panel[0], 'Drawing Revisions') ? 'revision-panel' : '' ?>">
            <header class="panel-header"><h2><?= h($panel[0]) ?></h2><span class="badge bg-light text-dark"><?= count($panel[2]) ?></span></header>
            <div class="panel-body">
                <?php if($panel[2]): foreach($panel[2] as $row): ?><div class="feed-row">
                    <div class="feed-reference"><?= h($row['reference'] ?: '-') ?></div>
                    <div><div class="feed-detail" title="<?= h($row['detail'] ?: '-') ?>"><?= h($row['detail'] ?: '-') ?></div><div class="feed-meta"><?= h($row['meta'] ?: '-') ?></div></div>
                    <div class="feed-action"><span class="badge bg-<?= dashBadge((string)$row['status']) ?>"><?= h($row['status'] ?: '-') ?></span>
                    <?php if($panel[3] !== ''): ?><a href="<?= h($panel[3].(int)$row['id']) ?>" class="btn btn-outline-dark btn-sm">Open</a><?php endif; ?></div>
                </div><?php endforeach; else: ?><div class="empty-state"><?= h($panel[1]) ?></div><?php endif; ?>
            </div>
        </article><?php endforeach; ?>
    </section>
</main></div></body></html>
