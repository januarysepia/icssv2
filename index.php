<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}

if (!empty($_SESSION['user_id']) && !empty($_SESSION['system_role'])) {
    header('Location: dashboard/index.php');
    exit();
}

header('Location: login.php');
exit();

