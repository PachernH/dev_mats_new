<?php
// model/transfer_pallet_save_bk_mats.php (อ้างอิง f8 - ACRSHPROD2: สาย Blanking/Circle)
header('Content-Type: application/json; charset=utf-8');
session_start();

ini_set('display_errors', 0);
error_reporting(E_ALL);

include("../dbcon_mats-new.php");

$iduser_func = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : 'SYSTEM';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Request Method']);
    exit();
}

$product_no    = isset($_POST['product_no']) ? trim($_POST['product_no']) : '';
$job_order     = isset($_POST['job_order']) ? trim($_POST['job_order']) : '';
$product_id    = isset($_POST['product_id']) ? trim($_POST['product_id']) : '';
$temper        = isset($_POST['temper']) ? trim($_POST['temper']) : '';
$next_process  = isset($_POST['next_process']) ? trim($_POST['next_process']) : 'NN';

$start_date    = $_POST['start_date'] ?? date('Y-m-d');
$start_time    = (!empty($_POST['start_time']) ? $_POST['start_time'] : date('H:i')) . ":00";
$end_date      = $_POST['end_date'] ?? date('Y-m-d');
$end_time      = (!empty($_POST['end_time']) ? $_POST['end_time'] : date('H:i')) . ":00";

// ใน f8: รับค่าน้ำหนักเป็นหลัก แล้วนำไปคำนวณจำนวนชิ้น
$accept_weight = (float)($_POST['accept_weight'] ?? 0);
$bottom_weight = (float)($_POST['bottom_weight'] ?? 0);

$operator1     = !empty($_POST['operator1']) ? trim($_POST['operator1']) : $iduser_func;
$operator2     = trim($_POST['operator2'] ?? '');

$line_process  = '1'; // Default ใน f8.txt สำหรับ TPTPPROD1
$now_time      = date('Y-m-d H:i:s');

if (empty($product_no)) {
    echo json_encode(['status' => 'error', 'message' => 'ไม่พบข้อมูล Product No']);
    exit();
}

