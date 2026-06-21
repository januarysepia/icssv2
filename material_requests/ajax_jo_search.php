<?php

include '../auth/auth_check.php';
require_role(['Boss', 'Admin', 'Technical']);
include '../config/database.php';

header('Content-Type: application/json; charset=utf-8');

$search = trim($_GET['q'] ?? '');
if (mb_strlen($search) < 1) {
    echo json_encode([]);
    exit;
}

$term = '%' . $search . '%';
$stmt = $conn->prepare("
    SELECT id, jo_no, project_name, client_name, workflow_status, overall_status
    FROM job_orders
    WHERE jo_no LIKE ?
       OR project_name LIKE ?
       OR client_name LIKE ?
    ORDER BY
        CASE WHEN workflow_status = 'Completed' OR overall_status = 'Completed' THEN 1 ELSE 0 END,
        id DESC
    LIMIT 20
");
$stmt->bind_param('sss', $term, $term, $term);
$stmt->execute();
$result = $stmt->get_result();

$rows = [];
while ($row = $result->fetch_assoc()) {
    $is_completed = $row['workflow_status'] === 'Completed'
        || $row['overall_status'] === 'Completed';

    $rows[] = [
        'id' => (int) $row['id'],
        'jo_no' => $row['jo_no'],
        'project_name' => $row['project_name'],
        'client_name' => $row['client_name'],
        'workflow_status' => $row['workflow_status'],
        'is_completed' => $is_completed,
        'context' => $is_completed ? 'After Delivery / Correction' : 'Ongoing JO',
    ];
}

$stmt->close();
echo json_encode($rows, JSON_UNESCAPED_UNICODE);

