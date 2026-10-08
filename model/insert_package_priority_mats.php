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

// ฟังก์ชันบังคับจัดรูปแบบตัวเลขขนาดเป็น 8 อักขระ ทศนิยม 3 ตำแหน่ง เช่น 110 -> 0110.000
function formatDimensionValue($val) {
    if ($val === '' || $val === null) return '0000.000';
    $num = (float)$val;
    return sprintf("%08.3f", $num);
}

try {
    // 1. รับค่าและบังคับปรับตัวอักษรเป็นตัวพิมพ์ใหญ่ UPPERCASE (แปลง cc -> CC, ht -> HT)
    $product_id        = isset($_POST['product_id']) ? strtoupper(trim($_POST['product_id'])) : '';
    $package_treatment = isset($_POST['package_treatment']) ? strtoupper(trim($_POST['package_treatment'])) : '';
    
    // 2. Format ตัวเลขระยะขนาดให้ได้รูปแบบ 0110.000
    $width_from        = isset($_POST['width_from']) ? formatDimensionValue($_POST['width_from']) : '';
    $width_to          = isset($_POST['width_to']) ? formatDimensionValue($_POST['width_to']) : '';
    $length_from       = isset($_POST['length_from']) ? formatDimensionValue($_POST['length_from']) : '';
    $length_to         = isset($_POST['length_to']) ? formatDimensionValue($_POST['length_to']) : '';

    if ($product_id === '' || $package_treatment === '' || $width_from === '' || $width_to === '' || $length_from === '' || $length_to === '') {
        $response['error'] = 'กรุณากรอกข้อมูล Header Keys ให้ครบถ้วน';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 3. รับอาร์เรย์ตาราง Priority
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

    $conn->beginTransaction();

    // เช็คคีย์ซ้ำ
    $checkSql = "SELECT COUNT(*) FROM MTRLSPCT2 
                 WHERE LTRIM(RTRIM(PRODUCT_ID))        = LTRIM(RTRIM(:pid))
                   AND LTRIM(RTRIM(PACKAGE_TREATMENT)) = LTRIM(RTRIM(:pkg))
                   AND LTRIM(RTRIM(WIDTH_FROM))        = LTRIM(RTRIM(:wf))
                   AND LTRIM(RTRIM(WIDTH_TO))          = LTRIM(RTRIM(:wt))
                   AND LTRIM(RTRIM(LENGTH_FROM))       = LTRIM(RTRIM(:lf))
                   AND LTRIM(RTRIM(LENGTH_TO))         = LTRIM(RTRIM(:lt))
                   AND PACKAGE_PRIORITY                = :prio";
                   
    $checkStmt = $conn->prepare($checkSql);

    // ประโยค INSERT
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
        if ($priority === '') continue;

        // เช็ครายการซ้ำ
        $checkStmt->execute([
            ':pid'  => $product_id,
            ':pkg'  => $package_treatment,
            ':wf'   => $width_from,
            ':wt'   => $width_to,
            ':lf'   => $length_from,
            ':lt'   => $length_to,
            ':prio' => (int)$priority
        ]);

        if ($checkStmt->fetchColumn() > 0) {
            $conn->rollBack();
            $response['error'] = "รายการ Priority ที่ $priority ของชุดคีย์นี้ ($product_id / $package_treatment / $width_from - $width_to) มีอยู่ในระบบแล้ว";
            echo json_encode($response, JSON_UNESCAPED_UNICODE);
            exit;
        }

        $mat_code = isset($material_codes[$i]) ? strtoupper(trim($material_codes[$i])) : '';
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