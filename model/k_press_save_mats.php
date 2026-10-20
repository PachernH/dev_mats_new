<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

$iduser_func = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$line_func   = isset($_SESSION['LINE']) ? htmlspecialchars($_SESSION['LINE'], ENT_QUOTES, 'UTF-8') : 'K';

if (empty($iduser_func)) {
    echo json_encode(['status' => 'error', 'message' => 'Session หมดอายุ กรุณาเข้าสู่ระบบใหม่อีกครั้ง']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Request Method']);
    exit();
}

require_once "../dbcon_mats-new.php";

$job_order      = isset($_POST['job_order']) ? trim($_POST['job_order']) : '';
$coil_no        = isset($_POST['coil_no']) ? trim($_POST['coil_no']) : '';
$saleorder_no   = isset($_POST['saleorder_no']) ? trim($_POST['saleorder_no']) : '';
$saleorder_item = isset($_POST['saleorder_item']) ? trim($_POST['saleorder_item']) : '';

if (empty($job_order) || empty($coil_no)) {
    echo json_encode(['status' => 'error', 'message' => 'ข้อมูลไม่ครบถ้วน']);
    exit();
}

try {
    $conn->beginTransaction();

    $current_now = date('Y-m-d H:i:s');

    // 1. ลบรายการเดิมใน COILWORK1 ถ้ามีสำหรับ Line นี้
    $sql_del_cw = "DELETE FROM COILWORK1 WHERE COIL_NO = :coil_no AND LINE_PROCESS = :line_process";
    $stmt_del_cw = $conn->prepare($sql_del_cw);
    $stmt_del_cw->bindParam(':coil_no', $coil_no, PDO::PARAM_STR);
    $stmt_del_cw->bindParam(':line_process', $line_func, PDO::PARAM_STR);
    $stmt_del_cw->execute();

    // 2. Insert ล็อกม้วน Coil เข้ากับ Line K ใน COILWORK1
    $sql_cw = "INSERT INTO COILWORK1 (
                    COIL_NO, JOB_ORDER, SALEORDER_NO, SALEORDER_ITEM, MOULDSETUP_NO,
                    LINE_PROCESS, WORK_OPERATOR1, WORK_OPERATEDATE
               ) VALUES (
                    :coil_no, :job_order, :saleorder_no, :saleorder_item, '',
                    :line_process, :operator1, :operatedate
               )";

    $stmt_cw = $conn->prepare($sql_cw);
    $stmt_cw->bindParam(':coil_no', $coil_no, PDO::PARAM_STR);
    $stmt_cw->bindParam(':job_order', $job_order, PDO::PARAM_STR);
    $stmt_cw->bindParam(':saleorder_no', $saleorder_no, PDO::PARAM_STR);
    $stmt_cw->bindParam(':saleorder_item', $saleorder_item, PDO::PARAM_STR);
    $stmt_cw->bindParam(':line_process', $line_func, PDO::PARAM_STR);
    $stmt_cw->bindParam(':operator1', $iduser_func, PDO::PARAM_STR);
    $stmt_cw->bindParam(':operatedate', $current_now);
    $stmt_cw->execute();

    // 3. ปรับสถานะ COILPROD1 เป็น 'WK'
    $sql_up_coil = "UPDATE COILPROD1 SET COIL_STATUS = 'WK' WHERE COIL_NO = :coil_no";
    $stmt_up_coil = $conn->prepare($sql_up_coil);
    $stmt_up_coil->bindParam(':coil_no', $coil_no, PDO::PARAM_STR);
    $stmt_up_coil->execute();

    $conn->commit();

    echo json_encode([
        'status'  => 'success',
        'message' => 'ทำรายการ Map Coil สำหรับ Circle Press สำเร็จเรียบร้อยแล้ว',
        'jno'     => $job_order,
        'cno'     => $coil_no
    ]);

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
}
?>