<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

$iduser_func     = isset($_SESSION['ID']) ? trim($_SESSION['ID']) : '';
$folder_func     = isset($_SESSION['FUNC']) ? strtoupper(trim($_SESSION['FUNC'])) : '';
$department_func = isset($_SESSION['DEPARTMENT']) ? strtoupper(trim($_SESSION['DEPARTMENT'])) : '';

if ($iduser_func === '') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Session expired. Please login again.']);
    exit();
}

// เงื่อนไขสิทธิ์การสร้าง / แก้ไข / ลบ
$is_authorized = ($folder_func === 'WH' || $department_func === 'IT' || $department_func === 'FN');

require_once '../dbcon_mats-new.php';
require_once 'booking_shipment_model_mats.php';

$model = new BookingShipmentModel($conn);

// อ่านข้อมูล Request (รองรับทั้ง GET และ JSON POST)
$rawInput = file_get_contents('php://input');
$postData = json_decode($rawInput, true) ?: $_POST;
$action   = $_GET['action'] ?? ($postData['action'] ?? '');

// รายการ Action ที่ต้องมีสิทธิ์แก้ไข
$writeActions = [
    'save_heading',
    'save_container',
    'add_product',
    'remove_product',
    'delete_container',
    'delete_booking'
];

if (in_array($action, $writeActions, true) && !$is_authorized) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'คุณไม่มีสิทธิ์ในการแก้ไขหรือลบข้อมูล (Authorized: WH, IT, FN only)']);
    exit();
}

try {
    switch ($action) {
        case 'get_customer':
            $customerId = $_GET['customer_id'] ?? ($postData['customer_id'] ?? '');
            $customer = $model->getCustomerDetail($customerId);
            if (!$customer) {
                throw new Exception("ไม่พบข้อมูลลูกค้าในระบบ (STNDD-06)");
            }
            echo json_encode(['success' => true, 'data' => $customer]);
            break;

        case 'get_detail':
            $bookNo = $_GET['book_no'] ?? ($postData['book_no'] ?? '');
            $detail = $model->getBookingDetail($bookNo);
            echo json_encode(['success' => true, 'data' => $detail]);
            break;

        case 'save_heading':
            $isNew = !empty($postData['is_new']);
            $model->saveHeading($postData, $iduser_func, $isNew);
            $detail = $model->getBookingDetail($postData['BOOK_NO']);
            echo json_encode([
                'success' => true,
                'message' => 'บันทึกข้อมูลหัวใบจองเรียบร้อยแล้ว (STNDD-01)',
                'data'    => $detail
            ]);
            break;

        case 'save_container':
            $bookNo      = $postData['book_no'] ?? '';
            $containerNo = $postData['container_no'] ?? '';
            $sealNo      = $postData['seal_no'] ?? '';
            $uom         = $postData['uom'] ?? '';
            $isNew       = !empty($postData['is_new']);

            $model->saveContainer($bookNo, $containerNo, $sealNo, $uom, $isNew);
            $detail = $model->getBookingDetail($bookNo);
            echo json_encode([
                'success' => true,
                'message' => $isNew ? 'เพิ่มตู้คอนเทนเนอร์เรียบร้อยแล้ว' : 'อัปเดตข้อมูลตู้คอนเทนเนอร์เรียบร้อยแล้ว',
                'data'    => $detail
            ]);
            break;

        case 'add_product':
            $bookNo      = $postData['book_no'] ?? '';
            $containerNo = $postData['container_no'] ?? '';
            $productNo   = $postData['product_no'] ?? '';

            $model->addProductToContainer($bookNo, $containerNo, $productNo);
            $detail = $model->getBookingDetail($bookNo);
            echo json_encode([
                'success' => true,
                'message' => "บรรจุสินค้า {$productNo} ลงตู้ {$containerNo} เรียบร้อยแล้ว",
                'data'    => $detail
            ]);
            break;

        case 'remove_product':
            $bookNo      = $postData['book_no'] ?? '';
            $containerNo = $postData['container_no'] ?? '';
            $productNo   = $postData['product_no'] ?? '';

            $model->removeProductFromContainer($bookNo, $containerNo, $productNo);
            $detail = $model->getBookingDetail($bookNo);
            echo json_encode([
                'success' => true,
                'message' => "นำสินค้า {$productNo} ออกจากตู้เรียบร้อยแล้ว",
                'data'    => $detail
            ]);
            break;

        case 'delete_container':
            $bookNo      = $postData['book_no'] ?? '';
            $containerNo = $postData['container_no'] ?? '';

            $model->deleteContainer($bookNo, $containerNo);
            $detail = $model->getBookingDetail($bookNo);
            echo json_encode([
                'success' => true,
                'message' => "ลบตู้คอนเทนเนอร์ {$containerNo} เรียบร้อยแล้ว (STNDD-02)",
                'data'    => $detail
            ]);
            break;

        case 'delete_booking':
            $bookNo = $postData['book_no'] ?? '';
            $model->deleteBooking($bookNo);
            echo json_encode([
                'success' => true,
                'message' => "ลบใบจอง {$bookNo} เรียบร้อยแล้ว (STNDD-02)"
            ]);
            break;

        default:
            throw new Exception("Invalid API action.");
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}