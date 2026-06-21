<?php

function logActivity($conn, $module, $activity, $user_id){

    $module = $conn->real_escape_string($module);
    $activity = $conn->real_escape_string($activity);

    $conn->query("
    INSERT INTO activity_logs
    (
        module_name,
        activity,
        user_id
    )
    VALUES
    (
        '$module',
        '$activity',
        '$user_id'
    )
    ");
}

?>