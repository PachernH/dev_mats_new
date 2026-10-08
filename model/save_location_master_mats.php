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
    // 1. รับค่าจากฟอร์ม (ตาม name="data_loca" และ name="data_desc")
    $location_id = isset($_POST['data_loca']) ? strtoupper(trim($_POST['data_loca'])) : '';
    $description = isset($_POST['data_desc']) ? trim($_POST['data_desc']) : '';

    if ($location_id === '') {
        $response['error'] = 'กรุณากรอกข้อมูล Location ID ให้ครบถ้วน';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 2. ตรวจสอบข้อมูลซ้ำในตาราง LCTNMSTR1
    $checkSql = "SELECT COUNT(*) FROM LCTNMSTR1 WHERE LTRIM(RTRIM(LOCATION_ID)) = LTRIM(RTRIM(:location_id))";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bindValue(':location_id', $location_id, PDO::PARAM_STR);
    $checkStmt->execute();

    if ($checkStmt->fetchColumn() > 0) {
        $response['error'] = 'รหัส Location ID นี้มีอยู่ในระบบเรียบร้อยแล้ว ไม่สามารถเพิ่มซ้ำได้';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 3. บันทึกข้อมูลลงตาราง LCTNMSTR1
    $sql = "INSERT INTO LCTNMSTR1 (LOCATION_ID, DESCRIPTION) VALUES (:location_id, :description)";
    $stmt = $conn->prepare($sql);

    $stmt->bindValue(':location_id', $location_id, PDO::PARAM_STR);
    $stmt->bindValue(':description', $description, PDO::PARAM_STR);

    if ($stmt->execute()) {
        $response['status']  = 'success';
        $response['message'] = true;
    } else {
        $errorInfo = $stmt->errorInfo();
        $response['error'] = 'ไม่สามารถบันทึกข้อมูลได้: ' . $errorInfo[2];
    }

} catch (PDOException $e) {
    $response['error'] = 'Database Error: ' . $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;