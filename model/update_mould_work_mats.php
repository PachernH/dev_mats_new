<?php
session_start();

if (!isset($_SESSION['ID'])) {
    header("Location: ../index.php");
    exit();
}

include("../dbcon_mats-new.php");

$mould_setup_no = isset($_POST['mould_setup_no']) ? trim($_POST['mould_setup_no']) : '';
$status_id = isset($_POST['status_id']) ? trim($_POST['status_id']) : '';
$folder_func = isset($_POST['func']) ? htmlspecialchars($_POST['func'], ENT_QUOTES, 'UTF-8') : 'mats';

if (empty($mould_setup_no) || empty($status_id)) {
    echo "<script>alert('STNDD-03: Missing required parameters.'); window.history.back();</script>";
    exit();
}

try {
    $conn->beginTransaction();

    // 1. ตรวจสอบเงื่อนไขตาม m1.txt (ถ้าสถานะเป็น WK เช็กข้อมูลจาก MLDSWORK1)
    if ($status_id === 'OP') {
        $stmt_check = $conn->prepare("SELECT * FROM MLDSWORK1 WHERE MOULDSETUP_NO = :mno");
        $stmt_check->execute([':mno' => $mould_setup_no]);
        if ($stmt_check->fetch()) {
            throw new Exception("There is real work. It can't change status.");
        }
    }

    // 2. อัปเดตตาราง MLDSTRNS2 ตามฟังก์ชัน UpdateMouldStatus
    $stmt_update = $conn->prepare("UPDATE MLDSTRNS2 SET MSTP_STATUS = :status_id WHERE MOULDSETUP_NO = :mno");
    $result = $stmt_update->execute([
        ':status_id' => $status_id,
        ':mno' => $mould_setup_no
    ]);

    if ($result) {
        $conn->commit();
        echo "<script>alert('STNDD-01: Update Successfully.'); window.location.href='../maintain_mould_setup_mats.php?func={$folder_func}&mno=" . urlencode($mould_setup_no) . "';</script>";
    } else {
        throw new Exception("STNDD-03: Update Failed.");
    }

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    $err_msg = $e->getMessage();
    echo "<script>alert('{$err_msg}'); window.history.back();</script>";
}
?>