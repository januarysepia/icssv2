<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

include 'config/database.php';
require_once 'includes/security.php';

$redirect = safe_local_redirect($_GET['redirect'] ?? null);
if (str_starts_with($redirect, 'icssv2/')) {
    $redirect = substr($redirect, strlen('icssv2/'));
}

/*
An authenticated browser session is shared across tabs.
Do not show the login form again when the user is already signed in.
*/
if (!empty($_SESSION['user_id']) && !empty($_SESSION['system_role'])) {
    header("Location: " . $redirect);
    exit();
}

if(isset($_POST['login'])){

    verify_csrf();

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $redirect = safe_local_redirect($_POST['redirect'] ?? null);
    if (str_starts_with($redirect, 'icssv2/')) {
        $redirect = substr($redirect, strlen('icssv2/'));
    }

    $stmt = $conn->prepare("
        SELECT *
        FROM users
        WHERE username = ?
        AND status = 'Active'
        LIMIT 1
    ");
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    $password_valid = false;

    if ($user) {
        $stored_password = (string) $user['password'];
        $password_valid = password_verify($password, $stored_password);

        // One-time migration for existing legacy plain-text passwords.
        if (!$password_valid && hash_equals($stored_password, $password)) {
            $password_valid = true;
            $new_hash = password_hash($password, PASSWORD_DEFAULT);
            $update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $update->bind_param('si', $new_hash, $user['id']);
            $update->execute();
        } elseif ($password_valid && password_needs_rehash($stored_password, PASSWORD_DEFAULT)) {
            $new_hash = password_hash($password, PASSWORD_DEFAULT);
            $update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $update->bind_param('si', $new_hash, $user['id']);
            $update->execute();
        }
    }

    if($user && $password_valid){

        $login_ip = substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 45);
        $login_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
        $login_user_id = (int) $user['id'];
        $login_success = 1;
        $login_log = $conn->prepare("
            INSERT INTO login_attempts
                (user_id, username_attempted, success, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?)
        ");
        $login_log->bind_param('isiss', $login_user_id, $username, $login_success, $login_ip, $login_agent);
        $login_log->execute();

        session_regenerate_id(true);

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['employee_no'] = $user['employee_no'];
        $_SESSION['fullname'] = $user['fullname'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['system_role'] = $user['system_role'];
        $_SESSION['department_id'] = $user['department_id'];
        $_SESSION['position'] = $user['position'];

        header("Location: " . $redirect);
        exit();

    }else{

        $login_ip = substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 45);
        $login_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
        $login_user_id = $user ? (int) $user['id'] : null;
        $login_success = 0;
        $login_log = $conn->prepare("
            INSERT INTO login_attempts
                (user_id, username_attempted, success, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?)
        ");
        $login_log->bind_param('isiss', $login_user_id, $username, $login_success, $login_ip, $login_agent);
        $login_log->execute();

        $error = "Invalid username or password.";
    }
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>ICSS v2 Login</title>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">
</head>

<body class="bg-light">

<div class="container mt-5">

    <div class="row justify-content-center">

        <div class="col-md-4">

            <div class="card shadow border-0">

                <div class="card-header bg-dark text-white text-center">
                    <h4 class="mb-0">ICSS v2 ERP Login</h4>
                </div>

                <div class="card-body">

                    <?php if(isset($error)){ ?>

                        <div class="alert alert-danger">
                            <?php echo $error; ?>
                        </div>

                    <?php } ?>

                    <form method="POST">
                        <?php echo csrf_field(); ?>

                        <input type="hidden"
                               name="redirect"
                               value="<?php echo h($redirect); ?>">

                        <div class="mb-3">
                            <label class="fw-bold">Username</label>
                            <input type="text"
                                   name="username"
                                   class="form-control"
                                   required>
                        </div>

                        <div class="mb-3">
                            <label class="fw-bold">Password</label>
                            <input type="password"
                                   name="password"
                                   class="form-control"
                                   required>
                        </div>

                        <button type="submit"
                                name="login"
                                class="btn btn-dark w-100">
                            Login
                        </button>

                    </form>

                </div>

            </div>

        </div>

    </div>

</div>

</body>
</html>
