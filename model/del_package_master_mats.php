<?php
// model/delete_package_mats.php
session_start();

// ป้องกันข้อความชั่วคราวหลุดไปแปดเปื้อน JSON
ob_start();

header('Content-Type: application/json; charset=utf-8');

$response = array(
    'message' => false,
    'status'  => 'error'
);

// ตรวจสอบว่าได้รับการส่งค่าแบบ POST มาจริงหรือไม่
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_clean();
    $response['error'] = 'Invalid Request Method';
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

// ตรวจสอบไฟล์เชื่อมต่อฐานข้อมูล SQL Server
if (!file_exists('../dbcon_mats-new.php')) {
    ob_clean();
    $response['error'] = 'ไม่พบไฟล์เชื่อมต่อฐานข้อมูล (dbcon_mats-new.php)';
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

include('../dbcon_mats-new.php');

// ล้าง Output Buffer ก่อนเริ่มส่ง JSON
ob_clean();

try {
    // รับค่า data_tag จากหน้าบ้าน
    $data_tag = isset($_POST['data_tag']) ? trim($_POST['data_tag']) : '';

    if (empty($data_tag)) {
        echo json_encode([
            'status' => 'error',
            'error'  => 'ไม่พบข้อมูลอ้างอิงสำหรับลบรายการ'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // แยกข้อมูลด้วยเครื่องหมาย * (กรณีส่งมาเป็น MATERIAL_CODE*ITEMNUM)
    $params  = explode('*', $data_tag);
    $ma_code = isset($params[0]) ? trim($params[0]) : '';

    if (empty($ma_code)) {
        echo json_encode([
            'status' => 'error',
            'error'  => 'ไม่พบรหัส MATERIAL CODE ที่ต้องการลบ'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // เริ่มต้น Transaction เพื่อลบพร้อมกัน 2 ตาราง
    $conn->beginTransaction();

    // 1. ลบจากตารางที่ 1: MTRLMSTR1
    $sql1 = "DELETE FROM MTRLMSTR1 
             WHERE LTRIM(RTRIM(MATERIAL_CODE)) = LTRIM(RTRIM(:ma_code))";
    
    $stmt1 = $conn->prepare($sql1);
    $stmt1->bindParam(':ma_code', $ma_code, PDO::PARAM_STR);
    $stmt1->execute();

    // 2. ลบจากตารางที่ 2: MTRLSPCT1
    $sql2 = "DELETE FROM MTRLSPCT1 
             WHERE LTRIM(RTRIM(MATERIAL_CODE)) = LTRIM(RTRIM(:ma_code))";
    
    $stmt2 = $conn->prepare($sql2);
    $stmt2->bindParam(':ma_code', $ma_code, PDO::PARAM_STR);
    $stmt2->execute();

    // ยืนยันการลบข้อมูลทั้งสองตาราง
    $conn->commit();

    $response['message'] = true;
    $response['status']  = 'success';

} catch (PDOException $e) {
    // หากเกิด Error ให้ rollback คืนค่าการลบทั้งหมด
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    $response['error'] = 'Database Error: ' . $e->getMessage();
}

// ส่งผลลัพธ์กลับในรูปแบบ JSON ให้สอดคล้องกับ AJAX หน้าบ้าน
echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;