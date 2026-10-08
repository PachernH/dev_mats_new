<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

$response = array('message' => false);

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
    // 1. รับค่าเงื่อนไขหลัก (Composite Keys)
    $product_id     = isset($_POST['in_pro']) ? trim($_POST['in_pro']) : '';
    $alloy          = isset($_POST['in_alloy']) ? trim($_POST['in_alloy']) : '';
    $temper_initial = isset($_POST['in_temper']) ? trim($_POST['in_temper']) : '';
    $temper_target  = isset($_POST['in_temper2']) ? trim($_POST['in_temper2']) : '';
    $range_from     = isset($_POST['in_range_f']) ? trim($_POST['in_range_f']) : '';
    $range_to       = isset($_POST['in_range_t']) ? trim($_POST['in_range_t']) : '';

    if ($product_id == '' || $alloy == '' || $temper_initial == '' || $temper_target == '' || $range_from == '' || $range_to == '') {
        $response['error'] = 'ข้อมูลหลักไม่ครบถ้วน ไม่สามารถระบุรายการที่ต้องการอัปเดตได้';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 2. รับค่าที่สามารถแก้ไขได้
    $range_type           = isset($_POST['rt']) ? trim($_POST['rt']) : '';
    $min_weight           = (isset($_POST['mw']) && $_POST['mw'] !== '') ? (float)$_POST['mw'] : 0;
    $o2puring_from        = (isset($_POST['o2p_f']) && $_POST['o2p_f'] !== '') ? (float)$_POST['o2p_f'] : 0;
    $o2puring_to          = (isset($_POST['o2p_t']) && $_POST['o2p_t'] !== '') ? (float)$_POST['o2p_t'] : 0;
    $heating_temperature  = (isset($_POST['h_tdc']) && $_POST['h_tdc'] !== '') ? (float)$_POST['h_tdc'] : 0;
    $heating_time         = (isset($_POST['h_tmc']) && $_POST['h_tmc'] !== '') ? (float)$_POST['h_tmc'] : 0;
    $soaking_temperature  = (isset($_POST['s_tdc']) && $_POST['s_tdc'] !== '') ? (float)$_POST['s_tdc'] : 0;
    $soaking_time         = (isset($_POST['s_tmc']) && $_POST['s_tmc'] !== '') ? (float)$_POST['s_tmc'] : 0;
    $workpiece_heating    = (isset($_POST['wph']) && $_POST['wph'] !== '') ? (float)$_POST['wph'] : 0;
    $workpiece_soakingfrom= (isset($_POST['wps_f']) && $_POST['wps_f'] !== '') ? (float)$_POST['wps_f'] : 0;
    $workpiece_soakingto  = (isset($_POST['wps_t']) && $_POST['wps_t'] !== '') ? (float)$_POST['wps_t'] : 0;
    $o2_percentagefrom    = (isset($_POST['o2_f']) && $_POST['o2_f'] !== '') ? (float)$_POST['o2_f'] : 0;
    $o2_percentageto      = (isset($_POST['o2_t']) && $_POST['o2_t'] !== '') ? (float)$_POST['o2_t'] : 0;
    $program              = isset($_POST['pg']) ? trim($_POST['pg']) : '';

    // SQL คำสั่ง UPDATE ตาราง TPMAT1001
    $sql = "UPDATE TPMAT1001 
            SET RANGE_TYPE            = :range_type,
                MIN_WEIGHT            = :min_weight,
                O2PURING_FROM         = :o2puring_from,
                O2PURING_TO           = :o2puring_to,
                HEATING_TEMPERATURE   = :heating_temperature,
                HEATING_TIME          = :heating_time,
                SOAKING_TEMPERATURE   = :soaking_temperature,
                SOAKING_TIME          = :soaking_time,
                WORKPIECE_HEATING     = :workpiece_heating,
                WORKPIECE_SOAKINGFROM = :workpiece_soakingfrom,
                WORKPIECE_SOAKINGTO   = :workpiece_soakingto,
                O2_PERCENTAGEFROM     = :o2_percentagefrom,
                O2_PERCENTAGETO       = :o2_percentageto,
                PROGRAM               = :program
            WHERE LTRIM(RTRIM(PRODUCT_ID))     = LTRIM(RTRIM(:product_id))
              AND LTRIM(RTRIM(ALLOY))          = LTRIM(RTRIM(:alloy))
              AND LTRIM(RTRIM(TEMPER_INITIAL)) = LTRIM(RTRIM(:temper_initial))
              AND LTRIM(RTRIM(TEMPER_TARGET))  = LTRIM(RTRIM(:temper_target))
              AND LTRIM(RTRIM(RANGE_FROM))     = LTRIM(RTRIM(:range_from))
              AND LTRIM(RTRIM(RANGE_TO))       = LTRIM(RTRIM(:range_to))";

    $stmt = $conn->prepare($sql);

    // Bind ค่าข้อมูลแก้ไข
    $stmt->bindValue(':range_type', $range_type);
    $stmt->bindValue(':min_weight', $min_weight);
    $stmt->bindValue(':o2puring_from', $o2puring_from);
    $stmt->bindValue(':o2puring_to', $o2puring_to);
    $stmt->bindValue(':heating_temperature', $heating_temperature);
    $stmt->bindValue(':heating_time', $heating_time);
    $stmt->bindValue(':soaking_temperature', $soaking_temperature);
    $stmt->bindValue(':soaking_time', $soaking_time);
    $stmt->bindValue(':workpiece_heating', $workpiece_heating);
    $stmt->bindValue(':workpiece_soakingfrom', $workpiece_soakingfrom);
    $stmt->bindValue(':workpiece_soakingto', $workpiece_soakingto);
    $stmt->bindValue(':o2_percentagefrom', $o2_percentagefrom);
    $stmt->bindValue(':o2_percentageto', $o2_percentageto);
    $stmt->bindValue(':program', $program);

    // Bind เงื่อนไข Composite Keys
    $stmt->bindValue(':product_id', $product_id);
    $stmt->bindValue(':alloy', $alloy);
    $stmt->bindValue(':temper_initial', $temper_initial);
    $stmt->bindValue(':temper_target', $temper_target);
    $stmt->bindValue(':range_from', $range_from);
    $stmt->bindValue(':range_to', $range_to);

    if ($stmt->execute()) {
        $response['message'] = true;
        $response['status'] = 'success';
    } else {
        $errorInfo = $stmt->errorInfo();
        $response['error'] = 'ไม่สามารถอัปเดตข้อมูลได้: ' . $errorInfo[2];
    }

} catch (PDOException $e) {
    $response['error'] = 'Database Error: ' . $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;