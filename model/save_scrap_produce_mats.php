<?php
// model/save_scrap_produce_mats.php
session_start();
header('Content-Type: application/json; charset=UTF-8');

include '../function_mats.php';
include '../dbcon_mats-new.php'; // เรียกใช้ $conn PDO เดิมของ MATS

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);

if (!$input || !isset($input['action'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Request Data']);
    exit();
}

$action = $input['action'];
$user_id = $_SESSION['ID'] ?? 'OPERATOR';

// ==========================================
// 1. ACTION: บันทึกข้อมูล (SAVE SCRAP)
// ==========================================
if ($action === 'save') {
    $scrapType   = $input['scrapType']; // 'S' หรือ 'U'
    $lineProcess = !empty($input['lineProcess']) ? $input['lineProcess'] : 'M';
    $coilNo      = trim($input['coilNo'] ?? '');
    $materialIn  = trim($input['materialIn'] ?? '');
    $alloy       = trim($input['alloy'] ?? '');
    $toLocation  = trim($input['toLocation'] ?? '');
    $scrapWeight = floatval($input['scrapWeight'] ?? 0);
    $remark      = trim($input['remark'] ?? '');

    if ($scrapWeight <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'กรุณากรอกน้ำหนัก Scrap ที่มากกว่า 0']);
        exit();
    }

    // Mapping รหัสกระบวนการตาม Logic f14
    $processMapping = [
        '1' => 'BK01', '2' => 'BK02', '3' => 'BK03', '5' => 'XC01',
        'A' => 'CL01', 'F' => 'AP01', 'G' => 'AP02', 'H' => 'AP03',
        'K' => 'KP01', 'L' => 'KP02', 'M' => 'CM01', 'T' => 'SS01'
    ];
    $processCode = $processMapping[$lineProcess] ?? 'CM01';

    try {
        $conn->beginTransaction();

        $newCoilScrapWeight = 0;
        $newCoilBalanceWeight = 0;
        $newCoilStatus = '';

        // ----------------------------------------------------
        // ตรวจสอบและคำนวณตัดสต็อก Coil (กรณี Type S)
        // ----------------------------------------------------
        if ($scrapType === 'S') {
            // Lock แถวป้องกันการบันทึกพร้อมกัน
            $stmtCoil = $conn->prepare("SELECT COIL_ACTUALWEIGHT, COIL_PRODUCEWEIGHT, COIL_SCRAPWEIGHT, 
                                               COIL_ADJUSTWEIGHT, COIL_BALANCEWEIGHT, COIL_STATUS 
                                        FROM COILPROD1 WITH (UPDLOCK, ROWLOCK) 
                                        WHERE COIL_NO = ?");
            $stmtCoil->execute([$coilNo]);
            $coil = $stmtCoil->fetch(PDO::FETCH_ASSOC);

            if (!$coil) {
                throw new Exception("ไม่พบข้อมูล Coil No: " . $coilNo);
            }

            $coilActualWeight  = floatval($coil['COIL_ACTUALWEIGHT']);
            $coilProduceWeight = floatval($coil['COIL_PRODUCEWEIGHT']);
            $coilScrapWeight   = floatval($coil['COIL_SCRAPWEIGHT']);
            $coilAdjustWeight  = floatval($coil['COIL_ADJUSTWEIGHT']);
            $currentStatus     = $coil['COIL_STATUS'];

            // คำนวณค่าน้ำหนักคงเหลือตาม logic VerifyValue
            $newCoilScrapWeight = $coilScrapWeight + $scrapWeight;
            $newCoilBalanceWeight = $coilActualWeight - ($coilProduceWeight + $newCoilScrapWeight + $coilAdjustWeight);

            if ($newCoilBalanceWeight < 0) {
                throw new Exception("ไม่สามารถบันทึกได้: ยอด Balance คงเหลือติดลบ!");
            }

            // ถ้า Balance เป็น 0 และไม่ใช่ WK ให้ปิดสถานะคอยล์เป็น CL
            if ($newCoilBalanceWeight == 0) {
                $newCoilStatus = ($currentStatus !== 'WK') ? 'CL' : $currentStatus;
            } else {
                $newCoilStatus = $currentStatus;
            }
        }

        // ----------------------------------------------------
        // Gen Running Number (Format: SYYMMDD-LINE-XX หรือ UYYMMDD-1-XX)
        // ----------------------------------------------------
        $todayStr = date('ymd'); // YYMMDD
        $lineId   = ($scrapType === 'U') ? '1' : $lineProcess;
        $prefix   = $scrapType . $todayStr . '-' . $lineId . '-';

        // หา Running ล่าสุดของวันนั้น
        $stmtSeq = $conn->prepare("SELECT TOP(1) PRODUCT_NO 
                                   FROM SCRPPROD1 WITH (UPDLOCK) 
                                   WHERE PRODUCT_NO LIKE ? 
                                   ORDER BY PRODUCT_NO DESC");
        $stmtSeq->execute([$prefix . '%']);
        $lastRow = $stmtSeq->fetch(PDO::FETCH_ASSOC);

        $nextSeq = 1;
        if ($lastRow) {
            $lastDigits = intval(substr($lastRow['PRODUCT_NO'], -2));
            $nextSeq = $lastDigits + 1;
        }
        $productNo = sprintf("%s%02d", $prefix, $nextSeq);

        // ตรวจสอบความปลอดภัยซ้ำ (ป้องกันชน)
        $stmtVerify = $conn->prepare("SELECT PRODUCT_NO FROM SCRPPROD1 WHERE PRODUCT_NO = ?");
        $stmtVerify->execute([$productNo]);
        while ($stmtVerify->fetch(PDO::FETCH_ASSOC)) {
            $nextSeq++;
            $productNo = sprintf("%s%02d", $prefix, $nextSeq);
            $stmtVerify->execute([$productNo]);
        }

        // ----------------------------------------------------
        // บันทึกลง SCRPPROD1
        // ----------------------------------------------------
        $sqlInsert = "INSERT INTO SCRPPROD1 (
                        PRODUCT_NO, SCRP_DATE, COIL_NO, MATERIAL_IN, LINE_PROCESS, PROCESS, 
                        SCRP_FROM, LOCATION_ID, ALLOY, SCRP_WIDTH, SCRP_WEIGHT, SCRP_REMARK, 
                        SCRP_LABEL, SCRP_OPERATOR, SCRP_OPERATEDATE, SCRP_STATUS
                      ) VALUES (?, GETDATE(), ?, ?, ?, ?, 'CO', ?, ?, 0, ?, ?, 1, ?, GETDATE(), 'OP')";
        $stmtInsert = $conn->prepare($sqlInsert);
        $stmtInsert->execute([
            $productNo,
            ($scrapType === 'S' ? $coilNo : ''),
            ($scrapType === 'S' ? $materialIn : ''),
            $lineProcess,
            $processCode,
            $toLocation,
            $alloy,
            $scrapWeight,
            $remark,
            $user_id
        ]);

        // ----------------------------------------------------
        // อัปเดต COILPROD1 (เฉพาะกรณี Type S)
        // ----------------------------------------------------
        if ($scrapType === 'S') {
            $sqlUpdateCoil = "UPDATE COILPROD1 
                              SET COIL_SCRAPWEIGHT = ?, COIL_BALANCEWEIGHT = ?, COIL_STATUS = ? 
                              WHERE COIL_NO = ?";
            $stmtUpdateCoil = $conn->prepare($sqlUpdateCoil);
            $stmtUpdateCoil->execute([
                $newCoilScrapWeight,
                $newCoilBalanceWeight,
                $newCoilStatus,
                $coilNo
            ]);
        }

        $conn->commit();

        echo json_encode([
            'status' => 'success',
            'product_no' => $productNo,
            'message' => 'บันทึกข้อมูลเรียบร้อยแล้ว'
        ]);

    } catch (Exception $e) {
        $conn->rollBack();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}

// ==========================================
// 2. ACTION: ลบข้อมูล (DELETE SCRAP)
// ==========================================
if ($action === 'delete') {
    $productNo   = trim($input['productNo'] ?? '');
    $coilNo      = trim($input['coilNo'] ?? '');
    $scrapWeight = floatval($input['scrapWeight'] ?? 0);

    try {
        $conn->beginTransaction();

        // 1. ลบจากตาราง SCRPPROD1
        $stmtDel = $conn->prepare("DELETE FROM SCRPPROD1 WHERE PRODUCT_NO = ?");
        $stmtDel->execute([$productNo]);

        // 2. คืนค่าน้ำหนักกลับเข้าคอยล์แม่ (ถ้ามี Coil อ้างอิง)
        if (!empty($coilNo)) {
            $stmtCoil = $conn->prepare("SELECT COIL_SCRAPWEIGHT, COIL_BALANCEWEIGHT FROM COILPROD1 WITH (UPDLOCK) WHERE COIL_NO = ?");
            $stmtCoil->execute([$coilNo]);
            $coil = $stmtCoil->fetch(PDO::FETCH_ASSOC);

            if ($coil) {
                $restoredScrapWeight   = max(0, floatval($coil['COIL_SCRAPWEIGHT']) - $scrapWeight);
                $restoredBalanceWeight = floatval($coil['COIL_BALANCEWEIGHT']) + $scrapWeight;

                $stmtRestore = $conn->prepare("UPDATE COILPROD1 
                                              SET COIL_SCRAPWEIGHT = ?, COIL_BALANCEWEIGHT = ?, COIL_STATUS = 'AC' 
                                              WHERE COIL_NO = ?");
                $stmtRestore->execute([$restoredScrapWeight, $restoredBalanceWeight, $coilNo]);
            }
        }

        $conn->commit();
        echo json_encode(['status' => 'success']);

    } catch (Exception $e) {
        $conn->rollBack();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}