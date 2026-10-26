<?php
// model/annealing_save_mats.php
header('Content-Type: application/json; charset=utf-8');
session_start();

ini_set('display_errors', 0);
error_reporting(E_ALL);

include("../dbcon_mats-new.php");    // เชื่อมต่อระบบหลัก MATS-NEW
include("../dbcon_cm6_mats.php");    // เชื่อมต่อเตาอบ MMD6

$iduser_func = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : 'SYSTEM';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Request Method']);
    exit();
}

// รับค่าจาก Form
$product_no    = isset($_POST['product_no']) ? trim($_POST['product_no']) : '';
$job_order     = isset($_POST['job_order']) ? trim($_POST['job_order']) : '';
$line_process  = isset($_POST['line_process']) ? trim($_POST['line_process']) : '2';
$product_id    = isset($_POST['product_id']) ? trim($_POST['product_id']) : '';
$temper        = isset($_POST['temper']) ? trim($_POST['temper']) : '';
$next_process  = isset($_POST['next_process']) ? trim($_POST['next_process']) : 'NN';

$start_date    = $_POST['start_date'];
$start_time    = $_POST['start_time'] . ":00";
$end_date      = $_POST['end_date'];
$end_time      = $_POST['end_time'] . ":00";

$accept_weight = (float)($_POST['accept_weight'] ?? 0);
$accept_piece  = (float)($_POST['accept_piece'] ?? 0);
$reject_weight = (float)($_POST['reject_weight'] ?? 0);
$reject_piece  = (float)($_POST['reject_piece'] ?? 0);
$bottom_weight = (float)($_POST['bottom_weight'] ?? 0);
$actl_worktime = (int)($_POST['actl_worktime'] ?? 0);

$operator1     = !empty($_POST['operator1']) ? trim($_POST['operator1']) : $iduser_func;
$operator2     = trim($_POST['operator2'] ?? '');

$sp_t1 = (float)($_POST['sp_t1'] ?? 0);
$sp_t2 = (float)($_POST['sp_t2'] ?? 0);
$sp_t3 = (float)($_POST['sp_t3'] ?? 0);
$act_t1 = (float)($_POST['act_t1'] ?? 0);
$act_t2 = (float)($_POST['act_t2'] ?? 0);
$act_t3 = (float)($_POST['act_t3'] ?? 0);
$fan1 = (float)($_POST['fan1'] ?? 0);
$fan2 = (float)($_POST['fan2'] ?? 0);
$fan3 = (float)($_POST['fan3'] ?? 0);
$cooling_fan = (float)($_POST['cooling_fan'] ?? 0);
$sp_speed = (float)($_POST['sp_speed'] ?? 0);
$act_speed = (float)($_POST['act_speed'] ?? 0);

$now_time = date('Y-m-d H:i:s');

if (empty($product_no)) {
    echo json_encode(['status' => 'error', 'message' => 'ไม่พบข้อมูล Product No']);
    exit();
}

