<?php

include '../auth/auth_check.php';

require_role([
    'Boss',
    'Admin',
    'Supervisor',
    'Technical'
]);

include '../config/database.php';
include '../libs/phpqrcode/qrlib.php';

require '../vendor/autoload.php';


$id = intval($_GET['id']);

$job = $conn->query("
SELECT
job_orders.*,
users.fullname AS technical_name,
users.employee_no AS technical_employee_no

FROM job_orders

LEFT JOIN users
ON users.id = job_orders.created_by

WHERE job_orders.id = '$id'
")->fetch_assoc();

if(!$job){
    die("Job Order not found.");
}

$jo_no = $job['jo_no'];

/*
QR CODE
*/

$qr_folder = "../uploads/qrcodes/";

if(!file_exists($qr_folder)){
    mkdir($qr_folder, 0777, true);
}

$qr_file = $qr_folder . $jo_no . ".png";

/*
PALITAN MO NG ACTUAL IP NG PC/SERVER MO
*/

$server_ip = "192.168.10.15";

$qr_url =
"http://" .
$server_ip .
"/icssv2/job_orders/scan_jo.php?id=" .
$id;

if(!file_exists($qr_file)){
    QRcode::png(
        $qr_url,
        $qr_file,
        QR_ECLEVEL_H,
        10
    );
}

/*
TECHNICAL NAME
*/

if(!empty($job['technical_name'])){
    $technical =
    $job['technical_employee_no'] .
    " - " .
    $job['technical_name'];
}else{
    $technical = "System / Unknown";
}

/*
CREATE COVER SHEET PDF ONLY
*/

$pdf = new FPDF();

$pdf->AddPage('P','A4');

$pdf->SetFont('Arial','B',22);
$pdf->Cell(0,12,'ICSS ERP',0,1,'C');

$pdf->SetFont('Arial','B',16);
$pdf->Cell(0,10,'JOB ORDER COVER SHEET',0,1,'C');

$pdf->Ln(8);

$pdf->SetDrawColor(0,0,0);
$pdf->SetLineWidth(0.4);
$pdf->Rect(15,35,180,88);

$label_x = 22;
$value_x = 65;
$y = 43;
$row_h = 9;

function coverRow($pdf, $label, $value, $label_x, $value_x, &$y, $row_h){
    $pdf->SetXY($label_x, $y);
    $pdf->SetFont('Arial','B',10);
    $pdf->Cell(40,7,$label,0,0);

    $pdf->SetXY($value_x, $y);
    $pdf->SetFont('Arial','',10);
    $pdf->MultiCell(120,7,$value,0,'L');

    $y = $pdf->GetY() + 1;
}

coverRow($pdf, 'JO NO:', $job['jo_no'], $label_x, $value_x, $y, $row_h);
coverRow($pdf, 'CLIENT:', $job['client_name'], $label_x, $value_x, $y, $row_h);
coverRow($pdf, 'PROJECT:', $job['project_name'], $label_x, $value_x, $y, $row_h);
coverRow($pdf, 'ENGINEER:', $job['engineer_name'], $label_x, $value_x, $y, $row_h);
coverRow($pdf, 'SALES:', $job['sales_name'], $label_x, $value_x, $y, $row_h);
coverRow($pdf, 'TECHNICAL:', $technical, $label_x, $value_x, $y, $row_h);
coverRow($pdf, 'RELEASE DATE:', $job['release_date'], $label_x, $value_x, $y, $row_h);
coverRow($pdf, 'DUE DATE:', $job['due_date'], $label_x, $value_x, $y, $row_h);

/*
QR AREA
*/

$pdf->SetY(132);

$pdf->SetFont('Arial','B',13);
$pdf->Cell(0,8,'SCAN QR CODE FOR LIVE JOB ORDER STATUS',0,1,'C');

$pdf->Image($qr_file, 75, 140, 60, 60);

$pdf->SetY(200);

$pdf->SetFont('Arial','',10);
$pdf->MultiCell(
    0,
    7,
    "Scan this QR code to open the live ERP page for this Job Order.\n\n" .
    "Production users will be redirected to their assigned task.\n" .
    "QA engineers will be redirected to the QA inspection page.\n" .
    "Logistics users will be redirected to the delivery page.\n" .
    "Boss, Admin, Supervisor, and Technical users will see the full Job Order details and audit trail.",
    0,
    'C'
);

$pdf->SetY(260);
$pdf->SetFont('Arial','I',9);
$pdf->Cell(
    0,
    8,
    'This cover sheet contains static JO information only. Live status is available through the QR code.',
    0,
    1,
    'C'
);

$pdf->Output('I', 'cover_sheet_' . $jo_no . '.pdf');

?>