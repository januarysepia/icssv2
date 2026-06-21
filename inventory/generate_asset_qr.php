<?php
include '../auth/auth_check.php';

require_role([
    'Boss',
    'Admin',
    'Purchasing'
]);

include '../config/database.php';
include '../libs/phpqrcode/qrlib.php';

$inventory_id = intval($_GET['id'] ?? 0);

if ($inventory_id <= 0) {
    echo "
    <script>
        alert('Invalid asset ID.');
        window.location='asset_list.php';
    </script>";
    exit;
}

$asset = $conn->query("
    SELECT *
    FROM inventory_items
    WHERE id = '$inventory_id'
      AND item_type = 'Asset'
")->fetch_assoc();

if (!$asset) {
    echo "
    <script>
        alert('Asset not found.');
        window.location='asset_list.php';
    </script>";
    exit;
}

$unit = $conn->query("
    SELECT id FROM asset_units
    WHERE inventory_id = '$inventory_id'
    ORDER BY id ASC
    LIMIT 1
")->fetch_assoc();

if (!$unit) {
    exit('No physical asset unit exists for this catalog item.');
}

header('Location: generate_asset_unit_qr.php?unit_id=' . intval($unit['id']));
exit();

$qr_folder = "../uploads/asset_qr/";

if (!is_dir($qr_folder)) {
    mkdir($qr_folder, 0777, true);
}

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$configured_base_url = rtrim(getenv('ICSS_BASE_URL') ?: '', '/');
$base_url = $configured_base_url !== ''
    ? $configured_base_url
    : $scheme . '://' . $host . '/icssv2';

$qr_link = $base_url . "/inventory/public_asset_borrow.php?asset_id=" . $inventory_id;

$qr_filename = "asset_qr_" . $inventory_id . ".png";
$qr_path = $qr_folder . $qr_filename;

QRcode::png($qr_link, $qr_path, QR_ECLEVEL_L, 6);

$db_qr_path = "uploads/asset_qr/" . $qr_filename;

$stmt = $conn->prepare("
    UPDATE inventory_items
    SET qr_code = ?
    WHERE id = ?
");

$stmt->bind_param("si", $db_qr_path, $inventory_id);
$stmt->execute();

echo "
<script>
    alert('QR Code generated successfully.');
    window.location='asset_qr_view.php?asset_id=$inventory_id';
</script>";
exit;
?>
