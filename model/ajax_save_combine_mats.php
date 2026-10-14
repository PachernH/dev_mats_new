<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['ID'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

include("../dbcon_mats-new.php");

$main_pno    = isset($_POST['main_product_no']) ? trim($_POST['main_product_no']) : '';
$combine_pno = isset($_POST['combine_product_no']) ? trim($_POST['combine_product_no']) : '';
$operator    = $_SESSION['ID'] ?? 'SYSTEM';

// Validate 1: ตรวจสอบการกรอกข้อมูล
if (empty($main_pno) || empty($combine_pno)) {
    echo json_encode(['status' => 'error', 'message' => 'กรุณาระบุ Product No ให้ครบถ้วน']);
    exit();
}

// Validate 2: ห้ามรวมกับตัวเอง
if ($main_pno === $combine_pno) {
    echo json_encode(['status' => 'error', 'message' => 'ไม่สามารถนำ Product ตัวเองมารวมได้']);
    exit();
}

try {
    // ดึงข้อมูล Main Product (CRSHPROD1 + CSTMORDR2)
    $sqlMain = "SELECT c.*, j.TEMPER AS JOB_TEMPER, o.MATERIAL_IN AS CTM2_MATERIAL_IN 
                FROM CRSHPROD1 c 
                LEFT JOIN JOBORDER1 j ON c.JOB_ORDER = j.JOB_ORDER 
                LEFT JOIN CSTMORDR2 o ON c.SALEORDER_NO = o.SALEORDER_NO AND c.SALEORDER_ITEM = o.SALEORDER_ITEM 
                WHERE c.PRODUCT_NO = :pno";
    $stmtMain = $conn->prepare($sqlMain);
    $stmtMain->execute([':pno' => $main_pno]);
    $mainRow = $stmtMain->fetch(PDO::FETCH_ASSOC);

    if (!$mainRow) {
        echo json_encode(['status' => 'error', 'message' => "ไม่พบข้อมูลผลิตภัณฑ์หลัก ({$main_pno})"]);
        exit();
    }

    // ดึงข้อมูล Combine Product (CRSHPROD1)
    $sqlCmbn = "SELECT c.*, j.TEMPER AS JOB_TEMPER 
                FROM CRSHPROD1 c 
                LEFT JOIN JOBORDER1 j ON c.JOB_ORDER = j.JOB_ORDER 
                WHERE c.PRODUCT_NO = :pno";
    $stmtCmbn = $conn->prepare($sqlCmbn);
    $stmtCmbn->execute([':pno' => $combine_pno]);
    $cmbnRow = $stmtCmbn->fetch(PDO::FETCH_ASSOC);

    if (!$cmbnRow) {
        echo json_encode(['status' => 'error', 'message' => "ไม่พบข้อมูลผลิตภัณฑ์ที่จะนำมารวม ({$combine_pno})"]);
        exit();
    }

    // Validate 3: ตรวจสอบสถานะชิ้นงานรอง (ต้องเป็น 'AC')
    $cmbn_status = trim($cmbnRow['CRSH_STATUS'] ?? '');
    if ($cmbn_status !== 'AC') {
        echo json_encode(['status' => 'error', 'message' => "สถานะของผลิตภัณฑ์รองเป็น '{$cmbn_status}' (ต้องเป็น 'AC' เท่านั้น)"]);
        exit();
    }

    // Validate 4: ตรวจสอบ Job Order เดียวกัน
    if (trim($mainRow['JOB_ORDER']) !== trim($cmbnRow['JOB_ORDER'])) {
        echo json_encode(['status' => 'error', 'message' => "Job Order ไม่ตรงกัน (หลัก: {$mainRow['JOB_ORDER']} / รอง: {$cmbnRow['JOB_ORDER']})"]);
        exit();
    }

    // Validate 5: ตรวจสอบ Temper
    if (trim($mainRow['TEMPER']) !== trim($cmbnRow['TEMPER'])) {
        echo json_encode(['status' => 'error', 'message' => "Temper ไม่ตรงกัน (หลัก: {$mainRow['TEMPER']} / รอง: {$cmbnRow['TEMPER']})"]);
        exit();
    }

    // Validate 6: ตรวจสอบเงื่อนไข Material In (BOI Group Rules)
    $ctm2_mat_in = trim($mainRow['CTM2_MATERIAL_IN'] ?? '');
    $cmbn_mat_in = trim($cmbnRow['MATERIAL_IN'] ?? '');

    if (!empty($ctm2_mat_in) && !empty($cmbn_mat_in)) {
        $allowed = false;
        switch ($ctm2_mat_in) {
            case 'BOI-MAT1':
                $allowed = in_array($cmbn_mat_in, ['BOI-MAT1', 'BOI-MAT2', 'BOI-GRS1', 'BOI-GRS2', 'NONE-BOI']);
                break;
            case 'BOI-MAT2':
            case 'BOI-GRS1':
            case 'BOI-GRS2':
                $allowed = in_array($cmbn_mat_in, ['BOI-MAT2', 'BOI-GRS1', 'BOI-GRS2']);
                break;
            case 'BOI-IMP1':
            case 'BOI-IMP2':
            case 'NONE-BOI':
                $allowed = in_array($cmbn_mat_in, [$ctm2_mat_in, 'NONE-BOI']);
                break;
            default:
                $allowed = ($ctm2_mat_in === $cmbn_mat_in);
                break;
        }
        if (!$allowed) {
            echo json_encode(['status' => 'error', 'message' => "กลุ่ม Material In ไม่สอดคล้องกัน (หลัก: {$ctm2_mat_in} / รอง: {$cmbn_mat_in})"]);
            exit();
        }
    }

    // Validate 7: ตรวจสอบ Sub-Process Route (AN, TW, BA, PH, SH, CT, SC)
    $work_process = trim($cmbnRow['CRSH_WORKPROCESS'] ?? '');

    $checks = [
        'AN' => ['tbl' => 'ANNLPROD1', 'name' => 'Flash Annealing'],
        'TW' => ['tbl' => 'TPTPPROD1', 'name' => 'Transfer Product to Pallet'],
        'PH' => ['tbl' => 'PCHLPROD1', 'name' => 'Punch Hole'],
        'SH' => ['tbl' => 'SHRSPROD1', 'name' => 'Shear'],
        'CT' => ['tbl' => 'CTSHPROD1', 'name' => 'Cut Sheet'],
        'SC' => ['tbl' => 'STCHPROD1', 'name' => 'Stretcher']
    ];

    foreach ($checks as $code => $proc) {
        if (strpos($work_process, $code) !== false) {
            $chkStmt = $conn->prepare("SELECT COUNT(*) FROM {$proc['tbl']} WHERE PRODUCT_NO = :pno");
            $chkStmt->execute([':pno' => $combine_pno]);
            if ($chkStmt->fetchColumn() == 0) {
                echo json_encode(['status' => 'error', 'message' => "ชิ้นงานนี้ระบุให้ผ่านกระบวนการ {$proc['name']} ({$code}) แต่ไม่พบข้อมูลการผ่านงาน"]);
                exit();
            }
        }
    }

    // ตรวจสอบ BA (Batch Annealing)
    if (strpos($work_process, 'BA') !== false) {
        $chkBA = $conn->prepare("SELECT COUNT(*) FROM BTCHPROD1 WHERE PRODUCT_NO = :pno");
        $chkBA->execute([':pno' => $combine_pno]);
        if ($chkBA->fetchColumn() == 0) {
            echo json_encode(['status' => 'error', 'message' => "ชิ้นงานนี้ระบุให้ผ่านกระบวนการ Batch Annealing (BA) แต่ไม่พบข้อมูล Output"]);
            exit();
        }
    }

    // --- เริ่มกระบวนการ Transaction บันทึกข้อมูล ---
    $conn->beginTransaction();

    $cmbn_weight = (float)($cmbnRow['CRSH_ACTUALWEIGHT'] ?? 0);
    $cmbn_piece  = (float)($cmbnRow['CRSH_ACTUALPIECE'] ?? 0);

    // 1. Insert ตาราง CMBNPROD1
    $insStmt = $conn->prepare("INSERT INTO CMBNPROD1 (PRODUCT_NO, PRODUCT_REFERENCE, MATERIAL_IN, CMBN_DATE, CMBN_WEIGHT, CMBN_PIECE, CMBN_OPERATOR) VALUES (:pno, :ref, :mat, GETDATE(), :w, :p, :op)");
    $insStmt->execute([
        ':pno' => $main_pno,
        ':ref' => $combine_pno,
        ':mat' => $mainRow['MATERIAL_IN'] ?? '',
        ':w'   => $cmbn_weight,
        ':p'   => $cmbn_piece,
        ':op'  => $operator
    ]);

    // 2. คำนวณน้ำหนัก/จำนวนชิ้นรวมใหม่ของ Main Product
    $new_actual_weight  = (float)($mainRow['CRSH_ACTUALWEIGHT'] ?? 0) + $cmbn_weight;
    $new_actual_piece   = (float)($mainRow['CRSH_ACTUALPIECE'] ?? 0) + $cmbn_piece;
    $new_combine_weight = (float)($mainRow['CRSH_COMBINEWEIGHT'] ?? 0) + $cmbn_weight;
    $new_combine_piece  = (float)($mainRow['CRSH_COMBINEPIECE'] ?? 0) + $cmbn_piece;

    // Update ตาราง CRSHPROD1 ตัวหลัก
    $updMain = $conn->prepare("UPDATE CRSHPROD1 SET CRSH_COMBINEWEIGHT = :cw, CRSH_COMBINEPIECE = :cp, CRSH_ACTUALWEIGHT = :aw, CRSH_ACTUALPIECE = :ap, CRSH_UPDATE = :op, CRSH_UPDATEDATE = GETDATE() WHERE PRODUCT_NO = :pno");
    $updMain->execute([
        ':cw'  => $new_combine_weight,
        ':cp'  => $new_combine_piece,
        ':aw'  => $new_actual_weight,
        ':ap'  => $new_actual_piece,
        ':op'  => $operator,
        ':pno' => $main_pno
    ]);

    // 3. เปลี่ยนสถานะชิ้นงานรองใน CRSHPROD1 เป็น 'CB'
    $updCmbn = $conn->prepare("UPDATE CRSHPROD1 SET CRSH_STATUS = 'CB', CRSH_UPDATE = :op, CRSH_UPDATEDATE = GETDATE() WHERE PRODUCT_NO = :pno");
    $updCmbn->execute([
        ':op'  => $operator,
        ':pno' => $combine_pno
    ]);

    $conn->commit();
    echo json_encode(['status' => 'success', 'message' => 'บันทึกการรวม Palette เรียบร้อยแล้ว']);

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(['status' => 'error', 'message' => 'Database Error: ' . $e->getMessage()]);
}
?>