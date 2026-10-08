<?php
session_start();

// 1. ตรวจสอบการ Include ไฟล์ dbcon_mats-new.php
if (file_exists("../dbcon_mats-new.php")) {
    include("../dbcon_mats-new.php");
} elseif (file_exists("dbcon_mats-new.php")) {
    include("dbcon_mats-new.php");
} else {
    die("Error: ไม่พบไฟล์เชื่อมต่อฐานข้อมูล (dbcon_mats-new.php)");
}

// ตรวจสอบ Request Method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../coil_production_inspec_update_mats.php");
    exit();
}

$coil_no = isset($_POST['COIL_NO']) ? trim($_POST['COIL_NO']) : '';

if (empty($coil_no)) {
    echo "<script>alert('กรุณาระบุ COIL NO.'); window.history.back();</script>";
    exit();
}

// ตรวจสอบ PRODUCT STATUS (COIL_STATUS) หากไม่มีให้แจ้งเตือนและย้อนกลับ
$coil_status = isset($_POST['COIL_STATUS']) ? trim($_POST['COIL_STATUS']) : '';
if (empty($coil_status)) {
    echo "<script>alert('กรุณาเลือก PRODUCT STATUS ก่อนบันทึกข้อมูล'); window.history.back();</script>";
    exit();
}

// ฟังก์ชั่นดักจับค่าว่างทั่วไป
function get_val($key, $default = '') {
    if (!isset($_POST[$key]) || trim($_POST[$key]) === '') {
        return $default;
    }
    return trim($_POST[$key]);
}

// ฟังก์ชั่นจัดการวันที่
function get_date_val($key) {
    if (!isset($_POST[$key]) || trim($_POST[$key]) === '') {
        return date('Y-m-d H:i:s');
    }
    
    $date_str = trim($_POST[$key]);
    $timestamp = strtotime($date_str);
    if ($timestamp !== false) {
        return date('Y-m-d H:i:s', $timestamp);
    }
    
    return $date_str;
}

// -------------------------------------------------------------
// ดึงข้อมูล Operator/ผู้บันทึก โดยนำค่า $iduser_func มาพิจารณาก่อน
// -------------------------------------------------------------
$iduser_func   = get_val('IDUSER_FUNC', '');
$oin1_operator = get_val('OIN1_OPERATOR', '');

if (!empty($iduser_func)) {
    $oin1_operator = $iduser_func;
} elseif (empty($oin1_operator)) {
    if (isset($_SESSION['ID']) && !empty($_SESSION['ID'])) {
        $oin1_operator = $_SESSION['ID'];
    } elseif (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
        $oin1_operator = $_SESSION['user_id'];
    } elseif (isset($_SESSION['username']) && !empty($_SESSION['username'])) {
        $oin1_operator = $_SESSION['username'];
    } else {
        $oin1_operator = 'SYSTEM';
    }
}

$oin1_operate_date = get_date_val('OIN1_OPERATEDATE');

