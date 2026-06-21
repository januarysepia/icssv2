<?php

include '../auth/auth_check.php';
include '../config/database.php';

$user_id = $_SESSION['user_id'];

$notifications = $conn->query("
SELECT *
FROM notifications
WHERE user_id = '$user_id'
ORDER BY id DESC
");

$conn->query("
UPDATE notifications
SET is_read = 1
WHERE user_id = '$user_id'
");

?>

<!DOCTYPE html>
<html>
<head>
    <title>Notifications</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">

<div class="container mt-4">

    <div class="card shadow border-0">

        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Notifications</h4>

            <a href="../dashboard/index.php" class="btn btn-light btn-sm">
                Dashboard
            </a>
        </div>

        <div class="card-body">

            <?php while($row = $notifications->fetch_assoc()){ ?>

                <div class="border rounded p-3 mb-3 bg-white">

                    <h6 class="mb-1">
                        <?php echo $row['title']; ?>
                    </h6>

                    <p class="mb-1">
                        <?php echo $row['message']; ?>
                    </p>

                    <small class="text-muted">
                        <?php echo $row['created_at']; ?>
                    </small>

                    <br>

                    <a href="<?php echo $row['link']; ?>" class="btn btn-primary btn-sm mt-2">
                        Open
                    </a>

                </div>

            <?php } ?>

        </div>

    </div>

</div>

</body>
</html>