try {
    // 1. ดึงข้อมูลประกอบและตรวจสอบสถานะล่าสุดจาก CRSHPROD1
    $stmt_p = $conn->prepare("SELECT * FROM CRSHPROD1 WHERE PRODUCT_NO = :pno");
    $stmt_p->bindParam(':pno', $product_no);
    $stmt_p->execute();
    $prod_info = $stmt_p->fetch(PDO::FETCH_ASSOC);

    if (!$prod_info) {
        echo json_encode(['status' => 'error', 'message' => 'ไม่พบข้อมูล Product ในฐานข้อมูล']);
        exit();
    }

    // 2. Guard Check: ตรวจสอบสายการผลิตตั้งต้นตาม f8.txt (ต้องเป็น BK, AP, KP, XC, RC เท่านั้น)
    $work_proc = trim($prod_info['CRSH_WORKPROCESS'] ?? '');
    $work_prefix = strtoupper(substr($work_proc, 0, 2));
    if (!in_array($work_prefix, ['BK', 'AP', 'KP', 'XC', 'RC'])) {
        echo json_encode([
            'status'  => 'error', 
            'message' => 'ผลิตภัณฑ์ ไม่ได้ผลิตมาจากสายกระบวนการผลิต Blanking, XY5, A Press, K Press (Circle/Non Circular) จึงไม่สามารถทำกระบวนการย่อยนี้ได้'
        ]);
        exit();
    }

    // 3. Guard Check: ตรวจสอบสถานะ CRSH_STATUS (BCRSH-06) ตาม f8.txt
    $crsh_status = strtoupper(trim($prod_info['CRSH_STATUS'] ?? ''));
    if (!in_array($crsh_status, ['OP', 'AC'])) {
        echo json_encode([
            'status'  => 'error', 
            'message' => 'ผลิตภัณฑ์มีสถานะภาพเป็น : ' . $crsh_status . ' ไม่เท่ากับสถานะภาพ : OP: OPEN (BCRSH-06)'
        ]);
        exit();
    }

    $input_weight = (float)$prod_info['CRSH_ACTUALWEIGHT'];
    $input_piece  = (float)$prod_info['CRSH_ACTUALPIECE'];

    // กำหนดตรรกะคำนวณน้ำหนัก SHEET WEIGHT vs PRODUCT WEIGHT สำหรับ TPTPPROD1 ใน f8.txt
    $unit_weight = (float)$prod_info['WEIGHT_PIECE'];
    if (strpos($work_proc, 'SH>') !== false) {
        $pos_sh = strpos($work_proc, 'SH>');
        $pos_tw_curr = strpos($work_proc, 'TW>');
        if ($pos_tw_curr <= $pos_sh) {
            $unit_weight = (float)$prod_info['CUT_WEIGHTPIECE'];
        }
    }
    if ($unit_weight <= 0) { $unit_weight = 1; }

    // 4. ตรวจสอบเงื่อนไขค่าน้ำหนักตาม f8 (BCRSH-82, BCRSH-83, BCRSH-90)
    if ($accept_weight > $input_weight) {
        echo json_encode([
            'status'  => 'error', 
            'message' => "น้ำหนักงานดี ({$accept_weight} kg) ห้ามมากกว่าน้ำหนักนำเข้า ({$input_weight} kg) [BCRSH-82]"
        ]);
        exit();
    }
    if ($accept_weight <= 0) {
        echo json_encode([
            'status'  => 'error', 
            'message' => 'ข้อมูลน้ำหนักงานดีต้องมากกว่า 0 (BCRSH-83)'
        ]);
        exit();
    }
    if ($bottom_weight >= 200) {
        echo json_encode([
            'status'  => 'error', 
            'message' => 'น้ำหนักก้นพาเลท (Bottom Weight) ต้องน้อยกว่า 200 kg. (BCRSH-90)'
        ]);
        exit();
    }

    // 5. คำนวณจำนวนชิ้นและของเสียหลังบ้านตามตรรกะ f8 (VerifyValue)
    $reject_weight = $input_weight - $accept_weight;
    if ($reject_weight < 0) { $reject_weight = 0; }

    if ($accept_weight === $input_weight) {
        $accept_piece = $input_piece;
    } else {
        $accept_piece = round($accept_weight / $unit_weight, 0);
    }
    $reject_piece = $input_piece - $accept_piece;
    if ($reject_piece < 0) { $reject_piece = 0; }

    // 6. ตรวจสอบการข้ามขั้นตอนตามเงื่อนไข EnableField ของ f8.txt
    $pos_tw = strpos($work_proc, 'TW>');
    $calc_next_proc = "NN";
    if ($pos_tw !== false) {
        $calc_next_proc = substr($work_proc, $pos_tw + 3, 2);
    }

    $curr_next_proc = trim($prod_info['CRSH_NEXTPROCESS'] ?? '');
    if (!empty($curr_next_proc) && $curr_next_proc !== 'TW') {
        if ($curr_next_proc !== $calc_next_proc && $curr_next_proc !== 'IS') {
            echo json_encode([
                'status'  => 'error', 
                'message' => 'ชิ้นงานนี้ถูกส่งข้ามขั้นตอนไปแล้ว (' . $curr_next_proc . ') ไม่อนุญาตให้แก้ไขข้อมูล'
            ]);
            exit();
        }
    }

    $coil_no      = $prod_info['COIL_NO'] ?? '';
    $material_in  = $prod_info['MATERIAL_IN'] ?? '';
    $alloy        = $prod_info['ALLOY'] ?? '';
    $location_id  = 'P' . $line_process;

    $conn->beginTransaction();

    // 7. บันทึกข้อมูลลงตาราง TPTPPROD1 (AddProcess หรือ UpdateProcess)
    $stmt_chk_tptp = $conn->prepare("SELECT PRODUCT_NO FROM TPTPPROD1 WHERE PRODUCT_NO = :pno");
    $stmt_chk_tptp->bindParam(':pno', $product_no);
    $stmt_chk_tptp->execute();
    $is_existing = $stmt_chk_tptp->fetch(PDO::FETCH_ASSOC);

    if (!$is_existing) {
        // AddProcess - TPTPPROD1 ใน f8.txt
        $sql_ins = "INSERT INTO TPTPPROD1 (
                        PRODUCT_NO, LINE_PROCESS, PRODUCT_ID, TPTP_TIME, TPTP_STARTDATE, TPTP_STARTTIME,
                        TPTP_ENDDATE, TPTP_ENDTIME, TPTP_ACCEPTWEIGHT, TPTP_ACCEPTPIECE,
                        TPTP_REJECTWEIGHT, TPTP_REJECTPIECE, TPTP_OPERATOR1, TPTP_OPERATOR2, TPTP_OPERATEDATE
                    ) VALUES (
                        :pno, '1', :pid, 0, :sdate, :stime,
                        :edate, :etime, :ac_wt, :ac_pc,
                        :rj_wt, :rj_pc, :op1, :op2, :op_date
                    )";
        $stmt_ins = $conn->prepare($sql_ins);
        $stmt_ins->bindParam(':pno', $product_no);
        $stmt_ins->bindParam(':pid', $product_id);
        $stmt_ins->bindParam(':sdate', $start_date);
        $stmt_ins->bindParam(':stime', $start_time);
        $stmt_ins->bindParam(':edate', $end_date);
        $stmt_ins->bindParam(':etime', $end_time);
        $stmt_ins->bindParam(':ac_wt', $accept_weight);
        $stmt_ins->bindParam(':ac_pc', $accept_piece);
        $stmt_ins->bindParam(':rj_wt', $reject_weight);
        $stmt_ins->bindParam(':rj_pc', $reject_piece);
        $stmt_ins->bindParam(':op1', $operator1);
        $stmt_ins->bindParam(':op2', $operator2);
        $stmt_ins->bindParam(':op_date', $now_time);
        $stmt_ins->execute();
    } else {
        // UpdateProcess - TPTPPROD1 ใน f8.txt
        $sql_upd_tptp = "UPDATE TPTPPROD1 SET 
                            LINE_PROCESS = '1',
                            TPTP_STARTDATE = :sdate, TPTP_STARTTIME = :stime, 
                            TPTP_ENDDATE = :edate, TPTP_ENDTIME = :etime,
                            TPTP_ACCEPTWEIGHT = :ac_wt, TPTP_ACCEPTPIECE = :ac_pc,
                            TPTP_REJECTWEIGHT = :rj_wt, TPTP_REJECTPIECE = :rj_pc,
                            TPTP_UPDATE = :op1, TPTP_UPDATEDATE = :op_date
                         WHERE PRODUCT_NO = :pno";
        $stmt_upd_tptp = $conn->prepare($sql_upd_tptp);
        $stmt_upd_tptp->bindParam(':sdate', $start_date);
        $stmt_upd_tptp->bindParam(':stime', $start_time);
        $stmt_upd_tptp->bindParam(':edate', $end_date);
        $stmt_upd_tptp->bindParam(':etime', $end_time);
        $stmt_upd_tptp->bindParam(':ac_wt', $accept_weight);
        $stmt_upd_tptp->bindParam(':ac_pc', $accept_piece);
        $stmt_upd_tptp->bindParam(':rj_wt', $reject_weight);
        $stmt_upd_tptp->bindParam(':rj_pc', $reject_piece);
        $stmt_upd_tptp->bindParam(':op1', $operator1);
        $stmt_upd_tptp->bindParam(':op_date', $now_time);
        $stmt_upd_tptp->bindParam(':pno', $product_no);
        $stmt_upd_tptp->execute();
    }

    // 8. UPDATE CRSHPROD1 (อัปเดต CRSH_TRANSFERWEIGHT และ CRSH_TRANSFERPIECE ตาม f8.txt)
    $sql_upd_crsh = "UPDATE CRSHPROD1 SET 
                        CRSH_NEXTPROCESS = :next_proc,
                        CRSH_ACTUALWEIGHT = :ac_wt,
                        CRSH_ACTUALPIECE = :ac_pc,
                        CRSH_BOTTOMWEIGHT = :bot_wt,
                        CRSH_TRANSFERWEIGHT = :tw_wt,
                        CRSH_TRANSFERPIECE = :tw_pc
                     WHERE PRODUCT_NO = :pno";
    $stmt_upd_crsh = $conn->prepare($sql_upd_crsh);
    $stmt_upd_crsh->bindParam(':next_proc', $next_process);
    $stmt_upd_crsh->bindParam(':ac_wt', $accept_weight);
    $stmt_upd_crsh->bindParam(':ac_pc', $accept_piece);
    $stmt_upd_crsh->bindParam(':bot_wt', $bottom_weight);
    $stmt_upd_crsh->bindParam(':tw_wt', $accept_weight);
    $stmt_upd_crsh->bindParam(':tw_pc', $accept_piece);
    $stmt_upd_crsh->bindParam(':pno', $product_no);
    $stmt_upd_crsh->execute();

    // 9. สร้าง Scrap อัตโนมัติ (รหัสกระบวนการ TW01 เมื่อมีเศษเสีย)
    $scrap_no_created = '';
    if ($reject_weight > 0) {
        $scrp_prefix = "S" . date('ymd') . "-" . $line_process . "-";
        $sql_sk_run = "SELECT COUNT(*) as run_cnt FROM SCRPPROD1 WHERE PRODUCT_NO LIKE :prefix";
        $stmt_sk_run = $conn->prepare($sql_sk_run);
        $search_sk_prefix = $scrp_prefix . "%";
        $stmt_sk_run->bindParam(':prefix', $search_sk_prefix, PDO::PARAM_STR);
        $stmt_sk_run->execute();
        $row_sk_run = $stmt_sk_run->fetch(PDO::FETCH_ASSOC);
        $next_sk_no = ((int)($row_sk_run['run_cnt'] ?? 0)) + 1;
        $scrap_no_created = $scrp_prefix . sprintf("%02d", $next_sk_no);

        $sql_scrp = "INSERT INTO SCRPPROD1 (
                        PRODUCT_NO, SCRP_DATE, COIL_NO, MATERIAL_IN, LINE_PROCESS, PROCESS, SCRP_FROM,
                        LOCATION_ID, ALLOY, SCRP_WIDTH, SCRP_WEIGHT, SCRP_REMARK, SCRP_LABEL, SCRP_OPERATOR,
                        SCRP_OPERATEDATE, SCRP_STATUS
                    ) VALUES (
                        :pno, :scrp_date, :coil_no, :mat_in, :line, 'TW01', 'SK',
                        :loc, :alloy, 0, :scrp_wt, 'SCRAP FROM TRANSFER TO PALLET PROCESS', 0, :op,
                        :op_date, 'OP'
                    )";
        $stmt_scrp = $conn->prepare($sql_scrp);
        $stmt_scrp->bindParam(':pno', $scrap_no_created);
        $stmt_scrp->bindParam(':scrp_date', $end_date);
        $stmt_scrp->bindParam(':coil_no', $coil_no);
        $stmt_scrp->bindParam(':mat_in', $material_in);
        $stmt_scrp->bindParam(':line', $line_process);
        $stmt_scrp->bindParam(':loc', $location_id);
        $stmt_scrp->bindParam(':alloy', $alloy);
        $stmt_scrp->bindParam(':scrp_wt', $reject_weight);
        $stmt_scrp->bindParam(':op', $operator1);
        $stmt_scrp->bindParam(':op_date', $now_time);
        $stmt_scrp->execute();
    }

    // 10. ReCalJobOrder คำนวณยอดสะสมของ Job Order ตาม f8.txt
    if (!empty($job_order)) {
        $stmt_jb = $conn->prepare("SELECT WEIGHT_PIECE FROM JOBORDER1 WHERE JOB_ORDER = :jo");
        $stmt_jb->bindParam(':jo', $job_order);
        $stmt_jb->execute();
        $jb_row = $stmt_jb->fetch(PDO::FETCH_ASSOC);
        $dblWeightPiece = $jb_row ? (float)$jb_row['WEIGHT_PIECE'] : 0;

        $sqlSum = "SELECT 
            SUM(CRSH_ACTUALWEIGHT - CRSH_COMBINEWEIGHT) AS ACTUALWEIGHT, 
            SUM(CRSH_ACTUALPIECE - CRSH_COMBINEPIECE) AS ACTUALPIECE, 
            SUM(CRSH_PRODUCEWEIGHT) AS PRODUCEWEIGHT, 
            SUM(CRSH_PRODUCEPIECE) AS PRODUCEPIECE, 
            SUM(CRSH_TRANSFERWEIGHT) AS TRANSFERWEIGHT, 
            SUM(CRSH_TRANSFERPIECE) AS TRANSFERPIECE, 
            SUM(CRSH_TAKEOUTWEIGHT) AS TAKEOUTWEIGHT, 
            SUM(CRSH_TAKEOUTPIECE) AS TAKEOUTPIECE, 
            SUM(CRSH_PACKWEIGHT) AS PACKWEIGHT, 
            SUM(CRSH_PACKPIECE) AS PACKPIECE 
            FROM CRSHPROD1 WHERE JOB_ORDER = :jo";
        $stmtSum = $conn->prepare($sqlSum);
        $stmtSum->bindParam(':jo', $job_order);
        $stmtSum->execute();
        $sum = $stmtSum->fetch(PDO::FETCH_ASSOC);

        $sqlPack = "SELECT PACK_NETWEIGHT, PACK_NETPIECE FROM PACKPROD1 
                    WHERE JOB_ORDER = :jo AND PACK_STATUS IN ('SH', 'IN', 'ST', 'DS')";
        $stmtPack = $conn->prepare($sqlPack);
        $stmtPack->bindParam(':jo', $job_order);
        $stmtPack->execute();
        $stockWeight = 0;
        $stockPiece = 0;
        while ($pack = $stmtPack->fetch(PDO::FETCH_ASSOC)) {
            $netW = round((float)$pack['PACK_NETWEIGHT'], 0);
            $stockWeight += $netW;
            if ((float)$pack['PACK_NETPIECE'] <= 0) {
                if ($dblWeightPiece > 0) {
                    $stockPiece += round($netW / $dblWeightPiece, 0);
                }
            } else {
                $stockPiece += (float)$pack['PACK_NETPIECE'];
            }
        }

        $sqlUpdateJob = "UPDATE JOBORDER1 SET 
            JOB_ACTUALPIECE = :ac_pc, JOB_ACTUALWEIGHT = :ac_wt, 
            JOB_PRODUCEPIECE = :pr_pc, JOB_PRODUCEWEIGHT = :pr_wt, 
            JOB_TRANSFERPIECE = :tw_pc, JOB_TRANSFERWEIGHT = :tw_wt, 
            JOB_TAKEOUTPIECE = :to_pc, JOB_TAKEOUTWEIGHT = :to_wt, 
            JOB_PACKPIECE = :pk_pc, JOB_PACKWEIGHT = :pk_wt, 
            JOB_STOCKPIECE = :st_pc, JOB_STOCKWEIGHT = :st_wt 
            WHERE JOB_ORDER = :jo";
        $stmtUpdateJob = $conn->prepare($sqlUpdateJob);
        $ac_p = round((float)($sum['ACTUALPIECE'] ?? 0), 0);
        $ac_w = round((float)($sum['ACTUALWEIGHT'] ?? 0), 0);
        $pr_p = round((float)($sum['PRODUCEPIECE'] ?? 0), 0);
        $pr_w = round((float)($sum['PRODUCEWEIGHT'] ?? 0), 0);
        $tw_p = round((float)($sum['TRANSFERPIECE'] ?? 0), 0);
        $tw_w = round((float)($sum['TRANSFERWEIGHT'] ?? 0), 0);
        $to_p = round((float)($sum['TAKEOUTPIECE'] ?? 0), 0);
        $to_w = round((float)($sum['TAKEOUTWEIGHT'] ?? 0), 0);
        $pk_p = round((float)($sum['PACKPIECE'] ?? 0), 0);
        $pk_w = round((float)($sum['PACKWEIGHT'] ?? 0), 0);

        $stmtUpdateJob->bindParam(':ac_pc', $ac_p);
        $stmtUpdateJob->bindParam(':ac_wt', $ac_w);
        $stmtUpdateJob->bindParam(':pr_pc', $pr_p);
        $stmtUpdateJob->bindParam(':pr_wt', $pr_w);
        $stmtUpdateJob->bindParam(':tw_pc', $tw_p);
        $stmtUpdateJob->bindParam(':tw_wt', $tw_w);
        $stmtUpdateJob->bindParam(':to_pc', $to_p);
        $stmtUpdateJob->bindParam(':to_wt', $to_w);
        $stmtUpdateJob->bindParam(':pk_pc', $pk_p);
        $stmtUpdateJob->bindParam(':pk_wt', $pk_w);
        $stmtUpdateJob->bindParam(':st_pc', $stockPiece);
        $stmtUpdateJob->bindParam(':st_wt', $stockWeight);
        $stmtUpdateJob->bindParam(':jo', $job_order);
        $stmtUpdateJob->execute();
    }

    $conn->commit();

    echo json_encode([
        'status'       => 'success',
        'message'      => 'บันทึกข้อมูลการถ่ายลงพาเลทสำเร็จ',
        'pno'          => $product_no,
        'scrap_no'     => $scrap_no_created,
        'scrap_weight' => $reject_weight
    ]);
    exit();

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode([
        'status'  => 'error',
        'message' => 'บันทึกล้มเหลว: ' . $e->getMessage()
    ]);
    exit();
}
?>