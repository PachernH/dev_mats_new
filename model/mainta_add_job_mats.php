<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['ID'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit();
}

include("../dbcon_mats-new.php");

$product_no = isset($_POST['product_no']) ? trim($_POST['product_no']) : '';
$job_order  = isset($_POST['job_order']) ? trim($_POST['job_order']) : '';

if (empty($product_no) || empty($job_order)) {
    echo json_encode(['status' => 'error', 'message' => 'ข้อมูลนำเข้าไม่ครบถ้วน']);
    exit();
}

try {
    $conn->beginTransaction();

    // 1. ค้นหา Sale Order No และ Item จาก JOBORDER1 มาเตรียมผูก
    $stmtJob = $conn->prepare("SELECT SALEORDER_NO, SALEORDER_ITEM FROM [MATS-NEW].dbo.JOBORDER1 WHERE JOB_ORDER = :job_no");
    $stmtJob->execute([':job_no' => $job_order]);
    $jobInfo = $stmtJob->fetch(PDO::FETCH_ASSOC);

    if (!$jobInfo) {
        throw new Exception("ไม่พบข้อมูล Job Order: {$job_order} ในระบบ");
    }

    $soNo   = $jobInfo['SALEORDER_NO'] ?? '';
    $soItem = $jobInfo['SALEORDER_ITEM'] ?? '';

    // 2. ผูก JOB_ORDER และ SALEORDER เข้ากับตาราง CRSHPROD1
    $stmtBindCrsh = $conn->prepare("
        UPDATE [MATS-NEW].dbo.CRSHPROD1 
        SET JOB_ORDER = :job_no, SALEORDER_NO = :so_no, SALEORDER_ITEM = :so_item 
        WHERE PRODUCT_NO = :pno
    ");
    $stmtBindCrsh->execute([
        ':job_no'  => $job_order,
        ':so_no'   => $soNo,
        ':so_item' => $soItem,
        ':pno'     => $product_no
    ]);

    // 3. ผูกกับตาราง BTCHPROD0 ด้วย
    $stmtBindBtch = $conn->prepare("
        UPDATE [MATS-NEW].dbo.BTCHPROD0 
        SET JOB_ORDER = :job_no, SALEORDER_NO = :so_no, SALEORDER_ITEM = :so_item 
        WHERE PRODUCT_NO = :pno
    ");
    $stmtBindBtch->execute([
        ':job_no'  => $job_order,
        ':so_no'   => $soNo,
        ':so_item' => $soItem,
        ':pno'     => $product_no
    ]);

    // 4. สั่ง Recalculate ยอดรวมของ JOBORDER1 และ CSTMORDR2 ใหม่ (เพิ่มยอดที่ผูกใหม่เข้าไป)
    reCalJobOrder($conn, $job_order);

    $conn->commit();
    echo json_encode(['status' => 'success', 'message' => "ผูก Job Order '{$job_order}' เรียบร้อยแล้ว"]);

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในการผูก Job: ' . $e->getMessage()]);
}

/**
 * ฟังก์ชัน Recalculate ยอดรวมสะสมของ JOBORDER1 
 */
function reCalJobOrder($conn, $jobNo) {
    if (empty($jobNo)) return;

    $stmtJob = $conn->prepare("SELECT SALEORDER_NO, SALEORDER_ITEM FROM [MATS-NEW].dbo.JOBORDER1 WHERE JOB_ORDER = :job_no");
    $stmtJob->execute([':job_no' => $jobNo]);
    $jobInfo = $stmtJob->fetch(PDO::FETCH_ASSOC);

    // SUM ยอดรวมผลิตทั้งหมดหลังจากการผูกสินค้าเพิ่มแล้ว
    $sqlSum = "SELECT 
                SUM(ISNULL(CRSH_ACTUALWEIGHT,0) - ISNULL(CRSH_COMBINEWEIGHT,0)) AS ACTUALWEIGHT,
                SUM(ISNULL(CRSH_ACTUALPIECE,0) - ISNULL(CRSH_COMBINEPIECE,0)) AS ACTUALPIECE,
                SUM(ISNULL(CRSH_PRODUCEWEIGHT,0)) AS PRODUCEWEIGHT, SUM(ISNULL(CRSH_PRODUCEPIECE,0)) AS PRODUCEPIECE,
                SUM(ISNULL(CRSH_STRETCHERWEIGHT,0)) AS STRETCHERWEIGHT, SUM(ISNULL(CRSH_STRETCHERPIECE,0)) AS STRETCHERPIECE,
                SUM(ISNULL(CRSH_CUTSHEETWEIGHT,0)) AS CUTSHEETWEIGHT, SUM(ISNULL(CRSH_CUTSHEETPIECE,0)) AS CUTSHEETPIECE,
                SUM(ISNULL(CRSH_SHEARWEIGHT,0)) AS SHEARWEIGHT, SUM(ISNULL(CRSH_SHEARPIECE,0)) AS SHEARPIECE,
                SUM(ISNULL(CRSH_PUNCHWEIGHT,0)) AS PUNCHWEIGHT, SUM(ISNULL(CRSH_PUNCHPIECE,0)) AS PUNCHPIECE,
                SUM(ISNULL(CRSH_BATCHANNEALWEIGHT,0)) AS BATCHANNEALWEIGHT, SUM(ISNULL(CRSH_BATCHANNEALPIECE,0)) AS BATCHANNEALPIECE,
                SUM(ISNULL(CRSH_TRANSFERWEIGHT,0)) AS TRANSFERWEIGHT, SUM(ISNULL(CRSH_TRANSFERPIECE,0)) AS TRANSFERPIECE,
                SUM(ISNULL(CRSH_ANNEALWEIGHT,0)) AS ANNEALWEIGHT, SUM(ISNULL(CRSH_ANNEALPIECE,0)) AS ANNEALPIECE,
                SUM(ISNULL(CRSH_SORTWEIGHT,0)) AS SORTWEIGHT, SUM(ISNULL(CRSH_SORTPIECE,0)) AS SORTPIECE,
                SUM(ISNULL(CRSH_TAKEOUTWEIGHT,0)) AS TAKEOUTWEIGHT, SUM(ISNULL(CRSH_TAKEOUTPIECE,0)) AS TAKEOUTPIECE,
                SUM(ISNULL(CRSH_PACKWEIGHT,0)) AS PACKWEIGHT, SUM(ISNULL(CRSH_PACKPIECE,0)) AS PACKPIECE
               FROM [MATS-NEW].dbo.CRSHPROD1 WHERE JOB_ORDER = :job_no";
               
    $stmtSum = $conn->prepare($sqlSum);
    $stmtSum->execute([':job_no' => $jobNo]);
    $sums = $stmtSum->fetch(PDO::FETCH_ASSOC);

    // อัปเดตยอดสรุปสะสมใหม่ลง JOBORDER1
    $sqlUpdateJob = "UPDATE [MATS-NEW].dbo.JOBORDER1 SET 
                        JOB_ACTUALWEIGHT = :act_wt, JOB_ACTUALPIECE = :act_pc,
                        JOB_PRODUCEWEIGHT = :prod_wt, JOB_PRODUCEPIECE = :prod_pc,
                        JOB_STRETCHERWEIGHT = :str_wt, JOB_STRETCHERPIECE = :str_pc,
                        JOB_CUTSHEETWEIGHT = :cut_wt, JOB_CUTSHEETPIECE = :cut_pc,
                        JOB_SHEARWEIGHT = :shr_wt, JOB_SHEARPIECE = :shr_pc,
                        JOB_PUNCHWEIGHT = :pun_wt, JOB_PUNCHPIECE = :pun_pc,
                        JOB_BATCHANNEALWEIGHT = :bat_wt, JOB_BATCHANNEALPIECE = :bat_pc,
                        JOB_TRANSFERWEIGHT = :trf_wt, JOB_TRANSFERPIECE = :trf_pc,
                        JOB_ANNEALWEIGHT = :ann_wt, JOB_ANNEALPIECE = :ann_pc,
                        JOB_SORTWEIGHT = :sort_wt, JOB_SORTPIECE = :sort_pc,
                        JOB_TAKEOUTWEIGHT = :take_wt, JOB_TAKEOUTPIECE = :take_pc,
                        JOB_PACKWEIGHT = :pck_wt, JOB_PACKPIECE = :pck_pc
                     WHERE JOB_ORDER = :job_no";
                     
    $stmtUpdateJob = $conn->prepare($sqlUpdateJob);
    $stmtUpdateJob->execute([
        ':act_wt'  => round($sums['ACTUALWEIGHT'] ?? 0),  ':act_pc'  => round($sums['ACTUALPIECE'] ?? 0),
        ':prod_wt' => round($sums['PRODUCEWEIGHT'] ?? 0), ':prod_pc' => round($sums['PRODUCEPIECE'] ?? 0),
        ':str_wt'  => round($sums['STRETCHERWEIGHT'] ?? 0),':str_pc'  => round($sums['STRETCHERPIECE'] ?? 0),
        ':cut_wt'  => round($sums['CUTSHEETWEIGHT'] ?? 0),  ':cut_pc'  => round($sums['CUTSHEETPIECE'] ?? 0),
        ':shr_wt'  => round($sums['SHEARWEIGHT'] ?? 0),   ':shr_pc'  => round($sums['SHEARPIECE'] ?? 0),
        ':pun_wt'  => round($sums['PUNCHWEIGHT'] ?? 0),   ':pun_pc'  => round($sums['PUNCHPIECE'] ?? 0),
        ':bat_wt'  => round($sums['BATCHANNEALWEIGHT'] ?? 0), ':bat_pc' => round($sums['BATCHANNEALPIECE'] ?? 0),
        ':trf_wt'  => round($sums['TRANSFERWEIGHT'] ?? 0), ':trf_pc'  => round($sums['TRANSFERPIECE'] ?? 0),
        ':ann_wt'  => round($sums['ANNEALWEIGHT'] ?? 0),   ':ann_pc'  => round($sums['ANNEALPIECE'] ?? 0),
        ':sort_wt' => round($sums['SORTWEIGHT'] ?? 0),    ':sort_pc' => round($sums['SORTPIECE'] ?? 0),
        ':take_wt' => round($sums['TAKEOUTWEIGHT'] ?? 0), ':take_pc' => round($sums['TAKEOUTPIECE'] ?? 0),
        ':pck_wt'  => round($sums['PACKWEIGHT'] ?? 0),    ':pck_pc'  => round($sums['PACKPIECE'] ?? 0),
        ':job_no'  => $jobNo
    ]);

    // Recalculate ยอด Sale Order สอดคล้องกันด้วย
    if ($jobInfo && !empty($jobInfo['SALEORDER_NO'])) {
        reCalSaleOrder($conn, $jobInfo['SALEORDER_NO'], $jobInfo['SALEORDER_ITEM']);
    }
}

/**
 * ฟังก์ชัน Recalculate ยอดรวมสะสมของ CSTMORDR2 
 */
function reCalSaleOrder($conn, $soNo, $soItem) {
    if (empty($soNo)) return;

    $sqlSumSO = "SELECT 
                    SUM(ISNULL(CRSH_ACTUALWEIGHT,0) - ISNULL(CRSH_COMBINEWEIGHT,0)) AS ACTUALWEIGHT,
                    SUM(ISNULL(CRSH_ACTUALPIECE,0) - ISNULL(CRSH_COMBINEPIECE,0)) AS ACTUALPIECE,
                    SUM(ISNULL(CRSH_PRODUCEWEIGHT,0)) AS PRODUCEWEIGHT, SUM(ISNULL(CRSH_PRODUCEPIECE,0)) AS PRODUCEPIECE,
                    SUM(ISNULL(CRSH_STRETCHERWEIGHT,0)) AS STRETCHERWEIGHT, SUM(ISNULL(CRSH_STRETCHERPIECE,0)) AS STRETCHERPIECE,
                    SUM(ISNULL(CRSH_CUTSHEETWEIGHT,0)) AS CUTSHEETWEIGHT, SUM(ISNULL(CRSH_CUTSHEETPIECE,0)) AS CUTSHEETPIECE,
                    SUM(ISNULL(CRSH_SHEARWEIGHT,0)) AS SHEARWEIGHT, SUM(ISNULL(CRSH_SHEARPIECE,0)) AS SHEARPIECE,
                    SUM(ISNULL(CRSH_PUNCHWEIGHT,0)) AS PUNCHWEIGHT, SUM(ISNULL(CRSH_PUNCHPIECE,0)) AS PUNCHPIECE,
                    SUM(ISNULL(CRSH_BATCHANNEALWEIGHT,0)) AS BATCHANNEALWEIGHT, SUM(ISNULL(CRSH_BATCHANNEALPIECE,0)) AS BATCHANNEALPIECE,
                    SUM(ISNULL(CRSH_TRANSFERWEIGHT,0)) AS TRANSFERWEIGHT, SUM(ISNULL(CRSH_TRANSFERPIECE,0)) AS TRANSFERPIECE,
                    SUM(ISNULL(CRSH_ANNEALWEIGHT,0)) AS ANNEALWEIGHT, SUM(ISNULL(CRSH_ANNEALPIECE,0)) AS ANNEALPIECE,
                    SUM(ISNULL(CRSH_SORTWEIGHT,0)) AS SORTWEIGHT, SUM(ISNULL(CRSH_SORTPIECE,0)) AS SORTPIECE,
                    SUM(ISNULL(CRSH_TAKEOUTWEIGHT,0)) AS TAKEOUTWEIGHT, SUM(ISNULL(CRSH_TAKEOUTPIECE,0)) AS TAKEOUTPIECE,
                    SUM(ISNULL(CRSH_PACKWEIGHT,0)) AS PACKWEIGHT, SUM(ISNULL(CRSH_PACKPIECE,0)) AS PACKPIECE
                 FROM [MATS-NEW].dbo.CRSHPROD1 
                 WHERE SALEORDER_NO = :so_no AND SALEORDER_ITEM = :so_item";
                 
    $stmtSumSO = $conn->prepare($sqlSumSO);
    $stmtSumSO->execute([':so_no' => $soNo, ':so_item' => $soItem]);
    $sumsSO = $stmtSumSO->fetch(PDO::FETCH_ASSOC);

    $sqlUpdateSO = "UPDATE [MATS-NEW].dbo.CSTMORDR2 SET 
                        CTM2_ACTUALWEIGHT = :act_wt, CTM2_ACTUALPIECE = :act_pc,
                        CTM2_PRODUCEWEIGHT = :prod_wt, CTM2_PRODUCEPIECE = :prod_pc,
                        CTM2_STRETCHERWEIGHT = :str_wt, CTM2_STRETCHERPIECE = :str_pc,
                        CTM2_CUTSHEETWEIGHT = :cut_wt, CTM2_CUTSHEETPIECE = :cut_pc,
                        CTM2_SHEARWEIGHT = :shr_wt, CTM2_SHEARPIECE = :shr_pc,
                        CTM2_PUNCHWEIGHT = :pun_wt, CTM2_PUNCHPIECE = :pun_pc,
                        CTM2_BATCHANNEALWEIGHT = :bat_wt, CTM2_BATCHANNEALPIECE = :bat_pc,
                        CTM2_TRANSFERWEIGHT = :trf_wt, CTM2_TRANSFERPIECE = :trf_pc,
                        CTM2_ANNEALWEIGHT = :ann_wt, CTM2_ANNEALPIECE = :ann_pc,
                        CTM2_SORTWEIGHT = :sort_wt, CTM2_SORTPIECE = :sort_pc,
                        CTM2_TAKEOUTWEIGHT = :take_wt, CTM2_TAKEOUTPIECE = :take_pc,
                        CTM2_PACKWEIGHT = :pck_wt, CTM2_PACKPIECE = :pck_pc
                    WHERE SALEORDER_NO = :so_no AND SALEORDER_ITEM = :so_item";
                    
    $stmtUpdateSO = $conn->prepare($sqlUpdateSO);
    $stmtUpdateSO->execute([
        ':act_wt'  => round($sumsSO['ACTUALWEIGHT'] ?? 0),  ':act_pc'  => round($sumsSO['ACTUALPIECE'] ?? 0),
        ':prod_wt' => round($sumsSO['PRODUCEWEIGHT'] ?? 0), ':prod_pc' => round($sumsSO['PRODUCEPIECE'] ?? 0),
        ':str_wt'  => round($sumsSO['STRETCHERWEIGHT'] ?? 0),':str_pc'  => round($sumsSO['STRETCHERPIECE'] ?? 0),
        ':cut_wt'  => round($sumsSO['CUTSHEETWEIGHT'] ?? 0),  ':cut_pc'  => round($sumsSO['CUTSHEETPIECE'] ?? 0),
        ':shr_wt'  => round($sumsSO['SHEARWEIGHT'] ?? 0),   ':shr_pc'  => round($sumsSO['SHEARPIECE'] ?? 0),
        ':pun_wt'  => round($sumsSO['PUNCHWEIGHT'] ?? 0),   ':pun_pc'  => round($sumsSO['PUNCHPIECE'] ?? 0),
        ':bat_wt'  => round($sumsSO['BATCHANNEALWEIGHT'] ?? 0), ':bat_pc' => round($sumsSO['BATCHANNEALPIECE'] ?? 0),
        ':trf_wt'  => round($sumsSO['TRANSFERWEIGHT'] ?? 0), ':trf_pc'  => round($sumsSO['TRANSFERPIECE'] ?? 0),
        ':ann_wt'  => round($sumsSO['ANNEALWEIGHT'] ?? 0),   ':ann_pc'  => round($sumsSO['ANNEALPIECE'] ?? 0),
        ':sort_wt' => round($sumsSO['SORTWEIGHT'] ?? 0),    ':sort_pc' => round($sumsSO['SORTPIECE'] ?? 0),
        ':take_wt' => round($sumsSO['TAKEOUTWEIGHT'] ?? 0), ':take_pc' => round($sumsSO['TAKEOUTPIECE'] ?? 0),
        ':pck_wt'  => round($sumsSO['PACKWEIGHT'] ?? 0),    ':pck_pc'  => round($sumsSO['PACKPIECE'] ?? 0),
        ':so_no'   => $soNo,
        ':so_item' => $soItem
    ]);
}