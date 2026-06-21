<?php
include '../auth/auth_check.php';
require_role(['Admin']);
include '../config/database.php';
include '../includes/activity_logger.php';

set_time_limit(120);
$filename = 'icss_backup_' . date('Ymd_His') . '.sql';
header('Content-Type: application/sql; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store');

echo "-- ICSS v2 database backup\n";
echo "-- Generated: " . date('Y-m-d H:i:s') . "\n";
echo "SET FOREIGN_KEY_CHECKS=0;\n\n";

$tables = $conn->query("SHOW FULL TABLES WHERE Table_type='BASE TABLE'");
while ($table_row = $tables->fetch_row()) {
    $table = $table_row[0];
    $safe_table = str_replace('`', '``', $table);
    $create = $conn->query("SHOW CREATE TABLE `$safe_table`")->fetch_row();

    echo "DROP TABLE IF EXISTS `$safe_table`;\n";
    echo $create[1] . ";\n\n";

    $data = $conn->query("SELECT * FROM `$safe_table`");
    while ($row = $data->fetch_assoc()) {
        $columns = [];
        $values = [];
        foreach ($row as $column => $value) {
            $columns[] = '`' . str_replace('`', '``', $column) . '`';
            $values[] = $value === null ? 'NULL' : "'" . $conn->real_escape_string((string) $value) . "'";
        }
        echo "INSERT INTO `$safe_table` (" . implode(',', $columns) . ") VALUES (" . implode(',', $values) . ");\n";
    }
    echo "\n";
}

echo "SET FOREIGN_KEY_CHECKS=1;\n";
logActivity($conn, 'Administration', 'Downloaded database backup ' . $filename, (int) $_SESSION['user_id']);
exit();
