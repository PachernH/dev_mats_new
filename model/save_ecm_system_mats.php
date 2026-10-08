<?php
// เริ่ม session
session_start();

// กำหนด Header ส่งคืนค่าเป็นรูปแบบ JSON
header('Content-Type: application/json; charset=utf-8');

// ตั้งค่าโซนเวลามาตรฐาน
date_default_timezone_set("Asia/Bangkok");

$response = array();
$response['message'] = false;

// ตรวจสอบ Request Method
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // ดึงไฟล์เชื่อมต่อฐานข้อมูล SQL Server
    if (!file_exists('../dbcon_mats-new.php')) {
        $response['error'] = 'ไม่พบไฟล์เชื่อมต่อฐานข้อมูล (dbcon_mats-new.php)';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }
    include('../dbcon_mats-new.php');

    // --- 1. Group 1: Product Information ---
    $cn_data         = isset($_POST['cn_data']) ? trim($_POST['cn_data']) : '';
    $bn_data         = isset($_POST['bn_data']) ? trim($_POST['bn_data']) : '';
    $primary_smelt   = isset($_POST['primary_smelt']) ? trim($_POST['primary_smelt']) : '';
    $secondary_smelt = isset($_POST['secondary_smelt']) ? trim($_POST['secondary_smelt']) : '';
    $country_melt    = isset($_POST['country_melt']) ? trim($_POST['country_melt']) : '';
    $country_origin  = isset($_POST['country_origin']) ? trim($_POST['country_origin']) : '';
    $mi_data         = isset($_POST['mi_data']) ? trim($_POST['mi_data']) : '';
    $lc_data         = isset($_POST['lc_data']) ? trim($_POST['lc_data']) : '';
    $cw_actual_data  = isset($_POST['cw_actual_data']) && $_POST['cw_actual_data'] !== '' ? floatval($_POST['cw_actual_data']) : 0.0;
    
    // จัดการ DateTime Format ให้ลงฐานข้อมูล SQL Server
    $start_date_data = !empty($_POST['start_date_data']) ? date('Y-m-d H:i:s', strtotime($_POST['start_date_data'])) : null;
    $stop_date_data  = !empty($_POST['stop_date_data']) ? date('Y-m-d H:i:s', strtotime($_POST['stop_date_data'])) : null;

    // --- 2. Group 2: Specification ---
    $ay_data = isset($_POST['ay_data']) ? trim($_POST['ay_data']) : '';
    $tm_data = isset($_POST['tm_data']) ? trim($_POST['tm_data']) : '';
    $sf_data = isset($_POST['sf_data']) ? trim($_POST['sf_data']) : '';
    $mg_data = isset($_POST['mg_data']) ? trim($_POST['mg_data']) : '';
    $tn_data = isset($_POST['tn_data']) && $_POST['tn_data'] !== '' ? floatval($_POST['tn_data']) : 0.0;
    $w_data  = isset($_POST['w_data']) && $_POST['w_data'] !== '' ? floatval($_POST['w_data']) : 0.0;
    $aw_data = isset($_POST['aw_data']) && $_POST['aw_data'] !== '' ? floatval($_POST['aw_data']) : 0.0;

    // --- 3. Group 3: Edge Curl & Calculate ---
    $ec_a     = isset($_POST['ec_a']) && $_POST['ec_a'] !== '' ? floatval($_POST['ec_a']) : 0.0;
    $ec_b     = isset($_POST['ec_b']) && $_POST['ec_b'] !== '' ? floatval($_POST['ec_b']) : 0.0;
    $ec_c     = isset($_POST['ec_c']) && $_POST['ec_c'] !== '' ? floatval($_POST['ec_c']) : 0.0;
    $ec_d     = isset($_POST['ec_d']) && $_POST['ec_d'] !== '' ? floatval($_POST['ec_d']) : 0.0;
    $ec_e     = isset($_POST['ec_e']) && $_POST['ec_e'] !== '' ? floatval($_POST['ec_e']) : 0.0;
    $total_ti = isset($_POST['total_ti']) && $_POST['total_ti'] !== '' ? floatval($_POST['total_ti']) : 0.0;
    $in_ti    = isset($_POST['in_ti']) && $_POST['in_ti'] !== '' ? floatval($_POST['in_ti']) : 0.0;
    $delta_ti = isset($_POST['delta_ti']) && $_POST['delta_ti'] !== '' ? floatval($_POST['delta_ti']) : 0.0;

    // ตรวจสอบเงื่อนไขบังคับ
    if (empty($cn_data) || empty($ay_data)) {
        $response['error'] = 'กรุณากรอกรหัส COIL NO และ ALLOY ให้ครบถ้วน';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        // ตรวจสอบว่ามีข้อมูล Coil อยู่จริงหรือไม่
        $checkSql = "SELECT COUNT(*) FROM COILPROD1 WHERE COIL_NO = :coil";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->execute([':coil' => $cn_data]);
        
        if ($checkStmt->fetchColumn() <= 0) {
            $response['error'] = 'ไม่พบรหัส Coil นี้ในระบบ';
            echo json_encode($response, JSON_UNESCAPED_UNICODE);
            exit;
        }

        // เตรียมคำสั่ง UPDATE ข้อมูลครบทั้ง 3 กลุ่ม
        $sql = "UPDATE COILPROD1
                SET BATCH_NO            = :bn_data,
                    PRIMARY_SMELT       = :primary_smelt,
                    SECONDARY_SMELT     = :secondary_smelt,
                    COUNTRY_MELT        = :country_melt,
                    COUNTRY_ORIGIN      = :country_origin,
                    MATERIAL_IN         = :mi_data,
                    LOCATION_ID         = :lc_data,
                    COIL_ACTUALWEIGHT   = :cw_actual_data,
                    COIL_STARTTIME      = :start_date_data,
                    COIL_ENDTIME        = :stop_date_data,
                    
                    ALLOY               = :ay_data,
                    TEMPER              = :tm_data,
                    SURFACE_GRADE       = :sf_data,
                    METALLURGICAL_GRADE = :mg_data,
                    THICKNESS           = :tn_data,
                    WIDTH               = :w_data,
                    ACTUAL_WIDTH        = :aw_data,
                    
                    COIL_EDGECURLA      = :ec_a,
                    COIL_EDGECURLB      = :ec_b,
                    COIL_EDGECURLC      = :ec_c,
                    COIL_EDGECURLD      = :ec_d,
                    COIL_EDGECURLE      = :ec_e,
                    TOTAL_TI            = :total_ti,
                    IN_TI               = :in_ti,
                    DELTA_TI            = :delta_ti,
                    COIL_STATUS        = 'OP',
                    
                    COIL_UPDATEDATE     = GETDATE()
                WHERE COIL_NO = :coil";

        $stmt = $conn->prepare($sql);

        // Bind Parameters
        $stmt->bindValue(':bn_data', $bn_data);
        $stmt->bindValue(':primary_smelt', $primary_smelt);
        $stmt->bindValue(':secondary_smelt', $secondary_smelt);
        $stmt->bindValue(':country_melt', $country_melt);
        $stmt->bindValue(':country_origin', $country_origin);
        $stmt->bindValue(':mi_data', $mi_data);
        $stmt->bindValue(':lc_data', $lc_data);
        $stmt->bindValue(':cw_actual_data', $cw_actual_data);
        $stmt->bindValue(':start_date_data', $start_date_data);
        $stmt->bindValue(':stop_date_data', $stop_date_data);

        $stmt->bindValue(':ay_data', $ay_data);
        $stmt->bindValue(':tm_data', $tm_data);
        $stmt->bindValue(':sf_data', $sf_data);
        $stmt->bindValue(':mg_data', $mg_data);
        $stmt->bindValue(':tn_data', $tn_data);
        $stmt->bindValue(':w_data', $w_data);
        $stmt->bindValue(':aw_data', $aw_data);

        $stmt->bindValue(':ec_a', $ec_a);
        $stmt->bindValue(':ec_b', $ec_b);
        $stmt->bindValue(':ec_c', $ec_c);
        $stmt->bindValue(':ec_d', $ec_d);
        $stmt->bindValue(':ec_e', $ec_e);
        $stmt->bindValue(':total_ti', $total_ti);
        $stmt->bindValue(':in_ti', $in_ti);
        $stmt->bindValue(':delta_ti', $delta_ti);

        $stmt->bindValue(':coil', $cn_data);

        if ($stmt->execute()) {
            $response['message'] = true;
        } else {
            $errorInfo = $stmt->errorInfo();
            $response['error'] = 'ไม่สามารถบันทึกข้อมูลได้: ' . $errorInfo[2];
        }

    } catch (PDOException $e) {
        $response['error'] = 'Database Error: ' . $e->getMessage();
    }
} else {
    $response['error'] = 'รูปแบบการส่งข้อมูลไม่ถูกต้อง';
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;
?>