try {
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->beginTransaction();

    // -------------------------------------------------------------
    // 1. UPDATE ตาราง COILPROD1
    // -------------------------------------------------------------
    $sql_prod = "UPDATE COILPROD1 SET 
                    PRODUCT_REFERENCE  = :PRODUCT_REFERENCE,
                    BATCH_NO           = :BATCH_NO,
                    MATERIAL_IN        = :MATERIAL_IN,
                    CSTMSPPL_ID        = :CSTMSPPL_ID,
                    ALLOY              = :ALLOY,
                    TEMPER             = :TEMPER,
                    GRADE              = :GRADE,
                    SURFACE_GRADE      = :SURFACE_GRADE,
                    METALLURGICAL_GRADE= :METALLURGICAL_GRADE,
                    THICKNESS          = :THICKNESS,
                    WIDTH              = :WIDTH,
                    F_THICKNESS        = :F_THICKNESS,
                    F_WIDTH            = :F_WIDTH,
                    T5_TEMPERATURE     = :T5_TEMPERATURE,
                    COIL_STATUS        = :COIL_STATUS,
                    COIL_WORKPROCESS   = :COIL_WORKPROCESS,
                    COIL_NEXTPROCESS   = :COIL_NEXTPROCESS,
                    COIL_CASTWEIGHT    = :COIL_CASTWEIGHT,
                    COIL_ACTUALWEIGHT  = :COIL_ACTUALWEIGHT,
                    COIL_PRODUCEWEIGHT = :COIL_PRODUCEWEIGHT,
                    COIL_REMARK        = :COIL_REMARK,
                    EXTRA_DESCRIPTION1 = :EXTRA_DESCRIPTION1,
                    EXTRA_DESCRIPTION2 = :EXTRA_DESCRIPTION2,
                    EXTRA_DESCRIPTION3 = :EXTRA_DESCRIPTION3,
                    ACTUAL_WIDTH       = :ACTUAL_WIDTH,
                    ACT_CMMAXTHICK     = :ACT_CMMAXTHICK,
                    ACT_CMMINTHICK     = :ACT_CMMINTHICK,
                    EDGE_OPS           = :EDGE_OPS,
                    TPBT_OPS           = :TPBT_OPS,
                    EDGE_DRS           = :EDGE_DRS,
                    TPBT_DRS           = :TPBT_DRS,
                    BTWN_OPS           = :BTWN_OPS,
                    BTWN_DRS           = :BTWN_DRS,
                    TPBT_BTWN          = :TPBT_BTWN
                WHERE COIL_NO = :COIL_NO";

    $stmt_prod = $conn->prepare($sql_prod);
    $stmt_prod->execute([
        ':PRODUCT_REFERENCE' => get_val('PRODUCT_REFERENCE', ''),
        ':BATCH_NO'          => get_val('BATCH_NO', ''),
        ':MATERIAL_IN'       => get_val('MATERIAL_IN', ''),
        ':CSTMSPPL_ID'       => get_val('CSTMSPPL_ID', ''),
        ':ALLOY'             => get_val('ALLOY', ''),
        ':TEMPER'            => get_val('TEMPER', ''),
        ':GRADE'             => get_val('GRADE', ''),
        ':SURFACE_GRADE'     => get_val('SURFACE_GRADE', ''),
        ':METALLURGICAL_GRADE' => get_val('METALLURGICAL_GRADE', ''),
        ':THICKNESS'         => get_val('THICKNESS', 0),
        ':WIDTH'             => get_val('WIDTH', 0),
        ':F_THICKNESS'       => get_val('F_THICKNESS', 0),
        ':F_WIDTH'           => get_val('F_WIDTH', 0),
        ':T5_TEMPERATURE'    => get_val('T5_TEMPERATURE', ''),
        ':COIL_STATUS'       => $coil_status,
        ':COIL_WORKPROCESS'  => get_val('COIL_WORKPROCESS', ''),
        ':COIL_NEXTPROCESS'  => get_val('COIL_NEXTPROCESS', ''),
        ':COIL_CASTWEIGHT'   => get_val('COIL_CASTWEIGHT', 0),
        ':COIL_ACTUALWEIGHT' => get_val('COIL_ACTUALWEIGHT', 0),
        ':COIL_PRODUCEWEIGHT'=> get_val('COIL_PRODUCEWEIGHT', 0),
        ':COIL_REMARK'       => get_val('COIL_REMARK', ''),
        ':EXTRA_DESCRIPTION1'=> get_val('EXTRA_DESCRIPTION1', ''),
        ':EXTRA_DESCRIPTION2'=> get_val('EXTRA_DESCRIPTION2', ''),
        ':EXTRA_DESCRIPTION3'=> get_val('EXTRA_DESCRIPTION3', ''),
        ':ACTUAL_WIDTH'      => get_val('ACTUAL_WIDTH', 0),
        ':ACT_CMMAXTHICK'    => get_val('ACT_CMMAXTHICK', 0),
        ':ACT_CMMINTHICK'    => get_val('ACT_CMMINTHICK', 0),
        ':EDGE_OPS'          => get_val('EDGE_OPS', 0),
        ':TPBT_OPS'          => get_val('TPBT_OPS', ''),
        ':EDGE_DRS'          => get_val('EDGE_DRS', 0),
        ':TPBT_DRS'          => get_val('TPBT_DRS', ''),
        ':BTWN_OPS'          => get_val('BTWN_OPS', 0),
        ':BTWN_DRS'          => get_val('BTWN_DRS', 0),
        ':TPBT_BTWN'         => get_val('TPBT_BTWN', ''),
        ':COIL_NO'           => $coil_no
    ]);

    // -------------------------------------------------------------
    // 2. UPDATE หรือ INSERT ตาราง COILINSP1
    // -------------------------------------------------------------
    $chk_stmt = $conn->prepare("SELECT COUNT(*) FROM COILINSP1 WHERE COIL_NO = :coil");
    $chk_stmt->execute([':coil' => $coil_no]);
    $exists = $chk_stmt->fetchColumn();

    if ($exists > 0) {
        $sql_insp = "UPDATE COILINSP1 SET 
                        INSPECTION_DATE   = :INSPECTION_DATE,
                        OIN1_OPERATOR     = :OIN1_OPERATOR,
                        OIN1_OPERATEDATE  = :OIN1_OPERATEDATE,
                        OIN1_STATUS       = :OIN1_STATUS,
                        LINE_PROCESS      = :LINE_PROCESS,
                        MAL_CASTNO        = :MAL_CASTNO,
                        SCD_OS            = :SCD_OS,
                        SCD_DS            = :SCD_DS,
                        MDF_UPPER         = :MDF_UPPER,
                        MDF_LOWER         = :MDF_LOWER,
                        ACTUAL_THICKNESS  = :ACTUAL_THICKNESS,
                        MAXIMUM_THICKNESS = :MAXIMUM_THICKNESS,
                        MINIMUM_THICKNESS = :MINIMUM_THICKNESS,
                        OIN1_APPEARANCE   = :OIN1_APPEARANCE,
                        OIN1_DIMENSION    = :OIN1_DIMENSION,
                        PROFILE           = :PROFILE,
                        UTS               = :UTS,
                        UTS1              = :UTS1,
                        YIELD_STRENGTH    = :YIELD_STRENGTH,
                        YIELD_STRENGTH1   = :YIELD_STRENGTH1,
                        ELONGATION        = :ELONGATION,
                        ELONGATION1       = :ELONGATION1,
                        EARING            = :EARING,
                        EARING1           = :EARING1,
                        EARING_ANGLE      = :EARING_ANGLE,
                        EARING_ANGLE1     = :EARING_ANGLE1,
                        OIN1_REMARK       = :OIN1_REMARK,

                        CHECK_AL = :CHECK_AL, CHECK_FE = :CHECK_FE, CHECK_SI = :CHECK_SI,
                        CHECK_CR = :CHECK_CR, CHECK_CU = :CHECK_CU, CHECK_MN = :CHECK_MN,
                        CHECK_MG = :CHECK_MG, CHECK_ZN = :CHECK_ZN, CHECK_PB = :CHECK_PB,
                        CHECK_TI = :CHECK_TI, CHECK_AS = :CHECK_AS, CHECK_NI = :CHECK_NI,
                        CHECK_SN = :CHECK_SN, CHECK_SB = :CHECK_SB, CHECK_BE = :CHECK_BE,
                        CHECK_BI = :CHECK_BI, CHECK_CD = :CHECK_CD, CHECK_IN = :CHECK_IN,
                        GRAIN_SIZE = :GRAIN_SIZE, IN_TI = :IN_TI, DELTA_TI = :DELTA_TI,

                        CALIBRATIONFLAG_CR = :CALIBRATIONFLAG_CR, CALIBRATIONFLAG_CU = :CALIBRATIONFLAG_CU,
                        CALIBRATIONFLAG_MN = :CALIBRATIONFLAG_MN, CALIBRATIONFLAG_MG = :CALIBRATIONFLAG_MG,
                        CALIBRATIONFLAG_ZN = :CALIBRATIONFLAG_ZN, CALIBRATIONFLAG_PB = :CALIBRATIONFLAG_PB,
                        CALIBRATIONFLAG_TI = :CALIBRATIONFLAG_TI, CALIBRATIONFLAG_AS = :CALIBRATIONFLAG_AS,
                        CALIBRATIONFLAG_NI = :CALIBRATIONFLAG_NI, CALIBRATIONFLAG_SN = :CALIBRATIONFLAG_SN,
                        CALIBRATIONFLAG_SB = :CALIBRATIONFLAG_SB, CALIBRATIONFLAG_BE = :CALIBRATIONFLAG_BE,
                        CALIBRATIONFLAG_BI = :CALIBRATIONFLAG_BI, CALIBRATIONFLAG_CD = :CALIBRATIONFLAG_CD,
                        CALIBRATIONFLAG_IN = :CALIBRATIONFLAG_IN
                    WHERE COIL_NO = :COIL_NO";
    } else {
        $sql_insp = "INSERT INTO COILINSP1 (
                        COIL_NO, INSPECTION_DATE, OIN1_OPERATOR, OIN1_OPERATEDATE, OIN1_STATUS, LINE_PROCESS, MAL_CASTNO, SCD_OS, SCD_DS,
                        MDF_UPPER, MDF_LOWER, ACTUAL_THICKNESS, MAXIMUM_THICKNESS, MINIMUM_THICKNESS,
                        OIN1_APPEARANCE, OIN1_DIMENSION, PROFILE, UTS, UTS1, YIELD_STRENGTH, YIELD_STRENGTH1,
                        ELONGATION, ELONGATION1, EARING, EARING1, EARING_ANGLE, EARING_ANGLE1, OIN1_REMARK,
                        CHECK_AL, CHECK_FE, CHECK_SI, CHECK_CR, CHECK_CU, CHECK_MN, CHECK_MG, CHECK_ZN, CHECK_PB,
                        CHECK_TI, CHECK_AS, CHECK_NI, CHECK_SN, CHECK_SB, CHECK_BE, CHECK_BI, CHECK_CD, CHECK_IN,
                        GRAIN_SIZE, IN_TI, DELTA_TI,
                        CALIBRATIONFLAG_CR, CALIBRATIONFLAG_CU, CALIBRATIONFLAG_MN, CALIBRATIONFLAG_MG, CALIBRATIONFLAG_ZN,
                        CALIBRATIONFLAG_PB, CALIBRATIONFLAG_TI, CALIBRATIONFLAG_AS, CALIBRATIONFLAG_NI, CALIBRATIONFLAG_SN,
                        CALIBRATIONFLAG_SB, CALIBRATIONFLAG_BE, CALIBRATIONFLAG_BI, CALIBRATIONFLAG_CD, CALIBRATIONFLAG_IN
                    ) VALUES (
                        :COIL_NO, :INSPECTION_DATE, :OIN1_OPERATOR, :OIN1_OPERATEDATE, :OIN1_STATUS, :LINE_PROCESS, :MAL_CASTNO, :SCD_OS, :SCD_DS,
                        :MDF_UPPER, :MDF_LOWER, :ACTUAL_THICKNESS, :MAXIMUM_THICKNESS, :MINIMUM_THICKNESS,
                        :OIN1_APPEARANCE, :OIN1_DIMENSION, :PROFILE, :UTS, :UTS1, :YIELD_STRENGTH, :YIELD_STRENGTH1,
                        :ELONGATION, :ELONGATION1, :EARING, :EARING1, :EARING_ANGLE, :EARING_ANGLE1, :OIN1_REMARK,
                        :CHECK_AL, :CHECK_FE, :CHECK_SI, :CHECK_CR, :CHECK_CU, :CHECK_MN, :CHECK_MG, :CHECK_ZN, :CHECK_PB,
                        :CHECK_TI, :CHECK_AS, :CHECK_NI, :CHECK_SN, :CHECK_SB, :CHECK_BE, :CHECK_BI, :CHECK_CD, :CHECK_IN,
                        :GRAIN_SIZE, :IN_TI, :DELTA_TI,
                        :CALIBRATIONFLAG_CR, :CALIBRATIONFLAG_CU, :CALIBRATIONFLAG_MN, :CALIBRATIONFLAG_MG, :CALIBRATIONFLAG_ZN,
                        :CALIBRATIONFLAG_PB, :CALIBRATIONFLAG_TI, :CALIBRATIONFLAG_AS, :CALIBRATIONFLAG_NI, :CALIBRATIONFLAG_SN,
                        :CALIBRATIONFLAG_SB, :CALIBRATIONFLAG_BE, :CALIBRATIONFLAG_BI, :CALIBRATIONFLAG_CD, :CALIBRATIONFLAG_IN
                    )";
    }

    $stmt_insp = $conn->prepare($sql_insp);
    $stmt_insp->execute([
        ':COIL_NO'           => $coil_no,
        ':INSPECTION_DATE'   => get_date_val('INSPECTION_DATE'),
        ':OIN1_OPERATOR'     => $oin1_operator,
        ':OIN1_OPERATEDATE'  => $oin1_operate_date,
        ':OIN1_STATUS'       => $coil_status,
        ':LINE_PROCESS'      => get_val('LINE_PROCESS', ''),
        ':MAL_CASTNO'        => get_val('MAL_CASTNO', ''),
        ':SCD_OS'            => get_val('SCD_OS', 0),
        ':SCD_DS'            => get_val('SCD_DS', 0),
        ':MDF_UPPER'         => get_val('MDF_UPPER', ''),
        ':MDF_LOWER'         => get_val('MDF_LOWER', ''),
        ':ACTUAL_THICKNESS'  => get_val('ACTUAL_THICKNESS', 0),
        ':MAXIMUM_THICKNESS' => get_val('MAXIMUM_THICKNESS', 0),
        ':MINIMUM_THICKNESS' => get_val('MINIMUM_THICKNESS', 0),
        ':OIN1_APPEARANCE'   => get_val('OIN1_APPEARANCE', ''),
        ':OIN1_DIMENSION'    => get_val('OIN1_DIMENSION', ''),
        ':PROFILE'           => get_val('PROFILE', 0),
        ':UTS'               => get_val('UTS', 0),
        ':UTS1'              => get_val('UTS1', 0),
        ':YIELD_STRENGTH'    => get_val('YIELD_STRENGTH', 0),
        ':YIELD_STRENGTH1'   => get_val('YIELD_STRENGTH1', 0),
        ':ELONGATION'        => get_val('ELONGATION', 0),
        ':ELONGATION1'       => get_val('ELONGATION1', 0),
        ':EARING'            => get_val('EARING', 0),
        ':EARING1'           => get_val('EARING1', 0),
        ':EARING_ANGLE'      => get_val('EARING_ANGLE', ''),
        ':EARING_ANGLE1'     => get_val('EARING_ANGLE1', ''),
        ':OIN1_REMARK'       => get_val('OIN1_REMARK', ''),
        
        // ค่า Composition Checking Values
        ':CHECK_AL'          => get_val('CHECK_AL', 0),
        ':CHECK_FE'          => get_val('CHECK_FE', 0),
        ':CHECK_SI'          => get_val('CHECK_SI', 0),
        ':CHECK_CR'          => get_val('CHECK_CR', 0),
        ':CHECK_CU'          => get_val('CHECK_CU', 0),
        ':CHECK_MN'          => get_val('CHECK_MN', 0),
        ':CHECK_MG'          => get_val('CHECK_MG', 0),
        ':CHECK_ZN'          => get_val('CHECK_ZN', 0),
        ':CHECK_PB'          => get_val('CHECK_PB', 0),
        ':CHECK_TI'          => get_val('CHECK_TI', 0),
        ':CHECK_AS'          => get_val('CHECK_AS', 0),
        ':CHECK_NI'          => get_val('CHECK_NI', 0),
        ':CHECK_SN'          => get_val('CHECK_SN', 0),
        ':CHECK_SB'          => get_val('CHECK_SB', 0),
        ':CHECK_BE'          => get_val('CHECK_BE', 0),
        ':CHECK_BI'          => get_val('CHECK_BI', 0),
        ':CHECK_CD'          => get_val('CHECK_CD', 0),
        ':CHECK_IN'          => get_val('CHECK_IN', 0),
        ':GRAIN_SIZE'        => get_val('GRAIN_SIZE', 0),
        ':IN_TI'             => get_val('IN_TI', 0),
        ':DELTA_TI'          => get_val('DELTA_TI', 0),

        // ค่า CALIBRATIONFLAG สำหรับ 15 ธาตุ
        ':CALIBRATIONFLAG_CR' => get_val('CALIBRATIONFLAG_CR', ''),
        ':CALIBRATIONFLAG_CU' => get_val('CALIBRATIONFLAG_CU', ''),
        ':CALIBRATIONFLAG_MN' => get_val('CALIBRATIONFLAG_MN', ''),
        ':CALIBRATIONFLAG_MG' => get_val('CALIBRATIONFLAG_MG', ''),
        ':CALIBRATIONFLAG_ZN' => get_val('CALIBRATIONFLAG_ZN', ''),
        ':CALIBRATIONFLAG_PB' => get_val('CALIBRATIONFLAG_PB', ''),
        ':CALIBRATIONFLAG_TI' => get_val('CALIBRATIONFLAG_TI', ''),
        ':CALIBRATIONFLAG_AS' => get_val('CALIBRATIONFLAG_AS', ''),
        ':CALIBRATIONFLAG_NI' => get_val('CALIBRATIONFLAG_NI', ''),
        ':CALIBRATIONFLAG_SN' => get_val('CALIBRATIONFLAG_SN', ''),
        ':CALIBRATIONFLAG_SB' => get_val('CALIBRATIONFLAG_SB', ''),
        ':CALIBRATIONFLAG_BE' => get_val('CALIBRATIONFLAG_BE', ''),
        ':CALIBRATIONFLAG_BI' => get_val('CALIBRATIONFLAG_BI', ''),
        ':CALIBRATIONFLAG_CD' => get_val('CALIBRATIONFLAG_CD', ''),
        ':CALIBRATIONFLAG_IN' => get_val('CALIBRATIONFLAG_IN', '')
    ]);

    $conn->commit();

    if($coil_status=='NN'){
        echo "<script>
                alert('บันทึกข้อมูลเรียบร้อยแล้ว!');
                window.location.href = '../virtual_coil_production_inspec_detail_mats.php?COIL=" . urlencode($coil_no) . "';
            </script>";
    }else{

        echo "<script>
                alert('บันทึกข้อมูลเรียบร้อยแล้ว!');
                window.location.href = '../coil_production_inspec_detail_mats.php?COIL=" . urlencode($coil_no) . "';
              </script>";
    }     

} catch (PDOException $e) {
    if (isset($conn)) {
        $conn->rollBack();
    }
    echo "<div style='padding:20px; font-family:sans-serif;'>";
    echo "<h3 style='color:red;'>เกิดข้อผิดพลาดในการบันทึกข้อมูล (Database Error):</h3>";
    echo "<pre style='background:#f1f1f1; padding:15px; border-radius:5px;'>" . htmlspecialchars($e->getMessage()) . "</pre>";
    echo "<button onclick='window.history.back();'>กลับหน้าเดิม</button>";
    echo "</div>";
}
?>