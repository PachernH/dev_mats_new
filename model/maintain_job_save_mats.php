<?php
// model/maintain_job_save_mats.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// 1. ตรวจสอบ Session
if (!isset($_SESSION['ID'])) {
    header("Location: ../index.php");
    exit();
}

// 2. Include ไฟล์การเชื่อมต่อ DB
require_once __DIR__ . '/../function_mats.php';
require_once __DIR__ . '/../dbcon_mats-new.php';

// 3. รับค่าจาก POST
$folder_func     = isset($_POST['func']) ? trim($_POST['func']) : '';
$txtJobOrderNo   = isset($_POST['txtJobOrderNo']) ? trim($_POST['txtJobOrderNo']) : '';
$txtSaleOrderNo  = isset($_POST['txtSaleOrderNo']) ? trim($_POST['txtSaleOrderNo']) : '';
$txtSaleOrderItem= isset($_POST['txtSaleOrderItem']) ? trim($_POST['txtSaleOrderItem']) : '';
$cmbJobStatus    = isset($_POST['cmbJobStatus']) ? trim($_POST['cmbJobStatus']) : 'OP';

$txtCutWidth     = isset($_POST['txtCutWidth']) ? (float)$_POST['txtCutWidth'] : 0;
$txtCutLength    = isset($_POST['txtCutLength']) ? (float)$_POST['txtCutLength'] : 0;
$txtCutWeightPiece = isset($_POST['txtCutWeightPiece']) ? (float)$_POST['txtCutWeightPiece'] : 0;

$cmbJOBMAXTolerance = isset($_POST['cmbJOBMAXTolerance']) ? (int)$_POST['cmbJOBMAXTolerance'] : 0;

$txtProdRemark1  = isset($_POST['txtProdRemark1']) ? strtoupper(trim($_POST['txtProdRemark1'])) : '';
$txtProdRemark2  = isset($_POST['txtProdRemark2']) ? strtoupper(trim($_POST['txtProdRemark2'])) : '';

$strOtherWidth   = (isset($_POST['chkOtherWidth']) && $_POST['chkOtherWidth'] === 'Y') ? 'Y' : 'N';
$strOperator     = isset($_SESSION['ID']) ? $_SESSION['ID'] : 'SYSTEM';

if (empty($txtJobOrderNo)) {
    die("<script>alert('Error: Job Order No. is empty!'); window.history.back();</script>");
}

try {
    if (!isset($conn)) {
        throw new Exception("Database connection variable (\$conn) is not defined.");
    }

    // เริ่ม Transaction
    $conn->beginTransaction();

    // -------------------------------------------------------------
    // คำสั่งที่ 1: UPDATE JOBORDER1 (ปรับชื่อ Column ตรงตาม Function VB)
    // -------------------------------------------------------------
    $sql1 = "UPDATE JOBORDER1 
             SET JOB_STATUS       = :cmbJobStatus,
                 OTHER_COILWIDTH  = :strOtherWidth,
                 CUT_WIDTH        = :txtCutWidth,
                 CUT_LENGTH       = :txtCutLength,
                 CUT_WEIGHTPIECE  = :txtCutWeightPiece,
                 JOB_MAXTOLERANCE = :cmbJOBMAXTolerance,
                 PRD_REMARK1      = :txtProdRemark1,
                 PRD_REMARK2      = :txtProdRemark2,
                 JOB_UPDATE       = :strOperator,
                 JOB_UPDATEDATE   = GETDATE()
             WHERE JOB_ORDER      = :txtJobOrderNo";

    $stmt1 = $conn->prepare($sql1);
    $res1 = $stmt1->execute([
        ':cmbJobStatus'       => $cmbJobStatus,
        ':strOtherWidth'      => $strOtherWidth,
        ':txtCutWidth'        => $txtCutWidth,
        ':txtCutLength'       => $txtCutLength,
        ':txtCutWeightPiece'  => $txtCutWeightPiece,
        ':cmbJOBMAXTolerance' => $cmbJOBMAXTolerance,
        ':txtProdRemark1'     => $txtProdRemark1,
        ':txtProdRemark2'     => $txtProdRemark2,
        ':strOperator'        => $strOperator,
        ':txtJobOrderNo'      => $txtJobOrderNo
    ]);

    // -------------------------------------------------------------
    // คำสั่งที่ 2: UPDATE CSTMORDR2 (ตาม Function VB)
    // -------------------------------------------------------------
    if (!empty($txtSaleOrderNo) && !empty($txtSaleOrderItem)) {
        $sql2 = "UPDATE CSTMORDR2 
                 SET CUT_WEIGHTPIECE = :txtCutWeightPiece 
                 WHERE SALEORDER_NO  = :txtSaleOrderNo 
                   AND SALEORDER_ITEM= :txtSaleOrderItem";

        $stmt2 = $conn->prepare($sql2);
        $stmt2->execute([
            ':txtCutWeightPiece' => $txtCutWeightPiece,
            ':txtSaleOrderNo'    => $txtSaleOrderNo,
            ':txtSaleOrderItem'  => $txtSaleOrderItem
        ]);
    }

    if ($res1) {
        // Commit Transaction เมื่อทำรายการสำเร็จ
        $conn->commit();
        
        echo "<script>
                alert('บันทึกข้อมูล Job Order " . addslashes($txtJobOrderNo) . " เรียบร้อยแล้ว');
                window.location.href = '../maintain_job_data_mats.php?func=" . urlencode($folder_func) . "&jno=" . urlencode($txtJobOrderNo) . "';
              </script>";
        exit();
    } else {
        $conn->rollBack();
        echo "<script>
                alert('เกิดข้อผิดพลาดในการบันทึกข้อมูล (STNDD-03)');
                window.history.back();
              </script>";
        exit();
    }

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    die("<h3>Database Save Error:</h3> " . htmlspecialchars($e->getMessage()));
}
?>