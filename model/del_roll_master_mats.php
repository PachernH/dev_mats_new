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
    // 3. รับค่า รหัส Roll No. / Recipe No.
    $recipe_no = isset($_POST['recipe_no']) ? trim($_POST['recipe_no']) : '';

    if (empty($recipe_no)) {
        $response['error'] = 'ไม่พบรหัส Roll Set (RSET_NO) ที่ต้องการลบ';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 4. ตรวจสอบสถานะก่อนลบ เพื่อป้องกันการสั่งลบรายการที่เป็น Status 'OP'
    $checkSql = "SELECT RSET_STATUS FROM RDMTMSTR3 WHERE LTRIM(RTRIM(RSET_NO)) = LTRIM(RTRIM(:rno))";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->execute([':rno' => $recipe_no]);
    $status = $checkStmt->fetchColumn();

    if ($status === false) {
        $response['error'] = "ไม่พบข้อมูล Roll Set No. ($recipe_no) ในระบบ";
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (trim($status) === 'OP') {
        $response['error'] = "ไม่สามารถลบได้ เนื่องจาก Roll Set No. ($recipe_no) มีสถานะเป็น 'OP'";
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 5. ดำเนินการลบข้อมูลแบบ Transaction
    $conn->beginTransaction();

    $delSql = "DELETE FROM RDMTMSTR3 WHERE LTRIM(RTRIM(RSET_NO)) = LTRIM(RTRIM(:rno))";
    $delStmt = $conn->prepare($delSql);
    $delStmt->execute([':rno' => $recipe_no]);

    $conn->commit();

    $response['status']  = 'success';
    $response['message'] = true;

} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    $response['error'] = 'Database Error: ' . $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;