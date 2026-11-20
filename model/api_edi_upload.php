<?php
session_start();
ob_start();
header('Content-Type: application/json; charset=utf-8');

function sendJson(array $payload): void {
    if (ob_get_length()) {
        ob_clean();
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit();
}

if (!isset($_SESSION['ID'])) {
    sendJson(['success' => false, 'message' => 'Session หมดอายุ กรุณา Login ใหม่']);
}

try {
    include '../function_mats.php';
    include '../dbcon_mats-new.php';

    require_once 'EdiOrderModel.php';

    global $conn;
    if (!$conn) {
        throw new Exception('ไม่พบการเชื่อมต่อฐานข้อมูล ($conn) ใน function_mats.php');
    }
    $ediModel = new EdiOrderModel($conn);
} catch (Throwable $e) {
    sendJson(['success' => false, 'message' => 'เกิดข้อผิดพลาดขณะโหลดระบบหลังบ้าน: ' . $e->getMessage()]);
}

$userOperate = $_SESSION['ID'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$jsonInput = [];

if (empty($action)) {
    $jsonInput = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $jsonInput['action'] ?? '';
}

// =========================================================================
// ACTIONS สำหรับหน้า AUTO_EDI (CSV ลูกค้า MILO - load_edi_data_mats.php)
// =========================================================================

if ($action === 'template') {
    if (ob_get_length()) ob_clean();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="EDI_Order_Template.csv"');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    $headers = [
        'CSTMSPPL_ID', 'BUYER_ID', 'PO_NO', 'PO_ITEM', 'CUSTOMER_PARTNO', 'PRODUCT_ID',
        'ALLOY', 'TEMPER', 'SURFACE_GRADE', 'METALLURGICAL_GRADE', 'THICKNESS', 'WIDTH',
        'LENGTH', 'UOM_DIMENSION', 'ETD_DATE', 'ORDER_QTY', 'UOM_WEIGHT', 'MAX_TOLERANCE',
        'MIN_TOLERANCE', 'UNIT_PRICE', 'CURRENCY_ID', 'SCRAP_WIRE', 'COIL_FROM', 'GRADE',
        'T5_TEMPERATURE', 'PAPER_INTERLEAVE', 'LME', 'LME_DATE', 'END_CUSTOMER_NAME',
        'END_CUSTOMER_PO', 'WORK_PROCESS'
    ];
    fputcsv($output, $headers);
    fclose($output);
    exit();
}

if ($action === 'validate') {
    $uploadDate = $_POST['upload_date'] ?? date('Y-m-d');
    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        sendJson(['success' => false, 'message' => 'ไม่พบไฟล์ หรือไฟล์อัปโหลดมีปัญหา']);
    }
    if ($_FILES['csv_file']['size'] === 0) {
        sendJson(['success' => false, 'message' => 'ไฟล์ CSV ที่เลือกเป็นไฟล์ว่างเปล่า (0 Byte)']);
    }

    try {
        $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") rewind($handle);

        $firstLine = fgets($handle);
        if ($firstLine === false || trim($firstLine) === '') {
            fclose($handle);
            sendJson(['success' => false, 'message' => 'ไม่พบข้อมูลภายในไฟล์ CSV']);
        }

        $delimiter = (substr_count($firstLine, ';') > substr_count($firstLine, ',')) ? ';' : ',';
        rewind($handle);
        if ($bom === "\xEF\xBB\xBF") fread($handle, 3);

        $rows = []; $summary = ['total' => 0, 'valid' => 0, 'price_mismatch' => 0, 'duplicate' => 0, 'error' => 0]; $rowNum = 0;
        while (($col = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rowNum++;
            if (empty($col) || (count($col) === 1 && trim($col[0]) === '')) continue;
            $firstCell = strtoupper(trim($col[0] ?? ''));
            if ($rowNum === 1 && in_array($firstCell, ['CSTMSPPL_ID', 'CUSTOMER', 'CUSTOMER_ID'])) continue;

            $col = array_map(function($val) {
                return !mb_check_encoding($val, 'UTF-8') ? mb_convert_encoding($val, 'UTF-8', 'Windows-874') : $val;
            }, $col);

            $summary['total']++;
            $validated = $ediModel->validateCsvRow($col, $rowNum, $uploadDate);
            if ($validated['status'] === 'ERROR') $summary['error']++;
            elseif ($validated['status'] === 'PRICE_MISMATCH') $summary['price_mismatch']++;
            elseif ($validated['status'] === 'DUPLICATE') $summary['duplicate']++;
            else $summary['valid']++;
            $rows[] = $validated;
        }
        fclose($handle);
        sendJson(['success' => true, 'summary' => $summary, 'rows' => $rows]);
    } catch (Throwable $e) {
        sendJson(['success' => false, 'message' => 'ตรวจสอบ CSV ไม่สำเร็จ: ' . $e->getMessage()]);
    }
}

if ($action === 'revalidate') {
    try {
        $uploadDate = $jsonInput['upload_date'] ?? date('Y-m-d');
        $inputRows  = $jsonInput['rows'] ?? [];
        $rows = []; $summary = ['total' => 0, 'valid' => 0, 'price_mismatch' => 0, 'duplicate' => 0, 'error' => 0];

        foreach ($inputRows as $r) {
            $summary['total']++;
            $validated = $ediModel->validateMappedRow($r, $uploadDate);
            if ($validated['status'] === 'ERROR') $summary['error']++;
            elseif ($validated['status'] === 'PRICE_MISMATCH') $summary['price_mismatch']++;
            elseif ($validated['status'] === 'DUPLICATE') $summary['duplicate']++;
            else $summary['valid']++;
            $rows[] = $validated;
        }
        sendJson(['success' => true, 'summary' => $summary, 'rows' => $rows]);
    } catch (Throwable $e) {
        sendJson(['success' => false, 'message' => 'ตรวจสอบซ้ำไม่สำเร็จ: ' . $e->getMessage()]);
    }
}

if ($action === 'commit') {
    $uploadDate   = $jsonInput['upload_date'] ?? date('Y-m-d');
    $overwriteDup = $jsonInput['overwrite_dup'] ?? true;
    $rows         = $jsonInput['rows'] ?? [];
    try {
        $result = $ediModel->saveBatchOrders($rows, $uploadDate, $overwriteDup, $userOperate);
        $pending = $ediModel->getPendingEdiOrders($uploadDate, 'AUTO_EDI');
        sendJson([
            'success' => true,
            'message' => "บันทึกลง EDIORDER1 สำเร็จ! (เพิ่มใหม่: {$result['inserted']} รายการ, อัปเดตทับ: {$result['updated']} รายการ)",
            'pending_count' => count($pending),
            'pending_rows'  => $pending
        ]);
    } catch (Throwable $e) {
        sendJson(['success' => false, 'message' => 'เกิดข้อผิดพลาด ระบบยกเลิกการบันทึกทั้งหมด: ' . $e->getMessage()]);
    }
}

// =========================================================================
// ACTIONS สำหรับหน้า MANUAL_EXCEL (ไฟล์ .xlsx - load_edi_manual_mats.php)
// =========================================================================

if ($action === 'validate_manual') {
    $uploadDate = $_POST['upload_date'] ?? date('Y-m-d');
    if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
        sendJson(['success' => false, 'message' => 'ไม่พบไฟล์ Excel หรือไฟล์อัปโหลดมีปัญหา (Code: ' . ($_FILES['excel_file']['error'] ?? 'none') . ')']);
    }
    try {
        $result = $ediModel->parseAndValidateManualExcel($_FILES['excel_file']['tmp_name'], $uploadDate);
        sendJson(['success' => true, 'summary' => $result['summary'], 'rows' => $result['rows']]);
    } catch (Throwable $e) {
        sendJson(['success' => false, 'message' => 'อ่านไฟล์ Excel ไม่สำเร็จ: ' . $e->getMessage()]);
    }
}

