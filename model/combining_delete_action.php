<?php
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header("Location: ../combining_process_mats.php");
    exit();
}

ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
include("../dbcon_mats-new.php");

$product_no = trim($_GET['productno'] ?? '');
$ref        = trim($_GET['ref'] ?? '');
$jno        = trim($_GET['jno'] ?? '');
$folder_func = trim($_GET['func'] ?? 'pd');

if (empty($product_no) || empty($ref)) {
    echo "<script>alert('Error: ข้อมูลไม่ครบถ้วนสำหรับการลบ'); window.history.back();</script>";
    exit();
}

try {
    $conn->beginTransaction();

    // 1. ดึงน้ำหนักของ Scrap ที่ต้องการลบจาก CMBNPROD1
    $stmt_cmbn = $conn->prepare("SELECT CMBN_WEIGHT FROM CMBNPROD1 WHERE PRODUCT_NO = ? AND PRODUCT_REFERENCE = ?");
    $stmt_cmbn->execute([$product_no, $ref]);
    $cmbn_data = $stmt_cmbn->fetch(PDO::FETCH_ASSOC);

    if (!$cmbn_data) {
        throw new Exception("ไม่พบข้อมูล Scrap รายการนี้ในระบบ Combine");
    }
    $scrap_weight = floatval($cmbn_data['CMBN_WEIGHT']);

    // 2. คืนสถานะ Scrap ใน SCRPPROD1 กลับเป็น 'OP'
    $conn->prepare("UPDATE SCRPPROD1 SET SCRP_STATUS = 'OP', SCRP_UPDATE = 'SYSTEM', SCRP_UPDATEDATE = GETDATE() WHERE PRODUCT_NO = ?")
         ->execute([$ref]);

    // 3. ลบรายการออกจาก CMBNPROD1
    $conn->prepare("DELETE FROM CMBNPROD1 WHERE PRODUCT_NO = ? AND PRODUCT_REFERENCE = ?")
         ->execute([$product_no, $ref]);

    // 4. ตรวจสอบว่าใน CMBNPROD1 ยังเหลือรายการอื่นใน Product No. นี้อีกหรือไม่
    $chk_left = $conn->prepare("SELECT COUNT(*) AS CNT FROM CMBNPROD1 WHERE PRODUCT_NO = ?");
    $chk_left->execute([$product_no]);
    $left_data = $chk_left->fetch(PDO::FETCH_ASSOC);

    if ($left_data['CNT'] == 0) {
        // ถ้าไม่เหลือรายการ Scrap แล้ว ให้ลบออกจาก CRSHPROD1 และ PACKPROD1 ด้วย
        $conn->prepare("DELETE FROM CRSHPROD1 WHERE PRODUCT_NO = ?")->execute([$product_no]);
        $conn->prepare("DELETE FROM PACKPROD1 WHERE PRODUCT_NO = ?")->execute([$product_no]);
    } else {
        // ถ้ายังเหลือ ให้ลดน้ำหนักใน CRSHPROD1 และ PACKPROD1 ลงตามน้ำหนักของ Scrap ที่ถูกลบออก
        $conn->prepare("UPDATE CRSHPROD1 SET 
            CRSH_ACTUALWEIGHT = CASE WHEN CRSH_ACTUALWEIGHT - ? < 0 THEN 0 ELSE CRSH_ACTUALWEIGHT - ? END,
            CRSH_PRODUCEWEIGHT = CASE WHEN CRSH_PRODUCEWEIGHT - ? < 0 THEN 0 ELSE CRSH_PRODUCEWEIGHT - ? END,
            CRSH_PACKWEIGHT = CASE WHEN CRSH_PACKWEIGHT - ? < 0 THEN 0 ELSE CRSH_PACKWEIGHT - ? END
            WHERE PRODUCT_NO = ?")
             ->execute([$scrap_weight, $scrap_weight, $scrap_weight, $scrap_weight, $scrap_weight, $scrap_weight, $product_no]);

        $conn->prepare("UPDATE PACKPROD1 SET 
            PACK_NETWEIGHT = CASE WHEN PACK_NETWEIGHT - ? < 0 THEN 0 ELSE PACK_NETWEIGHT - ? END
            WHERE PRODUCT_NO = ?")
             ->execute([$scrap_weight, $scrap_weight, $product_no]);
    }

    // 5. ลดน้ำหนักสะสมใน JOBORDER1 และ CSTMORDR2 ลงตามน้ำหนักที่ถูกลบ
    $conn->prepare("UPDATE JOBORDER1 SET 
        JOB_ACTUALWEIGHT = CASE WHEN JOB_ACTUALWEIGHT - ? < 0 THEN 0 ELSE JOB_ACTUALWEIGHT - ? END,
        JOB_PRODUCEWEIGHT = CASE WHEN JOB_PRODUCEWEIGHT - ? < 0 THEN 0 ELSE JOB_PRODUCEWEIGHT - ? END,
        JOB_PACKWEIGHT = CASE WHEN JOB_PACKWEIGHT - ? < 0 THEN 0 ELSE JOB_PACKWEIGHT - ? END,
        JOB_STOCKWEIGHT = CASE WHEN JOB_STOCKWEIGHT - ? < 0 THEN 0 ELSE JOB_STOCKWEIGHT - ? END
        WHERE JOB_ORDER = ?")
         ->execute([$scrap_weight, $scrap_weight, $scrap_weight, $scrap_weight, $scrap_weight, $scrap_weight, $scrap_weight, $scrap_weight, $jno]);

    // ดึง Sale Order มาลดด้วย (ถ้ามี)
    $stmt_so = $conn->prepare("SELECT SALEORDER_NO, SALEORDER_ITEM FROM JOBORDER1 WHERE JOB_ORDER = ?");
    $stmt_so->execute([$jno]);
    $so_data = $stmt_so->fetch(PDO::FETCH_ASSOC);
    if ($so_data && !empty($so_data['SALEORDER_NO'])) {
        $conn->prepare("UPDATE CSTMORDR2 SET 
            CTM2_ACTUALWEIGHT = CASE WHEN CTM2_ACTUALWEIGHT - ? < 0 THEN 0 ELSE CTM2_ACTUALWEIGHT - ? END,
            CTM2_PRODUCEWEIGHT = CASE WHEN CTM2_PRODUCEWEIGHT - ? < 0 THEN 0 ELSE CTM2_PRODUCEWEIGHT - ? END,
            CTM2_PACKWEIGHT = CASE WHEN CTM2_PACKWEIGHT - ? < 0 THEN 0 ELSE CTM2_PACKWEIGHT - ? END,
            CTM2_STOCKWEIGHT = CASE WHEN CTM2_STOCKWEIGHT - ? < 0 THEN 0 ELSE CTM2_STOCKWEIGHT - ? END
            WHERE SALEORDER_NO = ? AND SALEORDER_ITEM = ?")
             ->execute([$scrap_weight, $scrap_weight, $scrap_weight, $scrap_weight, $scrap_weight, $scrap_weight, $scrap_weight, $scrap_weight, $so_data['SALEORDER_NO'], $so_data['SALEORDER_ITEM']]);
    }

    $conn->commit();

    if ($left_data['CNT'] == 0) {
        header("Location: ../combining_process_mats.php?func=" . urlencode($folder_func) . "&tab=history");
    } else {
        header("Location: ../combining_entry_mats.php?func=" . urlencode($folder_func) . "&jno=" . urlencode($jno) . "&pno=" . urlencode($product_no));
    }
    exit();

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo "<h3 style='color:red;'>Database Error / Exception:</h3>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
    echo "<br><a href='javascript:history.back()'>[ ย้อนกลับ ]</a>";
}
?>