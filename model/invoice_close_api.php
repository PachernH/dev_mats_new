<?php
// model/invoice_close_api.php
session_start();
header('Content-Type: application/json; charset=utf-8');

$iduser_func     = isset($_SESSION['ID']) ? trim($_SESSION['ID']) : '';
$folder_func     = isset($_SESSION['FUNC']) ? strtoupper(trim($_SESSION['FUNC'])) : '';
$department_func = isset($_SESSION['DEPARTMENT']) ? strtoupper(trim($_SESSION['DEPARTMENT'])) : '';

if ($iduser_func === '') {
    http_response_code(401);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Session หมดอายุ กรุณาเข้าสู่ระบบใหม่อีกครั้ง'
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

$is_invoice_authorized = ($folder_func === 'WH' || $department_func === 'IT' || $department_func === 'FN');

require_once __DIR__ . '/../dbcon_mats-new.php';
require_once __DIR__ . '/InvoiceCloseModel.php';

if (!isset($conn) || !$conn) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'ไม่สามารถเชื่อมต่อฐานข้อมูลได้'
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

$model = new InvoiceCloseModel($conn);

// รองรับทั้ง Query String (?action=...) และ JSON Body
$rawInput = file_get_contents('php://input');
$jsonBody = json_decode($rawInput, true);
if (!is_array($jsonBody)) {
    $jsonBody = [];
}

$action = $_REQUEST['action'] ?? ($jsonBody['action'] ?? '');

try {
    switch ($action) {
        case 'options':
            echo json_encode([
                'status' => 'success',
                'data'   => $model->getDropdownOptions(),
                'authorized' => $is_invoice_authorized
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'list':
            $filters = [
                'keyword'   => $_REQUEST['keyword'] ?? ($jsonBody['keyword'] ?? ''),
                'date_from' => $_REQUEST['date_from'] ?? ($jsonBody['date_from'] ?? ''),
                'date_to'   => $_REQUEST['date_to'] ?? ($jsonBody['date_to'] ?? ''),
                'status'    => $_REQUEST['status'] ?? ($jsonBody['status'] ?? '')
            ];
            $limit = isset($_REQUEST['limit']) ? (int)$_REQUEST['limit'] : 300;
            $list = $model->getInvoiceList($filters, $limit);

            echo json_encode([
                'status'     => 'success',
                'count'      => count($list),
                'authorized' => $is_invoice_authorized,
                'data'       => $list
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'detail':
            $invoiceNo = $_REQUEST['invoice_no'] ?? ($jsonBody['invoice_no'] ?? '');
            $invoiceNo = strtoupper(trim($invoiceNo));

            if (strlen($invoiceNo) !== 12) {
                echo json_encode([
                    'status'  => 'error',
                    'message' => 'เลขที่ Invoice ไม่ถูกต้อง (ต้องมีความยาว 12 ตัวอักษร)'
                ], JSON_UNESCAPED_UNICODE);
                break;
            }

            $detail = $model->getInvoiceDetail($invoiceNo);
            if (!$detail) {
                echo json_encode([
                    'status'  => 'error',
                    'message' => "ไม่พบข้อมูลเลขที่ Invoice: {$invoiceNo}"
                ], JSON_UNESCAPED_UNICODE);
                break;
            }

            echo json_encode([
                'status'     => 'success',
                'authorized' => $is_invoice_authorized,
                'options'    => $model->getDropdownOptions(),
                'warnings'   => $detail['warnings'],
                'data'       => [
                    'heading'       => $detail['heading'],
                    'invoice_items' => $detail['invoice_items'],
                    'product_items' => $detail['product_items']
                ]
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'save':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode([
                    'status'  => 'error',
                    'message' => 'Method Not Allowed'
                ], JSON_UNESCAPED_UNICODE);
                break;
            }

            if (!$is_invoice_authorized) {
                http_response_code(403);
                echo json_encode([
                    'status'  => 'error',
                    'message' => 'คุณไม่มีสิทธิ์ในการบันทึกหรือแก้ไขข้อมูล Close Invoice'
                ], JSON_UNESCAPED_UNICODE);
                break;
            }

            $payload = !empty($jsonBody) ? $jsonBody : $_POST;
            $payload['operator'] = $iduser_func;

            $result = $model->saveClosingInvoice($payload);

            $msg = "บันทึกข้อมูล Close Invoice ({$result['invoice_no']}) เรียบร้อยแล้ว";
            if ($result['batch_invoice_count'] > 0) {
                $msg .= " (อัปเดตอัตราแลกเปลี่ยนแบบกลุ่มสำเร็จ {$result['batch_invoice_count']} รายการ)";
            }

            echo json_encode([
                'status'  => 'success',
                'message' => $msg,
                'data'    => $result
            ], JSON_UNESCAPED_UNICODE);
            break;

        default:
            http_response_code(400);
            echo json_encode([
                'status'  => 'error',
                'message' => 'Invalid API action'
            ], JSON_UNESCAPED_UNICODE);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}