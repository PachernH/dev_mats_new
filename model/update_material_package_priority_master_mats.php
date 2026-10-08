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
    // 1. รับค่า 6 คีย์หลัก
    $product_id        = isset($_POST['product_id']) ? trim($_POST['product_id']) : '';
    $package_treatment = isset($_POST['package_treatment']) ? trim($_POST['package_treatment']) : '';
    $width_from        = isset($_POST['width_from']) ? trim($_POST['width_from']) : '';
    $width_to          = isset($_POST['width_to']) ? trim($_POST['width_to']) : '';
    $length_from       = isset($_POST['length_from']) ? trim($_POST['length_from']) : '';
    $length_to         = isset($_POST['length_to']) ? trim($_POST['length_to']) : '';

    if ($product_id === '' || $package_treatment === '') {
        $response['error'] = 'ข้อมูลหลัก (Header Keys) ไม่ครบถ้วน';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 2. รับอาร์เรย์รายการลูก
    $pkg_priorities    = isset($_POST['pkg_priority']) ? $_POST['pkg_priority'] : [];
    $material_codes    = isset($_POST['material_code']) ? $_POST['material_code'] : [];
    $stack_rows        = isset($_POST['stack_row']) ? $_POST['stack_row'] : [];
    $stack_columns     = isset($_POST['stack_column']) ? $_POST['stack_column'] : [];
    $weightperpackages = isset($_POST['weightperpackage']) ? $_POST['weightperpackage'] : [];

    if (count($pkg_priorities) === 0) {
        $response['error'] = 'กรุณาระบุรายการ Priority อย่างน้อย 1 รายการ';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // เริ่ม Transaction เพื่อความปลอดภัยของข้อมูล
    $conn->beginTransaction();

    // 3. ลบรายการเดิมทั้งหมดของกลุ่มคีย์หลัก 6 ตัวนี้
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

    // 4. บันทึกข้อมูลรายการลูกใหม่ทั้งหมด
    $insSql = "INSERT INTO MTRLSPCT2 (
                    PRODUCT_ID, PACKAGE_TREATMENT, WIDTH_FROM, WIDTH_TO, LENGTH_FROM, LENGTH_TO,
                    PACKAGE_PRIORITY, MATERIAL_CODE, STACK_ROW, STACK_COLUMN, WEIGHTPERPACKAGE
                ) VALUES (
                    :pid, :pkg, :wf, :wt, :lf, :lt,
                    :priority, :mat_code, :s_row, :s_col, :weight
                )";

    $insStmt = $conn->prepare($insSql);

    for ($i = 0; $i < count($pkg_priorities); $i++) {
        $priority = trim($pkg_priorities[$i]);
        if ($priority === '') continue; // ข้ามรายการที่ไม่ได้กรอก priority

        $mat_code = isset($material_codes[$i]) ? trim($material_codes[$i]) : '';
        $s_row    = isset($stack_rows[$i]) && $stack_rows[$i] !== '' ? (int)$stack_rows[$i] : 0;
        $s_col    = isset($stack_columns[$i]) && $stack_columns[$i] !== '' ? (int)$stack_columns[$i] : 0;
        $weight   = isset($weightperpackages[$i]) && $weightperpackages[$i] !== '' ? (int)$weightperpackages[$i] : 0;

        $insStmt->execute([
            ':pid'      => $product_id,
            ':pkg'      => $package_treatment,
            ':wf'       => $width_from,
            ':wt'       => $width_to,
            ':lf'       => $length_from,
            ':lt'       => $length_to,
            ':priority' => (int)$priority,
            ':mat_code' => $mat_code,
            ':s_row'    => $s_row,
            ':s_col'    => $s_col,
            ':weight'   => $weight
        ]);
    }

    // ยืนยันการทำงานลง Database
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