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
    // รับค่า data_tag ซึ่งส่งมาในรูปแบบ LOCATION_ID*DESCRIPTION
    $data_tag = isset($_POST['data_tag']) ? trim($_POST['data_tag']) : '';

    if (empty($data_tag)) {
        $response['error'] = 'ไม่พบข้อมูลอ้างอิงสำหรับลบรายการ';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // แยกข้อมูลด้วยเครื่องหมาย *
    $params = explode('*', $data_tag);
    $location_id = isset($params[0]) ? trim($params[0]) : '';

    if (empty($location_id)) {
        $response['error'] = 'ไม่พบรหัส Location ID ที่ต้องการลบ';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // คำสั่ง SQL DELETE ตาราง LCTNMSTR1
    $sql = "DELETE FROM LCTNMSTR1 WHERE LTRIM(RTRIM(LOCATION_ID)) = LTRIM(RTRIM(:location_id))";
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':location_id', $location_id, PDO::PARAM_STR);

    if ($stmt->execute()) {
        $response['status']  = 'success';
        $response['message'] = true;
    } else {
        $errorInfo = $stmt->errorInfo();
        $response['error'] = 'ไม่สามารถลบข้อมูลได้: ' . $errorInfo[2];
    }

} catch (PDOException $e) {
    $response['error'] = 'Database Error: ' . $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;