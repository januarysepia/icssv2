<?php

function createNotification(
    $conn,
    $user_id,
    $title,
    $message,
    $link = '#',
    $notification_key = null
){
    $stmt = $conn->prepare("
        INSERT IGNORE INTO notifications
        (user_id, title, message, link, notification_key)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        'issss',
        $user_id,
        $title,
        $message,
        $link,
        $notification_key
    );
    $stmt->execute();
    $stmt->close();
}
?>
