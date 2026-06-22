<?php

function getNextJobOrderNumber(mysqli $conn, ?int $year = null): string
{
    $year = $year ?: (int) date('Y');
    $prefix = 'JO-' . $year . '-';
    $like = $prefix . '%';

    $stmt = $conn->prepare("
        SELECT jo_no
        FROM job_orders
        WHERE jo_no LIKE ?
        ORDER BY CAST(SUBSTRING_INDEX(jo_no, '-', -1) AS UNSIGNED) DESC
        LIMIT 1
    ");
    $stmt->bind_param('s', $like);
    $stmt->execute();
    $last = $stmt->get_result()->fetch_assoc();

    $next_number = 1;
    if ($last && preg_match('/^JO-\d{4}-(\d+)$/', $last['jo_no'], $matches)) {
        $next_number = ((int) $matches[1]) + 1;
    }

    $width = max(3, strlen((string) $next_number));
    return $prefix . str_pad((string) $next_number, $width, '0', STR_PAD_LEFT);
}

