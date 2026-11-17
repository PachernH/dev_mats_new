<?php
session_start();
include("../dbcon_mats-new.php");

$folder_func = isset($_POST['func']) ? htmlspecialchars($_POST['func'], ENT_QUOTES, 'UTF-8') : 'mats';
$product_no  = isset($_POST['product_no']) ? trim($_POST['product_no']) : '';
$product_id  = isset($_POST['product_id']) ? trim($_POST['product_id']) : '';
$work_process = isset($_POST['work_process']) ? trim($_POST['work_process']) : '';
$next_process = isset($_POST['next_process']) ? trim($_POST['next_process']) : '';

if (empty($product_no)) {
    echo "<script>alert('Error: Product No. is missing.'); window.history.back();</script>";
    exit();
}

try {
    $conn->beginTransaction();

    // 1. อัปเดตตาราง CRSHPROD1 (เทียบเท่า UpdateWorkProcess สำหรับ CRSHPROD1)
    $sql_crsh = "UPDATE CRSHPROD1 SET CRSH_WORKPROCESS = :wp, CRSH_NEXTPROCESS = :np WHERE PRODUCT_NO = :pno";
    $stmt_crsh = $conn->prepare($sql_crsh);
    $stmt_crsh->execute([
        ':wp'  => $work_process,
        ':np'  => $next_process,
        ':pno' => $product_no
    ]);

    // 2. ตรวจสอบ Product ID และอัปเดตตารางที่เกี่ยวข้อง 
    if ($product_id === 'CO') {
        $sql_coil = "UPDATE COILPROD1 SET COIL_WORKPROCESS = :wp, COIL_NEXTPROCESS = :np WHERE COIL_NO = :pno";
        $stmt_coil = $conn->prepare($sql_coil);
        $stmt_coil->execute([
            ':wp'  => $work_process,
            ':np'  => $next_process,
            ':pno' => $product_no
        ]);
    }

    $conn->commit();

    echo "<script>
            alert('บันทึกข้อมูล Work Process สำเร็จแล้ว');
            window.location.href = '../maintain_ccsh_work_process_mats.php?func=" . urlencode($folder_func) . "';
          </script>";

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo "<script>
            alert('เกิดข้อผิดพลาดในการบันทึกข้อมูล: " . addslashes($e->getMessage()) . "');
            window.history.back();
          </script>";
}
?>