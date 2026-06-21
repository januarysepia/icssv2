<?php

include '../auth/auth_check.php';
require_role(['Boss', 'Admin', 'Technical', 'Supervisor', 'Engineer']);
include '../config/database.php';

header('Content-Type: application/json; charset=utf-8');

$view = $_GET['view'] ?? 'active';
if (!in_array($view, ['active', 'completed'], true)) {
    $view = 'active';
}

$search = trim($_GET['search'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 10;
$is_completed_view = $view === 'completed';
$status_condition = $is_completed_view
    ? "(jo.workflow_status = 'Completed' OR jo.overall_status = 'Completed')"
    : "jo.workflow_status <> 'Completed' AND COALESCE(jo.overall_status, '') <> 'Completed'";

$params = [];
$types = '';
$search_condition = '';

if ($search !== '') {
    $search_condition = "
        AND (
            jo.jo_no LIKE ?
            OR jo.client_name LIKE ?
            OR jo.project_name LIKE ?
            OR jo.engineer_name LIKE ?
        )
    ";
    $term = '%' . $search . '%';
    $params = [$term, $term, $term, $term];
    $types = 'ssss';
}

$count_sql = "
    SELECT COUNT(*) AS total
    FROM job_orders jo
    WHERE $status_condition
    $search_condition
";
$count_stmt = $conn->prepare($count_sql);
if ($params) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total = (int) ($count_stmt->get_result()->fetch_assoc()['total'] ?? 0);
$count_stmt->close();

$total_pages = max(1, (int) ceil($total / $per_page));
$page = min($page, $total_pages);
$offset = ($page - 1) * $per_page;

$sql = "
    SELECT
        jo.*,
        users.fullname AS created_by_name
    FROM job_orders jo
    LEFT JOIN users ON users.id = jo.created_by
    WHERE $status_condition
    $search_condition
";

$sql .= $is_completed_view
    ? " ORDER BY COALESCE(jo.completed_at, jo.created_at) DESC, jo.id DESC"
    : " ORDER BY CASE WHEN jo.due_date < CURDATE() THEN 0 ELSE 1 END, jo.due_date ASC, jo.id DESC";
$sql .= " LIMIT $per_page OFFSET $offset";

$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$role = $_SESSION['system_role'] ?? '';
$rows = [];

while ($row = $result->fetch_assoc()) {
    $rows[] = [
        'id' => (int) $row['id'],
        'jo_no' => $row['jo_no'],
        'client_name' => $row['client_name'],
        'project_name' => $row['project_name'],
        'created_by_name' => $row['created_by_name'] ?: 'System / Unknown',
        'engineer_name' => $row['engineer_name'],
        'sales_name' => $row['sales_name'],
        'release_date' => $row['release_date'],
        'due_date' => $row['due_date'],
        'workflow_status' => $row['workflow_status'],
        'is_overdue' => !$is_completed_view
            && !empty($row['due_date'])
            && $row['due_date'] < date('Y-m-d'),
    ];
}

$stmt->close();

echo json_encode([
    'rows' => $rows,
    'count' => count($rows),
    'total' => $total,
    'page' => $page,
    'per_page' => $per_page,
    'total_pages' => $total_pages,
    'view' => $view,
], JSON_UNESCAPED_UNICODE);