if ($action === 'revalidate_manual') {
    try {
        $uploadDate = $jsonInput['upload_date'] ?? date('Y-m-d');
        $inputRows  = $jsonInput['rows'] ?? [];
        $rows = []; $summary = ['total' => 0, 'valid' => 0, 'duplicate' => 0, 'error' => 0];

        foreach ($inputRows as $r) {
            $summary['total']++;
            $validated = $ediModel->validateManualRow($r, $uploadDate);
            if ($validated['status'] === 'ERROR') $summary['error']++;
            elseif ($validated['status'] === 'DUPLICATE') $summary['duplicate']++;
            else $summary['valid']++;
            $rows[] = $validated;
        }
        sendJson(['success' => true, 'summary' => $summary, 'rows' => $rows]);
    } catch (Throwable $e) {
        sendJson(['success' => false, 'message' => 'ตรวจสอบซ้ำไม่สำเร็จ: ' . $e->getMessage()]);
    }
}

if ($action === 'commit_manual') {
    $uploadDate   = $jsonInput['upload_date'] ?? date('Y-m-d');
    $overwriteDup = $jsonInput['overwrite_dup'] ?? true;
    $rows         = $jsonInput['rows'] ?? [];
    try {
        $result = $ediModel->saveManualBatchOrders($rows, $uploadDate, $overwriteDup, $userOperate);
        $pending = $ediModel->getPendingEdiOrders($uploadDate, 'MANUAL_EXCEL');
        sendJson([
            'success' => true,
            'message' => "บันทึกลง EDIORDER1 สำเร็จ! (เพิ่มใหม่: {$result['inserted']} รายการ, อัปเดตทับ: {$result['updated']} รายการ)",
            'pending_count' => count($pending),
            'pending_rows'  => $pending
        ]);
    } catch (Throwable $e) {
        sendJson(['success' => false, 'message' => 'เกิดข้อผิดพลาด ระบบยกเลิกการบันทึกทั้งหมด: ' . $e->getMessage()]);
    }
}

// =========================================================================
// SHARED ACTIONS: ดูรายการค้าง (OP) และสร้าง SO / Job Order
// =========================================================================

if ($action === 'get_pending') {
    try {
        $uploadDate = $_GET['upload_date'] ?? $_POST['upload_date'] ?? date('Y-m-d');
        $source     = $_GET['source'] ?? $_POST['source'] ?? '';
        $pending    = $ediModel->getPendingEdiOrders($uploadDate, $source);
        sendJson([
            'success'       => true,
            'pending_count' => count($pending),
            'pending_rows'  => $pending
        ]);
    } catch (Throwable $e) {
        sendJson(['success' => false, 'message' => 'ดึงรายการค้างไม่สำเร็จ: ' . $e->getMessage()]);
    }
}

if ($action === 'gen_so_job') {
    $uploadDate = $jsonInput['upload_date'] ?? date('Y-m-d');
    $source     = $jsonInput['source'] ?? '';
    try {
        $generated = $ediModel->generateSoAndJob($uploadDate, $userOperate, $source);
        sendJson([
            'success'   => true,
            'message'   => 'สร้าง Sale Order และ Job Order สำเร็จทั้งหมด ' . count($generated) . ' รายการ!',
            'generated' => $generated
        ]);
    } catch (Throwable $e) {
        sendJson(['success' => false, 'message' => 'ไม่สามารถสร้าง SO/Job Order ได้: ' . $e->getMessage()]);
    }
}

sendJson(['success' => false, 'message' => 'ไม่พบคำสั่ง Action ที่ระบุ']);