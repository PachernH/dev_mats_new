<?php
session_start();
header('Content-Type: application/json; charset=UTF-8');

function sendJson($data, $httpCode = 200) {
    http_response_code($httpCode);
    $clean = utf8ize($data);
    echo json_encode($clean, JSON_UNESCAPED_UNICODE);
    exit();
}

function utf8ize($mixed) {
    if (is_array($mixed)) {
        foreach ($mixed as $key => $value) {
            $mixed[$key] = utf8ize($value);
        }
    } elseif (is_string($mixed)) {
        if (preg_match('//u', $mixed) !== 1) {
            $converted = @iconv('Windows-874', 'UTF-8//IGNORE', $mixed);
            $mixed = ($converted !== false) ? $converted : utf8_encode($mixed);
        }
    }
    return $mixed;
}

$iduser_func     = isset($_SESSION['ID']) ? trim($_SESSION['ID']) : '';
$folder_func     = isset($_SESSION['FUNC']) ? trim($_SESSION['FUNC']) : '';
$department_func = isset($_SESSION['DEPARTMENT']) ? strtoupper(trim($_SESSION['DEPARTMENT'])) : '';

if (empty($iduser_func)) {
    sendJson(['status' => 'error', 'message' => 'Session expired. Please login again.'], 401);
}

$current_func = strtoupper(trim(!empty($folder_func) ? $folder_func : ($_REQUEST['func'] ?? '')));
$current_dept = strtoupper(trim($department_func));
$is_invoice_authorized = ($current_func === 'WH' || $current_dept === 'IT' || $current_dept === 'FN');

require_once '../dbcon_mats-new.php';
require_once '../model/InvoiceModelMats.php';

$model = new InvoiceModelMats($conn);
$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

$writeActions = [
    'create_heading', 'update_heading', 'delete_heading',
    'add_item', 'add_multiple_items', 'update_item', 'delete_item',
    'add_products', 'update_product_skid', 'delete_product'
];
if (in_array($action, $writeActions) && !$is_invoice_authorized) {
    sendJson(['status' => 'error', 'message' => 'คุณไม่มีสิทธิ์ในการเพิ่ม แก้ไข หรือลบข้อมูล Invoice'], 403);
}

try {
    switch ($action) {
        case 'get_masters':
            sendJson(['status' => 'success', 'data' => $model->getMasterDropdowns()]);
            break;

        case 'get_customer':
            $cust = $model->getCustomerInfo($_GET['customer_id'] ?? '');
            $bookings = $model->getAvailableBookings($_GET['customer_id'] ?? '', $_GET['invoice_no'] ?? '');
            sendJson(['status' => 'success', 'customer' => $cust, 'bookings' => $bookings]);
            break;

        case 'get_booking':
            $book = $model->getBookingDetail($_GET['book_no'] ?? '', $_GET['customer_id'] ?? '', $_GET['invoice_no'] ?? '');
            sendJson(['status' => 'success', 'data' => $book]);
            break;

        case 'get_detail':
            $detail = $model->getInvoiceDetail($_GET['invoice_no'] ?? '');
            sendJson(['status' => 'success', 'data' => $detail]);
            break;

        case 'create_heading':
            sendJson($model->createInvoiceHeading($input, $iduser_func));
            break;

        case 'update_heading':
            sendJson($model->updateInvoiceHeading($input, $iduser_func));
            break;

        case 'delete_heading':
            sendJson($model->deleteInvoiceHeading($input['invoice_no'] ?? ''));
            break;

        case 'lookup_so_items':
            $result = $model->getAvailableSaleOrders(
                $_GET['invoice_no'] ?? '',
                $_GET['search'] ?? '',
                $_GET['category'] ?? '',
                $_GET['prod_id'] ?? ''
            );
            sendJson([
                'status'     => 'success',
                'data'       => $result['rows'],
                'categories' => $result['categories'],
                'products'   => $result['products']
            ]);
            break;

        case 'add_item':
            sendJson($model->addInvoiceItem($input['invoice_no'] ?? '', $input['so_no'] ?? '', $input['so_item'] ?? ''));
            break;

        case 'add_multiple_items':
            sendJson($model->addMultipleInvoiceItems($input['invoice_no'] ?? '', $input['items'] ?? []));
            break;

        case 'update_item':
            sendJson($model->updateInvoiceItem($input));
            break;

        case 'delete_item':
            sendJson($model->deleteInvoiceItem($input['invoice_no'] ?? '', $input['invoice_item'] ?? ''));
            break;

        case 'lookup_stock_products':
            $rows = $model->getAvailableStockProducts($_GET['invoice_no'] ?? '', $_GET['invoice_item'] ?? '');
            sendJson(['status' => 'success', 'data' => $rows]);
            break;

        case 'add_products':
            sendJson($model->addInvoiceProducts(
                $input['invoice_no'] ?? '',
                $input['invoice_item'] ?? '',
                $input['product_nos'] ?? [],
                $input['skid_no'] ?? '00',
                !empty($input['close_so']),
                $iduser_func
            ));
            break;

        case 'update_product_skid':
            sendJson($model->updateInvoiceProductSkid($input['invoice_no'] ?? '', $input['product_no'] ?? '', $input['skid_no'] ?? '00'));
            break;

        case 'delete_product':
            sendJson($model->deleteInvoiceProduct($input['invoice_no'] ?? '', $input['invoice_item'] ?? '', $input['product_no'] ?? ''));
            break;

        default:
            sendJson(['status' => 'error', 'message' => 'Unknown API action'], 400);
    }
} catch (Exception $e) {
    sendJson(['status' => 'error', 'message' => $e->getMessage()], 400);
}