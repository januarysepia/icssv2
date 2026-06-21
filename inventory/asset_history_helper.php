<?php

function logAssetHistory(
    $conn,
    $inventory_id,
    $asset_unit_id,
    $assignment_id,
    $action_type,
    $employee_id,
    $action_date,
    $condition_status,
    $remarks,
    $created_by
){
    $stmt = $conn->prepare("
        INSERT INTO asset_history 
        (
            inventory_id,
            asset_unit_id,
            assignment_id,
            action_type,
            employee_id,
            action_date,
            condition_status,
            remarks,
            created_by
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->bind_param(
        "iiisisssi",
        $inventory_id,
        $asset_unit_id,
        $assignment_id,
        $action_type,
        $employee_id,
        $action_date,
        $condition_status,
        $remarks,
        $created_by
    );

    return $stmt->execute();
}
