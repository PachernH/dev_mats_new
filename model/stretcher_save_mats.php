<?php
// model/stretcher_save_mats.php
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
$line_process  = isset($_POST['line_process']) ? trim($_POST['line_process']) : '1';
$next_process  = isset($_POST['next_process']) ? trim($_POST['next_process']) : 'NN';

$start_date    = $_POST['start_date'] ?? date('Y-m-d');
$start_time    = (!empty($_POST['start_time']) ? $_POST['start_time'] : date('H:i')) . ":00";
$end_date      = $_POST['end_date'] ?? date('Y-m-d');
$end_time      = (!empty($_POST['end_time']) ? $_POST['end_time'] : date('H:i')) . ":00";

$flatness_h    = (float)($_POST['flatness_h'] ?? 0);
$flatness_l    = (float)($_POST['flatness_l'] ?? 0);
$i_unit        = (float)($_POST['i_unit'] ?? 0);

$accept_weight = (float)($_POST['accept_weight'] ?? 0);
$accept_piece  = (float)($_POST['accept_piece'] ?? 0);
$reject_weight = (float)($_POST['reject_weight'] ?? 0);
$reject_piece  = (float)($_POST['reject_piece'] ?? 0);
$bottom_weight = (float)($_POST['bottom_weight'] ?? 0);

$operator1     = !empty($_POST['operator1']) ? trim($_POST['operator1']) : $iduser_func;
$operator2     = trim($_POST['operator2'] ?? '');

$now_time      = date('Y-m-d H:i:s');

if (empty($product_no)) {
    echo json_encode(['status' => 'error', 'message' => 'ไม่พบข้อมูล Product No']);
    exit();
}

$start_ts = strtotime($start_date . ' ' . $start_time);
$end_ts   = strtotime($end_date . ' ' . $end_time);
$process_time = ($end_ts > $start_ts) ? round(($end_ts - $start_ts) / 60) : 0;

