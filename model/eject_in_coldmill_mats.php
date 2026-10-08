<?php
// model/eject_in_coldmill_mats.php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['ID'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit();
}

include '../dbcon_mats-new.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit();
}

$coil_no       = isset($_POST['coil_no']) ? trim($_POST['coil_no']) : '';
$slot_index    = isset($_POST['slot_index']) ? intval($_POST['slot_index']) : 0;
$area_type     = isset($_POST['area_type']) ? strtoupper(trim($_POST['area_type'])) : 'I';
$is_new_coil   = isset($_POST['is_new_coil']) ? intval($_POST['is_new_coil']) : 0;
$confirm_eject = isset($_POST['confirm_eject']) ? intval($_POST['confirm_eject']) : 0; // 0 = เช็คสถานะ, 1 = ยืนยันทำงานจริง
$user_operate  = isset($_SESSION['ID']) ? $_SESSION['ID'] : 'SYSTEM';

if (empty($coil_no)) {
    echo json_encode(['status' => 'error', 'message' => 'Coil No is required']);
    exit();
}

try {
    // 1. ดึงข้อมูลจาก CDMLMCHN1 เพื่อเช็ค COUNT_PROCESS และ PASS
    $stmt = $conn->prepare("SELECT * FROM CDMLMCHN1 WITH (NOLOCK) WHERE COIL_NO = :coil_no");
    $stmt->execute([':coil_no' => $coil_no]);
    $mchn = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$mchn) {
        echo json_encode(['status' => 'success', 'message' => "Coil [{$coil_no}] removed (Not found in DB)"]);
        exit();
    }

    $count_process = intval($mchn['COUNT_PROCESS'] ?? 0);
    $pass_no       = intval($mchn['CURRENT_PASS'] ?? 0);
    $total_pass    = intval($mchn['TOTAL_PASS'] ?? 0);

    // ตรวจสอบว่ารีดเสร็จสมบูรณ์แล้วหรือไม่ (CURRENT_PASS >= TOTAL_PASS)
    $is_pass_completed = ($total_pass > 0 && $pass_no >= $total_pass);

    // =========================================================================
    // ขั้นที่ 1: ตรวจสอบกรณีส่งมาเช็คสถานะจากหน้าบ้าน (ยังไม่ได้ยืนยันกด EJECT จริง)
    // =========================================================================
    if ($confirm_eject === 0) {
        if ($is_pass_completed) {
            // บังคับว่าต้องสร้าง Coil M เนื่องจากรีดจบทุก Pass แล้ว
            echo json_encode([
                'status'             => 'check', 
                'is_pass_completed'  => true, 
                'require_choice'     => false, 
                'count_process'      => $count_process
            ]);
        } else if ($count_process >= 1 && $pass_no > 0) {
            // ให้เลือกได้ระหว่างสร้างหรือไม่สร้าง Coil M
            echo json_encode([
                'status'             => 'check', 
                'is_pass_completed'  => false, 
                'require_choice'     => true, 
                'count_process'      => $count_process
            ]);
        } else {
            // COUNT_PROCESS = 0 ลบออกได้ทันที
            echo json_encode([
                'status'             => 'check', 
                'is_pass_completed'  => false, 
                'require_choice'     => false, 
                'count_process'      => $count_process
            ]);
        }
        exit();
    }

    // =========================================================================
    // ขั้นที่ 2: ยืนยันการ EJECT (confirm_eject = 1)
    // =========================================================================

    // ----------------------------------------------------------------------
    // หากเลือกรีดครบ Pass แล้ว ($is_pass_completed) ให้บังคับค่า $is_new_coil = 1
    // ----------------------------------------------------------------------
    if ($is_pass_completed) {
        $is_new_coil = 1;
    }

    // ----------------------------------------------------------------------
    // เงื่อนไขพิเศษ: หาก COUNT_PROCESS = 0 (ยังไม่ได้ทำอะไรเลย)
    // -> ให้ลบออกจาก CDMLMCHN1 และ CDMLMCHN0 ทันที โดยไม่ INSERT/UPDATE ตารางอื่น
    // ----------------------------------------------------------------------
    if ($count_process <= 0 || $pass_no <= 0) {
        $conn->beginTransaction();

        $del1 = $conn->prepare("DELETE FROM CDMLMCHN1 WHERE COIL_NO = :coil_no");
        $del1->execute([':coil_no' => $coil_no]);

        $del0 = $conn->prepare("DELETE FROM CDMLMCHN0 WHERE COIL_NO = :coil_no");
        $del0->execute([':coil_no' => $coil_no]);

        $conn->commit();

        echo json_encode([
            'status'     => 'success',
            'message'    => "นำ Coil [{$coil_no}] ออกจากระบบเรียบร้อยแล้ว (ยังไม่ได้เริ่มกระบวนการรีด COUNT_PROCESS = 0)",
            'product_no' => ''
        ]);
        exit();
    }

    // ----------------------------------------------------------------------
    // กรณี COUNT_PROCESS >= 1 -> ประมวลผลตามตรรกะปกติ
    // ----------------------------------------------------------------------
    $conn->beginTransaction();

    // หากผู้ใช้เลือกรีดเสร็จ หรือถูกบังคับเนื่องจากรีดครบ Pass ให้ Auto Generate หมายเลข CYYMMDD-M-XX
    $product_no = "";
    if ($is_new_coil === 1) {
        $current_yymmdd = date('ymd');
        $prefix_pattern = "C" . $current_yymmdd . "-M-%";

        $stmt_find_max = $conn->prepare("SELECT TOP 1 COIL_NO FROM COILPROD1 WITH (NOLOCK) 
                                         WHERE COIL_NO LIKE :prefix 
                                         ORDER BY COIL_NO DESC");
        $stmt_find_max->execute([':prefix' => $prefix_pattern]);
        $last_coil = $stmt_find_max->fetch(PDO::FETCH_ASSOC);

        $next_run_no = 1;
        if ($last_coil && !empty($last_coil['COIL_NO'])) {
            $last_no_str = substr(trim($last_coil['COIL_NO']), -2);
            if (is_numeric($last_no_str)) {
                $next_run_no = intval($last_no_str) + 1;
            }
        }

        $product_no = sprintf("C%s-M-%02d", $current_yymmdd, $next_run_no);
    }

    // ดึงข้อมูลจาก COILPROD1 เดิม
    $stmt_cp = $conn->prepare("SELECT * FROM COILPROD1 WITH (NOLOCK) WHERE COIL_NO = :coil_no");
    $stmt_cp->execute([':coil_no' => $coil_no]);
    $cp = $stmt_cp->fetch(PDO::FETCH_ASSOC);

    if (!$cp) {
        $conn->rollBack();
        echo json_encode(['status' => 'error', 'message' => "ไม่พบข้อมูล Coil [{$coil_no}] ในตาราง COILPROD1"]);
        exit();
    }

    $job_process    = $mchn['JOB_PROCESS'] ?? '';
    $alloy          = $mchn['ALLOY'] ?? '';
    $org_thick      = round(floatval($mchn['THICKNESS_ORIGINAL'] ?? 0), 3);
    $thick          = round(floatval($mchn['THICKNESS'] ?? 0), 3);
    $thick_final    = round(floatval($mchn['THICKNESS_FINAL'] ?? 0), 3);
    $width          = round(floatval($mchn['WIDTH'] ?? 0), 3);
    $recipe_no      = $mchn['RECIPE_NO'] ?? '';
    $recipe_item    = intval($mchn['RECIPE_ITEM'] ?? 0);
    $temper         = $mchn['TEMPER_FINISH'] ?? '';
    $actual_weight  = floatval($mchn['CDML_ACTUALWEIGHT'] ?? 0);
    $start_datetime = $mchn['CDML_STARTDATETIME'] ?? date('Y-m-d H:i:s');
    $end_datetime   = $mchn['CDML_ENDDATETIME'] ?? date('Y-m-d H:i:s');
    $start_date     = date('Y-m-d', strtotime($start_datetime));
    $end_date       = date('Y-m-d', strtotime($end_datetime));

    $sg = $cp['SURFACE_GRADE'] ?? '';
    $mg = $cp['METALLURGICAL_GRADE'] ?? '';

    if ($alloy === '1002' && $thick_final < 3 && $sg === 'SGR' && $mg === 'MG6') {
        $sg = 'SG3';
        $mg = 'MG3';
    }

    $work_process = $cp['COIL_WORKPROCESS'] ?? '';
    $next_process = '';
    if (strpos($work_process, 'CM>') !== false) {
        $parts = explode('CM>', $work_process);
        $next_process = substr(trim($parts[1]), 0, 2);
    }
    $coil_status = ($next_process === 'I2') ? 'OP' : 'AC';

    // ดึงข้อมูลจาก JOBORDER1
    $stmt_job = $conn->prepare("SELECT JOB_COLDMILLWEIGHT, JOB_COLDMILLPIECE FROM JOBORDER1 WITH (NOLOCK) WHERE JOB_ORDER = :job");
    $stmt_job->execute([':job' => $job_process]);
    $job = $stmt_job->fetch(PDO::FETCH_ASSOC);

    $cm_weight = round(floatval($job['JOB_COLDMILLWEIGHT'] ?? 0));
    $cm_piece  = round(floatval($job['JOB_COLDMILLPIECE'] ?? 0));

    if (!empty($product_no)) {
        $acc_cm_weight  = $cm_weight + $actual_weight;
        $acc_cm_piece   = $cm_piece + 1;
        $coil_cm_weight = $actual_weight;
    } else {
        $acc_cm_weight  = $cm_weight;
        $acc_cm_piece   = $cm_piece;
        $coil_cm_weight = 0;
    }

    if ($thick == $thick_final) {
        $stmt_upd_job = $conn->prepare("UPDATE JOBORDER1 SET JOB_COLDMILLWEIGHT = ?, JOB_COLDMILLPIECE = ? WHERE JOB_ORDER = ?");
        $stmt_upd_job->execute([$acc_cm_weight, $acc_cm_piece, $job_process]);
    }

    // -------------------------------------------------------------------------
    // แยกกรณีมี product_no (สร้าง Master Coil M ใหม่) หรือ รีดปกติ (ไม่สร้าง Coil M)
    // -------------------------------------------------------------------------
    if (!empty($product_no)) {
        // --- INSERT CDMLPROD1 ---
        $stmt_ins_cdml = $conn->prepare("INSERT INTO CDMLPROD1 (
            COIL_NO, PASS_NO, TEMPER, THICKNESS, CDML_STARTDATE, CDML_STARTTIME,
            CDML_ENDDATE, CDML_ENDTIME, CDML_ACCEPTWEIGHT, CDML_OPERATOR1, CDML_OPERATEDATE
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt_ins_cdml->execute([
            $product_no, $pass_no, $temper, $thick, $start_date, $start_datetime,
            $end_date, $end_datetime, $actual_weight, $user_operate, date('Y-m-d H:i:s')
        ]);

        // --- INSERT COILPROD1 ---
        $stmt_ins_cp = $conn->prepare("INSERT INTO COILPROD1 (
            COIL_NO, PRODUCT_REFERENCE, SALEORDER_NO, SALEORDER_ITEM, JOB_ORDER, JOB_PROCESS,
            BATCH_NO, PRIMARY_SMELT, SECONDARY_SMELT, COUNTRY_MELT, COUNTRY_ORIGIN, MP2_PARTNO,
            MATERIAL_IN, CSTMSPPL_ID, COIL_TYPE, PRODUCT_ID, LINE_PROCESS, CSRSET_NO, RSET_NO, RECIPE_NO,
            RECIPE_ITEM, TOTAL_PASS, CURRENT_PASS, THICKNESS_FINAL, F_ALLOY, F_TEMPER, F_GRADE, F_THICKNESS,
            F_WIDTH, ALLOY, TEMPER, GRADE, SURFACE_GRADE, METALLURGICAL_GRADE, T5_TEMPERATURE, THICKNESS,
            WIDTH, ACTUAL_WIDTH, CDML_STATUS, COIL_WORKPROCESS, COIL_NEXTPROCESS, USE_FORPROCESS,
            COIL_ACTUALWEIGHT, COIL_FIRSTWEIGHT, COIL_COLDMILLWEIGHT, COIL_BALANCEWEIGHT, COIL_STARTDATE,
            COIL_STARTTIME, COIL_ENDDATE, COIL_ENDTIME, COIL_OPERATOR1, COIL_OPERATEDATE, COIL_REMARK,
            LABEL_CODE, EXTRA_DESCRIPTION1, EXTRA_DESCRIPTION2, EXTRA_DESCRIPTION3, EDGE_OPS, TPBT_OPS,
            EDGE_DRS, TPBT_DRS, BTWN_OPS, BTWN_DRS, TPBT_BTWN, COIL_STATUS
        ) VALUES (
            ?, ?, '', '', '', ?,
            ?, ?, ?, ?, 'THAILAND', ?,
            ?, ?, ?, 'CO', 'M', ?, ?, ?,
            ?, ?, ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?, ?, ?,
            ?, ?, 'O', ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?
        )");

        $stmt_ins_cp->execute([
            $product_no, $coil_no, $job_process,
            $cp['BATCH_NO'] ?? '', $cp['PRIMARY_SMELT'] ?? '', $cp['SECONDARY_SMELT'] ?? '', $cp['COUNTRY_MELT'] ?? '', $cp['MP2_PARTNO'] ?? '',
            $cp['MATERIAL_IN'] ?? '', $cp['CSTMSPPL_ID'] ?? '', $cp['COIL_TYPE'] ?? '', $cp['CSRSET_NO'] ?? '', $cp['RSET_NO'] ?? '', $recipe_no,
            $recipe_item, $total_pass, $pass_no, $thick_final, $alloy, $temper, $cp['GRADE'] ?? '', $thick,
            $width, $alloy, $temper, $cp['GRADE'] ?? '', $sg, $mg, $cp['T5_TEMPERATURE'] ?? '', $thick,
            $width, floatval($cp['ACTUAL_WIDTH'] ?? 0), $work_process, $next_process, $cp['USE_FORPROCESS'] ?? '',
            $actual_weight, $actual_weight, $coil_cm_weight, $actual_weight, $start_date,
            $start_datetime, $end_date, $end_datetime, $user_operate, date('Y-m-d H:i:s'), $cp['COIL_REMARK'] ?? '',
            $cp['LABEL_CODE'] ?? '', $cp['EXTRA_DESCRIPTION1'] ?? '', $cp['EXTRA_DESCRIPTION2'] ?? '', $cp['EXTRA_DESCRIPTION3'] ?? '', intval($cp['EDGE_OPS'] ?? 0), $cp['TPBT_OPS'] ?? '',
            intval($cp['EDGE_DRS'] ?? 0), $cp['TPBT_DRS'] ?? '', intval($cp['BTWN_OPS'] ?? 0), intval($cp['BTWN_DRS'] ?? 0), $cp['TPBT_BTWN'] ?? '', $coil_status
        ]);

        // --- Copy Inspection Data (ถ้ามี) ---
        $stmt_insp = $conn->prepare("SELECT * FROM COILINSP1 WITH (NOLOCK) WHERE COIL_NO = :coil_no");
        $stmt_insp->execute([':coil_no' => $coil_no]);
        $insp = $stmt_insp->fetch(PDO::FETCH_ASSOC);

        if ($insp) {
            $stmt_ins_insp = $conn->prepare("INSERT INTO COILINSP1 (
                COIL_NO, INSPECTION_DATE, LINE_PROCESS, CHECK_AL, CHECK_FE, CHECK_SI, CHECK_CR,
                CHECK_CU, CHECK_MN, CHECK_MG, CHECK_ZN, CHECK_PB, CHECK_TI, CHECK_AS, CHECK_NI,
                CHECK_SN, CHECK_SB, CHECK_BE, CHECK_BI, CHECK_CD, CHECK_IN, CALIBRATIONFLAG_CR,
                CALIBRATIONFLAG_CU, CALIBRATIONFLAG_MN, CALIBRATIONFLAG_MG, CALIBRATIONFLAG_ZN,
                CALIBRATIONFLAG_PB, CALIBRATIONFLAG_TI, CALIBRATIONFLAG_AS, CALIBRATIONFLAG_NI,
                CALIBRATIONFLAG_SN, CALIBRATIONFLAG_SB, CALIBRATIONFLAG_BE, CALIBRATIONFLAG_BI,
                CALIBRATIONFLAG_CD, CALIBRATIONFLAG_IN, TOTAL_TI, IN_TI, DELTA_TI, MDF_UPPER, MDF_LOWER,
                GRAIN_SIZE, ACTUAL_THICKNESS, OIN1_REMARK, OIN1_OPERATOR, OIN1_OPERATEDATE,
                OIN1_STATUS, OIN1_APPEARANCE, OIN1_DIMENSION
            ) VALUES (
                ?, ?, 'M', ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?, ?
            )");

            $stmt_ins_insp->execute([
                $product_no, $insp['INSPECTION_DATE'] ?? date('Y-m-d'), $insp['CHECK_AL'] ?? 0, $insp['CHECK_FE'] ?? 0, $insp['CHECK_SI'] ?? 0, $insp['CHECK_CR'] ?? 0,
                $insp['CHECK_CU'] ?? 0, $insp['CHECK_MN'] ?? 0, $insp['CHECK_MG'] ?? 0, $insp['CHECK_ZN'] ?? 0, $insp['CHECK_PB'] ?? 0, $insp['CHECK_TI'] ?? 0, $insp['CHECK_AS'] ?? 0, $insp['CHECK_NI'] ?? 0,
                $insp['CHECK_SN'] ?? 0, $insp['CHECK_SB'] ?? 0, $insp['CHECK_BE'] ?? 0, $insp['CHECK_BI'] ?? 0, $insp['CHECK_CD'] ?? 0, $insp['CHECK_IN'] ?? 0, $insp['CALIBRATIONFLAG_CR'] ?? '0',
                $insp['CALIBRATIONFLAG_CU'] ?? '0', $insp['CALIBRATIONFLAG_MN'] ?? '0', $insp['CALIBRATIONFLAG_MG'] ?? '0', $insp['CALIBRATIONFLAG_ZN'] ?? '0',
                $insp['CALIBRATIONFLAG_PB'] ?? '0', $insp['CALIBRATIONFLAG_TI'] ?? '0', $insp['CALIBRATIONFLAG_AS'] ?? '0', $insp['CALIBRATIONFLAG_NI'] ?? '0',
                $insp['CALIBRATIONFLAG_SN'] ?? '0', $insp['CALIBRATIONFLAG_SB'] ?? '0', $insp['CALIBRATIONFLAG_BE'] ?? '0', $insp['CALIBRATIONFLAG_BI'] ?? '0',
                $insp['CALIBRATIONFLAG_CD'] ?? '0', $insp['CALIBRATIONFLAG_IN'] ?? '0', $insp['TOTAL_TI'] ?? 0, $insp['IN_TI'] ?? 0, $insp['DELTA_TI'] ?? 0, $insp['MDF_UPPER'] ?? '', $insp['MDF_LOWER'] ?? '',
                $insp['GRAIN_SIZE'] ?? 0, $thick, $insp['OIN1_REMARK'] ?? '', $insp['OIN1_OPERATOR'] ?? '', $insp['OIN1_OPERATEDATE'] ?? date('Y-m-d'),
                $insp['OIN1_STATUS'] ?? '', $insp['OIN1_APPEARANCE'] ?? '', $insp['OIN1_DIMENSION'] ?? ''
            ]);
        }

        // UPDATE COILPROD1 เดิม
        $stmt_upd_cp = $conn->prepare("UPDATE COILPROD1 SET RECIPE_ITEM = ?, CURRENT_PASS = ?, THICKNESS = ?, COIL_NEXTPROCESS = 'NN', COIL_STATUS = 'CM' WHERE COIL_NO = ?");
        $stmt_upd_cp->execute([$recipe_item, $pass_no, $org_thick, $coil_no]);

    } else {
        // --- กรณีเลือกรีดไม่เสร็จ: นำออกโดยไม่สร้าง Coil M ---
        $stmt_chk_prod = $conn->prepare("SELECT COIL_NO FROM CDMLPROD1 WITH (NOLOCK) WHERE COIL_NO = :coil_no");
        $stmt_chk_prod->execute([':coil_no' => $coil_no]);

        if ($stmt_chk_prod->fetch()) {
            $stmt_upd_cdml = $conn->prepare("UPDATE CDMLPROD1 SET 
                PASS_NO = ?, TEMPER = ?, THICKNESS = ?, CDML_STARTDATE = ?, CDML_STARTTIME = ?,
                CDML_ENDDATE = ?, CDML_ENDTIME = ?, CDML_ACCEPTWEIGHT = ?, CDML_UPDATE = ?, CDML_UPDATEDATE = ? 
                WHERE COIL_NO = ?");
            $stmt_upd_cdml->execute([
                $pass_no, $temper, $thick, $start_date, $start_datetime,
                $end_date, $end_datetime, $actual_weight, $user_operate, date('Y-m-d H:i:s'), $coil_no
            ]);
        } else {
            $stmt_ins_cdml = $conn->prepare("INSERT INTO CDMLPROD1 (
                COIL_NO, PASS_NO, TEMPER, THICKNESS, CDML_STARTDATE, CDML_STARTTIME,
                CDML_ENDDATE, CDML_ENDTIME, CDML_ACCEPTWEIGHT, CDML_OPERATOR1, CDML_OPERATEDATE
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt_ins_cdml->execute([
                $coil_no, $pass_no, $temper, $thick, $start_date, $start_datetime,
                $end_date, $end_datetime, $actual_weight, $user_operate, date('Y-m-d H:i:s')
            ]);
        }

        // UPDATE COILPROD1 เดิม
        $stmt_upd_cp = $conn->prepare("UPDATE COILPROD1 SET RECIPE_ITEM = ?, CURRENT_PASS = ?, THICKNESS = ?, COIL_NEXTPROCESS = 'CM' WHERE COIL_NO = ?");
        $stmt_upd_cp->execute([$recipe_item, $pass_no, $thick, $coil_no]);
    }

    // ลบข้อมูลจาก CDMLMCHN1 และ CDMLMCHN0 เพื่อเคลียร์ช่องให้ว่าง
    $del1 = $conn->prepare("DELETE FROM CDMLMCHN1 WHERE COIL_NO = :coil_no");
    $del1->execute([':coil_no' => $coil_no]);

    $del0 = $conn->prepare("DELETE FROM CDMLMCHN0 WHERE COIL_NO = :coil_no");
    $del0->execute([':coil_no' => $coil_no]);

    $conn->commit();

    $msg = !empty($product_no) 
        ? "Eject Coil [{$coil_no}] กรณี สร้าง Master Coil M [{$product_no}] เรียบร้อยแล้ว" 
        : "Eject Coil [{$coil_no}] กรณี ไม่สร้าง Coil M เรียบร้อยแล้ว";

    echo json_encode([
        'status'     => 'success',
        'message'    => $msg,
        'product_no' => $product_no
    ]);

} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(['status' => 'error', 'message' => 'Database Transaction Failed: ' . $e->getMessage()]);
}
?>