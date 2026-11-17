<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['ID'])) {
    echo json_encode(['status' => 'error', 'message' => 'Session expired. Please login again.']);
    exit();
}

include("../dbcon_mats-new.php");

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!$data) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON payload']);
    exit();
}

$userOperate   = $_SESSION['ID'];
$recordStatus  = $data['record_status'] ?? 'New Record';
$productNo     = trim($data['product_no'] ?? '');
$jobOrder      = trim($data['job_order'] ?? '');
$saleOrderNo   = trim($data['saleorder_no'] ?? '');
$saleOrderItem = trim($data['saleorder_item'] ?? '');
$poNo          = trim($data['po_no'] ?? '');
$poItem        = trim($data['po_item'] ?? '');
$materialIn    = trim($data['material_in'] ?? '');
$alloy         = trim($data['alloy'] ?? '');
$workProcess   = trim($data['work_process'] ?? '');
$nextProcess   = trim($data['next_process'] ?? 'IS');
$productId     = trim($data['product_id'] ?? 'RM');

$reqNo         = trim($data['requisition_no'] ?? '');
$mp2PartNo     = trim($data['mp2_partno'] ?? '');
$serialNo      = trim($data['serial_no'] ?? '');

$produceWeight = (float)($data['produce_weight'] ?? 0);
$bottomWeight  = (float)($data['bottom_weight'] ?? 0);
$oldProduceWt  = (float)($data['old_produce_wt'] ?? 0);
$oldLabelCount = (int)($data['old_label_count'] ?? 0);
$chkPrintLabel = (int)($data['chk_print_label'] ?? 0);
$labelSize     = trim($data['label_size'] ?? '21x15');

$crshStatus    = ($produceWeight > 0) ? 'ST' : 'AC';
$crshLabel     = ($chkPrintLabel === 1) ? ($oldLabelCount + 1) : $oldLabelCount;