try {
    $stmt_p = $conn->prepare("SELECT * FROM CRSHPROD1 WHERE PRODUCT_NO = :pno");
    $stmt_p->bindParam(':pno', $product_no);
    $stmt_p->execute();
    $prod_info = $stmt_p->fetch(PDO::FETCH_ASSOC);

    if (!$prod_info) {
        echo json_encode(['status' => 'error', 'message' => 'ไม่พบข้อมูล Product ในฐานข้อมูล']);
        exit();
    }

    $crsh_status = strtoupper(trim($prod_info['CRSH_STATUS'] ?? ''));
    if (!in_array($crsh_status, ['OP', 'AC'])) {
        echo json_encode([
            'status'  => 'error', 
            'message' => 'ผลิตภัณฑ์มีสถานะภาพเป็น : ' . $crsh_status . ' ไม่เท่ากับสถานะภาพ : OP: OPEN (BCRSH-06)'
        ]);
        exit();
    }

    if ($accept_piece <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'จำนวนงานดีต้องมากกว่า 0 (BCRSH-83)']);
        exit();
    }
    if ($bottom_weight >= 200) {
        echo json_encode(['status' => 'error', 'message' => 'น้ำหนักก้นพาเลทต้องน้อยกว่า 200 kg. (BCRSH-90)']);
        exit();
    }

    $conn->beginTransaction();

    $stmt_chk_stch = $conn->prepare("SELECT PRODUCT_NO FROM STCHPROD1 WHERE PRODUCT_NO = :pno");
    $stmt_chk_stch->bindParam(':pno', $product_no);
    $stmt_chk_stch->execute();
    $is_existing = $stmt_chk_stch->fetch(PDO::FETCH_ASSOC);

    if (!$is_existing) {
        // อ้างอิงคำสั่ง INSERT ใน AddProcess [STCHPROD1] ของ f9.txt
        $sql_ins = "INSERT INTO STCHPROD1 (
                        PRODUCT_NO, LINE_PROCESS, STCH_TIME, FLATNESS_HIGH, FLATNESS_LENGTH,
                        I_UNIT, STCH_STARTDATE, STCH_STARTTIME, STCH_ENDDATE, STCH_ENDTIME,
                        STCH_ACCEPTWEIGHT, STCH_ACCEPTPIECE, STCH_REJECTWEIGHT, STCH_REJECTPIECE,
                        STCH_OPERATOR1, STCH_OPERATOR2, STCH_OPERATEDATE
                    ) VALUES (
                        :pno, :line, :stime_val, :fh, :fl,
                        :iunit, :sdate, :stime, :edate, :etime,
                        :ac_wt, :ac_pc, :rj_wt, :rj_pc,
                        :op1, :op2, :op_date
                    )";
        $stmt_ins = $conn->prepare($sql_ins);
        $stmt_ins->bindParam(':pno', $product_no);
        $stmt_ins->bindParam(':line', $line_process);
        $stmt_ins->bindParam(':stime_val', $process_time, PDO::PARAM_INT);
        $stmt_ins->bindParam(':fh', $flatness_h);
        $stmt_ins->bindParam(':fl', $flatness_l);
        $stmt_ins->bindParam(':iunit', $i_unit);
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
        // อ้างอิงคำสั่ง UPDATE ใน UpdateProcess [STCHPROD1] ของ f9.txt
        $sql_upd_stch = "UPDATE STCHPROD1 SET 
                            LINE_PROCESS = :line, 
                            FLATNESS_HIGH = :fh, 
                            FLATNESS_LENGTH = :fl, 
                            I_UNIT = :iunit, 
                            STCH_STARTDATE = :sdate, STCH_STARTTIME = :stime, 
                            STCH_ENDDATE = :edate, STCH_ENDTIME = :etime, 
                            STCH_ACCEPTWEIGHT = :ac_wt, STCH_ACCEPTPIECE = :ac_pc, 
                            STCH_REJECTWEIGHT = :rj_wt, STCH_REJECTPIECE = :rj_pc, 
                            STCH_UPDATE = :op1, STCH_UPDATEDATE = :op_date
                         WHERE PRODUCT_NO = :pno";
        $stmt_upd_stch = $conn->prepare($sql_upd_stch);
        $stmt_upd_stch->bindParam(':line', $line_process);
        $stmt_upd_stch->bindParam(':fh', $flatness_h);
        $stmt_upd_stch->bindParam(':fl', $flatness_l);
        $stmt_upd_stch->bindParam(':iunit', $i_unit);
        $stmt_upd_stch->bindParam(':sdate', $start_date);
        $stmt_upd_stch->bindParam(':stime', $start_time);
        $stmt_upd_stch->bindParam(':edate', $end_date);
        $stmt_upd_stch->bindParam(':etime', $end_time);
        $stmt_upd_stch->bindParam(':ac_wt', $accept_weight);
        $stmt_upd_stch->bindParam(':ac_pc', $accept_piece);
        $stmt_upd_stch->bindParam(':rj_wt', $reject_weight);
        $stmt_upd_stch->bindParam(':rj_pc', $reject_piece);
        $stmt_upd_stch->bindParam(':op1', $operator1);
        $stmt_upd_stch->bindParam(':op_date', $now_time);
        $stmt_upd_stch->bindParam(':pno', $product_no);
        $stmt_upd_stch->execute();
    }

    // อัปเดตตารางหลัก CRSHPROD1 (บันทึก CRSH_STRETCHERWEIGHT และ CRSH_STRETCHERPIECE)
    $sql_upd_crsh = "UPDATE CRSHPROD1 SET 
                        CRSH_NEXTPROCESS = :next_proc,
                        CRSH_ACTUALWEIGHT = :ac_wt,
                        CRSH_ACTUALPIECE = :ac_pc,
                        CRSH_BOTTOMWEIGHT = :bot_wt,
                        CRSH_STRETCHERWEIGHT = :sc_wt,
                        CRSH_STRETCHERPIECE = :sc_pc
                     WHERE PRODUCT_NO = :pno";
    $stmt_upd_crsh = $conn->prepare($sql_upd_crsh);
    $stmt_upd_crsh->bindParam(':next_proc', $next_process);
    $stmt_upd_crsh->bindParam(':ac_wt', $accept_weight);
    $stmt_upd_crsh->bindParam(':ac_pc', $accept_piece);
    $stmt_upd_crsh->bindParam(':bot_wt', $bottom_weight);
    $stmt_upd_crsh->bindParam(':sc_wt', $accept_weight);
    $stmt_upd_crsh->bindParam(':sc_pc', $accept_piece);
    $stmt_upd_crsh->bindParam(':pno', $product_no);
    $stmt_upd_crsh->execute();

    // คำนวณ ReCalJobOrder สะสมเข้าตาราง JOBORDER1
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
            SUM(CRSH_STRETCHERWEIGHT) AS STRETCHERWEIGHT, 
            SUM(CRSH_STRETCHERPIECE) AS STRETCHERPIECE, 
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
            JOB_STRETCHERPIECE = :sc_pc, JOB_STRETCHERWEIGHT = :sc_wt, 
            JOB_TAKEOUTPIECE = :to_pc, JOB_TAKEOUTWEIGHT = :to_wt, 
            JOB_PACKPIECE = :pk_pc, JOB_PACKWEIGHT = :pk_wt, 
            JOB_STOCKPIECE = :st_pc, JOB_STOCKWEIGHT = :st_wt 
            WHERE JOB_ORDER = :jo";
        $stmtUpdateJob = $conn->prepare($sqlUpdateJob);
        $ac_p = round((float)($sum['ACTUALPIECE'] ?? 0), 0);
        $ac_w = round((float)($sum['ACTUALWEIGHT'] ?? 0), 0);
        $pr_p = round((float)($sum['PRODUCEPIECE'] ?? 0), 0);
        $pr_w = round((float)($sum['PRODUCEWEIGHT'] ?? 0), 0);
        $sc_p = round((float)($sum['STRETCHERPIECE'] ?? 0), 0);
        $sc_w = round((float)($sum['STRETCHERWEIGHT'] ?? 0), 0);
        $to_p = round((float)($sum['TAKEOUTPIECE'] ?? 0), 0);
        $to_w = round((float)($sum['TAKEOUTWEIGHT'] ?? 0), 0);
        $pk_p = round((float)($sum['PACKPIECE'] ?? 0), 0);
        $pk_w = round((float)($sum['PACKWEIGHT'] ?? 0), 0);

        $stmtUpdateJob->bindParam(':ac_pc', $ac_p);
        $stmtUpdateJob->bindParam(':ac_wt', $ac_w);
        $stmtUpdateJob->bindParam(':pr_pc', $pr_p);
        $stmtUpdateJob->bindParam(':pr_wt', $pr_w);
        $stmtUpdateJob->bindParam(':sc_pc', $sc_p);
        $stmtUpdateJob->bindParam(':sc_wt', $sc_w);
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
        'status'  => 'success',
        'message' => 'บันทึกข้อมูล Stretcher และคำนวณสะสมสำเร็จ',
        'pno'     => $product_no
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