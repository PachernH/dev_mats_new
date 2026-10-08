<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

$response = array('status' => 'error', 'message' => false);

// 1. ตรวจสอบ HTTP Method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['error'] = 'Invalid Request Method';
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

// 2. ตรวจสอบไฟล์เชื่อมต่อฐานข้อมูล
if (!file_exists('../dbcon_mats-new.php')) {
    $response['error'] = 'ไม่พบไฟล์เชื่อมต่อฐานข้อมูล (dbcon_mats-new.php)';
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

include('../dbcon_mats-new.php');

try {
    // 3. รับค่า recipe_no จาก POST
    $recipe_no = isset($_POST['recipe_no']) ? trim($_POST['recipe_no']) : '';

    if (empty($recipe_no)) {
        $response['error'] = 'ไม่พบรหัส Recipe No. ที่ต้องการลบ';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 4. ตรวจสอบจำนวน Record ก่อนลบ (เพื่อความปลอดภัยเสริมอีกชั้น)
    $checkSql = "SELECT COUNT(*) AS TOTAL_REC FROM CMRCMSTR1 WHERE LTRIM(RTRIM(RECIPE_NO)) = LTRIM(RTRIM(:rno))";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->execute([':rno' => $recipe_no]);
    $total_rec = (int)$checkStmt->fetchColumn();

    if ($total_rec === 0) {
        $response['error'] = 'ไม่พบข้อมูล Recipe No. นี้ในระบบ';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 5. ทำการลบข้อมูลของ RECIPE_NO นี้ออกจากตาราง CMRCMSTR1
    $delSql = "DELETE FROM CMRCMSTR1 WHERE LTRIM(RTRIM(RECIPE_NO)) = LTRIM(RTRIM(:rno))";
    $delStmt = $conn->prepare($delSql);
    $delStmt->execute([':rno' => $recipe_no]);

    $response['status']  = 'success';
    $response['message'] = true;

} catch (PDOException $e) {
    $response['error'] = 'Database Error: ' . $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;