try {
    // 1. ตรวจสอบข้อมูลชิ้นงานจาก CRSHPROD1
    $stmt_p = $conn->prepare("SELECT * FROM CRSHPROD1 WHERE PRODUCT_NO = :pno");
    $stmt_p->bindParam(':pno', $product_no);
    $stmt_p->execute();
    $prod_info = $stmt_p->fetch(PDO::FETCH_ASSOC);

    if (!$prod_info) {
        echo json_encode(['status' => 'error', 'message' => 'ไม่พบข้อมูล Product ในฐานข้อมูล']);
        exit();
    }

    $coil_no     = $prod_info['COIL_NO'] ?? '';
    $material_in = $prod_info['MATERIAL_IN'] ?? '';
    $alloy       = $prod_info['ALLOY'] ?? '';
    $location_id = 'P' . $line_process; // พิกัด Location เช่น P2 หรือ P3

    // 2. เริ่ม Transaction
    $conn->beginTransaction();

    // 3. ตรวจสอบว่าเคยมีใน ANNLPROD1 หรือไม่ (New Record หรือ Update)
    $stmt_chk_annl = $conn->prepare("SELECT PRODUCT_NO FROM ANNLPROD1 WHERE PRODUCT_NO = :pno");
    $stmt_chk_annl->bindParam(':pno', $product_no);
    $stmt_chk_annl->execute();
    $is_existing = $stmt_chk_annl->fetch(PDO::FETCH_ASSOC);

    if (!$is_existing) {
        // === INSERT ANNLPROD1 (เลียนแบบ AddProcess ใน f8.txt) ===
        $sql_ins = "INSERT INTO ANNLPROD1 (
                        PRODUCT_NO, LINE_PROCESS, PRODUCT_ID, TEMPER, ANNL_TIME, ACTL_WORKTIME,
                        SP_TEMPERATURE1, SP_TEMPERATURE2, SP_TEMPERATURE3, ANNL_TEMPERATURE1,
                        ANNL_TEMPERATURE2, ANNL_TEMPERATURE3, RECYCLE_FAN1, RECYCLE_FAN2, RECYCLE_FAN3,
                        COOLING_FAN, SP_SPEEDBELT, ANNL_SPEEDBELT, ANNL_STARTDATE, ANNL_STARTTIME,
                        ANNL_ENDDATE, ANNL_ENDTIME, ANNL_ACCEPTWEIGHT, ANNL_ACCEPTPIECE,
                        ANNL_REJECTWEIGHT, ANNL_REJECTPIECE, ANNL_OPERATOR1, ANNL_OPERATOR2, ANNL_OPERATEDATE
                    ) VALUES (
                        :pno, :line, :pid, :temper, 0, :actl_time,
                        :sp1, :sp2, :sp3, :act1,
                        :act2, :act3, :fan1, :fan2, :fan3,
                        :cool, :sp_spd, :act_spd, :sdate, :stime,
                        :edate, :etime, :ac_wt, :ac_pc,
                        :rj_wt, :rj_pc, :op1, :op2, :op_date
                    )";
        $stmt_ins = $conn->prepare($sql_ins);
        $stmt_ins->bindParam(':pno', $product_no);
        $stmt_ins->bindParam(':line', $line_process);
        $stmt_ins->bindParam(':pid', $product_id);
        $stmt_ins->bindParam(':temper', $temper);
        $stmt_ins->bindParam(':actl_time', $actl_worktime);
        $stmt_ins->bindParam(':sp1', $sp_t1);
        $stmt_ins->bindParam(':sp2', $sp_t2);
        $stmt_ins->bindParam(':sp3', $sp_t3);
        $stmt_ins->bindParam(':act1', $act_t1);
        $stmt_ins->bindParam(':act2', $act_t2);
        $stmt_ins->bindParam(':act3', $act_t3);
        $stmt_ins->bindParam(':fan1', $fan1);
        $stmt_ins->bindParam(':fan2', $fan2);
        $stmt_ins->bindParam(':fan3', $fan3);
        $stmt_ins->bindParam(':cool', $cooling_fan);
        $stmt_ins->bindParam(':sp_spd', $sp_speed);
        $stmt_ins->bindParam(':act_spd', $act_speed);
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
        // === UPDATE ANNLPROD1 (เลียนแบบ UpdateProcess ใน f8.txt) ===
        $sql_upd_annl = "UPDATE ANNLPROD1 SET 
                            LINE_PROCESS = :line, ACTL_WORKTIME = :actl_time,
                            SP_TEMPERATURE1 = :sp1, SP_TEMPERATURE2 = :sp2, SP_TEMPERATURE3 = :sp3,
                            ANNL_TEMPERATURE1 = :act1, ANNL_TEMPERATURE2 = :act2, ANNL_TEMPERATURE3 = :act3,
                            RECYCLE_FAN1 = :fan1, RECYCLE_FAN2 = :fan2, RECYCLE_FAN3 = :fan3, COOLING_FAN = :cool,
                            SP_SPEEDBELT = :sp_spd, ANNL_SPEEDBELT = :act_spd,
                            ANNL_STARTDATE = :sdate, ANNL_STARTTIME = :stime, ANNL_ENDDATE = :edate, ANNL_ENDTIME = :etime,
                            ANNL_ACCEPTWEIGHT = :ac_wt, ANNL_ACCEPTPIECE = :ac_pc,
                            ANNL_REJECTWEIGHT = :rj_wt, ANNL_REJECTPIECE = :rj_pc,
                            ANNL_UPDATE = :op1, ANNL_UPDATEDATE = :op_date
                         WHERE PRODUCT_NO = :pno";
        $stmt_upd_annl = $conn->prepare($sql_upd_annl);
        $stmt_upd_annl->bindParam(':line', $line_process);
        $stmt_upd_annl->bindParam(':actl_time', $actl_worktime);
        $stmt_upd_annl->bindParam(':sp1', $sp_t1);
        $stmt_upd_annl->bindParam(':sp2', $sp_t2);
        $stmt_upd_annl->bindParam(':sp3', $sp_t3);
        $stmt_upd_annl->bindParam(':act1', $act_t1);
        $stmt_upd_annl->bindParam(':act2', $act_t2);
        $stmt_upd_annl->bindParam(':act3', $act_t3);
        $stmt_upd_annl->bindParam(':fan1', $fan1);
        $stmt_upd_annl->bindParam(':fan2', $fan2);
        $stmt_upd_annl->bindParam(':fan3', $fan3);
        $stmt_upd_annl->bindParam(':cool', $cooling_fan);
        $stmt_upd_annl->bindParam(':sp_spd', $sp_speed);
        $stmt_upd_annl->bindParam(':act_spd', $act_speed);
        $stmt_upd_annl->bindParam(':sdate', $start_date);
        $stmt_upd_annl->bindParam(':stime', $start_time);
        $stmt_upd_annl->bindParam(':edate', $end_date);
        $stmt_upd_annl->bindParam(':etime', $end_time);
        $stmt_upd_annl->bindParam(':ac_wt', $accept_weight);
        $stmt_upd_annl->bindParam(':ac_pc', $accept_piece);
        $stmt_upd_annl->bindParam(':rj_wt', $reject_weight);
        $stmt_upd_annl->bindParam(':rj_pc', $reject_piece);
        $stmt_upd_annl->bindParam(':op1', $operator1);
        $stmt_upd_annl->bindParam(':op_date', $now_time);
        $stmt_upd_annl->bindParam(':pno', $product_no);
        $stmt_upd_annl->execute();
    }

    // 4. UPDATE CRSHPROD1
    $sql_upd_crsh = "UPDATE CRSHPROD1 SET 
                        CRSH_NEXTPROCESS = :next_proc,
                        CRSH_ACTUALWEIGHT = :ac_wt,
                        CRSH_ACTUALPIECE = :ac_pc,
                        CRSH_BOTTOMWEIGHT = :bot_wt,
                        CRSH_ANNEALWEIGHT = :an_wt,
                        CRSH_ANNEALPIECE = :an_pc
                     WHERE PRODUCT_NO = :pno";
    $stmt_upd_crsh = $conn->prepare($sql_upd_crsh);
    $stmt_upd_crsh->bindParam(':next_proc', $next_process);
    $stmt_upd_crsh->bindParam(':ac_wt', $accept_weight);
    $stmt_upd_crsh->bindParam(':ac_pc', $accept_piece);
    $stmt_upd_crsh->bindParam(':bot_wt', $bottom_weight);
    $stmt_upd_crsh->bindParam(':an_wt', $accept_weight);
    $stmt_upd_crsh->bindParam(':an_pc', $accept_piece);
    $stmt_upd_crsh->bindParam(':pno', $product_no);
    $stmt_upd_crsh->execute();

    // 5. สร้าง Scrap อัตโนมัติ (กรณีมีของเสีย Reject Weight > 0)
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
                        :pno, :scrp_date, :coil_no, :mat_in, :line, 'AN01', 'SK',
                        :loc, :alloy, 0, :scrp_wt, 'SCRAP FROM ANNEALING PROCESS', 0, :op,
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

    // 6. UPDATE ตารางเตาอบ MMD6 (FAF2MCHN1) ผูก Product No ตามช่วงเวลาผลิต
    $db_mch = null;
    if (isset($conn6) && $conn6 instanceof PDO) { $db_mch = $conn6; }
    elseif (isset($conn_cm6) && $conn_cm6 instanceof PDO) { $db_mch = $conn_cm6; }

    if ($db_mch) {
        $start_dt = "{$start_date} {$start_time}";
        $end_dt   = "{$end_date} {$end_time}";
        $sql_mch = "UPDATE FAF2MCHN1 SET PRODUCT_NO = :pno 
                    WHERE [DATETIME] >= CONVERT(datetime, :sdt, 120) 
                      AND [DATETIME] <= CONVERT(datetime, :edt, 120)";
        $stmt_mch = $db_mch->prepare($sql_mch);
        $stmt_mch->bindParam(':pno', $product_no);
        $stmt_mch->bindParam(':sdt', $start_dt);
        $stmt_mch->bindParam(':edt', $end_dt);
        $stmt_mch->execute();
    }

    // 7. คำนวณ ReCalJobOrder ตาม logic ของ function.txt
    if (!empty($job_order)) {
        // ดึง WEIGHT_PIECE ของ Job
        $stmt_jb = $conn->prepare("SELECT WEIGHT_PIECE FROM JOBORDER1 WHERE JOB_ORDER = :jo");
        $stmt_jb->bindParam(':jo', $job_order);
        $stmt_jb->execute();
        $jb_row = $stmt_jb->fetch(PDO::FETCH_ASSOC);
        $dblWeightPiece = $jb_row ? (float)$jb_row['WEIGHT_PIECE'] : 0;

        // คำนวณ SUM จาก CRSHPROD1
        $sqlSum = "SELECT 
            SUM(CRSH_ACTUALWEIGHT - CRSH_COMBINEWEIGHT) AS ACTUALWEIGHT, 
            SUM(CRSH_ACTUALPIECE - CRSH_COMBINEPIECE) AS ACTUALPIECE, 
            SUM(CRSH_PRODUCEWEIGHT) AS PRODUCEWEIGHT, 
            SUM(CRSH_PRODUCEPIECE) AS PRODUCEPIECE, 
            SUM(CRSH_ANNEALWEIGHT) AS ANNEALWEIGHT, 
            SUM(CRSH_ANNEALPIECE) AS ANNEALPIECE,
            SUM(CRSH_TAKEOUTWEIGHT) AS TAKEOUTWEIGHT, 
            SUM(CRSH_TAKEOUTPIECE) AS TAKEOUTPIECE, 
            SUM(CRSH_PACKWEIGHT) AS PACKWEIGHT, 
            SUM(CRSH_PACKPIECE) AS PACKPIECE 
            FROM CRSHPROD1 WHERE JOB_ORDER = :jo";
        $stmtSum = $conn->prepare($sqlSum);
        $stmtSum->bindParam(':jo', $job_order);
        $stmtSum->execute();
        $sum = $stmtSum->fetch(PDO::FETCH_ASSOC);

        // คำนวณสต็อกบรรจุภัณฑ์ PACKPROD1
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

        // UPDATE JOBORDER1
        $sqlUpdateJob = "UPDATE JOBORDER1 SET 
            JOB_ACTUALPIECE = :ac_pc, JOB_ACTUALWEIGHT = :ac_wt, 
            JOB_PRODUCEPIECE = :pr_pc, JOB_PRODUCEWEIGHT = :pr_wt, 
            JOB_ANNEALPIECE = :an_pc, JOB_ANNEALWEIGHT = :an_wt, 
            JOB_TAKEOUTPIECE = :to_pc, JOB_TAKEOUTWEIGHT = :to_wt, 
            JOB_PACKPIECE = :pk_pc, JOB_PACKWEIGHT = :pk_wt, 
            JOB_STOCKPIECE = :st_pc, JOB_STOCKWEIGHT = :st_wt 
            WHERE JOB_ORDER = :jo";
        $stmtUpdateJob = $conn->prepare($sqlUpdateJob);
        $ac_p = round((float)($sum['ACTUALPIECE'] ?? 0), 0);
        $ac_w = round((float)($sum['ACTUALWEIGHT'] ?? 0), 0);
        $pr_p = round((float)($sum['PRODUCEPIECE'] ?? 0), 0);
        $pr_w = round((float)($sum['PRODUCEWEIGHT'] ?? 0), 0);
        $an_p = round((float)($sum['ANNEALPIECE'] ?? 0), 0);
        $an_w = round((float)($sum['ANNEALWEIGHT'] ?? 0), 0);
        $to_p = round((float)($sum['TAKEOUTPIECE'] ?? 0), 0);
        $to_w = round((float)($sum['TAKEOUTWEIGHT'] ?? 0), 0);
        $pk_p = round((float)($sum['PACKPIECE'] ?? 0), 0);
        $pk_w = round((float)($sum['PACKWEIGHT'] ?? 0), 0);

        $stmtUpdateJob->bindParam(':ac_pc', $ac_p);
        $stmtUpdateJob->bindParam(':ac_wt', $ac_w);
        $stmtUpdateJob->bindParam(':pr_pc', $pr_p);
        $stmtUpdateJob->bindParam(':pr_wt', $pr_w);
        $stmtUpdateJob->bindParam(':an_pc', $an_p);
        $stmtUpdateJob->bindParam(':an_wt', $an_w);
        $stmtUpdateJob->bindParam(':to_pc', $to_p);
        $stmtUpdateJob->bindParam(':to_wt', $to_w);
        $stmtUpdateJob->bindParam(':pk_pc', $pk_p);
        $stmtUpdateJob->bindParam(':pk_wt', $pk_w);
        $stmtUpdateJob->bindParam(':st_pc', $stockPiece);
        $stmtUpdateJob->bindParam(':st_wt', $stockWeight);
        $stmtUpdateJob->bindParam(':jo', $job_order);
        $stmtUpdateJob->execute();
    }

    // ยืนยัน Transaction ทั้งหมด
    $conn->commit();

    echo json_encode([
        'status'       => 'success',
        'message'      => 'บันทึกข้อมูลและคำนวณสะสมสำเร็จ',
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