<?php
// model/cutsheet_save_mats.php
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

$accept_weight = (float)($_POST['accept_weight'] ?? 0);
$accept_piece  = (float)($_POST['accept_piece'] ?? 0);
$reject_weight = (float)($_POST['reject_weight'] ?? 0);
$reject_piece  = (float)($_POST['reject_piece'] ?? 0);
$bottom_weight = (float)($_POST['bottom_weight'] ?? 0);

$operator1     = !empty($_POST['operator1']) ? trim($_POST['operator1']) : $iduser_func;
$operator2     = trim($_POST['operator2'] ?? '');

$line_process  = '1'; // ตาม Default ของ Cut Sheet ใน f9.txt (AddProcess)
$now_time      = date('Y-m-d H:i:s');

if (empty($product_no)) {
    echo json_encode(['status' => 'error', 'message' => 'ไม่พบข้อมูล Product No']);
    exit();
}

// คำนวณเวลาการผลิตในหน่วยนาที (Process Time)
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

    // 1. Guard Check: สถานะ CRSH_STATUS (BCRSH-06) ตาม f9.txt
    $crsh_status = strtoupper(trim($prod_info['CRSH_STATUS'] ?? ''));
    if (!in_array($crsh_status, ['OP', 'AC'])) {
        echo json_encode([
            'status'  => 'error', 
            'message' => 'ผลิตภัณฑ์มีสถานะภาพเป็น : ' . $crsh_status . ' ไม่เท่ากับสถานะภาพ : OP: OPEN (BCRSH-06)'
        ]);
        exit();
    }

    // 2. ตรวจสอบเงื่อนไขค่านำเข้าตาม f9.txt
    if ($accept_piece <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'จำนวนงานดีต้องมากกว่า 0 (BCRSH-83)']);
        exit();
    }
    if ($bottom_weight >= 200) {
        echo json_encode(['status' => 'error', 'message' => 'น้ำหนักก้นพาเลท (Bottom Weight) ต้องน้อยกว่า 200 kg. (BCRSH-90)']);
        exit();
    }

    $conn->beginTransaction();

    // 3. ตรวจสอบว่าเคยมี Record ใน CTSHPROD1 หรือไม่
    $stmt_chk_ctsh = $conn->prepare("SELECT PRODUCT_NO FROM CTSHPROD1 WHERE PRODUCT_NO = :pno");
    $stmt_chk_ctsh->bindParam(':pno', $product_no);
    $stmt_chk_ctsh->execute();
    $is_existing = $stmt_chk_ctsh->fetch(PDO::FETCH_ASSOC);

    if (!$is_existing) {
        // กรณี New Record: อ้างอิง AddProcess [CTSHPROD1] ใน f9.txt
        $sql_ins = "INSERT INTO CTSHPROD1 (
                        PRODUCT_NO, LINE_PROCESS, PRODUCT_ID, CTSH_TIME, 
                        CTSH_STARTDATE, CTSH_STARTTIME, CTSH_ENDDATE, CTSH_ENDTIME, 
                        CTSH_ACCEPTWEIGHT, CTSH_ACCEPTPIECE, CTSH_REJECTWEIGHT, CTSH_REJECTPIECE, 
                        CTSH_OPERATOR1, CTSH_OPERATOR2, CTSH_OPERATEDATE
                    ) VALUES (
                        :pno, :line, :pid, :ctime,
                        :sdate, :stime, :edate, :etime,
                        :ac_wt, :ac_pc, :rj_wt, :rj_pc,
                        :op1, :op2, :op_date
                    )";
        $stmt_ins = $conn->prepare($sql_ins);
        $stmt_ins->bindParam(':pno', $product_no);
        $stmt_ins->bindParam(':line', $line_process);
        $stmt_ins->bindParam(':pid', $product_id);
        $stmt_ins->bindParam(':ctime', $process_time, PDO::PARAM_INT);
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
        // กรณี Old Record: อ้างอิง UpdateProcess [CTSHPROD1] ใน f9.txt
        $sql_upd_ctsh = "UPDATE CTSHPROD1 SET 
                            LINE_PROCESS = :line,
                            CTSH_STARTDATE = :sdate, CTSH_STARTTIME = :stime, 
                            CTSH_ENDDATE = :edate, CTSH_ENDTIME = :etime,
                            CTSH_ACCEPTWEIGHT = :ac_wt, CTSH_ACCEPTPIECE = :ac_pc,
                            CTSH_REJECTWEIGHT = :rj_wt, CTSH_REJECTPIECE = :rj_pc,
                            CTSH_UPDATE = :op1, CTSH_UPDATEDATE = :op_date
                         WHERE PRODUCT_NO = :pno";
        $stmt_upd_ctsh = $conn->prepare($sql_upd_ctsh);
        $stmt_upd_ctsh->bindParam(':line', $line_process);
        $stmt_upd_ctsh->bindParam(':sdate', $start_date);
        $stmt_upd_ctsh->bindParam(':stime', $start_time);
        $stmt_upd_ctsh->bindParam(':edate', $end_date);
        $stmt_upd_ctsh->bindParam(':etime', $end_time);
        $stmt_upd_ctsh->bindParam(':ac_wt', $accept_weight);
        $stmt_upd_ctsh->bindParam(':ac_pc', $accept_piece);
        $stmt_upd_ctsh->bindParam(':rj_wt', $reject_weight);
        $stmt_upd_ctsh->bindParam(':rj_pc', $reject_piece);
        $stmt_upd_ctsh->bindParam(':op1', $operator1);
        $stmt_upd_ctsh->bindParam(':op_date', $now_time);
        $stmt_upd_ctsh->bindParam(':pno', $product_no);
        $stmt_upd_ctsh->execute();
    }

    // 4. อัปเดตตารางหลัก CRSHPROD1 ตาม f9.txt (ปรับ CRSH_CUTSHEETWEIGHT/PIECE)
    $sql_upd_crsh = "UPDATE CRSHPROD1 SET 
                        CRSH_NEXTPROCESS = :next_proc,
                        CRSH_ACTUALWEIGHT = :ac_wt,
                        CRSH_ACTUALPIECE = :ac_pc,
                        CRSH_BOTTOMWEIGHT = :bot_wt,
                        CRSH_CUTSHEETWEIGHT = :ct_wt,
                        CRSH_CUTSHEETPIECE = :ct_pc
                     WHERE PRODUCT_NO = :pno";
    $stmt_upd_crsh = $conn->prepare($sql_upd_crsh);
    $stmt_upd_crsh->bindParam(':next_proc', $next_process);
    $stmt_upd_crsh->bindParam(':ac_wt', $accept_weight);
    $stmt_upd_crsh->bindParam(':ac_pc', $accept_piece);
    $stmt_upd_crsh->bindParam(':bot_wt', $bottom_weight);
    $stmt_upd_crsh->bindParam(':ct_wt', $accept_weight);
    $stmt_upd_crsh->bindParam(':ct_pc', $accept_piece);
    $stmt_upd_crsh->bindParam(':pno', $product_no);
    $stmt_upd_crsh->execute();

    // 5. คำนวณยอดสะสมของ Job Order ใหม่ (ReCalJobOrder)
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
            SUM(CRSH_CUTSHEETWEIGHT) AS CUTSHEETWEIGHT, 
            SUM(CRSH_CUTSHEETPIECE) AS CUTSHEETPIECE,
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
            JOB_CUTSHEETPIECE = :ct_pc, JOB_CUTSHEETWEIGHT = :ct_wt, 
            JOB_TAKEOUTPIECE = :to_pc, JOB_TAKEOUTWEIGHT = :to_wt, 
            JOB_PACKPIECE = :pk_pc, JOB_PACKWEIGHT = :pk_wt, 
            JOB_STOCKPIECE = :st_pc, JOB_STOCKWEIGHT = :st_wt 
            WHERE JOB_ORDER = :jo";
        $stmtUpdateJob = $conn->prepare($sqlUpdateJob);
        $ac_p = round((float)($sum['ACTUALPIECE'] ?? 0), 0);
        $ac_w = round((float)($sum['ACTUALWEIGHT'] ?? 0), 0);
        $pr_p = round((float)($sum['PRODUCEPIECE'] ?? 0), 0);
        $pr_w = round((float)($sum['PRODUCEWEIGHT'] ?? 0), 0);
        $ct_p = round((float)($sum['CUTSHEETPIECE'] ?? 0), 0);
        $ct_w = round((float)($sum['CUTSHEETWEIGHT'] ?? 0), 0);
        $to_p = round((float)($sum['TAKEOUTPIECE'] ?? 0), 0);
        $to_w = round((float)($sum['TAKEOUTWEIGHT'] ?? 0), 0);
        $pk_p = round((float)($sum['PACKPIECE'] ?? 0), 0);
        $pk_w = round((float)($sum['PACKWEIGHT'] ?? 0), 0);

        $stmtUpdateJob->bindParam(':ac_pc', $ac_p);
        $stmtUpdateJob->bindParam(':ac_wt', $ac_w);
        $stmtUpdateJob->bindParam(':pr_pc', $pr_p);
        $stmtUpdateJob->bindParam(':pr_wt', $pr_w);
        $stmtUpdateJob->bindParam(':ct_pc', $ct_p);
        $stmtUpdateJob->bindParam(':ct_wt', $ct_w);
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
        'message' => 'บันทึกข้อมูล Cut Sheet และคำนวณสะสมสำเร็จ',
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