<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}

require_once __DIR__ . '/../includes/security.php';

if(!isset($_SESSION['user_id'])){

    $current_url = $_SERVER['REQUEST_URI'];

    header("Location: ../login.php?redirect=" . urlencode($current_url));
    exit();
}

function require_role($allowed_roles){

    if(!isset($_SESSION['system_role'])){
        header("Location: ../login.php");
        exit();
    }

    if(!in_array($_SESSION['system_role'], $allowed_roles, true)){
        http_response_code(403);
        die("Access Denied");
    }
}

?>
