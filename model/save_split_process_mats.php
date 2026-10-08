<?php
// model/save_split_process_mats.php
session_start();
include("../dbcon_mats-new.php");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../split_process_mats.php");
    exit();
}

$main_coil_no    = isset($_POST['coil_no']) ? trim($_POST['coil_no']) : '';
$split_thickness = isset($_POST['split_thickness']) ? (float)$_POST['split_thickness'] : 0;
$split_weight    = isset($_POST['split_weight']) ? (float)$_POST['split_weight'] : 0;
$func            = isset($_POST['func']) ? trim($_POST['func']) : '';

if (empty($main_coil_no) || $split_weight <= 0) {
    echo "<script>alert('ข้อมูลไม่ถูกต้อง'); window.history.back();</script>";
    exit();
}

$current_user     = $_SESSION['ID'] ?? 'SYSTEM';
$now_dt           = new DateTime();
$current_datetime = $now_dt->format('Y-m-d H:i:s');
$current_date     = $now_dt->format('Y-m-d');

// 1. Format CYYMMDD (เช่น C260909)
$yymmdd          = $now_dt->format('ymd'); 

try {
    $conn->beginTransaction();

    // ==========================================
    // 1. ค้นหาข้อมูล Coil หลัก จาก COILPROD1
    // ==========================================
    $sql_main = "SELECT COIL_NO, PRODUCT_REFERENCE, BATCH_NO, PRIMARY_SMELT, SECONDARY_SMELT, COUNTRY_MELT, 
                        COUNTRY_ORIGIN, MATERIAL_IN, SALEORDER_NO, SALEORDER_ITEM, JOB_ORDER, JOB_PROCESS, CSTMSPPL_ID,
                        PRODUCT_ID, LINE_PROCESS, RSET_NO, RECIPE_NO, RECIPE_ITEM, TOTAL_PASS, CURRENT_PASS, THICKNESS_FINAL,
                        SUBCONTACT, LOCATION_ID, F_ALLOY, F_TEMPER, F_GRADE, F_THICKNESS, F_WIDTH, ALLOY, TEMPER, GRADE, 
                        SURFACE_GRADE, METALLURGICAL_GRADE, THICKNESS, WIDTH, ACTUAL_WIDTH, T5_TEMPERATURE, CDML_STATUS,
                        COIL_WORKPROCESS, COIL_NEXTPROCESS, USE_FORPROCESS, COIL_COLDMILLWEIGHT, COIL_BATCHANNEALWEIGHT,
                        COIL_ACTUALWEIGHT, COIL_FIRSTWEIGHT, COIL_BALANCEWEIGHT, COIL_STARTDATE, COIL_STARTTIME, COIL_ENDDATE,
                        COIL_ENDTIME, PRODUCE_FLAG, COIL_LABEL, LABEL_CODE, EXTRA_DESCRIPTION1, EXTRA_DESCRIPTION2,
                        EXTRA_DESCRIPTION3, EDGE_OPS, TPBT_OPS, EDGE_DRS, TPBT_DRS, BTWN_OPS, BTWN_DRS, TPBT_BTWN,
                        COIL_REMARK, COIL_OPERATOR1, COIL_OPERATEDATE, COIL_STATUS
                 FROM [MATS-TEST].dbo.COILPROD1 AS c
                 WHERE COIL_NO = :coil_no";
    
    $stmt_main = $conn->prepare($sql_main);
    $stmt_main->execute([':coil_no' => $main_coil_no]);
    $mainData = $stmt_main->fetch(PDO::FETCH_ASSOC);

    if (!$mainData) {
        throw new Exception("ไม่พบข้อมูล Coil หลัก: " . $main_coil_no);
    }

    // ==========================================
    // 2. สร้าง New Coil NO (Format: CYYMMDD-S-RUNNING(2หลัก) Running รายวัน)
    // ==========================================
    $prefix = "C" . $yymmdd . "-S-";
    $sql_running = "SELECT MAX(COIL_NO) AS max_coil FROM COILPROD1 WHERE COIL_NO LIKE :prefix";
    $stmt_running = $conn->prepare($sql_running);
    $stmt_running->execute([':prefix' => $prefix . '%']);
    $max_coil = $stmt_running->fetchColumn();

    if ($max_coil) {
        $last_seq = (int)substr($max_coil, -2);
        $new_seq = str_pad($last_seq + 1, 2, '0', STR_PAD_LEFT);
    } else {
        $new_seq = '01';
    }
    $new_coil_no = $prefix . $new_seq;

    // ตรวจสอบเงื่อนไข Weight ของ Coil หลัก
    $main_coldmill_wt     = (float)($mainData['COIL_COLDMILLWEIGHT'] ?? 0);
    $main_batchanneal_wt  = (float)($mainData['COIL_BATCHANNEALWEIGHT'] ?? 0);
    $is_coldmill_valid    = ($main_coldmill_wt > 0);
    $is_batchanneal_valid = ($main_batchanneal_wt > 0);

    // ==========================================
    // 3. เตรียมข้อมูลสำหรับ New Coil
    // ==========================================
    $newData = $mainData;
    $newData['COIL_NO']           = $new_coil_no;
    $newData['PRODUCT_REFERENCE'] = $main_coil_no;

    // น้ำหนัก Coil ใหม่ = split_weight
    $newData['COIL_COLDMILLWEIGHT']    = $is_coldmill_valid ? $split_weight : 0;
    $newData['COIL_BATCHANNEALWEIGHT'] = $is_batchanneal_valid ? $split_weight : 0;
    $newData['COIL_ACTUALWEIGHT']      = $split_weight;
    $newData['COIL_FIRSTWEIGHT']       = $split_weight;
    $newData['COIL_BALANCEWEIGHT']     = $split_weight;

    // กำหนดสถานะและคุณสมบัติต่าง ๆ
    $newData['LINE_PROCESS'] = 'S';
    $newData['SUBCONTACT']   = 'N';
    $newData['LOCATION_ID']  = 'NONE';
    $newData['CDML_STATUS']  = 'O';
    $newData['COIL_STATUS']  = 'AC';
    $newData['F_THICKNESS']    = $split_thickness > 0 ? $split_thickness : $mainData['F_THICKNESS'];
    $newData['THICKNESS']    = $split_thickness > 0 ? $split_thickness : $mainData['THICKNESS'];

    // ==========================================
    // 4. INSERT ข้อมูลสร้าง New Coil ลง COILPROD1
    // ==========================================
    $sql_insert_coilprod1 = "INSERT INTO COILPROD1 (
        COIL_NO, PRODUCT_REFERENCE, BATCH_NO, PRIMARY_SMELT, SECONDARY_SMELT, COUNTRY_MELT, 
        COUNTRY_ORIGIN, MATERIAL_IN, SALEORDER_NO, SALEORDER_ITEM, JOB_ORDER, JOB_PROCESS, CSTMSPPL_ID,
        PRODUCT_ID, LINE_PROCESS, RSET_NO, RECIPE_NO, RECIPE_ITEM, TOTAL_PASS, CURRENT_PASS, THICKNESS_FINAL,
        SUBCONTACT, LOCATION_ID, F_ALLOY, F_TEMPER, F_GRADE, F_THICKNESS, F_WIDTH, ALLOY, TEMPER, GRADE, 
        SURFACE_GRADE, METALLURGICAL_GRADE, THICKNESS, WIDTH, ACTUAL_WIDTH, T5_TEMPERATURE, CDML_STATUS,
        COIL_WORKPROCESS, COIL_NEXTPROCESS, USE_FORPROCESS, COIL_COLDMILLWEIGHT, COIL_BATCHANNEALWEIGHT,
        COIL_ACTUALWEIGHT, COIL_FIRSTWEIGHT, COIL_BALANCEWEIGHT, COIL_STARTDATE, COIL_STARTTIME, COIL_ENDDATE,
        COIL_ENDTIME, PRODUCE_FLAG, COIL_LABEL, LABEL_CODE, EXTRA_DESCRIPTION1, EXTRA_DESCRIPTION2,
        EXTRA_DESCRIPTION3, EDGE_OPS, TPBT_OPS, EDGE_DRS, TPBT_DRS, BTWN_OPS, BTWN_DRS, TPBT_BTWN,
        COIL_REMARK, COIL_OPERATOR1, COIL_OPERATEDATE, COIL_STATUS
    ) VALUES (
        ?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?
    )";

    $stmt_ins_c1 = $conn->prepare($sql_insert_coilprod1);
    $stmt_ins_c1->execute([
        $newData['COIL_NO'], $newData['PRODUCT_REFERENCE'], $newData['BATCH_NO'], $newData['PRIMARY_SMELT'], $newData['SECONDARY_SMELT'], $newData['COUNTRY_MELT'],
        $newData['COUNTRY_ORIGIN'], $newData['MATERIAL_IN'], $newData['SALEORDER_NO'], $newData['SALEORDER_ITEM'], $newData['JOB_ORDER'], $newData['JOB_PROCESS'], $newData['CSTMSPPL_ID'],
        $newData['PRODUCT_ID'], $newData['LINE_PROCESS'], $newData['RSET_NO'], $newData['RECIPE_NO'], $newData['RECIPE_ITEM'], $newData['TOTAL_PASS'], $newData['CURRENT_PASS'], $newData['THICKNESS_FINAL'],
        $newData['SUBCONTACT'], $newData['LOCATION_ID'], $newData['F_ALLOY'], $newData['F_TEMPER'], $newData['F_GRADE'], $newData['F_THICKNESS'], $newData['F_WIDTH'], $newData['ALLOY'], $newData['TEMPER'], $newData['GRADE'],
        $newData['SURFACE_GRADE'], $newData['METALLURGICAL_GRADE'], $newData['THICKNESS'], $newData['WIDTH'], $newData['ACTUAL_WIDTH'], $newData['T5_TEMPERATURE'], $newData['CDML_STATUS'],
        $newData['COIL_WORKPROCESS'], $newData['COIL_NEXTPROCESS'], $newData['USE_FORPROCESS'], $newData['COIL_COLDMILLWEIGHT'], $newData['COIL_BATCHANNEALWEIGHT'],
        $newData['COIL_ACTUALWEIGHT'], $newData['COIL_FIRSTWEIGHT'], $newData['COIL_BALANCEWEIGHT'], $newData['COIL_STARTDATE'], $newData['COIL_STARTTIME'], $newData['COIL_ENDDATE'],
        $newData['COIL_ENDTIME'], $newData['PRODUCE_FLAG'], $newData['COIL_LABEL'], $newData['LABEL_CODE'], $newData['EXTRA_DESCRIPTION1'], $newData['EXTRA_DESCRIPTION2'],
        $newData['EXTRA_DESCRIPTION3'], $newData['EDGE_OPS'], $newData['TPBT_OPS'], $newData['EDGE_DRS'], $newData['TPBT_DRS'], $newData['BTWN_OPS'], $newData['BTWN_DRS'], $newData['TPBT_BTWN'],
        $newData['COIL_REMARK'], $current_user, $current_datetime, $newData['COIL_STATUS']
    ]);

    // ==========================================
    // 5. COPY/INSERT CDMLPROD1 (กรณี COIL_COLDMILLWEIGHT > 0)
    // ==========================================
    if ($is_coldmill_valid) {
        $sql_cdml_src = "SELECT PASS_NO, TEMPER, THICKNESS, CDML_STARTDATE, CDML_STARTTIME, CDML_ENDDATE, CDML_ENDTIME, CDML_REJECTWEIGHT 
                         FROM CDMLPROD1 WHERE COIL_NO = :coil_no";
        $stmt_cdml_src = $conn->prepare($sql_cdml_src);
        $stmt_cdml_src->execute([':coil_no' => $main_coil_no]);
        $cdmlRows = $stmt_cdml_src->fetchAll(PDO::FETCH_ASSOC);

        $sql_ins_cdml = "INSERT INTO CDMLPROD1 (COIL_NO, PASS_NO, TEMPER, THICKNESS, CDML_STARTDATE, CDML_STARTTIME, CDML_ENDDATE, CDML_ENDTIME, 
                                                CDML_ACCEPTWEIGHT, CDML_REJECTWEIGHT, CDML_OPERATOR1, CDML_OPERATEDATE, CDML_UPDATE, CDML_UPDATEDATE) 
                         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
        $stmt_ins_cdml = $conn->prepare($sql_ins_cdml);

        foreach ($cdmlRows as $cRow) {
            $new_cdml_thick = $split_thickness > 0 ? $split_thickness : $cRow['THICKNESS'];
            $stmt_ins_cdml->execute([
                $new_coil_no, $cRow['PASS_NO'], $cRow['TEMPER'], $new_cdml_thick,
                $cRow['CDML_STARTDATE'], $cRow['CDML_STARTTIME'], $cRow['CDML_ENDDATE'], $cRow['CDML_ENDTIME'],
                $split_weight,
                $cRow['CDML_REJECTWEIGHT'], $current_user, $current_datetime, 
                $current_user, // CDML_UPDATE = current user
                $current_datetime
            ]);
        }
    }

    // ==========================================
    // 6. COPY/INSERT BTCHPROD1 (กรณี BATCHANNEALWEIGHT > 0)
    // ==========================================
    if ($is_batchanneal_valid) {
        $sql_btch_src = "SELECT LINE_PROCESS, PRODUCT_ID, TEMPER, BTCH_TIME, BTCH_STARTDATE, BTCH_STARTTIME, BTCH_ENDDATE, BTCH_ENDTIME, BTCH_REJECTWEIGHT 
                         FROM BTCHPROD1 WHERE PRODUCT_NO = :coil_no";
        $stmt_btch_src = $conn->prepare($sql_btch_src);
        $stmt_btch_src->execute([':coil_no' => $main_coil_no]);
        $btchRows = $stmt_btch_src->fetchAll(PDO::FETCH_ASSOC);

        $sql_ins_btch = "INSERT INTO BTCHPROD1 (PRODUCT_NO, LINE_PROCESS, PRODUCT_ID, TEMPER, BTCH_TIME, BTCH_STARTDATE, BTCH_STARTTIME, BTCH_ENDDATE, BTCH_ENDTIME, 
                                                BTCH_ACCEPTWEIGHT, BTCH_REJECTWEIGHT, BTCH_OPERATOR1, BTCH_OPERATEDATE, BTCH_UPDATE, BTCH_UPDATEDATE) 
                         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
        $stmt_ins_btch = $conn->prepare($sql_ins_btch);

        foreach ($btchRows as $bRow) {
            $stmt_ins_btch->execute([
                $new_coil_no, $bRow['LINE_PROCESS'], $bRow['PRODUCT_ID'], $bRow['TEMPER'], $bRow['BTCH_TIME'],
                $bRow['BTCH_STARTDATE'], $bRow['BTCH_STARTTIME'], $bRow['BTCH_ENDDATE'], $bRow['BTCH_ENDTIME'],
                $split_weight,
                $bRow['BTCH_REJECTWEIGHT'], $current_user, $current_datetime, 
                $current_user, // BTCH_UPDATE = current user
                $current_datetime
            ]);
        }
    }

    // ==========================================
    // 7. คำนวณหักลบน้ำหนัก และ UPDATE กลับไปยัง Coil หลัก
    // ==========================================
    $main_act_wt = (float)($mainData['COIL_ACTUALWEIGHT'] ?? 0);
    $main_fst_wt = (float)($mainData['COIL_FIRSTWEIGHT'] ?? 0);
    $main_bal_wt = (float)($mainData['COIL_BALANCEWEIGHT'] ?? 0);

    // คำนวณน้ำหนักคงเหลือ
    $updated_actual     = max(0, $main_act_wt - $split_weight);
    $updated_first      = max(0, $main_fst_wt - $split_weight);
    $updated_coldmill   = max(0, $main_coldmill_wt - $split_weight);
    $updated_batchanneal= max(0, $main_batchanneal_wt - $split_weight);

    // ตรวจสอบ COIL_BALANCEWEIGHT: ถ้าเดิมเป็น 0 หรือ NULL ให้ใช้น้ำหนัก ACTUALWEIGHT เป็นฐานคำนวณแทน
    $base_balance_wt    = ($main_bal_wt > 0) ? $main_bal_wt : $main_act_wt;
    $updated_balance    = max(0, $base_balance_wt - $split_weight);

    if ($is_coldmill_valid || $is_batchanneal_valid) {
        $sql_upd_main = "UPDATE COILPROD1 SET 
                            COIL_NEXTPROCESS = ?, 
                            COIL_ACTUALWEIGHT = ?, 
                            COIL_FIRSTWEIGHT = ?, 
                            COIL_COLDMILLWEIGHT = ?, 
                            COIL_BATCHANNEALWEIGHT = ?, 
                            COIL_BALANCEWEIGHT = ?, 
                            COIL_UPDATE = ?, 
                            COIL_UPDATEDATE = ? 
                         WHERE COIL_NO = ?";
        $stmt_upd_main = $conn->prepare($sql_upd_main);
        $stmt_upd_main->execute([
            $mainData['COIL_NEXTPROCESS'], 
            $updated_actual, 
            $updated_first,
            $updated_coldmill, 
            $updated_batchanneal, 
            $updated_balance,
            $current_datetime, 
            $current_datetime, 
            $main_coil_no
        ]);
    } else {
        $sql_upd_main = "UPDATE COILPROD1 SET 
                            COIL_NEXTPROCESS = 'NN', 
                            LOCATION_ID = 'NONE', 
                            COIL_ACTUALWEIGHT = ?, 
                            COIL_FIRSTWEIGHT = ?, 
                            COIL_COLDMILLWEIGHT = ?, 
                            COIL_BATCHANNEALWEIGHT = ?, 
                            COIL_BALANCEWEIGHT = ?, 
                            COIL_UPDATE = ?, 
                            COIL_UPDATEDATE = ? 
                         WHERE COIL_NO = ?";
        $stmt_upd_main = $conn->prepare($sql_upd_main);
        $stmt_upd_main->execute([
            $updated_actual, 
            $updated_first,
            $updated_coldmill, 
            $updated_batchanneal, 
            $updated_balance,
            $current_datetime, 
            $current_datetime, 
            $main_coil_no
        ]);
    }

    // ==========================================
    // 8. COPY/INSERT COILINSP1, COILINSP3, COILINSP5
    // ==========================================
    // 8.1 COILINSP1
    $sql_insp1_src = "SELECT INSPECTION_DATE, LINE_PROCESS, CHECK_AL, CHECK_FE, CHECK_SI, CHECK_CR, CHECK_CU, CHECK_MN, CHECK_MG, CHECK_ZN, CHECK_PB, CHECK_TI, GRAIN_SIZE, DELTA_TI, SCD_OS, SCD_DS, MDF_UPPER, MDF_LOWER, YIELD_STRENGTH, UTS, ELONGATION, EARING, PROFILE, ACTUAL_THICKNESS, MINIMUM_THICKNESS, MAXIMUM_THICKNESS, BEGIN_THICKNESS, END_THICKNESS, CS_YIELDSTRENGTH, CS_UTS, CS_ELONGATION, CS_EARING, CS_PROFILE, CS_ACTUALTHICKNESS, CS_MINIMUMTHICKNESS, CS_MAXIMUMTHICKNESS, CS_BEGINTHICKNESS, CS_ENDTHICKNESS, OIN1_REMARK, OIN1_OPERATOR, OIN1_OPERATEDATE, OIN1_STATUS, OIN1_APPEARANCE, OIN1_DIMENSION FROM COILINSP1 WHERE COIL_NO = :coil_no";
    $stmt_i1 = $conn->prepare($sql_insp1_src);
    $stmt_i1->execute([':coil_no' => $main_coil_no]);
    $i1_rows = $stmt_i1->fetchAll(PDO::FETCH_ASSOC);

    $sql_ins_i1 = "INSERT INTO COILINSP1 (COIL_NO, INSPECTION_DATE, LINE_PROCESS, CHECK_AL, CHECK_FE, CHECK_SI, CHECK_CR, CHECK_CU, CHECK_MN, CHECK_MG, CHECK_ZN, CHECK_PB, CHECK_TI, GRAIN_SIZE, DELTA_TI, SCD_OS, SCD_DS, MDF_UPPER, MDF_LOWER, YIELD_STRENGTH, UTS, ELONGATION, EARING, PROFILE, ACTUAL_THICKNESS, MINIMUM_THICKNESS, MAXIMUM_THICKNESS, BEGIN_THICKNESS, END_THICKNESS, CS_YIELDSTRENGTH, CS_UTS, CS_ELONGATION, CS_EARING, CS_PROFILE, CS_ACTUALTHICKNESS, CS_MINIMUMTHICKNESS, CS_MAXIMUMTHICKNESS, CS_BEGINTHICKNESS, CS_ENDTHICKNESS, OIN1_REMARK, OIN1_OPERATOR, OIN1_OPERATEDATE, OIN1_UPDATE, OIN1_UPDATEDATE, OIN1_STATUS, OIN1_APPEARANCE, OIN1_DIMENSION) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
    $stmt_ins_i1 = $conn->prepare($sql_ins_i1);

    foreach ($i1_rows as $row) {
        $stmt_ins_i1->execute([
            $new_coil_no, 
            $row['INSPECTION_DATE'], 
            'S', // LINE_PROCESS = 'S' สำหรับ COILINSP1
            $row['CHECK_AL'], $row['CHECK_FE'], $row['CHECK_SI'], $row['CHECK_CR'], $row['CHECK_CU'], $row['CHECK_MN'], $row['CHECK_MG'], $row['CHECK_ZN'], $row['CHECK_PB'], $row['CHECK_TI'], $row['GRAIN_SIZE'], $row['DELTA_TI'], $row['SCD_OS'], $row['SCD_DS'], $row['MDF_UPPER'], $row['MDF_LOWER'], $row['YIELD_STRENGTH'], $row['UTS'], $row['ELONGATION'], $row['EARING'], $row['PROFILE'], $row['ACTUAL_THICKNESS'], $row['MINIMUM_THICKNESS'], $row['MAXIMUM_THICKNESS'], $row['BEGIN_THICKNESS'], $row['END_THICKNESS'], $row['CS_YIELDSTRENGTH'], $row['CS_UTS'], $row['CS_ELONGATION'], $row['CS_EARING'], $row['CS_PROFILE'], $row['CS_ACTUALTHICKNESS'], $row['CS_MINIMUMTHICKNESS'], $row['CS_MAXIMUMTHICKNESS'], $row['CS_BEGINTHICKNESS'], $row['CS_ENDTHICKNESS'], $row['OIN1_REMARK'], $row['OIN1_OPERATOR'], $row['OIN1_OPERATEDATE'], $current_datetime, $current_datetime, $row['OIN1_STATUS'], $row['OIN1_APPEARANCE'], $row['OIN1_DIMENSION']
        ]);
    }

    // 8.2 COILINSP3
    $sql_insp3_src = "SELECT CS_STP_THICKNESS, CS_STP_WIDTH, CS_STP_SCD_DRS, CS_STP_SCD_OPS, CS_STP_TRL_DMT, CS_STP_BRL_DMT, CS_STP_TRL_SPD, CS_STP_BRL_SPD, CS_STP_TBR_SPD, CS_STP_BBR_SPD, CS_STP_TLM_DRS, CS_STP_TLM_OPS, CS_STP_CLR_SPD, CS_STP_MTL_LVL, CS_ACT_THICKNESS, CS_ACT_SCD_DRS, CS_ACT_SCD_OPS, CS_ACT_TRL_SPD, CS_ACT_BRL_SPD, CS_ACT_TRL_MTQ, CS_ACT_BRL_MTQ, CS_ACT_STP_SPD, CS_ACT_STP_TNS, CS_ACT_CLR_SPD, CS_ACT_TBR_SPD, CS_ACT_BBR_SPD, CS_ACT_TSP_PMF, CS_ACT_BSP_PMF, CS_ACT_TMT_TMP, CS_ACT_TTM_DS, CS_ACT_TTM_MDS, CS_ACT_TTM_OS, CS_ACT_TTS_PST, CS_ACT_TEV_PST, CS_ACT_MTL_LVL, CM_STP_WRL_RDN, CM_STP_BRL_RDN, CM_STP_RLL_FRC, CM_STP_PUP_FRC, CM_STP_RLL_GAP, CM_STP_RBD_PRS, CM_STP_BDR_PST, CM_STP_MLL_SPD, CM_STP_DLR_TNS, CM_STP_CLR_TNS, CM_STP_STR_PRS, CM_ACT_OPT_THK, CM_ACT_PUF_DRS, CM_ACT_PUF_OPS, CM_ACT_RLL_GAP, CM_ACT_RBD_PRS, CM_ACT_BDR_PST, CM_ACT_MLL_SPD, CM_ACT_DLR_MCR, CM_ACT_CLR_MC1, CM_ACT_CLR_MC2, CM_ACT_MNM_MC1, CM_ACT_MNM_MC2, CM_ACT_DLR_TNS, CM_ACT_CLR_TNS, CM_ACT_STR_PRS FROM COILINSP3 WHERE COIL_NO = :coil_no";
    $stmt_i3 = $conn->prepare($sql_insp3_src);
    $stmt_i3->execute([':coil_no' => $main_coil_no]);
    $i3_rows = $stmt_i3->fetchAll(PDO::FETCH_ASSOC);

    $sql_ins_i3 = "INSERT INTO COILINSP3 (COIL_NO, CS_STP_THICKNESS, CS_STP_WIDTH, CS_STP_SCD_DRS, CS_STP_SCD_OPS, CS_STP_TRL_DMT, CS_STP_BRL_DMT, CS_STP_TRL_SPD, CS_STP_BRL_SPD, CS_STP_TBR_SPD, CS_STP_BBR_SPD, CS_STP_TLM_DRS, CS_STP_TLM_OPS, CS_STP_CLR_SPD, CS_STP_MTL_LVL, CS_ACT_THICKNESS, CS_ACT_SCD_DRS, CS_ACT_SCD_OPS, CS_ACT_TRL_SPD, CS_ACT_BRL_SPD, CS_ACT_TRL_MTQ, CS_ACT_BRL_MTQ, CS_ACT_STP_SPD, CS_ACT_STP_TNS, CS_ACT_CLR_SPD, CS_ACT_TBR_SPD, CS_ACT_BBR_SPD, CS_ACT_TSP_PMF, CS_ACT_BSP_PMF, CS_ACT_TMT_TMP, CS_ACT_TTM_DS, CS_ACT_TTM_MDS, CS_ACT_TTM_OS, CS_ACT_TTS_PST, CS_ACT_TEV_PST, CS_ACT_MTL_LVL, CM_STP_WRL_RDN, CM_STP_BRL_RDN, CM_STP_RLL_FRC, CM_STP_PUP_FRC, CM_STP_RLL_GAP, CM_STP_RBD_PRS, CM_STP_BDR_PST, CM_STP_MLL_SPD, CM_STP_DLR_TNS, CM_STP_CLR_TNS, CM_STP_STR_PRS, CM_ACT_OPT_THK, CM_ACT_PUF_DRS, CM_ACT_PUF_OPS, CM_ACT_RLL_GAP, CM_ACT_RBD_PRS, CM_ACT_BDR_PST, CM_ACT_MLL_SPD, CM_ACT_DLR_MCR, CM_ACT_CLR_MC1, CM_ACT_CLR_MC2, CM_ACT_MNM_MC1, CM_ACT_MNM_MC2, CM_ACT_DLR_TNS, CM_ACT_CLR_TNS, CM_ACT_STR_PRS) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
    $stmt_ins_i3 = $conn->prepare($sql_ins_i3);

    foreach ($i3_rows as $row) {
        $params = array_merge([$new_coil_no], array_values($row));
        $stmt_ins_i3->execute($params);
    }

    // 8.3 COILINSP5
    $sql_insp5_src = "SELECT PASS_NO, JOB_PROCESS, RECIPE_NO, RECIPE_ITEM, COOLANT_RECIPE, ALLOY, TEMPER, THICKNESS, WIDTH, COIL_WEIGHT, THICKNESS_ORIGINAL, THICKNESS_FINAL, THICKNESS_ENTRY, THICKNESS_EXIT, TOTAL_PASS, STP_WRL_RDN, STP_TWR_CMB, STP_BWR_CMB, STP_PUP_FRC, STP_RLL_GAP, STP_RBD_PRS, STP_MLL_SPD, STP_DLR_TNS, STP_CLR_TNS, STP_STR_PRS, STP_BDR_PST, ACT_OPT_THK, ACT_PUF_DRS, ACT_PUF_OPS, ACT_RLL_GAP, ACT_RBD_PRS, ACT_MLL_SPD, ACT_DLR_TNS, ACT_CLR_TNS, ACT_STR_PRS, ACT_DLR_MCR, ACT_CLR_MC1, ACT_CLR_MC2, ACT_MNM_MC1, ACT_MNM_MC2, ACT_BDR_PST, ACT_CSP_D15, ACT_CSP_D14, ACT_CSP_D13, ACT_CSP_D12, ACT_CSP_D11, ACT_CSP_D10, ACT_CSP_D09, ACT_CSP_D08, ACT_CSP_D07, ACT_CSP_D06, ACT_CSP_D05, ACT_CSP_D04, ACT_CSP_D03, ACT_CSP_D02, ACT_CSP_D01, ACT_CSP_W00, ACT_CSP_W01, ACT_CSP_W02, ACT_CSP_W03, ACT_CSP_W04, ACT_CSP_W05, ACT_CSP_W06, ACT_CSP_W07, ACT_CSP_W08, ACT_CSP_W09, ACT_CSP_W10, ACT_CSP_W11, ACT_CSP_W12, ACT_CSP_W13, ACT_CSP_W14, ACT_CSP_W15 FROM COILINSP5 WHERE COIL_NO = :coil_no";
    $stmt_i5 = $conn->prepare($sql_insp5_src);
    $stmt_i5->execute([':coil_no' => $main_coil_no]);
    $i5_rows = $stmt_i5->fetchAll(PDO::FETCH_ASSOC);

    $sql_ins_i5 = "INSERT INTO COILINSP5 (COIL_NO, PASS_NO, JOB_PROCESS, RECIPE_NO, RECIPE_ITEM, COOLANT_RECIPE, ALLOY, TEMPER, THICKNESS, WIDTH, COIL_WEIGHT, THICKNESS_ORIGINAL, THICKNESS_FINAL, THICKNESS_ENTRY, THICKNESS_EXIT, TOTAL_PASS, STP_WRL_RDN, STP_TWR_CMB, STP_BWR_CMB, STP_PUP_FRC, STP_RLL_GAP, STP_RBD_PRS, STP_MLL_SPD, STP_DLR_TNS, STP_CLR_TNS, STP_STR_PRS, STP_BDR_PST, ACT_OPT_THK, ACT_PUF_DRS, ACT_PUF_OPS, ACT_RLL_GAP, ACT_RBD_PRS, ACT_MLL_SPD, ACT_DLR_TNS, ACT_CLR_TNS, ACT_STR_PRS, ACT_DLR_MCR, ACT_CLR_MC1, ACT_CLR_MC2, ACT_MNM_MC1, ACT_MNM_MC2, ACT_BDR_PST, ACT_CSP_D15, ACT_CSP_D14, ACT_CSP_D13, ACT_CSP_D12, ACT_CSP_D11, ACT_CSP_D10, ACT_CSP_D09, ACT_CSP_D08, ACT_CSP_D07, ACT_CSP_D06, ACT_CSP_D05, ACT_CSP_D04, ACT_CSP_D03, ACT_CSP_D02, ACT_CSP_D01, ACT_CSP_W00, ACT_CSP_W01, ACT_CSP_W02, ACT_CSP_W03, ACT_CSP_W04, ACT_CSP_W05, ACT_CSP_W06, ACT_CSP_W07, ACT_CSP_W08, ACT_CSP_W09, ACT_CSP_W10, ACT_CSP_W11, ACT_CSP_W12, ACT_CSP_W13, ACT_CSP_W14, ACT_CSP_W15) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
    $stmt_ins_i5 = $conn->prepare($sql_ins_i5);

    foreach ($i5_rows as $row) {
        if ($split_thickness > 0) {
            $row['THICKNESS'] = $split_thickness;
        }
        $params = array_merge([$new_coil_no], array_values($row));
        $stmt_ins_i5->execute($params);
    }

    $conn->commit();

    echo "<script>
            alert('Split coil data has been saved\\nCoil New: {$new_coil_no}');
            window.location.href = '../split_coil_mats.php?func=" . urlencode($func) . "&COIL=" . urlencode($main_coil_no) . "';
          </script>";
    exit();

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo "<script>
            alert('เกิดข้อผิดพลาดในการบันทึกข้อมูล: " . addslashes($e->getMessage()) . "');
            window.history.back();
          </script>";
    exit();
}