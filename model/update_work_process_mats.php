<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

$response = array('status' => 'error', 'message' => false);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['error'] = 'Invalid Request Method';
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!file_exists('../dbcon_mats-new.php')) {
    $response['error'] = 'ไม่พบไฟล์เชื่อมต่อฐานข้อมูล (dbcon_mats-new.php)';
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

include('../dbcon_mats-new.php');

try {
    // 1. รับค่าจากฟอร์ม
    $work_process = isset($_POST['work_process']) ? trim($_POST['work_process']) : '';
    $product_id   = isset($_POST['product_id']) ? trim($_POST['product_id']) : '';
    $work_type    = isset($_POST['work_type']) ? trim($_POST['work_type']) : '';
    $description  = isset($_POST['description']) ? trim($_POST['description']) : '';

    if ($work_process === '' || $product_id === '') {
        $response['error'] = 'กรุณากรอกข้อมูล Work Process และ Product ID ให้ครบถ้วน';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 2. คำสั่ง SQL UPDATE ตาราง WKPCMSTR1
    $sql = "UPDATE WKPCMSTR1 
            SET PRODUCT_ID  = :product_id,
                WORK_TYPE   = :work_type,
                DESCRIPTION = :description
            WHERE LTRIM(RTRIM(WORK_PROCESS)) = LTRIM(RTRIM(:work_process))";

    $stmt = $conn->prepare($sql);

    // Bind ค่า
    $stmt->bindValue(':product_id', $product_id, PDO::PARAM_STR);
    $stmt->bindValue(':work_type', $work_type, PDO::PARAM_STR);
    $stmt->bindValue(':description', $description, PDO::PARAM_STR);
    $stmt->bindValue(':work_process', $work_process, PDO::PARAM_STR);

    if ($stmt->execute()) {
        $response['status']  = 'success';
        $response['message'] = true;
    } else {
        $errorInfo = $stmt->errorInfo();
        $response['error'] = 'ไม่สามารถอัปเดตข้อมูลได้: ' . $errorInfo[2];
    }

} catch (PDOException $e) {
    $response['error'] = 'Database Error: ' . $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;