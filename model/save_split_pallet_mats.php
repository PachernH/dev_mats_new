<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['ID'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit();
}

include("../dbcon_mats-new.php");

// ดึงข้อมูลนำเข้าจาก POST
$mode                 = $_POST['mode'] ?? 'SPLIT';
$strProductNo         = trim($_POST['product_no'] ?? '');
$strProductRef        = trim($_POST['product_ref'] ?? '');
$dblMainBottomWeight  = (float)($_POST['main_bottom_weight'] ?? 0);
$dblMainPiece         = (float)($_POST['main_piece'] ?? 0);
$dblMainNetWeight     = (float)($_POST['main_net_weight'] ?? 0);

$dblSplitBottomWeight = (float)($_POST['split_bottom_weight'] ?? 0);
$dblSplitPiece        = (float)($_POST['split_piece'] ?? 0);
$dblSplitNetWeight    = (float)($_POST['split_net_weight'] ?? 0);

$strUserOperator       = $_SESSION['ID'] ?? 'SYSTEM';
$currentDateTime       = date('Y-m-d H:i:s');
$currentDate           = date('Y-m-d');
$currentTime           = date('H:i:s');

if (empty($strProductNo)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Product No']);
    exit();
}

try {
    $conn->beginTransaction();

    if ($mode === 'SPLIT') {
        // =========================================================
        // 1. สร้าง PRODUCT_NO ใหม่รูปแบบ PYYMMDD-S-XX Running รายวัน
        // =========================================================
        $prefix = "P" . date('ymd') . "-S-";
        
        $sql_max = "SELECT MAX(PRODUCT_NO) AS max_pno FROM SPLTPROD1 WHERE PRODUCT_NO LIKE :prefix";
        $stmt_max = $conn->prepare($sql_max);
        $search_prefix = $prefix . "%";
        $stmt_max->bindParam(':prefix', $search_prefix, PDO::PARAM_STR);
        $stmt_max->execute();
        $row_max = $stmt_max->fetch(PDO::FETCH_ASSOC);

        $next_run = 1;
        if (!empty($row_max['max_pno'])) {
            $last_pno = trim($row_max['max_pno']);
            $last_run = (int)substr($last_pno, -2);
            $next_run = $last_run + 1;
        }
        $strSplitProductNo = $prefix . sprintf("%02d", $next_run);

        // =========================================================
        // 2. ดึงข้อมูลต้นฉบับจาก CRSHPROD1 ของ Main Product
        // =========================================================
        $sql_src = "SELECT * FROM CRSHPROD1 WHERE PRODUCT_NO = :pno";
        $stmt_src = $conn->prepare($sql_src);
        $stmt_src->bindParam(':pno', $strProductNo, PDO::PARAM_STR);
        $stmt_src->execute();
        $src = $stmt_src->fetch(PDO::FETCH_ASSOC);

        if (!$src) {
            throw new Exception("Source product not found in CRSHPROD1");
        }

        // คำนวณค่าน้ำหนัก History Production ที่ต้องปรับลดสำหรับ Main และใส่ให้ Split
        $stch_wt  = max(0, (float)($src['CRSH_STRETCHERWEIGHT'] ?? 0) - $dblSplitNetWeight);
        $stch_pc  = max(0, (float)($src['CRSH_STRETCHERPIECE'] ?? 0) - $dblSplitPiece);
        $ctsh_wt  = max(0, (float)($src['CRSH_CUTSHEETWEIGHT'] ?? 0) - $dblSplitNetWeight);
        $ctsh_pc  = max(0, (float)($src['CRSH_CUTSHEETPIECE'] ?? 0) - $dblSplitPiece);
        $shrs_wt  = max(0, (float)($src['CRSH_SHEARWEIGHT'] ?? 0) - $dblSplitNetWeight);
        $shrs_pc  = max(0, (float)($src['CRSH_SHEARPIECE'] ?? 0) - $dblSplitPiece);
        $pchl_wt  = max(0, (float)($src['CRSH_PUNCHWEIGHT'] ?? 0) - $dblSplitNetWeight);
        $pchl_pc  = max(0, (float)($src['CRSH_PUNCHPIECE'] ?? 0) - $dblSplitPiece);
        $btch_wt  = max(0, (float)($src['CRSH_BATCHANNEALWEIGHT'] ?? 0) - $dblSplitNetWeight);
        $btch_pc  = max(0, (float)($src['CRSH_BATCHANNEALPIECE'] ?? 0) - $dblSplitPiece);
        $tptp_wt  = max(0, (float)($src['CRSH_TRANSFERWEIGHT'] ?? 0) - $dblSplitNetWeight);
        $tptp_pc  = max(0, (float)($src['CRSH_TRANSFERPIECE'] ?? 0) - $dblSplitPiece);
        $annl_wt  = max(0, (float)($src['CRSH_ANNEALWEIGHT'] ?? 0) - $dblSplitNetWeight);
        $annl_pc  = max(0, (float)($src['CRSH_ANNEALPIECE'] ?? 0) - $dblSplitPiece);
        $sort_wt  = max(0, (float)($src['CRSH_SORTWEIGHT'] ?? 0) - $dblSplitNetWeight);
        $sort_pc  = max(0, (float)($src['CRSH_SORTPIECE'] ?? 0) - $dblSplitPiece);

        // =========================================================
        // 3. INSERT ลงตาราง CRSHPROD1 สำหรับ Split Pallet ใหม่
        // =========================================================
        $sql_ins_crsh = "INSERT INTO CRSHPROD1 (
            PRODUCT_NO, PRODUCT_REFERENCE, COIL_NO, SALEORDER_NO, SALEORDER_ITEM, JOB_ORDER, MATERIAL_IN,
            MOULDSETUP_NO, MAINTENANCE_NO1, DIESET_NO, MAINTENANCE_NO2, DIE_NO, MAINTENANCE_NO3, PUNCH_NO1,
            MAINTENANCE_NO4, PUNCH_NO2, CLEARANCE, BTCH_RESULT, BTCH_OUTPUTTEMPER, PRODUCT_ID, PRODUCT_MODEL,
            AREA_SIZE, LINE_PROCESS, ORG_LINEPROCESS, LOCATION_ID, CRSH_WORKPROCESS, CRSH_NEXTPROCESS, F_ALLOY,
            F_TEMPER, F_WIDTH, F_LENGTH, ALLOY, TEMPER, GRADE, SURFACE_GRADE, METALLURGICAL_GRADE, T5_TEMPERATURE,
            FLATNESS_GRADE, THICKNESS, WIDTH, LENGTH, WEIGHT_PIECE, CUT_WIDTH, CUT_LENGTH, CUT_WEIGHTPIECE, UTS,
            ELONGATION, CRSH_ACTUALWEIGHT, CRSH_ACTUALPIECE, CRSH_PRODUCEWEIGHT, CRSH_PRODUCEPIECE, CRSH_STRETCHERWEIGHT,
            CRSH_STRETCHERPIECE, CRSH_CUTSHEETWEIGHT, CRSH_CUTSHEETPIECE, CRSH_SHEARWEIGHT, CRSH_SHEARPIECE,
            CRSH_PUNCHWEIGHT, CRSH_PUNCHPIECE, CRSH_BATCHANNEALWEIGHT, CRSH_BATCHANNEALPIECE, CRSH_TRANSFERWEIGHT,
            CRSH_TRANSFERPIECE, CRSH_ANNEALWEIGHT, CRSH_ANNEALPIECE, CRSH_SORTWEIGHT, CRSH_SORTPIECE, CRSH_TOPWEIGHT,
            CRSH_BOTTOMWEIGHT, CRSH_STARTDATE, CRSH_STARTTIME, CRSH_ENDDATE, CRSH_ENDTIME, CRSH_LABEL, CRSH_OPERATOR1,
            CRSH_OPERATEDATE, CRSH_STATUS, ORG_STATUS
        ) VALUES (
            :split_pno, :pno, :coil_no, :so_no, :so_item, :job_order, :mat_in,
            :mould, :maint1, :dieset, :maint2, :die, :maint3, :punch1,
            :maint4, :punch2, :clearance, :btch_res, :btch_out, :prod_id, :prod_model,
            :area, 'S', :org_line, 'NONE', :work_proc, :next_proc, :alloy,
            :temper, :width, :length, :alloy2, :temper2, :grade, :sg, :mg, :t5,
            :flatness, :thickness, :width2, :length2, :weight_pc, :cut_w, :cut_l, :cut_w_pc, :uts,
            :elong, :split_net_wt, :split_pc, :split_net_wt, :split_pc, 
            :stch_wt, :stch_pc, :ctsh_wt, :ctsh_pc, :shrs_wt, :shrs_pc,
            :pchl_wt, :pchl_pc, :btch_wt_s, :btch_pc_s, :tptp_wt, :tptp_pc,
            :annl_wt, :annl_pc, :sort_wt, :sort_pc, 0, :split_btm_wt,
            :startDate, :startTime, :endDate, :endTime, 1, :operator,
            :opDate, :status, :org_status
        )";

        $stmt_ins_crsh = $conn->prepare($sql_ins_crsh);
        $stmt_ins_crsh->execute([
            ':split_pno'   => $strSplitProductNo,
            ':pno'         => $strProductNo,
            ':coil_no'     => $src['COIL_NO'] ?? '',
            ':so_no'       => $src['SALEORDER_NO'] ?? '',
            ':so_item'     => $src['SALEORDER_ITEM'] ?? '',
            ':job_order'   => $src['JOB_ORDER'] ?? '',
            ':mat_in'      => $src['MATERIAL_IN'] ?? '',
            ':mould'       => $src['MOULDSETUP_NO'] ?? '',
            ':maint1'      => $src['MAINTENANCE_NO1'] ?? '',
            ':dieset'      => $src['DIESET_NO'] ?? '',
            ':maint2'      => $src['MAINTENANCE_NO2'] ?? '',
            ':die'         => $src['DIE_NO'] ?? '',
            ':maint3'      => $src['MAINTENANCE_NO3'] ?? '',
            ':punch1'      => $src['PUNCH_NO1'] ?? '',
            ':maint4'      => $src['MAINTENANCE_NO4'] ?? '',
            ':punch2'      => $src['PUNCH_NO2'] ?? '',
            ':clearance'   => $src['CLEARANCE'] ?? 0,
            ':btch_res'    => $src['BTCH_RESULT'] ?? '',
            ':btch_out'    => $src['BTCH_OUTPUTTEMPER'] ?? '',
            ':prod_id'     => $src['PRODUCT_ID'] ?? '',
            ':prod_model'  => $src['PRODUCT_MODEL'] ?? '',
            ':area'        => $src['AREA_SIZE'] ?? 0,
            ':org_line'    => $src['ORG_LINEPROCESS'] ?? '',
            ':work_proc'   => $src['CRSH_WORKPROCESS'] ?? '',
            ':next_proc'   => $src['CRSH_NEXTPROCESS'] ?? '',
            ':alloy'       => $src['ALLOY'] ?? '',
            ':temper'      => $src['TEMPER'] ?? '',
            ':width'       => $src['WIDTH'] ?? 0,
            ':length'      => $src['LENGTH'] ?? 0,
            ':alloy2'      => $src['ALLOY'] ?? '',
            ':temper2'     => $src['TEMPER'] ?? '',
            ':grade'       => $src['GRADE'] ?? '',
            ':sg'          => $src['SURFACE_GRADE'] ?? '',
            ':mg'          => $src['METALLURGICAL_GRADE'] ?? '',
            ':t5'          => $src['T5_TEMPERATURE'] ?? '',
            ':flatness'    => $src['FLATNESS_GRADE'] ?? '',
            ':thickness'   => $src['THICKNESS'] ?? 0,
            ':width2'      => $src['WIDTH'] ?? 0,
            ':length2'     => $src['LENGTH'] ?? 0,
            ':weight_pc'   => $src['WEIGHT_PIECE'] ?? 0,
            ':cut_w'       => $src['CUT_WIDTH'] ?? 0,
            ':cut_l'       => $src['CUT_LENGTH'] ?? 0,
            ':cut_w_pc'    => $src['CUT_WEIGHTPIECE'] ?? 0,
            ':uts'         => $src['UTS'] ?? 0,
            ':elong'       => $src['ELONGATION'] ?? 0,
            ':split_net_wt'=> $dblSplitNetWeight,
            ':split_pc'    => $dblSplitPiece,
            ':stch_wt'     => ((float)($src['CRSH_STRETCHERWEIGHT'] ?? 0) > 0) ? $dblSplitNetWeight : 0,
            ':stch_pc'     => ((float)($src['CRSH_STRETCHERWEIGHT'] ?? 0) > 0) ? $dblSplitPiece : 0,
            ':ctsh_wt'     => ((float)($src['CRSH_CUTSHEETWEIGHT'] ?? 0) > 0) ? $dblSplitNetWeight : 0,
            ':ctsh_pc'     => ((float)($src['CRSH_CUTSHEETWEIGHT'] ?? 0) > 0) ? $dblSplitPiece : 0,
            ':shrs_wt'     => ((float)($src['CRSH_SHEARWEIGHT'] ?? 0) > 0) ? $dblSplitNetWeight : 0,
            ':shrs_pc'     => ((float)($src['CRSH_SHEARWEIGHT'] ?? 0) > 0) ? $dblSplitPiece : 0,
            ':pchl_wt'     => ((float)($src['CRSH_PUNCHWEIGHT'] ?? 0) > 0) ? $dblSplitNetWeight : 0,
            ':pchl_pc'     => ((float)($src['CRSH_PUNCHWEIGHT'] ?? 0) > 0) ? $dblSplitPiece : 0,
            ':btch_wt_s'   => ((float)($src['CRSH_BATCHANNEALWEIGHT'] ?? 0) > 0) ? $dblSplitNetWeight : 0,
            ':btch_pc_s'   => ((float)($src['CRSH_BATCHANNEALWEIGHT'] ?? 0) > 0) ? $dblSplitPiece : 0,
            ':tptp_wt'     => ((float)($src['CRSH_TRANSFERWEIGHT'] ?? 0) > 0) ? $dblSplitNetWeight : 0,
            ':tptp_pc'     => ((float)($src['CRSH_TRANSFERWEIGHT'] ?? 0) > 0) ? $dblSplitPiece : 0,
            ':annl_wt'     => ((float)($src['CRSH_ANNEALWEIGHT'] ?? 0) > 0) ? $dblSplitNetWeight : 0,
            ':annl_pc'     => ((float)($src['CRSH_ANNEALWEIGHT'] ?? 0) > 0) ? $dblSplitPiece : 0,
            ':sort_wt'     => ((float)($src['CRSH_SORTWEIGHT'] ?? 0) > 0) ? $dblSplitNetWeight : 0,
            ':sort_pc'     => ((float)($src['CRSH_SORTWEIGHT'] ?? 0) > 0) ? $dblSplitPiece : 0,
            ':split_btm_wt'=> $dblSplitBottomWeight,
            ':startDate'   => $currentDate,
            ':startTime'   => $currentTime,
            ':endDate'     => $currentDate,
            ':endTime'     => $currentTime,
            ':operator'    => $strUserOperator,
            ':opDate'      => $currentDateTime,
            ':status'      => $src['CRSH_STATUS'] ?? 'OP',
            ':org_status'  => $src['CRSH_STATUS'] ?? 'OP'
        ]);

        // =========================================================
        // 4. INSERT ลงตาราง SPLTPROD1
        // =========================================================
        $sql_ins_splt = "INSERT INTO SPLTPROD1 (
            PRODUCT_NO, PRODUCT_REFERENCE, PRODUCT_ID, LINE_PROCESS, SPLT_ENDDATE, SPLT_ENDTIME,
            ORGN_ENDDATE, ORGN_ENDTIME, SPLT_ACCEPTWEIGHT, SPLT_ACCEPTPIECE, SPLT_OPERATOR1, SPLT_OPERATEDATE, ORG_STATUS
        ) VALUES (
            :split_pno, :pno, :prod_id, :org_line, :endDate, :endTime,
            :orgDate, :orgTime, :split_wt, :split_pc, :operator, :opDate, :status
        )";

        $stmt_ins_splt = $conn->prepare($sql_ins_splt);
        $stmt_ins_splt->execute([
            ':split_pno' => $strSplitProductNo,
            ':pno'       => $strProductNo,
            ':prod_id'   => $src['PRODUCT_ID'] ?? '',
            ':org_line'  => $src['ORG_LINEPROCESS'] ?? '',
            ':endDate'   => $currentDate,
            ':endTime'   => $currentDateTime,
            ':orgDate'   => $src['CRSH_ENDDATE'] ?? $currentDate,
            ':orgTime'   => $src['CRSH_ENDTIME'] ?? $currentDateTime,
            ':split_wt'  => $dblSplitNetWeight,
            ':split_pc'  => $dblSplitPiece,
            ':operator'  => $strUserOperator,
            ':opDate'    => $currentDateTime,
            ':status'    => $src['CRSH_STATUS'] ?? 'OP'
        ]);

        // =========================================================
        // 5. หาก CRSH_STATUS = 'AC' ให้สร้างข้อมูลใน CRSHINSP1
        // =========================================================
        if (trim($src['CRSH_STATUS'] ?? '') === 'AC') {
            $sql_ins_insp = "INSERT INTO CRSHINSP1 (
                PRODUCT_NO, PRODUCT_ID, PRODUCT_MODEL, COIL_NO, INSPECTION_DATE, UTS, ELONGATION,
                MINIMUM_THICKNESS, MAXIMUM_THICKNESS, CIN1_OPERATOR, CIN1_OPERATEDATE, CIN1_STATUS,
                CIN1_APPEARANCE, CIN1_DIMENSION
            ) VALUES (
                :split_pno, :prod_id, :prod_model, :coil_no, :insp_date, 0, 0,
                0, 0, :operator, :opDate, 'AC', 'ACCEPT', 'ACCEPT'
            )";

            $stmt_ins_insp = $conn->prepare($sql_ins_insp);
            $stmt_ins_insp->execute([
                ':split_pno'  => $strSplitProductNo,
                ':prod_id'    => $src['PRODUCT_ID'] ?? '',
                ':prod_model' => $src['PRODUCT_MODEL'] ?? '',
                ':coil_no'    => $src['COIL_NO'] ?? '',
                ':insp_date'  => $currentDateTime,
                ':operator'   => $strUserOperator,
                ':opDate'     => $currentDateTime
            ]);
        }

        // =========================================================
        // 6. ตรวจสอบประวัติกระบวนการย้อนหลังและบันทึกลงตารางที่เกี่ยวข้อง
        // =========================================================

        // --- Stretcher ---
        if ((float)($src['CRSH_STRETCHERWEIGHT'] ?? 0) > 0) {
            $stmt_l = $conn->prepare("SELECT LINE_PROCESS FROM STCHPROD1 WHERE PRODUCT_NO = :pno");
            $stmt_l->execute([':pno' => $strProductNo]);
            $stch_l = $stmt_l->fetchColumn() ?: 'X';

            $stmt_stch_ins = $conn->prepare("INSERT INTO STCHPROD1 (PRODUCT_NO, LINE_PROCESS, STCH_TIME, STCH_STARTDATE, STCH_STARTTIME, STCH_ENDDATE, STCH_ENDTIME, STCH_ACCEPTWEIGHT, STCH_ACCEPTPIECE, STCH_OPERATOR1, STCH_OPERATEDATE) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
            $stmt_stch_ins->execute([$strSplitProductNo, $stch_l, 1, $currentDate, $currentTime, $currentDate, $currentTime, $dblSplitNetWeight, $dblSplitPiece, $strUserOperator, $currentDateTime]);

            $stmt_stch_upd = $conn->prepare("UPDATE STCHPROD1 SET STCH_ACCEPTWEIGHT = ?, STCH_ACCEPTPIECE = ?, STCH_UPDATE = ?, STCH_UPDATEDATE = ? WHERE PRODUCT_NO = ?");
            $stmt_stch_upd->execute([$stch_wt, $stch_pc, $strUserOperator, $currentDateTime, $strProductNo]);
        }

        // --- Cut Sheet ---
        if ((float)($src['CRSH_CUTSHEETWEIGHT'] ?? 0) > 0) {
            $stmt_ctsh_ins = $conn->prepare("INSERT INTO CTSHPROD1 (PRODUCT_NO, LINE_PROCESS, PRODUCT_ID, CTSH_TIME, CTSH_STARTDATE, CTSH_STARTTIME, CTSH_ENDDATE, CTSH_ENDTIME, CTSH_ACCEPTWEIGHT, CTSH_ACCEPTPIECE, CTSH_OPERATOR1, CTSH_OPERATEDATE) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt_ctsh_ins->execute([$strSplitProductNo, '1', $src['PRODUCT_ID'] ?? '', 1, $currentDate, $currentTime, $currentDate, $currentTime, $dblSplitNetWeight, $dblSplitPiece, $strUserOperator, $currentDateTime]);

            $stmt_ctsh_upd = $conn->prepare("UPDATE CTSHPROD1 SET CTSH_ACCEPTWEIGHT = ?, CTSH_ACCEPTPIECE = ?, CTSH_UPDATE = ?, CTSH_UPDATEDATE = ? WHERE PRODUCT_NO = ?");
            $stmt_ctsh_upd->execute([$ctsh_wt, $ctsh_pc, $strUserOperator, $currentDateTime, $strProductNo]);
        }

        // --- Shear ---
        if ((float)($src['CRSH_SHEARWEIGHT'] ?? 0) > 0) {
            $stmt_shrs_ins = $conn->prepare("INSERT INTO SHRSPROD1 (PRODUCT_NO, SHRS_STARTDATE, SHRS_STARTTIME, SHRS_ENDDATE, SHRS_ENDTIME, SHRS_ACCEPTWEIGHT, SHRS_ACCEPTPIECE, SHRS_OPERATOR1, SHRS_OPERATEDATE) VALUES (?,?,?,?,?,?,?,?,?)");
            $stmt_shrs_ins->execute([$strSplitProductNo, $currentDate, $currentTime, $currentDate, $currentTime, $dblSplitNetWeight, $dblSplitPiece, $strUserOperator, $currentDateTime]);

            $stmt_shrs_upd = $conn->prepare("UPDATE SHRSPROD1 SET SHRS_ACCEPTWEIGHT = ?, SHRS_ACCEPTPIECE = ?, SHRS_UPDATE = ?, SHRS_UPDATEDATE = ? WHERE PRODUCT_NO = ?");
            $stmt_shrs_upd->execute([$shrs_wt, $shrs_pc, $strUserOperator, $currentDateTime, $strProductNo]);
        }

        // --- Punch Hole ---
        if ((float)($src['CRSH_PUNCHWEIGHT'] ?? 0) > 0) {
            $stmt_pchl_ins = $conn->prepare("INSERT INTO PCHLPROD1 (PRODUCT_NO, LINE_PROCESS, PRODUCT_ID, PCHL_TIME, PCHL_STARTDATE, PCHL_STARTTIME, PCHL_ENDDATE, PCHL_ENDTIME, PCHL_ACCEPTWEIGHT, PCHL_ACCEPTPIECE, PCHL_OPERATOR1, PCHL_OPERATEDATE) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt_pchl_ins->execute([$strSplitProductNo, '1', $src['PRODUCT_ID'] ?? '', 1, $currentDate, $currentTime, $currentDate, $currentTime, $dblSplitNetWeight, $dblSplitPiece, $strUserOperator, $currentDateTime]);

            $stmt_pchl_upd = $conn->prepare("UPDATE PCHLPROD1 SET PCHL_ACCEPTWEIGHT = ?, PCHL_ACCEPTPIECE = ?, PCHL_UPDATE = ?, PCHL_UPDATEDATE = ? WHERE PRODUCT_NO = ?");
            $stmt_pchl_upd->execute([$pchl_wt, $pchl_pc, $strUserOperator, $currentDateTime, $strProductNo]);
        }

        // --- Batch Anneal ---
        if ((float)($src['CRSH_BATCHANNEALWEIGHT'] ?? 0) > 0) {
            $stmt_b0 = $conn->prepare("SELECT TOP 1 * FROM BTCHPROD0 WHERE PRODUCT_NO = :pno ORDER BY BTCH_NO DESC");
            $stmt_b0->execute([':pno' => $strProductNo]);
            $btch0 = $stmt_b0->fetch(PDO::FETCH_ASSOC);

            if ($btch0) {
                $strBatchNo = $btch0['BTCH_NO'];
                $orgInWt = round(($btch0['BTCH_INPUTWEIGHT'] ?? 0) - $dblSplitNetWeight);
                $orgInPc = round(($btch0['BTCH_INPUTPIECE'] ?? 0) - $dblSplitPiece);

                $sql_ins_b0 = "INSERT INTO BTCHPROD0 (
                    BTCH_NO, PRODUCT_NO, PALLET_ITEM, SALEORDER_NO, SALEORDER_ITEM, JOB_ORDER, PRODUCT_ID, ALLOY, TEMPER_INITIAL,
                    TEMPER_TARGET, THICKNESS, WIDTH, LENGTH, BTCH_INPUTWEIGHT, BTCH_INPUTPIECE, BTCH_TOTALWEIGHT, BTCH_INPUTDATE, BTCH_INPUTTIME,
                    CONTROL_BY, RANGE_FROM, RANGE_TO, TP_HEATTIME, TP_HEATTEMPERATURE, TP_SOAKTIME, TP_SOAKTEMPERATURE, TP_WPHEATTEMPERATURE,
                    TP_WPSOAKTEMPERATUREFROM, TP_WPSOAKTEMPERATURETO, MS_HEATTIME, MS_HEATTEMPERATURE, MS_SOAKTIME, MS_SOAKTEMPERATURE,
                    MS_WPHEATTEMPERATURE, MS_WPSOAKTEMPERATURE, AS_HEATTIME, AS_HEATTEMPERATURE, AS_SOAKTIME, AS_SOAKTEMPERATURE,
                    AS_WPHEATTEMPERATURE, AS_WPSOAKTEMPERATURE, AC_HEATTIME, AC_HEATTEMPERATURE, AC_SOAKTIME, AC_SOAKTEMPERATURE,
                    AC_WPHEATTEMPERATURE, AC_WPSOAKTEMPERATURE, N2_STORAGE, N2_START, N2_END, N2_METER, BTCH_STARTDATE, BTCH_STARTTIME,
                    BTCH_EXPECTDATE, BTCH_EXPECTTIME, BTCH_PRODREMARK, BTCH_TECHREMARK, BTCH_PRINTNOTE, BTCH_OPERATOR, BTCH_OPERATEDATE,
                    BTCH_UPDATE, BTCH_UPDATEDATE, BTCH_STATUS
                ) VALUES (
                    :bno, :split_pno, :pallet_item, :so_no, :so_item, :job_order, :prod_id, :alloy, :temper_init,
                    :temper_tgt, :thickness, :width, :length, :in_wt, :in_pc, :tot_wt, :in_date, :in_time,
                    :ctl, :rf, :rt, :tp_ht, :tp_htemp, :tp_st, :tp_stemp, :tp_wpht,
                    :tp_wpstf, :tp_wpstt, :ms_ht, :ms_htemp, :ms_st, :ms_stemp,
                    :ms_wpht, :ms_wpst, :as_ht, :as_htemp, :as_st, :as_stemp,
                    :as_wpht, :as_wpst, :ac_ht, :ac_htemp, :ac_st, :ac_stemp,
                    :ac_wpht, :ac_wpst, :n2_stor, :n2_st, :n2_end, :n2_mtr, :st_date, :st_time,
                    :exp_date, :exp_time, :prod_rem, :tech_rem, :prt_note, :operator, :opDate,
                    :upd, :updDate, :status
                )";

                $stmt_ins_b0 = $conn->prepare($sql_ins_b0);
                $stmt_ins_b0->execute([
                    ':bno'        => $strBatchNo,
                    ':split_pno'  => $strSplitProductNo,
                    ':pallet_item'=> $btch0['PALLET_ITEM'] ?? '',
                    ':so_no'      => $btch0['SALEORDER_NO'] ?? '',
                    ':so_item'    => $btch0['SALEORDER_ITEM'] ?? '',
                    ':job_order'  => $btch0['JOB_ORDER'] ?? '',
                    ':prod_id'    => $btch0['PRODUCT_ID'] ?? '',
                    ':alloy'      => $btch0['ALLOY'] ?? '',
                    ':temper_init'=> $btch0['TEMPER_INITIAL'] ?? '',
                    ':temper_tgt' => $btch0['TEMPER_TARGET'] ?? '',
                    ':thickness'  => $btch0['THICKNESS'] ?? 0,
                    ':width'      => $btch0['WIDTH'] ?? 0,
                    ':length'     => $btch0['LENGTH'] ?? 0,
                    ':in_wt'      => $dblSplitNetWeight,
                    ':in_pc'      => $dblSplitPiece,
                    ':tot_wt'     => $btch0['BTCH_TOTALWEIGHT'] ?? 0,
                    ':in_date'    => $btch0['BTCH_INPUTDATE'] ?? $currentDate,
                    ':in_time'    => $btch0['BTCH_INPUTTIME'] ?? $currentTime,
                    ':ctl'        => $btch0['CONTROL_BY'] ?? '',
                    ':rf'         => $btch0['RANGE_FROM'] ?? 0,
                    ':rt'         => $btch0['RANGE_TO'] ?? 0,
                    ':tp_ht'      => $btch0['TP_HEATTIME'] ?? 0,
                    ':tp_htemp'   => $btch0['TP_HEATTEMPERATURE'] ?? 0,
                    ':tp_st'      => $btch0['TP_SOAKTIME'] ?? 0,
                    ':tp_stemp'   => $btch0['TP_SOAKTEMPERATURE'] ?? 0,
                    ':tp_wpht'    => $btch0['TP_WPHEATTEMPERATURE'] ?? 0,
                    ':tp_wpstf'   => $btch0['TP_WPSOAKTEMPERATUREFROM'] ?? 0,
                    ':tp_wpstt'   => $btch0['TP_WPSOAKTEMPERATURETO'] ?? 0,
                    ':ms_ht'      => $btch0['MS_HEATTIME'] ?? 0,
                    ':ms_htemp'   => $btch0['MS_HEATTEMPERATURE'] ?? 0,
                    ':ms_st'      => $btch0['MS_SOAKTIME'] ?? 0,
                    ':ms_stemp'   => $btch0['MS_SOAKTEMPERATURE'] ?? 0,
                    ':ms_wpht'    => $btch0['MS_WPHEATTEMPERATURE'] ?? 0,
                    ':ms_wpst'    => $btch0['MS_WPSOAKTEMPERATURE'] ?? 0,
                    ':as_ht'      => $btch0['AS_HEATTIME'] ?? 0,
                    ':as_htemp'   => $btch0['AS_HEATTEMPERATURE'] ?? 0,
                    ':as_st'      => $btch0['AS_SOAKTIME'] ?? 0,
                    ':as_stemp'   => $btch0['AS_SOAKTEMPERATURE'] ?? 0,
                    ':as_wpht'    => $btch0['AS_WPHEATTEMPERATURE'] ?? 0,
                    ':as_wpst'    => $btch0['AS_WPSOAKTEMPERATURE'] ?? 0,
                    ':ac_ht'      => $btch0['AC_HEATTIME'] ?? 0,
                    ':ac_htemp'   => $btch0['AC_HEATTEMPERATURE'] ?? 0,
                    ':ac_st'      => $btch0['AC_SOAKTIME'] ?? 0,
                    ':ac_stemp'   => $btch0['AC_SOAKTEMPERATURE'] ?? 0,
                    ':ac_wpht'    => $btch0['AC_WPHEATTEMPERATURE'] ?? 0,
                    ':ac_wpst'    => $btch0['AC_WPSOAKTEMPERATURE'] ?? 0,
                    ':n2_stor'    => $btch0['N2_STORAGE'] ?? 0,
                    ':n2_st'      => $btch0['N2_START'] ?? 0,
                    ':n2_end'     => $btch0['N2_END'] ?? 0,
                    ':n2_mtr'     => $btch0['N2_METER'] ?? 0,
                    ':st_date'    => $btch0['BTCH_STARTDATE'] ?? $currentDate,
                    ':st_time'    => $btch0['BTCH_STARTTIME'] ?? $currentTime,
                    ':exp_date'   => $btch0['BTCH_EXPECTDATE'] ?? $currentDate,
                    ':exp_time'   => $btch0['BTCH_EXPECTTIME'] ?? $currentTime,
                    ':prod_rem'   => $btch0['BTCH_PRODREMARK'] ?? '',
                    ':tech_rem'   => $btch0['BTCH_TECHREMARK'] ?? '',
                    ':prt_note'   => $btch0['BTCH_PRINTNOTE'] ?? '',
                    ':operator'   => $btch0['BTCH_OPERATOR'] ?? $strUserOperator,
                    ':opDate'     => $btch0['BTCH_OPERATEDATE'] ?? $currentDateTime,
                    ':upd'        => $strUserOperator,
                    ':updDate'    => $currentDateTime,
                    ':status'     => $btch0['BTCH_STATUS'] ?? 'OP'
                ]);

                $stmt_b0_upd = $conn->prepare("UPDATE BTCHPROD0 SET BTCH_INPUTWEIGHT = ?, BTCH_INPUTPIECE = ?, BTCH_UPDATE = ?, BTCH_UPDATEDATE = ? WHERE BTCH_NO = ? AND PRODUCT_NO = ?");
                $stmt_b0_upd->execute([$orgInWt, $orgInPc, $strUserOperator, $currentDateTime, $strBatchNo, $strProductNo]);
            }

            $stmt_b1 = $conn->prepare("SELECT TOP 1 * FROM BTCHPROD1 WHERE PRODUCT_NO = :pno ORDER BY BTCH_NO DESC");
            $stmt_b1->execute([':pno' => $strProductNo]);
            $btch1 = $stmt_b1->fetch(PDO::FETCH_ASSOC);

            if ($btch1) {
                $strBatchNo = $btch1['BTCH_NO'];
                $orgAccWt = round(($btch1['BTCH_ACCEPTWEIGHT'] ?? 0) - $dblSplitNetWeight);
                $orgAccPc = round(($btch1['BTCH_ACCEPTPIECE'] ?? 0) - $dblSplitPiece);

                $sql_ins_b1 = "INSERT INTO BTCHPROD1 (
                    PRODUCT_NO, BTCH_NO, PALLET_ITEM, LINE_PROCESS, PRODUCT_ID, TEMPER, BTCH_TIME,
                    BTCH_UTS, BTCH_YS, BTCH_ELONGATION, BTCH_ACTUTS, BTCH_ACTYS, BTCH_ACTELONGATION,
                    BTCH_RESULT, BTCH_STARTDATE, BTCH_STARTTIME, BTCH_ENDDATE, BTCH_ENDTIME, BTCH_ACCEPTWEIGHT,
                    BTCH_ACCEPTPIECE, BTCH_REJECTWEIGHT, BTCH_OPERATOR1, BTCH_OPERATEDATE
                ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

                $stmt_ins_b1 = $conn->prepare($sql_ins_b1);
                $stmt_ins_b1->execute([
                    $strSplitProductNo,
                    $btch1['BTCH_NO'] ?? '',
                    $btch1['PALLET_ITEM'] ?? '',
                    $btch1['LINE_PROCESS'] ?? '',
                    $btch1['PRODUCT_ID'] ?? '',
                    $btch1['TEMPER'] ?? '',
                    1,
                    $btch1['BTCH_UTS'] ?? 0,
                    $btch1['BTCH_YS'] ?? 0,
                    $btch1['BTCH_ELONGATION'] ?? 0,
                    $btch1['BTCH_ACTUTS'] ?? 0,
                    $btch1['BTCH_ACTYS'] ?? 0,
                    $btch1['BTCH_ACTELONGATION'] ?? 0,
                    $btch1['BTCH_RESULT'] ?? '',
                    $btch1['BTCH_STARTDATE'] ?? $currentDate,
                    $btch1['BTCH_STARTTIME'] ?? $currentTime,
                    $btch1['BTCH_ENDDATE'] ?? $currentDate,
                    $btch1['BTCH_ENDTIME'] ?? $currentTime,
                    $dblSplitNetWeight,
                    $dblSplitPiece,
                    0,
                    $strUserOperator,
                    $currentDateTime
                ]);

                $stmt_b1_upd = $conn->prepare("UPDATE BTCHPROD1 SET BTCH_ACCEPTWEIGHT = ?, BTCH_ACCEPTPIECE = ?, BTCH_UPDATE = ?, BTCH_UPDATEDATE = ? WHERE BTCH_NO = ? AND PRODUCT_NO = ?");
                $stmt_b1_upd->execute([$orgAccWt, $orgAccPc, $strUserOperator, $currentDateTime, $strBatchNo, $strProductNo]);
            }
        }

        // --- Transfer ---
        if ((float)($src['CRSH_TRANSFERWEIGHT'] ?? 0) > 0) {
            $stmt_tptp_ins = $conn->prepare("INSERT INTO TPTPPROD1 (PRODUCT_NO, LINE_PROCESS, PRODUCT_ID, TPTP_TIME, TPTP_STARTDATE, TPTP_STARTTIME, TPTP_ENDDATE, TPTP_ENDTIME, TPTP_ACCEPTWEIGHT, TPTP_ACCEPTPIECE, TPTP_OPERATOR1, TPTP_OPERATEDATE) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt_tptp_ins->execute([$strSplitProductNo, '1', $src['PRODUCT_ID'] ?? '', 1, $currentDate, $currentTime, $currentDate, $currentTime, $dblSplitNetWeight, $dblSplitPiece, $strUserOperator, $currentDateTime]);

            $stmt_tptp_upd = $conn->prepare("UPDATE TPTPPROD1 SET TPTP_ACCEPTWEIGHT = ?, TPTP_ACCEPTPIECE = ?, TPTP_UPDATE = ?, TPTP_UPDATEDATE = ? WHERE PRODUCT_NO = ?");
            $stmt_tptp_upd->execute([$tptp_wt, $tptp_pc, $strUserOperator, $currentDateTime, $strProductNo]);
        }

        // --- Anneal ---
        if ((float)($src['CRSH_ANNEALWEIGHT'] ?? 0) > 0) {
            $stmt_l = $conn->prepare("SELECT LINE_PROCESS FROM ANNLPROD1 WHERE PRODUCT_NO = :pno");
            $stmt_l->execute([':pno' => $strProductNo]);
            $annl_l = $stmt_l->fetchColumn() ?: '1';

            $stmt_annl_ins = $conn->prepare("INSERT INTO ANNLPROD1 (PRODUCT_NO, LINE_PROCESS, PRODUCT_ID, TEMPER, ANNL_TIME, ANNL_STARTDATE, ANNL_STARTTIME, ANNL_ENDDATE, ANNL_ENDTIME, ANNL_ACCEPTWEIGHT, ANNL_ACCEPTPIECE, ANNL_OPERATOR1, ANNL_OPERATEDATE) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt_annl_ins->execute([$strSplitProductNo, $annl_l, $src['PRODUCT_ID'] ?? '', $src['TEMPER'] ?? '', 1, $currentDate, $currentTime, $currentDate, $currentTime, $dblSplitNetWeight, $dblSplitPiece, $strUserOperator, $currentDateTime]);

            $stmt_annl_upd = $conn->prepare("UPDATE ANNLPROD1 SET ANNL_ACCEPTWEIGHT = ?, ANNL_ACCEPTPIECE = ?, ANNL_UPDATE = ?, ANNL_UPDATEDATE = ? WHERE PRODUCT_NO = ?");
            $stmt_annl_upd->execute([$annl_wt, $annl_pc, $strUserOperator, $currentDateTime, $strProductNo]);
        }

        // --- Sort ---
        if ((float)($src['CRSH_SORTWEIGHT'] ?? 0) > 0) {
            $stmt_sort_ins = $conn->prepare("INSERT INTO SORTPROD1 (PRODUCT_NO, SCRAP_NO, SORT_STARTDATE, SORT_STARTTIME, SORT_ENDDATE, SORT_ENDTIME, SORT_ACCEPTWEIGHT, SORT_ACCEPTPIECE, SORT_OPERATOR1, SORT_OPERATEDATE) VALUES (?,?,?,?,?,?,?,?,?,?)");
            $stmt_sort_ins->execute([$strSplitProductNo, $strProductNo, $currentDate, $currentTime, $currentDate, $currentTime, $dblSplitNetWeight, $dblSplitPiece, $strUserOperator, $currentDateTime]);

            $stmt_sort_upd = $conn->prepare("UPDATE SORTPROD1 SET SORT_ACCEPTWEIGHT = ?, SORT_ACCEPTPIECE = ?, SORT_UPDATE = ?, SORT_UPDATEDATE = ? WHERE PRODUCT_NO = ?");
            $stmt_sort_upd->execute([$sort_wt, $sort_pc, $strUserOperator, $currentDateTime, $strProductNo]);
        }

        // =========================================================
        // 7. UPDATE ปรับลดค่าน้ำหนักหลักใน CRSHPROD1 ของ Main Product
        // =========================================================
        $sql_upd_main = "UPDATE CRSHPROD1 SET 
            CRSH_ACTUALWEIGHT = :main_wt,
            CRSH_ACTUALPIECE = :main_pc,
            CRSH_PRODUCEWEIGHT = :main_wt,
            CRSH_PRODUCEPIECE = :main_pc,
            CRSH_STRETCHERWEIGHT = :stch_wt,
            CRSH_STRETCHERPIECE = :stch_pc,
            CRSH_CUTSHEETWEIGHT = :ctsh_wt,
            CRSH_CUTSHEETPIECE = :ctsh_pc,
            CRSH_SHEARWEIGHT = :shrs_wt,
            CRSH_SHEARPIECE = :shrs_pc,
            CRSH_PUNCHWEIGHT = :pchl_wt,
            CRSH_PUNCHPIECE = :pchl_pc,
            CRSH_BATCHANNEALWEIGHT = :btch_wt,
            CRSH_BATCHANNEALPIECE = :btch_pc,
            CRSH_TRANSFERWEIGHT = :tptp_wt,
            CRSH_TRANSFERPIECE = :tptp_pc,
            CRSH_ANNEALWEIGHT = :annl_wt,
            CRSH_ANNEALPIECE = :annl_pc,
            CRSH_SORTWEIGHT = :sort_wt,
            CRSH_SORTPIECE = :sort_pc,
            CRSH_BOTTOMWEIGHT = :main_btm_wt,
            CRSH_UPDATE = :operator,
            CRSH_UPDATEDATE = :opDate 
            WHERE PRODUCT_NO = :pno";

        $stmt_upd_main = $conn->prepare($sql_upd_main);
        $stmt_upd_main->execute([
            ':main_wt'     => $dblMainNetWeight,
            ':main_pc'     => $dblMainPiece,
            ':stch_wt'     => $stch_wt,
            ':stch_pc'     => $stch_pc,
            ':ctsh_wt'     => $ctsh_wt,
            ':ctsh_pc'     => $ctsh_pc,
            ':shrs_wt'     => $shrs_wt,
            ':shrs_pc'     => $shrs_pc,
            ':pchl_wt'     => $pchl_wt,
            ':pchl_pc'     => $pchl_pc,
            ':btch_wt'     => $btch_wt,
            ':btch_pc'     => $btch_pc,
            ':tptp_wt'     => $tptp_wt,
            ':tptp_pc'     => $tptp_pc,
            ':annl_wt'     => $annl_wt,
            ':annl_pc'     => $annl_pc,
            ':sort_wt'     => $sort_wt,
            ':sort_pc'     => $sort_pc,
            ':main_btm_wt' => $dblMainBottomWeight,
            ':operator'    => $strUserOperator,
            ':opDate'      => $currentDateTime,
            ':pno'         => $strProductNo
        ]);

    } else if ($mode === 'UPDATE') {
        // =========================================================
        // กรณี UPDATE PALLET: คำนวณส่วนต่าง (Diff) และปรับยอดตารางย้อนหลังทั้งหมด
        // =========================================================
        
        // 1. ดึงค่าน้ำหนัก/ชิ้น เดิมของ Split Pallet จาก SPLTPROD1 มาคำนวณส่วนต่าง
        $stmt_old_splt = $conn->prepare("SELECT SPLT_ACCEPTWEIGHT, SPLT_ACCEPTPIECE FROM SPLTPROD1 WHERE PRODUCT_NO = :pno");
        $stmt_old_splt->execute([':pno' => $strProductNo]);
        $old_splt = $stmt_old_splt->fetch(PDO::FETCH_ASSOC);

        $old_split_wt = (float)($old_splt['SPLT_ACCEPTWEIGHT'] ?? 0);
        $old_split_pc = (float)($old_splt['SPLT_ACCEPTPIECE'] ?? 0);

        // คำนวณส่วนต่างค่าน้ำหนักและชิ้น (Diff)
        $diff_wt = $dblSplitNetWeight - $old_split_wt;
        $diff_pc = $dblSplitPiece - $old_split_pc;

        // 2. ดึงข้อมูล History Production ปัจจุบันของ Pallet หลัก (Ref Product)
        $stmt_ref_crsh = $conn->prepare("SELECT * FROM CRSHPROD1 WHERE PRODUCT_NO = :pno");
        $stmt_ref_crsh->execute([':pno' => $strProductRef]);
        $ref_crsh = $stmt_ref_crsh->fetch(PDO::FETCH_ASSOC);

        if ($ref_crsh) {
            // คำนวณปรับยอด History Production ฝั่ง Main (Ref Product)
            $ref_stch_wt = max(0, (float)($ref_crsh['CRSH_STRETCHERWEIGHT'] ?? 0) - $diff_wt);
            $ref_stch_pc = max(0, (float)($ref_crsh['CRSH_STRETCHERPIECE'] ?? 0) - $diff_pc);

            $ref_ctsh_wt = max(0, (float)($ref_crsh['CRSH_CUTSHEETWEIGHT'] ?? 0) - $diff_wt);
            $ref_ctsh_pc = max(0, (float)($ref_crsh['CRSH_CUTSHEETPIECE'] ?? 0) - $diff_pc);

            $ref_shrs_wt = max(0, (float)($ref_crsh['CRSH_SHEARWEIGHT'] ?? 0) - $diff_wt);
            $ref_shrs_pc = max(0, (float)($ref_crsh['CRSH_SHEARPIECE'] ?? 0) - $diff_pc);

            $ref_pchl_wt = max(0, (float)($ref_crsh['CRSH_PUNCHWEIGHT'] ?? 0) - $diff_wt);
            $ref_pchl_pc = max(0, (float)($ref_crsh['CRSH_PUNCHPIECE'] ?? 0) - $diff_pc);

            $ref_btch_wt = max(0, (float)($ref_crsh['CRSH_BATCHANNEALWEIGHT'] ?? 0) - $diff_wt);
            $ref_btch_pc = max(0, (float)($ref_crsh['CRSH_BATCHANNEALPIECE'] ?? 0) - $diff_pc);

            $ref_tptp_wt = max(0, (float)($ref_crsh['CRSH_TRANSFERWEIGHT'] ?? 0) - $diff_wt);
            $ref_tptp_pc = max(0, (float)($ref_crsh['CRSH_TRANSFERPIECE'] ?? 0) - $diff_pc);

            $ref_annl_wt = max(0, (float)($ref_crsh['CRSH_ANNEALWEIGHT'] ?? 0) - $diff_wt);
            $ref_annl_pc = max(0, (float)($ref_crsh['CRSH_ANNEALPIECE'] ?? 0) - $diff_pc);

            $ref_sort_wt = max(0, (float)($ref_crsh['CRSH_SORTWEIGHT'] ?? 0) - $diff_wt);
            $ref_sort_pc = max(0, (float)($ref_crsh['CRSH_SORTPIECE'] ?? 0) - $diff_pc);

            // 3. UPDATE ปรับยอดใน CRSHPROD1 ของ Pallet หลัก (Ref Product)
            $sql_upd_ref = "UPDATE CRSHPROD1 SET 
                CRSH_ACTUALWEIGHT = :main_wt,
                CRSH_ACTUALPIECE = :main_pc,
                CRSH_PRODUCEWEIGHT = :main_wt,
                CRSH_PRODUCEPIECE = :main_pc,
                CRSH_STRETCHERWEIGHT = :stch_wt, CRSH_STRETCHERPIECE = :stch_pc,
                CRSH_CUTSHEETWEIGHT = :ctsh_wt, CRSH_CUTSHEETPIECE = :ctsh_pc,
                CRSH_SHEARWEIGHT = :shrs_wt, CRSH_SHEARPIECE = :shrs_pc,
                CRSH_PUNCHWEIGHT = :pchl_wt, CRSH_PUNCHPIECE = :pchl_pc,
                CRSH_BATCHANNEALWEIGHT = :btch_wt, CRSH_BATCHANNEALPIECE = :btch_pc,
                CRSH_TRANSFERWEIGHT = :tptp_wt, CRSH_TRANSFERPIECE = :tptp_pc,
                CRSH_ANNEALWEIGHT = :annl_wt, CRSH_ANNEALPIECE = :annl_pc,
                CRSH_SORTWEIGHT = :sort_wt, CRSH_SORTPIECE = :sort_pc,
                CRSH_BOTTOMWEIGHT = :main_btm_wt,
                CRSH_UPDATE = :operator,
                CRSH_UPDATEDATE = :opDate 
                WHERE PRODUCT_NO = :pno";

            $stmt_upd_ref = $conn->prepare($sql_upd_ref);
            $stmt_upd_ref->execute([
                ':main_wt'     => $dblMainNetWeight,
                ':main_pc'     => $dblMainPiece,
                ':stch_wt'     => $ref_stch_wt, ':stch_pc' => $ref_stch_pc,
                ':ctsh_wt'     => $ref_ctsh_wt, ':ctsh_pc' => $ref_ctsh_pc,
                ':shrs_wt'     => $ref_shrs_wt, ':shrs_pc' => $ref_shrs_pc,
                ':pchl_wt'     => $ref_pchl_wt, ':pchl_pc' => $ref_pchl_pc,
                ':btch_wt'     => $ref_btch_wt, ':btch_pc' => $ref_btch_pc,
                ':tptp_wt'     => $ref_tptp_wt, ':tptp_pc' => $ref_tptp_pc,
                ':annl_wt'     => $ref_annl_wt, ':annl_pc' => $ref_annl_pc,
                ':sort_wt'     => $ref_sort_wt, ':sort_pc' => $ref_sort_pc,
                ':main_btm_wt' => $dblMainBottomWeight,
                ':operator'    => $strUserOperator,
                ':opDate'      => $currentDateTime,
                ':pno'         => $strProductRef
            ]);
        }

        // 4. UPDATE ปรับยอดใน CRSHPROD1 ของ Split Product (Target Product No)
        $sql_upd_split = "UPDATE CRSHPROD1 SET 
            CRSH_ACTUALWEIGHT = :split_wt,
            CRSH_ACTUALPIECE = :split_pc,
            CRSH_PRODUCEWEIGHT = :split_wt,
            CRSH_PRODUCEPIECE = :split_pc,
            CRSH_STRETCHERWEIGHT = CASE WHEN CRSH_STRETCHERWEIGHT > 0 THEN :split_wt ELSE 0 END,
            CRSH_STRETCHERPIECE  = CASE WHEN CRSH_STRETCHERPIECE > 0 THEN :split_pc ELSE 0 END,
            CRSH_CUTSHEETWEIGHT  = CASE WHEN CRSH_CUTSHEETWEIGHT > 0 THEN :split_wt ELSE 0 END,
            CRSH_CUTSHEETPIECE   = CASE WHEN CRSH_CUTSHEETPIECE > 0 THEN :split_pc ELSE 0 END,
            CRSH_SHEARWEIGHT     = CASE WHEN CRSH_SHEARWEIGHT > 0 THEN :split_wt ELSE 0 END,
            CRSH_SHEARPIECE      = CASE WHEN CRSH_SHEARPIECE > 0 THEN :split_pc ELSE 0 END,
            CRSH_PUNCHWEIGHT     = CASE WHEN CRSH_PUNCHWEIGHT > 0 THEN :split_wt ELSE 0 END,
            CRSH_PUNCHPIECE      = CASE WHEN CRSH_PUNCHPIECE > 0 THEN :split_pc ELSE 0 END,
            CRSH_BATCHANNEALWEIGHT = CASE WHEN CRSH_BATCHANNEALWEIGHT > 0 THEN :split_wt ELSE 0 END,
            CRSH_BATCHANNEALPIECE  = CASE WHEN CRSH_BATCHANNEALPIECE > 0 THEN :split_pc ELSE 0 END,
            CRSH_TRANSFERWEIGHT  = CASE WHEN CRSH_TRANSFERWEIGHT > 0 THEN :split_wt ELSE 0 END,
            CRSH_TRANSFERPIECE   = CASE WHEN CRSH_TRANSFERPIECE > 0 THEN :split_pc ELSE 0 END,
            CRSH_ANNEALWEIGHT    = CASE WHEN CRSH_ANNEALWEIGHT > 0 THEN :split_wt ELSE 0 END,
            CRSH_ANNEALPIECE     = CASE WHEN CRSH_ANNEALPIECE > 0 THEN :split_pc ELSE 0 END,
            CRSH_SORTWEIGHT      = CASE WHEN CRSH_SORTWEIGHT > 0 THEN :split_wt ELSE 0 END,
            CRSH_SORTPIECE       = CASE WHEN CRSH_SORTPIECE > 0 THEN :split_pc ELSE 0 END,
            CRSH_BOTTOMWEIGHT    = :split_btm_wt,
            CRSH_UPDATE = :operator,
            CRSH_UPDATEDATE = :opDate 
            WHERE PRODUCT_NO = :pno";

        $stmt_upd_split = $conn->prepare($sql_upd_split);
        $stmt_upd_split->execute([
            ':split_wt'     => $dblSplitNetWeight,
            ':split_pc'     => $dblSplitPiece,
            ':split_btm_wt' => $dblSplitBottomWeight,
            ':operator'     => $strUserOperator,
            ':opDate'       => $currentDateTime,
            ':pno'          => $strProductNo
        ]);

        // 5. UPDATE ตารางประวัติกระบวนการย้อนหลังแยกตามรายตาราง (Process Tables)
        
        // --- STCHPROD1 (Stretcher) ---
        $stmt_stch_ref = $conn->prepare("UPDATE STCHPROD1 SET STCH_ACCEPTWEIGHT = ?, STCH_ACCEPTPIECE = ?, STCH_UPDATE = ?, STCH_UPDATEDATE = ? WHERE PRODUCT_NO = ?");
        $stmt_stch_ref->execute([$ref_stch_wt, $ref_stch_pc, $strUserOperator, $currentDateTime, $strProductRef]);

        $stmt_stch_splt = $conn->prepare("UPDATE STCHPROD1 SET STCH_ACCEPTWEIGHT = ?, STCH_ACCEPTPIECE = ?, STCH_UPDATE = ?, STCH_UPDATEDATE = ? WHERE PRODUCT_NO = ?");
        $stmt_stch_splt->execute([$dblSplitNetWeight, $dblSplitPiece, $strUserOperator, $currentDateTime, $strProductNo]);

        // --- CTSHPROD1 (Cut Sheet) ---
        $stmt_ctsh_ref = $conn->prepare("UPDATE CTSHPROD1 SET CTSH_ACCEPTWEIGHT = ?, CTSH_ACCEPTPIECE = ?, CTSH_UPDATE = ?, CTSH_UPDATEDATE = ? WHERE PRODUCT_NO = ?");
        $stmt_ctsh_ref->execute([$ref_ctsh_wt, $ref_ctsh_pc, $strUserOperator, $currentDateTime, $strProductRef]);

        $stmt_ctsh_splt = $conn->prepare("UPDATE CTSHPROD1 SET CTSH_ACCEPTWEIGHT = ?, CTSH_ACCEPTPIECE = ?, CTSH_UPDATE = ?, CTSH_UPDATEDATE = ? WHERE PRODUCT_NO = ?");
        $stmt_ctsh_splt->execute([$dblSplitNetWeight, $dblSplitPiece, $strUserOperator, $currentDateTime, $strProductNo]);

        // --- SHRSPROD1 (Shear) ---
        $stmt_shrs_ref = $conn->prepare("UPDATE SHRSPROD1 SET SHRS_ACCEPTWEIGHT = ?, SHRS_ACCEPTPIECE = ?, SHRS_UPDATE = ?, SHRS_UPDATEDATE = ? WHERE PRODUCT_NO = ?");
        $stmt_shrs_ref->execute([$ref_shrs_wt, $ref_shrs_pc, $strUserOperator, $currentDateTime, $strProductRef]);

        $stmt_shrs_splt = $conn->prepare("UPDATE SHRSPROD1 SET SHRS_ACCEPTWEIGHT = ?, SHRS_ACCEPTPIECE = ?, SHRS_UPDATE = ?, SHRS_UPDATEDATE = ? WHERE PRODUCT_NO = ?");
        $stmt_shrs_splt->execute([$dblSplitNetWeight, $dblSplitPiece, $strUserOperator, $currentDateTime, $strProductNo]);

        // --- PCHLPROD1 (Punch Hole) ---
        $stmt_pchl_ref = $conn->prepare("UPDATE PCHLPROD1 SET PCHL_ACCEPTWEIGHT = ?, PCHL_ACCEPTPIECE = ?, PCHL_UPDATE = ?, PCHL_UPDATEDATE = ? WHERE PRODUCT_NO = ?");
        $stmt_pchl_ref->execute([$ref_pchl_wt, $ref_pchl_pc, $strUserOperator, $currentDateTime, $strProductRef]);

        $stmt_pchl_splt = $conn->prepare("UPDATE PCHLPROD1 SET PCHL_ACCEPTWEIGHT = ?, PCHL_ACCEPTPIECE = ?, PCHL_UPDATE = ?, PCHL_UPDATEDATE = ? WHERE PRODUCT_NO = ?");
        $stmt_pchl_splt->execute([$dblSplitNetWeight, $dblSplitPiece, $strUserOperator, $currentDateTime, $strProductNo]);

        // --- BTCHPROD0 & BTCHPROD1 (Batch Anneal) ---
        $stmt_b0_ref = $conn->prepare("UPDATE BTCHPROD0 SET BTCH_INPUTWEIGHT = ?, BTCH_INPUTPIECE = ?, BTCH_UPDATE = ?, BTCH_UPDATEDATE = ? WHERE PRODUCT_NO = ?");
        $stmt_b0_ref->execute([$ref_btch_wt, $ref_btch_pc, $strUserOperator, $currentDateTime, $strProductRef]);

        $stmt_b0_splt = $conn->prepare("UPDATE BTCHPROD0 SET BTCH_INPUTWEIGHT = ?, BTCH_INPUTPIECE = ?, BTCH_UPDATE = ?, BTCH_UPDATEDATE = ? WHERE PRODUCT_NO = ?");
        $stmt_b0_splt->execute([$dblSplitNetWeight, $dblSplitPiece, $strUserOperator, $currentDateTime, $strProductNo]);

        $stmt_b1_ref = $conn->prepare("UPDATE BTCHPROD1 SET BTCH_ACCEPTWEIGHT = ?, BTCH_ACCEPTPIECE = ?, BTCH_UPDATE = ?, BTCH_UPDATEDATE = ? WHERE PRODUCT_NO = ?");
        $stmt_b1_ref->execute([$ref_btch_wt, $ref_btch_pc, $strUserOperator, $currentDateTime, $strProductRef]);

        $stmt_b1_splt = $conn->prepare("UPDATE BTCHPROD1 SET BTCH_ACCEPTWEIGHT = ?, BTCH_ACCEPTPIECE = ?, BTCH_UPDATE = ?, BTCH_UPDATEDATE = ? WHERE PRODUCT_NO = ?");
        $stmt_b1_splt->execute([$dblSplitNetWeight, $dblSplitPiece, $strUserOperator, $currentDateTime, $strProductNo]);

        // --- TPTPPROD1 (Transfer) ---
        $stmt_tptp_ref = $conn->prepare("UPDATE TPTPPROD1 SET TPTP_ACCEPTWEIGHT = ?, TPTP_ACCEPTPIECE = ?, TPTP_UPDATE = ?, TPTP_UPDATEDATE = ? WHERE PRODUCT_NO = ?");
        $stmt_tptp_ref->execute([$ref_tptp_wt, $ref_tptp_pc, $strUserOperator, $currentDateTime, $strProductRef]);

        $stmt_tptp_splt = $conn->prepare("UPDATE TPTPPROD1 SET TPTP_ACCEPTWEIGHT = ?, TPTP_ACCEPTPIECE = ?, TPTP_UPDATE = ?, TPTP_UPDATEDATE = ? WHERE PRODUCT_NO = ?");
        $stmt_tptp_splt->execute([$dblSplitNetWeight, $dblSplitPiece, $strUserOperator, $currentDateTime, $strProductNo]);

        // --- ANNLPROD1 (Anneal) ---
        $stmt_annl_ref = $conn->prepare("UPDATE ANNLPROD1 SET ANNL_ACCEPTWEIGHT = ?, ANNL_ACCEPTPIECE = ?, ANNL_UPDATE = ?, ANNL_UPDATEDATE = ? WHERE PRODUCT_NO = ?");
        $stmt_annl_ref->execute([$ref_annl_wt, $ref_annl_pc, $strUserOperator, $currentDateTime, $strProductRef]);

        $stmt_annl_splt = $conn->prepare("UPDATE ANNLPROD1 SET ANNL_ACCEPTWEIGHT = ?, ANNL_ACCEPTPIECE = ?, ANNL_UPDATE = ?, ANNL_UPDATEDATE = ? WHERE PRODUCT_NO = ?");
        $stmt_annl_splt->execute([$dblSplitNetWeight, $dblSplitPiece, $strUserOperator, $currentDateTime, $strProductNo]);

        // --- SORTPROD1 (Sort) ---
        $stmt_sort_ref = $conn->prepare("UPDATE SORTPROD1 SET SORT_ACCEPTWEIGHT = ?, SORT_ACCEPTPIECE = ?, SORT_UPDATE = ?, SORT_UPDATEDATE = ? WHERE PRODUCT_NO = ?");
        $stmt_sort_ref->execute([$ref_sort_wt, $ref_sort_pc, $strUserOperator, $currentDateTime, $strProductRef]);

        $stmt_sort_splt = $conn->prepare("UPDATE SORTPROD1 SET SORT_ACCEPTWEIGHT = ?, SORT_ACCEPTPIECE = ?, SORT_UPDATE = ?, SORT_UPDATEDATE = ? WHERE PRODUCT_NO = ?");
        $stmt_sort_splt->execute([$dblSplitNetWeight, $dblSplitPiece, $strUserOperator, $currentDateTime, $strProductNo]);

        // 6. UPDATE ตาราง SPLTPROD1
        $sql_upd_splt = "UPDATE SPLTPROD1 SET 
            SPLT_ACCEPTWEIGHT = :split_wt,
            SPLT_ACCEPTPIECE = :split_pc,
            SPLT_OPERATOR1 = :operator,
            SPLT_OPERATEDATE = :opDate 
            WHERE PRODUCT_NO = :pno";

        $stmt_upd_splt = $conn->prepare($sql_upd_splt);
        $stmt_upd_splt->execute([
            ':split_wt' => $dblSplitNetWeight,
            ':split_pc' => $dblSplitPiece,
            ':operator' => $strUserOperator,
            ':opDate'   => $currentDateTime,
            ':pno'      => $strProductNo
        ]);
    }

    $conn->commit();
    echo json_encode(['status' => 'success', 'message' => 'Split Pallet completed successfully']);

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}