try {
    $conn->beginTransaction();

    // ตรวจสอบเงื่อนไขบังคับ: ต้องมี Product No. เสมอ
    if (empty($productNo) && $recordStatus === 'Old Record') {
        throw new Exception("BCRSH-06: กรุณาระบุหรือเลือก Raw Material Product No. ก่อนทำการบันทึก");
    }

    // ตรวจสอบสถานะ Product กรณีแก้ไข (Old Record) ตามเงื่อนไข GetProduct 
    if ($recordStatus === 'Old Record') {
        $stmtCheckProd = $conn->prepare("SELECT CRSH_STATUS FROM CRSHPROD1 WHERE PRODUCT_NO = :pno");
        $stmtCheckProd->execute([':pno' => $productNo]);
        $prodRow = $stmtCheckProd->fetch(PDO::FETCH_ASSOC);

        if (!$prodRow || $prodRow['CRSH_STATUS'] !== 'AC') {
            $currentStatus = $prodRow['CRSH_STATUS'] ?? 'UNKNOWN';
            throw new Exception("BCRSH-06: ผลิตภัณฑ์มีสถานะภาพเป็น : " . $currentStatus . " ไม่เท่ากับสถานะภาพ : AC: ACCEPT ไม่สามารถบันทึกได้");
        }
    }

    // ตรวจสอบสถานะ Job Order ล่าสุดก่อนบันทึก
    $stmtCheckJob = $conn->prepare("SELECT JOB_STATUS, JOB_PRODUCEWEIGHT, JOB_PRODUCEPIECE FROM JOBORDER1 WHERE JOB_ORDER = :job");
    $stmtCheckJob->execute([':job' => $jobOrder]);
    $jobRow = $stmtCheckJob->fetch(PDO::FETCH_ASSOC);

    if (!$jobRow || $jobRow['JOB_STATUS'] !== 'OP') {
        throw new Exception("BCRSH-06: Job Order ไม่อยู่ในสถานะ OP (OPEN) ไม่สามารถบันทึกได้");
    }

    $stmtCheckCtm = $conn->prepare("SELECT CTM2_PRODUCEWEIGHT, CTM2_PRODUCEPIECE FROM CSTMORDR2 WHERE SALEORDER_NO = :so AND SALEORDER_ITEM = :item");
    $stmtCheckCtm->execute([':so' => $saleOrderNo, ':item' => $saleOrderItem]);
    $ctmRow = $stmtCheckCtm->fetch(PDO::FETCH_ASSOC);

    if ($recordStatus === 'New Record') {
        // สร้าง Product No. อัตโนมัติจาก CRSHPROD1
        $todayStr = date('ymd');
        $prefixPattern = "R" . $todayStr . "-0-%"; 

        $stmtAuto = $conn->prepare("SELECT TOP 1 PRODUCT_NO FROM CRSHPROD1 WHERE PRODUCT_NO LIKE :pattern ORDER BY PRODUCT_NO DESC");
        $stmtAuto->execute([':pattern' => $prefixPattern]);
        $lastProdRow = $stmtAuto->fetch(PDO::FETCH_ASSOC);

        $nextRunNo = 1;
        if ($lastProdRow && !empty($lastProdRow['PRODUCT_NO'])) {
            $lastRunStr = substr($lastProdRow['PRODUCT_NO'], -2);
            if (is_numeric($lastRunStr)) {
                $nextRunNo = (int)$lastRunStr + 1;
            }
        }
        
        $runNoPadded = str_pad($nextRunNo, 2, '0', STR_PAD_LEFT);
        $productNo = "R" . $todayStr . "-0-" . $runNoPadded;

        $sqlInsertCrsh = "INSERT INTO CRSHPROD1 (
            PRODUCT_NO, PRODUCT_REFERENCE, COIL_NO, SALEORDER_NO, SALEORDER_ITEM, JOB_ORDER, REQUISITION_NO,
            MP2_PARTNO, SERIAL_NO, MATERIAL_IN, MOULDSETUP_NO, MAINTENANCE_NO1, DIESET_NO, MAINTENANCE_NO2, DIE_NO,
            MAINTENANCE_NO3, PUNCH_NO1, MAINTENANCE_NO4, PUNCH_NO2, PRODUCT_ID, PRODUCT_MODEL, ORG_LINEPROCESS,
            LINE_PROCESS, LOCATION_ID, F_ALLOY, F_TEMPER, ALLOY, TEMPER, GRADE, SURFACE_GRADE, METALLURGICAL_GRADE,
            CRSH_WORKPROCESS, CRSH_NEXTPROCESS, CRSH_ACTUALWEIGHT, CRSH_ACTUALPIECE, CRSH_PRODUCEWEIGHT,
            CRSH_PRODUCEPIECE, CRSH_BOTTOMWEIGHT, MATERIAL_CODE, CRSH_STARTDATE, CRSH_STARTTIME, CRSH_ENDDATE,
            CRSH_ENDTIME, CRSH_LABEL, CRSH_OPERATOR1, CRSH_OPERATEDATE, CRSH_STATUS
        ) VALUES (
            :pno, '', '', :so, :so_item, :job, :req_no,
            :mp2, :serial, :mat_in, '', '', '', '', '',
            '', '', '', '', :prod_id, 'RAW MATERIAL', '0',
            '0', '', :f_alloy, '', :alloy, '', '', '', '',
            :work_proc, :next_proc, :act_wt, 1, :prod_wt,
            1, :bot_wt, '', GETDATE(), GETDATE(), GETDATE(),
            GETDATE(), :label, :oper, GETDATE(), :status
        )";
        $stmtCrsh = $conn->prepare($sqlInsertCrsh);
        $stmtCrsh->execute([
            ':pno'       => $productNo,
            ':so'        => $saleOrderNo,
            ':so_item'   => $saleOrderItem,
            ':job'       => $jobOrder,
            ':req_no'    => $reqNo,
            ':mp2'       => $mp2PartNo,
            ':serial'    => $serialNo,
            ':mat_in'    => $materialIn,
            ':prod_id'   => $productId,
            ':f_alloy'   => $alloy,
            ':alloy'     => $alloy,
            ':work_proc' => $workProcess,
            ':next_proc' => $nextProcess,
            ':act_wt'    => $produceWeight,
            ':prod_wt'   => $produceWeight,
            ':bot_wt'    => $bottomWeight,
            ':label'     => $crshLabel,
            ':oper'      => $userOperate,
            ':status'    => $crshStatus
        ]);

        $addWeight = ($produceWeight > 0) ? $produceWeight : 0;
        $addPiece  = ($produceWeight > 0) ? 1 : 0;

    } else {
        // กรณี Old Record (แก้ไขรายการเดิม)
        $sqlUpdCrsh = "UPDATE CRSHPROD1 
                       SET CRSH_ACTUALWEIGHT = :act_wt, 
                           CRSH_PRODUCEWEIGHT = :prod_wt, 
                           CRSH_BOTTOMWEIGHT = :bot_wt, 
                           CRSH_LABEL = :label, 
                           CRSH_STATUS = :status 
                       WHERE PRODUCT_NO = :pno";
        $stmtUpdCrsh = $conn->prepare($sqlUpdCrsh);
        $stmtUpdCrsh->execute([
            ':act_wt'  => $produceWeight,
            ':prod_wt' => $produceWeight,
            ':bot_wt'  => $bottomWeight,
            ':label'   => $crshLabel,
            ':status'  => $crshStatus,
            ':pno'     => $productNo
        ]);

        $addWeight = $produceWeight - $oldProduceWt;
        $addPiece  = 0;
    }

    if ($produceWeight > 0 && $crshStatus === 'ST') {
        $sqlPack = "INSERT INTO PACKPROD1 (
            PRODUCT_NO, SALEORDER_NO, SALEORDER_ITEM, JOB_ORDER, MATERIAL_IN, TRANSFER_DATE,
            RECEIVE_DATE, LINE_PROCESS, PRODUCT_ID, PRODUCT_MODEL, PACK_PONO, PACK_POITEM, PACK_DATE,
            PACK_PACKAGEWEIGHT, PACK_NETPIECE, PACK_NETWEIGHT, PACK_LABEL, PACK_OPERATOR1,
            PACK_OPERATEDATE, PACK_STATUS, ORG_STATUS
        ) VALUES (
            :pno, :so, :so_item, :job, :mat_in, GETDATE(),
            GETDATE(), '1', 'RM', 'RAW MATERIAL', :pono, :poitem, GETDATE(),
            :pkg_wt, 1, :net_wt, :label, :oper,
            GETDATE(), :status, :org_status
        )";
        $stmtPack = $conn->prepare($sqlPack);
        $stmtPack->execute([
            ':pno'        => $productNo,
            ':so'         => $saleOrderNo,
            ':so_item'    => $saleOrderItem,
            ':job'        => $jobOrder,
            ':mat_in'     => $materialIn,
            ':pono'       => $poNo,
            ':poitem'     => $poItem,
            ':pkg_wt'     => $bottomWeight,
            ':net_wt'     => $produceWeight,
            ':label'      => $crshLabel,
            ':oper'       => $userOperate,
            ':status'     => $crshStatus,
            ':org_status' => $crshStatus
        ]);
    }

    $newJobWt = (float)$jobRow['JOB_PRODUCEWEIGHT'] + $addWeight;
    $newJobPc = (float)$jobRow['JOB_PRODUCEPIECE'] + $addPiece;

    $sqlUpdJob = "UPDATE JOBORDER1 
                  SET JOB_ACTUALWEIGHT = :act_wt, 
                      JOB_ACTUALPIECE = :act_pc, 
                      JOB_PRODUCEWEIGHT = :prod_wt, 
                      JOB_PRODUCEPIECE = :prod_pc 
                  WHERE JOB_ORDER = :job";
    $stmtUpdJob = $conn->prepare($sqlUpdJob);
    $stmtUpdJob->execute([
        ':act_wt'  => $newJobWt,
        ':act_pc'  => $newJobPc,
        ':prod_wt' => $newJobWt,
        ':prod_pc' => $newJobPc,
        ':job'     => $jobOrder
    ]);

    if ($ctmRow) {
        $newCtmWt = (float)$ctmRow['CTM2_PRODUCEWEIGHT'] + $addWeight;
        $newCtmPc = (float)$ctmRow['CTM2_PRODUCEPIECE'] + $addPiece;

        $sqlUpdCtm = "UPDATE CSTMORDR2 
                      SET CTM2_ACTUALWEIGHT = :act_wt, 
                          CTM2_ACTUALPIECE = :act_pc, 
                          CTM2_PRODUCEWEIGHT = :prod_wt, 
                          CTM2_PRODUCEPIECE = :prod_pc 
                      WHERE SALEORDER_NO = :so AND SALEORDER_ITEM = :so_item";
        $stmtUpdCtm = $conn->prepare($sqlUpdCtm);
        $stmtUpdCtm->execute([
            ':act_wt'  => $newCtmWt,
            ':act_pc'  => $newCtmPc,
            ':prod_wt' => $newCtmWt,
            ':prod_pc' => $newCtmPc,
            ':so'      => $saleOrderNo,
            ':so_item' => $saleOrderItem
        ]);
    }

    $conn->commit();

    $printUrl = ($chkPrintLabel === 1) ? "print_small_ticket_label_mats.php?product_no=" . urlencode($productNo) . "&size=" . urlencode($labelSize) : "";

    echo json_encode([
        'status'     => 'success',
        'message'    => 'บันทึกข้อมูลเรียบร้อยแล้ว',
        'product_no' => $productNo,
        'print_url'  => $printUrl
    ]);

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode([
        'status'  => 'error',
        'message' => $e->getMessage()
    ]);
}
?>