<?php
// model/shearing_delete_mats.php
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

$product_no = isset($_POST['product_no']) ? trim($_POST['product_no']) : '';
$job_order  = isset($_POST['job_order']) ? trim($_POST['job_order']) : '';

if (empty($product_no)) {
    echo json_encode(['status' => 'error', 'message' => 'ไม่พบรหัส Product No']);
    exit();
}

try {
    $stmt_p = $conn->prepare("SELECT * FROM CRSHPROD1 WHERE PRODUCT_NO = :pno");
    $stmt_p->bindParam(':pno', $product_no);
    $stmt_p->execute();
    $prod_info = $stmt_p->fetch(PDO::FETCH_ASSOC);

    if (!$prod_info) {
        echo json_encode(['status' => 'error', 'message' => 'ไม่พบข้อมูล Product ในฐานข้อมูล']);
        exit();
    }

    // Guard Check 1: ตรวจสถานะห้ามลบตาม f8.txt (ST, DS, IN, SH)
    $crsh_status = strtoupper(trim($prod_info['CRSH_STATUS'] ?? ''));
    if (in_array($crsh_status, ['ST', 'DS', 'IN', 'SH'])) {
        echo json_encode([
            'status'  => 'error', 
            'message' => 'ผลิตภัณฑ์มีสถานะภาพเป็น : ' . $crsh_status . ' ไม่สามารถยกเลิก/ลบรายการได้'
        ]);
        exit();
    }

    // Guard Check 2: ตรวจการข้ามขั้นตอน (หากขั้นตอนถัดไปเริ่มทำงานแล้ว ห้ามลบ)
    $work_proc = $prod_info['CRSH_WORKPROCESS'] ?? '';
    $pos_sh = strpos($work_proc, 'SH>');
    $calc_next_proc = "NN";
    if ($pos_sh !== false) {
        $calc_next_proc = substr($work_proc, $pos_sh + 3, 2);
    }
    $curr_next_proc = trim($prod_info['CRSH_NEXTPROCESS'] ?? '');
    if (!empty($curr_next_proc) && $curr_next_proc !== 'SH') {
        if ($curr_next_proc !== $calc_next_proc && $curr_next_proc !== 'IS') {
            echo json_encode([
                'status'  => 'error', 
                'message' => 'ชิ้นงานนี้ถูกกระบวนการถัดไป (' . $curr_next_proc . ') ใช้งานแล้ว ไม่สามารถยกเลิกได้'
            ]);
            exit();
        }
    }

    // Guard Check 3: ห้ามติด Sort, Combine หรือ Punch Hole
    $stmt_sr = $conn->prepare("SELECT TOP 1 PRODUCT_NO FROM SORTPROD1 WHERE PRODUCT_NO = :pno");
    $stmt_sr->bindParam(':pno', $product_no);
    $stmt_sr->execute();
    if ($stmt_sr->fetch()) {
        echo json_encode(['status' => 'error', 'message' => 'ไม่สามารถยกเลิกได้ เนื่องจากติดกระบวนการ Sort']);
        exit();
    }

    $stmt_cb = $conn->prepare("SELECT TOP 1 PRODUCT_NO FROM CMBNPROD1 WHERE PRODUCT_NO = :pno");
    $stmt_cb->bindParam(':pno', $product_no);
    $stmt_cb->execute();
    if ($stmt_cb->fetch()) {
        echo json_encode(['status' => 'error', 'message' => 'ไม่สามารถยกเลิกได้ เนื่องจากติดกระบวนการ Combine']);
        exit();
    }

    $stmt_ph = $conn->prepare("SELECT TOP 1 PRODUCT_NO FROM PCHLPROD1 WHERE PRODUCT_NO = :pno");
    $stmt_ph->bindParam(':pno', $product_no);
    $stmt_ph->execute();
    if ($stmt_ph->fetch()) {
        echo json_encode(['status' => 'error', 'message' => 'ไม่สามารถยกเลิกได้ เนื่องจากมีการทำกระบวนการ Punch Hole แล้ว']);
        exit();
    }

    $conn->beginTransaction();

    // คืนค่ายอดเดิมจาก SHRSPROD1
    $stmt_shrs = $conn->prepare("SELECT SHRS_ACCEPTWEIGHT, SHRS_ACCEPTPIECE, SHRS_REJECTWEIGHT, SHRS_REJECTPIECE FROM SHRSPROD1 WHERE PRODUCT_NO = :pno");
    $stmt_shrs->bindParam(':pno', $product_no);
    $stmt_shrs->execute();
    $old_shrs = $stmt_shrs->fetch(PDO::FETCH_ASSOC);

    $old_accept_wt = (float)($old_shrs['SHRS_ACCEPTWEIGHT'] ?? 0);
    $old_reject_wt = (float)($old_shrs['SHRS_REJECTWEIGHT'] ?? 0);
    $rollback_actual_wt = $old_accept_wt + $old_reject_wt;

    $old_accept_pc = (float)($old_shrs['SHRS_ACCEPTPIECE'] ?? 0);
    $old_reject_pc = (float)($old_shrs['SHRS_REJECTPIECE'] ?? 0);
    $rollback_actual_pc = $old_accept_pc + $old_reject_pc;

    if ($rollback_actual_wt <= 0) {
        $rollback_actual_wt = (float)$prod_info['CRSH_ACTUALWEIGHT'];
        $rollback_actual_pc = (float)$prod_info['CRSH_ACTUALPIECE'];
    }

    // ลบข้อมูลออกจาก SHRSPROD1
    $stmt_del_shrs = $conn->prepare("DELETE FROM SHRSPROD1 WHERE PRODUCT_NO = :pno");
    $stmt_del_shrs->bindParam(':pno', $product_no);
    $stmt_del_shrs->execute();

    // คืนค่า CRSHPROD1: ดึง Next Process กลับมาเป็น 'SH' และเซ็ตยอด Shear เป็น 0
    $sql_upd_crsh = "UPDATE CRSHPROD1 SET 
                        CRSH_NEXTPROCESS = 'SH',
                        CRSH_ACTUALWEIGHT = :act_wt,
                        CRSH_ACTUALPIECE  = :act_pc,
                        CRSH_SHEARWEIGHT  = 0,
                        CRSH_SHEARPIECE   = 0
                     WHERE PRODUCT_NO = :pno";
    $stmt_upd_crsh = $conn->prepare($sql_upd_crsh);
    $stmt_upd_crsh->bindParam(':act_wt', $rollback_actual_wt);
    $stmt_upd_crsh->bindParam(':act_pc', $rollback_actual_pc);
    $stmt_upd_crsh->bindParam(':pno', $product_no);
    $stmt_upd_crsh->execute();

    // ลบ Scrap ที่สร้างขึ้นจากขั้นตอน SH01
    $coil_no = $prod_info['COIL_NO'] ?? '';
    if (!empty($coil_no)) {
        $stmt_del_scrp = $conn->prepare("DELETE FROM SCRPPROD1 WHERE COIL_NO = :coil AND PROCESS = 'SH01' AND SCRP_OPERATOR = :op");
        $stmt_del_scrp->bindParam(':coil', $coil_no);
        $stmt_del_scrp->bindParam(':op', $iduser_func);
        $stmt_del_scrp->execute();
    }

    // ReCalJobOrder คำนวณยอดสะสมของ Job Order ใหม่
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
            SUM(CRSH_SHEARWEIGHT) AS SHEARWEIGHT, 
            SUM(CRSH_SHEARPIECE) AS SHEARPIECE,
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
            JOB_SHEARPIECE = :sh_pc, JOB_SHEARWEIGHT = :sh_wt, 
            JOB_TAKEOUTPIECE = :to_pc, JOB_TAKEOUTWEIGHT = :to_wt, 
            JOB_PACKPIECE = :pk_pc, JOB_PACKWEIGHT = :pk_wt, 
            JOB_STOCKPIECE = :st_pc, JOB_STOCKWEIGHT = :st_wt 
            WHERE JOB_ORDER = :jo";
        $stmtUpdateJob = $conn->prepare($sqlUpdateJob);
        $ac_p = round((float)($sum['ACTUALPIECE'] ?? 0), 0);
        $ac_w = round((float)($sum['ACTUALWEIGHT'] ?? 0), 0);
        $pr_p = round((float)($sum['PRODUCEPIECE'] ?? 0), 0);
        $pr_w = round((float)($sum['PRODUCEWEIGHT'] ?? 0), 0);
        $sh_p = round((float)($sum['SHEARPIECE'] ?? 0), 0);
        $sh_w = round((float)($sum['SHEARWEIGHT'] ?? 0), 0);
        $to_p = round((float)($sum['TAKEOUTPIECE'] ?? 0), 0);
        $to_w = round((float)($sum['TAKEOUTWEIGHT'] ?? 0), 0);
        $pk_p = round((float)($sum['PACKPIECE'] ?? 0), 0);
        $pk_w = round((float)($sum['PACKWEIGHT'] ?? 0), 0);

        $stmtUpdateJob->bindParam(':ac_pc', $ac_p);
        $stmtUpdateJob->bindParam(':ac_wt', $ac_w);
        $stmtUpdateJob->bindParam(':pr_pc', $pr_p);
        $stmtUpdateJob->bindParam(':pr_wt', $pr_w);
        $stmtUpdateJob->bindParam(':sh_pc', $sh_p);
        $stmtUpdateJob->bindParam(':sh_wt', $sh_w);
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
        'message' => 'ยกเลิกผลการตัดสำเร็จ ชิ้นงานถูกถอยกลับไปรอในคิวตัด (Pending) เรียบร้อยแล้ว'
    ]);
    exit();

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode([
        'status'  => 'error',
        'message' => 'ยกเลิกล้มเหลว: ' . $e->getMessage()
    ]);
    exit();
}
?>