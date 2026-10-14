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

if (empty($main_pno) || empty($combine_pno)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Parameters']);
    exit();
}

try {
    // ดึงข้อมูลรายการจาก CMBNPROD1
    $stmtCmbn = $conn->prepare("SELECT CMBN_WEIGHT, CMBN_PIECE FROM CMBNPROD1 WHERE PRODUCT_NO = :pno AND PRODUCT_REFERENCE = :ref");
    $stmtCmbn->execute([':pno' => $main_pno, ':ref' => $combine_pno]);
    $cmbnData = $stmtCmbn->fetch(PDO::FETCH_ASSOC);

    if (!$cmbnData) {
        echo json_encode(['status' => 'error', 'message' => 'ไม่พบรายการบันทึกการรวมในระบบ']);
        exit();
    }

    $cmbn_w = (float)($cmbnData['CMBN_WEIGHT'] ?? 0);
    $cmbn_p = (float)($cmbnData['CMBN_PIECE'] ?? 0);

    // ดึงข้อมูล Main Product
    $stmtMain = $conn->prepare("SELECT CRSH_ACTUALWEIGHT, CRSH_ACTUALPIECE, CRSH_COMBINEWEIGHT, CRSH_COMBINEPIECE FROM CRSHPROD1 WHERE PRODUCT_NO = :pno");
    $stmtMain->execute([':pno' => $main_pno]);
    $mainData = $stmtMain->fetch(PDO::FETCH_ASSOC);

    $conn->beginTransaction();

    // 1. คืนค่าน้ำหนัก Main Product
    $new_actual_weight  = max(0, (float)($mainData['CRSH_ACTUALWEIGHT'] ?? 0) - $cmbn_w);
    $new_actual_piece   = max(0, (float)($mainData['CRSH_ACTUALPIECE'] ?? 0) - $cmbn_p);
    $new_combine_weight = max(0, (float)($mainData['CRSH_COMBINEWEIGHT'] ?? 0) - $cmbn_w);
    $new_combine_piece  = max(0, (float)($mainData['CRSH_COMBINEPIECE'] ?? 0) - $cmbn_p);

    $updMain = $conn->prepare("UPDATE CRSHPROD1 SET CRSH_COMBINEWEIGHT = :cw, CRSH_COMBINEPIECE = :cp, CRSH_ACTUALWEIGHT = :aw, CRSH_ACTUALPIECE = :ap, CRSH_UPDATEDATE = GETDATE() WHERE PRODUCT_NO = :pno");
    $updMain->execute([
        ':cw'  => $new_combine_weight,
        ':cp'  => $new_combine_piece,
        ':aw'  => $new_actual_weight,
        ':ap'  => $new_actual_piece,
        ':pno' => $main_pno
    ]);

    // 2. ปรับสถานะชิ้นงานรองกลับเป็น 'AC'
    $updCmbn = $conn->prepare("UPDATE CRSHPROD1 SET CRSH_STATUS = 'AC', CRSH_UPDATEDATE = GETDATE() WHERE PRODUCT_NO = :pno");
    $updCmbn->execute([':pno' => $combine_pno]);

    // 3. ลบแถวออกจาก CMBNPROD1
    $delStmt = $conn->prepare("DELETE FROM CMBNPROD1 WHERE PRODUCT_NO = :pno AND PRODUCT_REFERENCE = :ref");
    $delStmt->execute([':pno' => $main_pno, ':ref' => $combine_pno]);

    $conn->commit();
    echo json_encode(['status' => 'success', 'message' => 'ยกเลิกการรวม Palette เรียบร้อยแล้ว']);

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>