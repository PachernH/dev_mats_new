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
    $data_tag = isset($_POST['data_tag']) ? trim($_POST['data_tag']) : '';

    if (empty($data_tag)) {
        $response['error'] = 'ไม่พบข้อมูลอ้างอิงสำหรับลบรายการ';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // แยกคีย์หลัก 6 ตัว
    $keys = explode('*', $data_tag);
    $product_id        = isset($keys[0]) ? trim($keys[0]) : '';
    $package_treatment = isset($keys[1]) ? trim($keys[1]) : '';
    $width_from        = isset($keys[2]) ? trim($keys[2]) : '';
    $width_to          = isset($keys[3]) ? trim($keys[3]) : '';
    $length_from       = isset($keys[4]) ? trim($keys[4]) : '';
    $length_to         = isset($keys[5]) ? trim($keys[5]) : '';

    if ($product_id === '' || $package_treatment === '') {
        $response['error'] = 'ข้อมูลอ้างอิงไม่ถูกต้อง';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 1. ตรวจสอบเงื่อนไข: ต้องมี PACKAGE_PRIORITY = 1 ในกลุ่มข้อมูลนี้หรือไม่
    $checkSql = "SELECT COUNT(*) FROM MTRLSPCT2 
                 WHERE LTRIM(RTRIM(PRODUCT_ID))        = LTRIM(RTRIM(:pid))
                   AND LTRIM(RTRIM(PACKAGE_TREATMENT)) = LTRIM(RTRIM(:pkg))
                   AND LTRIM(RTRIM(WIDTH_FROM))        = LTRIM(RTRIM(:wf))
                   AND LTRIM(RTRIM(WIDTH_TO))          = LTRIM(RTRIM(:wt))
                   AND LTRIM(RTRIM(LENGTH_FROM))       = LTRIM(RTRIM(:lf))
                   AND LTRIM(RTRIM(LENGTH_TO))         = LTRIM(RTRIM(:lt))
                   AND PACKAGE_PRIORITY = 1";

    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->execute([
        ':pid' => $product_id,
        ':pkg' => $package_treatment,
        ':wf'  => $width_from,
        ':wt'  => $width_to,
        ':lf'  => $length_from,
        ':lt'  => $length_to
    ]);

    if ($checkStmt->fetchColumn() == 0) {
        $response['error'] = 'ไม่สามารถลบได้: อนุญาตให้ลบได้เฉพาะรายการที่มี PACKAGE_PRIORITY เท่ากับ 1 เท่านั้น';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 2. ถ้ามี PACKAGE_PRIORITY = 1 จะทำการลบข้อมูลของชุดคีย์นี้ออกทั้งหมด
    $delSql = "DELETE FROM MTRLSPCT2 
               WHERE LTRIM(RTRIM(PRODUCT_ID))        = LTRIM(RTRIM(:pid))
                 AND LTRIM(RTRIM(PACKAGE_TREATMENT)) = LTRIM(RTRIM(:pkg))
                 AND LTRIM(RTRIM(WIDTH_FROM))        = LTRIM(RTRIM(:wf))
                 AND LTRIM(RTRIM(WIDTH_TO))          = LTRIM(RTRIM(:wt))
                 AND LTRIM(RTRIM(LENGTH_FROM))       = LTRIM(RTRIM(:lf))
                 AND LTRIM(RTRIM(LENGTH_TO))         = LTRIM(RTRIM(:lt))";

    $delStmt = $conn->prepare($delSql);
    $delStmt->execute([
        ':pid' => $product_id,
        ':pkg' => $package_treatment,
        ':wf'  => $width_from,
        ':wt'  => $width_to,
        ':lf'  => $length_from,
        ':lt'  => $length_to
    ]);

    $response['status']  = 'success';
    $response['message'] = true;

} catch (PDOException $e) {
    $response['error'] = 'Database Error: ' . $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;