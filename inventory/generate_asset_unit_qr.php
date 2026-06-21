<?php

include '../auth/auth_check.php';
require_role(['Boss', 'Admin', 'Purchasing']);
include '../config/database.php';
include '../libs/phpqrcode/qrlib.php';

$unit_id = intval($_GET['unit_id'] ?? 0);
$stmt = $conn->prepare("
    SELECT au.id, au.asset_code
    FROM asset_units au
    INNER JOIN inventory_items i ON i.id = au.inventory_id
    WHERE au.id = ? AND i.item_type = 'Asset'
");
$stmt->bind_param('i', $unit_id);
$stmt->execute();
$unit = $stmt->get_result()->fetch_assoc();

if (!$unit) {
    exit('Asset unit not found.');
}

$folder = '../uploads/asset_qr/';
if (!is_dir($folder)) {
    mkdir($folder, 0777, true);
}

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$configured = rtrim(getenv('ICSS_BASE_URL') ?: '', '/');
$base_url = $configured !== '' ? $configured : $scheme . '://' . $host . '/icssv2';
$url = $base_url . '/inventory/public_asset_borrow.php?unit_id=' . $unit_id;
$filename = 'asset_unit_' . $unit_id . '.png';
$path = $folder . $filename;

QRcode::png($url, $path, QR_ECLEVEL_L, 6);
$db_path = 'uploads/asset_qr/' . $filename;
$update = $conn->prepare("UPDATE asset_units SET qr_code = ? WHERE id = ?");
$update->bind_param('si', $db_path, $unit_id);
$update->execute();

header('Location: asset_unit_qr_view.php?unit_id=' . $unit_id);
exit();
