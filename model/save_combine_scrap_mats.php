<?php
// model/save_combine_scrap_mats.php
session_start();
header('Content-Type: application/json; charset=UTF-8');

include '../function_mats.php';
include '../dbcon_mats-new.php';

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);

if (!$input || !isset($input['action'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Request']);
    exit();
}

$action     = $input['action'];
$user_id    = $_SESSION['ID'] ?? 'OPERATOR';
$line_proc  = $_SESSION['LINE'] ?? '1';

// =========================================================================
// ACTION 1: ดูรายละเอียดการ Combine (CMBNSCRP1)
// =========================================================================
if ($action === 'get_combine_detail') {
    $productNo = trim($input['productNo'] ?? '');
    try {
        $stmt = $conn->prepare("SELECT PRODUCT_NO, PRODUCT_REFERENCE, REFERENCE_NO, MATERIAL_IN, ALLOY, SCRP_WEIGHT, CMBN_OPERATOR, CMBN_OPERATEDATE 
                                FROM CMBNSCRP1 
                                WHERE PRODUCT_NO = ? 
                                ORDER BY CMBN_OPERATEDATE ASC");
        $stmt->execute([$productNo]);
        $details = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['status' => 'success', 'data' => $details]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}

// =========================================================================
// ACTION 2: ดึง Candidate Reference ตาม Logic f15 พร้อมบล็อก Alloy ที่ว่าง หรือ NONE
// =========================================================================
if ($action === 'get_candidate_list') {
    $scrapFrom    = $input['scrapFrom'];
    $process      = substr(trim($input['process']), 0, 4);
    $currentAlloy = trim($input['currentAlloy'] ?? '');

    try {
        $data = [];
        $params = [];

        // เงื่อนไขพื้นฐาน: Alloy ต้องไม่ว่าง ไม่เป็น NULL และต้องไม่ใช่ข้อความ 'NONE'
        $alloyFilter = " AND ALLOY IS NOT NULL AND RTRIM(LTRIM(ALLOY)) <> '' AND ALLOY <> 'NONE' ";

        if ($scrapFrom === 'FB') {
            // 1. FURNACE BATCH
            $sql = "SELECT TOP(100) BATCH_NO AS REF_NO, '' AS COIL_NO, MATERIAL_IN, ALLOY, 0 AS SCRP_WEIGHT 
                    FROM FRNCPRCS1 
                    WHERE 1=1 {$alloyFilter} ";
            if (!empty($currentAlloy)) {
                $sql .= " AND ALLOY = :alloy ";
                $params[':alloy'] = $currentAlloy;
            }
            $sql .= " ORDER BY BATCH_DATE DESC";
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        } elseif ($scrapFrom === 'CO') {
            // 2. COIL
            $where = "WHERE 1=1 {$alloyFilter} ";
            if (in_array($process, ['CM01', 'SS01', 'BK01', 'BK02', 'BK03', 'CL01', 'XC01'])) {
                $where .= " AND (LINE_PROCESS <> 'T') ";
            } elseif (in_array($process, ['AP01', 'AP02', 'KP01'])) {
                $where .= " AND (LINE_PROCESS = 'R' OR LINE_PROCESS = 'T') ";
            }

            if (!empty($currentAlloy)) {
                $where .= " AND ALLOY = :alloy ";
                $params[':alloy'] = $currentAlloy;
            }

            $sql = "SELECT TOP(150) COIL_NO AS REF_NO, COIL_NO, MATERIAL_IN, ALLOY, 0 AS SCRP_WEIGHT 
                    FROM COILPROD1 {$where} ORDER BY COIL_NO DESC";
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        } elseif ($scrapFrom === 'CS') {
            // 3. CIRCLE / SHEET (LINE_PROCESS <> 'W')
            $where = "WHERE (LINE_PROCESS <> 'W') {$alloyFilter} ";
            if (!empty($currentAlloy)) {
                $where .= " AND ALLOY = :alloy ";
                $params[':alloy'] = $currentAlloy;
            }

            $sql = "SELECT TOP(150) PRODUCT_NO AS REF_NO, COIL_NO, MATERIAL_IN, ALLOY, 0 AS SCRP_WEIGHT 
                    FROM CRSHPROD1 {$where} ORDER BY PRODUCT_NO DESC";
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        } elseif ($scrapFrom === 'SC') {
            // 4. SCRAP TO SCRAP (SCRP_STATUS = 'OP')
            $where = "WHERE SCRP_STATUS = 'OP' {$alloyFilter} ";
            if (!empty($currentAlloy)) {
                $where .= " AND ALLOY = :alloy ";
                $params[':alloy'] = $currentAlloy;
            }

            $sql = "SELECT TOP(150) PRODUCT_NO AS REF_NO, COIL_NO, MATERIAL_IN, ALLOY, SCRP_WEIGHT 
                    FROM SCRPPROD1 {$where} ORDER BY PRODUCT_NO DESC";
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        echo json_encode(['status' => 'success', 'data' => $data]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}

// =========================================================================
// ACTION 3: บันทึกข้อมูล Combine ลง SCRPPROD1 และ CMBNSCRP1
// =========================================================================
if ($action === 'save_combine_full') {
    $process     = substr(trim($input['process']), 0, 4);
    $scrapFrom   = trim($input['scrapFrom']);
    $locationId  = trim($input['locationId']);
    $totalWeight = floatval($input['totalWeight']);
    $alloy       = trim($input['alloy']);
    $materialIn  = trim($input['materialIn']);
    $references  = $input['references'] ?? [];

    if (empty($references)) {
        echo json_encode(['status' => 'error', 'message' => 'ไม่มีรายการ Reference']);
        exit();
    }

    // ตรวจสอบ Alloy อีกครั้งก่อนบันทึก
    if (empty($alloy) || strtoupper($alloy) === 'NONE') {
        echo json_encode(['status' => 'error', 'message' => 'ไม่อนุญาตให้บันทึก: ค่า Alloy ไม่ถูกต้องหรือเป็นค่า NONE']);
        exit();
    }

    try {
        $conn->beginTransaction();

        $todayStr = date('ymd');
        $prefix   = 'S' . $todayStr . '-0-';

        $stmtSeq = $conn->prepare("SELECT TOP(1) PRODUCT_NO FROM SCRPPROD1 WITH (UPDLOCK) WHERE PRODUCT_NO LIKE ? ORDER BY PRODUCT_NO DESC");
        $stmtSeq->execute([$prefix . '%']);
        $lastRow = $stmtSeq->fetch(PDO::FETCH_ASSOC);

        $nextSeq = 1;
        if ($lastRow) {
            $lastDigits = intval(substr($lastRow['PRODUCT_NO'], -2));
            $nextSeq = $lastDigits + 1;
        }
        $masterProductNo = sprintf("%s%02d", $prefix, $nextSeq);

        $stmtVerify = $conn->prepare("SELECT PRODUCT_NO FROM SCRPPROD1 WHERE PRODUCT_NO = ?");
        $stmtVerify->execute([$masterProductNo]);
        while ($stmtVerify->fetch(PDO::FETCH_ASSOC)) {
            $nextSeq++;
            $masterProductNo = sprintf("%s%02d", $prefix, $nextSeq);
            $stmtVerify->execute([$masterProductNo]);
        }

        $refCoilNo = $references[0]['coilNo'] ?? '';

        $sqlM = "INSERT INTO SCRPPROD1 (
                    PRODUCT_NO, SCRP_DATE, COIL_NO, MATERIAL_IN, LINE_PROCESS, PROCESS, 
                    SCRP_FROM, LOCATION_ID, ALLOY, SCRP_WIDTH, SCRP_WEIGHT, SCRP_LABEL, 
                    SCRP_OPERATOR, SCRP_OPERATEDATE, SCRP_STATUS
                ) VALUES (?, GETDATE(), ?, ?, '0', ?, ?, ?, ?, 0, ?, 1, ?, GETDATE(), 'OP')";
        $stmtM = $conn->prepare($sqlM);
        $stmtM->execute([
            $masterProductNo,
            $refCoilNo,
            $materialIn,
            $process,
            $scrapFrom,
            $locationId,
            $alloy,
            $totalWeight,
            $user_id
        ]);

        $sqlD = "INSERT INTO CMBNSCRP1 (
                    PRODUCT_NO, PRODUCT_REFERENCE, REFERENCE_NO, MATERIAL_IN, 
                    ALLOY, SCRP_WEIGHT, CMBN_OPERATOR, CMBN_OPERATEDATE
                ) VALUES (?, ?, ?, ?, ?, ?, ?, GETDATE())";
        $stmtD = $conn->prepare($sqlD);

        $sqlUpdateSub = $conn->prepare("UPDATE SCRPPROD1 SET SCRP_STATUS = 'CB' WHERE PRODUCT_NO = ?");

        foreach ($references as $ref) {
            $stmtD->execute([
                $masterProductNo,
                $ref['refNo'],
                $ref['coilNo'],
                $ref['materialIn'],
                $ref['alloy'],
                $ref['weight'],
                $user_id
            ]);

            if ($scrapFrom === 'SC' || in_array($process, ['CM1X', 'SS1X', 'AP1X', 'AP2X', 'KP1X'])) {
                $sqlUpdateSub->execute([$ref['refNo']]);
            }
        }

        $conn->commit();
        echo json_encode([
            'status' => 'success',
            'product_no' => $masterProductNo
        ]);

    } catch (Exception $e) {
        $conn->rollBack();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}