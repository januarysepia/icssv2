<?php

include '../auth/auth_check.php';

require_role([
    'Boss',
    'Admin',
    'Technical'
]);
include '../includes/create_notification.php';
include '../config/database.php';
include '../includes/activity_logger.php';
include '../includes/jo_audit.php';
include '../includes/jo_number.php';

require_post();
verify_csrf();

/*
FORM DATA
*/

$client_name = trim($_POST['client_name'] ?? '');
$project_name = trim($_POST['project_name'] ?? '');
$engineer_name = trim($_POST['engineer_name'] ?? '');
$sales_name = trim($_POST['sales_name'] ?? '');
$release_date = $_POST['release_date'] ?? '';
$due_date = $_POST['due_date'] ?? '';

if ($client_name === '' || $project_name === '' || $engineer_name === '' || $sales_name === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $release_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $due_date)) {
    exit('Invalid job order details.');
}

if ($due_date < $release_date) {
    exit('Due date cannot be earlier than the release date.');
}

$engineer_check = $conn->prepare("
    SELECT id
    FROM users
    WHERE fullname = ?
      AND system_role = 'Engineer'
      AND status = 'Active'
    LIMIT 1
");
$engineer_check->bind_param('s', $engineer_name);
$engineer_check->execute();
if (!$engineer_check->get_result()->fetch_assoc()) {
    exit('Please select a valid active engineer.');
}

$sales_check = $conn->prepare("
    SELECT id
    FROM sales_representatives
    WHERE sales_name = ?
      AND status = 'Active'
    LIMIT 1
");
$sales_check->bind_param('s', $sales_name);
$sales_check->execute();
if (!$sales_check->get_result()->fetch_assoc()) {
    exit('Please select a valid active sales personnel.');
}

$created_by = $_SESSION['user_id'];
$creator_name = $_SESSION['fullname'] ?? $_SESSION['name'] ?? 'User';

/*
INSERT JO
*/

$jo_no = '';
$jo_id = 0;
$max_attempts = 5;

for ($attempt = 1; $attempt <= $max_attempts; $attempt++) {
    $jo_no = getNextJobOrderNumber($conn);

    $insert = $conn->prepare("
        INSERT INTO job_orders
        (
            jo_no, client_name, project_name, engineer_name, sales_name,
            release_date, due_date, created_by, workflow_status, overall_status
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'For Validation', 'Pending')
    ");
    $insert->bind_param(
        'sssssssi',
        $jo_no,
        $client_name,
        $project_name,
        $engineer_name,
        $sales_name,
        $release_date,
        $due_date,
        $created_by
    );

    try {
        $insert->execute();
        $jo_id = $conn->insert_id;
        break;
    } catch (mysqli_sql_exception $e) {
        if ((int) $e->getCode() !== 1062 || $attempt === $max_attempts) {
            error_log('Unable to create JO: ' . $e->getMessage());
            http_response_code(500);
            exit('Unable to create the job order. Please try again.');
        }
    }
}

/*
UPLOAD DRAWING FILE
*/

if(isset($_FILES['drawing_file']) && $_FILES['drawing_file']['name'] != ''){

    $file = $_FILES['drawing_file'];

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        exit('Drawing upload failed.');
    }

    if (($file['size'] ?? 0) <= 0 || $file['size'] > 10 * 1024 * 1024) {
        exit('Drawing must be no larger than 10 MB.');
    }

    $allowed_types = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
    ];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);

    if (!isset($allowed_types[$mime])) {
        exit('Only valid PDF, JPG, and PNG drawings are allowed.');
    }

    $new_name = bin2hex(random_bytes(16)) . '.' . $allowed_types[$mime];
    $upload_dir = __DIR__ . '/../uploads/drawings';

    if (!is_dir($upload_dir) && !mkdir($upload_dir, 0775, true)) {
        exit('Unable to prepare the drawing upload folder.');
    }

    $upload_path = $upload_dir . DIRECTORY_SEPARATOR . $new_name;

    if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
        exit('Unable to store the drawing.');
    }

    $original_name = basename($file['name']);
    $version_no = 'Original';
    $attachment = $conn->prepare("
        INSERT INTO job_order_attachments
            (jo_id, file_name, original_name, file_type, version_no, uploaded_by)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $attachment->bind_param(
        'issssi',
        $jo_id,
        $new_name,
        $original_name,
        $mime,
        $version_no,
        $created_by
    );

    if (!$attachment->execute()) {
        @unlink($upload_path);
        exit('Unable to save the drawing attachment.');
    }
}

/*
ACTIVITY LOG
*/

logActivity(
    $conn,
    'Job Order',
    $creator_name . ' created ' . $jo_no . ' for project ' . $project_name,
    $created_by
);

/*
JO AUDIT LOG
*/

logJOAudit(
    $conn,
    $jo_id,
    'JO Created',
    $created_by,
    'Job order created by ' . $creator_name
);
/*
NOTIFY ALL ACTIVE SUPERVISORS
*/

$supervisors = $conn->query("
SELECT id
FROM users
WHERE system_role = 'Supervisor'
AND status = 'Active'
");

while($sup = $supervisors->fetch_assoc()){

    createNotification(
        $conn,
        $sup['id'],
        'New Job Order',
        'A new Job Order ' . $jo_no . ' has been created and is waiting for validation.',
        '../job_orders/view_jo.php?id=' . $jo_id
    );
}
echo "
<script>
    alert('Job Order Created Successfully. JO No: $jo_no');
    window.location='view_jo.php?id=$jo_id';
</script>
";

?>
