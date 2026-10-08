<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

$response = array(
    'message' => false,
    'status'  => 'error'
);

// ตรวจสอบว่าได้รับการส่งค่าแบบ POST มาจริงหรือไม่
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['error'] = 'Invalid Request Method';
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

// ตรวจสอบไฟล์เชื่อมต่อฐานข้อมูล SQL Server
if (!file_exists('../dbcon_mats-new.php')) {
    $response['error'] = 'ไม่พบไฟล์เชื่อมต่อฐานข้อมูล (dbcon_mats-new.php)';
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

include('../dbcon_mats-new.php');

try {
    // รับค่า data_tag จากหน้าบ้าน (ส่งมาในรูปแบบ WORK_PROCESS*PRODUCT_ID)
    $data_tag = isset($_POST['data_tag']) ? trim($_POST['data_tag']) : '';

    if (empty($data_tag)) {
        $response['error'] = 'ไม่พบข้อมูลอ้างอิงสำหรับลบรายการ';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // แยกข้อมูลด้วยเครื่องหมาย *
    $params = explode('*', $data_tag);
    $work_process = isset($params[0]) ? trim($params[0]) : '';
    $product_id   = isset($params[1]) ? trim($params[1]) : '';

    if (empty($work_process)) {
        $response['error'] = 'ไม่พบรหัส Work Process ที่ต้องการลบ';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // เตรียมคำสั่ง SQL DELETE
    // ถ้ามี PRODUCT_ID ส่งมาด้วยจะใช้เช็คเป็น Composite Key ร่วม เพื่อความถูกต้อง 100%
    if (!empty($product_id)) {
        $sql = "DELETE FROM WKPCMSTR1 
                WHERE LTRIM(RTRIM(WORK_PROCESS)) = LTRIM(RTRIM(:work_process))
                  AND LTRIM(RTRIM(PRODUCT_ID))   = LTRIM(RTRIM(:product_id))";
    } else {
        $sql = "DELETE FROM WKPCMSTR1 
                WHERE LTRIM(RTRIM(WORK_PROCESS)) = LTRIM(RTRIM(:work_process))";
    }

    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':work_process', $work_process, PDO::PARAM_STR);
    
    if (!empty($product_id)) {
        $stmt->bindValue(':product_id', $product_id, PDO::PARAM_STR);
    }

    // ประมวลผลคำสั่งลบข้อมูล
    if ($stmt->execute()) {
        $response['message'] = true;
        $response['status']  = 'success';
    } else {
        $errorInfo = $stmt->errorInfo();
        $response['error'] = 'ไม่สามารถลบข้อมูลได้: ' . $errorInfo[2];
    }

} catch (PDOException $e) {
    $response['error'] = 'Database Error: ' . $e->getMessage();
}

// ส่งผลลัพธ์กลับในรูปแบบ JSON ให้สอดคล้องกับ AJAX หน้าบ้าน
echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;