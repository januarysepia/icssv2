<?php

function logJOAudit(
    $conn,
    $jo_id,
    $action,
    $user_id,
    $remarks = ''
){

    $action = mysqli_real_escape_string($conn,$action);
    $remarks = mysqli_real_escape_string($conn,$remarks);

    $conn->query("
    INSERT INTO jo_audit_logs
    (
        jo_id,
        action,
        remarks,
        user_id
    )
    VALUES
    (
        '$jo_id',
        '$action',
        '$remarks',
        '$user_id'
    )
    ");
}
?>