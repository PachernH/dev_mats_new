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
    echo json_encode(['status' => 'error', 'message' => 'ข้อมูลนำเข้าไม่ครบถ้วน (กรุณาระบุ Product No และ Job Order)']);
    exit();
}

try {
    $conn->beginTransaction();

    // 1. ดึงข้อมูล Sale Order และ JOIN กับ CSTMORDR2 เพื่อเอา ORDER_CATEGORY ของ Job ใหม่
    $stmtJob = $conn->prepare("
        SELECT 
            j.SALEORDER_NO, 
            j.SALEORDER_ITEM, 
            c.ORDER_CATEGORY 
        FROM JOBORDER1 AS j
        LEFT JOIN CSTMORDR2 AS c 
            ON c.SALEORDER_NO = j.SALEORDER_NO 
           AND c.SALEORDER_ITEM = j.SALEORDER_ITEM
        WHERE j.JOB_ORDER = :job_no
    ");
    $stmtJob->execute([':job_no' => $job_order]);
    $jobInfo = $stmtJob->fetch(PDO::FETCH_ASSOC);

    if (!$jobInfo) {
        throw new Exception("ไม่พบรหัส Job Order '{$job_order}' ในระบบ JOBORDER1");
    }

    $soNo        = $jobInfo['SALEORDER_NO'] ?? '';
    $soItem      = $jobInfo['SALEORDER_ITEM'] ?? '';
    $toOrderCat  = trim($jobInfo['ORDER_CATEGORY'] ?? '');

    // 2. [แก้ไขจุด Error] ดึงข้อมูลสถานะ CRSH_STATUS และ JOIN เอา ORDER_CATEGORY เดิมของสินค้าผ่าน Sale Order ใน CRSHPROD1
    $stmtCrshInfo = $conn->prepare("
        SELECT 
            a.CRSH_STATUS, 
            b.ORDER_CATEGORY 
        FROM CRSHPROD1 AS a
        LEFT JOIN CSTMORDR2 AS b 
            ON b.SALEORDER_NO = a.SALEORDER_NO 
           AND b.SALEORDER_ITEM = a.SALEORDER_ITEM
        WHERE a.PRODUCT_NO = :pno
    ");
    $stmtCrshInfo->execute([':pno' => $product_no]);
    $crshInfo   = $stmtCrshInfo->fetch(PDO::FETCH_ASSOC);

    $crshStatus = trim($crshInfo['CRSH_STATUS'] ?? '');
    $frOrderCat = trim($crshInfo['ORDER_CATEGORY'] ?? '');

    // 3. ผูก JOB_ORDER, SALEORDER_NO และ SALEORDER_ITEM เข้ากับตาราง CRSHPROD1
    $stmtBindCrsh = $conn->prepare("
        UPDATE CRSHPROD1 
        SET JOB_ORDER = :job_no, SALEORDER_NO = :so_no, SALEORDER_ITEM = :so_item 
        WHERE PRODUCT_NO = :pno
    ");
    $stmtBindCrsh->execute([
        ':job_no'  => $job_order,
        ':so_no'   => $soNo,
        ':so_item' => $soItem,
        ':pno'     => $product_no
    ]);

    // 4. ผูกกับตาราง BTCHPROD0
    $stmtBindBtch = $conn->prepare("
        UPDATE BTCHPROD0 
        SET JOB_ORDER = :job_no, SALEORDER_NO = :so_no, SALEORDER_ITEM = :so_item 
        WHERE PRODUCT_NO = :pno
    ");
    $stmtBindBtch->execute([
        ':job_no'  => $job_order,
        ':so_no'   => $soNo,
        ':so_item' => $soItem,
        ':pno'     => $product_no
    ]);

    // 5. อัปเดตตาราง PACKPROD1 (Sale Order / Job Order / PO)
    $stmtCheckPack = $conn->prepare("SELECT PRODUCT_NO FROM PACKPROD1 WHERE PRODUCT_NO = :pno");
    $stmtCheckPack->execute([':pno' => $product_no]);
    if ($stmtCheckPack->fetch()) {
        $stmtUpdPack = $conn->prepare("
            UPDATE PACKPROD1 
            SET SALEORDER_NO = :so_no, SALEORDER_ITEM = :so_item, JOB_ORDER = :job_no 
            WHERE PRODUCT_NO = :pno
        ");
        $stmtUpdPack->execute([
            ':so_no'   => $soNo,
            ':so_item' => $soItem,
            ':job_no'  => $job_order,
            ':pno'     => $product_no
        ]);
    }

    // 6. ตรวจสอบเงื่อนไขการอัปเดต VMI Status ใน PACKPROD1
    $isVmiUpdateNeeded = false;
    if ($frOrderCat === "OVMI" && $toOrderCat !== "OVMI") {
        if ($crshStatus !== "OP" && $crshStatus !== "AC") { $isVmiUpdateNeeded = true; }
    } elseif ($frOrderCat !== "OVMI" && $toOrderCat === "OVMI") {
        if ($crshStatus !== "OP" && $crshStatus !== "AC") { $isVmiUpdateNeeded = true; }
    }

    if ($isVmiUpdateNeeded) {
        $stmtVmi = $conn->prepare("UPDATE PACKPROD1 SET VMI_STATUS = 'ST' WHERE PRODUCT_NO = :pno");
        $stmtVmi->execute([':pno' => $product_no]);
    }

    // 7. ค้นหาและอัปเดตชิ้นงานอ้างอิง CMBNPROD1 / PRODUCT_REFERENCE
    $stmtRef = $conn->prepare("SELECT A.PRODUCT_NO, A.PRODUCT_REFERENCE FROM CMBNPROD1 AS A WHERE A.PRODUCT_NO = :pno");
    $stmtRef->execute([':pno' => $product_no]);
    $refs = $stmtRef->fetchAll(PDO::FETCH_ASSOC);

    foreach ($refs as $row) {
        if (!empty($row['PRODUCT_REFERENCE'])) {
            $stmtUpdRef = $conn->prepare("UPDATE CRSHPROD1 SET SALEORDER_NO = :so_no, SALEORDER_ITEM = :so_item, JOB_ORDER = :job_no WHERE PRODUCT_NO = :ref_pno");
            $stmtUpdRef->execute([
                ':so_no'   => $soNo,
                ':so_item' => $soItem,
                ':job_no'  => $job_order,
                ':ref_pno' => $row['PRODUCT_REFERENCE']
            ]);
        }
    }

    // 8. สั่ง Recalculate คำนวณบวกยอดสะสมใหม่เข้า JOBORDER1 และ CSTMORDR2
    reCalJobOrder($conn, $job_order);

    $conn->commit();
    echo json_encode(['status' => 'success', 'message' => "ผูก Job Order '{$job_order}' กับสินค้า '{$product_no}' เรียบร้อยแล้ว"]);

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในการผูก Job: ' . $e->getMessage()]);
}

/**
 * ฟังก์ชัน Recalculate ยอดรวมสะสมของ JOBORDER1 (รวม PACKPROD1)
 */
function reCalJobOrder($conn, $jobNo) {
    if (empty($jobNo)) return;

    $stmtJob = $conn->prepare("SELECT SALEORDER_NO, SALEORDER_ITEM, ISNULL(WEIGHT_PIECE, 0) AS WEIGHT_PIECE FROM JOBORDER1 WHERE JOB_ORDER = :job_no");
    $stmtJob->execute([':job_no' => $jobNo]);
    $jobInfo = $stmtJob->fetch(PDO::FETCH_ASSOC);

    if (!$jobInfo) return;

    $weightPiece = (float)($jobInfo['WEIGHT_PIECE'] ?? 0);

    // [แก้ไขจุดสะกด Alias] CRSH_CUTSHEETPIECE ให้ Alias เป็น CUTSHEETPIECE
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
               FROM CRSHPROD1 WHERE JOB_ORDER = :job_no";
               
    $stmtSum = $conn->prepare($sqlSum);
    $stmtSum->execute([':job_no' => $jobNo]);
    $sums = $stmtSum->fetch(PDO::FETCH_ASSOC);

    $stockWeight = 0;
    $stockPiece  = 0;

    $sqlPack = "SELECT PACK_NETWEIGHT, PACK_NETPIECE 
                FROM PACKPROD1 
                WHERE JOB_ORDER = :job_no 
                  AND (PACK_STATUS = 'SH' OR PACK_STATUS = 'IN' OR PACK_STATUS = 'ST' OR PACK_STATUS = 'DS')";
    $stmtPack = $conn->prepare($sqlPack);
    $stmtPack->execute([':job_no' => $jobNo]);
    $packList = $stmtPack->fetchAll(PDO::FETCH_ASSOC);

    foreach ($packList as $pack) {
        $netWeight = (float)($pack['PACK_NETWEIGHT'] ?? 0);
        $netPiece  = (float)($pack['PACK_NETPIECE'] ?? 0);

        $stockWeight += round($netWeight);

        if ($netPiece <= 0) {
            if ($weightPiece != 0) {
                $stockPiece += round($netWeight / $weightPiece);
            }
        } else {
            $stockPiece += $netPiece;
        }
    }

    $sqlUpdateJob = "UPDATE JOBORDER1 SET 
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
                        JOB_PACKWEIGHT = :pck_wt, JOB_PACKPIECE = :pck_pc,
                        JOB_STOCKWEIGHT = :stk_wt, JOB_STOCKPIECE = :stk_pc
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
        ':stk_wt'  => round($stockWeight),               ':stk_pc'  => round($stockPiece),
        ':job_no'  => $jobNo
    ]);

    if (!empty($jobInfo['SALEORDER_NO'])) {
        reCalSaleOrder($conn, $jobInfo['SALEORDER_NO'], $jobInfo['SALEORDER_ITEM']);
    }
}

/**
 * ฟังก์ชัน Recalculate ยอดรวมสะสมของ CSTMORDR2 (รวม PACKPROD1 และ INVCPRCS3)
 */
function reCalSaleOrder($conn, $soNo, $soItem) {
    if (empty($soNo)) return;

    $stmtSO = $conn->prepare("SELECT ISNULL(WEIGHT_PIECE, 0) AS WEIGHT_PIECE FROM CSTMORDR2 WHERE SALEORDER_NO = :so_no AND SALEORDER_ITEM = :so_item");
    $stmtSO->execute([':so_no' => $soNo, ':so_item' => $soItem]);
    $soInfo = $stmtSO->fetch(PDO::FETCH_ASSOC);

    $weightPiece = (float)($soInfo['WEIGHT_PIECE'] ?? 0);

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
                 FROM CRSHPROD1 
                 WHERE SALEORDER_NO = :so_no AND SALEORDER_ITEM = :so_item";
                 
    $stmtSumSO = $conn->prepare($sqlSumSO);
    $stmtSumSO->execute([':so_no' => $soNo, ':so_item' => $soItem]);
    $sumsSO = $stmtSumSO->fetch(PDO::FETCH_ASSOC);

    $ctmStockWeight = 0;
    $ctmStockPiece  = 0;

    $sqlPackSO = "SELECT PACK_NETWEIGHT, PACK_NETPIECE 
                  FROM PACKPROD1 
                  WHERE SALEORDER_NO = :so_no AND SALEORDER_ITEM = :so_item
                    AND (PACK_STATUS = 'SH' OR PACK_STATUS = 'IN' OR PACK_STATUS = 'ST' OR PACK_STATUS = 'DS')";
    $stmtPackSO = $conn->prepare($sqlPackSO);
    $stmtPackSO->execute([':so_no' => $soNo, ':so_item' => $soItem]);
    $packSOList = $stmtPackSO->fetchAll(PDO::FETCH_ASSOC);

    foreach ($packSOList as $pack) {
        $netWeight = (float)($pack['PACK_NETWEIGHT'] ?? 0);
        $netPiece  = (float)($pack['PACK_NETPIECE'] ?? 0);

        $ctmStockWeight += round($netWeight);

        if ($netPiece <= 0) {
            if ($weightPiece != 0) {
                $ctmStockPiece += round($netWeight / $weightPiece);
            }
        } else {
            $ctmStockPiece += round($netPiece);
        }
    }

    $ctmInvoiceBundle = 0;
    $ctmInvoiceWeight = 0;
    $ctmInvoicePiece  = 0;

    $sqlInv = "SELECT INV3_NETWEIGHT, INV3_NETPIECE, INV3_PACKAGE 
               FROM INVCPRCS3 
               WHERE SALEORDER_NO = :so_no AND SALEORDER_ITEM = :so_item";
    $stmtInv = $conn->prepare($sqlInv);
    $stmtInv->execute([':so_no' => $soNo, ':so_item' => $soItem]);
    $invList = $stmtInv->fetchAll(PDO::FETCH_ASSOC);

    foreach ($invList as $inv) {
        $invPkg = (float)($inv['INV3_PACKAGE'] ?? 0);
        $invWt  = (float)($inv['INV3_NETWEIGHT'] ?? 0);
        $invPc  = (float)($inv['INV3_NETPIECE'] ?? 0);

        $ctmInvoiceBundle += round($invPkg);
        $ctmInvoiceWeight += round($invWt);

        if ($invPc <= 0) {
            if ($weightPiece != 0) {
                $ctmInvoicePiece += round($invWt / $weightPiece);
            }
        } else {
            $ctmInvoicePiece += round($invPc);
        }
    }

    $sqlUpdateSO = "UPDATE CSTMORDR2 SET 
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
                        CTM2_PACKWEIGHT = :pck_wt, CTM2_PACKPIECE = :pck_pc,
                        CTM2_STOCKWEIGHT = :stk_wt, CTM2_STOCKPIECE = :stk_pc,
                        CTM2_INVOICEBUNDLE = :inv_bd, CTM2_INVOICEPIECE = :inv_pc, CTM2_INVOICEWEIGHT = :inv_wt
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
        ':stk_wt'  => round($ctmStockWeight),            ':stk_pc'  => round($ctmStockPiece),
        ':inv_bd'  => round($ctmInvoiceBundle),          ':inv_pc'  => round($ctmInvoicePiece), ':inv_wt' => round($ctmInvoiceWeight),
        ':so_no'   => $soNo,
        ':so_item' => $soItem
    ]);
}