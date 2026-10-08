<?php
header('Content-Type: application/json; charset=utf-8');

// ปิดการแสดง error รบกวน JSON output
error_reporting(0);
ini_set('display_errors', 0);

// นำเข้า PhpSpreadsheet (ปรับ path ของ vendor/autoload.php ให้ตรงกับโฟลเดอร์โครงการของคุณ)
if (file_exists('../vendor/autoload.php')) {
    require_once '../vendor/autoload.php';
} else if (file_exists('../../vendor/autoload.php')) {
    require_once '../../vendor/autoload.php';
} else if (file_exists('vendor/autoload.php')) {
    require_once 'vendor/autoload.php';
}

use PhpOffice\PhpSpreadsheet\IOFactory;

// รับค่า Product No / Coil No จาก AJAX
$coil_no = isset($_POST['coil_no']) ? trim($_POST['coil_no']) : '';

if (empty($coil_no)) {
    echo json_encode(['status' => 'error', 'message' => 'Not found Product No']);
    exit;
}

// 1. ตัด Product No เอาเฉพาะ 7 ตัวแรก (เช่น C260814 จาก C260814-2-02)
$file_prefix = substr($coil_no, 0, 7);

// 2. รายการ Path ทั้ง Drive R:\ และ Network Path \\fp1server\
$possible_paths = [
    'R:\\All Department\\Technical\\Technical Profile MATS\\',
    'R:\\Technical Profile MATS\\',
    '\\\\fp1server\\common\\All Department\\Technical\\Technical Profile MATS\\',
    '\\\\fp1server\\Common\\All Department\\Technical\\Technical Profile MATS\\'
];

$file_path = '';

// วนลูปหาไฟล์ว่ามีอยู่ใน Path ไหน
foreach ($possible_paths as $dir) {
    if (file_exists($dir . $file_prefix . '.xls')) {
        $file_path = $dir . $file_prefix . '.xls';
        break;
    } else if (file_exists($dir . $file_prefix . '.xlsx')) {
        $file_path = $dir . $file_prefix . '.xlsx';
        break;
    }
}

// 3. ถ้าตรวจสอบแล้วไม่พบไฟล์
if (empty($file_path)) {
    echo json_encode([
        'status' => 'error', 
        'message' => "File not found : {$file_prefix}.xls in Directory"
    ]);
    exit;
}

try {
    // 4. โหลดไฟล์ Excel ด้วย PhpSpreadsheet
    $spreadsheet = IOFactory::load($file_path);
    $worksheet = $spreadsheet->getActiveSheet();
    $rows = $worksheet->toArray(null, true, true, false);

    if (empty($rows) || count($rows) < 2) {
        echo json_encode(['status' => 'error', 'message' => 'File structure Excel Incorrect or lacking information.']);
        exit;
    }

    // 5. อ่าน Header Row (แถวแรก) เพื่อทำ Map คอลัมน์ตำแหน่งระยะ (-800 ถึง 800)
    $headerRow = $rows[0];
    $colMap = [];
    foreach ($headerRow as $colIdx => $val) {
        if ($val !== null && $val !== '') {
            // แปลงค่าให้เป็นตัวเลขจำนวนเต็ม เช่น -750.0 -> "-750"
            $posStr = (string)round(floatval($val));
            $colMap[$posStr] = $colIdx;
        }
    }

    // 6. ค้นหาแถวที่มี Product No / Coil No ตรงกับค่าปัจจุบัน
    $targetRow = null;
    for ($i = 1; $i < count($rows); $i++) {
        $rowCoilNo = isset($rows[$i][0]) ? trim((string)$rows[$i][0]) : '';
        if (strcasecmp($rowCoilNo, $coil_no) === 0) {
            $targetRow = $rows[$i];
            break;
        }
    }

    if (!$targetRow) {
        echo json_encode([
            'status' => 'error', 
            'message' => "No information found Product No: {$coil_no} In the file {$file_prefix}.xls"
        ]);
        exit;
    }

    // 7. ลำดับตำแหน่งระยะ 33 จุด
    $positionKeys = [
        "-800", "-750", "-700", "-650", "-600", "-550", "-500", "-450", "-400", "-350", "-300", "-250", "-200", "-150", "-100", "-50", "0",
        "50", "100", "150", "200", "250", "300", "350", "400", "450", "500", "550", "600", "650", "700", "750", "800"
    ];

    $resultData = [];
    foreach ($positionKeys as $key) {
        if (isset($colMap[$key]) && isset($targetRow[$colMap[$key]]) && $targetRow[$colMap[$key]] !== null && $targetRow[$colMap[$key]] !== '') {
            $resultData[$key] = floatval($targetRow[$colMap[$key]]);
        } else {
            $resultData[$key] = 0.000;
        }
    }

    // ส่งค่ากลับเป็น JSON
    echo json_encode([
        'status' => 'success',
        'data' => $resultData
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'An error occurred while reading the file Excel: ' . $e->getMessage()
    ]);
}