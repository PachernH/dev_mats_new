<?php
session_start();
include("../dbcon_mats-new.php");

$product_no = trim($_GET['product_no'] ?? '');
$folder_func = trim($_GET['func'] ?? '');
$user_id = $_SESSION['ID'] ?? 'SYSTEM';

if (empty($product_no)) {
    header("Location: ../packing_crsh_process_mats.php?func={$folder_func}");
    exit();
}

try {
    $conn->beginTransaction();

    // 1. ดึงข้อมูล Pack เดิมเพื่อนำค่าน้ำหนักไปถอนออก
    $stmt_p = $conn->prepare("SELECT TOP 1 * FROM PACKPROD1 WHERE PRODUCT_NO = :pno");
    $stmt_p->execute([':pno' => $product_no]);
    $p = $stmt_p->fetch(PDO::FETCH_ASSOC);

    if ($p) {
        $net_wt = (float)($p['PACK_NETWEIGHT'] ?? 0);
        $net_pc = (float)($p['PACK_NETPIECE'] ?? 0);
        $job_order = trim($p['JOB_ORDER'] ?? '');
        $so_no = trim($p['SALEORDER_NO'] ?? '');
        $so_item = trim($p['SALEORDER_ITEM'] ?? '');

        // 2. อัปเดต CRSHPROD1 กลับเป็นสถานะ 'AC' และล้างน้ำหนักแพ็ค
        $stmt_crsh = $conn->prepare("UPDATE CRSHPROD1 SET 
                                        CRSH_PACKWEIGHT = 0, 
                                        CRSH_PACKPIECE = 0, 
                                        CRSH_UPDATE = :usr, 
                                        CRSH_UPDATEDATE = GETDATE(), 
                                        PAPER_INTERLEAVE ='N',
                                        CRSH_STATUS = 'AC' 
                                     WHERE PRODUCT_NO = :pno");
        $stmt_crsh->execute([':usr' => $user_id, ':pno' => $product_no]);

        // 3. ถอนยอดน้ำหนักสะสมใน JOBORDER1
        if (!empty($job_order)) {
            $stmt_job = $conn->prepare("UPDATE JOBORDER1 SET 
                                            JOB_PACKWEIGHT = CASE WHEN (JOB_PACKWEIGHT - :wt) < 0 THEN 0 ELSE (JOB_PACKWEIGHT - :wt) END,
                                            JOB_PACKPIECE  = CASE WHEN (JOB_PACKPIECE - :pc) < 0 THEN 0 ELSE (JOB_PACKPIECE - :pc) END,
                                            JOB_STATUS     = 'OP' 
                                        WHERE JOB_ORDER = :job");
            $stmt_job->execute([':wt' => $net_wt, ':pc' => $net_pc, ':job' => $job_order]);
        }

        // 4. ถอนยอดน้ำหนักสะสมใน CSTMORDR2
        if (!empty($so_no)) {
            $stmt_so = $conn->prepare("UPDATE CSTMORDR2 SET 
                                           CTM2_PACKWEIGHT = CASE WHEN (CTM2_PACKWEIGHT - :wt) < 0 THEN 0 ELSE (CTM2_PACKWEIGHT - :wt) END,
                                           CTM2_PACKPIECE  = CASE WHEN (CTM2_PACKPIECE - :pc) < 0 THEN 0 ELSE (CTM2_PACKPIECE - :pc) END 
                                       WHERE SALEORDER_NO = :so AND SALEORDER_ITEM = :item");
            $stmt_so->execute([':wt' => $net_wt, ':pc' => $net_pc, ':so' => $so_no, ':item' => $so_item]);
        }

        // 5. ลบเรคอร์ดออกจาก PACKPROD1
        $stmt_del = $conn->prepare("DELETE FROM PACKPROD1 WHERE PRODUCT_NO = :pno");
        $stmt_del->execute([':pno' => $product_no]);
    }

    $conn->commit();
    echo "<script>alert('ลบข้อมูลการแพ็คเรียบร้อยแล้ว สถานะถูกคืนเป็น AC'); window.location.href='../packing_crsh_process_mats.php?func={$folder_func}';</script>";
} catch (Exception $e) {
    $conn->rollBack();
    echo "<script>alert('เกิดข้อผิดพลาดในการลบ: " . addslashes($e->getMessage()) . "'); window.history.back();</script>";
}